<?php

namespace App\Http\Controllers\Api\Category;

use App\Http\Controllers\Api\Category\Services\CategoryDeleteService;
use App\Http\Controllers\Api\Category\Services\CategoryIndexService;
use App\Http\Controllers\Api\Category\Services\CategorySaveService;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function categories(CategoryIndexService $service): Collection
    {
        return $service->handle();
    }

    public function category(CategorySaveService $service, Request $request, ?int $id = null): Category
    {
        return $service->handle($request, $id);
    }

    public function deleteCategory(CategoryDeleteService $service, int $id): Response
    {
        return $service->handle($id);
    }
}
