<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ProductValue; 
use App\Models\Unit;
use Illuminate\Support\Facades\Validator;
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
    if (!in_array($user->rid, [1, 2, 3, 4])) {
        return response()->json(['message' => 'Unauthorized to add product'], 403);
    }

    // Validation (without c_factor conditional rules)
    $validatedData = $request->validate([
        'products' => 'required|array',
        'products.*.name' => [
            'required',
            'string',
            'max:255',
            // function ($attribute, $value, $fail) {
            //     if (Product::whereRaw('LOWER(name) = LOWER(?)', [$value])->exists()) {
            //         $fail($value . ' has already been taken.');
            //     }
            // },
            function ($attribute, $value, $fail) use ($user) { // Pass $user into closure
                // Check for duplicate ONLY within current company (cid)
                if (Product::where('cid', $user->cid) // Add cid condition
                            ->whereRaw('LOWER(name) = LOWER(?)', [$value])
                            ->exists()) {
                    $fail($value . ' already exists in your company.');
                }
            },
        ],
        'products.*.category_id' => 'nullable|integer|exists:categories,id',
        'products.*.hscode' => 'nullable|string|max:255',
        'products.*.description'=> 'nullable|string|max:500',
        'products.*.p_unit' => [
            'required',
            'integer',
            'exists:units,id',
            function ($attribute, $value, $fail) {
                if ($value === 0) {
                    $fail('None is not applicable. Select any other unit.');
                }
            },
        ],
        'products.*.s_unit' => 'nullable|integer|exists:units,id',
        'products.*.c_factor' => 'nullable|numeric', // Simplified rule
    ], [
        'products.*.p_unit.required' => 'Primary unit is required.',
        'products.*.p_unit.integer' => 'Primary unit must be a valid number.',
        'products.*.p_unit.exists' => 'Selected primary unit does not exist.',
    ]);

    // Case-insensitive duplicate check within request
    $names = collect($validatedData['products'])->pluck('name');
    $lowercaseNames = $names->map('strtolower');
    if ($lowercaseNames->duplicates()->isNotEmpty()) {
        return response()->json([
            'message' => "Duplicate product names found in the request (case-insensitive)."
        ], 422);
    }

    // ===== CRITICAL FIX: Manual c_factor validation =====
    $cFactorErrors = [];
    
    foreach ($validatedData['products'] as $index => $product) {
        $pUnit = $product['p_unit'];
        $sUnit = $product['s_unit'] ?? 0; // Handles missing s_unit
        $cFactor = $product['c_factor'] ?? 0; // Handles missing c_factor

        // Skip if p_unit is invalid (shouldn't happen due to prior validation)
        if ($pUnit <= 0) continue;

        // Case 1: Only primary unit (s_unit not provided)
        if ($sUnit === 0) {
            if ($cFactor !== 0) {
                $cFactorErrors["products.{$index}.c_factor"][] = 
                    'When only primary unit is provided and secondary unit is None, c_factor must be 0.';
            }
        } 
        // Case 2: Both units provided
        else {
            if ($cFactor === 0 || $cFactor < 0) {
                $cFactorErrors["products.{$index}.c_factor"][] = 
                    'When both primary and secondary units are provided, c_factor must be at least 0.1.';
            }
        }
    }

    // Return errors if any
    if (!empty($cFactorErrors)) {
        return response()->json([
            'message' => 'Validation failed',
            'errors' => $cFactorErrors
        ], 422);
    }
    // ===== END FIX =====

    // Create products
    $createdProducts = [];
    foreach ($validatedData['products'] as $productData) {
        $product = Product::create([
            'name' => $productData['name'],
            'category_id' => $productData['category_id'] ?? 0,
            'hscode' => $productData['hscode'] ?? null,
            'description' => $productData['description'] ?? null,
            'p_unit' => $productData['p_unit'],
            's_unit' => $productData['s_unit'] ?? 0,
            'c_factor' => $productData['c_factor'] ?? 0,
            'uid' => $user->id,
            'cid' => $user->cid,
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
        ->leftJoin('users', 'products.uid', '=', 'users.id') // JOIN users table
        ->select(
            'products.id',
            'products.name as product_name',
            'products.category_id',
            'categories.name as category_name',
            'products.hscode',
            'products.description',
            'products.p_unit',
            'primary_units.name as primary_unit',
            'products.s_unit',
            'secondary_units.name as secondary_unit',
            'products.c_factor',
            'users.name as created_by'
        )
        ->where('products.cid', $user->cid); // CRITICAL: Filter by current user's company

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
        ->leftJoin('units as primary_units', 'products.p_unit', '=', 'primary_units.id')
        ->leftJoin('units as secondary_units', 'products.s_unit', '=', 'secondary_units.id')
        ->select(
            'products.id as product_id',
            'products.name as product_name',
            'products.category_id',
            'categories.name as category_name',
            'products.hscode',
            'products.description',
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
// public function update(Request $request, $id)
// {
//     try {
//         $user = Auth::user();
//         if (!$user) {
//             return response()->json(['message' => 'Unauthorized'], 401);
//         }
//         if (!in_array($user->rid, [1, 2, 3])) {
//             return response()->json(['message' => 'Forbidden'], 403);
//         }

//         // Validation
//     $validated = $request->validate([
//         'name' => [
//             'required',
//             'string',
//             'max:255',
//             function ($attribute, $value, $fail) use ($id) {
//                 // Case-insensitive database check, ignoring current product
//                 if (Product::whereRaw('LOWER(name) = LOWER(?)', [$value])->where('id', '!=', $id)->exists()) {
//                     $fail($value . ' has already been taken.');
//                 }
//             },
//         ],
//         'category_id' => 'nullable|integer|exists:categories,id',
//         'hscode' => 'nullable|string|max:255',
//         'products.*.p_unit' => [
//             'required',  // ← will trigger if missing
//             'integer',
//             'exists:units,id',
//             function ($attribute, $value, $fail) {
//                 // ← will trigger if value is 0
//                 if ($value === 0) {
//                     $fail('None is not applicable. Select any other unit.');
//                 }
//             },
//         ],
//         'products.*.s_unit' => 'nullable|integer|exists:units,id',
//         'products.*.c_factor' => 'nullable|numeric',
//     ], [
//         // Custom error messages
//         'products.*.p_unit.required' => 'Primary unit is required.',
//         'products.*.p_unit.integer' => 'Primary unit must be a valid number.',
//         'products.*.p_unit.exists' => 'Selected primary unit does not exist.',
//     ]);

//          // CORRECTED c_factor VALIDATION (single product)
//         $pUnit = $validated['p_unit'];
//         $sUnit = $validated['s_unit'] ?? 0; // Default to 0 if not provided
//         $cFactor = $validated['c_factor'] ?? 0; // Default to 0 if not provided

//         $errors = [];

//         // Case 1: Secondary unit is 0 ("None") → c_factor must be 0
//         if ($sUnit == 0) {
//             if ($cFactor != 0) {
//                 $errors['c_factor'][] = 'When secondary unit is "None", conversion factor must be 0.';
//             }
//         }
//         // Case 2: Secondary unit > 0 → c_factor must be ≥1
//         elseif ($sUnit > 0) {
//             if ($cFactor < 1) {
//                 $errors['c_factor'][] = 'When secondary unit is provided, conversion factor must be at least 1.';
//             }
//         }

//         // Return errors if any
//         if (!empty($errors)) {
//             return response()->json([
//                 'message' => 'Validation failed',
//                 'errors' => $errors
//             ], 422);
//         }

//         $product = Product::find($id);
//         if (!$product) {
//             return response()->json(['message' => 'Product not found'], 404);
//         }

//         $product->update($validated);

//         return response()->json([
//             'message' => 'Product updated successfully',
//             'product' => $product
//         ], 200);
//     } catch (\Exception $e) {
//         \Log::error($e->getMessage());
//         return response()->json([ 'error' => $e->getMessage()], 500);
//     }
// }
// public function update(Request $request, $id)
// {
//     try {
//         $user = Auth::user();
//         if (!$user) {
//             return response()->json(['message' => 'Unauthorized'], 401);
//         }
//         if (!in_array($user->rid, [1, 2, 3])) {
//             return response()->json(['message' => 'Forbidden'], 403);
//         }

//         $product = Product::find($id);
//         if (!$product) {
//             return response()->json(['message' => 'Product not found'], 404);
//         }

//         // ===== FIX: Corrected validation rules (top-level fields) =====
//         $validated = $request->validate([
//             'name' => [
//                 'required',
//                 'string',
//                 'max:255',
//                 function ($attribute, $value, $fail) use ($id) {
//                     if (Product::whereRaw('LOWER(name) = LOWER(?)', [$value])
//                         ->where('id', '!=', $id)
//                         ->exists()) {
//                         $fail($value . ' has already been taken.');
//                     }
//                 },
//             ],
//             'category_id' => 'nullable|integer|exists:categories,id',
//             'hscode' => 'nullable|string|max:255',
//             'p_unit' => [
//                 'required',
//                 'integer',
//                 'exists:units,id',
//                 function ($attribute, $value, $fail) {
//                     if ($value === 0) {
//                         $fail('None is not applicable. Select any other unit.');
//                     }
//                 },
//             ],
//             's_unit' => 'nullable|integer|exists:units,id',
//             'c_factor' => 'nullable|numeric',
//         ], [
//             'p_unit.required' => 'Primary unit is required.',
//             'p_unit.integer' => 'Primary unit must be a valid number.',
//             'p_unit.exists' => 'Selected primary unit does not exist.',
//         ]);

//          // ===== CRITICAL FIX: Manual c_factor validation =====
//     $cFactorErrors = [];
    
//     foreach ($validatedData['products'] as $index => $product) {
//         $pUnit = $product['p_unit'];
//         $sUnit = $product['s_unit'] ?? 0; // Handles missing s_unit
//         $cFactor = $product['c_factor'] ?? 0; // Handles missing c_factor

//         // Skip if p_unit is invalid (shouldn't happen due to prior validation)
//         if ($pUnit <= 0) continue;

//         // Case 1: Only primary unit (s_unit not provided)
//         if ($sUnit === 0) {
//             if ($cFactor !== 0) {
//                 $cFactorErrors["products.{$index}.c_factor"][] = 
//                     'When only primary unit is provided and secondary unit is None, c_factor must be 0.';
//             }
//         } 
//         // Case 2: Both units provided
//         else {
//             if ($cFactor === 0 || $cFactor < 1) {
//                 $cFactorErrors["products.{$index}.c_factor"][] = 
//                     'When both primary and secondary units are provided, c_factor must be at least 1.';
//             }
//         }
//     }

//     // Return errors if any
//     if (!empty($cFactorErrors)) {
//         return response()->json([
//             'message' => 'Validation failed',
//             'errors' => $cFactorErrors
//         ], 422);
//     }
//     // ===== END FIX =====

//         $product->update($validated);

//         return response()->json([
//             'message' => 'Product updated successfully',
//             'product' => $product->refresh()
//         ], 200);
//     } catch (\Exception $e) {
//         \Log::error($e->getMessage());
//         return response()->json(['error' => $e->getMessage()], 500);
//     }
// }

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

        // Find product and verify it belongs to current company
        $product = Product::where('id', $id)
            ->where('cid', $user->cid) // CRITICAL: Only allow products from current company
            ->first();

        if (!$product) {
            return response()->json(['message' => 'Product not found or not authorized'], 404);
        }
        // CORRECTED VALIDATION (top-level fields, NOT array-based)
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($id, $user) {
                    // Check duplicate ONLY within current company
                    if (Product::whereRaw('LOWER(name) = LOWER(?)', [$value])
                        ->where('id', '!=', $id)
                        ->where('cid', $user->cid) // Company-specific filter
                        ->exists()) {
                        $fail('This product name is already in use in your company.');
                    }
                },
            ],
            'category_id' => 'nullable|integer|exists:categories,id',
            'hscode' => 'nullable|string|max:255',
            'description'=> 'nullable|string|max:500',
            'p_unit' => [
                'required',
                'integer',
                'exists:units,id',
                function ($attribute, $value, $fail) {
                    if ($value === 0) {
                        $fail('Primary unit cannot be "None". Please select a valid unit.');
                    }
                },
            ],
            's_unit' => 'nullable|integer|exists:units,id', // Allow 0 as valid "None"
            'c_factor' => 'nullable|numeric',
        ], [
            'p_unit.required' => 'Primary unit is required.',
            'p_unit.integer' => 'Primary unit must be a valid number.',
            'p_unit.exists' => 'Selected primary unit does not exist.',
        ]);

        // CORRECTED c_factor VALIDATION (single product)
        $pUnit = $validated['p_unit'];
        $sUnit = $validated['s_unit'] ?? 0; // Default to 0 if not provided
        $cFactor = $validated['c_factor'] ?? 0; // Default to 0 if not provided

        $errors = [];

        // Case 1: Secondary unit is 0 ("None") → c_factor must be 0
        if ($pUnit>0 && $sUnit == 0) {
            if ($cFactor != 0) {
                $errors['c_factor'][] = 'When secondary unit is "None", conversion factor must be 0.';
            }
        }
        // Case 2: Secondary unit > 0 → c_factor must be ≥1
        elseif ($pUnit>0 && $sUnit >0) {
            if ($cFactor < 0) {
                $errors['c_factor'][] = 'When secondary unit is provided, conversion factor must be at least 0.1 .';
            }
        }

        // Return errors if any
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $errors
            ], 422);
        }

        $product->update($validated);

        return response()->json([
            'message' => 'Product updated successfully',
            'product' => $product->refresh()
        ], 200);
    } catch (\Exception $e) {
        \Log::error($e->getMessage());
        return response()->json(['error' => $e->getMessage()], 500);
    }
}
// public function getUnitsByProductId(Request $request, $product_id)
// {
//     // Force JSON response
//     $request->headers->set('Accept', 'application/json');

