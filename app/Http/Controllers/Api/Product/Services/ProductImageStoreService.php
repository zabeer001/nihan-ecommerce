<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductImageStoreService
{
    public function handle(Request $request, int $id): ProductImage
    {
        Product::findOrFail($id);
        $validated = $request->validate([
            'image' => ProductImageUploadService::IMAGE_RULE,
            'position' => 'required|integer|min:0',
            'primary' => 'required|boolean',
        ]);
        $path = $request->file('image')->store('products', 'public');

        abort_if($path === false, 500, 'Could not store the image.');
        try {
            return DB::transaction(function () use ($id, $validated, $path) {
                Product::whereKey($id)->lockForUpdate()->firstOrFail();
                $shared = ProductImage::where('product_id', $id)->whereNull('variant_id');
                $primary = $validated['primary'] || ! $shared->exists();
                if ($primary) {
                    $shared->update(['primary' => false]);
                }

                return ProductImage::create([
                    'product_id' => $id,
                    'path' => $path,
                    'position' => $validated['position'],
                    'primary' => $primary,
                ]);
            });
        } catch (Throwable $exception) {
            rescue(fn () => Storage::disk('public')->delete($path));
            throw $exception;
        }
    }
}
