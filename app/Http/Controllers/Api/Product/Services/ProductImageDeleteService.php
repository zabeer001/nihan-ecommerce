<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductImageDeleteService
{
    public function handle(int $id): Response
    {
        $shared = ProductImage::whereNull('variant_id');
        $productId = (clone $shared)->findOrFail($id)->product_id;
        DB::transaction(function () use ($shared, $productId, $id) {
            Product::whereKey($productId)->lockForUpdate()->firstOrFail();
            $image = (clone $shared)->lockForUpdate()->findOrFail($id);
            $image->delete();
            if ($image->primary) {
                $nextImage = $shared->where('product_id', $productId)->orderBy('position')->orderBy('id')->first();
                $nextImage?->update(['primary' => true]);
            }
            DB::afterCommit(fn () => rescue(fn () => Storage::disk('public')->delete($image->path)));
        });

        return response()->noContent();
    }
}
