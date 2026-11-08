<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Variant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductShowService
{
    use ApiResponse;

    public function handle(Request $request, Product $product): JsonResponse
    {
        $product->load('categories');
        $id = $product->id;

        return $this->successResponse([
            'product' => $product,
            'variants' => Variant::where('product_id', $id)->get(),
            'images' => ProductImage::where('product_id', $id)->orderBy('position')->get()->map(fn ($image) => $image->toArray() + ['url' => Storage::disk('public')->url($image->path)]),
        ], 'Product fetched successfully.');
    }
}
