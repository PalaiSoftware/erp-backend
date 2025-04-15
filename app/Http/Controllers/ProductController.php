<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; 

class ProductController extends Controller
{
    public function store(Request $request)
    {
         // Get the authenticated user
        $user = Auth::user();
        
        // Check if user is authenticated
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        
        // Restrict to rid 5, 6, 7, or 8 only
        if (!in_array($user->rid, [5, 6, 7, 8])) {
            return response()->json(['message' => 'Unauthorized to add product to company'], 403);
        }
            
            $validatedData = $request->validate([
                'products' => 'required|array', 
                'products.*.name' => 'required|string|max:255',
                'products.*.description' => 'nullable|string',
                'products.*.category_id' => 'required|integer|exists:categories,id', 
                'products.*.hscode' => 'nullable|string|max:255', 
                'products.*.uid' => 'required|integer', 
                'products.*.cid' => 'required|integer', 
            ]);

        
            $createdProducts = [];
            foreach ($validatedData['products'] as $productData) {
                $product = Product::create([
                    'name' => $productData['name'],
                    'description' => $productData['description'] ?? null,
                    'category_id' => $productData['category_id'], 
                    'hscode' => $productData['hscode'] ?? null,
                    'uid' => $productData['uid'],
                    'cid' => $productData['cid'], 
                ]);
                $createdProducts[] = $product;
            }

            
            return response()->json([
                'message' => 'Products created successfully',
                'products' => $createdProducts
            ], 201);
    }

    public function index(Request $request)
{
    // Authentication and authorization checks
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    if ($user->rid < 5 || $user->rid > 10) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate the request
    $validated = $request->validate([
        'cid' => 'required|integer',
    ]);

    // Fetch products by cid with category name
    $products = Product::where('products.cid', $validated['cid'])
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id') // Join with categories table
        ->select(
            'products.id',
            'products.name as product_name', // Rename columns for clarity
            'products.description',
            'products.category_id',
            'categories.name as category_name', // Include category name
            'products.hscode',
            'products.cid'
        )
        ->orderBy('products.id', 'desc')
        ->get();

    // Return response
    return response()->json([
        'message' => 'Products retrieved successfully',
        'products' => $products,
    ], 200);
}
    public function checkHscodeProduct(Request $request)
    {
        // Authentication and authorization checks
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (!in_array($user->rid, [5, 6, 7, 8])) {
            return response()->json(['message' => 'Unauthorized to check product'], 403);
        }
    
        // Validate BOTH cid and hscode from the request body
        $validated = $request->validate([
            'hscode' => 'required|string',
            'cid' => 'required|integer', // Add cid to validation rules
        ]);
    
        // Fetch products by HS code AND cid from the request body
        $products = Product::where('hscode', $validated['hscode'])
                           ->where('cid', $validated['cid'])
                           ->select('id', 'name', 'description', 'category_id', 'hscode', 'cid')
                           ->get();
    
        // Return response
        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'No products found for the given HS code and company.',
                'products' => []
            ], 404);
        } else {
            return response()->json([
                'message' => 'Products retrieved successfully.',
                'products' => $products
            ], 200);
        }
    }

    public function update(Request $request, $id)
        {
            // Authentication and authorization checks
                $user = Auth::user();
                if (!$user) {
                    return response()->json(['message' => 'Unauthorized'], 401);
                }
                if (!in_array($user->rid, [5, 6, 7, 8])) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }

                // Validate the request
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'description' => 'nullable|string',
                    'category_id' => 'required|integer|exists:categories,id',
                    'hscode' => 'nullable|string|max:255',
                    'uid' => 'required|integer',
                    'cid' => 'required|integer',
                ]);

                // Find the product
                $product = Product::find($id);
                if (!$product) {
                    return response()->json(['message' => 'Product not found'], 404);
                }

                // Update the product
                $product->update($validated);

                // Return response
                return response()->json([
                    'message' => 'Product updated successfully',
                    'product' => $product
                ], 200);
        }
        public function getProductById($product_id)
        {
            // Authentication check
            $user = Auth::user();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
    
            // Authorization check (e.g., restrict access based on role or cid)
            if ($user->rid < 5 || $user->rid > 10) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
    
            // Validate that the product_id is numeric
            if (!is_numeric($product_id)) {
                return response()->json(['message' => 'Invalid product ID'], 422);
            }
    
            // Fetch the product by product_id
            $product = Product::where('products.id', $product_id)
    ->leftJoin('categories', 'products.category_id', '=', 'categories.id') // Join with categories table
    ->select(
        'products.id as product_id',
        'products.name as product_name',
        'products.description',
        'products.category_id',
        'categories.name as category_name', // Include category name
        'products.hscode',
        'products.cid'
    )
    ->first();
    
            // Check if the product exists
            if (!$product) {
                return response()->json([
                    'message' => 'Product not found',
                ], 404);
            }
    
            // Ensure the product belongs to the user's company (cid)
            if ($product->cid !== $user->cid) {
                return response()->json(['message' => 'Access denied'], 403);
            }
    
            // Return the product details
            return response()->json([
                'message' => 'Product retrieved successfully',
                'product' => $product,
            ], 200);
        }
   
}