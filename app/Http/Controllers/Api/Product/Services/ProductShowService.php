<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductShowService
{
    use ApiResponse;

    public function handle(Request $request, Product $product): JsonResponse
    {
        $product->load('categories');
        $id = $product->id;

        return $this->successResponse([
            'product' => $product,
            'variants' => $product->variants()->with('images')->get()->map(fn ($variant) => [
                ...$variant->attributesToArray(),
                'images' => $variant->images->map->withUrl(),
            ]),
            'images' => ProductImage::where('product_id', $id)->whereNull('variant_id')
                ->orderBy('position')->orderBy('id')->get()->map->withUrl(),
        ], 'Product fetched successfully.');
    }
}
