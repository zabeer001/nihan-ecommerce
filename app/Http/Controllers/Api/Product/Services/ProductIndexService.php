<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductIndexService
{
    use ApiResponse;

    public function handle(Request $request): JsonResponse
    {
        $products = Product::with('categories')->when($request->query('search'), fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))->orderByDesc('id')->paginate(20);

        return $this->successResponse($products, 'Products fetched successfully.');
    }
}
