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
public function getProductStock($cid)
    {
        // Authentication and validation
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        if ($user->rid < 1 || $user->rid > 5) return response()->json(['message' => 'Forbidden'], 403);
        // Check if the user belongs to the requested company
        if ($user->cid != $cid) {
            return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
        }
        if (!is_numeric($cid) || (int)$cid <= 0) return response()->json(['error' => 'Invalid company ID'], 400);
        $cid = (int)$cid;

        // Precompute purchase totals
        $purchaseTotals = DB::table('purchase_items as pi')
            ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
            ->join('users as u', 'pb.uid', '=', 'u.id')
            ->where('u.cid', $cid)
            ->select('pi.pid as product_id', DB::raw('SUM(pi.quantity) as total'))
            ->groupBy('pi.pid');

        // Precompute sales totals
        $salesTotals = DB::table('sales_items as si')
            ->join('sales_bills as sb', 'si.bid', '=', 'sb.id')
            ->join('users as u', 'sb.uid', '=', 'u.id')
            ->where('u.cid', $cid)
            ->select('si.pid as product_id', DB::raw('SUM(si.quantity) as total'))
            ->groupBy('si.pid');

        // Main query with subquery for unit
        $products = DB::table('products as p')
            ->join('categories as c', 'p.category_id', '=', 'c.id')
            ->leftJoinSub($purchaseTotals, 'pt', function($join) {
                $join->on('p.id', '=', 'pt.product_id');
            })
            ->leftJoinSub($salesTotals, 'st', function($join) {
                $join->on('p.id', '=', 'st.product_id');
            })
            ->where(function($query) {
                $query->whereNotNull('pt.total')
                      ->orWhereNotNull('st.total');
            })
            ->select([
                'p.id',
                'p.name',
                'c.name as category',
                'p.hscode',
                DB::raw("(
                    COALESCE(
                        (SELECT u.name FROM units u JOIN purchase_items pi ON u.id = pi.unit_id WHERE pi.pid = p.id LIMIT 1),
                        (SELECT u.name FROM units u JOIN sales_items si ON u.id = si.unit_id WHERE si.pid = p.id LIMIT 1)
                    )
                ) as unit"),
                'pt.total as purchase_stock',
                'st.total as sales_stock',
                DB::raw('COALESCE(pt.total, 0) - COALESCE(st.total, 0) as current_stock')
            ])
            ->orderByDesc('p.id')
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
            // Check if a unit with the same name already exists
            if (Unit::where('name', $request->name)->exists()) {
                return response()->json([
                    'message' => 'Unit name already exists',
                ], 409); // 409 Conflict status code
            }
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