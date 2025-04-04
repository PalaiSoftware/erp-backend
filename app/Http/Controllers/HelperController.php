<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\TransactionPurchase;
use App\Models\Sale;
use App\Models\SalesItem;
use App\Models\TransactionSales;
use App\Models\User;
use App\Models\Company;
use App\Models\Unit;

class HelperController extends Controller
{

// public function getProductStock($cid)
// {
//     // Check if user is authenticated
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     $uid = Auth::id();

//     // Validate and cast cid to integer
//     if (!is_numeric($cid) || (int)$cid <= 0) {
//         return response()->json(['error' => 'Invalid company ID'], 400);
//     }
//     $cid = (int)$cid;

//     // Fetch products with stock calculations
//     $products = DB::table('products as p')
//         // ->where('p.uid', $uid)
//         ->select([
//             'p.id',
//             'p.name',
//             'p.description',
//             'p.category',
//             'p.hscode',
//             // Purchase stock subquery
//             DB::raw("(
//                 SELECT COALESCE(SUM(pi.quantity), 0)
//                 FROM purchases pur
//                 JOIN transaction_purchases tp ON pur.transaction_id = tp.id
//                 JOIN purchase_items pi ON pur.id = pi.purchase_id
//                 WHERE pur.product_id = p.id AND tp.cid = $cid
//             ) as purchase_stock"),
//             // Sales stock subquery
//             DB::raw("(
//                 SELECT COALESCE(SUM(si.quantity), 0)
//                 FROM sales s
//                 JOIN transaction_sales ts ON s.transaction_id = ts.id
//                 JOIN sales_items si ON s.id = si.sale_id
//                 WHERE s.product_id = p.id AND ts.cid = $cid
//             ) as sales_stock"),
//             // Current stock (purchase_stock - sales_stock)
//             DB::raw("(
//                 SELECT COALESCE(SUM(pi.quantity), 0)
//                 FROM purchases pur
//                 JOIN transaction_purchases tp ON pur.transaction_id = tp.id
//                 JOIN purchase_items pi ON pur.id = pi.purchase_id
//                 WHERE pur.product_id = p.id AND tp.cid = $cid
//             ) - (
//                 SELECT COALESCE(SUM(si.quantity), 0)
//                 FROM sales s
//                 JOIN transaction_sales ts ON s.transaction_id = ts.id
//                 JOIN sales_items si ON s.id = si.sale_id
//                 WHERE s.product_id = p.id AND ts.cid = $cid
//             ) as current_stock")
//         ])
//         ->get();

//     return response()->json($products);
// }
public function getProductStock($cid)
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
    $uid = Auth::id();

    // Validate and cast cid to integer
    if (!is_numeric($cid) || (int)$cid <= 0) {
        return response()->json(['error' => 'Invalid company ID'], 400);
    }
    $cid = (int)$cid;

    // Fetch products with stock calculations, only for products with transactions for this cid
    $products = DB::table('products as p')
        ->join('categories as c','p.category_id','=','c.id')
        ->where(function ($query) use ($cid) {
            // Products with purchases for this company
            $query->whereExists(function ($subquery) use ($cid) {
                $subquery->select(DB::raw(1))
                         ->from('purchases as pur')
                         ->join('transaction_purchases as tp', 'pur.transaction_id', '=', 'tp.id')
                         ->where('pur.product_id', '=', DB::raw('p.id'))
                         ->where('tp.cid', $cid);
            })
            // Or products with sales for this company
            ->orWhereExists(function ($subquery) use ($cid) {
                $subquery->select(DB::raw(1))
                         ->from('sales as s')
                         ->join('transaction_sales as ts', 's.transaction_id', '=', 'ts.id')
                         ->where('s.product_id', '=', DB::raw('p.id'))
                         ->where('ts.cid', $cid);
            });
        })
        ->select([
            'p.id',
            'p.name',
            'p.description',
            // 'p.category',
            'c.name as category',
            'p.hscode',
            DB::raw("(
                SELECT COALESCE(SUM(pi.quantity), 0)
                FROM purchases pur
                JOIN transaction_purchases tp ON pur.transaction_id = tp.id
                JOIN purchase_items pi ON pur.id = pi.purchase_id
                WHERE pur.product_id = p.id AND tp.cid = $cid
            ) as purchase_stock"),
            DB::raw("(
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales s
                JOIN transaction_sales ts ON s.transaction_id = ts.id
                JOIN sales_items si ON s.id = si.sale_id
                WHERE s.product_id = p.id AND ts.cid = $cid
            ) as sales_stock"),
            DB::raw("(
                SELECT COALESCE(SUM(pi.quantity), 0)
                FROM purchases pur
                JOIN transaction_purchases tp ON pur.transaction_id = tp.id
                JOIN purchase_items pi ON pur.id = pi.purchase_id
                WHERE pur.product_id = p.id AND tp.cid = $cid
            ) - (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales s
                JOIN transaction_sales ts ON s.transaction_id = ts.id
                JOIN sales_items si ON s.id = si.sale_id
                WHERE s.product_id = p.id AND ts.cid = $cid
            ) as current_stock")
        ])
        ->get();

