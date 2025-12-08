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
use App\Exports\B2CSalesReportExport;
use Maatwebsite\Excel\Facades\Excel;


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
            'bill_name' => 'string|nullable|max:255',
            'customer_id' => 'required|integer|exists:sales_clients,id',
            'payment_mode' => 'required|integer|exists:payment_modes,id',
            'absolute_discount' => 'nullable|numeric|min:0',
            'total_paid' => 'required|numeric|min:0',
            'sales_date' => 'required|date_format:Y-m-d H:i:s',
            'products' => 'required|array',
            'products.*.product_id' => 'required|integer|exists:products,id',
            'products.*.quantity' => 'required|numeric|min:0',
            'products.*.discount' => 'nullable|numeric|min:0',
            'products.*.p_price' => 'nullable|numeric|min:0',
            'products.*.s_price' => 'required|numeric|min:0',
            'products.*.unit_id' => 'required|integer|exists:units,id',
            'products.*.gst' => 'nullable|numeric|min:0',
            'products.*.serial_numbers' => 'nullable|array',
'products.*.serial_numbers.*' => 'nullable|string|max:100',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed', ['errors' => $e->errors()]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    }
    // ✅ CRITICAL: Calculate total purchase amount
    $totalPurchase = 0;
    foreach ($request->products as $product) {
        $itemTotal = $product['s_price'] * $product['quantity'];
        $itemTotal -= $itemTotal * ($product['discount'] / 100);
        $itemTotal += $itemTotal * ($product['gst'] / 100);
        $totalPurchase += $itemTotal;
    }
    
    $totalPurchase -= $request->absolute_discount ?? 0;
    $totalPaid = $request->total_paid;
    $dueAmount = $totalPurchase - $totalPaid;

    // ✅ CRITICAL: Mobile number validation for due
    if ($dueAmount > 0) {
        $customer = SalesClient::find($request->customer_id);
        if (!$customer || empty($customer->phone)) {
            return response()->json([
                'message' => 'Mobile number is required for credit sales'
            ], 422);
        }
    }
    
    // Helper function: Format stock in mixed units
    function formatStock($total, $c_factor, $p_name, $s_name, $s_unit_id) {
        // Case 1: No secondary unit OR no conversion factor
        if ($s_unit_id == 0 || $c_factor <= 0) {
            return number_format($total, 3) . " " . $p_name;
        }
        
        // Case 2: Has secondary unit and conversion factor
        $primary = floor($total / $c_factor);
        $secondary = $total % $c_factor;
        
        $str = "";
        if ($primary > 0) {
            $str .= number_format($primary, 3) . " " . $p_name;
        }
        if ($secondary > 0) {
            if ($primary > 0) {
                $str .= " ";
            }
            $str .= number_format($secondary, 3) . " " . $s_name;
        }
        return $str ?: "0 " . $p_name;
    }

    // Determine bill_name
    if ($request->has('bill_name')) {
        $billName = $request->bill_name;
    } else {
        $customer = DB::table('sales_clients')->where('id', $request->customer_id)->first();
        $customerName = $customer->name;
        $formattedDate = Carbon::parse($request->sales_date)->format('Y-m-d');
        $billName = $customerName . ' - ' . $formattedDate;
    }

    // Extract product IDs from the request
    $productIds = array_column($request->products, 'product_id');

    // Fetch product details including c_factor, p_unit, and current stock in secondary units
    $stocks = DB::table('products as p')
        ->whereIn('p.id', $productIds)
        ->select([
            'p.id',
            'p.name',
            'p.c_factor',
            'p.p_unit',
            'p.s_unit', // Need this for stock formatting
            'pu.name as p_unit_name',
            'su.name as s_unit_name'
        ])
        ->leftJoin('units as pu', 'p.p_unit', '=', 'pu.id')
        ->leftJoin('units as su', 'p.s_unit', '=', 'su.id')
        ->selectRaw("(
            SELECT COALESCE(SUM(
                CASE
                    WHEN p.c_factor > 0 AND pi.unit_id = p.p_unit THEN pi.quantity * p.c_factor
                    ELSE pi.quantity
                END
            ), 0)
            FROM purchase_items pi
            JOIN purchase_bills pb ON pi.bid = pb.id
            JOIN users u ON pb.uid = u.id
            WHERE u.cid = ? AND pi.pid = p.id
        ) - (
            SELECT COALESCE(SUM(
                CASE
                    WHEN p.c_factor > 0 AND si.unit_id = p.p_unit THEN si.quantity * p.c_factor
                    ELSE si.quantity
                END
            ), 0)
            FROM sales_items si
            JOIN sales_bills sb ON si.bid = sb.id
            JOIN users u ON sb.uid = u.id
            WHERE u.cid = ? AND si.pid = p.id
        ) as current_stock_s", [$cid, $cid])
        ->get()
        ->keyBy('id');

    // Check stock availability for each product with unit conversion
    $errors = [];
    foreach ($request->products as $product) {
        $productId = $product['product_id'];
        $requestedQuantity = $product['quantity'];
        $requestedUnitId = $product['unit_id'];

        if (!isset($stocks[$productId])) {
            $errors[] = [
                'product_id' => $productId,
                'product_name' => 'Unknown',
                'current_stock' => '0',
                'requested_stock' => $requestedQuantity
            ];
            continue;
        }

        $stock = $stocks[$productId];
        $currentStockS = $stock->current_stock_s;  // Current stock in correct units

        // 🔥 FIX: Correct stock conversion for all product types
        if ($stock->c_factor > 0 && $stock->s_unit > 0) {
            // Products with conversion factor (Box → Piece)
            $requestedInS = ($requestedUnitId == $stock->p_unit) 
                ? $requestedQuantity * $stock->c_factor 
                : $requestedQuantity;
        } else {
            // Products without conversion (Piece/Packet) - direct comparison
            $requestedInS = $requestedQuantity;
        }

        if ($requestedInS > $currentStockS) {
            // Format current stock correctly in error message
            $currentStockFormatted = formatStock(
                $currentStockS,
                $stock->c_factor,
                $stock->p_unit_name,
                $stock->s_unit_name ?? 'Unit',
                $stock->s_unit
            );

            $errors[] = [
                'product_id' => $productId,
                'product_name' => $stock->name,
                'current_stock' => $currentStockFormatted,
                'requested_stock' => $requestedQuantity . " " . (
                    $requestedUnitId == $stock->p_unit 
                    ? $stock->p_unit_name 
                    : ($stock->s_unit_name ?? 'Unit')
                )
            ];
                $errorMessages[] = "{$stock->name} stock not available";

        }
    }

    // Return error response if stock check fails
    if (!empty($errors)) {
        // return response()->json([
        //     // 'message' => 'Stock check failed',
        //     // 'errors' => $errors
        //         'message' => implode(', ', $errorMessages)

        // ], 422);
        return response(implode(', ', $errorMessages), 422)
       ->header('Content-Type', 'text/plain');

    }

    // Proceed with the transaction if all stock checks pass
    DB::beginTransaction();
    try { 
        $salesDate = $request['sales_date'];
        $salesBill = SalesBill::create([
            'bill_name' => $billName,
            'scid' => $request->customer_id,
            'uid' => $user->id,
            'payment_mode' => $request->payment_mode,
            'absolute_discount' => $request->absolute_discount ?? 0,
            'paid_amount' => $request->total_paid,
            'created_at' => $salesDate,
            'updated_at' => $salesDate,
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
                'p_price' => $product['p_price'] ?? 0,
                's_price' => $product['s_price'],
                'quantity' => $product['quantity'],
                'unit_id' => $product['unit_id'],
                'dis' => $product['discount'] ?? 0,
                'gst' => $product['gst'] ?? 0,
                'serial_numbers' => !empty($product['serial_numbers'])
        ? implode(', ', array_map('trim', $product['serial_numbers']))
        : null,
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

// public function getAllInvoicesByCompany($cid)
// {
//     // Get the authenticated user
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }
//     $uid = $user->id;

//     // Check if the user belongs to the requested company
//     if ($user->cid != $cid) {
//         return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
//     }
    
//     // Determine which RIDs the user can view based on their own RID
//     $allowedRids = [];
//     switch ($user->rid) {
//         case 1: // Admin - can see everyone
//             $allowedRids = [1, 2, 3, 4, 5];
//             break;
//         case 2: // Superuser - can see Superuser, Moderator, Authenticated, Anonymous
//             $allowedRids = [2, 3, 4, 5];
//             break;
//         case 3: // Moderator - can see Moderator, Authenticated, Anonymous
//             $allowedRids = [3, 4, 5];
//             break;
//         case 4: // Authenticated - can only see Authenticated
//         case 5: // Anonymous - can only see Anonymous
//             $allowedRids = [$user->rid];
//             break;
//         default:
//             // For safety, deny access if rid is not recognized
//             return response()->json(['message' => 'Forbidden: Invalid role'], 403);
//     }
    
//     Log::info('Company access verified for getAllInvoicesByCompany', [
//         'cid' => $cid, 
//         'user_rid' => $user->rid,
//         'allowed_rids' => $allowedRids
//     ]);

//     try {
//         // Build the query with the sales_bills table
//         $query = DB::table('sales_bills as sb')
//             ->select(
//                 'sb.id as transaction_id',          // Bill ID as transaction ID
//                 'sb.bill_name as bill_name',
//                 'sc.name as customer_name',         // Customer name from sales_clients
//                 'sb.scid as customer_id',           // Customer ID from sales_bills
//                 'sb.payment_mode',                  // Payment mode integer
//                 'sb.updated_at as date',            // Date of the transaction
//                 'u.name as sales_by',               // Name of the user who made the sale
//                 'u.rid as seller_rid'               // For debugging visibility
//             )
//             ->leftJoin('sales_clients as sc', 'sb.scid', '=', 'sc.id')  // Join with customers
//             ->leftJoin('users as u', 'sb.uid', '=', 'u.id')             // Join with users
//             ->where('u.cid', $cid)                                      // Filter by company ID
//             ->whereIn('u.rid', $allowedRids)                            // Role-based filter
//             ->orderBy('sb.updated_at', 'desc');
            
//         // Execute the query
//         $transactions = $query->get();

//         // Fetch payment modes from the database
//         $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();

//         // Map payment_mode from integer to string
//         $transactions = $transactions->map(function ($transaction) use ($paymentModes) {
//             $transaction->payment_mode = $paymentModes[$transaction->payment_mode] ?? 'Unknown';
//             return $transaction;
//         });

//         // Handle empty results
//         if ($transactions->isEmpty()) {
//             Log::info('No transactions found for cid', [
//                 'cid' => $cid, 
//                 'allowed_rids' => $allowedRids
//             ]);
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'No transactions found for this customer ID'
//             ], 404);
//         }

//         // Return successful response
//         Log::info('Transactions retrieved successfully', [
//             'cid' => $cid, 
//             'count' => $transactions->count(),
//             'allowed_rids' => $allowedRids
//         ]);
        
//         return response()->json([
//             'status' => 'success',
//             'data' => $transactions
//         ], 200);
//     } catch (\Exception $e) {
//         Log::error('Failed to fetch transactions', [
//             'cid' => $cid, 
//             'user_rid' => $user->rid,
//             'error' => $e->getMessage()
//         ]);
//         return response()->json([
//             'message' => 'Failed to fetch transactions', 
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

public function getAllInvoicesByCompany($cid)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }

    try {
        $query = DB::table('sales_bills as sb')
            ->select(
                'sb.id as transaction_id',
                'sb.bill_name as bill_name',
                'sc.name as customer_name',
                'sb.scid as customer_id',
                'sb.payment_mode',
                'sb.updated_at as date',
                'u.name as sales_by',
                'u.rid as seller_rid'
            )
            ->leftJoin('sales_clients as sc', 'sb.scid', '=', 'sc.id')
            ->leftJoin('users as u', 'sb.uid', '=', 'u.id')
            ->where('u.cid', $cid)
            ->orderBy('sb.updated_at', 'desc');

        // Fix: Restrict by user ID for rid 4/5, role-based for others
        if ($user->rid == 4 || $user->rid == 5) {
            // Only show sales made by the current user
            $query->where('sb.uid', $user->id);
            Log::info('Restricting to own sales for rid 4/5', [
                'cid' => $cid,
                'user_rid' => $user->rid,
                'user_id' => $user->id
            ]);
        } else {
            // Role-based access for rid 1,2,3
            $allowedRids = [];
            switch ($user->rid) {
                case 1: // Admin
                    $allowedRids = [1, 2, 3, 4, 5];
                    break;
                case 2: // Superuser
                    $allowedRids = [2, 3, 4, 5];
                    break;
                case 3: // Moderator
                    $allowedRids = [3, 4, 5];
                    break;
            }
            $query->whereIn('u.rid', $allowedRids);
            Log::info('Role-based access for sales', [
                'cid' => $cid,
                'user_rid' => $user->rid,
                'allowed_rids' => $allowedRids
            ]);
        }

        $transactions = $query->get();

        $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();
        $transactions = $transactions->map(function ($transaction) use ($paymentModes) {
            $transaction->payment_mode = $paymentModes[$transaction->payment_mode] ?? 'Unknown';
            return $transaction;
        });

        if ($transactions->isEmpty()) {
            Log::info('No transactions found', ['cid' => $cid]);
            return response()->json(['status' => 'error', 'message' => 'No transactions found'], 404);
        }

        return response()->json(['status' => 'success', 'data' => $transactions], 200);
    } catch (\Exception $e) {
        Log::error('Failed to fetch transactions', [
            'cid' => $cid,
            'error' => $e->getMessage()
        ]);
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

    // Fetch sales items with calculated fields
    $salesDetails = DB::table('sales_items as si')
        ->join('products as prod', 'si.pid', '=', 'prod.id')
        ->join('sales_bills as sb', 'si.bid', '=', 'sb.id')
        ->join('units as u', 'si.unit_id', '=', 'u.id')
        ->select(
            'si.pid as product_id',
            'prod.name as product_name',
            'si.s_price as selling_price',
            'si.p_price as purchase_price',
            'si.dis as discount',
            'si.serial_numbers',
            DB::raw('ROUND(si.quantity * si.s_price * (1 - COALESCE(si.dis, 0)/100), 2) AS pre_gst_total'),
            'si.quantity',
            'si.gst as gst',
            DB::raw('ROUND((si.quantity * si.s_price * (1 - COALESCE(si.dis, 0)/100)) * (COALESCE(si.gst, 0)/100), 2) AS gst_amount'),
            DB::raw('ROUND(si.quantity * si.s_price * (1 - COALESCE(si.dis, 0)/100) * (1 + COALESCE(si.gst, 0)/100), 2) AS per_product_total'),
            'si.unit_id',
            'u.name as unit_name'
        )
        ->where('si.bid', $transactionId)
        ->get();

    if ($salesDetails->isEmpty()) {
        return response()->json([
            'status' => 'error',
            'message' => 'No sales details found for this transaction ID'
        ], 404);
    }

    // Calculate financial totals
    $totalItemNetValue = $salesDetails->sum('pre_gst_total');
    $totalGstAmount = $salesDetails->sum('gst_amount');
    $totalAmount = $totalItemNetValue + $totalGstAmount;
    $payableAmount = $totalAmount - $absoluteDiscount;
    $dueAmount = max(0, $payableAmount - $paidAmount);

    // Fetch payment modes
    $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();
    $paymentModeName = $paymentModes[$transaction->payment_mode] ?? 'Unknown';

    // Fetch customer details
    $customer = DB::table('sales_clients')
        ->where('id', $transaction->scid)
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
            'products' => $salesDetails,
            'transaction_id' => $transaction->id,
            'bill_name' => $transaction->bill_name,
            'sales_by' => $userDetail ? $userDetail->name : 'Unknown',
            'customer_name' => $customer ? $customer->customer_name : 'Unknown',
            'customer_id' => $transaction->scid,
            'payment_mode' => $paymentModeName,
            'date' => $transaction->updated_at,
            'total_item_net_value' => round($totalItemNetValue, 2),
            'total_gst_amount' => round($totalGstAmount, 2),
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
            'products.*.gst' => 'nullable|numeric|min:0',
            'absolute_discount' => 'nullable|numeric|min:0',
            'set_paid_amount' => 'nullable|numeric',
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
    
    // ✅ CRITICAL FIX 1: LOAD TRANSACTION BEFORE ANY CALCULATIONS
    // Check if the transaction exists
    $transaction = SalesBill::where('id', $transactionId)->first();
    if (!$transaction) {
        \Log::info('Transaction not found', ['transaction_id' => $transactionId]);
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }
    
    // Check if the current user is the one who created this transaction
    if ($transaction->uid != $user->id) {
        \Log::warning('Unauthorized transaction update attempt', [
            'transaction_id' => $transactionId,
            'user_id' => $user->id,
            'transaction_owner_id' => $transaction->uid
        ]);
        return response()->json([
            'message' => 'Forbidden: You do not have permission to update this transaction'
        ], 403);
    }

    // ✅ CRITICAL FIX 2: CALCULATE TOTAL PURCHASE AFTER TRANSACTION LOADING
    $totalPurchase = 0;
    if ($request->has('products')) {
        foreach ($request->products as $product) {
            $itemTotal = $product['s_price'] * $product['quantity'];
            $itemTotal -= $itemTotal * ($product['dis'] / 100);
            $itemTotal += $itemTotal * ($product['gst'] / 100);
            $totalPurchase += $itemTotal;
        }
    }
    
    $totalPurchase -= $request->absolute_discount ?? 0;

    // ✅ CRITICAL FIX 3: TREAT set_paid_amount AS ADJUSTMENT (NOT ABSOLUTE VALUE)
    $adjustment = $request->input('set_paid_amount', 0);
    $existingPaid = $transaction->paid_amount;
    $totalPaid = $existingPaid + $adjustment;

    // Validate new paid amount
    if ($totalPaid < 0) {
        return response()->json([
            'message' => 'Paid amount cannot be negative'
        ], 422);
    }
    // if ($totalPaid > $totalPurchase) {
    //     return response()->json([
    //         'message' => 'Paid amount cannot exceed total sale amount'
    //     ], 422);
    // }
    $roundedPaid = round($totalPaid, 2);
    $roundedPurchase = round($totalPurchase, 2);

     if ($roundedPaid - $roundedPurchase > 0.001) {
    return response()->json([
        'message' => 'Paid amount cannot exceed total sale amount'
    ], 422);
}


    $dueAmount = $totalPurchase - $totalPaid;

    // ✅ CRITICAL: Only validate mobile if dueAmount is ACTUALLY positive
    if ($dueAmount > 0.001) { // Allow small floating point errors
        $customer = SalesClient::find($request->customer_id);
        if (!$customer || empty($customer->phone)) {
            return response()->json([
                'message' => 'Mobile number is required for credit sales'
            ], 422);
        }
    }
    
    $cid = $user->cid;

    // Helper function: Format stock in mixed units
    function formatStock($total, $c_factor, $p_name, $s_name, $s_unit_id) {
        // Case 1: No secondary unit OR no conversion factor
        if ($s_unit_id == 0 || $c_factor <= 0) {
            return number_format($total, 3) . " " . $p_name;
        }
        
        // Case 2: Has secondary unit and conversion factor
        $primary = floor($total / $c_factor);
        $secondary = $total % $c_factor;
        
        $str = "";
        if ($primary > 0) {
            $str .= number_format($primary, 3) . " " . $p_name;
        }
        if ($secondary > 0) {
            if ($primary > 0) {
                $str .= " ";
            }
            $str .= number_format($secondary, 3) . " " . $s_name;
        }
        return $str ?: "0 " . $p_name;
    }

    // Handle stock check if products are being updated
    if ($request->has('products')) {
        $products = $request->input('products', []);
        $productIds = array_column($products, 'product_id');

        // Fetch existing sales items for this transaction
        $existingItems = DB::table('sales_items')
            ->where('bid', $transactionId)
            ->get(['pid', 'quantity', 'unit_id']);

        // Fetch product details and current stock in secondary units
        $stocks = DB::table('products as p')
            ->leftJoin('units as pu', 'p.p_unit', '=', 'pu.id')
            ->leftJoin('units as su', 'p.s_unit', '=', 'su.id')
            ->whereIn('p.id', $productIds)
            ->select([
                'p.id',
                'p.name',
                'p.c_factor',
                'p.p_unit',
                'p.s_unit', // Critical for stock formatting
                'pu.name as p_unit_name',
                'su.name as s_unit_name'
            ])
            ->selectRaw("(
                SELECT COALESCE(SUM(
                    CASE
                        WHEN p.c_factor > 0 AND pi.unit_id = p.p_unit THEN pi.quantity * p.c_factor
                        ELSE pi.quantity
                    END
                ), 0)
                FROM purchase_items pi
                JOIN purchase_bills pb ON pi.bid = pb.id
                JOIN users u ON pb.uid = u.id
                WHERE u.cid = {$cid} AND pi.pid = p.id
            ) as total_purchase_s")
            ->selectRaw("(
                SELECT COALESCE(SUM(
                    CASE
                        WHEN p.c_factor > 0 AND si.unit_id = p.p_unit THEN si.quantity * p.c_factor
                        ELSE si.quantity
                    END
                ), 0)
                FROM sales_items si
                JOIN sales_bills sb ON si.bid = sb.id
                JOIN users u ON sb.uid = u.id
                WHERE u.cid = {$cid} AND si.pid = p.id
            ) as total_sales_s")
            ->get()
            ->keyBy('id');

        // Compute old_s map for products in request
        $old_s_map = [];
        foreach ($existingItems as $item) {
            if (in_array($item->pid, $productIds) && isset($stocks[$item->pid])) {
                $stock = $stocks[$item->pid];
                
                // 🔥 FIX 1: Correct old_s calculation
                if ($stock->s_unit > 0 && $stock->c_factor > 0) {
                    $old_s = ($item->unit_id == $stock->p_unit) 
                        ? $item->quantity * $stock->c_factor 
                        : $item->quantity;
                } else {
                    $old_s = $item->quantity;
                }
                
                $old_s_map[$item->pid] = $old_s;
            }
        }

        // Check stock availability
        $errors = [];
        foreach ($products as $product) {
            $productId = $product['product_id'];
            $requestedQuantity = $product['quantity'];
            $requestedUnitId = $product['unit_id'];

            if (!isset($stocks[$productId])) {
                $errors[] = [
                    'product_id' => $productId,
                    'product_name' => 'Unknown',
                    'current_stock' => '0',
                    'requested_stock' => $requestedQuantity
                ];
                continue;
            }

            $stock = $stocks[$productId];
            $current_s = $stock->total_purchase_s - $stock->total_sales_s;
            $old_s = $old_s_map[$productId] ?? 0;
            $available_s = $current_s + $old_s; // Stock without this transaction + old sale

            // 🔥 FIX 1: Correct new_s calculation
            if ($stock->s_unit > 0 && $stock->c_factor > 0) {
                $new_s = ($requestedUnitId == $stock->p_unit) 
                    ? $requestedQuantity * $stock->c_factor 
                    : $requestedQuantity;
            } else {
                $new_s = $requestedQuantity;
            }

            if ($new_s > $available_s) {
                // 🔥 FIX 2: Format current stock correctly
                $currentStockFormatted = formatStock(
                    $available_s,
                    $stock->c_factor,
                    $stock->p_unit_name,
                    $stock->s_unit_name ?? 'Unit',
                    $stock->s_unit
                );

                $errors[] = [
                    'product_id' => $productId,
                    'product_name' => $stock->name,
                    'current_stock' => $currentStockFormatted,
                    'requested_stock' => $requestedQuantity . " " . (
                        $requestedUnitId == $stock->p_unit 
                        ? $stock->p_unit_name 
                        : ($stock->s_unit_name ?? 'Unit')
                    )
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
    }

    // Start a database transaction
    DB::beginTransaction();
    try {
        // Prepare update data for sales_bills
        $updateData = [
            'updated_at' => $request->input('updated_at', now()),
            'bill_name' => $request->input('bill_name', $transaction->bill_name),
            'scid' => $request->input('customer_id', $transaction->scid),
            'payment_mode' => $request->input('payment_mode', $transaction->payment_mode),
            'absolute_discount' => $request->input('absolute_discount', $transaction->absolute_discount),
            'paid_amount' => $totalPaid, // Use calculated total (not raw input)
        ];

        // Update sales_bills
        SalesBill::where('id', $transactionId)->update($updateData);

        // Handle products if provided
        if ($request->has('products')) {
            $products = $request->input('products', []);
            $productIds = array_column($products, 'product_id');

            // Fetch existing sales items pids
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
                            'gst' => $product['gst'] ?? 0,
                            'serial_numbers' => !empty($product['serial_numbers'])
            ? implode(', ', array_map('trim', $product['serial_numbers']))
            : null,
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
                        'gst' => $product['gst'] ?? 0,
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
                 si.quantity * si.s_price * (1 - COALESCE(si.dis, 0) / 100) * (1 + COALESCE(si.gst, 0) / 100)
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
        $totalItemNetValue = 0; // Initialize total item net value
        $totalGstAmount = 0;    // Initialize total GST amount
        foreach ($sales as $sale) {
            $product = Product::find($sale->pid);
            $salesItem = $sale;

            // 
            if ($salesItem) {
                // Calculate net price after discount (excluding GST)
                $netPrice = $salesItem->s_price * (1 - ($salesItem->dis ?? 0) / 100);
                // Calculate per product total (without GST)
                $perProductTotal = $salesItem->quantity * $netPrice;
                // Calculate GST amount
                $gstAmount = $perProductTotal * (($salesItem->gst ?? 0) / 100);
                // Calculate total including GST for the item
                $itemTotal = $perProductTotal + $gstAmount;

                $items[] = [
                    'product_name' => $product ? $product->name : 'Unknown Product',
                    'hsn' => $product->hscode ?? 0000,
                    'quantity' => $salesItem->quantity,
                    'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A',
                    'per_item_cost' => $salesItem->s_price,
                    'discount' => $salesItem->dis ?? 0,
                    'net_price' => round($netPrice, 2),
                    'per_product_total' => round($perProductTotal, 2),
                    'gst' => $salesItem->gst ?? 0,
                    'gst_amount' => round($gstAmount, 2),
                    'total' => round($itemTotal, 2),
                    'amount' => round($perProductTotal, 2),
                    'serial_numbers' => $serials, // ← Show here
                ];

                // Accumulate totals
                $totalItemNetValue += $perProductTotal;
                $totalGstAmount += $gstAmount;
            }
        }
        // Calculate total amount (net value + GST)
        $totalAmount = $totalItemNetValue + $totalGstAmount;

        Log::info('Invoice items prepared', [
            'items' => $items,
            'total_item_net_value' => $totalItemNetValue,
            'total_gst_amount' => $totalGstAmount,
            'total_amount' => $totalAmount
        ]);
       // Apply global absolute discount
       $absoluteDiscount = $transaction->absolute_discount ?? 0;
       $payableAmount = $totalAmount - $absoluteDiscount; // Calculate payable_amount
       $paidAmount = $transaction->paid_amount ?? 0; // Fetch paid_amount
       $dueAmount = max(0, $payableAmount - $paidAmount); // Calculate due_amount

        Log::info('Final invoice totals', [
            'total_amount' => $totalAmount,
            'absolute_discount' => $absoluteDiscount,
            'payable_amount' => $payableAmount,
            'paid_amount' => $paidAmount,
            'due_amount' => $dueAmount,
        ]);

        $data = [
            'invoice' => (object) $invoice,
            'transaction' => $transaction,
            'items' => $items,
            'total_item_net_value' => round($totalItemNetValue, 2),
            'total_gst_amount' => round($totalGstAmount, 2),
            'total_amount' => round($totalAmount, 2),
            'absolute_discount' => round($absoluteDiscount, 2),
            'payable_amount' => round($payableAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'due_amount' => round($dueAmount, 2),
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
public function destroy(Request $request, $transactionId)
{
    Log::info('Delete sales bill endpoint reached', [
        'bill_id' => $transactionId,
    ]);

    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Restrict to roles 5 and 6 only
    if (!in_array($user->rid, [1,2,3])) {
        return response()->json(['message' => 'Unauthorized to delete sales bill'], 403);
    }

    // Check if bill exists and belongs to the user
    $bill = DB::table('sales_bills')
        ->where('id', $transactionId)
        ->where('uid', $user->id)
        ->first();

    if (!$bill) {
        return response()->json([
            'message' => 'Sales bill not found or unauthorized',
        ], 404);
    }

    DB::beginTransaction();
    try {
        // Delete related sales_items
        DB::table('sales_items')->where('bid', $transactionId)->delete();

        // Delete the sales bill
        DB::table('sales_bills')->where('id', $transactionId)->delete();

        DB::commit();
        Log::info('Sales bill deleted successfully', [
            'bill_id' => $transactionId,
        ]);

        return response()->json([
            'message' => 'Sales bill deleted successfully',
            'transaction_id' => $transactionId,
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to delete sales bill', [
            'bill_id' => $transactionId,
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Failed to delete sales bill',
            'error' => $e->getMessage(),
        ], 500);
    }
}
public function getCustomersWithDues($cid)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array($user->rid, [1, 2, 3,4])) {
            return response()->json(['message' => 'Unauthorized to access customer dues'], 403);
        }

        $cid = (int) $cid;
        if ($user->cid != $cid) {
            return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
        }

        if (!DB::table('users')->where('cid', $cid)->exists()) {
            return response()->json(['message' => 'Invalid cid'], 422);
        }

        Log::info("Fetching customers with dues for cid: {$cid}");

        $billTotals = DB::table('sales_items')
            ->select('bid', DB::raw('SUM(s_price * quantity * (1 - dis / 100) * (1 + gst / 100)) as item_total'))
            ->groupBy('bid');

        Log::info("Bill totals subquery prepared");

        $customersWithDues = DB::table('sales_clients as sc')
            ->join('sales_bills as sb', 'sc.id', '=', 'sb.scid')
            ->joinSub($billTotals, 'bt', function ($join) {
                $join->on('sb.id', '=', 'bt.bid');
            })
            ->select(
                'sc.id as customer_id',
                'sc.name as customer_name',
                DB::raw("TO_CHAR(SUM(bt.item_total - sb.absolute_discount), 'FM999999999.00') as total_purchase"),
                DB::raw("TO_CHAR(SUM(sb.paid_amount), 'FM999999999.00') as total_paid"),
                DB::raw("TO_CHAR(SUM(bt.item_total - sb.absolute_discount - sb.paid_amount), 'FM999999999.00') as total_due")
            )
            ->where('sc.cid', $cid)
            ->groupBy('sc.id', 'sc.name')
            ->havingRaw('SUM(bt.item_total - sb.absolute_discount - sb.paid_amount) > 0')
            ->get();

        Log::info("Customers with dues retrieved", ['count' => $customersWithDues->count()]);

        return response()->json($customersWithDues);
    }

    public function getCustomerDues($customer_id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!in_array($user->rid, [1, 2, 3,4])) {
            return response()->json(['message' => 'Unauthorized to access customer dues'], 403);
        }

        $customer_id = (int) $customer_id;

        if (!DB::table('sales_clients')->where('id', $customer_id)->exists()) {
            return response()->json(['message' => 'Invalid customer_id'], 422);
        }

        Log::info("Fetching dues for customer_id: {$customer_id}");

        $billTotals = DB::table('sales_items')
            ->select('bid', DB::raw('SUM(s_price * quantity * (1 - dis / 100) * (1 + gst / 100)) as item_total'))
            ->groupBy('bid');

        $customerData = DB::table('sales_clients as sc')
            ->join('sales_bills as sb', 'sc.id', '=', 'sb.scid')
            ->joinSub($billTotals, 'bt', function ($join) {
                $join->on('sb.id', '=', 'bt.bid');
            })
            ->select(
                'sc.id as customer_id',
                'sc.name as customer_name',
                DB::raw("TO_CHAR(SUM(bt.item_total - sb.absolute_discount), 'FM999999999.00') as total_purchase"),
                DB::raw("TO_CHAR(SUM(sb.paid_amount), 'FM999999999.00') as total_paid"),
                DB::raw("TO_CHAR(SUM(bt.item_total - sb.absolute_discount - sb.paid_amount), 'FM999999999.00') as total_due"),
                DB::raw("JSON_AGG(
                    JSON_BUILD_OBJECT(
                        'date', TO_CHAR(sb.updated_at, 'YYYY-MM-DD'),
                        'purchase', TO_CHAR(ROUND(bt.item_total - sb.absolute_discount, 2), 'FM999999999.00'),
                        'paid', TO_CHAR(ROUND(sb.paid_amount, 2), 'FM999999999.00'),
                        'due', TO_CHAR(ROUND(bt.item_total - sb.absolute_discount - sb.paid_amount, 2), 'FM999999999.00')
                    )
                        ORDER BY sb.updated_at DESC
                ) as transactions")
            )
            ->where('sc.id', $customer_id)
            ->groupBy('sc.id', 'sc.name')
            ->havingRaw('SUM(bt.item_total - sb.absolute_discount - sb.paid_amount) > 0')
            ->first();

        if (!$customerData) {
            return response()->json(['message' => 'No dues found for this customer'], 404);
        }

        $transactions = json_decode($customerData->transactions, true);

        $formattedData = [
            'customer_id' => $customerData->customer_id,
            'customer_name' => $customerData->customer_name,
            'total_purchase' => $customerData->total_purchase,
            'total_paid' => $customerData->total_paid,
            'total_due' => $customerData->total_due,
            'transactions' => $transactions,
        ];

        Log::info("Customer dues retrieved", [
            'customer_id' => $customer_id,
            'transaction_count' => count($transactions)
        ]);

        return response()->json($formattedData);
    }
    public function getSalesTransactionsByPid(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        $request->validate([
            'pid' => 'required|integer',
        ]);
    
        $pid = $request->input('pid');
        $cid = $user->cid; // ✅ Extracted from authenticated user
        $userRid = $user->rid;
    
        // Determine allowed roles for higher privileges (1-3)
        $allowedRids = [];
        switch ($userRid) {
            case 1: // Admin
                $allowedRids = [1, 2, 3, 4, 5];
                break;
            case 2: // Superuser
                $allowedRids = [2, 3, 4, 5];
                break;
            case 3: // Moderator
                $allowedRids = [3, 4, 5];
                break;
            case 4: // Authenticated
            case 5: // Anonymous
                // Will handle below
                break;
            default:
                return response()->json(['message' => 'Forbidden: Invalid role'], 403);
        }
    
        try {
            // Build base query with SALES tables 
            $query = DB::table('sales_bills as sb')
                ->select(
                    'sb.id as transaction_id',
                    'sb.bill_name as bill_name',
                    'sc.name as customer_name',  
                    'sb.scid as customer_id',  
                    'sb.payment_mode',
                    'sb.updated_at as date',
                    'u.name as sales_by'  
                )
                ->leftJoin('sales_clients as sc', 'sb.scid', '=', 'sc.id')
                ->leftJoin('users as u', 'sb.uid', '=', 'u.id')
                ->join('sales_items as si', 'sb.id', '=', 'si.bid')
                ->where('si.pid', $pid)
                ->where('u.cid', $cid)
                ->orderBy('sb.updated_at', 'desc');
    
            // Apply role-based filtering (same logic)
            if ($userRid <= 3) {
                $query->whereIn('u.rid', $allowedRids);
            } else {
                $query->where('sb.uid', $user->id);
            }
    
            $transactions = $query->get();
    
            // Convert payment mode (same as purchase)
            $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();
            $transactions = $transactions->map(function ($transaction) use ($paymentModes) {
                $transaction->payment_mode = $paymentModes[$transaction->payment_mode] ?? 'Unknown';
                return $transaction;
            });
    
            if ($transactions->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No transactions found for this product'
                ], 404);
            }
    
            return response()->json([
                'status' => 'success',
                'data' => $transactions
            ], 200);
    
        } catch (\Exception $e) {
            Log::error('Transaction fetch by PID failed', [
                'pid' => $pid,
                'cid' => $cid,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Failed to fetch transactions',
                'error' => $e->getMessage()
            ], 500);
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





// public function b2cSalesReport(Request $request)
// {
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     if (!in_array($user->rid, [1, 2, 3])) {
//         return response()->json(['message' => 'Forbidden'], 403);
//     }

//     $request->validate([
//         'start_date' => 'required|date',
//         'end_date'   => 'required|date|after_or_equal:start_date',
//     ]);

//     $startDate = Carbon::parse($request->start_date)->startOfDay();
//     $endDate   = Carbon::parse($request->end_date)->endOfDay();
//     $dateRange = $startDate->format('d-m-Y') . ' to ' . $endDate->format('d-m-Y');
//     $cid = $user->cid;

//     $report = DB::table('sales_items as si')
//         ->join('sales_bills as sb', 'si.bid', '=', 'sb.id')
//         ->join('products as p', 'si.pid', '=', 'p.id')
//         ->join('sales_clients as sc', 'sb.scid', '=', 'sc.id')
//         ->leftJoin('users as u', 'sb.uid', '=', 'u.id')
//         ->where('u.cid', $cid)
//         ->where(function ($q) {
//             $q->whereNull('sc.gst_no')->orWhere('sc.gst_no', '');
//         })
//         ->whereBetween('sb.updated_at', [$startDate, $endDate])
//         ->select(
//             'p.id',
//             'p.name as item_name',
//             'p.hscode',
//             'si.gst as gst_rate',
//             DB::raw('SUM(si.quantity * si.s_price * (1 - COALESCE(si.dis, 0)/100)) as taxable_amount'),
//             DB::raw('SUM(si.quantity * si.s_price * (1 - COALESCE(si.dis, 0)/100) * (COALESCE(si.gst, 0)/100)) as total_gst')
//         )
//         ->groupBy('p.id', 'p.name', 'p.hscode', 'si.gst')
//         ->orderBy('p.name')
//         ->get();

//     $finalReport = $report->map(function ($row) {
//         $gstRate = (float)$row->gst_rate;
//         return (object)[
//             'item_name'      => $row->item_name,
//             'hscode'         => $row->hscode ?: 'N/A',
//             'cgst_rate'      => $gstRate > 0 ? number_format($gstRate / 2, 1) : '0.0',
//             'sgst_rate'      => $gstRate > 0 ? number_format($gstRate / 2, 1) : '0.0',
//             'igst_rate'      => '0.0',
//             'taxable_amount' => round($row->taxable_amount, 2),
//             'total_gst'      => round($row->total_gst, 2),
//             'total_amount'   => round($row->taxable_amount + $row->total_gst, 2),
//         ];
//     });

//     if ($finalReport->isEmpty()) {
//         return response()->json(['message' => 'No B2C sales found in this period'], 404);
//     }

//     // THIS WORKS PERFECTLY WHEN ROUTE IS IN web.php
//     // return Excel::download(
//     //     new B2CSalesReportExport($finalReport, $dateRange),
//     //     'B2C_Sales_Report_' . now()->format('d_m_Y') . '.xlsx'
//     // );
//     // THIS ONE LINE FIXES EVERYTHING — WORKS IN POSTMAN, BROWSER, MOBILE, EVERYWHERE
// return response()->streamDownload(function () use ($finalReport, $dateRange) {
//     echo \Maatwebsite\Excel\Facades\Excel::raw(
//         new B2CSalesReportExport($finalReport, $dateRange),
//         \Maatwebsite\Excel\Excel::XLSX
//     );
// }, 'B2C_Sales_Report_' . now()->format('d_m_Y') . '.xlsx');
// }


// public function b2cSalesReport(Request $request)
// {
//     $request->validate([
//         'start_date' => 'required|date',
//         'end_date'   => 'required|date|after_or_equal:start_date',
//     ]);

//     $startDate = $request->start_date . ' 00:00:00';
//     $endDate   = $request->end_date . ' 23:59:59';

//     // Get all B2C bills (customers without GSTIN) created by current user
//     $b2cBills = DB::table('sales_bills as sb')
//         ->join('sales_clients as sc', 'sb.scid', '=', 'sc.id')
//         ->where('sb.uid', auth()->id())
//         ->where(function ($q) {
//             $q->whereNull('sc.gst_no')
//               ->orWhere('sc.gst_no', '=', '')
//               ->orWhereRaw("TRIM(COALESCE(sc.gst_no, '')) = ''");
//         })
//         ->whereBetween('sb.updated_at', [$startDate, $endDate])
//         ->pluck('sb.id');

//     if ($b2cBills->isEmpty()) {
//         return response()->json(['message' => 'No B2C sales found in this period'], 404);
//     }

//     // Aggregate all items from B2C bills → One row per product
//     $data = DB::table('sales_items as si')
//         ->join('products as p', 'si.pid', '=', 'p.id')
//         ->whereIn('si.bid', $b2cBills)
//         ->select(
//             'p.name as item_name',
//             'p.hscode',
//             'si.gst as gst_rate',
//             DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100), 2) as taxable_value'),
//             DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100 * (si.gst / 100)), 2) as gst_amount')
//         )
//         ->groupBy('p.id', 'p.name', 'p.hscode', 'si.gst')
//         ->orderBy('p.name')
//         ->get();

//     if ($data->isEmpty()) {
//         return response()->json(['message' => 'No items found in B2C sales'], 404);
//     }

//     $report = $data->map(function ($row) {
//         $rate = (float)$row->gst_rate;
//         return [
//             'item_name' => $row->item_name,
//             'hsn'       => $row->hscode ?: 'N/A',
//             'cgst'      => $rate > 0 ? round($rate / 2, 1) : 0,
//             'sgst'      => $rate > 0 ? round($rate / 2, 1) : 0,
//             'igst'      => 0,
//             'taxable'   => (float)$row->taxable_value,
//             'gst'       => (float)$row->gst_amount,
//             'total'     => round((float)$row->taxable_value + (float)$row->gst_amount, 2),
//         ];
//     });

//     // Stream Excel directly (NO PhpSpreadsheet error)
//     return response()->streamDownload(function () use ($report, $request) {
//         $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
//         $sheet = $spreadsheet->getActiveSheet();

//         // Title & Period
//         $sheet->setCellValue('A1', 'B2C Sales Report (Unregistered Customers)');
//         $sheet->mergeCells('A1:H1');
//         $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

//         $sheet->setCellValue('A2', 'Period: ' . $request->start_date . ' to ' . $request->end_date);
//         $sheet->mergeCells('A2:H2');

//         // Headers
//         $headers = ['Product Name', 'HSN Code', 'CGST %', 'SGST %', 'IGST %', 'Taxable Value', 'GST Amount', 'Total Amount'];
//         $sheet->fromArray($headers, null, 'A4');

//         // Header Style (Safe way - NO getFill() on Font)
//         $headerStyle = $sheet->getStyle('A4:H4');
//         $headerStyle->getFont()->setBold(true);
//         $headerStyle->getFill()
//             ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
//             ->getStartColor()->setARGB('FFE3F2FD');

//         // Data rows
//         $row = 5;
//         foreach ($report as $item) {
//             $sheet->setCellValue("A$row", $item['item_name']);
//             $sheet->setCellValue("B$row", $item['hsn']);
//             $sheet->setCellValue("C$row", $item['cgst']);
//             $sheet->setCellValue("D$row", $item['sgst']);
//             $sheet->setCellValue("E$row", $item['igst']);
//             $sheet->setCellValue("F$row", $item['taxable']);
//             $sheet->setCellValue("G$row", $item['gst']);
//             $sheet->setCellValue("H$row", $item['total']);
//             $row++;
//         }

//         // Grand Total
//         $lastRow = $row;
//         $sheet->setCellValue("E$lastRow", 'GRAND TOTAL');
//         $sheet->setCellValue("F$lastRow", $report->sum('taxable'));
//         $sheet->setCellValue("G$lastRow", $report->sum('gst'));
//         $sheet->setCellValue("H$lastRow", $report->sum('total'));

//         $totalStyle = $sheet->getStyle("E$lastRow:H$lastRow");
//         $totalStyle->getFont()->setBold(true);
//         $totalStyle->getFill()
//             ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
//             ->getStartColor()->setARGB('FFFFFF00'); // Yellow

//         // Auto-size + borders
//         foreach (range('A', 'H') as $col) {
//             $sheet->getColumnDimension($col)->setAutoSize(true);
//         }

//         $sheet->getStyle("A4:H$lastRow")->applyFromArray([
//             'borders' => [
//                 'allBorders' => [
//                     'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
//                     'color' => ['argb' => 'FF000000'],
//                 ],
//             ],
//         ]);

//         $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
//         $writer->save('php://output');
//     }, 'B2C_Report_' . $request->start_date . '_to_' . $request->end_date . '.xlsx', [
//         'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
//     ]);
// }

public function b2cSalesReport(Request $request)
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
    ]);

    $startDate = $request->start_date . ' 00:00:00';
    $endDate   = $request->end_date . ' 23:59:59';

    // Get all B2C bills (customers without GSTIN) created by current user
    $b2cBills = DB::table('sales_bills as sb')
        ->join('sales_clients as sc', 'sb.scid', '=', 'sc.id')
        ->where('sb.uid', auth()->id())
        ->where(function ($q) {
            $q->whereNull('sc.gst_no')
              ->orWhere('sc.gst_no', '=', '')
              ->orWhereRaw("TRIM(COALESCE(sc.gst_no, '')) = ''");
        })
        ->whereBetween('sb.updated_at', [$startDate, $endDate])
        ->pluck('sb.id');

    if ($b2cBills->isEmpty()) {
        return response()->json(['message' => 'No B2C sales found in this period'], 404);
    }

    // Aggregate items ONLY from products that have a valid HSN code
    $data = DB::table('sales_items as si')
        ->join('products as p', 'si.pid', '=', 'p.id')
        ->whereIn('si.bid', $b2cBills)
        ->whereNotNull('p.hscode')                    // HSN must exist
        ->where('p.hscode', '!=', '')                 // Not empty string
        ->whereRaw("TRIM(p.hscode) != ''")             // Not just spaces
        //->whereRaw("TRIM(p.hscode) ~ '^[0-9]{1,9}$'")   // Only valid HSN (1-9 digits)
        ->whereRaw("REGEXP_REPLACE(p.hscode, '\\s+', '', 'g') ~ '^[0-9]{1,20}$'")
        ->select(
            'p.name as item_name',
            'p.hscode',
            'si.gst as gst_rate',
            DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100), 2) as taxable_value'),
            DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100 * (si.gst / 100)), 2) as gst_amount')
        )
        ->groupBy('p.id', 'p.name', 'p.hscode', 'si.gst')
        ->orderBy('p.name')
        ->get();

    if ($data->isEmpty()) {
        return response()->json(['message' => 'No B2C sales found with products having HSN code in this period'], 404);
    }

    $report = $data->map(function ($row) {
        $rate = (float)$row->gst_rate;
        return [
            'item_name' => $row->item_name,
            'hsn'       => $row->hscode,  // Guaranteed to be valid now
            'cgst'      => $rate > 0 ? round($rate / 2, 1) : 0,
            'sgst'      => $rate > 0 ? round($rate / 2, 1) : 0,
            'igst'      => 0,
            'taxable'   => (float)$row->taxable_value,
            'gst'       => (float)$row->gst_amount,
            'total'     => round((float)$row->taxable_value + (float)$row->gst_amount, 2),
        ];
    });

    // Stream Excel Download
    return response()->streamDownload(function () use ($report, $request) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Title
        $sheet->setCellValue('A1', 'B2C Sales Report (Unregistered Customers - HSN Wise)');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        // Period
        $sheet->setCellValue('A2', 'Period: ' . $request->start_date . ' to ' . $request->end_date);
        $sheet->mergeCells('A2:H2');

        // Headers
        $headers = ['Product Name', 'HSN Code', 'CGST %', 'SGST %', 'IGST %', 'Taxable Value', 'GST Amount', 'Total Amount'];
        $sheet->fromArray($headers, null, 'A4');

        // Header Style
        $headerStyle = $sheet->getStyle('A4:H4');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD');

        // Data Rows
        $row = 5;
        foreach ($report as $item) {
            $sheet->setCellValue("A$row", $item['item_name']);
            $sheet->setCellValue("B$row", $item['hsn']);
            $sheet->setCellValue("C$row", $item['cgst']);
            $sheet->setCellValue("D$row", $item['sgst']);
            $sheet->setCellValue("E$row", $item['igst']);
            $sheet->setCellValue("F$row", $item['taxable']);
            $sheet->setCellValue("G$row", $item['gst']);
            $sheet->setCellValue("H$row", $item['total']);
            $row++;
        }

        // Grand Total Row
        $lastRow = $row;
        $sheet->setCellValue("E$lastRow", 'GRAND TOTAL');
        $sheet->getStyle("E$lastRow")->getFont()->setBold(true);
        $sheet->setCellValue("F$lastRow", $report->sum('taxable'));
        $sheet->setCellValue("G$lastRow", $report->sum('gst'));
        $sheet->setCellValue("H$lastRow", $report->sum('total'));

        // Total Row Style
        $totalStyle = $sheet->getStyle("E$lastRow:H$lastRow");
        $totalStyle->getFont()->setBold(true);
        $totalStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00');

        // Auto-size columns
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Apply borders to entire data
        $sheet->getStyle("A4:H$lastRow")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
    }, 'B2C_HSN_Report_' . $request->start_date . '_to_' . $request->end_date . '.xlsx', [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}


//B2B Sales Report

// public function b2bSalesReport(Request $request)
// {
//     $request->validate([
//         'start_date' => 'required|date',
//         'end_date'   => 'required|date|after_or_equal:start_date',
//     ]);

//     $user = Auth::user();
//     $start = $request->start_date . ' 00:00:00';
//     $end   = $request->end_date . ' 23:59:59';

//     // GET COMPANY GST FROM clients TABLE (your actual company table)
//     $company = DB::table('clients')->where('id', $user->cid)->first();

//     if (!$company || !$company->gst_no) {
//         return response()->json(['message' => 'Company GST not set. Please update company profile.'], 400);
//     }

//     $companyStateCode = substr(trim($company->gst_no), 0, 2);

//     // Get all B2B bills (only registered customers)
//     $bills = DB::table('sales_bills as sb')
//         ->join('sales_clients as sc', 'sb.scid', '=', 'sc.id')
//         ->where('sb.uid', $user->id)
//         ->where('sc.cid', $user->cid)
//         ->whereNotNull('sc.gst_no')
//         ->where('sc.gst_no', '!=', '')
//         ->whereBetween('sb.updated_at', [$start, $end])
//         ->select(
//             'sb.id',
//             'sb.bill_name as invoice_no',
//             'sb.updated_at as bill_date',
//             'sc.name as customer_name',
//             'sc.gst_no as customer_gst'
//         )
//         ->orderBy('sb.updated_at')
//         ->get();

//     if ($bills->isEmpty()) {
//         return response()->json(['message' => 'No B2B sales found in this period'], 404);
//     }

//     // Get items (merge same product in same invoice)
//     $items = DB::table('sales_items as si')
//         ->join('products as p', 'si.pid', '=', 'p.id')
//         ->whereIn('si.bid', $bills->pluck('id'))
//         ->select(
//             'si.bid',
//             'p.name as item_name',
//             'p.hscode',
//             'si.gst as gst_rate',
//             DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100), 2) as taxable_amount'),
//             DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100 * (si.gst / 100)), 2) as gst_amount')
//         )
//         ->groupBy('si.bid', 'p.id', 'p.name', 'p.hscode', 'si.gst')
//         ->get()
//         ->groupBy('bid');

//     return response()->streamDownload(function () use ($bills, $items, $companyStateCode, $request) {
//         $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
//         $sheet = $spreadsheet->getActiveSheet();

//         // Title
//         $sheet->setCellValue('A1', 'B2B Sales Report (Registered Dealers) - GSTR-1');
//         $sheet->mergeCells('A1:N1');
//         $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
//         $sheet->setCellValue('A2', "Period: {$request->start_date} to {$request->end_date}");
//         $sheet->mergeCells('A2:N2');

//         // Headers
//         $headers = ['Sl.', 'Inv No', 'Date', 'Customer', 'GSTIN', '', 'Item', 'HSN', 'CGST%', 'SGST%', 'IGST%', 'Taxable', 'GST', 'Total'];
//         $sheet->fromArray($headers, null, 'A4');
//         $sheet->getStyle('A4:N4')->getFont()->setBold(true);
//         $sheet->getStyle('A4:N4')->getFill()
//             ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
//             ->getStartColor()->setARGB('FFE3F2FD');

//         $row = 5;
//         $sl = 1;

//         foreach ($bills as $bill) {
//             $custState = substr(trim($bill->customer_gst), 0, 2);
//             $sameState = ($custState === $companyStateCode);

//             $billItems = $items->get($bill->id, collect());
//             $first = true;

//             foreach ($billItems as $item) {
//                 $cgst = $sameState ? round($item->gst_rate / 2, 1) : 0;
//                 $sgst = $sameState ? round($item->gst_rate / 2, 1) : 0;
//                 $igst = $sameState ? 0 : $item->gst_rate;

//                 $sheet->setCellValue("A$row", $first ? $sl : '');
//                 $sheet->setCellValue("B$row", $first ? $bill->invoice_no : '');
//                 $sheet->setCellValue("C$row", $first ? date('d-m-Y', strtotime($bill->bill_date)) : '');
//                 $sheet->setCellValue("D$row", $first ? $bill->customer_name : '');
//                 $sheet->setCellValue("E$row", $first ? $bill->customer_gst : '');

//                 $sheet->setCellValue("G$row", $item->item_name);
//                 $sheet->setCellValue("H$row", $item->hscode ?? 'N/A');
//                 $sheet->setCellValue("I$row", $cgst);
//                 $sheet->setCellValue("J$row", $sgst);
//                 $sheet->setCellValue("K$row", $igst);
//                 $sheet->setCellValue("L$row", $item->taxable_amount);
//                 $sheet->setCellValue("M$row", $item->gst_amount);
//                 $sheet->setCellValue("N$row", round($item->taxable_amount + $item->gst_amount, 2));

//                 if ($first) { $sl++; $first = false; }
//                 $row++;
//             }
//             $row++; // space between invoices
//         }

//         // Grand Total
//         $last = $row;
//         $sheet->setCellValue("K$last", 'GRAND TOTAL');
//         $sheet->setCellValue("L$last", '=SUM(L5:L'.($last-1).')');
//         $sheet->setCellValue("M$last", '=SUM(M5:M'.($last-1).')');
//         $sheet->setCellValue("N$last", '=SUM(N5:N'.($last-1).')');
//         $sheet->getStyle("K$last:N$last")->getFont()->setBold(true);
//         $sheet->getStyle("K$last:N$last")->getFill()
//             ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
//             ->getStartColor()->setARGB('FFFFFF00');

//         foreach (range('A', 'N') as $col) {
//             $sheet->getColumnDimension($col)->setAutoSize(true);
//         }

//         $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
//         $writer->save('php://output');
//     }, 'B2B_GSTR1_' . $request->start_date . '_to_' . $request->end_date . '.xlsx');
// }

public function b2bSalesReport(Request $request)
{
    $request->validate([
        'start_date' => 'required|date',
        'end_date'   => 'required|date|after_or_equal:start_date',
    ]);

    $user = Auth::user();
    $start = $request->start_date . ' 00:00:00';
    $end   = $request->end_date . ' 23:59:59';

    // Get Company GSTIN
    $company = DB::table('clients')->where('id', $user->cid)->first();
    if (!$company || empty(trim($company->gst_no ?? ''))) {
        return response()->json(['message' => 'Company GSTIN not configured.'], 400);
    }
    $companyStateCode = substr(trim($company->gst_no), 0, 2);

    // Get B2B Bills (Registered Customers Only)
    $bills = DB::table('sales_bills as sb')
        ->join('sales_clients as sc', 'sb.scid', '=', 'sc.id')
        ->where('sb.uid', $user->id)
        ->whereNotNull('sc.gst_no')
        ->where('sc.gst_no', '!=', '')
        ->whereRaw("TRIM(sc.gst_no) != ''")
        ->whereBetween('sb.updated_at', [$start, $end])
        ->select(
            'sb.id',
            'sb.bill_name as invoice_no',
            'sb.updated_at as bill_date',
            'sc.name as customer_name',
            'sc.gst_no as customer_gst'
        )
        ->orderBy('sb.updated_at')
        ->get();

    if ($bills->isEmpty()) {
        return response()->json(['message' => 'No B2B invoices found in this period.'], 404);
    }

    // Get ONLY items with valid HSN code
    $items = DB::table('sales_items as si')
        ->join('products as p', 'si.pid', '=', 'p.id')
        ->whereIn('si.bid', $bills->pluck('id'))
        ->whereNotNull('p.hscode')
        ->where('p.hscode', '!=', '')
        ->whereRaw("TRIM(p.hscode) != ''")
        ->select(
            'si.bid',
            'p.name as item_name',
            'p.hscode',
            'si.gst as gst_rate',
            DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100), 2) as taxable_amount'),
            DB::raw('ROUND(SUM(si.quantity * si.s_price * (100 - COALESCE(si.dis, 0)) / 100 * (si.gst / 100)), 2) as gst_amount')
        )
        ->groupBy('si.bid', 'p.id', 'p.name', 'p.hscode', 'si.gst')
        ->get()
        ->groupBy('bid');

    if ($items->flatten(1)->isEmpty()) {
        return response()->json(['message' => 'No items with valid HSN code found in B2B sales.'], 404);
    }

    return response()->streamDownload(function () use ($bills, $items, $companyStateCode, $request) {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Title
        $sheet->setCellValue('A1', 'B2B Sales Report - Only HSN Products (GSTR-1 Ready)');
        $sheet->mergeCells('A1:N1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        // Period
        $sheet->setCellValue('A2', "Period: {$request->start_date} to {$request->end_date}");
        $sheet->mergeCells('A2:N2');

        // Headers
        $headers = ['Sl', 'Invoice No', 'Date', 'Customer Name', 'GSTIN', '', 'Product', 'HSN', 'CGST %', 'SGST %', 'IGST %', 'Taxable Value', 'GST Amount', 'Total Amount'];
        $sheet->fromArray($headers, null, 'A4');

        // Header Style (CORRECT WAY - No getFill() on Font)
        $headerStyle = $sheet->getStyle('A4:N4');
        $headerStyle->getFont()->setBold(true);
        $headerStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE3F2FD'); // Light blue

        $row = 5;
        $sl = 1;

        foreach ($bills as $bill) {
            $customerState = substr(trim($bill->customer_gst), 0, 2);
            $isSameState = ($customerState === $companyStateCode);

            $billItems = $items->get($bill->id, collect());
            if ($billItems->isEmpty()) continue; // Skip invoice if no HSN items

            $firstItem = true;

            foreach ($billItems as $item) {
                $cgst = $isSameState ? round($item->gst_rate / 2, 1) : 0;
                $sgst = $isSameState ? round($item->gst_rate / 2, 1) : 0;
                $igst = $isSameState ? 0 : $item->gst_rate;

                $sheet->setCellValue("A$row", $firstItem ? $sl : '');
                $sheet->setCellValue("B$row", $firstItem ? $bill->invoice_no : '');
                $sheet->setCellValue("C$row", $firstItem ? date('d-m-Y', strtotime($bill->bill_date)) : '');
                $sheet->setCellValue("D$row", $firstItem ? $bill->customer_name : '');
                $sheet->setCellValue("E$row", $firstItem ? $bill->customer_gst : '');

                $sheet->setCellValue("G$row", $item->item_name);
                $sheet->setCellValue("H$row", $item->hscode);
                $sheet->setCellValue("I$row", $cgst);
                $sheet->setCellValue("J$row", $sgst);
                $sheet->setCellValue("K$row", $igst);
                $sheet->setCellValue("L$row", $item->taxable_amount);
                $sheet->setCellValue("M$row", $item->gst_amount);
                $sheet->setCellValue("N$row", round($item->taxable_amount + $item->gst_amount, 2));

                if ($firstItem) {
                    $sl++;
                    $firstItem = false;
                }
                $row++;
            }
            $row++; // Empty row between invoices
        }

        // Grand Total Row
        $lastRow = $row;
        $sheet->setCellValue("K{$lastRow}", 'GRAND TOTAL');
        $sheet->setCellValue("L{$lastRow}", '=SUM(L5:L'.($lastRow-1).')');
        $sheet->setCellValue("M{$lastRow}", '=SUM(M5:M'.($lastRow-1).')');
        $sheet->setCellValue("N{$lastRow}", '=SUM(N5:N'.($lastRow-1).')');

        // CORRECT Styling for Grand Total (Yellow + Bold)
        $totalStyle = $sheet->getStyle("K{$lastRow}:N{$lastRow}");
        $totalStyle->getFont()->setBold(true);
        $totalStyle->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF00'); // Yellow

        // Auto-size columns
        foreach (range('A', 'N') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Borders for entire table
        $sheet->getStyle("A4:N{$lastRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => 'FF000000'],
                ],
            ],
        ]);

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');

    }, 'B2B_GSTR1_HSN_Only_' . $request->start_date . '_to_' . $request->end_date . '.xlsx', [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}

}