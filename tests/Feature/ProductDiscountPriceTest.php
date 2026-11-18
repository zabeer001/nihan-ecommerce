<?php

use App\Http\Controllers\Api\Product\Services\VariantSaveService;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->product = Product::create(['name' => 'Discounted diffuser', 'slug' => 'discounted-diffuser', 'status' => 'active']);
    $category = Category::create(['name' => 'Diffusers', 'active' => true]);
    $this->product->categories()->attach($category);
    $this->variant = $this->product->variants()->create([
        'sku' => 'DISCOUNT-TEST', 'attributes' => [], 'combination' => hash('sha256', json_encode([])),
        'price_cents' => 10000, 'compare_at_price_cents' => 15000, 'stock' => 5, 'active' => true,
    ]);
    $this->variantData = ['sku' => 'DISCOUNT-TEST', 'attributes' => [], 'price_cents' => 10000, 'active' => true];
});

it('pairs the original price with the cheapest active variant on cards and exposes variant prices', function () {
    $this->product->variants()->create([
        'sku' => 'EXPENSIVE-TEST', 'attributes' => ['size' => 'large'], 'combination' => hash('sha256', 'large'),
        'price_cents' => 20000, 'compare_at_price_cents' => 90000, 'stock' => 2, 'active' => true,
    ]);
    $this->getJson('/api/storefront/products')->assertOk()
        ->assertJsonPath('data.0.price_cents', 10000)->assertJsonPath('data.0.compare_at_price_cents', 15000);
    $this->getJson('/api/storefront/products/discounted-diffuser')->assertOk()
        ->assertJsonPath('data.variants.0.compare_at_price_cents', 15000)
        ->assertJsonPath('data.variants.1.compare_at_price_cents', 90000);
});

it('saves and clears the original price without changing the selling price', function () {
    $service = app(VariantSaveService::class);
    $service->save([...$this->variantData, 'compare_at_price_cents' => 18000], $this->product->id, $this->variant->id, 1);
    expect($this->variant->fresh()->compare_at_price_cents)->toBe(18000);
    $service->save($this->variantData, $this->product->id, $this->variant->id, 1);
    expect($this->variant->fresh()->compare_at_price_cents)->toBe(18000);
    $service->save([...$this->variantData, 'compare_at_price_cents' => null], $this->product->id, $this->variant->id, 1);
    expect($this->variant->fresh()->compare_at_price_cents)->toBeNull()
        ->and($this->variant->fresh()->price_cents)->toBe(10000);
});

it('rejects original prices that are not above the selling price', function (int $original) {
    try {
        app(VariantSaveService::class)->save([...$this->variantData, 'compare_at_price_cents' => $original], $this->product->id, $this->variant->id, 1);
        $this->fail('Invalid original price was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('compare_at_price_cents');
    }
    expect($this->variant->fresh()->compare_at_price_cents)->toBe(15000);
})->with([10000, 9000]);

it('continues charging the retail price in cart quotes', function () {
    config(['storefront.delivery_cents' => 8000]);
    $this->postJson('/api/storefront/quote', ['items' => [['variant_id' => $this->variant->id, 'quantity' => 2]]])
        ->assertOk()->assertJsonPath('total_cents', 28000);
});
