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
    public function getProductStock($cid)
    {
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get the authenticated user's ID
        $uid = Auth::id();

        // Validate the cid parameter
        if (!is_numeric($cid) || (int)$cid <= 0) {
            return response()->json(['error' => 'Invalid company ID'], 400);
        }

        // Execute the SQL query adapted to Laravel's query builder
        $products = DB::table('products as p')
            ->leftJoin('purchases as pur', 'p.id', '=', 'pur.product_id')
            ->leftJoin('transaction_purchases as tp', function ($join) use ($cid) {
                $join->on('pur.transaction_id', '=', 'tp.id')
                     ->where('tp.cid', '=', $cid);
            })
            ->leftJoin('purchase_items as pi', 'pur.id', '=', 'pi.purchase_id')
            ->where('p.uid', '=', $uid) // Filter products by the authenticated user
            ->select(
                'p.id',
                'p.name',
                'p.description',
                'p.category',
                'p.hscode',
                DB::raw('COALESCE(SUM(pi.quantity), 0) as total_stock')
            )
            ->groupBy('p.id', 'p.name', 'p.description', 'p.category', 'p.hscode')
            ->get();

        // Return the results as JSON
        return response()->json($products);
    }
}