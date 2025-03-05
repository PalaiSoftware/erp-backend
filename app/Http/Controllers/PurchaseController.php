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
        Log::info('Purchase API endpoint reached', ['request' => $request->all()]);

        $user = Auth::user();
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $request->validate([
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.vendor_id' => 'required|integer',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.per_item_cost' => 'required|numeric|min:0',
                'cid' => 'required|integer',
                'payment_mode' => 'required|string|max:50',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Purchase validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            $purchaseId = DB::selectOne('SELECT nextval(\'purchase_id_seq\')')->nextval;
            Log::info('Generated purchase_id', ['purchase_id' => $purchaseId]);

            foreach ($request->products as $product) {
                Purchase::create([
                    'purchase_id' => $purchaseId,
                    'product_id' => $product['product_id'],
                    'created_at' => now(),
                ]);
            }

            foreach ($request->products as $product) {
                PurchaseItem::create([
                    'purchase_id' => $purchaseId,
                    'vendor_id' => $product['vendor_id'],
                    'quantity' => $product['quantity'],
                    'per_item_cost' => $product['per_item_cost'],
                    'created_at' => now(), // Explicitly set created_at
                ]);
            }
             // Calculate the total amount from purchase items using a foreach loop
             $totalAmount = 0;
             foreach ($request->products as $product) {
                 $lineTotal = $product['quantity'] * $product['per_item_cost'];
                 $totalAmount += $lineTotal;
             }
 
             // Insert into the transaction_purchases table
             $transaction = TransactionPurchase::create([
                 'purchase_id'  => $purchaseId,
                 'uid'          => $user->id,
                 'cid'          => $request->cid, // Now taking cid from the request
                 'total_amount' => $totalAmount,
                 'payment_mode' => $request->payment_mode,
                 'created_at'   => now(),
                 
                 
             ]);
 

            DB::commit();
            Log::info('Purchase and transaction recorded successfully', ['purchase_id' => $purchaseId]);

            return response()->json([
                'message' => 'Purchases recorded successfully',
                'purchase_id' => $purchaseId,
                'transaction' => $transaction,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Purchase failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Purchase failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}