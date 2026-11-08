<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\Orders\OrderController;
use App\Http\Controllers\Api\Product\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('signin', [AuthController::class, 'signin']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('refresh', [AuthController::class, 'refresh']);
Route::post('signout', [AuthController::class, 'signout']);
Route::get('profile', fn (Request $request) => response()->json([
    'data' => $request->user(),
]))->middleware('auth:api');

Route::prefix('admin')->middleware(['auth:api', 'active_account', 'role:admin,superadmin'])->group(function () {
    Route::get('summary', [DashboardController::class, 'summary']);
    Route::get('categories', [CategoryController::class, 'categories']);
    Route::post('categories', [CategoryController::class, 'category']);
    Route::put('categories/{id}', [CategoryController::class, 'category']);
    Route::delete('categories/{id}', [CategoryController::class, 'deleteCategory']);
    Route::get('products', [ProductController::class, 'index']);
    Route::post('products', [ProductController::class, 'store']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::put('products/{product}', [ProductController::class, 'update']);
    Route::delete('products/{product}', [ProductController::class, 'destroy']);
    Route::get('variants', [ProductController::class, 'variants']);
    Route::post('products/{product}/variants', [ProductController::class, 'variant']);
    Route::put('products/{product}/variants/{id}', [ProductController::class, 'variant']);
    Route::post('products/{id}/images', [ProductController::class, 'image']);
    Route::put('images/{id}', [ProductController::class, 'updateImage']);
    Route::delete('images/{id}', [ProductController::class, 'deleteImage']);
    Route::post('variants/{id}/adjust', [InventoryController::class, 'adjust']);
    Route::get('variants/{id}/movements', [InventoryController::class, 'movements']);
    Route::get('orders', [OrderController::class, 'orders']);
    Route::post('orders', [OrderController::class, 'createOrder']);
    Route::get('orders/{id}', [OrderController::class, 'order']);
    Route::patch('orders/{id}/status', [OrderController::class, 'status']);
});
