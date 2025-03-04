<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SalesItem;
use App\Models\TransactionSales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Fixed this import
class SalesController extends Controller
{
    public function store(Request $request)
    {
        Log::info('API endpoint reached', ['request' => $request->all()]);

        $user = Auth::user();
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $request->validate([
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.discount' => 'nullable|numeric|min:0',
                'products.*.per_item_cost' => 'required|numeric|min:0',
                'cid' => 'required|integer',
                'customer_id' => 'required|integer',
                'payment_mode' => 'required|string|max:50',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Get the next sale_id from the sequence
            $saleId = DB::selectOne('SELECT nextval(\'sale_id_seq\')')->nextval;
            Log::info('Generated sale_id', ['sale_id' => $saleId]);

            // Step 1: Create Sale records for each product with the same sale_id
            foreach ($request->products as $product) {
                Sale::create([
                    'sale_id' => $saleId,
                    'product_id' => $product['product_id'],
                ]);
            }

            // Step 2: Add all products as SalesItems with the same sale_id
            foreach ($request->products as $product) {
                SalesItem::create([
                    'sale_id' => $saleId,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
                ]);
            }
            // for transaction_sales table new added
               // Step 3: Calculate total amount separately
               $totalAmount = 0;
            foreach ($request->products as $product) {
               $quantity = $product['quantity'];
               $perItemCost = $product['per_item_cost'];
               $discount = $product['discount'] ?? 0;

               // Calculate item total after discount
               $itemTotal = ($quantity * $perItemCost) - (($quantity * $perItemCost) * ($discount / 100.0));
               $totalAmount += $itemTotal;
           }
               // Step 4: Insert transaction data into transactions table
         $transaction = TransactionSales::create([
                    'sale_id'      => $saleId,
                    'uid'          => $user->id,
                    'cid'          => $request->cid, // Now taking cid from the request
                    'customer_id'  => $request->customer_id,
                    'payment_mode' => $request->payment_mode,
                    'total_amount' => $totalAmount,
         ]);

            DB::commit();
            Log::info('Sale and transaction recorded successfully', ['sale_id' => $saleId]);

        return response()->json([
            'message' => 'Sale recorded successfully',
            'sale_id' => $saleId,
            'transaction' => $transaction
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
}