//     // Authentication and authorization checks
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthenticated'], 401);
//     }
//     // if ($user->rid < 1 || $user->rid > 5) {
//     //     return response()->json(['message' => 'Forbidden'], 403);
//     // }

//     // Validate product_id from route parameter
//     if (!is_numeric($product_id) || intval($product_id) <= 0) {
//         return response()->json([
//             'message' => 'The given data was invalid.',
//             'errors' => [
//                 'product_id' => ['The product ID must be a positive integer.']
//             ]
//         ], 422);
//     }

//     // Fetch product with p_unit and s_unit
//     $product = Product::where('products.id', $product_id)
//         ->select('products.id', 'products.p_unit', 'products.s_unit')
//         ->first();

//     if (!$product) {
//         return response()->json([
//             'message' => 'The given data was invalid.',
//             'errors' => [
//                 'product_id' => ['The product ID must exist in the products table.']
//             ]
//         ], 422);
//     }

//     // Fetch unit details for p_unit and s_unit
//     $units = Unit::whereIn('id', array_filter([$product->p_unit, $product->s_unit]))
//         ->select('id', 'name')
//         ->get()
//         ->map(function ($unit) {
//             return [
//                 'id' => $unit->id,
//                 'name' => $unit->name,
//             ];
//         });

//     if ($units->isEmpty()) {
//         return response()->json(['message' => 'Units not found for this product'], 404);
//     }

