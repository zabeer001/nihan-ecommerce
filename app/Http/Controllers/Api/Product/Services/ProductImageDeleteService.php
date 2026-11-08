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
        $image = ProductImage::findOrFail($id);
        DB::transaction(function () use ($image) {
            Product::whereKey($image->product_id)->lockForUpdate()->firstOrFail();
            $image->delete();
            if ($image->primary) {
                $nextImage = ProductImage::where('product_id', $image->product_id)->orderBy('position')->first();
                $nextImage?->update(['primary' => true]);
            }
        });
        Storage::disk('public')->delete($image->path);

        return response()->noContent();
    }
}
