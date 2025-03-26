<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\WidgetController;
use App\Http\Controllers\HelperController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) { 
    return $request->user();
});
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/companies', [CompanyController::class, 'createCompany']);
    Route::post('/newuser', [AuthController::class, 'newuser']);
    Route::post('/vendors', [VendorController::class, 'store']);
    Route::get('/vendors', [VendorController::class, 'index']);
    Route::post('/vendors/check', [VendorController::class, 'checkVendor']);
    Route::post('/vendors/add-to-company', [VendorController::class, 'addVendorToCompany']);
    Route::post('/products', [ProductController::class, 'store']); 
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/hscode/products', [ProductController::class, 'checkHscodeProduct']);
    Route::post('/product/add-to-company', [ProductController::class, 'addCompanyToProduct']);
    Route::post('/purchase', [PurchaseController::class, 'store']);
    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/invoices/{sale_id}', [SalesController::class, 'generateInvoice']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::post('/customers/check', [CustomerController::class, 'checkCustomer']);
    Route::post('/customer/add-to-company', [CustomerController::class, 'addCustomerToCompany']);
    Route::get('/customers', [CustomerController::class, 'index']);

    // New route for geting users
    Route::get('/users-by-role', [AuthController::class, 'getUsersByRole']);
    // New route for blocking/unblocking users
    Route::post('/user-block-unblock', [AuthController::class, 'userBlockUnblock']);
    // for promote/demote users
    Route::post('/user-promote-demote', [AuthController::class, 'UserPromoteDemote']);
    Route::get('/products/stock/{cid}', [HelperController::class, 'getProductStock']);
    Route::post('/transactions-by-cid', [PurchaseController::class, 'getTransactionsByCid']);
    Route::post('/purchases-by-transaction-id', [PurchaseController::class, 'getPurchaseDetailsByTransaction']);
    Route::get('/sales/company/{cid}', [SalesController::class, 'getAllInvoicesByCompany']);
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/update-recent-company', [CompanyController::class, 'updateRecentCompany']);
    Route::get('/get-all-companies', [CompanyController::class, 'getAllCompanies']);
    Route::post('/companies/toggle-block', [CompanyController::class, 'toggleBlockCompany']);
    Route::get('/total-sale/{cid}', [SalesController::class, 'getTotalSaleAmount']);
    Route::post('/purchase-widget', [PurchaseController::class, 'getPurchaseWidget']);
    Route::post('/customer-stats', [SalesController::class, 'getCustomerStats']);

});

Route::get('/units', [HelperController::class, 'index']);
Route::post('/add-unit', [HelperController::class, 'addUnit']);
Route::get('/widget/total-purchases/{cid}', [WidgetController::class, 'getTotalPurchases']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
