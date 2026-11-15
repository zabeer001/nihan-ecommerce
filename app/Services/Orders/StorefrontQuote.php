<?php

namespace App\Services\Orders;

use App\Models\Variant;
use Illuminate\Database\Eloquent\Collection;

class StorefrontQuote
{
    public function deliveryCents(): int
    {
        $fee = filter_var(config('storefront.delivery_cents'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0, 'max_range' => 100000000],
        ]);
        abort_if($fee === false, 503, 'Online checkout is temporarily unavailable.');

        return $fee;
    }

    public function variants(array $items, bool $lock = false): Collection
    {
        return Variant::query()
            ->with(['product' => fn ($query) => $query->withExists([
                'categories as has_active_category' => fn ($categories) => $categories->where('active', true),
            ])])
            ->whereIn('id', array_column($items, 'variant_id'))
            ->orderBy('id')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get()
            ->keyBy('id');
    }

    public function quote(array $items, Collection $variants, int $delivery): array
    {
        $lines = [];
        $subtotal = 0;
        $available = true;

        foreach ($items as $item) {
            $variant = $variants->get($item['variant_id']);
            $product = $variant?->product;
            $eligible = $variant?->active && $product?->status === 'active' && $product->has_active_category;
            $issue = ! $eligible ? 'unavailable' : ($variant->stock < $item['quantity'] ? 'insufficient_stock' : null);
            $price = $eligible ? (int) $variant->price_cents : null;
            $total = $price === null ? null : $price * $item['quantity'];
            $image = $eligible && $variant->relationLoaded('images') && $product->relationLoaded('images')
                ? ($variant->images->first() ?? $product->images->first())
                : null;

            $lines[] = [
                'variant_id' => (int) $item['variant_id'],
                'quantity' => (int) $item['quantity'],
                'name' => $eligible ? $product->name : null,
                'slug' => $eligible ? $product->slug : null,
                'label' => $eligible ? ($variant->name ?: (implode(' / ', $variant->attributes ?? []) ?: $variant->sku)) : null,
                'sku' => $eligible ? $variant->sku : null,
                'attributes' => $eligible ? $variant->attributes : null,
                'image_url' => $image?->withUrl()['url'] ?? null,
                'price_cents' => $price,
                'total_cents' => $total,
                'issue' => $issue,
            ];
            $available = $available && $issue === null;
            $subtotal += $total ?? 0;
        }

        return [
            'items' => $lines,
            'available' => $available,
            'subtotal_cents' => $subtotal,
            'delivery_cents' => $delivery,
            'total_cents' => $subtotal + $delivery,
        ];
    }
}
