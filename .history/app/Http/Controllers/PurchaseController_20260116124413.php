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
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

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
        // if (!in_array($user->rid, [1, 2, 3,4])) {
        //     return response()->json(['message' => 'Unauthorized to purchase product'], 403);
        // }
        if (!in_array($user->rid, [1, 2])) {
    return response()->json(['message' => 'Unauthorized: Only Admin and Superuser can create purchases'], 403);
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
                // if ($info && $new_unit != $current_unit) {
                //     $p_unit = $productModel->p_unit; // e.g., box
                //     $s_unit = $productModel->s_unit; // e.g., piece
                //     $c_factor = $productModel->c_factor; // e.g., 20 pieces per box

                //     if ($c_factor == 0) {
                //         throw new \Exception("Conversion factor is zero for product ID {$pid}, cannot convert units");
                //     }

                //     // Convert new prices to the stored unit in product_info
                //     // c_factor = number of secondary units (piece) per primary unit (box)
                //     if ($new_unit == $p_unit && $current_unit == $s_unit) {
                //         // New in box, stored in piece: divide by c_factor
                //         $converted_p = $new_p_price / $c_factor;
                //         $converted_s = $new_s_price / $c_factor;
                //     } elseif ($new_unit == $s_unit && $current_unit == $p_unit) {
                //         // New in piece, stored in box: multiply by c_factor
                //         $converted_p = $new_p_price * $c_factor;
                //         $converted_s = $new_s_price * $c_factor;
                //     } else {
                //         throw new \Exception("Unsupported unit conversion for product ID {$pid}: new unit {$new_unit}, current unit {$current_unit}");
                //     }

                //     Log::info('Prices converted for product', [
                //         'pid' => $pid,
                //         'cid' => $cid,
                //         'original_p_price' => $new_p_price,
                //         'converted_p_price' => $converted_p,
                //         'original_s_price' => $new_s_price,
                //         'converted_s_price' => $converted_s,
                //         'new_unit' => $new_unit,
                //         'current_unit' => $current_unit,
                //         'c_factor' => $c_factor,
                //     ]);
                // }
                // Handle unit conversion if necessary
if ($info && $new_unit != $current_unit) {
    $p_unit   = $productModel->p_unit;
    $s_unit   = $productModel->s_unit;
    $c_factor = (float) ($productModel->c_factor ?? 0);

    // NEW: Skip conversion safely if factor is zero or product now has only one unit
    if ($c_factor <= 0 || empty($s_unit) || $s_unit == 0) {
        Log::warning('Skipping unit price conversion - invalid factor or single unit now', [
            'pid'         => $pid,
            'new_unit'    => $new_unit,
            'current_unit'=> $current_unit,
            'c_factor'    => $c_factor,
            's_unit'      => $s_unit,
        ]);

        // Use the raw (unconverted) prices from this purchase
        $converted_p = $new_p_price;
        $converted_s = $new_s_price;
    } else {
        // Only convert if we have a valid factor
        if ($new_unit == $p_unit && $current_unit == $s_unit) {
            $converted_p = $new_p_price / $c_factor;
            $converted_s = $new_s_price / $c_factor;
        } elseif ($new_unit == $s_unit && $current_unit == $p_unit) {
            $converted_p = $new_p_price * $c_factor;
            $converted_s = $new_s_price * $c_factor;
        } else {
            throw new \Exception("Unsupported unit conversion for product ID {$pid}: new={$new_unit}, current={$current_unit}");
        }

        Log::info('Prices converted for product', [
            'pid'           => $pid,
            'cid'           => $cid,
            'original_p'    => $new_p_price,
            'converted_p'   => $converted_p,
            'original_s'    => $new_s_price,
            'converted_s'   => $converted_s,
            'c_factor'      => $c_factor,
        ]);
    }
} else {
    // No conversion needed (units match or no existing info)
    $converted_p = $new_p_price;
    $converted_s = $new_s_price;
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


public function getTransactionsByCid(Request $request)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $request->validate([
        'cid' => 'required|integer'
    ]);

    $cid = $request->input('cid');
    $userRid = $user->rid;

    if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }

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
            // Special case: We'll handle these differently below
            break;
        default:
            return response()->json(['message' => 'Forbidden: Invalid role'], 403);
    }

    try {
        // Build base query
        $query = DB::table('purchase_bills as pb')
            ->select(
                'pb.id as transaction_id',
                'pb.bill_name as bill_name',
                'pc.name as vendor_name',
                'pb.pcid as vendor_id',
                'pb.payment_mode',
                'pb.updated_at as date',
                'u.name as purchased_by',
                //'u.rid as purchaser_rid'
            )
            ->leftJoin('purchase_clients as pc', 'pb.pcid', '=', 'pc.id')
            ->leftJoin('users as u', 'pb.uid', '=', 'u.id')
            ->where('u.cid', $cid)
            ->orderBy('pb.updated_at', 'desc');

        // Apply role-based filtering for admins/superusers/moderators
        if ($userRid <= 3) {
            $query->whereIn('u.rid', $allowedRids);
        } 
        // For Authenticated (4) and Anonymous (5) users: restrict to ONLY their own transactions
        else {
            $query->where('pb.uid', $user->id);
        }

        $transactions = $query->get();

        // Convert payment mode integer to string
        $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();
        $transactions = $transactions->map(function ($transaction) use ($paymentModes) {
            $transaction->payment_mode = $paymentModes[$transaction->payment_mode] ?? 'Unknown';
            return $transaction;
        });

        if ($transactions->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No transactions found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ], 200);
    } catch (\Exception $e) {
        Log::error('Transaction fetch failed', [
            'cid' => $cid,
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


// public function updateTransactionById(Request $request, $transaction_id)
// {
//     // Authentication check
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     // Restrict access to users with rid between 1 and 4 inclusive
//     if ($user->rid < 1 || $user->rid > 4) {
//         return response()->json(['message' => 'Forbidden'], 403);
//     }

//     // Validation rules
//     try {
//         $request->validate([
//             'bill_name' => 'nullable|string|max:255',
//             'payment_mode' => 'nullable|integer|exists:payment_modes,id',
//             'vendor_id' => 'nullable|integer|exists:purchase_clients,id',
//             'products' => 'nullable|array',
//             'products.*.product_id' => 'required_with:products|integer|exists:products,id',
//             'products.*.quantity' => 'required_with:products|numeric|min:0',
//             'products.*.p_price' => 'required_with:products|numeric|min:0',
//             'products.*.s_price' => 'required_with:products|numeric|min:0',
//             'products.*.unit_id' => 'required_with:products|integer|exists:units,id',
//             'products.*.dis' => 'nullable|numeric|min:0|max:100',
//             'products.*.gst' => 'nullable|numeric|min:0',
//             'updated_at' => 'nullable|date_format:Y-m-d H:i:s',
//             'absolute_discount' => 'nullable|numeric|min:0',
//             'set_paid_amount' => 'nullable|numeric', // Removed min:0 to allow negative values
//         ]);
//         \Log::info('Validation passed successfully for updateTransactionById', ['transaction_id' => $transaction_id]);
//     } catch (\Illuminate\Validation\ValidationException $e) {
//         \Log::error('Validation failed for updateTransactionById', [
//             'errors' => $e->errors(),
//             'request_data' => $request->all()
//         ]);
//         return response()->json([
//             'message' => 'Validation failed',
//             'errors' => $e->errors(),
//         ], 422);
//     }

//     // ✅ CRITICAL FIX 1: LOAD TRANSACTION BEFORE ANY CALCULATIONS
//     // Check if the transaction exists
//     $transaction = PurchaseBill::where('id', $transaction_id)->first();
//     if (!$transaction) {
//         \Log::info('Transaction not found', ['transaction_id' => $transaction_id]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Transaction not found'
//         ], 404);
//     }

//     // Check if the current user is the one who created this transaction
//     if ($transaction->uid != $user->id) {
//         \Log::warning('Unauthorized transaction update attempt', [
//             'transaction_id' => $transaction_id,
//             'user_id' => $user->id,
//             'transaction_owner_id' => $transaction->uid
//         ]);
//         return response()->json([
//             'message' => 'Forbidden: You do not have permission to update this transaction'
//         ], 403);
//     }

//     // Get company ID from user
//     $cid = $user->cid;
//     if (!$cid) {
//         return response()->json(['message' => 'User company ID not found'], 400);
//     }

//     // ✅ CRITICAL FIX 2: CALCULATE TOTAL PURCHASE AMOUNT
//     $totalPurchase = 0;
//     if ($request->has('products')) {
//         foreach ($request->products as $product) {
//             $itemTotal = $product['p_price'] * $product['quantity'];
//             $itemTotal -= $itemTotal * ($product['dis'] / 100);
//             $itemTotal += $itemTotal * ($product['gst'] / 100);
//             $totalPurchase += $itemTotal;
//         }
//     }
    
//     $totalPurchase -= $request->absolute_discount ?? 0;

//     // ✅ CRITICAL FIX 3: TREAT set_paid_amount AS ADJUSTMENT
//     $adjustment = $request->input('set_paid_amount', 0);
//     $existingPaid = $transaction->paid_amount;
//     $totalPaid = $existingPaid + $adjustment;

//     // Validate new paid amount
//     if ($totalPaid < 0) {
//         return response()->json([
//             'message' => 'Paid amount cannot be negative'
//         ], 422);
//     }
//     if ($totalPaid > $totalPurchase) {
//         return response()->json([
//             'message' => 'Paid amount cannot exceed total purchase amount'
//         ], 422);
//     }

//     // Start a database transaction
//     DB::beginTransaction();
//     try {
//         // Define purchaseDate for timestamps
//         $purchaseDate = $request->input('updated_at', now());

//         // ✅ CRITICAL FIX 4: REMOVE created_at FROM UPDATE DATA
//         $updateData = [
//             'updated_at' => $purchaseDate,
//             'bill_name' => $request->input('bill_name', $transaction->bill_name),
//             'pcid' => $request->input('vendor_id', $transaction->pcid),
//             'payment_mode' => $request->input('payment_mode', $transaction->payment_mode),
//             'absolute_discount' => $request->input('absolute_discount', $transaction->absolute_discount),
//             'paid_amount' => $totalPaid, // Use calculated total (not raw input)
//         ];

//         // Update purchase_bills
//         PurchaseBill::where('id', $transaction_id)->update($updateData);

//         // Handle products if provided
//         if ($request->has('products')) {
//             $products = $request->input('products', []);
//             $productIds = array_column($products, 'product_id');

//             // Fetch existing purchase items
//             $existingItems = DB::table('purchase_items')
//                 ->where('bid', $transaction_id)
//                 ->get(['pid', 'bid']);

//             // Products to remove
//             $existingProductIds = $existingItems->pluck('pid')->toArray();
//             $productIdsToRemove = array_diff($existingProductIds, $productIds);

//             // Remove products not in request
//             if (!empty($productIdsToRemove)) {
//                 DB::table('purchase_items')
//                     ->where('bid', $transaction_id)
//                     ->whereIn('pid', $productIdsToRemove)
//                     ->delete();
//             }

//             // Insert or update products
//             foreach ($products as $product) {
//                 $item = DB::table('purchase_items')
//                     ->where('bid', $transaction_id)
//                     ->where('pid', $product['product_id'])
//                     ->first();

//                 $productData = [
//                     'p_price' => $product['p_price'],
//                     's_price' => $product['s_price'],
//                     'quantity' => $product['quantity'],
//                     'unit_id' => $product['unit_id'],
//                     'dis' => $product['dis'] ?? $product['discount'] ?? 0,
//                     'gst' => $product['gst'] ?? $product['gst'] ?? 0,
//                 ];

//                 if ($item) {
//                     // Update existing item
//                     DB::table('purchase_items')
//                         ->where('bid', $transaction_id)
//                         ->where('pid', $product['product_id'])
//                         ->update($productData);
//                     Log::info('Purchase item updated', [
//                         'bill_id' => $transaction_id,
//                         'product_id' => $product['product_id'],
//                         'quantity' => $product['quantity'],
//                         'unit_id' => $product['unit_id'],
//                         'dis' => $product['dis'] ?? 0,
//                         'gst' => $product['gst'] ?? 0, 
//                     ]);
//                 } else {
//                     // Insert new item
//                     $productData['bid'] = $transaction_id;
//                     $productData['pid'] = $product['product_id'];
//                     DB::table('purchase_items')->insert($productData);
//                     Log::info('Purchase item created', [
//                         'bill_id' => $transaction_id,
//                         'product_id' => $product['product_id'],
//                         'quantity' => $product['quantity'],
//                         'unit_id' => $product['unit_id'],
//                         'dis' => $product['dis'] ?? 0,
//                         'gst' => $product['gst'] ?? 0, 
//                     ]);
//                 }
//             }

//             // Process product_info for each provided product
//             foreach ($products as $product) {
//                 $pid = (int) $product['product_id']; // Explicitly cast to integer
//                 $productModel = Product::find($pid);
//                 if (!$productModel) {
//                     throw new \Exception("Product not found for pid: {$pid}");
//                 }

//                 $new_unit = $product['unit_id'];
//                 $new_p_price = $product['p_price'];
//                 $new_s_price = $product['s_price'] ?? 0;
//                 $new_gst = $product['gst'] ?? 0;

//                 // Check if product_info record exists for pid and cid
//                 $info = ProductInfo::where('pid', $pid)->where('cid', $cid)->first();

//                 // Prepare data for create or update
//                 $current_unit = $info ? $info->unit_id : $new_unit;
//                 $converted_p = $new_p_price;
//                 $converted_s = $new_s_price;

//                 // Handle unit conversion if necessary
//                 if ($info && $new_unit != $current_unit) {
//                     $p_unit = $productModel->p_unit; // e.g., box
//                     $s_unit = $productModel->s_unit; // e.g., piece
//                     $c_factor = $productModel->c_factor; // e.g., 20 pieces per box

//                     if ($c_factor == 0) {
//                         throw new \Exception("Conversion factor is zero for product ID {$pid}, cannot convert units");
//                     }

//                     // Convert new prices to the stored unit in product_info
//                     // c_factor = number of secondary units (piece) per primary unit (box)
//                     if ($new_unit == $p_unit && $current_unit == $s_unit) {
//                         // New in box, stored in piece: divide by c_factor
//                         $converted_p = $new_p_price / $c_factor;
//                         $converted_s = $new_s_price / $c_factor;
//                     } elseif ($new_unit == $s_unit && $current_unit == $p_unit) {
//                         // New in piece, stored in box: multiply by c_factor
//                         $converted_p = $new_p_price * $c_factor;
//                         $converted_s = $new_s_price * $c_factor;
//                     } else {
//                         throw new \Exception("Unsupported unit conversion for product ID {$pid}: new unit {$new_unit}, current unit {$current_unit}");
//                     }

//                     Log::info('Prices converted for product', [
//                         'pid' => $pid,
//                         'cid' => $cid,
//                         'original_p_price' => $new_p_price,
//                         'converted_p_price' => $converted_p,
//                         'original_s_price' => $new_s_price,
//                         'converted_s_price' => $converted_s,
//                         'new_unit' => $new_unit,
//                         'current_unit' => $current_unit,
//                         'c_factor' => $c_factor,
//                     ]);
//                 }

//                 // Prepare data for create or update
//                 $productInfoData = [
//                     'pid' => $pid,
//                     'hsn_code' => $productModel->hscode,
//                     'description' => null,
//                     'unit_id' => $current_unit,
//                     'purchase_price' => $converted_p,
//                     'profit_percentage' => 0,
//                     'pre_gst_sale_cost' => $converted_s,
//                     'gst' => $new_gst,
//                     'post_gst_sale_cost' => $converted_s * (1 + ($new_gst / 100)),
//                     'uid' => $user->id,
//                     'cid' => $cid,
//                     'created_at' => $purchaseDate,
//                     'updated_at' => $purchaseDate,
//                 ];

//                 if (!$info) {
//                     // Create new product_info record
//                     ProductInfo::create($productInfoData);
//                     Log::info('Product info created', [
//                         'pid' => $pid,
//                         'cid' => $cid,
//                         'unit_id' => $current_unit,
//                         'purchase_price' => $converted_p,
//                         'pre_gst_sale_cost' => $converted_s,
//                         'gst' => $new_gst,
//                     ]);
//                 } else {
//                     // Update existing product_info record for specific pid and cid
//                     $affectedRows = ProductInfo::where('pid', $pid)
//                         ->where('cid', $cid)
//                         ->update([
//                             'purchase_price' => $converted_p,
//                             'pre_gst_sale_cost' => $converted_s,
//                             'gst' => $new_gst,
//                             'post_gst_sale_cost' => $converted_s * (1 + ($new_gst / 100)),
//                             'updated_at' => $purchaseDate,
//                         ]);

//                     if ($affectedRows !== 1) {
//                         Log::warning('Unexpected number of rows updated in product_info', [
//                             'pid' => $pid,
//                             'cid' => $cid,
//                             'affected_rows' => $affectedRows,
//                         ]);
//                     }

//                     Log::info('Product info updated', [
//                         'pid' => $pid,
//                         'cid' => $cid,
//                         'unit_id' => $current_unit,
//                         'purchase_price' => $converted_p,
//                         'pre_gst_sale_cost' => $converted_s,
//                         'gst' => $new_gst,
//                         'affected_rows' => $affectedRows,
//                     ]);
//                 }
//             }
//         }

//         DB::commit();
//         \Log::info('Transaction updated successfully', ['transaction_id' => $transaction_id]);
//         return response()->json([
//             'status' => 'success',
//             'message' => 'Transaction updated successfully'
//         ], 200);
//     } catch (\Exception $e) {
//         DB::rollBack();
//         \Log::error('Failed to update transaction', [
//             'transaction_id' => $transaction_id,
//             'error' => $e->getMessage()
//         ]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Failed to update transaction',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }

public function updateTransactionById(Request $request, $transaction_id)
{
    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid between 1 and 4 inclusive
    // if ($user->rid < 1 || $user->rid > 4) {
    //     return response()->json(['message' => 'Forbidden'], 403);
    // }
    if (!in_array($user->rid, [1, 2])) {
    return response()->json(['message' => 'Forbidden: Only Admin and Superuser can update purchases'], 403);
}

    // Validation rules — s_price is now optional (kept for backward compatibility/history)
    try {
        $request->validate([
            'bill_name' => 'nullable|string|max:255',
            'payment_mode' => 'nullable|integer|exists:payment_modes,id',
            'vendor_id' => 'nullable|integer|exists:purchase_clients,id',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|integer|exists:products,id',
            'products.*.quantity' => 'required_with:products|numeric|min:0',
            'products.*.p_price' => 'required_with:products|numeric|min:0',
            'products.*.s_price' => 'nullable|numeric|min:0', // ← Changed: no longer required
            'products.*.unit_id' => 'required_with:products|integer|exists:units,id',
            'products.*.dis' => 'nullable|numeric|min:0|max:100',
            'products.*.gst' => 'nullable|numeric|min:0',
            'updated_at' => 'nullable|date_format:Y-m-d H:i:s',
            'absolute_discount' => 'nullable|numeric|min:0',
            'set_paid_amount' => 'nullable|numeric',
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

    // Load transaction
    $transaction = PurchaseBill::where('id', $transaction_id)->first();
    if (!$transaction) {
        \Log::info('Transaction not found', ['transaction_id' => $transaction_id]);
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }

    // Ownership check
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

    $cid = $user->cid;
    if (!$cid) {
        return response()->json(['message' => 'User company ID not found'], 400);
    }

    // Calculate total purchase amount (for paid amount validation)
    $totalPurchase = 0;
    if ($request->has('products')) {
        foreach ($request->products as $product) {
            $itemTotal = $product['p_price'] * $product['quantity'];
            $itemTotal -= $itemTotal * ($product['dis'] / 100 ?? 0);
            $itemTotal += $itemTotal * ($product['gst'] / 100 ?? 0);
            $totalPurchase += $itemTotal;
        }
    }
    $totalPurchase -= $request->absolute_discount ?? 0;

    // Handle payment adjustment
    $adjustment = $request->input('set_paid_amount', 0);
    $existingPaid = $transaction->paid_amount ?? 0;
    $totalPaid = $existingPaid + $adjustment;

    if ($totalPaid < 0) {
        return response()->json(['message' => 'Paid amount cannot be negative'], 422);
    }
    if ($totalPaid > $totalPurchase) {
        return response()->json(['message' => 'Paid amount cannot exceed total purchase amount'], 422);
    }

    DB::beginTransaction();
    try {
        $purchaseDate = $request->input('updated_at', now());

        // Update bill header
        $updateData = [
            'updated_at' => $purchaseDate,
            'bill_name' => $request->input('bill_name', $transaction->bill_name),
            'pcid' => $request->input('vendor_id', $transaction->pcid),
            'payment_mode' => $request->input('payment_mode', $transaction->payment_mode),
            'absolute_discount' => $request->input('absolute_discount', $transaction->absolute_discount),
            'paid_amount' => $totalPaid,
        ];

        PurchaseBill::where('id', $transaction_id)->update($updateData);

        // Handle products if provided
        if ($request->has('products')) {
            $products = $request->input('products', []);
            $productIds = array_column($products, 'product_id');

            // Remove deleted items
            $existingItems = DB::table('purchase_items')
                ->where('bid', $transaction_id)
                ->pluck('pid')
                ->toArray();

            $productIdsToRemove = array_diff($existingItems, $productIds);
            if (!empty($productIdsToRemove)) {
                DB::table('purchase_items')
                    ->where('bid', $transaction_id)
                    ->whereIn('pid', $productIdsToRemove)
                    ->delete();
            }

            // Insert or update items
            foreach ($products as $product) {
                $item = DB::table('purchase_items')
                    ->where('bid', $transaction_id)
                    ->where('pid', $product['product_id'])
                    ->first();

                $productData = [
                    'p_price' => $product['p_price'],
                    's_price' => $product['s_price'] ?? 0, // kept for history, but NOT used for selling price
                    'quantity' => $product['quantity'],
                    'unit_id' => $product['unit_id'],
                    'dis' => $product['dis'] ?? 0,
                    'gst' => $product['gst'] ?? 0,
                ];

                if ($item) {
                    DB::table('purchase_items')
                        ->where('bid', $transaction_id)
                        ->where('pid', $product['product_id'])
                        ->update($productData);
                } else {
                    $productData['bid'] = $transaction_id;
                    $productData['pid'] = $product['product_id'];
                    DB::table('purchase_items')->insert($productData);
                }
            }

            // === UPDATE PRODUCT_INFO: ONLY PURCHASE PRICE & GST ===
            foreach ($products as $product) {
                $pid = (int) $product['product_id'];
                $productModel = Product::find($pid);
                if (!$productModel) {
                    throw new \Exception("Product not found for pid: {$pid}");
                }

                $new_unit = $product['unit_id'];
                $new_p_price = $product['p_price'];
                $new_gst = $product['gst'] ?? 0;

                $info = ProductInfo::where('pid', $pid)->where('cid', $cid)->first();
                $current_unit = $info ? $info->unit_id : $new_unit;
                $converted_p = $new_p_price;

                // Unit conversion for purchase price only
                if ($info && $new_unit != $current_unit) {
                    $p_unit = $productModel->p_unit;
                    $s_unit = $productModel->s_unit;
                    $c_factor = $productModel->c_factor;

                    if ($c_factor == 0) {
                        throw new \Exception("Conversion factor is zero for product ID {$pid}");
                    }

                    if ($new_unit == $p_unit && $current_unit == $s_unit) {
                        $converted_p = $new_p_price / $c_factor;
                    } elseif ($new_unit == $s_unit && $current_unit == $p_unit) {
                        $converted_p = $new_p_price * $c_factor;
                    } else {
                        throw new \Exception("Unsupported unit conversion for product ID {$pid}");
                    }
                }

                // Only update purchase_price and gst — NO selling price fields
                $updateData = [
                    'purchase_price' => $converted_p,
                    'gst' => $new_gst,
                    'updated_at' => $purchaseDate,
                ];

                if (!$info) {
                    ProductInfo::create([
                        'pid' => $pid,
                        'hsn_code' => $productModel->hscode,
                        'description' => null,
                        'unit_id' => $current_unit,
                        'purchase_price' => $converted_p,
                        'profit_percentage' => 0,
                        'gst' => $new_gst,
                        'uid' => $user->id,
                        'cid' => $cid,
                        'created_at' => $purchaseDate,
                        'updated_at' => $purchaseDate,
                    ]);
                    Log::info('Product info created (purchase only)', ['pid' => $pid]);
                } else {
                    ProductInfo::where('pid', $pid)
                        ->where('cid', $cid)
                        ->update($updateData);
                    Log::info('Product info updated (purchase only)', ['pid' => $pid]);
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

public function getPurchaseTransactionsByPid(Request $request)
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
        // Build base query: Join purchase_bills with purchase_items to filter by pid
        $query = DB::table('purchase_bills as pb')
            ->select(
                'pb.id as transaction_id',
                'pb.bill_name as bill_name',
                'pc.name as vendor_name',
                'pb.pcid as vendor_id',
                'pb.payment_mode',
                'pb.updated_at as date',
                'u.name as purchased_by',
               // 'u.rid as purchaser_rid'
            )
            ->leftJoin('purchase_clients as pc', 'pb.pcid', '=', 'pc.id')
            ->leftJoin('users as u', 'pb.uid', '=', 'u.id')
            ->join('purchase_items as pi', 'pb.id', '=', 'pi.bid') // 🔑 Link to items
            ->where('pi.pid', $pid) // 🔍 Filter by product ID
            ->where('u.cid', $cid)  // ✅ Scope to user's company
            ->orderBy('pb.updated_at', 'desc');

        // Apply role-based filtering
        if ($userRid <= 3) {
            $query->whereIn('u.rid', $allowedRids);
        } else {
            $query->where('pb.uid', $user->id); // Only own transactions for auth/anonymous
        }

        $transactions = $query->get();

        // Convert payment mode integer to string
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



public function downloadPurchaseInvoice($transactionId)
{
    $user = Auth::user();
    if (!$user || !in_array($user->rid, [1, 2, 3])) {
        abort(403, 'Unauthorized');
    }

    // Fetch transaction (same logic as getPurchaseDetailsByTransaction)
    $transaction = DB::table('purchase_bills')
        ->where('id', $transactionId)
        ->first();

    if (!$transaction) {
        abort(404, 'Transaction not found');
    }

    // Optional: restrict to own company or own transaction
    if ($transaction->uid !== $user->id && $user->rid > 3) {
        abort(403, 'Forbidden');
    }

    $company = DB::table('clients')->where('id', $user->cid)->first();

    $vendor = DB::table('purchase_clients')
        ->where('id', $transaction->pcid)
        ->first();

    $purchasedBy = DB::table('users')
        ->where('id', $transaction->uid)
        ->value('name');

    $paymentModes = DB::table('payment_modes')->pluck('name', 'id')->toArray();
    $payment_mode = $paymentModes[$transaction->payment_mode] ?? 'Unknown';

    // Fetch items (same query as in getPurchaseDetailsByTransaction)
    $items = DB::table('purchase_items as pi')
        ->join('products as prod', 'pi.pid', '=', 'prod.id')
        ->join('units as u', 'pi.unit_id', '=', 'u.id')
        ->select(
            'prod.name as product_name',
            'prod.hscode as hsn',
            'pi.p_price as per_item_cost',
            'pi.dis as discount',
            DB::raw('ROUND(pi.p_price * (1 - COALESCE(pi.dis, 0)/100), 2) AS net_price'),
            'pi.quantity',
            DB::raw('ROUND(pi.quantity * pi.p_price * (1 - COALESCE(pi.dis, 0)/100), 2) AS per_product_total'),
            'pi.gst',
            DB::raw('ROUND((pi.quantity * pi.p_price * (1 - COALESCE(pi.dis, 0)/100)) * (COALESCE(pi.gst, 0)/100), 2) AS gst_amount'),
            'u.name as unit_name'
        )
        ->where('pi.bid', $transactionId)
        ->get();

    // Calculations
    $total_item_net_value = $items->sum('per_product_total');
    $total_gst_amount = $items->sum('gst_amount');
    $total_amount = $total_item_net_value + $total_gst_amount;
    $absolute_discount = $transaction->absolute_discount ?? 0;
    $payable_amount = $total_amount - $absolute_discount;
    $paid_amount = $transaction->paid_amount ?? 0;
    $due_amount = max(0, $payable_amount - $paid_amount);

    // Load view and generate PDF
    $pdf = Pdf::loadView('purchase.invoice', [
        'company' => $company,
        'vendor' => $vendor,
        'transaction' => $transaction,
        'items' => $items,
        'payment_mode' => $payment_mode,
        'purchased_by' => $purchasedBy ?? 'Unknown',
        'total_item_net_value' => round($total_item_net_value, 2),
        'total_gst_amount' => round($total_gst_amount, 2),
        'total_amount' => round($total_amount, 2),
        'absolute_discount' => round($absolute_discount, 2),
        'payable_amount' => round($payable_amount, 2),
        'paid_amount' => round($paid_amount, 2),
        'due_amount' => round($due_amount, 2),
    ]);

    $fileName = 'Purchase_Invoice_' . $transaction->bill_name . '_' . $transactionId . '.pdf';

    return $pdf->download($fileName);
}

public function getVendorsWithDues($cid)
{
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    if (!in_array($user->rid, [1, 2, 3, 4])) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }

    if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: Wrong company'], 403);
    }

    $vendorTotals = DB::table('purchase_bills as pb')
        ->join('purchase_clients as pc', 'pb.pcid', '=', 'pc.id')
        ->leftJoin('purchase_items as pi', 'pb.id', '=', 'pi.bid')
        ->select(
            'pc.id as vendor_id',
            'pc.name as vendor_name',
            DB::raw("
                COALESCE(SUM(
                    pi.quantity * pi.p_price * 
                    (1 - COALESCE(pi.dis, 0)/100) * 
                    (1 + COALESCE(pi.gst, 0)/100)
                ), 0) as gross_total
            "),
            DB::raw('COALESCE(SUM(pb.absolute_discount), 0) as total_discount'),
            DB::raw('COALESCE(SUM(pb.paid_amount), 0) as total_paid')
        )
        ->where('pc.cid', $cid)
        ->groupBy('pc.id', 'pc.name')
        ->havingRaw('
            COALESCE(SUM(
                pi.quantity * pi.p_price * 
                (1 - COALESCE(pi.dis, 0)/100) * 
                (1 + COALESCE(pi.gst, 0)/100)
            ), 0) - COALESCE(SUM(pb.absolute_discount), 0) - COALESCE(SUM(pb.paid_amount), 0) > 0
        ')
        ->get();

    $result = $vendorTotals->map(function ($row) {
        $billTotal = round($row->gross_total - $row->total_discount, 2);
        $due       = round($billTotal - $row->total_paid, 2);
        return [
            'vendor_id'    => $row->vendor_id,
            'vendor_name'  => $row->vendor_name,
            'total_billed' => round($billTotal, 2),
            'total_paid'   => round($row->total_paid, 2),
            'total_due'    => max(0, $due),
        ];
    })->filter(fn($v) => $v['total_due'] > 0.01);

    return response()->json([
        'status' => 'success',
        'data'   => $result->values(),
        'count'  => $result->count(),
    ]);
}

public function getVendorDues(Request $request, $vendor_id)
{
    $user = Auth::user();
    if (!$user || !in_array($user->rid, [1, 2, 3])) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }

    $vendor = DB::table('purchase_clients')->where('id', $vendor_id)->first();
    if (!$vendor) {
        return response()->json(['error' => 'Vendor not found'], 404);
    }

    // Record payment if amount is provided
    $rawAmount = $request->input('amount', $request->query('amount', '0'));
    $amount    = round((float) preg_replace('/[^\d.]/', '', (string) $rawAmount), 2);

    if ($amount > 0) {
        DB::transaction(function () use ($vendor_id, $amount, $request, $user) {
            $remaining = $amount;

            $bills = DB::table('purchase_bills as pb')
                ->leftJoin('purchase_items as pi', 'pb.id', '=', 'pi.bid')
                ->where('pb.pcid', $vendor_id)
                ->selectRaw("
                    pb.id,
                    COALESCE(pb.paid_amount, 0) as paid_amount,
                    COALESCE(pb.absolute_discount, 0) as discount,
                    COALESCE(SUM(
                        pi.quantity * pi.p_price *
                        (1 - COALESCE(pi.dis,0)/100) *
                        (1 + COALESCE(pi.gst,0)/100)
                    ), 0) as gross
                ")
                ->groupBy('pb.id')
                ->orderBy('pb.created_at')
                ->get();

            foreach ($bills as $bill) {
                if ($remaining <= 0) break;

                $billTotal = round($bill->gross - $bill->discount, 2);
                $billDue   = round($billTotal - $bill->paid_amount, 2);

                if ($billDue <= 0) continue;

                $paying = min($remaining, $billDue);

                DB::table('purchase_bills')
                    ->where('id', $bill->id)
                    ->update([
                        'paid_amount' => DB::raw("paid_amount + $paying"),
                        'updated_at'  => now(),
                    ]);

                DB::table('vendor_bill_payments')->insert([
                    'vendor_id'     => $vendor_id,
                    'bill_id'       => $bill->id,
                    'paid_amount'   => $paying,
                    'paid_on'       => $request->input('date', now()->format('Y-m-d')),
                    'payment_mode'  => $request->input('mode', 'Cash'),
                    'note'          => $request->input('note', ''),
                    'recorded_by'   => $user->id,
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);

                $remaining -= $paying;
            }
        });
    }

    // Fetch ledger
    $bills = DB::table('purchase_bills as pb')
        ->leftJoin('purchase_items as pi', 'pb.id', '=', 'pi.bid')
        ->where('pb.pcid', $vendor_id)
        ->selectRaw("
            pb.id,
            pb.bill_name,
            pb.updated_at as bill_date,
            COALESCE(pb.absolute_discount, 0) as discount,
            COALESCE(pb.paid_amount, 0) as paid,
            COALESCE(SUM(
                pi.quantity * pi.p_price *
                (1 - COALESCE(pi.dis,0)/100) *
                (1 + COALESCE(pi.gst,0)/100)
            ), 0) as gross
        ")
        ->groupBy('pb.id')
        ->orderBy('pb.created_at')
        ->get();

    $ledger = [];
    $totalBilled = 0;
    $totalPaid   = 0;

    foreach ($bills as $b) {
        $billTotal = round($b->gross - $b->discount, 2);
        $due       = round($billTotal - $b->paid, 2);

        $totalBilled += $billTotal;
        $totalPaid   += $b->paid;

        $ledger[] = [
            'date'       => Carbon::parse($b->bill_date)->format('d-m-Y'),
            'bill_name'  => $b->bill_name ?: "Bill #{$b->id}",
            'billed'     => number_format($billTotal, 2),
            'paid'       => number_format($b->paid, 2),
            'due'        => number_format(max(0, $due), 2),
            'status'     => $due <= 0.01 ? 'Paid' : 'Due',
        ];
    }

    $ledger = array_reverse($ledger); // Recent first

    // Payment history
    $history = DB::table('vendor_bill_payments as vp')
        ->join('purchase_bills as pb', 'vp.bill_id', '=', 'pb.id')
        ->where('vp.vendor_id', $vendor_id)
        ->select('vp.*', 'pb.bill_name')
        ->orderByDesc('vp.created_at')
        ->get();

    return response()->json([
        'vendor_name'     => $vendor->name,
        'phone'           => $vendor->phone ?? '',
        'total_billed'    => number_format($totalBilled, 2),
        'total_paid'      => number_format($totalPaid, 2),
        'current_due'     => number_format(max(0, $totalBilled - $totalPaid), 2),
        'ledger'          => $ledger,
        'payment_history' => $history->map(fn($p) => [
            'date'         => Carbon::parse($p->paid_on)->format('d-m-Y'),
            'bill'         => $p->bill_name ?: "Bill #{$p->bill_id}",
            'paid'         => '₹' . number_format($p->paid_amount, 2),
            'mode'         => $p->payment_mode,
            'note'         => $p->note ?: '-',
        ])
    ]);
}

}
