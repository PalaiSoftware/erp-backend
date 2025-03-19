<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SalesItem;
use App\Models\TransactionSales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Company;
use App\Models\Product;
use App\Models\Customer;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
class SalesController extends Controller
{

    // public function store(Request $request)
    // {
    //     Log::info('API endpoint reached', ['request' => $request->all()]);
    
    //     $user = Auth::user();
    //     if (!$user) {
    //         Log::warning('User not authenticated');
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }
    
    //     try {
    //         $request->validate([
    //             'products' => 'required|array',
    //             'products.*.product_id' => 'required|integer|exists:products,id',
    //             'products.*.quantity' => 'required|integer|min:1',
    //             'products.*.discount' => 'nullable|numeric|min:0',
    //             'products.*.per_item_cost' => 'required|numeric|min:0',
    //             'products.*.unit_id' => 'required|integer|exists:units,id', // Add unit_id validation
    //             'cid' => 'required|integer',
    //             'customer_id' => 'required|integer',
    //             'payment_mode' => 'required|string|max:50',
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         Log::error('Validation failed', ['errors' => $e->errors()]);
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);
    //     }
    
    //     DB::beginTransaction();
    //     try {
    //         $transaction = TransactionSales::create([
    //             'uid' => $user->id,
    //             'cid' => $request->cid,
    //             'customer_id' => $request->customer_id,
    //             'payment_mode' => $request->payment_mode,
    //             'created_at' => now(),
    //         ]);
    //         $transactionId = $transaction->id;
    //         Log::info('Created transaction', ['transaction_id' => $transactionId]);
    
    //         foreach ($request->products as $product) {
    //             $sale = Sale::create([
    //                 'transaction_id' => $transactionId,
    //                 'product_id' => $product['product_id'],
    //             ]);
    //             $saleId = $sale->id;
    //             Log::info('Created sale', ['sale_id' => $saleId]);
    
    //             SalesItem::create([
    //                 'sale_id' => $saleId,
    //                 'quantity' => $product['quantity'],
    //                 'discount' => $product['discount'] ?? 0,
    //                 'per_item_cost' => $product['per_item_cost'],
    //                 'unit_id' => $product['unit_id'], // Add unit_id
    //             ]);
    //         }
    
    //         DB::commit();
    //         Log::info('Sale recorded successfully', ['transaction_id' => $transactionId]);
    
    //         return response()->json([
    //             'message' => 'Sale recorded successfully',
    //             'transaction_id' => $transactionId,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Sale failed', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'message' => 'Sale failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    // public function store(Request $request)
    // {
    //     Log::info('API endpoint reached', ['request' => $request->all()]);
    
    //     $user = Auth::user();
    //     if (!$user) {
    //         Log::warning('User not authenticated');
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }
    
    //     try {
    //         $request->validate([
    //             'products' => 'required|array',
    //             'products.*.product_id' => 'required|integer|exists:products,id',
    //             'products.*.quantity' => 'required|integer|min:1',
    //             'products.*.discount' => 'nullable|numeric|min:0',
    //             'products.*.per_item_cost' => 'required|numeric|min:0',
    //             'products.*.unit_id' => 'required|integer|exists:units,id',
    //             'cid' => 'required|integer',
    //             'customer_id' => 'required|integer',
    //             'payment_mode' => 'required|string|max:50',
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         Log::error('Validation failed', ['errors' => $e->errors()]);
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);
    //     }
    
    //     $uid = $user->id;
    //     $cid = (int)$request->cid;
    
    //     // Extract product IDs from the request
    //     $productIds = array_column($request->products, 'product_id');
    
    //     // Fetch current stock for all products in one query
    //     $stocks = DB::table('products as p')
    //         ->whereIn('p.id', $productIds)
    //         ->where('p.uid', $uid)
    //         ->select([
    //             'p.id',
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
    //         ->get()
    //         ->keyBy('id'); // Key by product ID for efficient lookup
    
    //     // Check stock availability for each product
    //     $errors = [];
    //     foreach ($request->products as $product) {
    //         $productId = $product['product_id'];
    //         $requestedQuantity = $product['quantity'];
    
    //         if (!isset($stocks[$productId])) {
    //             $errors[] = "Product ID $productId not found or not authorized.";
    //             continue;
    //         }
    
    //         $currentStock = $stocks[$productId]->current_stock;
    //         if ($requestedQuantity > $currentStock) {
    //             $errors[] = "Insufficient stock for product ID $productId. Available: $currentStock, Requested: $requestedQuantity. Please enter a valid quantity.";
    //         }
    //     }
    
    //     // If there are any stock issues, return an error response
    //     if (!empty($errors)) {
    //         return response()->json([
    //             'message' => 'Stock check failed',
    //             'errors' => $errors
    //         ], 422);
    //     }
    
    //     // Proceed with the transaction if all stock checks pass
    //     DB::beginTransaction();
    //     try {
    //         $transaction = TransactionSales::create([
    //             'uid' => $user->id,
    //             'cid' => $request->cid,
    //             'customer_id' => $request->customer_id,
    //             'payment_mode' => $request->payment_mode,
    //             'created_at' => now(),
    //         ]);
    //         $transactionId = $transaction->id;
    //         Log::info('Created transaction', ['transaction_id' => $transactionId]);
    
    //         foreach ($request->products as $product) {
    //             $sale = Sale::create([
    //                 'transaction_id' => $transactionId,
    //                 'product_id' => $product['product_id'],
    //             ]);
    //             $saleId = $sale->id;
    //             Log::info('Created sale', ['sale_id' => $saleId]);
    
    //             SalesItem::create([
    //                 'sale_id' => $saleId,
    //                 'quantity' => $product['quantity'],
    //                 'discount' => $product['discount'] ?? 0,
    //                 'per_item_cost' => $product['per_item_cost'],
    //                 'unit_id' => $product['unit_id'],
    //             ]);
    //         }
    
    //         DB::commit();
    //         Log::info('Sale recorded successfully', ['transaction_id' => $transactionId]);
    
    //         return response()->json([
    //             'message' => 'Sale recorded successfully',
    //             'transaction_id' => $transactionId,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Sale failed', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'message' => 'Sale failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    // public function store(Request $request)
    // {
    //     Log::info('API endpoint reached', ['request' => $request->all()]);
    
    //     // Check if the user is authenticated
    //     $user = Auth::user();
    //     if (!$user) {
    //         Log::warning('User not authenticated');
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }
    
    //     // Validate the incoming request data
    //     try {
    //         $request->validate([
    //             'products' => 'required|array',
    //             'products.*.product_id' => 'required|integer|exists:products,id',
    //             'products.*.quantity' => 'required|integer|min:1',
    //             'products.*.discount' => 'nullable|numeric|min:0',
    //             'products.*.per_item_cost' => 'required|numeric|min:0',
    //             'products.*.unit_id' => 'required|integer|exists:units,id',
    //             'cid' => 'required|integer',
    //             'customer_id' => 'required|integer',
    //             'payment_mode' => 'required|string|max:50',
    //         ]);
    //     } catch (\Illuminate\Validation\ValidationException $e) {
    //         Log::error('Validation failed', ['errors' => $e->errors()]);
    //         return response()->json([
    //             'message' => 'Validation failed',
    //             'errors' => $e->errors()
    //         ], 422);
    //     }
    
    //     $uid = $user->id;
    //     $cid = (int)$request->cid;
    
    //     // Extract product IDs from the request
    //     $productIds = array_column($request->products, 'product_id');
    
    //     // Fetch current stock for all products in one query
    //     $stocks = DB::table('products as p')
    //         ->whereIn('p.id', $productIds)
    //         ->where('p.uid', $uid)
    //         ->select([
    //             'p.id',
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
    //         ->get()
    //         ->keyBy('id'); // Key by product ID for efficient lookup
    
    //     // Check stock availability for each product
    //     $errors = [];
    //     foreach ($request->products as $product) {
    //         $productId = $product['product_id'];
    //         $requestedQuantity = $product['quantity'];
    
    //         // Check if the product exists in the stock data
    //         if (!isset($stocks[$productId])) {
    //             $errors[] = "Product ID $productId not found or not authorized.";
    //             continue;
    //         }
    
    //         $currentStock = $stocks[$productId]->current_stock;
    //         // Validate requested quantity against current stock
    //         if ($requestedQuantity > $currentStock) {
    //             $errors[] = "Insufficient stock for product ID $productId. Available: $currentStock, Requested: $requestedQuantity. Please enter a valid quantity.";
    //         }
    //     }
    
    //     // Return error response if stock check fails
    //     if (!empty($errors)) {
    //         return response()->json([
    //             'message' => 'Stock check failed',
    //             'errors' => $errors
    //         ], 422);
    //     }
    
    //     // Proceed with the transaction if stock checks pass
    //     DB::beginTransaction();
    //     try {
    //         // Create the sales transaction
    //         $transaction = TransactionSales::create([
    //             'uid' => $user->id,
    //             'cid' => $request->cid,
    //             'customer_id' => $request->customer_id,
    //             'payment_mode' => $request->payment_mode,
    //             'created_at' => now(),
    //         ]);
    //         $transactionId = $transaction->id;
    //         Log::info('Created transaction', ['transaction_id' => $transactionId]);
    
    //         // Create sales records for each product
    //         foreach ($request->products as $product) {
    //             $sale = Sale::create([
    //                 'transaction_id' => $transactionId,
    //                 'product_id' => $product['product_id'],
    //             ]);
    //             $saleId = $sale->id;
    //             Log::info('Created sale', ['sale_id' => $saleId]);
    
    //             SalesItem::create([
    //                 'sale_id' => $saleId,
    //                 'quantity' => $product['quantity'],
    //                 'discount' => $product['discount'] ?? 0,
    //                 'per_item_cost' => $product['per_item_cost'],
    //                 'unit_id' => $product['unit_id'],
    //             ]);
    //         }
    
    //         // Commit the transaction
    //         DB::commit();
    //         Log::info('Sale recorded successfully', ['transaction_id' => $transactionId]);
    
    //         return response()->json([
    //             'message' => 'Sale recorded successfully',
    //             'transaction_id' => $transactionId,
    //         ], 201);
    //     } catch (\Exception $e) {
    //         // Roll back the transaction on failure
    //         DB::rollBack();
    //         Log::error('Sale failed', ['error' => $e->getMessage()]);
    //         return response()->json([
    //             'message' => 'Sale failed',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function store(Request $request)
    {
        Log::info('API endpoint reached', ['request' => $request->all()]);
    
        $user = Auth::user();
        if (!$user) {
            Log::warning('User not authenticated');
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        try {
            $request->validate([
                'products' => 'required|array',
                'products.*.product_id' => 'required|integer|exists:products,id',
                'products.*.quantity' => 'required|integer|min:1',
                'products.*.discount' => 'nullable|numeric|min:0',
                'products.*.per_item_cost' => 'required|numeric|min:0',
                'products.*.unit_id' => 'required|integer|exists:units,id',
                'cid' => 'required|integer',
                'customer_id' => 'required|integer',
                'payment_mode' => 'required|string|max:50',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        }
    
        $uid = $user->id;
        $cid = (int)$request->cid;
    
        // Extract product IDs from the request
        $productIds = array_column($request->products, 'product_id');
    
        // Fetch product details and current stock in one query
        $stocks = DB::table('products as p')
            ->whereIn('p.id', $productIds)
            // ->where('p.uid', $uid)
            ->select([
                'p.id',
                'p.name',
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
            ->get()
            ->keyBy('id'); // Key by product ID for efficient lookup
    
        // Check stock availability for each product
        $errors = [];
        foreach ($request->products as $product) {
            $productId = $product['product_id'];
            $requestedQuantity = $product['quantity'];
    
            if (!isset($stocks[$productId])) {
                $errors[] = [
                    'product_id' => $productId,
                    'product_name' => 'Unknown',
                    'current_stock' => 0,
                    'requested_stock' => $requestedQuantity
                ];
                continue;
            }
    
            $stock = $stocks[$productId];
            $currentStock = $stock->current_stock;
    
            if ($requestedQuantity > $currentStock) {
                $errors[] = [
                    'product_id' => $productId,
                    'product_name' => $stock->name,
                    'current_stock' => $currentStock,
                    'requested_stock' => $requestedQuantity
                ];
            }
        }
    
        // Return error response if stock check fails
        if (!empty($errors)) {
            return response()->json([
                'message' => 'Stock check failed',
                'errors' => $errors
            ], 422);
        }
    
        // Proceed with the transaction if all stock checks pass
        DB::beginTransaction();
        try {
            $transaction = TransactionSales::create([
                'uid' => $user->id,
                'cid' => $request->cid,
                'customer_id' => $request->customer_id,
                'payment_mode' => $request->payment_mode,
                'created_at' => now(),
            ]);
            $transactionId = $transaction->id;
            Log::info('Created transaction', ['transaction_id' => $transactionId]);
    
            foreach ($request->products as $product) {
                $sale = Sale::create([
                    'transaction_id' => $transactionId,
                    'product_id' => $product['product_id'],
                ]);
                $saleId = $sale->id;
                Log::info('Created sale', ['sale_id' => $saleId]);
    
                SalesItem::create([
                    'sale_id' => $saleId,
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
                    'unit_id' => $product['unit_id'],
                ]);
            }
    
            DB::commit();
            Log::info('Sale recorded successfully', ['transaction_id' => $transactionId]);
    
            return response()->json([
                'message' => 'Sale recorded successfully',
                'transaction_id' => $transactionId,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sale failed', ['error' => $e->getMessage()]);
            return response()->json([
                'message' => 'Sale failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    public function generateInvoice($transactionId)
{
    Log::info("Generating invoice for transaction_id: {$transactionId}");

    $user = Auth::user();
    if (!$user) {
        return response()->json(['message' => 'Unauthorized'], 401);
    }

    try {
        $transaction = TransactionSales::findOrFail($transactionId);
        $sales = Sale::where('transaction_id', $transactionId)->with('salesItem.unit')->get();
        if ($sales->isEmpty()) {
            Log::warning("No sales records found for transaction_id: {$transactionId}");
            return response()->json(['message' => 'No sales records found for this transaction'], 404);
        }

        $customer = Customer::find($transaction->customer_id);
        $company = Company::find($transaction->cid);
        $userDetails = User::find($transaction->uid);

        $invoice = [
            'number' => 'INV-' . $transactionId,
            'date' => Carbon::parse($transaction->created_at)->format('Y-m-d'),
        ];

        $items = [];
        $totalAmount = 0;
        foreach ($sales as $sale) {
            $product = Product::find($sale->product_id);
            $salesItem = $sale->salesItem;

            if ($salesItem) {
                $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
                $items[] = [
                    'product_name' => $product ? $product->name : 'Unknown Product',
                    'quantity' => $salesItem->quantity,
                    'unit' => $salesItem->unit ? $salesItem->unit->name : 'N/A', // Add unit name
                    'per_item_cost' => $salesItem->per_item_cost,
                    'discount' => $salesItem->discount,
                    'total' => $itemTotal,
                ];
                $totalAmount += $itemTotal;
            }
        }
        Log::info('Invoice items prepared', ['items' => $items, 'total_amount' => $totalAmount]);

        $data = [
            'invoice' => (object) $invoice,
            'transaction' => $transaction,
            'items' => $items,
            'total_amount' => $totalAmount,
            'company' => $company,
            'customer' => $customer,
            'userDetails' => $userDetails,
        ];

        $pdf = Pdf::loadView('invoices.invoice', $data);
        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"invoice_{$transactionId}.pdf\"");
    } catch (\Exception $e) {
        Log::error('Invoice generation failed', [
            'transaction_id' => $transactionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => 'Error generating invoice', 'error' => $e->getMessage()], 500);
    }
}
}