<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductImageUpdateService
{
    public function handle(Request $request, int $id): ProductImage
    {
        $validated = $request->validate([
            'position' => 'required|integer|min:0',
            'primary' => 'required|boolean',
        ]);

        return DB::transaction(function () use ($id, $validated) {
            $image = ProductImage::findOrFail($id);
            Product::whereKey($image->product_id)->lockForUpdate()->firstOrFail();
            if ($validated['primary']) {
                ProductImage::where('product_id', $image->product_id)->update(['primary' => false]);
            } elseif ($image->primary) {
                throw ValidationException::withMessages(['primary' => 'Choose another primary image first.']);
            }
            $image->update($validated);

            return $image;
        });
    }
}
