<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\ProductInfo;

class ProductInfoController extends Controller
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

        // Restrict to rid 1, 2, 3
        if (!in_array($user->rid, [1, 2, 3])) {
            return response()->json(['message' => 'Unauthorized to add product Info'], 403);
        }

        // Validate the products array
        $validated = $request->validate([
            'products' => 'required|array|min:1',
            'products.*.name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($user) {
                    // Case-insensitive database check for unique name within the same company
                    if (ProductInfo::whereRaw('LOWER(name) = LOWER(?)', [$value])
                        ->where('cid', $user->cid)
                        ->exists()) {
                        $fail($value . ' has already been taken for this company.');
                    }
                },
            ],
            'products.*.hsn_code' => 'nullable|string|max:255',
            'products.*.description'=> 'nullable|string|max:500',
            'products.*.purchase_price' => 'nullable|numeric|min:0',
            'products.*.profit_percentage' => 'nullable|numeric|min:0',
            'products.*.gst' => 'nullable|numeric|min:0',
        ]);

        $createdProducts = [];

        // Use a transaction to ensure data consistency
        DB::beginTransaction();
        try {
            foreach ($validated['products'] as $productData) {
                // Set default values for optional fields
                $purchase_price = $productData['purchase_price'] ?? 0;
                $profit_percentage = $productData['profit_percentage'] ?? 0;
                $gst = $productData['gst'] ?? 0;

                // Calculate pre_gst_sale_cost and post_gst_sale_cost only if purchase_price is non-zero
                $pre_gst_sale_cost = $purchase_price > 0
                    ? round($purchase_price * (1 + $profit_percentage / 100), 2)
                    : 0;
                $post_gst_sale_cost = $pre_gst_sale_cost > 0
                    ? round($pre_gst_sale_cost * (1 + $gst / 100), 2)
                    : 0;

                $product = ProductInfo::create([
                    'name' => $productData['name'],
                    'hsn_code' => $productData['hsn_code'] ?? null,
                    'description' => $productData['description'] ?? null,
                    'purchase_price' => $purchase_price,
                    'profit_percentage' => $profit_percentage,
                    'pre_gst_sale_cost' => $pre_gst_sale_cost,
                    'gst' => $gst,
                    'post_gst_sale_cost' => $post_gst_sale_cost,
                    'uid' => $user->id,
                    'cid' => $user->cid,
                ]);

                $createdProducts[] = $product;
            }

            DB::commit();
            return response()->json([
                'message' => 'Product recorded successfully',
                'products' => $createdProducts,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create products: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create products', 'error' => $e->getMessage()], 500);
        }
    }
    public function allProductInfo($cid)
    {
    
        // Get the authenticated user
        $user = Auth::user();

        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
         // Check if the user belongs to the requested company
         if ($user->cid != $cid) {
            return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
        }

        // Define columns based on rid
        $columns = in_array($user->rid, [1, 2, 3])
            ? ['id', 'name', 'hsn_code','description', 'purchase_price', 'profit_percentage', 'pre_gst_sale_cost', 'gst', 'post_gst_sale_cost', 'uid', 'updated_at']
            : ['name', 'hsn_code','description', 'pre_gst_sale_cost', 'gst', 'post_gst_sale_cost','uid', 'updated_at'];

        // Fetch products for the user's cid
        $products = ProductInfo::where('cid', $user->cid)
            ->select($columns)
            ->orderBy('id','desc') 
            ->get();

        return response()->json($products, 200);
    }
    public function getProductById($pid)
    {
        // Authentication check
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        // Authorization check (restrict access based on role)
        if ($user->rid < 1 || $user->rid > 3) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    
        // Validate that the product_id is numeric
        if (!is_numeric($pid)) {
            return response()->json(['message' => 'Invalid product ID'], 422);
        }
    
        // Fetch the product by product_id
        $product = ProductInfo::where('id', $pid)
        ->select(
                'id as pid',
                'name as product_name',
                'hsn_code',
                'description',
                'purchase_price',
                'profit_percentage',
                'pre_gst_sale_cost',
                'gst',
                'post_gst_sale_cost'
           )
       ->first();
    
        // Check if the product exists
        if (!$product) {
            return response()->json([
                'message' => 'Product not found',
            ], 404);
        }
    
        // Return the product details
        return response()->json([
            'message' => 'Product retrieved successfully',
            'product' => $product,
        ], 200);
    }
    public function updateProductById($pid, Request $request)
    {
        // Authentication check
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Authorization check (restrict access based on role)
        if ($user->rid < 1 || $user->rid > 3) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Validate that the product_id is numeric
        if (!is_numeric($pid)) {
            return response()->json(['message' => 'Invalid product ID'], 422);
        }

        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'hsn_code' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'purchase_price' => 'sometimes|numeric|min:0',
            'profit_percentage' => 'sometimes|numeric|min:0|max:100',
            'pre_gst_sale_cost' => 'sometimes|numeric|min:0',
            'gst' => 'sometimes|numeric|min:0|max:100',
            'post_gst_sale_cost' => 'sometimes|numeric|min:0',
        ]);

        // Fetch the product by product_id
        $product = ProductInfo::find($pid);

        // Check if the product exists
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        // Update the product with validated data
        $product->update($validatedData);

        // Prepare response data with aliased fields to match getProductById
        $productData = [
            'pid' => $product->id,
            'product_name' => $product->name,
            'hsn_code' => $product->hsn_code,
            'description' => $product->description,
            'purchase_price' => $product->purchase_price,
            'profit_percentage' => $product->profit_percentage,
            'pre_gst_sale_cost' => $product->pre_gst_sale_cost,
            'gst' => $product->gst,
            'post_gst_sale_cost' => $product->post_gst_sale_cost,
        ];

        // Return the updated product details
        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $productData,
        ], 200);
    }
    public function destroy($pid)
    {
        // // Force JSON response
        // request()->headers->set('Accept', 'application/json');
    
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
    
        // Find the product by ID and ensure it belongs to the user's company
        $product = ProductInfo::where('id', $pid)->where('cid', $user->cid)->first();
    
        // Check if the product exists and belongs to the user's company
        if (!$product) {
            return response()->json(['message' => 'Product not found or not authorized'], 404);
        }
    
        // Attempt to delete the product
        try {
            $product->delete();
            return response()->json(['message' => 'Product deleted successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete product: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to delete product', 'error' => $e->getMessage()], 500);
        }
    }
}
