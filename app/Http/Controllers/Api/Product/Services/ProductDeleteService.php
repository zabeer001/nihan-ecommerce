<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductDeleteService
{
    use ApiResponse;

    public function handle(Product $product): JsonResponse
    {
        DB::transaction(function () use ($product) {
            Product::whereKey($product->id)->lockForUpdate()->firstOrFail()->update(['status' => 'draft']);
            $product->variants()->update(['active' => false]);
        });

        return $this->successResponse(null, 'Product archived successfully.');
    }
}
