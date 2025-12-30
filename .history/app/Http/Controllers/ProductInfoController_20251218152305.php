<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ProductInfo;

class ProductInfoController extends Controller
{
    
    public function allProductInfo($cid)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
    
            if ($user->cid != $cid) {
                return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
            }
    
            // CORRECTED COLUMNS: Include purchase_price for privileged roles
            $columns = in_array($user->rid, [1, 2, 3])
                ? [
                    'product_info.pid',
                    'products.name as product_name',
                    'product_info.hsn_code',
                    'product_info.description',
                    'product_info.unit_id',
                    'units.name as unit_name',
                    'product_info.purchase_price', // CRITICAL: Base price from database
                    'product_info.profit_percentage',
                    'product_info.pre_gst_sale_cost',
                    'product_info.gst',
                    'product_info.post_gst_sale_cost',
                    'product_info.uid',
                    'product_info.updated_at'
                ]
                : [
                    'products.name as product_name',
                    'product_info.hsn_code',
                    'product_info.description',
                    'product_info.unit_id',
                    'units.name as unit_name',
                    'product_info.pre_gst_sale_cost',
                    'product_info.gst',
                    'product_info.post_gst_sale_cost',
                    'product_info.uid',
                    'product_info.updated_at'
                ];
    
            $products = ProductInfo::where('product_info.cid', $user->cid)
                ->join('products', 'product_info.pid', '=', 'products.id')
                ->join('units', 'product_info.unit_id', '=', 'units.id')
                ->select($columns)
                ->orderBy('product_info.pid', 'desc')
                ->get();
    
            if (in_array($user->rid, [1, 2, 3])) {
                $productIds = $products->pluck('pid')->toArray();
                
                // FIXED QUERY: Use users table join to get company ID
                $latestPurchaseData = DB::table('purchase_items as pi')
                    ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
                    ->join('users as u', 'pb.uid', '=', 'u.id') // Join users to get company
                    ->where('u.cid', $user->cid) // Filter by company ID from users
                    ->whereIn('pi.pid', $productIds)
                    ->orderBy('pb.created_at', 'desc')
                    ->get(['pi.pid', 'pi.dis', 'pi.gst'])
                    ->keyBy('pid');
    
                foreach ($products as $product) {
                    // Handle null/empty purchase_price safely
                    $basePrice = $product->purchase_price ?? 0;
                    if ($basePrice === null || $basePrice === '') {
                        $basePrice = 0;
                    }
                    $basePrice = (float)$basePrice;
                    
                    $latest = $latestPurchaseData[$product->pid] ?? null;
                    $dis = $latest ? (float)$latest->dis : 0;
                    $gst = $latest ? (float)$latest->gst : 0;
    
                    // Calculate effective price: (base - discount) + GST
                    $discountedPrice = $basePrice * (1 - ($dis / 100));
                    $effectivePrice = $discountedPrice * (1 + ($gst / 100));
    
                    $product->purchase_price = round($effectivePrice, 2);
                    $product->base_purchase_price = round($basePrice, 2);
                    $product->latest_discount = round($dis, 2);
                    $product->latest_gst = round($gst, 2);
                }
            }
    
            Log::info('Fetched product info with effective pricing', [
                'cid' => $cid,
                'user_id' => $user->id,
                'rid' => $user->rid,
                'product_count' => $products->count(),
            ]);
    
