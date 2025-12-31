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

        // Case 1: Secondary unit is 0 ("None") → c_factor must be exactly 0 or null (you may enforce presence if you want)
        if ($pUnit > 0 && $sUnit == 0) {
            // Accept null or 0, but reject any positive non-zero value
            if ($cFactor !== null && $cFactor != 0.0) {
                $errors['c_factor'][] = 'When secondary unit is "None", conversion factor must be 0.';
            }
        }
        // Case 2: Secondary unit > 0 → c_factor must be >= 0.1 (and must be present)
        elseif ($pUnit > 0 && $sUnit > 0) {
            if ($cFactor === null) {
                $errors['c_factor'][] = 'Conversion factor is required when a secondary unit is provided.';
            } elseif ($cFactor < 0.1) {
                $errors['c_factor'][] = 'When secondary unit is provided, conversion factor must be at least 0.1.';
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

/**
 * Set selling price for a product based on customer type
 * This is the ONLY place where selling price is set
 * Allowed for: Admin (rid=1) and Superuser (rid=2) only
 */
public function setPriceByCustomerType(Request $request, $productId)
{
    $user = Auth::user();

    // Only Admin and Superuser can set prices
    if (!in_array($user->rid, [1, 2])) {
        return response()->json([
            'message' => 'Unauthorized: Only Admin or Superuser can set product prices'
        ], 403);
    }

    // Validate input
    $validated = $request->validate([
        'customer_type_id' => 'required|integer|exists:customer_types,id,cid,' . $user->cid,
        'selling_price'    => 'required|numeric|min:0',
    ]);

    // Optional: Verify the product belongs to the user's company
    $productExists = \App\Models\Product::where('id', $productId)
        ->where('cid', $user->cid)
        ->exists();

    if (!$productExists) {
        return response()->json([
            'message' => 'Product not found in your company'
        ], 404);
    }

    // Save or update the price for this product + customer type + company
    $price = \App\Models\ProductPriceByType::updateOrCreate(
        [
            'product_id'       => $productId,
            'customer_type_id' => $validated['customer_type_id'],
            'cid'              => $user->cid,
        ],
        [
            'selling_price' => $validated['selling_price'],
        ]
    );

    return response()->json([
        'message' => 'Selling price successfully set for this customer type',
        'product_id' => $productId,
        'customer_type_id' => $validated['customer_type_id'],
        'selling_price' => $price->selling_price,
        'data' => $price
    ], 200);
}

// public function getUnitsByProductId(Request $request, $product_id)
// {
//     $request->headers->set('Accept', 'application/json');

//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthenticated'], 401);
//     }

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
//         // include product_info.unit_id as price_unit
//         $product = Product::where('products.id', $product_id)
//             ->leftJoin('product_info', function ($join) use ($cid) {
//                 $join->on('product_info.pid', '=', 'products.id')
//                      ->where('product_info.cid', '=', $cid);
//             })
//             ->select(
//                 'products.id',
//                 'products.name',
//                 'products.description',
//                 'products.p_unit',
//                 'products.s_unit',
//                 'products.c_factor',
//                 'product_info.purchase_price',
//                 'product_info.pre_gst_sale_cost',
//                 'product_info.profit_percentage',
//                 'product_info.gst',
//                 'product_info.unit_id as price_unit'    // <-- important
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
//                     'product_id' => ['The product ID does not exist in the products table.']
//                 ]
//             ], 422);
//         }

//         // Fetch units
//         $unitIds = array_filter([$product->p_unit, $product->s_unit]);
//         $units = Unit::whereIn('id', $unitIds)
//             ->select('id', 'name')
//             ->get()
//             ->keyBy('id');

//         if ($units->isEmpty()) {
//             Log::warning('Units not found for product', [
//                 'product_id' => $product_id,
//                 'cid' => $cid,
//                 'user_id' => $user->id,
//             ]);
//             return response()->json(['message' => 'Units not found for this product'], 404);
//         }

//         // Normalize values
//         $cFactor = (float) ($product->c_factor ?? 1);
//         if ($cFactor <= 0) {
//             // prevent division by zero; treat as 1 (or handle as you want)
//             $cFactor = 1;
//         }

//         $priceUnit = $product->price_unit ?? null; // unit_id from product_info (the unit in which prices are stored)
//         $infoPurchase = (float) ($product->purchase_price ?? 0.0);
//         $infoSale = (float) ($product->pre_gst_sale_cost ?? 0.0);
//         $infoProfit = (float) ($product->profit_percentage ?? 0.0);
//         $infoGst = (float) ($product->gst ?? 0.0);

//         // Prepare per-unit price arrays (default zero)
//         $primaryUnitPrice = [
//             'purchase_price' => 0.0,
//             'pre_gst_sale_cost' => 0.0,
//             'profit_percentage' => $infoProfit,
//             'gst' => $infoGst,
//         ];
//         $secondaryUnitPrice = $primaryUnitPrice;

//         // If product_info prices are recorded for the primary unit
//         if ($priceUnit && $priceUnit == $product->p_unit) {
//             // price info is per primary unit
//             $primaryUnitPrice['purchase_price'] = $infoPurchase;
//             $primaryUnitPrice['pre_gst_sale_cost'] = $infoSale;

//             // secondary = primary / cFactor (pieces per box etc.)
//             if ($product->s_unit) {
//                 $secondaryUnitPrice['purchase_price'] = $infoPurchase / $cFactor;
//                 $secondaryUnitPrice['pre_gst_sale_cost'] = $infoSale / $cFactor;
//             }
//         }
//         // If product_info prices are recorded for the secondary unit
//         elseif ($priceUnit && $priceUnit == $product->s_unit) {
//             // price info is per secondary unit
//             $secondaryUnitPrice['purchase_price'] = $infoPurchase;
//             $secondaryUnitPrice['pre_gst_sale_cost'] = $infoSale;

//             // primary = secondary * cFactor
//             if ($product->p_unit) {
//                 $primaryUnitPrice['purchase_price'] = $infoPurchase * $cFactor;
//                 $primaryUnitPrice['pre_gst_sale_cost'] = $infoSale * $cFactor;
//             }
//         }
//         // fallback: if price_unit not available, try to use whatever product_info gives as primary
//         else {
//             // assume given info is primary if p_unit exists, otherwise secondary
//             if ($product->p_unit) {
//                 $primaryUnitPrice['purchase_price'] = $infoPurchase;
//                 $primaryUnitPrice['pre_gst_sale_cost'] = $infoSale;
//                 if ($product->s_unit) {
//                     $secondaryUnitPrice['purchase_price'] = $infoPurchase / $cFactor;
//                     $secondaryUnitPrice['pre_gst_sale_cost'] = $infoSale / $cFactor;
//                 }
//             } elseif ($product->s_unit) {
//                 $secondaryUnitPrice['purchase_price'] = $infoPurchase;
//                 $secondaryUnitPrice['pre_gst_sale_cost'] = $infoSale;
//                 if ($product->p_unit) {
//                     $primaryUnitPrice['purchase_price'] = $infoPurchase * $cFactor;
//                     $primaryUnitPrice['pre_gst_sale_cost'] = $infoSale * $cFactor;
//                 }
//             }
//         }

//         // Round values
//         $primaryUnitPrice['purchase_price'] = round($primaryUnitPrice['purchase_price'], 2);
//         $primaryUnitPrice['pre_gst_sale_cost'] = round($primaryUnitPrice['pre_gst_sale_cost'], 2);
//         $secondaryUnitPrice['purchase_price'] = round($secondaryUnitPrice['purchase_price'], 2);
//         $secondaryUnitPrice['pre_gst_sale_cost'] = round($secondaryUnitPrice['pre_gst_sale_cost'], 2);

//         Log::info('Units and product info retrieved successfully', [
//             'product_id' => $product_id,
//             'cid' => $cid,
//             'user_id' => $user->id,
//             'unit_count' => $units->count(),
//             'price_unit' => $priceUnit,
//             'c_factor' => $cFactor,
//         ]);

//         // Build response
//         $unitPricing = [];

//         if ($product->p_unit && $units->has($product->p_unit)) {
//             $unitPricing[] = [
//                 'unit_id' => $product->p_unit,
//                 'unit_name' => $units->get($product->p_unit)->name,
//                 'is_primary' => true,
//                 'purchase_price' => $primaryUnitPrice['purchase_price'],
//                 'sale_price' => $primaryUnitPrice['pre_gst_sale_cost'],
//                 'profit_percentage' => $primaryUnitPrice['profit_percentage'],
//                 'gst' => $primaryUnitPrice['gst'],
//             ];
//         }

//         if ($product->s_unit && $units->has($product->s_unit)) {
//             $unitPricing[] = [
//                 'unit_id' => $product->s_unit,
//                 'unit_name' => $units->get($product->s_unit)->name,
//                 'is_primary' => false,
//                 'purchase_price' => $secondaryUnitPrice['purchase_price'],
//                 'sale_price' => $secondaryUnitPrice['pre_gst_sale_cost'],
//                 'profit_percentage' => $secondaryUnitPrice['profit_percentage'],
//                 'gst' => $secondaryUnitPrice['gst'],
//             ];
//         }

//         return response()->json([
//             'product' => [
//                 'id' => $product->id,
//                 'name' => $product->name,
//                 'description' => $product->description ?? '',
//                 'c_factor' => $product->c_factor ?? 1,
//             ],
//             'unit_pricing' => $unitPricing
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
    $request->headers->set('Accept', 'application/json');

    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthenticated'], 401);
    }

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
        // === REQUIRE customer_id ===
        $request->validate([
            'customer_id' => 'required|integer|exists:sales_clients,id,cid,' . $cid,        ]);

        $customerId = $request->input('customer_id');

        // Fetch customer and their type
        $customer = \App\Models\SalesClient::where('id', $customerId)
            ->where('cid', $cid)
            ->with('customerType')
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'Customer not found in your company'
            ], 404);
        }

        if (!$customer->customer_type_id || !$customer->customerType) {
            return response()->json([
                'message' => 'This customer has no customer type assigned. Please assign a type first.'
            ], 422);
        }

        // === GET TYPE-SPECIFIC SELLING PRICE (ONLY SOURCE) ===
        $typePrice = \App\Models\ProductPriceByType::where('product_id', $product_id)
            ->where('customer_type_id', $customer->customer_type_id)
            ->where('cid', $cid)
            ->first();

        if (!$typePrice) {
            return response()->json([
                'message' => 'No selling price set for customer type: "' . $customer->customerType->name . '". Please set the price first.'
            ], 422);
        }

        $salePrice = (float) $typePrice->selling_price;

        // === FETCH PRODUCT DETAILS (purchase price & GST from product_info) ===
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
                'products.c_factor',
                'product_info.purchase_price',
                'product_info.profit_percentage',
                'product_info.gst',
                'product_info.unit_id as price_unit'
            )
            ->first();

        if (!$product) {
            Log::warning('Product not found', [
                'product_id' => $product_id,
                'cid' => $cid,
                'user_id' => $user->id,
            ]);
            return response()->json([
                'message' => 'Product not found'
            ], 404);
        }

        // Fetch units
        $unitIds = array_filter([$product->p_unit, $product->s_unit]);
        $units = Unit::whereIn('id', $unitIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        if ($units->isEmpty()) {
            return response()->json(['message' => 'Units not found for this product'], 404);
        }

        // Normalize c_factor
        $cFactor = (float) ($product->c_factor ?? 1);
        if ($cFactor <= 0) {
            $cFactor = 1;
        }

        // Use purchase price and GST from product_info
        $infoPurchase = (float) ($product->purchase_price ?? 0.0);
        $infoProfit   = (float) ($product->profit_percentage ?? 0.0);
        $infoGst      = (float) ($product->gst ?? 0.0);
        $priceUnit    = $product->price_unit ?? null;

        // === PRICE CALCULATION USING TYPE-SPECIFIC SALE PRICE ===
        $primaryUnitPrice = [
            'purchase_price'    => 0.0,
            'pre_gst_sale_cost' => 0.0,
            'profit_percentage' => $infoProfit,
            'gst'               => $infoGst,
        ];
        $secondaryUnitPrice = $primaryUnitPrice;

        // Determine which unit the prices are stored in and convert
        if ($priceUnit && $priceUnit == $product->p_unit) {
            // Prices stored per primary unit
            $primaryUnitPrice['purchase_price'] = $infoPurchase;
            $primaryUnitPrice['pre_gst_sale_cost'] = $salePrice;

            if ($product->s_unit) {
                $secondaryUnitPrice['purchase_price'] = $infoPurchase / $cFactor;
                $secondaryUnitPrice['pre_gst_sale_cost'] = $salePrice / $cFactor;
            }
        } elseif ($priceUnit && $priceUnit == $product->s_unit) {
            // Prices stored per secondary unit
            $secondaryUnitPrice['purchase_price'] = $infoPurchase;
            $secondaryUnitPrice['pre_gst_sale_cost'] = $salePrice;

            if ($product->p_unit) {
                $primaryUnitPrice['purchase_price'] = $infoPurchase * $cFactor;
                $primaryUnitPrice['pre_gst_sale_cost'] = $salePrice * $cFactor;
            }
        } else {
            // Fallback: assume primary unit
            $primaryUnitPrice['purchase_price'] = $infoPurchase;
            $primaryUnitPrice['pre_gst_sale_cost'] = $salePrice;

            if ($product->s_unit) {
                $secondaryUnitPrice['purchase_price'] = $infoPurchase / $cFactor;
                $secondaryUnitPrice['pre_gst_sale_cost'] = $salePrice / $cFactor;
            }
        }

        // Round values
        $primaryUnitPrice['purchase_price'] = round($primaryUnitPrice['purchase_price'], 2);
        $primaryUnitPrice['pre_gst_sale_cost'] = round($primaryUnitPrice['pre_gst_sale_cost'], 2);
        $secondaryUnitPrice['purchase_price'] = round($secondaryUnitPrice['purchase_price'], 2);
        $secondaryUnitPrice['pre_gst_sale_cost'] = round($secondaryUnitPrice['pre_gst_sale_cost'], 2);

        // Build unit pricing response
        $unitPricing = [];

        if ($product->p_unit && $units->has($product->p_unit)) {
            $unitPricing[] = [
                'unit_id'           => $product->p_unit,
                'unit_name'         => $units->get($product->p_unit)->name,
                'is_primary'        => true,
                'purchase_price'    => $primaryUnitPrice['purchase_price'],
                'sale_price'        => $primaryUnitPrice['pre_gst_sale_cost'],
                'profit_percentage' => $primaryUnitPrice['profit_percentage'],
                'gst'               => $primaryUnitPrice['gst'],
            ];
        }

        if ($product->s_unit && $units->has($product->s_unit)) {
            $unitPricing[] = [
                'unit_id'           => $product->s_unit,
                'unit_name'         => $units->get($product->s_unit)->name,
                'is_primary'        => false,
                'purchase_price'    => $secondaryUnitPrice['purchase_price'],
                'sale_price'        => $secondaryUnitPrice['pre_gst_sale_cost'],
                'profit_percentage' => $secondaryUnitPrice['profit_percentage'],
                'gst'               => $secondaryUnitPrice['gst'],
            ];
        }

        Log::info('Units and type-specific price retrieved successfully', [
            'product_id'     => $product_id,
            'customer_id'    => $customerId,
            'customer_type'  => $customer->customerType->name,
            'sale_price'     => $salePrice,
            'cid'            => $cid,
            'user_id'        => $user->id,
        ]);

        return response()->json([
            'product' => [
                'id'          => $product->id,
                'name'        => $product->name,
                'description' => $product->description ?? '',
                'c_factor'    => $product->c_factor ?? 1,
            ],
            'unit_pricing' => $unitPricing
        ], 200);

    } catch (\Exception $e) {
        Log::error('Failed to fetch units and price', [
            'product_id' => $product_id,
            'cid'        => $cid,
            'user_id'    => $user->id,
            'error'      => $e->getMessage(),
        ]);
        return response()->json([
            'message' => 'Failed to fetch data',
            'error'   => $e->getMessage(),
        ], 500);
    }
}



}