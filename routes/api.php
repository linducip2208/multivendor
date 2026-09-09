<?php

use App\Http\Controllers\Api\DeliveryApiController;
use App\Http\Controllers\Api\MarketplaceApiController;
use App\Http\Controllers\Api\VendorApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->controller(MarketplaceApiController::class)->group(function () {
    Route::get('products', 'products');
    Route::get('products/{slug}', 'product');
    Route::get('categories', 'categories');
    Route::get('shops', 'shops');
    Route::get('shops/{slug}', 'shop');
    Route::post('login', 'login')->middleware('throttle:auth');
    Route::post('register', 'register')->middleware('throttle:auth');
    Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
        Route::get('profile', 'profile');
        Route::put('profile', 'updateProfile');
        Route::get('orders', 'orders');
        Route::get('orders/{order}', 'order');
        Route::post('orders/{order}/cancel', 'cancel');
        Route::post('reviews', 'review')->middleware('throttle:10,1');
        Route::get('track/{number}', 'track');
        Route::get('cart', 'cart');
        Route::post('cart', 'addCart');
        Route::put('cart/{cart}', 'updateCart');
        Route::delete('cart/{cart}', 'removeCart');
    });
});

Route::prefix('v2/vendor')->controller(VendorApiController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:auth');
    Route::middleware(['auth:sanctum', 'vendor.api', 'throttle:60,1'])->group(function () {
        Route::get('dashboard', 'dashboard');
        Route::get('products', 'products');
        Route::get('orders', 'orders');
        Route::put('orders/{order}/status', 'updateOrder');
    });
});

Route::prefix('v3/delivery')->controller(DeliveryApiController::class)->group(function () {
    Route::post('login', 'login')->middleware('throttle:auth');
    Route::middleware(['auth:sanctum', 'delivery.api', 'throttle:60,1'])->group(function () {
        Route::get('orders', 'orders');
        Route::put('orders/{order}/status', 'updateOrder');
    });
});
