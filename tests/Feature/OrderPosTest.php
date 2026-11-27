<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']), 'api');
    $this->category = Category::create(['name' => 'POS category', 'active' => true]);
    $this->product = Product::create(['name' => 'Car diffuser', 'slug' => 'pos-diffuser', 'status' => 'active']);
    $this->product->categories()->attach($this->category);
    $this->variant = $this->product->variants()->create([
        'sku' => 'POS-GREEN', 'name' => 'Forest green', 'barcode' => '8901234567890',
        'attributes' => ['color' => 'green'], 'combination' => hash('sha256', 'pos-green'),
        'price_cents' => 15000, 'stock' => 4, 'active' => true,
    ]);
});

it('returns POS product cards with stock, variant details and preferred images', function () {
    $this->product->images()->create(['path' => 'products/pos-product.png', 'primary' => true]);
    $this->getJson('/api/admin/orders/variants')->assertOk()->assertJsonPath('data.0.name', 'Forest green')
        ->assertJsonPath('data.0.product_name', 'Car diffuser')->assertJsonPath('data.0.attributes.color', 'green')
        ->assertJsonPath('data.0.stock', 4)->assertJsonPath('data.0.price_cents', 15000)
        ->assertJsonPath('data.0.image_url', Storage::disk('public')->url('products/pos-product.png'));
    $this->variant->images()->create(['product_id' => $this->product->id, 'path' => 'products/pos-variant.png', 'primary' => true]);
    $this->getJson('/api/admin/orders/variants')->assertJsonPath('data.0.image_url', Storage::disk('public')->url('products/pos-variant.png'));
});

it('searches products by name, variant name, SKU and barcode', function (string $search) {
    $this->getJson('/api/admin/orders/variants?search='.rawurlencode($search))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->variant->id);
})->with(['diffuser', 'Forest', 'POS-GREEN', '8901234567890']);

it('keeps out of stock products visible but excludes inactive and unpublished catalog products', function () {
    $this->variant->update(['stock' => 0]);
    $this->getJson('/api/admin/orders/variants')->assertJsonPath('data.0.stock', 0);
    $this->category->update(['active' => false]);
    $this->getJson('/api/admin/orders/variants')->assertJsonCount(0, 'data');
    $this->category->update(['active' => true]);
    $this->product->update(['status' => 'draft']);
    $this->getJson('/api/admin/orders/variants')->assertJsonCount(0, 'data');
    $this->product->update(['status' => 'active']);
    $this->variant->update(['active' => false]);
    $this->getJson('/api/admin/orders/variants')->assertJsonCount(0, 'data');
});

it('paginates the POS catalog rather than limiting product selection to the first page', function () {
    for ($index = 1; $index <= 30; $index++) {
        $this->product->variants()->create([
            'sku' => 'POS-'.$index, 'attributes' => [], 'combination' => hash('sha256', 'pos-'.$index),
            'price_cents' => 10000, 'stock' => 2, 'active' => true,
        ]);
    }
    $this->getJson('/api/admin/orders/variants')->assertOk()->assertJsonCount(30, 'data')
        ->assertJsonPath('total', 31)->assertJsonPath('last_page', 2);
    $this->getJson('/api/admin/orders/variants?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('current_page', 2);
});

it('creates POS orders with server prices and preserves duplicate submission protection', function () {
    $data = [
        'submission_key' => (string) Str::uuid(), 'customer_name' => 'POS customer',
        'phone' => '01700000000', 'email' => '', 'address' => 'Dhaka', 'delivery_cents' => 6000,
        'items' => [['variant_id' => $this->variant->id, 'quantity' => 2, 'price_cents' => 1]],
    ];
    $this->postJson('/api/admin/orders', $data)->assertCreated()->assertJsonPath('total_cents', 36000);
    $this->postJson('/api/admin/orders', $data)->assertSuccessful();
    expect(Order::count())->toBe(1)->and($this->variant->fresh()->stock)->toBe(2);
});
