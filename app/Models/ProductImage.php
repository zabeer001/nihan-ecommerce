<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    public const SEED_FALLBACK_PREFIX = 'seed-fallbacks/';

    protected $guarded = [];

    protected $casts = ['primary' => 'boolean'];

    public function isSeedFallback(): bool
    {
        return str_starts_with($this->path, self::SEED_FALLBACK_PREFIX);
    }

    // The caller holds the product lock; shared fallback files must never be deleted.
    public static function removeSeedFallbacks(int $productId): void
    {
        static::where('product_id', $productId)->where('path', 'like', self::SEED_FALLBACK_PREFIX.'%')->delete();
        $shared = static::where('product_id', $productId)->whereNull('variant_id');
        if (! (clone $shared)->where('primary', true)->exists()) {
            $shared->orderBy('position')->orderBy('id')->first()?->update(['primary' => true]);
        }
    }

    public function withUrl(): array
    {
        return $this->toArray() + ['url' => Storage::disk('public')->url($this->path)];
    }
}
