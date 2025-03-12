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
            // Step 1: Create TransactionSales record
            $transaction = TransactionSales::create([
                'uid' => $user->id,
                'cid' => $request->cid,
                'customer_id' => $request->customer_id,
                'payment_mode' => $request->payment_mode,
                'created_at' => now(),
            ]);
            $transactionId = $transaction->id;
            Log::info('Created transaction', ['transaction_id' => $transactionId]);

            // Step 2: Create Sale and SalesItem records for each product
            foreach ($request->products as $product) {
                // Create Sale record
                $sale = Sale::create([
                    'transaction_id' => $transactionId,
                    'product_id' => $product['product_id'],
                ]);
                $saleId = $sale->id;
                Log::info('Created sale', ['sale_id' => $saleId]);

                // Create SalesItem record
                SalesItem::create([
                    'sale_id' => $saleId,
                    'quantity' => $product['quantity'],
                    'discount' => $product['discount'] ?? 0,
                    'per_item_cost' => $product['per_item_cost'],
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

    // public function generateInvoice($transactionId)
    // {
    //     Log::info("Generating invoice for transaction_id: {$transactionId}");

    //     $user = Auth::user();
    //     if (!$user) {
    //         return response()->json(['message' => 'Unauthorized'], 401);
    //     }

    //     $transaction = TransactionSales::findOrFail($transactionId);
    //     $sales = Sale::where('transaction_id', $transactionId)->with('salesItem')->get();

    //     if ($sales->isEmpty()) {
    //         Log::warning("No sales records found for transaction_id: {$transactionId}");
    //     }

    //     $customer = Customer::find($transaction->customer_id);
    //     $company = Company::find($transaction->cid);
    //     $userDetails = User::find($transaction->uid);

    //     $invoice = [
    //         'number' => 'INV-' . $transactionId,
    //         'date' => $transaction->created_at,
    //     ];

    //     // Prepare invoice items
    //     $items = [];
    //     $totalAmount = 0;
    //     foreach ($sales as $sale) {
    //         $product = Product::find($sale->product_id);
    //         $salesItem = $sale->salesItem;
    //         if ($salesItem) {
    //             $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
    //             $items[] = [
    //                 'product_name' => $product ? $product->name : 'Unknown Product',
    //                 'quantity' => $salesItem->quantity,
    //                 'per_item_cost' => $salesItem->per_item_cost,
    //                 'discount' => $salesItem->discount,
    //                 'total' => $itemTotal,
    //             ];
    //             $totalAmount += $itemTotal;
    //         }
    //     }
    //     Log::info('Invoice items prepared', ['items' => $items]);

    //     $data = [
    //         'invoice' => (object) $invoice,
    //         'transaction' => $transaction,
    //         'items' => $items,
    //         'total_amount' => $totalAmount,
    //         'company' => $company,
    //         'customer' => $customer,
    //         'userDetails' => $userDetails,
    //     ];

    //     $pdf = Pdf::loadView('invoices.invoice', $data);
    //     return response($pdf->output())
    //         ->header('Content-Type', 'application/pdf')
    //         ->header('Content-Disposition', "inline; filename=\"invoice_{$transactionId}.pdf\"");
    // }
    public function generateInvoice($transactionId)
    {
        Log::info("Generating invoice for transaction_id: {$transactionId}");
    
        // Check if user is authenticated
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        try {
            // Fetch the transaction
            $transaction = TransactionSales::findOrFail($transactionId);
            Log::info('Transaction found', ['transaction' => $transaction->toArray()]);
    
            // Fetch sales with their items
            $sales = Sale::where('transaction_id', $transactionId)->with('salesItem')->get();
            if ($sales->isEmpty()) {
                Log::warning("No sales records found for transaction_id: {$transactionId}");
                return response()->json(['message' => 'No sales records found for this transaction'], 404);
            }
            Log::info('Sales records', ['sales' => $sales->toArray()]);
    
            // Fetch related data
            $customer = Customer::find($transaction->customer_id);
            $company = Company::find($transaction->cid);
            $userDetails = User::find($transaction->uid);
            Log::info('Related data', [
                'customer' => $customer ? $customer->toArray() : null,
                'company' => $company ? $company->toArray() : null,
                'user' => $userDetails ? $userDetails->toArray() : null
            ]);
    
            // Set up invoice basics
            $invoice = [
                'number' => 'INV-' . $transactionId,
                // 'date' => $transaction->created_at->format('Y-m-d'),
                // 'date' => Carbon::parse($transaction->created_at)->format('Y-m-d'),
                'date' => Carbon::parse($transaction->created_at)->format('Y-m-d'),


            ];
            Log::info('Transaction created_at value:', ['created_at' => $transaction->created_at]);
            // Prepare invoice items
            $items = [];
            $totalAmount = 0;
            foreach ($sales as $sale) {
                $product = Product::find($sale->product_id);
                $salesItem = $sale->salesItem;
    
                if ($salesItem) {
                    // Adjust this calculation if the sales update changed discount logic
                    $itemTotal = $salesItem->quantity * ($salesItem->per_item_cost - $salesItem->discount);
                    $items[] = [
                        'product_name' => $product ? $product->name : 'Unknown Product',
                        'quantity' => $salesItem->quantity,
                        'per_item_cost' => $salesItem->per_item_cost,
                        'discount' => $salesItem->discount,
                        'total' => $itemTotal,
                        // Add new fields here if they exist, e.g., 'tax' => $salesItem->tax
                    ];
                    $totalAmount += $itemTotal;
                }
            }
            Log::info('Invoice items prepared', ['items' => $items, 'total_amount' => $totalAmount]);
    
            // Prepare data for the PDF
            $data = [
                'invoice' => (object) $invoice,
                'transaction' => $transaction,
                'items' => $items,
                'total_amount' => $totalAmount,
                'company' => $company,
                'customer' => $customer,
                'userDetails' => $userDetails,
            ];
    
            // Generate and return the PDF
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