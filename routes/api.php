<?php

use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Category\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\Dashboard\DashboardController;
use App\Http\Controllers\Api\Inventory\InventoryController;
use App\Http\Controllers\Api\Orders\OrderController;
use App\Http\Controllers\Api\Product\ProductController;
use App\Http\Controllers\Api\Product\StorefrontProductController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('signin', [AuthController::class, 'signin']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('refresh', [AuthController::class, 'refresh']);
Route::post('signout', [AuthController::class, 'signout']);
Route::get('profile', fn (Request $request) => response()->json([
    'data' => [...$request->user()->toArray(), 'permissions' => $request->user()->permissionNames()],
]))->middleware(['auth:api', 'active_account']);

Route::pattern('role', '[0-9]+');

Route::get('storefront/categories', [StorefrontProductController::class, 'categories']);
Route::get('storefront/products', [StorefrontProductController::class, 'index']);
Route::get('storefront/products/{slug}', [StorefrontProductController::class, 'show']);

Route::prefix('admin')->middleware(['auth:api', 'active_account'])->group(function () {
    Route::get('summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');
    Route::get('categories', [CategoryController::class, 'categories'])->middleware('permission:categories.view,products.create,products.update');
    Route::post('categories', [CategoryController::class, 'category'])->middleware('permission:categories.create');
    Route::put('categories/{id}', [CategoryController::class, 'category'])->middleware('permission:categories.update');
    Route::delete('categories/{id}', [CategoryController::class, 'deleteCategory'])->middleware('permission:categories.delete');
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::post('products/image-uploads', [ProductController::class, 'stageImage'])->middleware('permission:products.create');
    Route::post('products/{product}/image-uploads', [ProductController::class, 'stageImage'])->middleware('permission:products.update');
    Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view,products.update');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::get('variants', [ProductController::class, 'variants'])->middleware('permission:inventory.view');
    Route::post('products/{product}/variants', [ProductController::class, 'variant'])->middleware('permission:products.update');
    Route::put('products/{product}/variants/{id}', [ProductController::class, 'variant'])->middleware('permission:products.update');
    Route::post('products/{id}/images', [ProductController::class, 'image'])->middleware('permission:products.update');
    Route::put('images/{id}', [ProductController::class, 'updateImage'])->middleware('permission:products.update');
    Route::delete('images/{id}', [ProductController::class, 'deleteImage'])->middleware('permission:products.update');
    Route::post('variants/{id}/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.update');
    Route::get('variants/{id}/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
    Route::get('orders', [OrderController::class, 'orders'])->middleware('permission:orders.view');
    Route::post('orders', [OrderController::class, 'createOrder'])->middleware('permission:orders.create');
    Route::get('orders/variants', [ProductController::class, 'orderVariants'])->middleware('permission:orders.create');
    Route::get('orders/{id}', [OrderController::class, 'order'])->middleware('permission:orders.view');
    Route::patch('orders/{id}/status', [OrderController::class, 'status'])->middleware('permission:orders.update');

    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

    Route::middleware('permission:roles.manage')->group(function () {
        Route::get('users', [UserController::class, 'index']);
        Route::get('roles/options', [RoleController::class, 'options']);
        Route::put('roles/assignment', [RoleController::class, 'assign']);
        Route::apiResource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
    });
});
