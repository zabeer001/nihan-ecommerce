<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductGalleryService
{
    public static function rules(string $field): array
    {
        return [
            $field => 'sometimes|array:items,removed_ids|required_array_keys:items',
            "$field.items" => 'sometimes|array|list',
            "$field.items.*" => 'array:id,upload_id,primary',
            "$field.items.*.id" => 'integer',
            "$field.items.*.upload_id" => 'uuid',
            "$field.items.*.primary" => 'required|boolean',
            "$field.removed_ids" => 'sometimes|array|list',
            "$field.removed_ids.*" => 'integer',
        ];
    }

    // The caller holds the product lock for the whole save.
    public function sync(Product $product, ?Variant $variant, array $gallery, int $userId, ?int $editTarget, string $field): void
    {
        $items = $gallery['items'];
        $removedIds = $gallery['removed_ids'] ?? [];
        $existing = ProductImage::where('product_id', $product->id)->where('variant_id', $variant?->id)->get()->keyBy('id');
        $retainedIds = array_column($items, 'id');
        $knownIds = array_map('intval', array_merge($retainedIds, $removedIds));
        sort($knownIds);
        $existingIds = $existing->keys()->sort()->values()->all();

        if ($knownIds !== $existingIds) {
            $this->invalid($field, 'The saved photos have changed. Reload the product before editing this gallery.');
        }
        if (count(array_filter(array_column($items, 'primary'))) > 1) {
            $this->invalid($field, 'Choose one featured image per gallery.');
        }

        foreach ($items as $position => $item) {
            if (isset($item['id']) === isset($item['upload_id'])) {
                $this->invalid($field, 'Each photo must reference one saved image or one staged upload.');
            }
            $details = ['position' => $position, 'primary' => $item['primary']];
            if (isset($item['id'])) {
                $existing[$item['id']]->update($details);

                continue;
            }

            $query = DB::table('product_image_uploads')
                ->where('id', $item['upload_id'])
                ->where('user_id', $userId)
                ->where('product_id', $editTarget)
                ->where('expires_at', '>', now());
            $upload = $query->lockForUpdate()->first();
            if (! $upload) {
                $this->invalid($field, 'A photo upload is unavailable. Select the file again and retry.');
            }

            ProductImage::create($details + [
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'path' => $upload->path,
            ]);
            $query->delete();
        }

        foreach ($removedIds as $id) {
            $image = $existing[$id];
            $image->delete();
            DB::afterCommit(fn () => rescue(fn () => Storage::disk('public')->delete($image->path)));
        }
    }

    public function validateActiveVariants(Product $product, array $submittedRows = []): void
    {
        if ($product->status !== 'active') {
            return;
        }

        $variants = $product->variants()->with('images')->get();
        $first = $variants->first();
        if ($variants->count() === 1 && ! $first->name && empty($first->attributes) && $first->images->isEmpty()) {
            return;
        }

        $errors = [];
        foreach ($variants->where('active', true) as $variant) {
            $photos = $variant->images;
            if ($photos->where('primary', true)->count() !== 1 || $photos->where('primary', false)->isEmpty()) {
                $field = isset($submittedRows[$variant->id]) ? "variants.{$submittedRows[$variant->id]}.gallery" : "variants.gallery.{$variant->id}";
                $errors[$field] = "Add a featured image and at least one gallery image for {$variant->sku}.";
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
