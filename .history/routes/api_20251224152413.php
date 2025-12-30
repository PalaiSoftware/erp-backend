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
use App\Http\Controllers\LmAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductInfoController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\ReportController;


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
    Route::put('/vendors/{id}', [VendorController::class, 'update']);    
    Route::post('/products', [ProductController::class, 'store']); 
    Route::get('/products', [ProductController::class, 'index']);
    Route::put('/products/{id}', [ProductController::class, 'update']);
    Route::post('/hscode/products', [ProductController::class, 'checkHscodeProduct']);
    Route::post('/purchase', [PurchaseController::class, 'store']);
    Route::post('/sales', [SalesController::class, 'store']);
    Route::get('/invoices/{sale_id}', [SalesController::class, 'generateInvoice']);
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::post('/customers/check', [CustomerController::class, 'checkCustomer']);
    Route::post('/customer/add-to-company', [CustomerController::class, 'addCustomerToCompany']);
    Route::get('/customers', [CustomerController::class, 'index']);
    
    //Report generation route
    Route::post('/report/profit-loss', [ReportController::class, 'getProfitLossReport']);
    
    //b2c sales report route
    Route::get('/sales/report/b2c', [SalesController::class, 'b2cSalesReport'])
         ->name('sales.b2c-report');

    // B2B sales report route
    Route::get('/sales/report/b2b', [SalesController::class, 'b2bSalesReport'])
         ->name('sales.b2b-report');

    // New route for geting users
    Route::post('/users-by-role', [AuthController::class, 'getUsersByRole']);
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
    Route::post('/lm-newuser', [LmAuthController::class, 'Lmnewuser']);
    Route::get('/lm-users', [LmAuthController::class, 'getLmUsersByRole']);
    Route::post('/lm-user-block-unblock', [LmAuthController::class, 'LmUserBlockUnblock']);
    Route::post('/lm-user-promote-demote', [LmAuthController::class, 'LmUserPromoteDemote']);
    Route::put('/customer/{id}', [CustomerController::class, 'update']);
    Route::put('/sales/{transactionId}', [SalesController::class, 'update']);
    Route::get('/sales/{transactionId}', [SalesController::class, 'getTransaction']);
    Route::put('/transactions/{transaction_id}', [PurchaseController::class, 'updateTransactionById']);
    Route::get('/customers/{customerId}', [CustomerController::class, 'getCustomer']);
    Route::get('/vendor/{vendorId}', [VendorController::class, 'getVendorById']);
    Route::get('/products/{product_id}', [ProductController::class, 'getProductById']);
    Route::delete('/destroy-sales/{transactionId}', [SalesController::class, 'destroy']);
    Route::delete('/destroy-purchase/{transactionId}', [PurchaseController::class, 'destroy']);
    Route::get('/payment-modes', [PaymentController::class, 'index']);
    //Route::post('/product-info', [ProductInfoController::class, 'store']);
    Route::get('/product-info/{cid}', [ProductInfoController::class, 'allProductInfo']);
    Route::get('/product/{pid}', [ProductInfoController::class, 'getProductById']);
    Route::put('/product-info/{pid}', [ProductInfoController::class, 'updateProductById']);
    Route::delete('/product-info/{pid}', [ProductInfoController::class, 'destroy']);
    Route::get('/dues/{cid}', [SalesController::class, 'getCustomersWithDues']);
    Route::get('/customer/dues/{customer_id}', [SalesController::class, 'getCustomerDues']);
    Route::get('/units/{product_id}', [ProductController::class, 'getUnitsByProductId']);
    Route::get('/clients/{cid}', [AuthController::class, 'getCompanyDetail']);
    Route::put('/clients/{cid}', [AuthController::class, 'updateCompanyDetails']);
    Route::get('/user/{userId}', [AuthController::class, 'getUserDetailsById']);
    Route::put('/user/{userId}', [AuthController::class, 'updateUserDetails']);
    Route::post('/change-password/{userId}', [AuthController::class, 'changeUserPassword']);
    Route::post('/add-unit', [HelperController::class, 'addUnit']);
    Route::get('/units', [HelperController::class, 'index']);
    Route::get('/unit/{unitId}', [HelperController::class, 'getUnit']);
    Route::put('/unit/{unitId}', [HelperController::class, 'updateUnit']);
    Route::post('/categories', [CategoryController::class, 'addCategory']);
    Route::get('/categories', [CategoryController::class, 'getCategories']);


    Route::get('/customer-types', [CustomerTypeController::class, 'index']);
    Route::post('/customer-types', [CustomerTypeController::class, 'store']);
    Route::put('/customer-types/{id}', [CustomerTypeController::class, 'update']);
    Route::delete('/customer-types/{id}', [CustomerTypeController::class, 'destroy']);
    
    Route::get('/pending-registrations', [RegistrationController::class, 'pendingList']); // for admin
    Route::get('/pending-user/{id}', [RegistrationController::class, 'getUserById']);
    Route::post('/approve', [RegistrationController::class, 'approve']); 
    Route::post('/pending/reject', [RegistrationController::class, 'reject']);
    Route::get('/purchase-transactions-by-pid', [PurchaseController::class, 'getPurchaseTransactionsByPid']);
    Route::get('/sales-transactions-by-pid', [SalesController::class, 'getSalesTransactionsByPid']);

});
Route::post('/register', [RegistrationController::class, 'register']);
//Route::get('/units', [HelperController::class, 'index']);
//Route::post('/add-unit', [HelperController::class, 'addUnit']);
Route::get('/widget/total-purchases/{cid}', [WidgetController::class, 'getTotalPurchases']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/company-stats', [HelperController::class, 'getCompanyStats']);
//Route::post('/categories', [CategoryController::class, 'addCategory']);
//Route::get('/categories', [CategoryController::class, 'getCategories']);

Route::get('/version', function () {
    return response()->json([
        'version' => config('app.version')
    ]);
});
//Route::post('/pending-register', [RegistrationController::class, 'register']);
