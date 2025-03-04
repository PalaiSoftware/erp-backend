<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SalesItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Fixed this import
class SalesController extends Controller
{
    // public function store(Request $request)
    // {
    //     // Check if the user is authenticated
    //     $user = Auth::user();
    //     if (!$user) {
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }

    //     // Validate the incoming request data
    //     $request->validate([
    //         'products' => 'required|array',
    //         'products.*.product_id' => 'required|integer|exists:products,id',
    //         'products.*.quantity' => 'required|integer|min:1',
    //         'products.*.discount' => 'nullable|numeric|min:0',
    //         'products.*.per_item_cost' => 'required|numeric|min:0',
    //     ]);

    //     // Use a database transaction for data integrity
    //     DB::beginTransaction();
    //     try {
    //         // Get the next sale_id from the sequence
    //         $saleId = DB::selectOne('SELECT nextval(\'sale_id_seq\')')->nextval;

    //         // Step 1: Create Sale records for each product with the same sale_id
    //         foreach ($request->products as $product) {
    //             Sale::create([
    //                 'sale_id' => $saleId,
    //                 'product_id' => $product['product_id'],
    //             ]);
    //         }

    //         // Step 2: Add all products as SalesItems with the same sale_id
    //         foreach ($request->products as $product) {
    //             SalesItem::create([
    //                 'sale_id' => $saleId,
    //                 // 'product_id' => $product['product_id'],
    //                 'quantity' => $product['quantity'],
    //                 'discount' => $product['discount'] ?? 0,
    //                 'per_item_cost' => $product['per_item_cost'],
    //             ]);
    //         }

    //         // Commit the transaction
    //         DB::commit();

    //         // Return a success response with the sale ID
    //         return response()->json([
    //             'message' => 'Sale recorded successfully',
    //             'sale_id' => $saleId,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         // Roll back on error
    //         DB::rollBack();
    //         return response()->json([
    //             'message' => 'Sale failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    // public function store(Request $request)
    // {
    //     \Log::info('API hit', ['request' => $request->all()]);

    //     // Check if the user is authenticated
    //     $user = Auth::user();
    //     if (!$user) {
    //         \Log::warning('User not authenticated');
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }

    //     // Validate the incoming request data
    //     try {
    //         $request->validate([
    //             'products' => 'required|array',
    //             'products.*.product_id' => 'required|integer|exists:products,id',
    //             'products.*.quantity' => 'required|integer|min:1',
    //             'products.*.discount' => 'nullable|numeric|min:0',
    //             'products.*.per_item_cost' => 'required|numeric|min:0',
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         \Log::error('Validation failed', ['errors' => $e->errors()]);
    //         throw $e;
    //     }

    //     // Use a database transaction for data integrity
    //     DB::beginTransaction();
    //     try {
    //         // Get the next sale_id from the sequence
    //         $saleId = DB::selectOne('SELECT nextval(\'sale_id_seq\')')->nextval;
    //         \Log::info('Generated sale_id', ['sale_id' => $saleId]);

    //         // Step 1: Create Sale records for each product with the same sale_id
    //         foreach ($request->products as $product) {
    //             Sale::create([
    //                 'sale_id' => $saleId,
    //                 'product_id' => $product['product_id'],
    //             ]);
    //         }

    //         // Step 2: Add all products as SalesItems with the same sale_id
    //         foreach ($request->products as $product) {
    //             SalesItem::create([
    //                 'sale_id' => $saleId,
    //                 'product_id' => $product['product_id'],
    //                 'quantity' => $product['quantity'],
    //                 'discount' => $product['discount'] ?? 0,
    //                 'per_item_cost' => $product['per_item_cost'],
    //             ]);
    //         }

    //         // Commit the transaction
    //         DB::commit();
    //         \Log::info('Sale recorded successfully', ['sale_id' => $saleId]);

    //         // Return a success response with the sale ID
    //         return response()->json([
    //             'message' => 'Sale recorded successfully',
    //             'sale_id' => $saleId,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         // Roll back on error
    //         DB::rollBack();
    //         \Log::error('Sale failed', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'message' => 'Sale failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
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

            DB::commit();
            Log::info('Sale recorded successfully', ['sale_id' => $saleId]);

            return response()->json([
                'message' => 'Sale recorded successfully',
                'sale_id' => $saleId,
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