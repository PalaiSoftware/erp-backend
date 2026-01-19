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
    
// public function getProductStock(Request $request, $cid)
// {
//     // Force JSON response
//     $request->headers->set('Accept', 'application/json');

//     // Authentication
//     $user = Auth::user();
//     if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
//     if ($user->rid < 1 || $user->rid > 5) return response()->json(['message' => 'Forbidden'], 403);
//     if ($user->cid != $cid) {
//         return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
//     }
//     if (!is_numeric($cid) || (int)$cid <= 0) return response()->json(['error' => 'Invalid company ID'], 400);
//     $cid = (int)$cid;

//     // Format number helper
//     function format_number($num) {
//         return number_format($num, 3);
//     }

//     // Format stock in correct units
//     function format_mixed($total, $c_factor, $p_name, $s_name, $s_unit_id) {
//         // Case 1: No secondary unit OR no conversion factor
//         if ($s_unit_id == 0 || $c_factor <= 0) {
//             // Stock is stored in PRIMARY units → display in primary unit
//             return format_number($total) . " " . $p_name;
//         }

//         // Case 2: Has secondary unit and conversion factor
//         $primary = floor($total / $c_factor);
//         $secondary = $total % $c_factor;

//         $str = "";
//         if ($primary > 0) {
//             $str .= format_number($primary) . " " . $p_name;
//         }
//         if ($secondary > 0) {
//             if ($primary > 0) {
//                 $str .= " ";
//             }
//             $str .= format_number($secondary) . " " . $s_name;
//         }
//         return $str ?: "0 " . $p_name;
//     }

//     // 🔥 Main Query: Calculate stock correctly
//     $products = DB::table('products as p')
//         ->join('categories as c', 'p.category_id', '=', 'c.id')
//         ->join('units as pu', 'p.p_unit', '=', 'pu.id')
//         ->leftJoin('units as su', 'p.s_unit', '=', 'su.id')
//         ->join('purchase_items as pi', 'p.id', '=', 'pi.pid')
//         ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
//         ->join('users as u', 'pb.uid', '=', 'u.id')
//         ->where('u.cid', $cid)
//         ->select([
//             'p.id',
//             'p.name',
//             'c.name as category',
//             'p.hscode',
//             'pu.name as unit',
//             // Calculate in CORRECT units
//             DB::raw("COALESCE(SUM(
//                 CASE
//                     WHEN p.s_unit > 0 AND p.c_factor > 0 THEN
//                         CASE
//                             WHEN pi.unit_id = p.p_unit THEN pi.quantity * p.c_factor
//                             ELSE pi.quantity
//                         END
//                     ELSE pi.quantity
//                 END
//             ), 0) as total_purchase_s"),
//             DB::raw("(
//                 SELECT COALESCE(SUM(
//                     CASE
//                         WHEN p.s_unit > 0 AND p.c_factor > 0 THEN
//                             CASE
//                                 WHEN si.unit_id = p.p_unit THEN si.quantity * p.c_factor
//                                 ELSE si.quantity
//                             END
//                         ELSE si.quantity
//                     END
//                 ), 0)
//                 FROM sales_items si
//                 JOIN sales_bills sb ON si.bid = sb.id
//                 JOIN users u2 ON sb.uid = u2.id
//                 WHERE u2.cid = {$cid} AND si.pid = p.id
//             ) as total_sales_s"),
//             'p.c_factor',
//             'pu.name as p_unit_name',
//             'su.name as s_unit_name',
//             'p.s_unit'
//         ])
//         ->groupBy('p.id', 'c.name', 'p.name', 'p.hscode', 'pu.name', 'p.c_factor', 'pu.name', 'su.name', 'p.s_unit')
//         ->orderByDesc('p.id')
//         ->get();

//     // Format response
//     $formatted = $products->map(function ($product) {
//         $total_purchase_s = (float)$product->total_purchase_s;
//         $total_sales_s = (float)$product->total_sales_s;
//         $current_s = $total_purchase_s - $total_sales_s;
//         $c_factor = $product->c_factor;
//         $p_name = $product->p_unit_name;
//         $s_name = $product->s_unit_name ?? 'Unit';
//         $s_unit_id = $product->s_unit;