//     return response()->json($units, 200);
// }

// public function getUnitsByProductId(Request $request, $product_id)
// {
//     // Force JSON response
//     $request->headers->set('Accept', 'application/json');

//     // Authentication check
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthenticated'], 401);
//     }

//     // Validate product_id from route parameter
//     if (!is_numeric($product_id) || intval($product_id) <= 0) {
//         return response()->json([
//             'message' => 'The given data was invalid.',
//             'errors' => [
//                 'product_id' => ['The product ID must be a positive integer.']
//             ]
//         ], 422);
//     }

//     $cid = $user->cid;
//     $product_id = (int) $product_id;

//     try {
//         // Fetch product with p_unit, s_unit, and product_info data
//         $product = Product::where('products.id', $product_id)
//             ->leftJoin('product_info', function ($join) use ($cid) {
//                 $join->on('product_info.pid', '=', 'products.id')
//                      ->where('product_info.cid', '=', $cid);
//             })
//             ->select(
//                 'products.id',
//                 'products.p_unit',
//                 'products.s_unit',
//                 'product_info.purchase_price',
//                 'product_info.post_gst_sale_cost'
//             )
//             ->first();

//         if (!$product) {
//             Log::warning('Product not found', [
//                 'product_id' => $product_id,
//                 'cid' => $cid,
//                 'user_id' => $user->id,
//             ]);
//             return response()->json([
//                 'message' => 'The given data was invalid.',
//                 'errors' => [
//                     'product_id' => ['The product ID not exist in the products table.']
//                 ]
//             ], 422);
//         }

