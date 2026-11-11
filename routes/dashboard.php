<?php

use App\Http\Controllers\Ui\DashBoardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [DashBoardController::class, 'dashboard']);
Route::get('/{section}', function (string $section) {
    return Inertia::render('backend/pages/catalog/ManagementPage', ['section' => $section]);
})->whereIn('section', ['categories', 'products', 'inventory', 'orders']);

Route::get('/customers', fn () => Inertia::render('backend/pages/customers/CustomersPage'));
Route::get('/users', fn () => Inertia::render('backend/pages/users/UsersPage'));
Route::get('/roles', fn () => Inertia::render('backend/pages/roles/RolesPage'));
Route::get('/roles/create', fn () => Inertia::render('backend/pages/roles/RolesPage', ['mode' => 'create']));
Route::get('/roles/{role}/edit', fn (int $role) => Inertia::render('backend/pages/roles/RolesPage', [
    'mode' => 'edit',
    'roleId' => $role,
]))->whereNumber('role');

Route::get('/products/create', fn () => Inertia::render('backend/pages/catalog/ProductFormPage'));
Route::get('/products/{product}/edit', fn (int $product) => Inertia::render('backend/pages/catalog/ProductFormPage', [
    'productId' => $product,
]))->whereNumber('product');
