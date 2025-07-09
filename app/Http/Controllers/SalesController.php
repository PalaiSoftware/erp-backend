<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalesBill;
use App\Models\SalesItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Client;
use App\Models\Product;
use App\Models\SalesClient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use App\Models\PaymentMode;


class SalesController extends Controller
{

    public function store(Request $request)
    {
        Log::info('API endpoint reached', ['request' => $request->all()]);

        // Get the authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Restrict to rid 1, 2, 3, 4, or 5 only
        if (!in_array($user->rid, [1, 2, 3, 4, 5])) {
            return response()->json(['message' => 'Unauthorized to sale product'], 403);
        }

        // Use the cid from the authenticated user
        $cid = $user->cid;
        $uid = $user->id;

        // Validate the request data (no 'cid' in the request)
        try {
            $request->validate([
                'bill_name' => 'required|string',
                'customer_id' => 'required|integer|exists:sales_clients,id',
                'payment_mode' => 'required|integer|exists:payment_modes,id',
                'absolute_discount' => 'nullable|numeric|min:0',
                'total_paid' => 'required|numeric|min:0',
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|numeric|min:0',
                'products.*.discount' => 'nullable|numeric|min:0',
                'products.*.p_price' => 'required|numeric|min:0', // Added p_price
                'products.*.s_price' => 'required|numeric|min:0', // Replaced per_item_cost with s_price
                'products.*.unit_id' => 'required|integer|exists:units,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        // Extract product IDs from the request
        $productIds = array_column($request->products, 'product_id');

        // Fetch product details and current stock using query builder
        $stocks = DB::table('products as p')
            ->whereIn('p.id', $productIds)
            ->select(['p.id', 'p.name'])
            ->selectRaw("(
                SELECT COALESCE(SUM(pi.quantity), 0)
                FROM purchase_items pi
                JOIN purchase_bills pb ON pi.bid = pb.id
                JOIN purchase_clients pc ON pb.pcid = pc.id
                WHERE pi.pid = p.id AND pc.cid = ?
            ) - (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales_items si
                JOIN sales_bills sb ON si.bid = sb.id
                JOIN sales_clients sc ON sb.scid = sc.id
                WHERE si.pid = p.id AND sc.cid = ?
            ) as current_stock", [$cid, $cid])
            ->get()
            ->keyBy('id');

        // Check stock availability for each product
        $errors = [];
        foreach ($request->products as $product) {
            $productId = $product['product_id'];
            $requestedQuantity = $product['quantity'];

            if (!isset($stocks[$productId])) {
                $errors[] = [
                    'product_id' => $productId,
                    'product_name' => 'Unknown',
                    'current_stock' => 0,
                    'requested_stock' => $requestedQuantity
                ];
                continue;
            }

            $stock = $stocks[$productId];
            $currentStock = $stock->current_stock;

            if ($requestedQuantity > $currentStock) {
                $errors[] = [
                    'product_id' => $productId,
                    'product_name' => $stock->name,
                    'current_stock' => $currentStock,
                    'requested_stock' => $requestedQuantity
                ];
            }
        }

        // Return error response if stock check fails
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Stock check failed',
                'errors' => $errors
            ], 422);
        }

        // Proceed with the transaction if all stock checks pass
        DB::beginTransaction();
        try {
            $salesBill = SalesBill::create([
                'bill_name' => $request->bill_name,
                'scid' => $request->customer_id,
                'uid' => $user->id,
                'payment_mode' => $request->payment_mode,
                'absolute_discount' => $request->absolute_discount ?? 0,
                'paid_amount' => $request->total_paid,
            ]);
            $billId = $salesBill->id;
            Log::info('Created sales bill', ['bill_id' => $billId]);

            foreach ($request->products as $product) {
                $productId = $product['product_id'];

                // Fetch the latest purchase price for the product and company
                $latestPurchase = DB::table('purchase_items as pi')
                    ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
                    ->join('purchase_clients as pc', 'pb.pcid', '=', 'pc.id')
                    ->where('pi.pid', $productId)
                    ->where('pc.cid', $cid)
                    ->orderBy('pb.created_at', 'desc')
                    ->select('pi.p_price')
                    ->first();

                $p_price = $latestPurchase ? $latestPurchase->p_price : 0;

                SalesItem::create([
                    'bid' => $billId,
                    'pid' => $productId,
                    'p_price' => $product['p_price'], // Use p_price from request
                    's_price' => $product['s_price'], // Use s_price from request
                    'quantity' => $product['quantity'],
                    'unit_id' => $product['unit_id'],
                    'dis' => $product['discount'] ?? 0,
                ]);
            }

            DB::commit();
            Log::info('Sale recorded successfully', ['bill_id' => $billId]);

            return response()->json([
                'message' => 'Sale recorded successfully',
                'bill_id' => $billId,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Sale failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getAllInvoicesByCompany($cid)
    {
        // Get the authenticated user
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        $uid = $user->id;

        // Restrict access to users with rid between 5 and 10 inclusive
        if ($user->rid < 1 || $user->rid > 5) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Check if the user belongs to the requested company
        if ($user->cid != $cid) {
            return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
        }
    try {
        
        // Build the query with the purchase_bills table
        $query = DB::table('sales_bills as sb')
            ->select(
                'sb.id as transaction_id',          // Bill ID as transaction ID
                'sb.bill_name as bill_name',
                'sc.name as customer_name',           // Vendor name from purchase_clients
                'sb.scid as customer_id',             // Vendor ID from purchase_bills
                'sb.payment_mode',                  // Payment mode integer
                'sb.updated_at as date',            // Date of the transaction
                'u.name as sales_by'            // Name of the user who made the purchase
            )
            ->leftJoin('sales_clients as sc', 'sb.scid', '=', 'sc.id')  // Join with vendors
            ->leftJoin('users as u', 'sb.uid', '=', 'u.id')                // Join with users
            ->where('u.cid', $cid);                                         // Filter by company ID

        // Execute the query
        $transactions = $query->get();

        // Fetch payment modes from the database
        $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();

        // Map payment_mode from integer to string
        $transactions = $transactions->map(function ($transaction) use ($paymentModes) {
            $transaction->payment_mode = $paymentModes[$transaction->payment_mode] ?? 'Unknown';
            return $transaction;
        });

        // Handle empty results
        if ($transactions->isEmpty()) {
            Log::info('No transactions found for cid', ['cid' => $cid]);
            return response()->json([
                'status' => 'error',
                'message' => 'No transactions found for this customer ID'
            ], 404);
        }

        // Return successful response
        Log::info('Transactions retrieved successfully', ['cid' => $cid, 'count' => $transactions->count()]);
        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ], 200);
    } catch (\Exception $e) {
        Log::error('Failed to fetch transactions', ['cid' => $cid, 'error' => $e->getMessage()]);
        return response()->json(['message' => 'Failed to fetch transactions', 'error' => $e->getMessage()], 500);
    }
}
  
public function getTransaction($transactionId)
{
    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Role-based access control
    if ($user->rid < 1 || $user->rid > 5) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Fetch transaction details
    $transaction = DB::table('sales_bills')
        ->where('id', $transactionId)
        ->select('id', 'bill_name', 'scid', 'uid', 'payment_mode', 'absolute_discount', 'paid_amount', 'updated_at')
        ->first();

    if (!$transaction) {
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }

    // Default values for null fields
    $absoluteDiscount = $transaction->absolute_discount ?? 0;
    $paidAmount = $transaction->paid_amount ?? 0;

    // Fetch purchase items (products) with unit name
    $purchaseDetails = DB::table('sales_items as si')
        ->join('products as prod', 'si.pid', '=', 'prod.id')
        ->join('sales_bills as sb', 'si.bid', '=', 'sb.id')
        ->join('units as u', 'si.unit_id', '=', 'u.id')
        ->select(
            'si.pid as product_id',
            'prod.name as product_name',
            'si.s_price as s_price',
            'si.p_price as p_price',
            'si.dis as discount',
            'si.quantity',
            'si.unit_id',
            'u.name as unit_name',
            DB::raw('ROUND(si.quantity * (si.s_price * (1 - COALESCE(si.dis, 0)/100)), 2) AS per_product_total')
        )
        ->where('si.bid', $transactionId)
        ->get();

    if ($purchaseDetails->isEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'No sales details found for this transaction ID'
        ], 404);
    }

    // Fetch payment modes
    $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();

    // Map payment_mode for transaction
    $paymentModeName = $paymentModes[$transaction->payment_mode] ?? 'Unknown';

    // Calculate financials
    $totalAmount = $purchaseDetails->sum('per_product_total');
    $payableAmount = $totalAmount - $absoluteDiscount;
    $dueAmount = max(0, $payableAmount - $paidAmount);

    // Fetch vendor details
    $customer = DB::table('sales_clients')
        ->where('id', $transaction->scid) // Fixed from pcid to scid
        ->select('name as customer_name')
        ->first();

    // Fetch user details
    $userDetail = DB::table('users')
        ->where('id', $transaction->uid)
        ->select('name')
        ->first();

    // Return response
    return response()->json([
        'status' => 'success',
        'data' => [
            'products' => $purchaseDetails,
            'transaction_id' => $transaction->id,
            'bill_name' => $transaction->bill_name,
            'sales_by' => $userDetail ? $userDetail->name : 'Unknown',
            'customer_name' => $customer ? $customer->customer_name : 'Unknown',
            'customer_id' => $transaction->scid,
            'payment_mode' => $paymentModeName,
            'date' => $transaction->updated_at,
            'total_amount' => round($totalAmount, 2),
            'absolute_discount' => round($absoluteDiscount, 2),
            'payable_amount' => round($payableAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'due_amount' => round($dueAmount, 2),
        ]
    ], 200);
}

public function update(Request $request, $transactionId)
{
    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid between 1 and 4 inclusive
    if ($user->rid < 1 || $user->rid > 4) {
        return response()->json(['message' => 'You do not have permission to update transactions'], 403);
    }

    // Validation rules
    try {
        $request->validate([
            'bill_name' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|integer|exists:payment_modes,id',
            'customer_id' => 'nullable|integer|exists:sales_clients,id',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|integer|exists:products,id',
            'products.*.quantity' => 'required_with:products|numeric|min:0',
            'products.*.p_price' => 'required_with:products|numeric|min:0',
            'products.*.s_price' => 'required_with:products|numeric|min:0',
            'products.*.unit_id' => 'required_with:products|integer|exists:units,id',
            'products.*.dis' => 'nullable|numeric|min:0|max:100',
            'absolute_discount' => 'nullable|numeric|min:0',
            'set_paid_amount' => 'nullable|numeric|min:0',
            'updated_at' => 'nullable|date_format:Y-m-d H:i:s',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        \Log::error('Validation failed for updateTransactionById', [
            'errors' => $e->errors(),
            'request_data' => $request->all()
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    // Check if the transaction exists
    $transaction = SalesBill::where('id', $transactionId)->first();
    if (!$transaction) {
        \Log::info('Transaction not found', ['transaction_id' => $transactionId]);
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }

    // Start a database transaction
    DB::beginTransaction();
    try {
        // Prepare update data for sales_bills
        $updateData = [
            'updated_at' => $request->input('updated_at', now()),
            'created_at' => $request->input('updated_at', now()),
            'bill_name' => $request->input('bill_name', $transaction->bill_name),
            'scid' => $request->input('customer_id', $transaction->scid),
            'payment_mode' => $request->input('payment_mode', $transaction->payment_mode),
            'absolute_discount' => $request->input('absolute_discount', $transaction->absolute_discount),
            'paid_amount' => $request->input('set_paid_amount', $transaction->paid_amount),
        ];

        // Update sales_bills
        SalesBill::where('id', $transactionId)->update($updateData);

        // Handle products if provided
        if ($request->has('products')) {
            $products = $request->input('products', []);
            $productIds = array_column($products, 'product_id');

            // Fetch existing sales items
            $existingItems = DB::table('sales_items')
                ->where('bid', $transactionId)
                ->get(['pid', 'bid']);

            // Products to remove
            $existingProductIds = $existingItems->pluck('pid')->toArray();
            $productIdsToRemove = array_diff($existingProductIds, $productIds);

            // Remove products not in request
            if (!empty($productIdsToRemove)) {
                DB::table('sales_items')
                    ->where('bid', $transactionId)
                    ->whereIn('pid', $productIdsToRemove)
                    ->delete();
            }

            // Insert or update products
            foreach ($products as $product) {
                $item = DB::table('sales_items')
                    ->where('bid', $transactionId)
                    ->where('pid', $product['product_id'])
                    ->first();

                if ($item) {
                    // Update existing item
                    DB::table('sales_items')
                        ->where('bid', $transactionId)
                        ->where('pid', $product['product_id'])
                        ->update([
                            'p_price' => $product['p_price'],
                            's_price' => $product['s_price'],
                            'quantity' => $product['quantity'],
                            'unit_id' => $product['unit_id'],
                            'dis' => $product['dis'] ?? 0,
                        ]);
                } else {
                    // Insert new item
                    DB::table('sales_items')->insert([
                        'bid' => $transactionId,
                        'pid' => $product['product_id'],
                        'p_price' => $product['p_price'],
                        's_price' => $product['s_price'],
                        'quantity' => $product['quantity'],
                        'unit_id' => $product['unit_id'],
                        'dis' => $product['dis'] ?? 0,
                    ]);
                }
            }
        }

        DB::commit();
        \Log::info('Transaction updated successfully', ['transaction_id' => $transactionId]);
        return response()->json([
            'status' => 'success',
            'message' => 'Transaction updated successfully'
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Failed to update transaction', [
            'transaction_id' => $transactionId,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update transaction',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getTotalSaleAmount($cid)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    $rid = $user->rid;
    $uid = $user->id;

    // Check if the user belongs to the requested company
    if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }

    try {
        // Subquery to calculate item totals and absolute discount per bill
        $subQuery = DB::table('sales_items as si')
            ->join('sales_bills as sb', 'si.bid', '=', 'sb.id')
            ->join('users as u', 'sb.uid', '=', 'u.id')
            ->where('u.cid', $cid)
            ->when(!in_array($rid, [1,2,3]), function ($query) use ($uid) {
                $query->where('sb.uid', $uid);
            })
            ->select('sb.id')
            ->selectRaw('
                SUM(
                    si.quantity * si.s_price * (1 - si.dis / 100)
                ) as item_total
            ')
            ->selectRaw('sb.absolute_discount')
            ->groupBy('sb.id');

        // Grand Total: Sum (item_total - absolute_discount) across all bills
        $grandTotal = DB::table(DB::raw("({$subQuery->toSql()}) as per_transaction"))
            ->mergeBindings($subQuery)
            ->sum(DB::raw('item_total - absolute_discount'));

        // Total Sale Orders
        $totalSaleOrder = SalesBill::where(function ($query) use ($cid, $rid, $uid) {
            if (in_array($rid, [1,2,3])) {
                $query->whereIn('uid', function ($subQuery) use ($cid) {
                    $subQuery->select('id')
                             ->from('users')
                             ->where('cid', $cid);
                });
            } else {
                $query->where('uid', $uid);
            }
        })->count();

        // Total Customers (distinct scid)
        $distinctCustomers = SalesBill::where(function ($query) use ($cid, $rid, $uid) {
        if (in_array($rid, [1,2,3])) {
        $query->whereIn('uid', function ($subQuery) use ($cid) {
            $subQuery->select('id')
                     ->from('users')
                     ->where('cid', $cid);
        });
        } else {
           $query->where('uid', $uid);
        }
})
->whereExists(function ($query) {
    $query->select(DB::raw(1))
          ->from('sales_items')
          ->whereRaw('sales_items.bid = sales_bills.id');
})
->select('scid')
->distinct()
->get()
->count();

        return response()->json([
            'grand_total' => (float) $grandTotal,
            'total_sale_order' => $totalSaleOrder,
            'total_customer' => $distinctCustomers
        ], 200);

    } catch (\Exception $e) {
        Log::error('Sales widget error', [
            'cid' => $cid,
            'user_id' => $uid,
            'error' => $e->getMessage()
        ]);
        return response()->json(['error' => 'Failed to retrieve sales data'], 500);
    }
}

//     public function generateInvoice($transactionId)
//             {
//                 Log::info("Generating invoice for transaction_id: {$transactionId}");

//                 // Get the authenticated user
//                 $user = Auth::user();
//                 if (!$user) {
//                     return response()->json(['message' => 'Unauthorized'], 401);
//                 }

//             // Restrict access to users with rid between 5 and 10 inclusive
//             if ($user->rid < 5 || $user->rid > 10) {
//                 return response()->json(['message' => 'Forbidden'], 403);
//             }
//             $paymentModes = [
//                 1 => 'Debit Card',
//                 2 => 'Credit Card',
//                 3 => 'Cash',
//                 4 => 'UPI',
//                 5 => 'Bank Transfer',
//                 6 => 'phonepe',
//             ];
//                 try {
//                     $transaction = TransactionSales::findOrFail($transactionId);
//                     $sales = Sale::where('transaction_id', $transactionId)->with('salesItem.unit')->get();
//                     if ($sales->isEmpty()) {
//                         Log::warning("No sales records found for transaction_id: {$transactionId}");
//                         return response()->json(['message' => 'No sales records found for this transaction'], 404);
//                     }

//                     $customer = Customer::find($transaction->customer_id);
//                     $company = Company::find($transaction->cid);
//                     $userDetails = User::find($transaction->uid);

//                     $invoice = [
//                         'number' => 'INV-' . $transactionId,
//                         'date' => Carbon::parse($transaction->created_at)->format('Y-m-d'),
//                     ];

//                     $items = [];
//                     $totalAmount = 0;
//                     foreach ($sales as $sale) {
//                         $product = Product::find($sale->product_id);
//                         $salesItem = $sale->salesItem;

//                     if ($salesItem) {
//                             // $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
//                         // $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost * (1 - $salesItem->discount / 100));
//                         // $itemTotal = ($salesItem->quantity * $salesItem->per_item_cost * (1 - $salesItem->discount / 100)) - $salesItem->flat_discount;
//                         // $itemTotal = $salesItem->quantity * ((($salesItem->per_item_cost- $salesItem->flat_discount) * (1 - $salesItem->discount / 100)) );   
//                         $itemTotal = $salesItem->quantity * (($salesItem->per_item_cost * (1 - $salesItem->discount / 100)) - $salesItem->flat_discount);
//                         $items[] = [
//                                 'product_name' => $product ? $product->name : 'Unknown Product',
//                                 'quantity' => $salesItem->quantity,
//                                 'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A', // Add unit name
//                                 'per_item_cost' => $salesItem->per_item_cost,
//                                 'discount' => $salesItem->discount,
//                                 'flat_discount' => $salesItem->flat_discount,
//                                 'total' => $itemTotal,
//                             ];
//                             $totalAmount += $itemTotal;
//                         }
//                     }
//                     Log::info('Invoice items prepared', ['items' => $items, 'total_amount' => $totalAmount]);
                    
//                      // ✅ Apply global absolute discount
//         $absoluteDiscount = $transaction->absolute_discount ?? 0;
//         $totalAmount -= $absoluteDiscount;

//         // ✅ Calculate due amount
//         $dueAmount = max(0, $totalAmount - $transaction->total_paid);

//         Log::info('Final invoice totals', [
//             'total_amount' => $totalAmount,
//             'absolute_discount' => $absoluteDiscount,
//             'total_paid' => $transaction->total_paid,
//             'due_amount' => $dueAmount
//         ]);
       
//                     $data = [
//                         'invoice' => (object) $invoice,
//                         'transaction' => $transaction,
//                         'items' => $items,
//                         'total_amount' => $totalAmount,
//                         'due_amount' => $dueAmount,     // Final due amount
//                         'company' => $company,
//                         'customer' => $customer,
//                         'userDetails' => $userDetails,
//                         'payment_mode' => $paymentModes[$transaction->payment_mode] ?? 'Unknown',

//                     ];

//                     $pdf = Pdf::loadView('invoices.invoice', $data);
//                     return response($pdf->output())
//                         ->header('Content-Type', 'application/pdf')
//                         ->header('Content-Disposition', "inline; filename=\"invoice_{$transactionId}.pdf\"");
//                 } catch (\Exception $e) {
//                     Log::error('Invoice generation failed', [
//                         'transaction_id' => $transactionId,
//                         'error' => $e->getMessage(),
//                         'trace' => $e->getTraceAsString()
//                     ]);
//                     return response()->json(['message' => 'Error generating invoice', 'error' => $e->getMessage()], 500);
//                 }
// }

public function generateInvoice($transactionId)
{
    Log::info("Generating invoice for transaction_id: {$transactionId}");

    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid between 1 and 5 inclusive
    if ($user->rid < 1 || $user->rid > 5) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    try {
        // Fetch transaction from sales_bills
        $transaction = SalesBill::findOrFail($transactionId);

        // Fetch sales items with unit
        $sales = SalesItem::where('bid', $transactionId)->with('unit')->get();
        if ($sales->isEmpty()) {
            Log::warning("No sales records found for transaction_id: {$transactionId}");
            return response()->json(['message' => 'No sales records found for this transaction'], 404);
        }
        $userDetails = User::find($transaction->uid);
        if (!$userDetails) {
       // Agar user nahi mila toh error handle karo
         Log::error('User not found for uid: ' . $transaction->uid);
          return response()->json(['message' => 'User not found for this transaction'], 404);
        } 

        // Company ID user se lo
$cid = $userDetails->cid;

// Company fetch karo
$company = Client::find($cid);
if (!$company) {
    // Agar company nahi mili toh error handle karo
    Log::error('Company not found for cid: ' . $cid);
    return response()->json(['message' => 'Company not found for this transaction'], 404);
}

        // Fetch customer from sales_clients
        $customer = SalesClient::find($transaction->scid);

        // Fetch payment modes from the payment_modes table
        $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();

        $invoice = [
            'number' => 'INV-' . $transactionId,
            'date' => Carbon::parse($transaction->created_at)->format('Y-m-d'),
        ];

        $items = [];
        $totalAmount = 0;
        foreach ($sales as $sale) {
            $product = Product::find($sale->pid);
            $salesItem = $sale;

            if ($salesItem) {
                // Calculate item total without flat_discount
                $itemTotal = $salesItem->quantity * ($salesItem->s_price * (1 - $salesItem->dis / 100));
                $items[] = [
                    'product_name' => $product ? $product->name : 'Unknown Product',
                    'quantity' => $salesItem->quantity,
                    'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A',
                    'per_item_cost' => $salesItem->s_price,
                    'discount' => $salesItem->dis,
                    'total' => $itemTotal,
                ];
                $totalAmount += $itemTotal;
            }
        }

        Log::info('Invoice items prepared', ['items' => $items, 'total_amount' => $totalAmount]);

        // Apply global absolute discount
        $absoluteDiscount = $transaction->absolute_discount ?? 0;
        $totalAmount -= $absoluteDiscount;

        // Calculate due amount
        $dueAmount = max(0, $totalAmount - $transaction->paid_amount);

        Log::info('Final invoice totals', [
            'total_amount' => $totalAmount,
            'absolute_discount' => $absoluteDiscount,
            'total_paid' => $transaction->paid_amount,
            'due_amount' => $dueAmount
        ]);

        $data = [
            'invoice' => (object) $invoice,
            'transaction' => $transaction,
            'items' => $items,
            'total_amount' => $totalAmount,
            'due_amount' => $dueAmount,
            'company' => $company,
            'customer' => $customer,
            'userDetails' => $userDetails,
            'payment_mode' => $paymentModes[$transaction->payment_mode] ?? 'Unknown',
        ];

        $pdf = Pdf::loadView('invoices.invoice', $data);
        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"invoice_{$transactionId}.pdf\"");
    } catch (\Exception $e) {
        Log::error('Invoice generation failed', [
            'transaction_id' => $transactionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => 'Error generating invoice', 'error' => $e->getMessage()], 500);
    }
}
private function getInvoiceData($transactionId)
{
    $transaction = TransactionSales::findOrFail($transactionId);
    $sales = Sale::where('transaction_id', $transactionId)
                 ->with('salesItem.unit')
                 ->get();

    $customer = Customer::find($transaction->customer_id);
    // Fetch only required user fields
    $user = User::select('name', 'mobile')->find($transaction->uid);

    $invoice = [
        'number' => 'INV-' . $transactionId,
        'date' => Carbon::parse($transaction->updated_at)->format('Y-m-d'),
    ];
    $paymentModes = [
        1 => 'debit_card',
        2 => 'credit_card',
        3 => 'cash',
        4 => 'upi',
        5 => 'bank_transfer',
        6 => 'phonepe',
    ];
    $items = [];
    $totalAmount = 0;
    foreach ($sales as $sale) {
        $product = Product::find($sale->product_id);
        $salesItem = $sale->salesItem;
        if ($salesItem) {
            // $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
            // $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost * (1 - $salesItem->discount / 100));
            // $itemTotal = ($salesItem->quantity * $salesItem->per_item_cost * (1 - $salesItem->discount / 100)) - $salesItem->flat_discount;
            // $itemTotal = $salesItem->quantity * (($salesItem->per_item_cost * (1 - $salesItem->discount / 100)) - $salesItem->flat_discount);
            $itemTotal = $salesItem->quantity * (($salesItem->per_item_cost * (1 - $salesItem->discount / 100)) - $salesItem->flat_discount);

            $items[] = [
                'product_name' => $product ? $product->name : 'Unknown Product',
                'quantity' => $salesItem->quantity,
                'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A',
                'per_item_cost' => $salesItem->per_item_cost,
                'flat_discount' => $salesItem->flat_discount,
                'discount' => $salesItem->discount,
                'total' => $itemTotal,
            ];
            $totalAmount += $itemTotal;
        }
    }
    $transactionData = $transaction->toArray();
    $transactionData['payment_mode'] = $paymentModes[$transaction->payment_mode] ?? 'Unknown';

    return [
        'invoice' => (object) $invoice,
        'transaction' => $transactionData,
        'items' => $items,
        'total_amount' => $totalAmount,
        'customer' => $customer,
        'user_name' => $user->name,
        'user_phone' => $user->mobile,
    ];
}

public function getCustomerStats(Request $request)
{
    // Validate request
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    $validated = $request->validate(['cid' => 'required|integer']);
    $cid = $validated['cid'];

    // Get customer stats using direct table joins
    $customers = DB::table('customers')
        ->join('transaction_sales', 'customers.id', '=', 'transaction_sales.customer_id')
        ->leftJoin('sales', 'transaction_sales.id', '=', 'sales.transaction_id')
        ->leftJoin('sales_items', 'sales.id', '=', 'sales_items.sale_id')
        ->where('transaction_sales.cid', $cid)
        ->select(
            'customers.name',
            'customers.email',
            'customers.phone',
            'customers.address',
            DB::raw('MAX(transaction_sales.created_at) as last_purchase'),
            DB::raw('COUNT(DISTINCT transaction_sales.id) as purchase_count'),
            DB::raw('COALESCE(SUM(sales_items.quantity * sales_items.per_item_cost), 0) as total_transactions')
        )
        ->groupBy(
            'customers.id',
            'customers.name',
            'customers.email',
            'customers.phone',
            'customers.address'
        )
        ->get();

    return response()->json($customers);
}

    public function destroy(Request $request, $transactionId)
    {
        Log::info('Delete API endpoint reached', [
            'transaction_id' => $transactionId,
        ]);
    
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
    
        if (!in_array($user->rid, [5, 6])) {
            return response()->json(['message' => 'Unauthorized to delete transaction'], 403);
        }
    
        $transaction = TransactionSales::where('id', $transactionId)
            ->where('uid', $user->id)
            ->first();
    
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found or unauthorized'], 404);
        }
    
        DB::beginTransaction();
        try {
            // Delete related SalesItems and Sales
            $sales = Sale::where('transaction_id', $transactionId)->get();
    
            foreach ($sales as $sale) {
                SalesItem::where('sale_id', $sale->id)->delete();
                $sale->delete();
            }
    
            // Delete the transaction
            $transaction->delete();
    
            DB::commit();
    
            Log::info('Transaction deleted successfully', [
                'transaction_id' => $transactionId,
            ]);
    
            return response()->json([
                'message' => 'Transaction deleted successfully',
                'transaction_id' => $transactionId,
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Transaction deletion failed', [
                'error' => $e->getMessage(),
                'transaction_id' => $transactionId,
            ]);
    
            return response()->json([
                'message' => 'Transaction deletion failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}