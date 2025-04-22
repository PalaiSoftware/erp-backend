<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\TransactionPurchase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseController extends Controller
{
    public function store(Request $request)
    {
       // Get the authenticated user
       $user = Auth::user();
    
       // Check if user is authenticated
       if (!$user) {
          return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Restrict to rid 5, 6, 7
        if (!in_array($user->rid, [5, 6, 7])) {
           return response()->json(['message' => 'Unauthorized to purchase product'], 403);
        }
    

        // Log the incoming request before validation
        Log::info('Incoming purchase request', ['request_data' => $request->all()]);

        // Validate the request with logging for errors
        try {
            $request->validate([
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.vendor_id' => 'required|integer',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.per_item_cost' => 'required|numeric|min:0',
                'products.*.discount' => 'nullable|numeric|min:0|max:100',
                'products.*.unit_id' => 'required|integer|exists:units,id', // Unit validation
                'products.*.selling_price' => 'nullable|numeric|min:0',
                'cid' => 'required|integer',
                'payment_mode' => 'required|string|max:50',
                'purchase_date' => 'required|date_format:Y-m-d H:i:s', // Add this line
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

        // Use a transaction to ensure data consistency
        DB::beginTransaction();
        try {
            $purchaseDate = $request->purchase_date; // Get manual timestamp

            // Step 1: Create the transaction
            $transaction = TransactionPurchase::create([
                'uid' => $user->id,
                'cid' => $request->cid,
                'total_amount' => 0, // Placeholder, update later if needed
                'payment_mode' => $request->payment_mode,
                'absolute_discount' => $request->absolute_discount ?? 0,
                'paid_amount' => $request->paid_amount ?? 0,
               'created_at' => $purchaseDate,
               'updated_at' =>  $purchaseDate, // Optional: Set updated_at if needed
            ]);
            $transactionId = $transaction->id;
            Log::info('Transaction created', ['transaction_id' => $transactionId]);

            // Step 2: Process each product
            foreach ($request->products as $product) {
                // Create purchase record
                $purchase = Purchase::create([
                    'transaction_id' => $transactionId,
                    'product_id' => $product['product_id'],
                    'created_at' =>  $purchaseDate,
                ]);
                $purchaseId = $purchase->id;
                Log::info('Purchase created', ['purchase_id' => $purchaseId, 'product_id' => $product['product_id']]);

                // Create purchase item record with unit_id
                PurchaseItem::create([
                    'purchase_id' => $purchaseId,
                    'vendor_id' => $product['vendor_id'],
                    'quantity' => $product['quantity'],
                    'per_item_cost' => $product['per_item_cost'],
                    'discount' => $product['discount'] ?? 0,
                    'unit_id' => $product['unit_id'], // Saving unit_id
                    'created_at' =>  $purchaseDate,
                ]);
                Log::info('Purchase item created', [
                    'purchase_id' => $purchaseId,
                    'vendor_id' => $product['vendor_id'],
                    'quantity' => $product['quantity'],
                    'unit_id' => $product['unit_id'],
                    'discount' => $product['discount'] ?? 0,

                ]);
               
                if (array_key_exists('selling_price', $product) && !is_null($product['selling_price'])) {
                    $productModel = Product::find($product['product_id']);
                    
                    if ($productModel && $productModel->productValue) {
                        $productModel->productValue->update([
                            'selling_price' => $product['selling_price'],
                        ]);
                        Log::info('Selling price updated', [
                            'product_id' => $product['product_id'],
                            'selling_price' => $product['selling_price']
                        ]);
                    } else {
                        Log::warning('Product or ProductValue not found', ['product_id' => $product['product_id']]);
                    }
                }
            }

            // Step 3: Commit the transaction
            DB::commit();
            Log::info('Transaction committed', ['transaction_id' => $transactionId]);

            return response()->json([
                'message' => 'Purchases recorded successfully',
                'transaction_id' => $transactionId,
                'transaction' => $transaction,
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
    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Extract uid from the authenticated user
    $uid = $user->id;

    // Restrict access to users with rid between 5 and 10 inclusive
    if ($user->rid < 5 || $user->rid > 10) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    try {
        // Validate the request data
        $request->validate([
            'cid' => 'required|integer'
        ]);
        Log::info('Validation passed successfully for getTransactionsByCid', ['cid' => $request->cid]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed for getTransactionsByCid', [
            'errors' => $e->errors(),
            'request_data' => $request->all()
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    // Extract cid from the request
    $cid = $request->input('cid');

    // Start building the query
    $query = DB::table('transaction_purchases as tp')
        ->select(
            'tp.id as transaction_id',
            'v.vendor_name',
            'pi.vendor_id', // Add vendor_id from purchase_items
            'tp.payment_mode',
            'tp.updated_at as date',
            'u.name as purchased_by'
        )
        ->leftJoin('purchases as p', 'tp.id', '=', 'p.transaction_id')
        ->leftJoin('purchase_items as pi', 'p.id', '=', 'pi.purchase_id')
        ->leftJoin('vendors as v', 'pi.vendor_id', '=', 'v.id')
        ->leftJoin('users as u', 'tp.uid', '=', 'u.id')
        ->where('tp.cid', $cid);

    // Apply additional restrictions based on the user's rid
    if ($user->rid === 5) {
        // RID 5: Allow all transactions for the company (cid)
        $query->where('tp.cid', $cid);
    } elseif ($user->rid === 6) {
        // RID 6: Allow admin to see their own transactions and lower role (7, 8, 9) transactions
        $query->where(function ($q) use ($uid) {
            $q->where('tp.uid', $uid) // Their own transactions
              ->orWhereIn('u.rid', [7, 8, 9]); // Transactions of lower-role users
        });
    } else {
        // RID 7, 8, 9: Restrict to transactions where uid matches the authenticated user's ID
        $query->where('tp.uid', $uid);
    }

    // Group by necessary columns
    $query->groupBy('tp.id', 'v.vendor_name', 'pi.vendor_id', 'tp.payment_mode', 'tp.created_at', 'u.name');

    // Execute the query
    $transactions = $query->get();

    // Check if any transactions were found
    if ($transactions->isEmpty()) {
        Log::info('No transactions found for cid', ['cid' => $cid]);
        return response()->json([
            'status' => 'error',
            'message' => 'No transactions found for this customer ID'
        ], 404);
    }

    // Log success and return the transactions
    Log::info('Transactions retrieved successfully', ['cid' => $cid, 'count' => $transactions->count()]);
    return response()->json([
        'status' => 'success',
        'data' => $transactions
    ], 200);
}
//     public function getPurchaseDetailsByTransaction(Request $request)
// {
//     // Get the authenticated user
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }
// {
//     // Get the authenticated user
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     // Restrict access to users with rid between 5 and 10 inclusive
//     if ($user->rid < 5 || $user->rid > 10) {
//         return response()->json(['message' => 'Forbidden'], 403);
//     }
  
//     try {
//         $request->validate([
//             'transaction_id' => 'required|integer'
//         ]);
//         Log::info('Validation passed successfully for getPurchaseDetailsByTransaction', ['transaction_id' => $request->transaction_id]);
//     } catch (\Illuminate\Validation\ValidationException $e) {
//         Log::error('Validation failed for getPurchaseDetailsByTransaction', [
//             'errors' => $e->errors(),
//             'request_data' => $request->all()
//         ]);
//         return response()->json([
//             'message' => 'Validation failed',
//             'errors' => $e->errors(),
//         ], 422);
//     }
//     try {
//         $request->validate([
//             'transaction_id' => 'required|integer'
//         ]);
//         Log::info('Validation passed successfully for getPurchaseDetailsByTransaction', ['transaction_id' => $request->transaction_id]);
//     } catch (\Illuminate\Validation\ValidationException $e) {
//         Log::error('Validation failed for getPurchaseDetailsByTransaction', [
//             'errors' => $e->errors(),
//             'request_data' => $request->all()
//         ]);
//         return response()->json([
//             'message' => 'Validation failed',
//             'errors' => $e->errors(),
//         ], 422);
//     }

//     $transactionId = $request->input('transaction_id');
//     $transactionId = $request->input('transaction_id');

//     $transactionExists = DB::table('transaction_purchases')
//         ->where('id', $transactionId)
//         ->exists();
//     $transactionExists = DB::table('transaction_purchases')
//         ->where('id', $transactionId)
//         ->exists();

//     if (!$transactionExists) {
//         Log::info('Transaction not found', ['transaction_id' => $transactionId]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Transaction not found'
//         ], 404);
//     }
//     if (!$transactionExists) {
//         Log::info('Transaction not found', ['transaction_id' => $transactionId]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'Transaction not found'
//         ], 404);
//     }

//     $purchaseDetails = DB::table('purchases as p')
//         ->join('products as prod', 'p.product_id', '=', 'prod.id')
//         ->join('purchase_items as pi', 'p.id', '=', 'pi.purchase_id')
//         ->join('transaction_purchases as tp', 'p.transaction_id', '=', 'tp.id')
//         ->select(
//             'prod.name as product_name',
//             'pi.quantity',
//             'pi.per_item_cost',
//             'pi.unit_id',
//             'pi.vendor_id', // Add vendor_id
//             'tp.payment_mode',
//             'pi.discount',
//             DB::raw('pi.quantity * pi.per_item_cost * (1 - pi.discount / 100) as per_product_total')
//             // DB::raw('pi.quantity * pi.per_item_cost as per_product_total')
//         )
//         ->where('p.transaction_id', $transactionId)
//         ->get();

//     if ($purchaseDetails->isEmpty()) {
//         Log::info('No purchase details found for transaction', ['transaction_id' => $transactionId]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'No purchase details found for this transaction ID'
//         ], 404);
//     }
//     if ($purchaseDetails->isEmpty()) {
//         Log::info('No purchase details found for transaction', ['transaction_id' => $transactionId]);
//         return response()->json([
//             'status' => 'error',
//             'message' => 'No purchase details found for this transaction ID'
//         ], 404);
//     }

//     $totalAmount = $purchaseDetails->sum('per_product_total');
    
//     // Get unique vendor_ids from the purchase details
//     $vendorIds = $purchaseDetails->pluck('vendor_id')->unique()->values();

//     // Fetch vendor details
//     $vendors = DB::table('vendors')
//         ->whereIn('id', $vendorIds)
//         ->select('id', 'vendor_name', 'contact_person', 'email', 'phone', 'address', 'gst_no', 'pan')
//         ->get()

//     Log::info('Purchase details retrieved successfully', ['transaction_id' => $transactionId]);
//     return response()->json([
//         'status' => 'success',
//         'data' => [
//             'products' => $purchaseDetails,
//             'total_amount' => $totalAmount
//         ]
//     ], 200);
// }
//     Log::info('Purchase details retrieved successfully', ['transaction_id' => $transactionId]);
//     return response()->json([
//         'status' => 'success',
//         'data' => [
//             'products' => $purchaseDetails,
//             'total_amount' => $totalAmount
//         ]
//     ], 200);
// }

public function getPurchaseDetailsByTransaction(Request $request)
{
    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid between 5 and 10 inclusive
    if ($user->rid < 5 || $user->rid > 10) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate the request
    try {
        $request->validate([
            'transaction_id' => 'required|integer'
        ]);
        Log::info('Validation passed successfully for getPurchaseDetailsByTransaction', ['transaction_id' => $request->transaction_id]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed for getPurchaseDetailsByTransaction', [
            'errors' => $e->errors(),
            'request_data' => $request->all()
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    $transactionId = $request->input('transaction_id');

    // Fetch the transaction using the model
    $transaction = TransactionPurchase::find($transactionId);
    Log::info('Transaction data retrieved via model', ['transaction' => $transaction ? $transaction->toArray() : null]);

    // Fallback: Fetch directly from DB to confirm data
    $rawTransaction = DB::table('transaction_purchases')->where('id', $transactionId)->first();
    Log::info('Raw transaction data retrieved via DB', ['raw_transaction' => $rawTransaction]);

    if (!$transaction) {
        Log::info('Transaction not found', ['transaction_id' => $transactionId]);
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }

    // Extract absolute_discount and paid_amount, defaulting to 0 if null
    $absoluteDiscount = $transaction->absolute_discount ?? 0;
    $paidAmount = $transaction->paid_amount ?? 0;

    // Fetch purchase details with vendor_id
    $purchaseDetails = DB::table('purchases as p')
        ->join('products as prod', 'p.product_id', '=', 'prod.id')
        ->join('purchase_items as pi', 'p.id', '=', 'pi.purchase_id')
        ->join('transaction_purchases as tp', 'p.transaction_id', '=', 'tp.id')
        ->select(
            'p.product_id',
            'prod.name as product_name',
            'pi.quantity',
            'pi.per_item_cost',
            'pi.unit_id',
            'pi.vendor_id',
            'tp.payment_mode',
            'pi.discount',
            DB::raw('ROUND(pi.quantity * pi.per_item_cost * (1 - pi.discount / 100), 2) as per_product_total')
        )
        ->where('p.transaction_id', $transactionId)
        ->get();

    if ($purchaseDetails->isEmpty()) {
        Log::info('No purchase details found for transaction', ['transaction_id' => $transactionId]);
        return response()->json([
            'status' => 'error',
            'message' => 'No purchase details found for this transaction ID'
        ], 404);
    }

    // Calculate total amount
    $totalAmount = $purchaseDetails->sum('per_product_total');

    // Calculate payable_amount and due_amount
    $payableAmount = $totalAmount - $absoluteDiscount;
    $dueAmount = max(0, $payableAmount - $paidAmount);

    // Get unique vendor_ids from the purchase details
    $vendorIds = $purchaseDetails->pluck('vendor_id')->unique()->values();

    // Fetch vendor details
    $vendors = DB::table('vendors')
        ->whereIn('id', $vendorIds)
        ->select('id', 'vendor_name', 'contact_person', 'email', 'phone', 'address', 'gst_no', 'pan')
        ->get();

    Log::info('Purchase details retrieved successfully', [
        'transaction_id' => $transactionId,
        'total_amount' => $totalAmount,
        'absolute_discount' => $absoluteDiscount,
        'payable_amount' => $payableAmount,
        'paid_amount' => $paidAmount,
        'due_amount' => $dueAmount
    ]);

    return response()->json([
        'status' => 'success',
        'data' => [
            'products' => $purchaseDetails,
            'vendors' => $vendors,
            'total_amount' => round($totalAmount, 2),
            'absolute_discount' => round($absoluteDiscount, 2),
            'payable_amount' => round($payableAmount, 2),
            'paid_amount' => round($paidAmount, 2),
            'due_amount' => round($dueAmount, 2),
            'updated_at' => $transaction->updated_at->format('Y-m-d H:i:s')
            ]
    ], 200);
}

    // In your controller (e.g., PurchaseController.php)
// public function getPurchaseWidget(Request $request)
// {
//     // Authentication check
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     // Validate request data
//     $validated = $request->validate([
//         'cid' => 'required|integer|exists:companies,id'
//     ]);
//     $cid = $validated['cid'];

//     // Get purchase count
//     $purchaseCount = TransactionPurchase::where('cid', $cid)->count();
//     $vendorCount = PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
//         ->join('transaction_purchases', 'purchases.transaction_id', '=', 'transaction_purchases.id')
//         ->where('transaction_purchases.cid', $cid)
//         ->distinct('purchase_items.vendor_id')
//         ->count('purchase_items.vendor_id');
//     $totalAmount = PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
//         ->join('transaction_purchases', 'purchases.transaction_id', '=', 'transaction_purchases.id')
//         ->where('transaction_purchases.cid', $cid)
//        // ->sum(DB::raw('purchase_items.quantity * purchase_items.per_item_cost'));
//         ->sum(DB::raw('purchase_items.quantity * purchase_items.per_item_cost * (1 - purchase_items.discount / 100)'));

//     return response()->json([
//         // 'cid' => $cid,
//         'total_purchase_order' => $purchaseCount,
//         'total_vendor'=>$vendorCount,
//         'total_purchase_amount' => $totalAmount

//     ], 200);
// }
    public function getPurchaseWidget(Request $request)
    {
        // Authentication check
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Validate and extract cid from request body
        $validated = $request->validate([
            'cid' => 'required|integer|exists:companies,id'
        ]);
        $cid = $validated['cid'];
        $rid = $user->rid; // Role ID from authenticated user
        $uid = $user->id; // User ID from authenticated user

        // Purchase count with conditional uid filtering
        $purchaseCount = TransactionPurchase::where('cid', $cid)
            ->when(!in_array($rid, [5, 6]), fn($q) => $q->where('uid', $uid))
            ->count();

        // Vendor count with conditional uid filtering
        $vendorCount = PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
        ->join('transaction_purchases', 'purchases.transaction_id', '=', 'transaction_purchases.id')
        ->where('transaction_purchases.cid', $cid)
        ->distinct('purchase_items.vendor_id')
        ->count('purchase_items.vendor_id');

        // Total amount calculation with conditional uid filtering
        $totalAmount = PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->join('transaction_purchases', 'purchases.transaction_id', '=', 'transaction_purchases.id')
            ->where('transaction_purchases.cid', $cid)
            ->when(!in_array($rid, [5, 6]), fn($q) => $q->where('transaction_purchases.uid', $uid))
            ->sum(DB::raw('purchase_items.quantity * purchase_items.per_item_cost * (1 - purchase_items.discount / 100)'));

        return response()->json([
            'total_purchase_order' => $purchaseCount,
            'total_vendor' => $vendorCount,
            'total_purchase_amount' => $totalAmount
        ], 200);
    }
    public function updateTransactionById(Request $request, $transaction_id)
{
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
        // Validation rules (unchanged from your code)
        $request->validate([
            'transaction_id' => 'sometimes|integer|in:' . $transaction_id,
            'payment_mode' => 'nullable|string|in:cash,credit_card,online',
            'vendor_id' => 'nullable|integer|exists:vendors,id',
            'products' => 'nullable|array',
            'products.*.product_id' => 'required_with:products|integer|exists:products,id',
            'products.*.quantity' => 'required_with:products|integer|min:1',
            'products.*.per_item_cost' => 'required_with:products|numeric|min:0',
            'products.*.unit_id' => 'required_with:products|integer|exists:units,id',
            'products.*.discount' => 'nullable|numeric|min:0|max:100',
            'updated_at' => 'nullable|date_format:Y-m-d H:i:s',
            'absolute_discount' => 'nullable|numeric|min:0',
            'paid_amount' => 'nullable|numeric|min:0',
        ]);
        Log::info('Validation passed successfully for updateTransactionById', ['transaction_id' => $transaction_id]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed for updateTransactionById', [
            'errors' => $e->errors(),
            'request_data' => $request->all()
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    // Check if the transaction exists
    $transaction = DB::table('transaction_purchases')
        ->where('id', $transaction_id)
        ->first();

    if (!$transaction) {
        Log::info('Transaction not found', ['transaction_id' => $transaction_id]);
        return response()->json([
            'status' => 'error',
            'message' => 'Transaction not found'
        ], 404);
    }

    DB::beginTransaction();
    try {
        // Prepare update data for transaction_purchases
        $updateData = [
            'updated_at' => $request->input('updated_at', now()) // Use provided updated_at or now()
        ];
        if ($request->has('payment_mode')) {
            $updateData['payment_mode'] = $request->input('payment_mode');
        }
         // Update absolute_discount if provided and not null
         if ($request->has('absolute_discount') && $request->input('absolute_discount') !== null) {
            $updateData['absolute_discount'] = (float)$request->input('absolute_discount');
        }
        // Update paid_amount by adding to existing value if provided and not null
        if ($request->has('paid_amount') && $request->input('paid_amount') !== null) {
            $currentPaidAmount = (float)($transaction->paid_amount ?? 0);
            $additionalPaidAmount = (float)$request->input('paid_amount');
            $updateData['paid_amount'] = $currentPaidAmount + $additionalPaidAmount;
        }
        if (!empty($updateData)) {
            DB::table('transaction_purchases')
                ->where('id', $transaction_id)
                ->update($updateData);
        }

        // Handle products if provided
        if ($request->has('products')) {
            // Require vendor_id if products are provided
            if (!$request->has('vendor_id')) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => ['vendor_id' => ['The vendor_id field is required when products are provided.']]
                ], 422);
            }

            $products = $request->input('products', []); // Default to empty array if null
            $vendorId = $request->input('vendor_id');

            // Fetch existing purchases for this transaction
            $existingPurchases = DB::table('purchases')
                ->where('transaction_id', $transaction_id)
                ->get(['id', 'product_id']);

            if (empty($products)) {
                // If products is empty, remove all existing purchases
                if ($existingPurchases->isNotEmpty()) {
                    $purchaseIds = $existingPurchases->pluck('id');
                    DB::table('purchase_items')->whereIn('purchase_id', $purchaseIds)->delete();
                    DB::table('purchases')->where('transaction_id', $transaction_id)->delete();
                }
            } else {
                // Extract product_ids from request
                $requestProductIds = array_column($products, 'product_id');
                $existingProductIds = $existingPurchases->pluck('product_id')->toArray();

                // Products to add (in request, not in DB)
                $productsToAdd = array_filter($products, function ($product) use ($existingProductIds) {
                    return !in_array($product['product_id'], $existingProductIds);
                });

                // Products to update (in both request and DB)
                $productsToUpdate = array_filter($products, function ($product) use ($existingProductIds) {
                    return in_array($product['product_id'], $existingProductIds);
                });

                // Products to remove (in DB, not in request)
                $productIdsToRemove = array_diff($existingProductIds, $requestProductIds);

                // Add new products
                foreach ($productsToAdd as $product) {
                    $purchaseId = DB::table('purchases')->insertGetId([
                        'transaction_id' => $transaction_id,
                        'product_id' => $product['product_id'],
                        'created_at' => now(),
                    ]);
                    DB::table('purchase_items')->insert([
                        'purchase_id' => $purchaseId,
                        'vendor_id' => $vendorId,
                        'quantity' => $product['quantity'],
                        'per_item_cost' => $product['per_item_cost'],
                        'unit_id' => $product['unit_id'],
                        'discount' => $product['discount'] ?? 0,
                        'created_at' => now(),
                    ]);
                }

                // Update existing products
                foreach ($productsToUpdate as $product) {
                    $purchase = $existingPurchases->firstWhere('product_id', $product['product_id']);
                    if ($purchase) {
                        DB::table('purchase_items')
                            ->where('purchase_id', $purchase->id)
                            ->update([
                                'vendor_id' => $vendorId,
                                'quantity' => $product['quantity'],
                                'per_item_cost' => $product['per_item_cost'],
                                'unit_id' => $product['unit_id'],
                                'discount' => $product['discount'] ?? 0,
                                
                            ]);
                    }
                }

                // Remove products not in request
                if (!empty($productIdsToRemove)) {
                    $purchaseIdsToRemove = $existingPurchases->whereIn('product_id', $productIdsToRemove)->pluck('id');
                    DB::table('purchase_items')->whereIn('purchase_id', $purchaseIdsToRemove)->delete();
                    DB::table('purchases')->whereIn('id', $purchaseIdsToRemove)->delete();
                }
            }
        }

        DB::commit();
        Log::info('Transaction updated successfully', ['transaction_id' => $transaction_id]);
        return response()->json([
            'status' => 'success',
            'message' => 'Transaction updated successfully'
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Failed to update transaction', [
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

}
