<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductImageStoreService
{
    public function handle(Request $request, int $id): ProductImage
    {
        Product::findOrFail($id);
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'position' => 'required|integer|min:0',
            'primary' => 'required|boolean',
        ]);
        $path = $request->file('image')->store('products', 'public');

        return DB::transaction(function () use ($id, $validated, $path) {
            Product::whereKey($id)->lockForUpdate()->firstOrFail();
            $primary = $validated['primary'] || ! ProductImage::where('product_id', $id)->exists();
            if ($primary) {
                ProductImage::where('product_id', $id)->update(['primary' => false]);
            }

            return ProductImage::create([
                'product_id' => $id,
                'path' => $path,
                'position' => $validated['position'],
                'primary' => $primary,
            ]);
        });
    }
}