//         // Fetch unit details for p_unit and s_unit
//         $units = Unit::whereIn('id', array_filter([$product->p_unit, $product->s_unit]))
//             ->select('id', 'name')
//             ->get()
//             ->map(function ($unit) {
//                 return [
//                     'id' => $unit->id,
//                     'name' => $unit->name,
//                 ];
//             });

//         if ($units->isEmpty()) {
//             Log::warning('Units not found for product', [
//                 'product_id' => $product_id,
//                 'cid' => $cid,
//                 'user_id' => $user->id,
//             ]);
//             return response()->json(['message' => 'Units not found for this product'], 404);
//         }

//         Log::info('Units and product info retrieved successfully', [
//             'product_id' => $product_id,
//             'cid' => $cid,
//             'user_id' => $user->id,
//             'unit_count' => $units->count(),
//         ]);

//         // Prepare response
//         return response()->json([
//             'units' => $units,
//             'product_info' => [
//                 'purchase_price' => $product->purchase_price ?? 0.00,
//                 'post_gst_sale_cost' => $product->post_gst_sale_cost ?? 0.00,
//             ]
//         ], 200);
//     } catch (\Exception $e) {
//         Log::error('Failed to fetch units and product info', [
//             'product_id' => $product_id,
//             'cid' => $cid,
//             'user_id' => $user->id,
//             'error' => $e->getMessage(),
//             'trace' => $e->getTraceAsString(),
//         ]);
//         return response()->json([
//             'message' => 'Failed to fetch data',
//             'error' => $e->getMessage(),
//         ], 500);
//     }
// }

