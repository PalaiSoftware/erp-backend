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
}