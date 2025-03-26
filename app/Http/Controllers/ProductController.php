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
            'products.*.category' => 'nullable|string', 
            'products.*.hscode' => 'required|string|max:255', 
            'products.*.uid' => 'required|integer', 
            'products.*.cid' => 'required|integer', 
        ]);

    
        $createdProducts = [];
        foreach ($validatedData['products'] as $productData) {
            $product = Product::create([
                'name' => $productData['name'],
                'description' => $productData['description'] ?? null,
                'category' => $productData['category'] ?? null,
                'hscode' => $productData['hscode'],
                'uid' => $productData['uid'],
                'cids' => [$productData['cid']], 
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
        
    // Get the authenticated user
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    // Restrict access to users with rid between 5 and 10 inclusive
    if ($user->rid < 5 || $user->rid > 10) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
        $validated = $request->validate([
            'cid' => 'required|integer',
        ]);

        
        $cid = $validated['cid'];

        
        $products = Product::whereJsonContains('cids', (int)$validated['cid'])
                           ->select('id','name', 'description', 'category', 'hscode')
                           ->get();

        
        return response()->json([
            'message' => 'Products retrieved successfully',
            'products' => $products,
        ], 200);
    }
    public function checkHscodeProduct(Request $request)
    {
    // Get the authenticated user
    $user = Auth::user();
    
    // Check if user is authenticated
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    
    // Restrict to rid 5, 6, 7, or 8 only
    if (!in_array($user->rid, [5, 6, 7, 8])) {
        return response()->json(['message' => 'Unauthorized to check product'], 403);
    }
        
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        
        $validated = $request->validate([
            'hscode' => 'required|string',
        ]);

    
        $hscode = $validated['hscode'];

        
        $products = Product::where('hscode', $hscode)
                           ->select('id','name', 'description', 'category', 'hscode')
                           ->get();

        
        if ($products->isEmpty()) {
            return response()->json([
                'message' => 'No products found for the given HS code',
                'products' => []
            ], 404);
        } else {
            return response()->json([
                'message' => 'Products retrieved successfully for the given HS code',
                'products' => $products
            ], 200);
        }
    }
    public function addCompanyToProduct(Request $request)
    {
     // Get the authenticated user
    $user = Auth::user();
    
    // Check if user is authenticated
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
    
    // Restrict to rid 5, 6, 7, or 8 only
    if (!in_array($user->rid, [5, 6, 7, 8])) {
        return response()->json(['message' => 'Unauthorized to add this product to company'], 403);
    }
        
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id', 
            'hs_code' => 'required|string|exists:products,hscode', 
            'cid' => 'required|integer',                           
        ]);

        
        $product = Product::where('id', $validated['product_id'])
                          ->where('hscode', $validated['hs_code'])
                          ->first();

    
        if (!$product) {
            return response()->json(['message' => 'Product with the specified ID and HS code not found'], 404);
        }


        $cids = $product->cids ?? [];

        
        if (!in_array($validated['cid'], $cids)) {
            $cids[] = $validated['cid'];    
            $product->cids = $cids;         
            $product->save();               
            return response()->json(['message' => 'Product added to your company successfully'], 201);
        } else {
            return response()->json(['message' => 'Product already exists in your company'], 200);
        }
    }

    public function getProductStock($cid)
    {
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        
        $uid = Auth::id();

        
        if (!is_numeric($cid) || (int)$cid <= 0) {
            return response()->json(['error' => 'Invalid company ID'], 400);
        }

        
        $products = DB::table('products as p')
            ->leftJoin('purchases as pur', 'p.id', '=', 'pur.product_id')
            ->leftJoin('transaction_purchases as tp', function ($join) use ($cid) {
                $join->on('pur.transaction_id', '=', 'tp.id')
                     ->where('tp.cid', '=', $cid);
            })
            ->leftJoin('purchase_items as pi', 'pur.id', '=', 'pi.purchase_id')
            ->where('p.uid', '=', $uid) 
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

        
        return response()->json($products);
    }
}