//         return [
//             'id' => $product->id,
//             'name' => $product->name,
//             'category' => $product->category,
//             'hscode' => $product->hscode,
//             'unit' => $product->unit,
//             'purchase_stock' => format_mixed($total_purchase_s, $c_factor, $p_name, $s_name, $s_unit_id),
//             'sales_stock' => format_mixed($total_sales_s, $c_factor, $p_name, $s_name, $s_unit_id),
//             'current_stock' => format_mixed($current_s, $c_factor, $p_name, $s_name, $s_unit_id),
//         ];
//     })->values();

//     return response()->json($formatted);
// }

public function getProductStock(Request $request, $cid)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Authentication
    $user = Auth::user();
    if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);
    if ($user->rid < 1 || $user->rid > 5) return response()->json(['message' => 'Forbidden'], 403);
    if ($user->cid != $cid) {
        return response()->json(['message' => 'Forbidden: You do not have access to this company\'s data'], 403);
    }
    if (!is_numeric($cid) || (int)$cid <= 0) return response()->json(['error' => 'Invalid company ID'], 400);
    $cid = (int)$cid;

    // Format number helper (closure)
    $format_number = function ($num) {
        // ensure numeric and avoid warnings
        $num = is_numeric($num) ? $num : 0;
        return number_format($num, 3);
    };

    // Format stock in correct units (closure)
    $format_mixed = function ($total, $c_factor, $p_name, $s_name, $s_unit_id) use ($format_number) {
        // normalize values
        $total = is_numeric($total) ? (float)$total : 0.0;
        $c_factor = is_numeric($c_factor) ? (float)$c_factor : 0.0;
        $p_name = $p_name ?: 'Unit';
        $s_name = $s_name ?: 'Unit';
        $s_unit_id = $s_unit_id ?? 0;

        // If there's no secondary unit or conversion factor is missing/zero -> show in primary unit
        if (empty($s_unit_id) || $c_factor == 0.0) {
            return $format_number($total) . " " . $p_name;
        }

        // Calculate primary and secondary amounts (avoid % to handle floats safely)
        $primary = floor($total / $c_factor);
        $secondary = $total - ($primary * $c_factor);
        // small float tolerance
        if (abs($secondary) < 0.0000001) {
            $secondary = 0.0;
        }

        $str = "";
        if ($primary > 0) {
            $str .= $format_number($primary) . " " . $p_name;
        }
        if ($secondary > 0) {
            if ($primary > 0) {
                $str .= " ";
            }
            $str .= $format_number($secondary) . " " . $s_name;
        }

        return $str !== "" ? $str : ("0 " . $p_name);
    };

    // 🔥 Main Query: Calculate stock correctly
    $products = DB::table('products as p')
        ->join('categories as c', 'p.category_id', '=', 'c.id')
        ->join('units as pu', 'p.p_unit', '=', 'pu.id')
        ->leftJoin('units as su', 'p.s_unit', '=', 'su.id')
        ->join('purchase_items as pi', 'p.id', '=', 'pi.pid')
        ->join('purchase_bills as pb', 'pi.bid', '=', 'pb.id')
        ->join('users as u', 'pb.uid', '=', 'u.id')
        ->where('u.cid', $cid)
        ->select([
            'p.id',
            'p.name',
            'c.name as category',
            'p.hscode',
            'pu.name as unit',
            // Calculate in CORRECT units
            DB::raw("COALESCE(SUM(
                CASE
                    WHEN p.s_unit > 0 AND p.c_factor > 0 THEN
                        CASE
                            WHEN pi.unit_id = p.p_unit THEN pi.quantity * p.c_factor
                            ELSE pi.quantity
                        END
                    ELSE pi.quantity
                END
            ), 0) as total_purchase_s"),
            DB::raw("(
                SELECT COALESCE(SUM(
                    CASE
                        WHEN p.s_unit > 0 AND p.c_factor > 0 THEN
                            CASE
                                WHEN si.unit_id = p.p_unit THEN si.quantity * p.c_factor
                                ELSE si.quantity
                            END
                        ELSE si.quantity
                    END
                ), 0)
                FROM sales_items si
                JOIN sales_bills sb ON si.bid = sb.id
                JOIN users u2 ON sb.uid = u2.id
                WHERE u2.cid = {$cid} AND si.pid = p.id
            ) as total_sales_s"),
            'p.c_factor',
            'pu.name as p_unit_name',
            'su.name as s_unit_name',
            'p.s_unit'
        ])
        ->groupBy('p.id', 'c.name', 'p.name', 'p.hscode', 'pu.name', 'p.c_factor', 'pu.name', 'su.name', 'p.s_unit')
        ->orderByDesc('p.id')
        ->get();

    // Format response (use the closures via use)
    $formatted = $products->map(function ($product) use ($format_mixed, $format_number) {
        $total_purchase_s = (float)$product->total_purchase_s;
        $total_sales_s = (float)$product->total_sales_s;
        $current_s = $total_purchase_s - $total_sales_s;
        $c_factor = $product->c_factor;
        $p_name = $product->p_unit_name ?: 'Unit';
        $s_name = $product->s_unit_name ?: 'Unit';
        $s_unit_id = $product->s_unit;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'category' => $product->category,
            'hscode' => $product->hscode,
            'unit' => $product->unit,
            'purchase_stock' => $format_mixed($total_purchase_s, $c_factor, $p_name, $s_name, $s_unit_id),
            'sales_stock' => $format_mixed($total_sales_s, $c_factor, $p_name, $s_name, $s_unit_id),
            'current_stock' => $format_mixed($current_s, $c_factor, $p_name, $s_name, $s_unit_id),
        ];
    })->values();

    return response()->json($formatted);
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
    // Authentication and authorization checks
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }
    if ($user->rid < 1 || $user->rid > 5) {
        return response()->json(['message' => 'Forbidden'], 403);
    }
    // Fetch all units with all fields
    $units = Unit::all();
   //$units = Unit::where('id', '>', 0)->get();
    
    // Return as JSON response
    return response()->json($units);
}
public function addUnit(Request $request)
{
    // Force JSON response
     $request->headers->set('Accept', 'application/json');

    // Auth check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }
     // Role restriction
     if (!in_array($user->rid, [1, 2])) {
        return response()->json(['message' => 'Unauthorized to add unit'], 403);
    }
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
    public function getUnit($unitId)
    {
        // Validate unit ID is a positive integer (0 is NOT allowed)
        if (!is_numeric($unitId) || $unitId <= 0 || floor($unitId) != $unitId) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['unit_id' => ['The unit_id must be a positive integer.']]
            ], 422);
        }
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($user->rid < 1 || $user->rid > 5) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        $unit = DB::table('units')->where('id', $unitId)->first();
        
        if (!$unit) {
            return response()->json(['message' => 'Unit not found'], 404);
        }
        
        return response()->json($unit);
    }
    public function updateUnit(Request $request, $unitId)
    {
        // Validate unit ID is a positive integer (0 is NOT allowed)
        if (!is_numeric($unitId) || $unitId <= 0 || floor($unitId) != $unitId) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => ['unit_id' => ['The unit_id must be a positive integer.']]
            ], 422);
        }
        
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        if ($user->rid < 1 || $user->rid > 5) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        
        // Validate input
        $request->validate([
            'name' => 'required|string|max:50'
        ]);
    
        // Check if unit exists
        $unit = DB::table('units')->where('id', $unitId)->first();
        if (!$unit) {
            return response()->json(['message' => 'Unit not found'], 404);
        }
    
        // Check if new name already exists (excluding current unit)
        $exists = DB::table('units')
            ->where('name', $request->name)
            ->where('id', '!=', $unitId)
            ->exists();
    
        if ($exists) {
            return response()->json([
                'message' => 'Unit name already exists'
            ], 409); // Conflict status code
        }
    
        // Update the unit (without updated_at since it doesn't exist in your table)
        DB::table('units')
            ->where('id', $unitId)
            ->update([
                'name' => $request->name
            ]);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Unit updated successfully'
        ]);
    }
}