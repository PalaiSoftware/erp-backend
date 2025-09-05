<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PurchaseBill;
use App\Models\PurchaseItem;
use App\Models\PaymentMode;
use App\Models\Product;
use App\Models\ProductInfo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function store(Request $request)
    {
        // Force JSON response
        $request->headers->set('Accept', 'application/json');

        // Get the authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Restrict to rid 1, 2, 3,4
        if (!in_array($user->rid, [1, 2, 3,4])) {
            return response()->json(['message' => 'Unauthorized to purchase product'], 403);
        }
        // Get company ID from user
        $cid = $user->cid;
        if (!$cid) {
            return response()->json(['message' => 'User company ID not found'], 400);
        }

        // Log the incoming request before validation
        Log::info('Incoming purchase request', ['request_data' => $request->all()]);

        // Validate the request with logging for errors
        try {
            $validated = $request->validate([
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|numeric|min:0',
                'products.*.p_price' => 'required|numeric|min:0',
                'products.*.s_price' => 'nullable|numeric|min:0',
                'products.*.unit_id' => 'required|integer|exists:units,id',
                'products.*.dis' => 'nullable|numeric|min:0|max:100',
                'products.*.gst' => 'nullable|numeric|min:0', // Added GST validation
                'vendor_id' => 'required|integer|exists:purchase_clients,id',
                'bill_name' => 'string|nullable|max:255',
                'payment_mode' => 'required|integer|exists:payment_modes,id',
                'purchase_date' => 'required|date_format:Y-m-d H:i:s',
                'absolute_discount' => 'nullable|numeric|min:0',
                'paid_amount' => 'nullable|numeric|min:0',
            ]);
            Log::info('Validation passed successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', [
                'errors' => $e->errors(),
                'request_data' => $request->all()
            ]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }
      // Determine bill_name
if ($request->filled('bill_name')) {
    $billName = $request->bill_name;
} else {
    $vendor = DB::table('purchase_clients')->where('id', $validated['vendor_id'])->first();
    $vendorName = $vendor ? $vendor->name : 'Unknown Vendor';
    $formattedDate = substr($validated['purchase_date'], 0, 10); // Extracts 'Y-m-d'
    $billName = $vendorName . ' - ' . $formattedDate;
}
        
        // Use a transaction to ensure data consistency
        DB::beginTransaction();
        try {
            $purchaseDate = $validated['purchase_date'];

            // Step 1: Create the purchase bill
            $purchaseBill = PurchaseBill::create([
                'bill_name' => $billName,
                'pcid' => $validated['vendor_id'],
                'uid' => $user->id,
                'payment_mode' => $validated['payment_mode'],
                'absolute_discount' => $validated['absolute_discount'] ?? 0,
                'paid_amount' => $validated['paid_amount'] ?? 0,
                'created_at' => $purchaseDate,
                'updated_at' => $purchaseDate,
            ]);
            $billId = $purchaseBill->id;
            Log::info('Purchase bill created', ['bill_id' => $billId]);

            // Step 2: Process each product
            foreach ($validated['products'] as $product) {
                // Create purchase item record
                $pid = (int) $product['product_id']; // Explicitly cast to integer
                PurchaseItem::create([
                    'bid' => $billId,
                    'pid' => $pid,
                    'p_price' => $product['p_price'],
                    's_price' => $product['s_price'] ?? 0,
                    'quantity' => $product['quantity'],
                    'unit_id' => $product['unit_id'],
                    'dis' => $product['dis'] ?? 0,
                    'gst' => $product['gst'] ?? 0, // Added GST to purchase item
                    'created_at' => $purchaseDate,
                    'updated_at' => $purchaseDate,
                ]);
                Log::info('Purchase item created', [
                    'bill_id' => $billId,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'unit_id' => $product['unit_id'],
                    'dis' => $product['dis'] ?? 0,
                    'gst' => $product['gst'] ?? 0, 
                ]);
                // Step 3: Update or create product_info (per cid and pid)
                $productModel = Product::find($pid);
                if (!$productModel) {
                    throw new \Exception("Product not found for pid: {$pid}");
                }

                $new_unit = $product['unit_id'];
                $new_p_price = $product['p_price'];
                $new_s_price = $product['s_price'] ?? 0;
                $new_gst = $product['gst'] ?? 0;

                // Check if product_info record exists for pid and cid
                $info = ProductInfo::where('pid', $pid)->where('cid', $cid)->first();

                // Prepare data for create or update
                $current_unit = $info ? $info->unit_id : $new_unit;
                $converted_p = $new_p_price;
                $converted_s = $new_s_price;

                // Handle unit conversion if necessary
                if ($info && $new_unit != $current_unit) {
                    $p_unit = $productModel->p_unit; // e.g., box
                    $s_unit = $productModel->s_unit; // e.g., piece
                    $c_factor = $productModel->c_factor; // e.g., 20 pieces per box

                    if ($c_factor == 0) {
                        throw new \Exception("Conversion factor is zero for product ID {$pid}, cannot convert units");
                    }

                    // Convert new prices to the stored unit in product_info
                    // c_factor = number of secondary units (piece) per primary unit (box)
                    if ($new_unit == $p_unit && $current_unit == $s_unit) {
                        // New in box, stored in piece: divide by c_factor
                        $converted_p = $new_p_price / $c_factor;
                        $converted_s = $new_s_price / $c_factor;
                    } elseif ($new_unit == $s_unit && $current_unit == $p_unit) {
                        // New in piece, stored in box: multiply by c_factor
                        $converted_p = $new_p_price * $c_factor;
                        $converted_s = $new_s_price * $c_factor;
                    } else {
                        throw new \Exception("Unsupported unit conversion for product ID {$pid}: new unit {$new_unit}, current unit {$current_unit}");
                    }

                    Log::info('Prices converted for product', [
                        'pid' => $pid,
                        'cid' => $cid,
                        'original_p_price' => $new_p_price,
                        'converted_p_price' => $converted_p,
                        'original_s_price' => $new_s_price,
                        'converted_s_price' => $converted_s,
                        'new_unit' => $new_unit,
                        'current_unit' => $current_unit,
                        'c_factor' => $c_factor,
                    ]);
                }

                // Prepare data for create or update
                $productInfoData = [
                    'pid' => $pid,
                    'hsn_code' => $productModel->hscode,
                    'description' => null,
                    'unit_id' => $current_unit,
                    'purchase_price' => $converted_p,
                    'profit_percentage' => 0,
                    'pre_gst_sale_cost' => $converted_s,
                    'gst' => $new_gst,
                    'post_gst_sale_cost' => $converted_s * (1 + ($new_gst / 100)),
                    'uid' => $user->id,
                    'cid' => $cid,
                    'created_at' => $purchaseDate,
                    'updated_at' => $purchaseDate,
                ];

                if (!$info) {
                    // Create new product_info record
                    ProductInfo::create($productInfoData);
                    Log::info('Product info created', [
                        'pid' => $pid,
                        'cid' => $cid,
                        'unit_id' => $current_unit,
                        'purchase_price' => $converted_p,
                        'pre_gst_sale_cost' => $converted_s,
                        'gst' => $new_gst,
                    ]);
                } else {
                    // Update existing product_info record for specific pid and cid
                    $affectedRows = ProductInfo::where('pid', $pid)
                        ->where('cid', $cid)
                        ->update([
                            'purchase_price' => $converted_p,
                            'pre_gst_sale_cost' => $converted_s,
                            'gst' => $new_gst,
                            'post_gst_sale_cost' => $converted_s * (1 + ($new_gst / 100)),
                            'updated_at' => $purchaseDate,
                        ]);

                    if ($affectedRows !== 1) {
                        Log::warning('Unexpected number of rows updated in product_info', [
                            'pid' => $pid,
                            'cid' => $cid,
                            'affected_rows' => $affectedRows,
                        ]);
                    }

                    Log::info('Product info updated', [
                        'pid' => $pid,
                        'cid' => $cid,
                        'unit_id' => $current_unit,
                        'purchase_price' => $converted_p,
                        'pre_gst_sale_cost' => $converted_s,
                        'gst' => $new_gst,
                        'affected_rows' => $affectedRows,
                    ]);
                }
            }

            // Step 4: Commit the transaction
            DB::commit();
            Log::info('Transaction committed', ['bill_id' => $billId]);

            return response()->json([
                'message' => 'Purchases recorded successfully',
                'transaction_id' => $billId,
                'transaction' => $purchaseBill,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Purchase failed',
                'error' => $e->getMessage(),
            ], 500);
        }
}   
// public function getTransactionsByCid(Request $request)
// {
//     // Get the authenticated user
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     // Extract uid from the authenticated user
//     $uid = $user->id;

//     // Restrict access to users with rid between 1 and 5 inclusive
//     if ($user->rid < 1 || $user->rid > 5) {
//         return response()->json(['message' => 'Forbidden'], 403);
//     }

//     // Validate the request data
//     $request->validate([
//         'cid' => 'required|integer'
//     ]);

//     // Extract cid from the request
//     $cid = $request->input('cid');

//     // Check if the user belongs to the requested company
//     if ($user->cid != $cid) {
//         return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
//     }

//     try {
//         // Log successful validation
//         Log::info('Validation passed successfully for getTransactionsByCid', ['cid' => $cid]);

//         // Build the query with the purchase_bills table
//         $query = DB::table('purchase_bills as pb')
//             ->select(
//                 'pb.id as transaction_id',          // Bill ID as transaction ID
//                 'pb.bill_name as bill_name',
//                 'pc.name as vendor_name',           // Vendor name from purchase_clients
//                 'pb.pcid as vendor_id',             // Vendor ID from purchase_bills
//                 'pb.payment_mode',                  // Payment mode integer
//                 'pb.updated_at as date',            // Date of the transaction
//                 'u.name as purchased_by'            // Name of the user who made the purchase
//             )
//             ->leftJoin('purchase_clients as pc', 'pb.pcid', '=', 'pc.id')  // Join with vendors
//             ->leftJoin('users as u', 'pb.uid', '=', 'u.id')                // Join with users
//             ->where('u.cid', $cid)                                   // Filter by company ID
//             ->orderBy('pb.updated_at', 'desc');      
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
//             Log::info('No transactions found for cid', ['cid' => $cid]);
//             return response()->json([
//                 'status' => 'error',
//                 'message' => 'No transactions found for this customer ID'
//             ], 404);
//         }

//         // Return successful response
//         Log::info('Transactions retrieved successfully', ['cid' => $cid, 'count' => $transactions->count()]);
//         return response()->json([
//             'status' => 'success',
//             'data' => $transactions
//         ], 200);
//     } catch (\Exception $e) {
//         Log::error('Failed to fetch transactions', ['cid' => $cid, 'error' => $e->getMessage()]);
//         return response()->json(['message' => 'Failed to fetch transactions', 'error' => $e->getMessage()], 500);
//     }
// }

public function getTransactionsByCid(Request $request)
{
    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Extract uid from the authenticated user
    $uid = $user->id;
    $userRid = $user->rid;

    // Validate the request data
    $request->validate([
        'cid' => 'required|integer'
    ]);

    // Extract cid from the request
    $cid = $request->input('cid');

    // Check if the user belongs to the requested company
    if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }

    // Determine which RIDs the user can view based on their own RID
    $allowedRids = [];
    switch ($userRid) {
        case 1: // Admin - can see everyone
            $allowedRids = [1, 2, 3, 4, 5];
            break;
        case 2: // Superuser - can see Superuser, Moderator, Authenticated, Anonymous
            $allowedRids = [2, 3, 4, 5];
            break;
        case 3: // Moderator - can see Moderator, Authenticated, Anonymous
            $allowedRids = [3, 4, 5];
            break;
        case 4: // Authenticated - can only see Authenticated
        case 5: // Anonymous - can only see Anonymous
            $allowedRids = [$userRid];
            break;
        default:
            // For safety, deny access if rid is not recognized
            return response()->json(['message' => 'Forbidden: Invalid role'], 403);
    }

    try {
        // Log successful validation
        Log::info('Validation passed successfully for getTransactionsByCid', [
            'cid' => $cid, 
            'user_rid' => $userRid,
            'allowed_rids' => $allowedRids
        ]);

        // Build the query with the purchase_bills table
        $query = DB::table('purchase_bills as pb')
            ->select(
                'pb.id as transaction_id',          // Bill ID as transaction ID
                'pb.bill_name as bill_name',
                'pc.name as vendor_name',           // Vendor name from purchase_clients
                'pb.pcid as vendor_id',             // Vendor ID from purchase_bills
                'pb.payment_mode',                  // Payment mode integer
                'pb.updated_at as date',            // Date of the transaction
                'u.name as purchased_by',           // Name of the user who made the purchase
                'u.rid as purchaser_rid'            // Include RID for debugging
            )
            ->leftJoin('purchase_clients as pc', 'pb.pcid', '=', 'pc.id')  // Join with vendors
            ->leftJoin('users as u', 'pb.uid', '=', 'u.id')                // Join with users
            ->where('u.cid', $cid)                                        // Filter by company ID
            ->whereIn('u.rid', $allowedRids)                              // NEW: Filter by allowed RIDs
            ->orderBy('pb.updated_at', 'desc');      

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
            Log::info('No transactions found for cid', ['cid' => $cid, 'allowed_rids' => $allowedRids]);
            return response()->json([
                'status' => 'error',
                'message' => 'No transactions found for this customer ID'
            ], 404);
        }

        // Return successful response
        Log::info('Transactions retrieved successfully', [
            'cid' => $cid, 
            'count' => $transactions->count(),
            'allowed_rids' => $allowedRids
        ]);
        
        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ], 200);
    } catch (\Exception $e) {
        Log::error('Failed to fetch transactions', [
            'cid' => $cid, 
            'user_rid' => $userRid,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'message' => 'Failed to fetch transactions', 
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getPurchaseDetailsByTransaction(Request $request)
    {
        // Authentication check
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        //Role-based access control
        if ($user->rid < 1 || $user->rid > 4) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Request validation
        try {
            $request->validate([
                'transaction_id' => 'required|integer'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $transactionId = $request->input('transaction_id');

        // Fetch transaction details
        $transaction = DB::table('purchase_bills')
            ->where('id', $transactionId)
            ->select('id', 'bill_name', 'pcid', 'uid', 'payment_mode', 'absolute_discount', 'paid_amount', 'updated_at')
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

        // Fetch purchase items with calculated fields
        $purchaseDetails = DB::table('purchase_items as pi')
            ->join('products as prod', 'pi.pid', '=', 'prod.id')
            ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
            ->join('units as u', 'pi.unit_id', '=', 'u.id')
            ->select(
                'pi.pid as product_id',
                'prod.name as product_name',
                'pi.s_price as selling_price',
                'pi.p_price as per_item_cost',
                'pi.dis as discount',
                DB::raw('ROUND(pi.p_price * (1 - COALESCE(pi.dis, 0)/100), 2) AS net_price'),
                'pi.quantity',
                DB::raw('ROUND(pi.quantity * pi.p_price * (1 - COALESCE(pi.dis, 0)/100), 2) AS per_product_total'),
                'pi.gst as gst',
                DB::raw('ROUND((pi.quantity * pi.p_price * (1 - COALESCE(pi.dis, 0)/100)) * (COALESCE(pi.gst, 0)/100), 2) AS gst_amount'),
                'pi.unit_id',
                'u.name as unit_name'
            )
            ->where('pi.bid', $transactionId)
            ->get();

        if ($purchaseDetails->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No purchase details found for this transaction ID'
            ], 404);
        }

        // Calculate financial totals
        $totalItemNetValue = $purchaseDetails->sum('per_product_total');
        $totalGstAmount = $purchaseDetails->sum('gst_amount');
        $totalAmount = $totalItemNetValue + $totalGstAmount;
        $payableAmount = $totalAmount - $absoluteDiscount;
        $dueAmount = max(0, $payableAmount - $paidAmount);

        // Fetch payment modes
        $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();
        $paymentModeName = $paymentModes[$transaction->payment_mode] ?? 'Unknown';

        // Fetch vendor details
        $vendor = DB::table('purchase_clients')
            ->where('id', $transaction->pcid)
            ->select('name as vendor_name')
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
                'purchased_by' => $userDetail ? $userDetail->name : 'Unknown',
                'vendor_name' => $vendor ? $vendor->vendor_name : 'Unknown',
                'vendor_id' => $transaction->pcid,
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

public function getPurchaseWidget(Request $request)
{
    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Validate the request
    $validated = $request->validate([
        'cid' => 'required|integer|exists:clients,id'
    ]);
    $cid = $validated['cid'];
    $rid = $user->rid;
    $uid = $user->id;
     // Check if the user belongs to the requested company
     if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }

    // Total Purchase Orders
    $purchaseCount = PurchaseBill::join('users', 'purchase_bills.uid', '=', 'users.id')
        ->where('users.cid', $cid)
        ->when(!in_array($rid, [1, 2, 3]), fn($q) => $q->where('purchase_bills.uid', $uid))
        ->count();

    // Total Vendors
    $vendorCount = PurchaseBill::join('users', 'purchase_bills.uid', '=', 'users.id')
        ->where('users.cid', $cid)
        ->distinct()
        ->count('purchase_bills.pcid');

    // Total Purchase Amount
    $totalAmount = DB::table(function ($query) use ($cid, $rid, $uid) {
        $query->from('purchase_items')
            ->join('purchase_bills', 'purchase_items.bid', '=', 'purchase_bills.id')
            ->join('users', 'purchase_bills.uid', '=', 'users.id')
            ->selectRaw('
                purchase_bills.id,
               SUM(purchase_items.quantity * purchase_items.p_price * (1 - COALESCE(purchase_items.dis, 0) / 100) * (1 + COALESCE(purchase_items.gst, 0) / 100)) AS transaction_total,
                MAX(purchase_bills.absolute_discount) AS absolute_discount
            ')
            ->where('users.cid', $cid)
            ->when(!in_array($rid, [1, 2, 3]), fn($q) => $q->where('purchase_bills.uid', $uid))
            ->groupBy('purchase_bills.id');
    }, 'transaction_sums')
    ->sum(DB::raw('transaction_total - COALESCE(absolute_discount, 0)'));

    // Return the response
    return response()->json([
        'total_purchase_order' => $purchaseCount,
        'total_vendor' => $vendorCount,
        'total_purchase_amount' => round($totalAmount, 2)
    ], 200);
}

public function updateTransactionById(Request $request, $transaction_id)
{
    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid between 5 and 10 inclusive
    if ($user->rid < 1 || $user->rid > 4) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validation rules
    try {
        $request->validate([
            'bill_name' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|integer|exists:payment_modes,id',
            'vendor_id' => 'nullable|integer|exists:purchase_clients,id',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|integer|exists:products,id',
            'products.*.quantity' => 'required_with:products|numeric|min:0',
            'products.*.p_price' => 'required_with:products|numeric|min:0',
            'products.*.s_price' => 'required_with:products|numeric|min:0',
            'products.*.unit_id' => 'required_with:products|integer|exists:units,id',
            'products.*.dis' => 'nullable|numeric|min:0|max:100',
            'products.*.gst' => 'nullable|numeric|min:0', // Added GST validation
            'updated_at' => 'nullable|date_format:Y-m-d H:i:s',
            'absolute_discount' => 'nullable|numeric|min:0',
            'set_paid_amount' => 'nullable|numeric|min:0',
        ]);
        \Log::info('Validation passed successfully for updateTransactionById', ['transaction_id' => $transaction_id]);
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
    $transaction = PurchaseBill::where('id', $transaction_id)->first();
    if (!$transaction) {
        \Log::info('Transaction not found', ['transaction_id' => $transaction_id]);
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }
    // Check if the current user is the one who created this transaction
if ($transaction->uid != $user->id) {
    \Log::warning('Unauthorized transaction update attempt', [
        'transaction_id' => $transaction_id,
        'user_id' => $user->id,
        'transaction_owner_id' => $transaction->uid
    ]);
    return response()->json([
        'message' => 'Forbidden: You do not have permission to update this transaction'
    ], 403);
}

    // Start a database transaction
    DB::beginTransaction();
    try {
        // Prepare update data for purchase_bills
        $updateData = [
            'updated_at' => $request->input('updated_at', now()),
            'created_at' => $request->input('updated_at', now()),
            'bill_name' => $request->input('bill_name', $transaction->bill_name),
            'pcid' => $request->input('vendor_id', $transaction->pcid),
            'payment_mode' => $request->input('payment_mode', $transaction->payment_mode),
            'absolute_discount' => $request->input('absolute_discount', $transaction->absolute_discount),
            'paid_amount' => $request->input('set_paid_amount', $transaction->paid_amount),
        ];

        // Update purchase_bills
        PurchaseBill::where('id', $transaction_id)->update($updateData);

        // Handle products if provided
        if ($request->has('products')) {
            $products = $request->input('products', []);
            $productIds = array_column($products, 'product_id');

            // Fetch existing purchase items
            $existingItems = DB::table('purchase_items')
                ->where('bid', $transaction_id)
                ->get(['pid', 'bid']);

            // Products to remove
            $existingProductIds = $existingItems->pluck('pid')->toArray();
            $productIdsToRemove = array_diff($existingProductIds, $productIds);

            // Remove products not in request
            if (!empty($productIdsToRemove)) {
                DB::table('purchase_items')
                    ->where('bid', $transaction_id)
                    ->whereIn('pid', $productIdsToRemove)
                    ->delete();
            }

            // Insert or update products
            foreach ($products as $product) {
                $item = DB::table('purchase_items')
                    ->where('bid', $transaction_id)
                    ->where('pid', $product['product_id'])
                    ->first();

                if ($item) {
                    // Update existing item
                    DB::table('purchase_items')
                        ->where('bid', $transaction_id)
                        ->where('pid', $product['product_id'])
                        ->update([
                            'p_price' => $product['p_price'],
                            's_price' => $product['s_price'],
                            'quantity' => $product['quantity'],
                            'unit_id' => $product['unit_id'],
                            'dis' => $product['dis'] ?? $product['discount'] ?? 0,
                            'gst' => $product['gst'] ?? $product['gst'] ?? 0,
                        ]);
                } else {
                    // Insert new item
                    DB::table('purchase_items')->insert([
                        'bid' => $transaction_id,
                        'pid' => $product['product_id'],
                        'p_price' => $product['p_price'],
                        's_price' => $product['s_price'],
                        'quantity' => $product['quantity'],
                        'unit_id' => $product['unit_id'],
                        'dis' => $product['dis'] ?? $product['discount'] ?? 0,
                        'gst' => $product['gst'] ?? $product['gst'] ?? 0,
                    ]);
                }
            }
        }

        DB::commit();
        \Log::info('Transaction updated successfully', ['transaction_id' => $transaction_id]);
        return response()->json([
            'status' => 'success',
            'message' => 'Transaction updated successfully'
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Failed to update transaction', [
            'transaction_id' => $transaction_id,
            'error' => $e->getMessage()
        ]);
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to update transaction',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function destroy(Request $request, $transactionId)
{
    Log::info('Delete purchase bill endpoint reached', [
        'bill_id' => $transactionId,
    ]);

    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Restrict to roles 5 and 6 only
    if (!in_array($user->rid, [1,2,3,4])) {
        return response()->json(['message' => 'Unauthorized to delete purchase bill'], 403);
    }

    // Check if bill exists and belongs to the user
    $bill = DB::table('purchase_bills')
        ->where('id', $transactionId)
        ->where('uid', $user->id)
        ->first();

    if (!$bill) {
        return response()->json([
            'message' => 'Unauthorized to delete purchase bill',
        ], 404);
    }

    DB::beginTransaction();
    try {
        // Delete related purchase_items
        DB::table('purchase_items')->where('bid', $transactionId)->delete();
        
        // Delete the bill
        DB::table('purchase_bills')->where('id', $transactionId)->delete();
        
        DB::commit();
        Log::info('Purchase bill deleted successfully', [
            'bill_id' => $transactionId,
        ]);
        
        return response()->json([
            'message' => 'Purchase bill deleted successfully',
            'transaction_id' => $transactionId,
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to delete purchase bill', [
            'bill_id' => $transactionId,
            'error' => $e->getMessage(),
        ]);
        
        return response()->json([
            'message' => 'Failed to delete purchase bill',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}
