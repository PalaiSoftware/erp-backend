<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SalesItem;
use App\Models\TransactionSales;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Fixed this import
use App\Models\Company; // Assuming this exists
use App\Models\Product; // For product names
// use Barryvdh\DomPDF\Facade as PDF;
use Barryvdh\DomPDF\Facade\Pdf;


class SalesController extends Controller
{
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

        DB::beginTransaction();
        try {
            // Get the next sale_id from the sequence
            $saleId = DB::selectOne('SELECT nextval(\'sale_id_seq\')')->nextval;
            Log::info('Generated sale_id', ['sale_id' => $saleId]);

            // Step 1: Create Sale records for each product with the same sale_id
            foreach ($request->products as $product) {
                Sale::create([
                    'sale_id' => $saleId,
                    'product_id' => $product['product_id'],
                ]);
            }

            // Step 2: Add all products as SalesItems with the same sale_id
            foreach ($request->products as $product) {
                SalesItem::create([
                    'sale_id' => $saleId,
                    'product_id' => $product['product_id'],
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
                ]);
            }
            // for transaction_sales table new added
               // Step 3: Calculate total amount separately
               $totalAmount = 0;
            foreach ($request->products as $product) {
               $quantity = $product['quantity'];
               $perItemCost = $product['per_item_cost'];
               $discount = $product['discount'] ?? 0;

               // Calculate item total after discount
               $itemTotal = ($quantity * $perItemCost) - (($quantity * $perItemCost) * ($discount / 100.0));
               $totalAmount += $itemTotal;
           }
               // Step 4: Insert transaction data into transactions table
         $transaction = TransactionSales::create([
                    'sale_id'      => $saleId,
                    'uid'          => $user->id,
                    'cid'          => $request->cid, // Now taking cid from the request
                    'customer_id'  => $request->customer_id,
                    'payment_mode' => $request->payment_mode,
                    'total_amount' => $totalAmount,
         ]);

            DB::commit();
            Log::info('Sale and transaction recorded successfully', ['sale_id' => $saleId]);

        return response()->json([
            'message' => 'Sale recorded successfully',
            'sale_id' => $saleId,
            'transaction' => $transaction
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



//     public function generateInvoice($saleId)
// {
//     // Check if user is logged in
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     // Fetch the data
//     $transaction = TransactionSales::where('sale_id', $saleId)->firstOrFail();
//     $salesItems = SalesItem::where('sale_id', $saleId)->get();
//     $products = Product::whereIn('id', $salesItems->pluck('product_id'))->get()->keyBy('id');
//     $company = Company::find($transaction->cid);
//     // $customer = Customer::find($transaction->customer_id);

//     // Prepare data for the invoice
//     $data = [
//         'transaction' => $transaction,
//         'salesItems' => $salesItems,
//         'products' => $products,
//         'company' => $company,
//         // 'customer' => $customer,
//         'invoiceNumber' => 'INV-' . $saleId, // Simple format, tweak as needed
//         'invoiceDate' => now()->format('Y-m-d'),
//     ];

//     // Generate the PDF
//     // $pdf = PDF::loadView('invoices.invoice', $data);

//     // // Send it back
//     // return response($pdf->output())
//     //     ->header('Content-Type', 'application/pdf')
//     //     ->header('Content-Disposition', 'inline; filename="invoice_' . $saleId . '.pdf"');
//     $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.invoice', $data);
//     return $pdf->download('invoice.pdf');
// }

// public function generateInvoice($saleId)
// {
//     // Check if user is logged in
//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     // Fetch the data
//     $transaction = TransactionSales::where('sale_id', $saleId)->firstOrFail();
//     $salesItems = SalesItem::where('sale_id', $saleId)->get();
//     $products = Product::whereIn('id', $salesItems->pluck('product_id'))->get()->keyBy('id');
//     $company = Company::find($transaction->cid);
//     // $customer = Customer::find($transaction->customer_id);

//     // Prepare invoice data
//     $invoice = [
//         'number' => 'INV-' . $saleId,
//         'date' => now()->format('Y-m-d'),
//     ];

//     // Prepare data for the view
//     $data = [
//         'invoice' => (object) $invoice,
//         'transaction' => $transaction,
//         'salesItems' => $salesItems,
//         'products' => $products,
//         'company' => $company,
//         // 'customer' => $customer,
//     ];

//     // Generate the PDF
//     $pdf = Pdf::loadView('invoices.invoice', $data);

//     // Send it back
//     return response($pdf->output())
//         ->header('Content-Type', 'application/pdf')
//         ->header('Content-Disposition', 'inline; filename="invoice_' . $saleId . '.pdf"');
// }
// public function generateInvoice($saleId)
// {
//     Log::info("Generating invoice for sale_id: {$saleId}");

//     $user = Auth::user();
//     if (!$user) {
//         return response()->json(['message' => 'Unauthorized'], 401);
//     }

//     $transaction = TransactionSales::where('sale_id', $saleId)->firstOrFail();
//     $salesItems = SalesItem::where('sale_id', $saleId)->get();
//     $productIds = $salesItems->pluck('product_id')->filter()->unique()->toArray();
//     Log::info('Product IDs from sales_items', ['product_ids' => $productIds]);

//     $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
//     Log::info('Products fetched', ['products' => $products->toArray()]);

//     $company = Company::find($transaction->cid);

//     $invoice = [
//         'number' => 'INV-' . $saleId,
//         'date' => now()->format('Y-m-d'),
//     ];

//     $data = [
//         'invoice' => (object) $invoice,
//         'transaction' => $transaction,
//         'salesItems' => $salesItems,
//         'products' => $products,
//         'company' => $company,
//     ];

//     $pdf = Pdf::loadView('invoices.invoice', $data);

//     return response($pdf->output())
//         ->header('Content-Type', 'application/pdf')
//         ->header('Content-Disposition', 'inline; filename="invoice_' . $saleId . '.pdf"');
// }

// public function generateInvoice($saleId)
//     {
//         Log::info("Generating invoice for sale_id: {$saleId}");

//         $user = Auth::user();
//         if (!$user) {
//             return response()->json(['message' => 'Unauthorized'], 401);
//         }

//         $transaction = TransactionSales::where('sale_id', $saleId)->firstOrFail();
//         $salesItems = SalesItem::where('sale_id', $saleId)->get();
//         if ($salesItems->isEmpty()) {
//             Log::warning("No sales items found for sale_id: {$saleId}");
//         }

//         // Fetch product_ids from sales table instead of sales_items
//         $sales = Sale::where('sale_id', $saleId)->get();
//         if ($sales->isEmpty()) {
//             Log::warning("No sales records found for sale_id: {$saleId}");
//         }

//         $productIds = $sales->pluck('product_id')->filter()->unique()->toArray();
//         Log::info('Product IDs from sales', ['product_ids' => $productIds]);

//         $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
//         Log::info('Products fetched', ['products' => $products->toArray()]);

//         $company = Company::find($transaction->cid);

//         $invoice = [
//             'number' => 'INV-' . $saleId,
//             'date' => now()->format('Y-m-d'),
//         ];

//         $data = [
//             'invoice' => (object) $invoice,
//             'transaction' => $transaction,
//             'salesItems' => $salesItems,
//             'products' => $products,
//             'company' => $company,
//         ];

//         $pdf = Pdf::loadView('invoices.invoice', $data);
//         return response($pdf->output())
//             ->header('Content-Type', 'application/pdf')
//             ->header('Content-Disposition', 'inline; filename="invoice_' . $saleId . '.pdf"');
//     }
public function generateInvoice($saleId)
    {
        Log::info("Generating invoice for sale_id: {$saleId}");

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $transaction = TransactionSales::where('sale_id', $saleId)->firstOrFail();
        $salesItems = SalesItem::where('sale_id', $saleId)->get();
        if ($salesItems->isEmpty()) {
            Log::warning("No sales items found for sale_id: {$saleId}");
        }

        $sales = Sale::where('sale_id', $saleId)->get();
        if ($sales->isEmpty()) {
            Log::warning("No sales records found for sale_id: {$saleId}");
        }

        $productIds = $sales->pluck('product_id')->filter()->unique()->toArray();
        Log::info('Product IDs from sales', ['product_ids' => $productIds]);

        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        Log::info('Products fetched', ['products' => $products->toArray()]);

        $company = Company::find($transaction->cid);

        $invoice = [
            'number' => 'INV-' . $saleId,
            'date' => $transaction->created_at,
        ];

        // Combine sales and sales_items data
        $items = [];
        foreach ($salesItems as $index => $salesItem) {
            $sale = $sales[$index] ?? null;
            $product = $sale ? $products->get($sale->product_id) : null;
            $items[] = [
                'product_name' => $product ? $product->name : 'Unknown Product',
                'quantity' => $salesItem->quantity,
                'per_item_cost' => $salesItem->per_item_cost,
                'discount' => $salesItem->discount,
                'total' => $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount),            ];
        }
        Log::info('Invoice items prepared', ['items' => $items]);

        $data = [
            'invoice' => (object) $invoice,
            'transaction' => $transaction,
            'items' => $items,
            'company' => $company,
        ];

        $pdf = Pdf::loadView('invoices.invoice', $data);
        return response($pdf->output())
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="invoice_' . $saleId . '.pdf"');
    }
}
