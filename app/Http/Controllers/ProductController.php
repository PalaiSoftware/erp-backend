<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log; // Added for logging

class ProductController extends Controller
{
    public function store(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Only allow users with rid = 1 (admin) to create products
        if ($user->rid !== 1) {
            Log::warning('Unauthorized product creation attempt', ['user_id' => $user->id]);
            return response()->json([
                'message' => 'You are not allowed to create a product'
            ], 403);
        }

        // Log the incoming request
        Log::info('Product creation request received', ['request_data' => $request->all()]);

        // Validate request data for an array of products
        $request->validate([
            'products' => 'required|array', // Expect an array of products
            'products.*.name' => 'required|string|max:255',
            'products.*.description' => 'nullable|string',
            'products.*.category' => 'nullable|string',
            'products.*.hscode' => 'nullable|string|max:255',
            'products.*.uid' => 'required|integer',
        ]);

        // Create multiple products
        $createdProducts = [];
        foreach ($request->products as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'description' => $productData['description'] ?? null,
                'category' => $productData['category'] ?? null,
                'hscode' => $productData['hscode'] ?? null,
                'uid' => $productData['uid'],
            ]);
            $createdProducts[] = $product;
            Log::info('Product created', ['product_id' => $product->id, 'name' => $product->name]);
        }

        return response()->json([
            'message' => 'Products created successfully',
            'products' => $createdProducts
        ], 201);
    }

    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }
}