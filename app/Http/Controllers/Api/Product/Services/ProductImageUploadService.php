<?php

namespace App\Http\Controllers\Api\Product\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductImageUploadService
{
    public const IMAGE_RULE = 'required|image|mimes:jpg,jpeg,png,webp|max:5120';

    public function handle(Request $request, ?int $productId = null): JsonResponse
    {
        $request->validate(['image' => self::IMAGE_RULE]);
        $id = (string) Str::uuid();
        $expires = now()->addDay();
        $path = $request->file('image')->store('products', 'public');
        abort_if($path === false, 500, 'Could not store the image.');

        try {
            DB::table('product_image_uploads')->insert([
                'id' => $id,
                'user_id' => $request->user()->id,
                'product_id' => $productId,
                'path' => $path,
                'expires_at' => $expires,
            ]);
        } catch (Throwable $exception) {
            rescue(fn () => Storage::disk('public')->delete($path));
            throw $exception;
        }

        return response()->json(['upload_id' => $id, 'expires_at' => $expires->toIso8601String()], 201);
    }

    public function prune(): void
    {
        DB::table('product_image_uploads')->where('expires_at', '<=', now())->select('id')
            ->chunkById(100, function ($uploads) {
                foreach ($uploads as $upload) {
                    DB::transaction(function () use ($upload) {
                        $query = DB::table('product_image_uploads')->where('id', $upload->id);
                        $expired = $query->lockForUpdate()->first();
                        if ($expired && Storage::disk('public')->delete($expired->path)) {
                            $query->delete();
                        }
                    });
                }
            });
    }
}
