<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Api\Product\Services\ProductDeleteService;
use App\Http\Controllers\Api\Product\Services\ProductImageDeleteService;
use App\Http\Controllers\Api\Product\Services\ProductImageStoreService;
use App\Http\Controllers\Api\Product\Services\ProductImageUpdateService;
use App\Http\Controllers\Api\Product\Services\ProductImageUploadService;
use App\Http\Controllers\Api\Product\Services\ProductIndexService;
use App\Http\Controllers\Api\Product\Services\ProductShowService;
use App\Http\Controllers\Api\Product\Services\ProductStoreService;
use App\Http\Controllers\Api\Product\Services\ProductUpdateService;
use App\Http\Controllers\Api\Product\Services\VariantIndexService;
use App\Http\Controllers\Api\Product\Services\VariantSaveService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    public function index(Request $request, ProductIndexService $service): JsonResponse
    {
        return $service->handle($request);
    }

    public function store(Request $request, ProductStoreService $service): JsonResponse
    {
        return $service->handle($request);
    }

    public function show(Request $request, Product $product, ProductShowService $service): JsonResponse
    {
        return $service->handle($request, $product);
    }

    public function update(Request $request, Product $product, ProductUpdateService $service): JsonResponse
    {
        return $service->handle($request, $product);
    }

    public function destroy(Product $product, ProductDeleteService $service): JsonResponse
    {
        return $service->handle($product);
    }

    public function variants(VariantIndexService $service, Request $request): LengthAwarePaginator
    {
        return $service->handle($request);
    }

    public function orderVariants(VariantIndexService $service, Request $request): LengthAwarePaginator
    {
        return $service->handle($request, forOrder: true);
    }

    public function variant(VariantSaveService $service, Request $request, int $product, ?int $id = null): Variant
    {
        return $service->handle($request, $product, $id);
    }

    public function image(ProductImageStoreService $service, Request $request, int $id): ProductImage
    {
        return $service->handle($request, $id);
    }

    public function stageImage(Request $request, ProductImageUploadService $service, ?Product $product = null): JsonResponse
    {
        return $service->handle($request, $product?->getKey());
    }

    public function updateImage(ProductImageUpdateService $service, Request $request, int $id): ProductImage
    {
        return $service->handle($request, $id);
    }

    public function deleteImage(ProductImageDeleteService $service, int $id): Response
    {
        return $service->handle($id);
    }
}
