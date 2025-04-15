<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SalesItem;
use App\Models\TransactionSales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
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

        // Restrict to rid 5, 6, 7,8 or 9 only
        if (!in_array($user->rid, [5, 6, 7,8,9])) {
           return response()->json(['message' => 'Unauthorized to sale product'], 403);
        }
    
    
        try {
            $request->validate([
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.discount' => 'nullable|numeric|min:0',
                'products.*.per_item_cost' => 'required|numeric|min:0',
                'products.*.unit_id' => 'required|integer|exists:units,id',
                'cid' => 'required|integer',
                'customer_id' => 'required|integer',
                'payment_mode' => 'required|string|max:50',
                'updated_at' => 'nullable|date', // Add validation for updated_at

            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    
        $uid = $user->id;
        $cid = (int)$request->cid;
    
        // Extract product IDs from the request
        $productIds = array_column($request->products, 'product_id');
    
        // Fetch product details and current stock in one query
        $stocks = DB::table('products as p')
            ->whereIn('p.id', $productIds)
            // ->where('p.uid', $uid)
            ->select([
                'p.id',
                'p.name',
                DB::raw("(
                    SELECT COALESCE(SUM(pi.quantity), 0)
                    FROM purchases pur
                    JOIN transaction_purchases tp ON pur.transaction_id = tp.id
                    JOIN purchase_items pi ON pur.id = pi.purchase_id
                    WHERE pur.product_id = p.id AND tp.cid = $cid
                ) - (
                    SELECT COALESCE(SUM(si.quantity), 0)
                    FROM sales s
                    JOIN transaction_sales ts ON s.transaction_id = ts.id
                    JOIN sales_items si ON s.id = si.sale_id
                    WHERE s.product_id = p.id AND ts.cid = $cid
                ) as current_stock")
            ])
            ->get()
            ->keyBy('id'); // Key by product ID for efficient lookup
    
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
            $updatedAt = $request->has('updated_at') ? Carbon::parse($request->updated_at) : now();
            $transaction = TransactionSales::create([
                'uid' => $user->id,
                'cid' => $request->cid,
                'customer_id' => $request->customer_id,
                'payment_mode' => $request->payment_mode,
                'updated_at' => $updatedAt, // Use the manually passed updated_at value
            ]);
            $transactionId = $transaction->id;
            Log::info('Created transaction', ['transaction_id' => $transactionId]);
    
            foreach ($request->products as $product) {
                $sale = Sale::create([
                    'transaction_id' => $transactionId,
                    'product_id' => $product['product_id'],
                ]);
                $saleId = $sale->id;
                Log::info('Created sale', ['sale_id' => $saleId]);
    
                SalesItem::create([
                    'sale_id' => $saleId,
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
                    'unit_id' => $product['unit_id'],
                ]);
            }
    
            DB::commit();
            Log::info('Sale recorded successfully', ['transaction_id' => $transactionId]);
    
            return response()->json([
                'message' => 'Sale recorded successfully',
                'transaction_id' => $transactionId,
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
    public function generateInvoice($transactionId)
{
    Log::info("Generating invoice for transaction_id: {$transactionId}");

      // Get the authenticated user
       $user = Auth::user();
      if (!$user) {
           return response()->json(['message' => 'Unauthorized'], 401);
       }

   // Restrict access to users with rid between 5 and 10 inclusive
   if ($user->rid < 5 || $user->rid > 10) {
       return response()->json(['message' => 'Forbidden'], 403);
   }

    try {
        $transaction = TransactionSales::findOrFail($transactionId);
        $sales = Sale::where('transaction_id', $transactionId)->with('salesItem.unit')->get();
        if ($sales->isEmpty()) {
            Log::warning("No sales records found for transaction_id: {$transactionId}");
            return response()->json(['message' => 'No sales records found for this transaction'], 404);
        }

        $customer = Customer::find($transaction->customer_id);
        $company = Company::find($transaction->cid);
        $userDetails = User::find($transaction->uid);

        $invoice = [
            'number' => 'INV-' . $transactionId,
            'date' => Carbon::parse($transaction->created_at)->format('Y-m-d'),
        ];

        $items = [];
        $totalAmount = 0;
        foreach ($sales as $sale) {
            $product = Product::find($sale->product_id);
            $salesItem = $sale->salesItem;

            if ($salesItem) {
                // $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
                $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost * (1 - $salesItem->discount / 100));

                $items[] = [
                    'product_name' => $product ? $product->name : 'Unknown Product',
                    'quantity' => $salesItem->quantity,
                    'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A', // Add unit name
                    'per_item_cost' => $salesItem->per_item_cost,
                    'discount' => $salesItem->discount,
                    'total' => $itemTotal,
                ];
                $totalAmount += $itemTotal;
            }
        }
        Log::info('Invoice items prepared', ['items' => $items, 'total_amount' => $totalAmount]);

        $data = [
            'invoice' => (object) $invoice,
            'transaction' => $transaction,
            'items' => $items,
            'total_amount' => $totalAmount,
            'company' => $company,
            'customer' => $customer,
            'userDetails' => $userDetails,
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

    $items = [];
    $totalAmount = 0;
    foreach ($sales as $sale) {
        $product = Product::find($sale->product_id);
        $salesItem = $sale->salesItem;
        if ($salesItem) {
            // $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
            $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost * (1 - $salesItem->discount / 100));
            $items[] = [
                'product_name' => $product ? $product->name : 'Unknown Product',
                'quantity' => $salesItem->quantity,
                'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A',
                'per_item_cost' => $salesItem->per_item_cost,
                'discount' => $salesItem->discount,
                'total' => $itemTotal,
            ];
            $totalAmount += $itemTotal;
        }
    }

    return [
        'invoice' => (object) $invoice,
        'transaction' => $transaction,
        'items' => $items,
        'total_amount' => $totalAmount,
        'customer' => $customer,
        // Include only required user fields
        'user_name' => $user->name,
        'user_phone' => $user->mobile,
    ];
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
            if ($user->rid < 5 || $user->rid > 10) {
                return response()->json(['message' => 'Forbidden'], 403);
            }

            try {

                if($user->rid ==5){
                    $transactionIds = TransactionSales::where('cid', $cid)->orderBy('id', 'asc')->pluck('id')->toArray();
                }
                else if($user->rid ==6){
                    $uids = User::where('rid', '>', 6)->pluck('id')->push($user->id)->unique()->toArray();
                    $transactionIds = TransactionSales::where('cid', $cid)->whereIn('uid', $uids)->orderBy('id', 'asc')->pluck('id')->toArray();
                }else{
                    $transactionIds = TransactionSales::where('cid', $cid)->where('uid', $uid)->orderBy('id', 'asc')->pluck('id')->toArray();
                }
                $invoices = [];

                foreach ($transactionIds as $tid) {
                    $invoices[] = $this->getInvoiceData($tid);
                }

                return response()->json(['invoices' => $invoices]);
            } catch (\Exception $e) {
                Log::error('Failed to fetch invoices', ['cid' => $cid, 'error' => $e->getMessage()]);
                return response()->json(['error' => 'Failed to fetch invoices'], 500);
            }
        }



// public function getTotalSaleAmount($cid)
// {
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     try {
//         $transactionIds = TransactionSales::where('cid', $cid)->pluck('id')->toArray();
//         $invoices = [];
//         $grandTotal = 0;
//         $distinctCustomers = TransactionSales::where('cid', $cid)
//         ->distinct('customer_id')
//         ->count('customer_id');   
//         foreach ($transactionIds as $tid) {
//             $invoiceData = $this->getInvoiceData($tid);
//             $invoices[] = $invoiceData;
//             $grandTotal += $invoiceData['total_amount']; // Add each invoice's total_amount
//         }
//         $transactionCount = count($invoices); // Number of invoices
//         return response()->json([
//             // 'invoices' => $invoices,
//             'grand_total' => $grandTotal, // Include the sum in the response
//             'total_sale_order' => $transactionCount,
//             'total_customer' => $distinctCustomers
//         ]);
//     } catch (\Exception $e) {
//         Log::error('Failed to fetch invoices', ['cid' => $cid, 'error' => $e->getMessage()]);
//         return response()->json(['error' => 'Failed to fetch invoices'], 500);
//     }
// }

    public function getTotalSaleAmount($cid)
        {
            // Authentication check
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Validate request data
            // $validated = $request->validate([
            //     'cid' => 'required|integer|exists:companies,id'
            // ]);
            // $cid = $validated['cid'];
            $rid = $user->rid;
            $uid = $user->id;

            try {
                // Grand Total Calculation
                $grandTotal = DB::table('sales_items')
                    ->join('sales', 'sales_items.sale_id', '=', 'sales.id')
                    ->join('transaction_sales', 'sales.transaction_id', '=', 'transaction_sales.id')
                    ->where('transaction_sales.cid', $cid)
                    ->when(!in_array($rid, [5, 6]), function ($query) use ($uid) {
                        $query->where('transaction_sales.uid', $uid);
                    })
                    ->sum(DB::raw('sales_items.quantity * sales_items.per_item_cost * (1 - sales_items.discount/100)'));

                // Total Sale Orders
                $totalSaleOrder = DB::table('transaction_sales')
                    ->where('cid', $cid)
                    ->when(!in_array($rid, [5, 6]), function ($query) use ($uid) {
                        $query->where('uid', $uid);
                    })
                    ->count();

                // Total Customers
                $distinctCustomers = TransactionSales::where('cid', $cid)
                ->distinct('customer_id')
                ->count('customer_id'); 

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
                
                return response()->json([
                    'error' => 'Failed to retrieve sales data'
                ], 500);
            }
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


public function update(Request $request, $transactionId)
{
    Log::info('Update API endpoint reached', [
        'transaction_id' => $transactionId,
        'request' => $request->all()
    ]);

    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Restrict to rid 5, 6, 7, 8, or 9 only
    if (!in_array($user->rid, [5, 6, 7, 8, 9])) {
        return response()->json(['message' => 'Unauthorized to update sale'], 403);
    }

    // Check if the transaction exists and belongs to the user's company
    $transaction = TransactionSales::where('id', $transactionId)
        ->where('uid', $user->id)
        ->first();
    if (!$transaction) {
        return response()->json(['message' => 'Transaction not found or unauthorized'], 404);
    }

    // Validate the request
    try {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.per_item_cost' => 'required|numeric|min:0',
            'products.*.unit_id' => 'required|integer|exists:units,id',
            'cid' => 'required|integer',
            'customer_id' => 'required|integer',
            'payment_mode' => 'required|string|max:50',
            'updated_at' => 'nullable|date_format:Y-m-d H:i:s', // Add this line
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed', ['errors' => $e->errors()]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    }

    $cid = (int)$request->cid;
    $productIds = array_column($request->products, 'product_id');

    // Fetch current stock for all products
    $stocks = DB::table('products as p')
        ->whereIn('p.id', $productIds)
        ->select([
            'p.id',
            'p.name',
            DB::raw("(
                SELECT COALESCE(SUM(pi.quantity), 0)
                FROM purchases pur
                JOIN transaction_purchases tp ON pur.transaction_id = tp.id
                JOIN purchase_items pi ON pur.id = pi.purchase_id
                WHERE pur.product_id = p.id AND tp.cid = $cid
            ) - (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales s
                JOIN transaction_sales ts ON s.transaction_id = ts.id
                JOIN sales_items si ON s.id = si.sale_id
                WHERE s.product_id = p.id AND ts.cid = $cid AND ts.id != $transactionId
            ) + (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales s
                JOIN sales_items si ON s.id = si.sale_id
                WHERE s.transaction_id = $transactionId AND s.product_id = p.id
            ) as current_stock")
        ])
        ->get()
        ->keyBy('id');

    // Check stock availability
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

    if (!empty($errors)) {
        return response()->json([
            'message' => 'Stock check failed',
            'errors' => $errors
        ], 422);
    }

    // Start transaction
    DB::beginTransaction();
    try {
        // Update the TransactionSales record
        $transaction->update([
            'cid' => $request->cid,
            'customer_id' => $request->customer_id,
            'payment_mode' => $request->payment_mode,
            'updated_at' => $request->updated_at ?? now(),
            // 'updated_at' => $request->updated_at ? Carbon::parse($request->updated_at) : now(),
            // 'updated_at' => now(),
        ]);
        // Fetch existing sales for this transaction
        $existingSales = Sale::where('transaction_id', $transactionId)->get()->keyBy('product_id');
        $newProductIds = array_column($request->products, 'product_id');

        // Delete sales that are no longer in the updated product list
        foreach ($existingSales as $sale) {
            if (!in_array($sale->product_id, $newProductIds)) {
                SalesItem::where('sale_id', $sale->id)->delete();
                $sale->delete();
                Log::info('Deleted sale', ['sale_id' => $sale->id]);
            }
        }

        // Update or create sales and sales items
        foreach ($request->products as $product) {
            $productId = $product['product_id'];

            if (isset($existingSales[$productId])) {
                // Update existing sale
                $sale = $existingSales[$productId];
                $salesItem = SalesItem::where('sale_id', $sale->id)->first();
                $salesItem->update([
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
                    'unit_id' => $product['unit_id'],
                ]);
                Log::info('Updated sale item', ['sale_id' => $sale->id]);
            } else {
                // Create new sale
                $sale = Sale::create([
                    'transaction_id' => $transactionId,
                    'product_id' => $productId,
                ]);
                SalesItem::create([
                    'sale_id' => $sale->id,
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
                    'unit_id' => $product['unit_id'],
                ]);
                Log::info('Created new sale', ['sale_id' => $sale->id]);
            }
        }

        DB::commit();
        Log::info('Sale updated successfully', ['transaction_id' => $transactionId]);

        return response()->json([
            'message' => 'Sale updated successfully',
            'transaction_id' => $transactionId,
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Sale update failed', ['error' => $e->getMessage()]);
        return response()->json([
            'message' => 'Sale update failed',
            'error' => $e->getMessage(),
        ], 500);
    }
}
    // public function getTransaction($transactionId)
    //     {
    //         Log::info('Get transaction API endpoint reached', ['transaction_id' => $transactionId]);

    //         // Get the authenticated user
    //         $user = Auth::user();
    //         if (!$user) {
    //             return response()->json(['message' => 'Unauthenticated'], 401);
    //         }

    //         // Restrict to rid 5, 6, 7, 8, or 9 only (consistent with store and update)
    //         if (!in_array($user->rid, [5, 6, 7, 8, 9])) {
    //             return response()->json(['message' => 'Unauthorized to view transaction'], 403);
    //         }

    //         // Fetch the transaction and ensure it belongs to the user
    //         $transaction = TransactionSales::where('id', $transactionId)
    //             ->where('uid', $user->id)
    //             ->first();
    //         if (!$transaction) {
    //             return response()->json(['message' => 'Transaction not found or unauthorized'], 404);
    //         }

    //         try {
    //             // Fetch associated sales and sales items with units
    //             $sales = Sale::where('transaction_id', $transactionId)
    //                 ->with('salesItem.unit')
    //                 ->get();

    //             if ($sales->isEmpty()) {
    //                 Log::warning("No sales records found for transaction_id: {$transactionId}");
    //                 return response()->json(['message' => 'No sales records found for this transaction'], 404);
    //             }

    //             // Prepare the products array
    //             $products = [];
    //             foreach ($sales as $sale) {
    //                 $salesItem = $sale->salesItem;
    //                 if ($salesItem) {
    //                     $products[] = [
    //                         'product_id' => $sale->product_id,
    //                         'quantity' => $salesItem->quantity,
    //                         'discount' => $salesItem->discount,
    //                         'per_item_cost' => $salesItem->per_item_cost,
    //                         'unit_id' => $salesItem->unit_id,
    //                         'unit_name' => $salesItem->unit ? $salesItem->unit->name : 'N/A', // Optional: include unit name
    //                     ];
    //                 }
    //             }

    //             // Construct the response data
    //             $transactionData = [
    //                 'transaction_id' => $transaction->id,
    //                 'cid' => $transaction->cid,
    //                 'customer_id' => $transaction->customer_id,
    //                 'payment_mode' => $transaction->payment_mode,
    //                 'created_at' => Carbon::parse($transaction->created_at)->format('Y-m-d H:i:s'),
    //                 'updated_at' => $transaction->updated_at ? Carbon::parse($transaction->updated_at)->format('Y-m-d H:i:s') : null,
    //                 'products' => $products,
    //             ];

    //             Log::info('Transaction data retrieved successfully', ['transaction_id' => $transactionId]);
    //             return response()->json($transactionData, 200);

    //         } catch (\Exception $e) {
    //             Log::error('Failed to fetch transaction data', [
    //                 'transaction_id' => $transactionId,
    //                 'error' => $e->getMessage()
    //             ]);
    //             return response()->json([
    //                 'message' => 'Failed to fetch transaction data',
    //                 'error' => $e->getMessage()
    //             ], 500);
    //         }
    //     }
    public function getTransaction($transactionId)
        {
            Log::info('Get transaction API endpoint reached', ['transaction_id' => $transactionId]);

            // Get the authenticated user
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }

            // Restrict to rid 5, 6, 7, 8, 9 only
            if (!in_array($user->rid, [5, 6, 7, 8, 9])) {
                return response()->json(['message' => 'Unauthorized to view transaction'], 403);
            }

            // Fetch the transaction
            $transaction = TransactionSales::where('id', $transactionId)
                ->where('uid', $user->id)
                ->first();
            
            if (!$transaction) {
                return response()->json(['message' => 'Transaction not found or unauthorized'], 404);
            }

            try {
                // Fetch associated sales and sales items with units
                $sales = Sale::where('transaction_id', $transactionId)
                    ->with('salesItem.unit')
                    ->get();

                if ($sales->isEmpty()) {
                    Log::warning("No sales records found for transaction_id: {$transactionId}");
                    return response()->json(['message' => 'No sales records found for this transaction'], 404);
                }

                // Fetch customer data directly using customer_id
                $customer = \App\Models\Customer::where('id', $transaction->customer_id)->first();
                
                // Prepare customer data
                $customerData = $customer ? [
                    'id' => $customer->id,
                    'first_name' => $customer->first_name,
                    'last_name' => $customer->last_name,

                    'email' => $customer->email ?? null,
                    'phone' => $customer->phone ?? null,
                    'gst' => $customer->gst ?? null,
                    'pan' => $customer->pan ?? null,
                    'address' => $customer->address ?? null,


                    // Add other fields as needed
                ] : null;

                // Prepare the products array
                $products = [];
                foreach ($sales as $sale) {
                    $salesItem = $sale->salesItem;
                    if ($salesItem) {
                        $products[] = [
                            'product_id' => $sale->product_id,
                            'quantity' => $salesItem->quantity,
                            'discount' => $salesItem->discount,
                            'per_item_cost' => $salesItem->per_item_cost,
                            'unit_id' => $salesItem->unit_id,
                            'unit_name' => $salesItem->unit ? $salesItem->unit->name : 'N/A',
                        ];
                    }
                }

                // Construct the response data
                $transactionData = [
                    'transaction_id' => $transaction->id,
                    'cid' => $transaction->cid,
                    'customer_id' => $transaction->customer_id,
                    'customer' => $customerData, // Added customer data
                    'payment_mode' => $transaction->payment_mode,
                    'created_at' => Carbon::parse($transaction->created_at)->format('Y-m-d H:i:s'),
                    'updated_at' => $transaction->updated_at ? Carbon::parse($transaction->updated_at)->format('Y-m-d H:i:s') : null,
                    'products' => $products,
                ];

                Log::info('Transaction data retrieved successfully', ['transaction_id' => $transactionId]);
                return response()->json($transactionData, 200);

            } catch (\Exception $e) {
                Log::error('Failed to fetch transaction data', [
                    'transaction_id' => $transactionId,
                    'error' => $e->getMessage()
                ]);
                return response()->json([
                    'message' => 'Failed to fetch transaction data',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

}