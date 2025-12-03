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

// 1. TEMPORARY route (no login required) – USE THIS FIRST TO TEST
Route::get('/b2c-download', [SalesController::class, 'b2cSalesReport'])
     ->name('b2c.download.temp');

// 2. FINAL secure route (requires login) – use this after testing
Route::middleware('auth:sanctum')->get('/report/b2c', [SalesController::class, 'b2cSalesReport'])
     ->name('b2c.report');