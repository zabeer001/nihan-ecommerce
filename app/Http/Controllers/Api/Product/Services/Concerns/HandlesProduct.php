<?php

namespace App\Http\Controllers\Api\Product\Services\Concerns;

use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

trait HandlesProduct
{
    protected function saveProduct(Request $request, ?Product $existingProduct = null): Product
    {
        $id = $existingProduct?->id;
        $validated = $request->validate($this->rules($id));

        return DB::transaction(function () use ($validated, $request, $id) {
            $product = $id ? Product::whereKey($id)->lockForUpdate()->firstOrFail() : new Product;
            $product->fill(collect($validated)->except(['sku', 'price_cents', 'opening_stock', 'category_ids', 'variants'])->all())->save();

            if (! $product->product_code) {
                $product->update(['product_code' => 'PRD-'.Str::upper(Str::random(12))]);
            }

            $product->categories()->sync($validated['category_ids']);

            if (isset($validated['variants'])) {
                foreach ($validated['variants'] as $index => $variantData) {
                    try {
                        $this->variantService->save($variantData, $product->id, $variantData['id'] ?? null, $request->user()->id);
                    } catch (ValidationException $exception) {
                        $errors = [];
                        foreach ($exception->errors() as $field => $messages) {
                            $errors["variants.$index.$field"] = $messages;
                        }
                        throw ValidationException::withMessages($errors);
                    }
                }
            }

            // A product without options still needs one sellable SKU for inventory and orders.
            if (! Variant::where('product_id', $product->id)->exists()) {
                $variant = Variant::create([
                    'product_id' => $product->id,
                    'sku' => $validated['sku'] ?? 'PRD-'.Str::upper(Str::random(12)),
                    'attributes' => [],
                    'combination' => hash('sha256', json_encode([])),
                    'price_cents' => $validated['price_cents'] ?? 0,
                    'stock' => 0,
                    'active' => true,
                ]);
                $this->stockMovements->record(
                    $variant,
                    $validated['opening_stock'] ?? 0,
                    'Opening stock',
                    $request->user()->id,
                );
            }

            return $product->load(['categories', 'variants']);
        });
    }

    protected function rules(?int $id = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('products')->ignore($id)],
            'product_code' => ['nullable', 'string', 'max:255', Rule::unique('products')->ignore($id)],
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            'product_type' => 'nullable|in:physical',
            'tracking_type' => 'nullable|in:quantity',
            'variants' => 'sometimes|array|min:1|max:100',
            'variants.*.id' => 'nullable|integer|distinct',
            'variants.*.sku' => 'required|string|max:255|distinct',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.attributes' => 'present|array|max:10',
            'variants.*.attributes.*' => 'required|string|max:100',
            'variants.*.price_cents' => 'required|integer|min:0|max:100000000',
            'variants.*.purchase_price_cents' => 'nullable|integer|min:0|max:100000000',
            'variants.*.dealer_price_cents' => 'nullable|integer|min:0|max:100000000',
            'variants.*.corporate_price_cents' => 'nullable|integer|min:0|max:100000000',
            'variants.*.active' => 'required|boolean',
            'description' => 'nullable|string',
            'category_ids' => 'required|array|min:1',
            'category_ids.*' => 'required|integer|distinct|exists:categories,id',
            'status' => 'required|in:active,draft',
            'sku' => ['nullable', 'string', 'max:255', Rule::unique('variants', 'sku')],
            'price_cents' => 'sometimes|integer|min:0|max:100000000',
            'opening_stock' => 'sometimes|integer|min:0|max:1000000',
        ];
    }
}
