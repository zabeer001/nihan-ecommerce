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
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $locked->update(['status' => 'draft']);
            $locked->variants()->update(['active' => false]);
            $locked->delete();
        });

        return $this->successResponse(null, 'Product deleted successfully.');
    }
}
