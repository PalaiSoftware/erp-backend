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
        $user = Auth::user();
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
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
                'products.*.unit_id' => 'required|integer|exists:units,id', // Unit validation
                'cid' => 'required|integer',
                'payment_mode' => 'required|string|max:50',
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
            // Step 1: Create the transaction
            $transaction = TransactionPurchase::create([
                'uid' => $user->id,
                'cid' => $request->cid,
                'total_amount' => 0, // Placeholder, update later if needed
                'payment_mode' => $request->payment_mode,
                'created_at' => now(),
            ]);
            $transactionId = $transaction->id;
            Log::info('Transaction created', ['transaction_id' => $transactionId]);

            // Step 2: Process each product
            foreach ($request->products as $product) {
                // Create purchase record
                $purchase = Purchase::create([
                    'transaction_id' => $transactionId,
                    'product_id' => $product['product_id'],
                    'created_at' => now(),
                ]);
                $purchaseId = $purchase->id;
                Log::info('Purchase created', ['purchase_id' => $purchaseId, 'product_id' => $product['product_id']]);

                // Create purchase item record with unit_id
                PurchaseItem::create([
                    'purchase_id' => $purchaseId,
                    'vendor_id' => $product['vendor_id'],
                    'quantity' => $product['quantity'],
                    'per_item_cost' => $product['per_item_cost'],
                    'unit_id' => $product['unit_id'], // Saving unit_id
                    'created_at' => now(),
                ]);
                Log::info('Purchase item created', [
                    'purchase_id' => $purchaseId,
                    'vendor_id' => $product['vendor_id'],
                    'quantity' => $product['quantity'],
                    'unit_id' => $product['unit_id']
                ]);
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
        $user = Auth::user();
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
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

        $cid = $request->input('cid');

        $transactions = DB::table('transaction_purchases as tp')
            ->select(
                'tp.id as transaction_id',
                'v.vendor_name',
                'tp.payment_mode',
                'tp.created_at as date',
                'u.name as purchased_by'
            )
            ->leftJoin('purchases as p', 'tp.id', '=', 'p.transaction_id')
            ->leftJoin('purchase_items as pi', 'p.id', '=', 'pi.purchase_id')
            ->leftJoin('vendors as v', 'pi.vendor_id', '=', 'v.id')
            ->leftJoin('users as u', 'tp.uid', '=', 'u.id')
            ->where('tp.cid', $cid)
            ->groupBy('tp.id', 'v.vendor_name', 'tp.payment_mode', 'tp.created_at', 'u.name')
            ->get();

        if ($transactions->isEmpty()) {
            Log::info('No transactions found for cid', ['cid' => $cid]);
            return response()->json([
                'status' => 'error',
                'message' => 'No transactions found for this customer ID'
            ], 404);
        }

        Log::info('Transactions retrieved successfully', ['cid' => $cid, 'count' => $transactions->count()]);
        return response()->json([
            'status' => 'success',
            'data' => $transactions
        ], 200);
    }

    public function getPurchaseDetailsByTransaction(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

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

        $transactionExists = DB::table('transaction_purchases')
            ->where('id', $transactionId)
            ->exists();

        if (!$transactionExists) {
            Log::info('Transaction not found', ['transaction_id' => $transactionId]);
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }

        $purchaseDetails = DB::table('purchases as p')
            ->select(
                'prod.name as product_name',
                'pi.quantity',
                'pi.per_item_cost',
                'pi.unit_id', // Added unit_id
                DB::raw('pi.quantity * pi.per_item_cost as per_product_total')
            )
            ->join('products as prod', 'p.product_id', '=', 'prod.id')
            ->join('purchase_items as pi', 'p.id', '=', 'pi.purchase_id')
            ->where('p.transaction_id', $transactionId)
            ->get();

        if ($purchaseDetails->isEmpty()) {
            Log::info('No purchase details found for transaction', ['transaction_id' => $transactionId]);
            return response()->json([
                'status' => 'error',
                'message' => 'No purchase details found for this transaction ID'
            ], 404);
        }

        $totalAmount = $purchaseDetails->sum('per_product_total');

        Log::info('Purchase details retrieved successfully', ['transaction_id' => $transactionId]);
        return response()->json([
            'status' => 'success',
            'data' => [
                'products' => $purchaseDetails,
                'total_amount' => $totalAmount
            ]
        ], 200);
    }
}