<?php

use App\Http\Controllers\Api\Storefront\OrderController;
use App\Http\Controllers\Api\Storefront\ProductController;
use App\Http\Middleware\EnsureStorefrontToken;
use Illuminate\Support\Facades\Route;

Route::prefix('store')->middleware([EnsureStorefrontToken::class])->group(function () {
    Route::get('/categories', [ProductController::class, 'categories']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show'])->whereNumber('id');
    Route::post('/orders', [OrderController::class, 'store']);
});
