<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Http\Controllers\Api\Product\Services\Concerns\HandlesProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductStoreService
{
    use ApiResponse, HandlesProduct;

    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly VariantSaveService $variantService,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->saveProduct($request),
            'Product created successfully.',
            201,
        );
    }
}