            return response()->json($products, 200);
            
        } catch (\Exception $e) {
            $logContext = [
                'cid' => $cid ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
    
            try {
                $currentUser = Auth::user();
                if ($currentUser) {
                    $logContext['user_id'] = $currentUser->id;
                    $logContext['rid'] = $currentUser->rid ?? 'unknown';
                }
            } catch (\Exception $ex) {
                // Ignore auth errors during logging
            }
    
            Log::error('Failed to fetch product info', $logContext);
            
            return response()->json([
                'message' => 'Failed to fetch product info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    public function getProductById($pid)
    {
        // Authentication check
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Authorization check (restrict access based on role)
        if (!in_array($user->rid, [1, 2, 3])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Validate that the product_id is numeric
        if (!is_numeric($pid) || intval($pid) <= 0) {
            return response()->json(['message' => 'Invalid product ID'], 422);
        }

        $cid = $user->cid;
        $pid = (int) $pid;

        // Fetch the product by pid and cid with joins
        try {
            $product = ProductInfo::where('product_info.pid', $pid)
                ->where('product_info.cid', $cid)
                ->join('products', 'product_info.pid', '=', 'products.id')
                ->join('units', 'product_info.unit_id', '=', 'units.id')
                ->select(
                    'product_info.pid',
                    'products.name as product_name',
                    'product_info.hsn_code',
                    'product_info.description',
                    'product_info.unit_id',
                    'units.name as unit_name',
                    'product_info.purchase_price',
                    'product_info.profit_percentage',
                    'product_info.pre_gst_sale_cost',
                    'product_info.gst',
                    'product_info.post_gst_sale_cost'
                )
                ->first();

            // Check if the product exists
            if (!$product) {
                Log::warning('Product not found for pid and cid', [
                    'pid' => $pid,
                    'cid' => $cid,
                    'user_id' => $user->id,
                ]);
                return response()->json([
                    'message' => 'Product not found for this company',
                ], 404);
            }

            Log::info('Product retrieved successfully', [
                'pid' => $pid,
                'cid' => $cid,
                'user_id' => $user->id,
            ]);

            // Return the product details
            return response()->json([
                'message' => 'Product retrieved successfully',
                'product' => $product,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch product', [
                'pid' => $pid,
                'cid' => $cid,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Failed to fetch product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
   

public function updateProductById($pid, Request $request)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Authorization check
    if (!in_array($user->rid, [1, 2, 3])) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate product ID
    if (!is_numeric($pid) || intval($pid) <= 0) {
        return response()->json(['message' => 'Invalid product ID'], 422);
    }

    $cid = $user->cid;
    $pid = (int) $pid;

    // Validate request data
    try {
        $validatedData = $request->validate([
            'hsn_code' => 'sometimes|string|max:255|nullable',
            'description' => 'sometimes|string|max:500|nullable',
            'unit_id' => 'sometimes|integer|exists:units,id',
            'purchase_price' => 'sometimes|numeric|min:0',
            'profit_percentage' => 'sometimes|numeric|min:0|max:100',
            'pre_gst_sale_cost' => 'sometimes|numeric|min:0',
            'gst' => 'sometimes|numeric|min:0|max:100',
            'post_gst_sale_cost' => 'sometimes|numeric|min:0',
        ]);
    } catch (\Illuminate\Validation\ValidationException $e) {
        Log::error('Validation failed for updateProductById', [
            'pid' => $pid,
            'cid' => $cid,
            'user_id' => $user->id,
            'errors' => $e->errors(),
            'request_data' => $request->all(),
        ]);
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }

    // Fetch product_info for the specific pid and cid
    $product = ProductInfo::where('pid', $pid)->where('cid', $cid)->first();
    if (!$product) {
        Log::warning('Product not found in product_info', [
            'pid' => $pid,
            'cid' => $cid,
            'user_id' => $user->id,
        ]);
        return response()->json(['message' => 'Product not found for this company'], 404);
    }

    // Prepare data for update
    $productInfoData = [];
    foreach (['hsn_code', 'description', 'unit_id', 'purchase_price', 'profit_percentage', 'pre_gst_sale_cost', 'gst', 'post_gst_sale_cost'] as $field) {
        if (isset($validatedData[$field])) {
            $productInfoData[$field] = $validatedData[$field];
        }
    }

    // Recalculate pre_gst_sale_cost and post_gst_sale_cost if purchase_price, profit_percentage, or gst is provided
    if (isset($validatedData['purchase_price']) || isset($validatedData['profit_percentage']) || isset($validatedData['gst'])) {
        $purchase_price = $validatedData['purchase_price'] ?? $product->purchase_price;
        $profit_percentage = $validatedData['profit_percentage'] ?? $product->profit_percentage;
        $gst = $validatedData['gst'] ?? $product->gst;

        // Calculate pre_gst_sale_cost
        $productInfoData['pre_gst_sale_cost'] = $purchase_price > 0
            ? round($purchase_price * (1 + $profit_percentage / 100), 2)
            : 0;

        // Calculate post_gst_sale_cost
        $productInfoData['post_gst_sale_cost'] = $productInfoData['pre_gst_sale_cost'] > 0
            ? round($productInfoData['pre_gst_sale_cost'] * (1 + $gst / 100), 2)
            : 0;
    }

    // Update product_info if there are changes
    if (!empty($productInfoData)) {
        $affectedRows = ProductInfo::where('pid', $pid)
            ->where('cid', $cid)
            ->update($productInfoData);

        if ($affectedRows !== 1) {
            Log::warning('Unexpected number of rows updated in product_info', [
                'pid' => $pid,
                'cid' => $cid,
                'user_id' => $user->id,
                'affected_rows' => $affectedRows,
                'data' => $productInfoData,
            ]);
        }

        Log::info('Product info updated', [
            'pid' => $pid,
            'cid' => $cid,
            'user_id' => $user->id,
            'data' => $productInfoData,
        ]);
    }

    // Fetch updated product_info
    $updatedProduct = ProductInfo::where('pid', $pid)
        ->where('cid', $cid)
        ->first();

    if (!$updatedProduct) {
        Log::error('Failed to fetch updated product_info', [
            'pid' => $pid,
            'cid' => $cid,
            'user_id' => $user->id,
        ]);
        return response()->json(['message' => 'Failed to fetch updated product data'], 500);
    }

    // Prepare response
    $productData = [
        'pid' => $updatedProduct->pid,
        'hsn_code' => $updatedProduct->hsn_code,
        'description' => $updatedProduct->description,
        'unit_id' => $updatedProduct->unit_id,
        'purchase_price' => $updatedProduct->purchase_price,
        'profit_percentage' => $updatedProduct->profit_percentage,
        'pre_gst_sale_cost' => $updatedProduct->pre_gst_sale_cost,
        'gst' => $updatedProduct->gst,
        'post_gst_sale_cost' => $updatedProduct->post_gst_sale_cost,
    ];

    return response()->json([
        'message' => 'Product updated successfully',
        'product' => $productData,
    ], 200);
}

public function destroy($pid)
    {
        // Force JSON response
        request()->headers->set('Accept', 'application/json');

        // Get the authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Restrict to rid 1, 2, 3
        if (!in_array($user->rid, [1, 2, 3])) {
            return response()->json(['message' => 'Unauthorized to delete product'], 403);
        }

        // Validate product ID
        if (!is_numeric($pid) || intval($pid) <= 0) {
            return response()->json(['message' => 'Invalid product ID'], 422);
        }

        $cid = $user->cid;
        $pid = (int) $pid;

        // Check if the product exists for the given pid and cid
        $product = ProductInfo::where('pid', $pid)->where('cid', $cid)->first();
        if (!$product) {
            Log::warning('Product not found for deletion', [
                'pid' => $pid,
                'cid' => $cid,
                'user_id' => $user->id,
            ]);
            return response()->json(['message' => 'Product not found or not authorized'], 404);
        }

        // Attempt to delete the product
        try {
            $affectedRows = ProductInfo::where('pid', $pid)
                ->where('cid', $cid)
                ->delete();

            if ($affectedRows !== 1) {
                Log::warning('Unexpected number of rows deleted in product_info', [
                    'pid' => $pid,
                    'cid' => $cid,
                    'user_id' => $user->id,
                    'affected_rows' => $affectedRows,
                ]);
            }

            Log::info('Product deleted successfully', [
                'pid' => $pid,
                'cid' => $cid,
                'user_id' => $user->id,
            ]);

            return response()->json(['message' => 'Product deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete product', [
                'pid' => $pid,
                'cid' => $cid,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Failed to delete product',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
