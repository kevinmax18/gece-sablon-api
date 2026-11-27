<?php

use App\Http\Controllers\AdminAccountController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

// ---- Publik (tamu boleh melihat katalog) ----
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);

// ---- Wajib login ----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Pesanan (pelanggan: miliknya; admin/owner: semua)
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

    // ---- Admin dan Owner ----
    Route::middleware('role:admin,owner')->group(function () {
        Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::put('/orders/{order}/production', [OrderController::class, 'updateProduction']);

        Route::get('/stock', [StockController::class, 'index']);
        Route::post('/stock', [StockController::class, 'store']);
        Route::put('/stock/{id}', [StockController::class, 'update']);
        Route::delete('/stock/{id}', [StockController::class, 'destroy']);
    });

    // ---- Owner saja (keuangan, produk, akun admin) ----
    Route::middleware('role:owner')->group(function () {
        Route::get('/owner/products', [ProductController::class, 'ownerIndex']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);

        Route::get('/finance/summary', [TransactionController::class, 'summary']);
        Route::get('/finance/transactions', [TransactionController::class, 'index']);
        Route::post('/finance/transactions', [TransactionController::class, 'store']);
        Route::put('/finance/transactions/{id}', [TransactionController::class, 'update']);
        Route::delete('/finance/transactions/{id}', [TransactionController::class, 'destroy']);

        Route::get('/owner/admins', [AdminAccountController::class, 'index']);
        Route::post('/owner/admins', [AdminAccountController::class, 'store']);
        Route::put('/owner/admins/{id}', [AdminAccountController::class, 'update']);
        Route::delete('/owner/admins/{id}', [AdminAccountController::class, 'destroy']);
    });
});
