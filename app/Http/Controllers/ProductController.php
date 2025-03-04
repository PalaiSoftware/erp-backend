<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        // Get the authenticated user
        $user = Auth::user();

        // Only allow users with rid = 1 (admin) to create a product
        if ($user->rid !== 1) {
            return response()->json([
                'message' => 'You are not allowed to create a product'
            ], 403);
        }

        // Validate request data
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'hscode' => 'nullable|string|max:255',
            'uid' => 'required|integer',
        ]);

        // Create the product
        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'hscode' => $request->hscode,
            'uid' => $request->uid,
        ]);

        return response()->json([
            'message' => 'Product created successfully',
            'product' => $product
        ], 201);
    }

    public function index()
    {
        $products = Product::all();
        return response()->json($products);
    }
}
