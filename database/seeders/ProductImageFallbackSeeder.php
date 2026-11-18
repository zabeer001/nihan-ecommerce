<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProductImageFallbackSeeder extends Seeder
{
    public function run(): void
    {
        $added = 0;
        Product::select('id')->chunkById(100, function ($products) use (&$added) {
            foreach ($products as $candidate) {
                DB::transaction(function () use ($candidate, &$added) {
                    // Uploads take the same product lock, preventing duplicate fallback rows.
                    $product = Product::whereKey($candidate->id)->lockForUpdate()->first();
                    if (! $product || ProductImage::where('product_id', $product->id)->exists()) return;
                    $file = $this->asset($product);
                    $path = ProductImage::SEED_FALLBACK_PREFIX.$file;
                    $disk = Storage::disk('public');
                    if (! $disk->exists($path)) {
                        $contents = file_get_contents(public_path('product-fallbacks/'.$file));
                        if ($contents === false || ! $disk->put($path, $contents)) {
                            throw new RuntimeException("Could not install fallback image $file.");
                        }
                    }
                    $product->images()->create(['path' => $path, 'primary' => true, 'position' => 0]);
                    $added++;
                });
            }
        });
        $this->command?->info("Added $added missing product image(s) from local fallback assets.");
    }

    private function asset(Product $product): string
    {
        $name = str_replace(['-', '_'], ' ', mb_strtolower($product->name.' '.$product->product_code.' '.$product->model));
        foreach (['starter kit', 'discovery', 'bundle', 'duo'] as $type) {
            if (str_contains($name, $type)) return 'starter-kit.webp';
        }
        foreach (['oud noir' => 'oud-noir', 'padma' => 'padma', 'mango leaf' => 'mango-leaf', 'monsoon' => 'monsoon'] as $scent => $file) {
            if (str_contains($name, $scent)) return $file.'.webp';
        }
        if (str_contains($name, 'diffuser')) {
            return str_contains($name, 'sand') ? 'diffuser-sand.webp' : 'diffuser.webp';
        }
        if (str_contains($name, 'refill') || str_contains($name, 'fragrance')) {
            return $this->choose($product, ['oud-noir.webp', 'padma.webp', 'mango-leaf.webp', 'monsoon.webp']);
        }
        foreach ($product->categories()->orderBy('categories.id')->pluck('name') as $category) {
            $pool = match (mb_strtolower(trim($category))) {
                'fragrance', 'fragrances', 'refill', 'refills' => ['oud-noir.webp', 'padma.webp', 'mango-leaf.webp', 'monsoon.webp'],
                'accessories', 'diffuser', 'diffusers' => ['diffuser.webp', 'diffuser-sand.webp', 'starter-kit.webp'],
                default => [],
            };
            if ($pool) return $this->choose($product, $pool);
        }
        return 'placeholder.svg';
    }

    private function choose(Product $product, array $pool): string
    {
        return $pool[$product->id % count($pool)];
    }
}
