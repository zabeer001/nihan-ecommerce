<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Models\Variant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VariantIndexService
{
    public function handle(Request $request, bool $forOrder = false): LengthAwarePaginator
    {
        if ($forOrder) {
            $search = $request->validate(['search' => 'nullable|string|max:255'])['search'] ?? null;

            return Variant::query()->with(['product.images', 'images'])
                ->where('active', true)
                ->whereHas('product', fn ($query) => $query->where('status', 'active')
                    ->whereHas('categories', fn ($categories) => $categories->where('active', true)))
                ->when($search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('sku', 'like', "%{$search}%")->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('product', fn ($product) => $product->where('name', 'like', "%{$search}%"))))
                ->orderBy('id')->paginate(30)->through(fn (Variant $variant) => [
                    'id' => $variant->id, 'sku' => $variant->sku, 'name' => $variant->name,
                    'barcode' => $variant->barcode, 'attributes' => $variant->attributes,
                    'price_cents' => $variant->price_cents, 'stock' => $variant->stock, 'active' => $variant->active,
                    'product_name' => $variant->product->name,
                    'image_url' => ($variant->images->first() ?? $variant->product->images->first())?->withUrl()['url'] ?? null,
                ]);
        }

        return DB::table('variants')->join('products', 'products.id', '=', 'variants.product_id')
            ->when($request->query('search'), fn ($query, $search) => $query->where(
                fn ($query) => $query->where('sku', 'like', "%{$search}%")->orWhere('products.name', 'like', "%{$search}%")
            ))
            ->orderBy('variants.id')->select('variants.*')->addSelect('products.name as product_name')->paginate(30);
    }
}
