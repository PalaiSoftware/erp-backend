<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// THIS IS THE MAGIC ROUTE — PUT IT IN web.php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/report/b2c', [SalesController::class, 'b2cSalesReport'])
         ->name('b2c.report');
});