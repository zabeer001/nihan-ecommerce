<?php

namespace App\Http\Controllers\Api\Product\Services;

use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VariantSaveService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    public function handle(Request $request, int $product, ?int $id = null): Variant
    {
        return $this->save($request->all(), $product, $id, $request->user()->id);
    }

    public function save(array $data, int $product, ?int $id, int $userId): Variant
    {
        Product::findOrFail($product);
        $validated = validator($data, [
            'sku' => ['required', 'string', 'max:255', Rule::unique('variants')->ignore($id)],
            'attributes' => 'present|array|max:10',
            'attributes.*' => 'required|string|max:100',
            'price_cents' => 'required|integer|min:0|max:100000000',
            'name' => 'nullable|string|max:255',
            'barcode' => 'nullable|string|max:255',
            'purchase_price_cents' => 'nullable|integer|min:0|max:100000000',
            'dealer_price_cents' => 'nullable|integer|min:0|max:100000000',
            'corporate_price_cents' => 'nullable|integer|min:0|max:100000000',
            'active' => 'required|boolean',
            'opening_stock' => 'sometimes|integer|min:0|max:1000000',
        ])->validate();
        $attributes = [];
        foreach ($validated['attributes'] as $key => $value) {
            $key = mb_strtolower(trim((string) $key));
            if ($key === '' || strlen($key) > 100 || isset($attributes[$key])) {
                throw ValidationException::withMessages(['attributes' => 'Attribute names must be unique and nonempty.']);
            }
            $attributes[$key] = trim($value);
        }
        ksort($attributes);
        $validated['attributes'] = $attributes;
        $validated['combination'] = hash('sha256', json_encode(array_map(fn ($value) => mb_strtolower($value), $attributes)));
        try {
            return DB::transaction(function () use ($validated, $product, $id, $userId) {
                // Serialize edits to the same product before checking combinations.
                Product::whereKey($product)->lockForUpdate()->firstOrFail();
                if (Variant::where('product_id', $product)->where('combination', $validated['combination'])->when($id, fn ($query) => $query->where('id', '!=', $id))->exists()) {
                    throw ValidationException::withMessages(['attributes' => 'This combination already exists.']);
                }

                $variant = $id ? Variant::where('product_id', $product)->whereKey($id)->lockForUpdate()->firstOrFail() : new Variant(['product_id' => $product]);
                $openingStock = $validated['opening_stock'] ?? 0;
                unset($validated['opening_stock']);
                $variant->fill($validated)->save();
                if (! $id) {
                    $this->stockMovements->record($variant, $openingStock, 'Opening stock', $userId);
                }

                return $variant;
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages([
                'sku' => 'This SKU or attribute combination is already in use.',
            ]);
        }
    }
}
