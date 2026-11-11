<?php

namespace App\Http\Controllers\Api\Product;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Variant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $category = $request->validate(['category' => 'sometimes|string|max:100'])['category'] ?? null;
        $products = $this->published()
            ->when($category, fn ($query) => $query->whereHas('categories', fn ($categories) => $categories
                ->where('active', true)->whereRaw('LOWER(name) = ?', [mb_strtolower($category)])))
            ->withMin(['variants as price_cents' => fn ($variants) => $variants->where('active', true)], 'price_cents')
            ->latest('id')->simplePaginate(24);

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $product) => [
                'slug' => $product->slug,
                'name' => $product->name,
                'price_cents' => (int) $product->price_cents,
                'image' => $this->photo($product->images->first()
                    ?? $product->variants->first(fn (Variant $variant) => $variant->images->isNotEmpty())?->images->first()),
            ]),
            'has_more' => $products->hasMorePages(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $product = $this->published()->withCount('variants')->where('slug', $slug)->firstOrFail();
        $first = $product->variants->first();

        return response()->json(['data' => [
            'slug' => $product->slug,
            'name' => $product->name,
            'description' => $product->description,
            'images' => $product->images->map(fn (ProductImage $image) => $this->photo($image)),
            'has_variants' => $product->variants_count > 1 || filled($first->name)
                || ! empty($first->attributes) || $first->images->isNotEmpty(),
            'variants' => $product->variants->map(fn (Variant $variant) => [
                'id' => $variant->id,
                'label' => $variant->name ?: (implode(' / ', $variant->attributes ?? []) ?: $variant->sku),
                'price_cents' => (int) $variant->price_cents,
                'in_stock' => $variant->stock > 0,
                'images' => $variant->images->map(fn (ProductImage $image) => $this->photo($image)),
            ]),
        ]]);
    }

    private function published(): Builder
    {
        return Product::query()->where('status', 'active')
            ->whereHas('variants', fn ($variants) => $variants->where('active', true))
            ->with(['images', 'variants' => fn ($variants) => $variants->where('active', true)->with([
                'images' => fn ($images) => $images->reorder('primary', 'desc')->orderBy('position')->orderBy('id'),
            ])]);
    }

    private function photo(?ProductImage $image): ?array
    {
        return $image ? ['id' => $image->id, 'url' => $image->withUrl()['url']] : null;
    }
}
