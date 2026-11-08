<?php

use App\Http\Controllers\Ui\DashBoardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [DashBoardController::class, 'dashboard']);
Route::get('/{section}', function (string $section) {
    return Inertia::render('backend/pages/catalog/ManagementPage', ['section' => $section]);
})->whereIn('section', ['categories', 'products', 'inventory', 'orders']);

Route::get('/products/create', fn () => Inertia::render('backend/pages/catalog/ProductFormPage'));
Route::get('/products/{product}/edit', fn (int $product) => Inertia::render('backend/pages/catalog/ProductFormPage', [
    'productId' => $product,
]))->whereNumber('product');
