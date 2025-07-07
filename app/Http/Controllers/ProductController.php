<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductValue; 
use App\Models\Unit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; 

class ProductController extends Controller
{
    public function store(Request $request)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Auth check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Role restriction
    if (!in_array($user->rid, [1, 2, 3,4])) {
        return response()->json(['message' => 'Unauthorized to add product'], 403);
    }

    // Validation
    $validatedData = $request->validate([
        'products' => 'required|array',
        'products.*.name' => [
            'required',
            'string',
            'max:255',
            function ($attribute, $value, $fail) {
                if (Product::where('name', $value)->exists()) {
                    $fail($value . ' has already been taken.');
                }
            },
        ],
        'products.*.category_id' => 'required|integer|exists:categories,id',
        'products.*.hscode' => 'nullable|string|max:255',
    ]);

    // Check duplicate names in current request
    $names = collect($validatedData['products'])->pluck('name');
    if ($names->duplicates()->isNotEmpty()) {
        return response()->json([
            'message' => "Duplicate product names found in the request."
        ], 422);
    }

    // Create products
    $createdProducts = [];
    foreach ($validatedData['products'] as $productData) {
        $product = Product::create([
            'name' => $productData['name'],
            'category_id' => $productData['category_id'],
            'hscode' => $productData['hscode'] ?? null,
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
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Authentication and authorization checks
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    if ($user->rid < 1 || $user->rid > 5) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate the request
    $validated = $request->validate([
        'category_id' => 'nullable|integer|exists:categories,id',
    ]);

    $query = Product::leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->select(
            'products.id',
            'products.name as product_name',
            'products.category_id',
            'categories.name as category_name',
            'products.hscode'
        );

    // Filter by category_id if provided
    if ($request->has('category_id')) {
        $query->where('products.category_id', $validated['category_id']);
    }

    $products = $query->orderBy('products.id', 'desc')
        ->get();

    return response()->json([
        'message' => 'Products retrieved successfully',
        'products' => $products,
    ], 200);
}

public function getProductById($product_id)
{
    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Authorization check (restrict access based on role)
    if ($user->rid < 1 || $user->rid > 5) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate that the product_id is numeric
    if (!is_numeric($product_id)) {
        return response()->json(['message' => 'Invalid product ID'], 422);
    }

    // Fetch the product by product_id
    $product = Product::where('products.id', $product_id)
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->select(
            'products.id as product_id',
            'products.name as product_name',
            'products.category_id',
            'categories.name as category_name',
            'products.hscode'
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
public function update(Request $request, $id)
{
    try {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if (!in_array($user->rid, [1, 2, 3, 4, 5])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|integer|exists:categories,id',
            'hscode' => 'nullable|string|max:255',
        ]);

        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product
        ], 200);
    } catch (\Exception $e) {
        \Log::error($e->getMessage());
        return response()->json(['message' => 'Server Error', 'error' => $e->getMessage()], 500);
    }
}
}