public function getUnitsByProductId(Request $request, $product_id)
{
    // Force JSON response
    $request->headers->set('Accept', 'application/json');

    // Authentication check
    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

    // Validate product_id
    if (!is_numeric($product_id) || intval($product_id) <= 0) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => [
                'product_id' => ['The product ID must be a positive integer.']
            ]
        ], 422);
    }

    $cid = $user->cid;
    $product_id = (int) $product_id;

    try {
        // Fetch product with description, p_unit, s_unit, and product_info data
        $product = Product::where('products.id', $product_id)
            ->leftJoin('product_info', function ($join) use ($cid) {
                $join->on('product_info.pid', '=', 'products.id')
                     ->where('product_info.cid', '=', $cid);
            })
            ->select(
                'products.id',
                'products.name',
                'products.description',
                'products.p_unit',
                'products.s_unit',
                'product_info.purchase_price',
                'product_info.post_gst_sale_cost',
                'product_info.profit_percentage',
                'product_info.gst'

            )
            ->first();

        if (!$product) {
            Log::warning('Product not found', [
                'product_id' => $product_id,
                'cid' => $cid,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'product_id' => ['The product ID does not exist in the products table.']
                ]
            ], 422);
        }

        // Fetch units
        $units = Unit::whereIn('id', array_filter([$product->p_unit, $product->s_unit]))
            ->select('id', 'name')
            ->get()
            ->map(fn($unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
            ]);

        if ($units->isEmpty()) {
            Log::warning('Units not found for product', [
                'product_id' => $product_id,
                'cid' => $cid,
                'user_id' => $user->id,
            ]);
            return response()->json(['message' => 'Units not found for this product'], 404);
        }

        Log::info('Units and product info retrieved successfully', [
            'product_id' => $product_id,
            'cid' => $cid,
            'user_id' => $user->id,
            'unit_count' => $units->count(),
        ]);

        // Prepare response with description included
        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description ?? '',
            ],
            'units' => $units,
            'product_info' => [
                'purchase_price' => $product->purchase_price ?? 0.00,
                'post_gst_sale_cost' => $product->post_gst_sale_cost ?? 0.00,
                'profit_percentage'=>$product->profit_percentage ?? 0.00,
                'gst' =>$product->gst ?? 0.00,
            ]
        ], 200);

    } catch (\Exception $e) {
        Log::error('Failed to fetch units and product info', [
            'product_id' => $product_id,
            'cid' => $cid,
            'user_id' => $user->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        return response()->json([
            'message' => 'Failed to fetch data',
            'error' => $e->getMessage(),
        ], 500);
    }
}

}