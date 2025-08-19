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
                // Case-insensitive database check
                if (Product::whereRaw('LOWER(name) = LOWER(?)', [$value])->exists()) {
                    $fail($value . ' has already been taken.');
                }
            },
        ],
        'products.*.category_id' => 'nullable|integer|exists:categories,id',
        'products.*.hscode' => 'nullable|string|max:255',
        'products.*.p_unit' => [
            'required',  // ← will trigger if missing
            'integer',
            'exists:units,id',
            function ($attribute, $value, $fail) {
                // ← will trigger if value is 0
                if ($value === 0) {
                    $fail('None is not applicable. Select any other unit.');
                }
            },
        ],
        'products.*.s_unit' => 'nullable|integer|exists:units,id',
        'products.*.c_factor' => 'nullable|numeric',
    ], [
        // Custom error messages
        'products.*.p_unit.required' => 'Primary unit is required.',
        'products.*.p_unit.integer' => 'Primary unit must be a valid number.',
        'products.*.p_unit.exists' => 'Selected primary unit does not exist.',
    ]);

    // Case-insensitive duplicate check within the request
    $names = collect($validatedData['products'])->pluck('name');
    $lowercaseNames = $names->map(function ($name) {
        return strtolower($name);
    });
    if ($lowercaseNames->duplicates()->isNotEmpty()) {
        return response()->json([
            'message' => "Duplicate product names found in the request (case-insensitive)."
        ], 422);
    }

    // Create products
    $createdProducts = [];
    foreach ($validatedData['products'] as $productData) {
        $product = Product::create([
            'name' => $productData['name'],
            'category_id' => $productData['category_id'] ?? 0,
            'hscode' => $productData['hscode'] ?? null,
            'p_unit' => $productData['p_unit'],
            's_unit' => $productData['s_unit'] ?? 0,
            'c_factor' => $productData['c_factor'] ?? 0,
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
        ->leftJoin('units as primary_units', 'products.p_unit', '=', 'primary_units.id')
        ->leftJoin('units as secondary_units', 'products.s_unit', '=', 'secondary_units.id')
        ->select(
            'products.id',
            'products.name as product_name',
            'products.category_id',
            'categories.name as category_name',
            'products.hscode',
            'products.p_unit',
            'primary_units.name as primary_unit',
            'products.s_unit',
            'secondary_units.name as secondary_unit',
            'products.c_factor'
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
    if ($user->rid < 1 || $user->rid > 3) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate that the product_id is numeric
    if (!is_numeric($product_id)) {
        return response()->json(['message' => 'Invalid product ID'], 422);
    }

    // Fetch the product by product_id
    $product = Product::where('products.id', $product_id)
        ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
        ->leftJoin('units as primary_units', 'products.p_unit', '=', 'primary_units.id')
        ->leftJoin('units as secondary_units', 'products.s_unit', '=', 'secondary_units.id')
        ->select(
            'products.id as product_id',
            'products.name as product_name',
            'products.category_id',
            'categories.name as category_name',
            'products.hscode',
            'products.p_unit',
            'primary_units.name as primary_unit',
            'products.s_unit',
            'secondary_units.name as secondary_unit',
            'products.c_factor'
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
        if (!in_array($user->rid, [1, 2, 3])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Validation
    $validated = $request->validate([
        'name' => [
            'required',
            'string',
            'max:255',
            function ($attribute, $value, $fail) use ($id) {
                // Case-insensitive database check, ignoring current product
                if (Product::whereRaw('LOWER(name) = LOWER(?)', [$value])->where('id', '!=', $id)->exists()) {
                    $fail($value . ' has already been taken.');
                }
            },
        ],
        'category_id' => 'nullable|integer|exists:categories,id',
        'hscode' => 'nullable|string|max:255',
        'products.*.p_unit' => [
            'required',  // ← will trigger if missing
            'integer',
            'exists:units,id',
            function ($attribute, $value, $fail) {
                // ← will trigger if value is 0
                if ($value === 0) {
                    $fail('None is not applicable. Select any other unit.');
                }
            },
        ],
        'products.*.s_unit' => 'nullable|integer|exists:units,id',
        'products.*.c_factor' => 'nullable|numeric',
    ], [
        // Custom error messages
        'products.*.p_unit.required' => 'Primary unit is required.',
        'products.*.p_unit.integer' => 'Primary unit must be a valid number.',
        'products.*.p_unit.exists' => 'Selected primary unit does not exist.',
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
        return response()->json([ 'error' => $e->getMessage()], 500);
    }
}
public function getUnitsByProductId(Request $request, $product_id)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Authentication and authorization checks
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    if ($user->rid < 1 || $user->rid > 5) {
        return response()->json(['message' => 'Forbidden'], 403);
    }

    // Validate product_id from route parameter
    if (!is_numeric($product_id) || intval($product_id) <= 0) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'product_id' => ['The product ID must be a positive integer.']
            ]
        ], 422);
    }

    // Fetch product with p_unit and s_unit
    $product = Product::where('products.id', $product_id)
        ->select('products.id', 'products.p_unit', 'products.s_unit')
        ->first();

    if (!$product) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'product_id' => ['The product ID must exist in the products table.']
            ]
        ], 422);
    }

    // Fetch unit details for p_unit and s_unit
    $units = Unit::whereIn('id', array_filter([$product->p_unit, $product->s_unit]))
        ->select('id', 'name')
        ->get()
        ->map(function ($unit) {
            return [
                'id' => $unit->id,
                'name' => $unit->name,
            ];
        });

    if ($units->isEmpty()) {
        return response()->json(['message' => 'Units not found for this product'], 404);
    }

    return response()->json($units, 200);
}
}