    return response()->json($products);
}


public function getMultipleProductStock(Request $request, $cid)
{
    // Check if the user is logged in
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    $uid = Auth::id(); // Get the user ID

    // Validate company ID
    if (!is_numeric($cid) || (int)$cid <= 0) {
        return response()->json(['error' => 'Invalid company ID'], 400);
    }
    $cid = (int)$cid;

    // Get the product IDs from the request body
    $product_ids = $request->input('product_ids');
    if (!is_array($product_ids) || empty($product_ids)) {
        return response()->json(['error' => 'Invalid or missing product IDs'], 400);
    }

    // Convert all product IDs to integers for safety
    $product_ids = array_map('intval', $product_ids);

    // Query the database for stock info on all product IDs
    $products = DB::table('products as p')
        ->whereIn('p.id', $product_ids)
        ->where('p.uid', $uid)
        ->select([
            'p.id',
            'p.name',
            'p.description',
            'p.category',
            'p.hscode',
            // Purchase stock
            DB::raw("(
                SELECT COALESCE(SUM(pi.quantity), 0)
                FROM purchases pur
                JOIN transaction_purchases tp ON pur.transaction_id = tp.id
                JOIN purchase_items pi ON pur.id = pi.purchase_id
                WHERE pur.product_id = p.id AND tp.cid = $cid
            ) as purchase_stock"),
            // Sales stock
            DB::raw("(
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales s
                JOIN transaction_sales ts ON s.transaction_id = ts.id
                JOIN sales_items si ON s.id = si.sale_id
                WHERE s.product_id = p.id AND ts.cid = $cid
            ) as sales_stock"),
            // Current stock (purchases - sales)
            DB::raw("(
                SELECT COALESCE(SUM(pi.quantity), 0)
                FROM purchases pur
                JOIN transaction_purchases tp ON pur.transaction_id = tp.id
                JOIN purchase_items pi ON pur.id = pi.purchase_id
                WHERE pur.product_id = p.id AND tp.cid = $cid
            ) - (
                SELECT COALESCE(SUM(si.quantity), 0)
                FROM sales s
                JOIN transaction_sales ts ON s.transaction_id = ts.id
                JOIN sales_items si ON s.id = si.sale_id
                WHERE s.product_id = p.id AND ts.cid = $cid
            ) as current_stock")
        ])
        ->get();

    // If no products are found, send an error
    if ($products->isEmpty()) {
        return response()->json(['error' => 'No products found or not authorized'], 404);
    }

    // Send back the stock info
    return response()->json($products);
}



public function index()
{
    // Fetch all units with all fields
    $units = Unit::all();
    
    // Return as JSON response
    return response()->json($units);
}
public function addUnit(Request $request)
    {
        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:50',
        ]);

        try {
            // Create a new unit
            $unit = Unit::create([
                'name' => $request->name,
            ]);

            // Return success response
            return response()->json([
                'message' => 'Unit created successfully',
                'unit' => $unit,
            ], 201);
        } catch (\Exception $e) {
            // Return error response if an exception occurs
            return response()->json([
                'message' => 'Failed to create unit',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}