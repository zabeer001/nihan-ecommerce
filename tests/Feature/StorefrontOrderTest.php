<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    config(['storefront.delivery_cents' => 8000]);
    $product = Product::create(['name' => 'Refill', 'slug' => 'refill', 'status' => 'active']);
    $this->category = Category::create(['name' => 'Fragrance', 'active' => true]);
    $product->categories()->attach($this->category);
    $this->variant = $product->variants()->create([
        'sku' => 'REFILL', 'attributes' => [], 'combination' => hash('sha256', 'refill'),
        'price_cents' => 15000, 'stock' => 2, 'active' => true,
    ]);
    $this->items = [['variant_id' => $this->variant->id, 'quantity' => 2]];
    $this->payload = [
        'items' => [$this->items[0] + ['price_cents' => 15000]],
        'delivery_cents' => 8000,
        'submission_key' => (string) Str::uuid(),
        'tracking_secret' => bin2hex(random_bytes(32)),
        'customer_name' => 'Guest shopper',
        'phone' => '01700000000',
        'email' => 'guest@example.test',
        'address' => 'Dhaka, Bangladesh',
    ];
});

it('quotes, places and recovers a COD order and shares staff status without exposing contact data', function () {
    $this->postJson('/api/storefront/quote', ['items' => $this->items])->assertOk()
        ->assertJsonPath('items.0.name', 'Refill')->assertJsonPath('total_cents', 38000);
    $receipt = $this->postJson('/api/storefront/orders', $this->payload)->assertOk()
        ->assertJsonPath('status', 'Pending')->assertJsonPath('total_cents', 38000)
        ->assertJsonMissingPath('customer_name')->assertJsonMissingPath('tracking_secret')->json();
    $reference = $receipt['reference'];
    expect($this->variant->fresh()->stock)->toBe(0);
    expect(Order::findOrFail($reference)->tracking_hash)->toBe(hash('sha256', $this->payload['tracking_secret']));
    config(['storefront.delivery_cents' => null]);
    $this->postJson('/api/storefront/orders', $this->payload)->assertOk()->assertExactJson($receipt);
    $this->postJson('/api/storefront/orders', [...$this->payload, 'address' => 'Chattogram'])->assertUnprocessable();
    $this->postJson('/api/storefront/tracking', ['tracking_secret' => str_repeat('f', 64)])->assertNotFound();
    $this->postJson('/api/storefront/tracking', ['tracking_secret' => $this->payload['tracking_secret']])
        ->assertOk()->assertExactJson($receipt);
    $this->getJson('/api/admin/orders')->assertUnauthorized();
    $staff = User::factory()->create(['role' => 'admin']);
    $this->actingAs($staff, 'api');
    $this->getJson("/api/admin/orders?source=storefront&status=Pending&reference=$reference")
        ->assertOk()->assertJsonPath('data.0.id', $reference)->assertJsonMissingPath('data.0.tracking_hash');
    $this->getJson('/api/admin/orders?source=staff')->assertOk()->assertJsonCount(0, 'data');
    $this->patchJson("/api/admin/orders/$reference/status", ['status' => 'Confirmed'])->assertOk();
    $this->postJson('/api/storefront/tracking', ['tracking_secret' => $this->payload['tracking_secret']])
        ->assertJsonPath('history.1.status', 'Confirmed')->assertJsonMissingPath('history.1.user_id');
    $this->patchJson("/api/admin/orders/$reference/status", ['status' => 'Cancelled'])->assertOk();
    $this->patchJson("/api/admin/orders/$reference/status", ['status' => 'Cancelled'])->assertOk();
    expect($this->variant->fresh()->stock)->toBe(2);
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseCount('customers', 1);
    $this->assertDatabaseHas('customers', ['name' => 'Guest shopper', 'phone' => '01700000000', 'email' => 'guest@example.test', 'address' => 'Dhaka, Bangladesh']);
    expect(Order::findOrFail($reference)->customer_id)->toBe(Customer::firstOrFail()->id);
    $this->assertDatabaseHas('order_status_events', ['order_id' => $reference, 'status' => 'Pending', 'user_id' => null]);
    $this->assertDatabaseHas('order_status_events', ['order_id' => $reference, 'status' => 'Cancelled', 'user_id' => $staff->id]);
});

it('quotes the SKU, variant attributes and storefront image for existing cart items', function () {
    $product = $this->variant->product;
    $this->variant->update(['attributes' => ['size' => '50 ml']]);
    $productImage = $product->images()->create(['path' => 'products/refill.webp', 'primary' => true]);

    $this->postJson('/api/storefront/quote', ['items' => $this->items])->assertOk()
        ->assertJsonPath('items.0.sku', 'REFILL')
        ->assertJsonPath('items.0.attributes.size', '50 ml')
        ->assertJsonPath('items.0.image_url', $productImage->withUrl()['url'])
        ->assertJsonPath('items.0.price_cents', 15000)
        ->assertJsonPath('items.0.total_cents', 30000);

    $variantImage = $this->variant->images()->create([
        'product_id' => $product->id, 'path' => 'products/refill-50.webp', 'primary' => true,
    ]);
    $this->postJson('/api/storefront/quote', ['items' => $this->items])->assertOk()
        ->assertJsonPath('items.0.image_url', $variantImage->withUrl()['url']);
});

it('rejects invalid details and preserves inventory on stale quotes or stock', function () {
    $this->postJson('/api/storefront/orders', [...$this->payload, 'phone' => 'invalid', 'address' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors(['phone', 'address']);
    $this->variant->update(['price_cents' => 16000]);
    config(['storefront.delivery_cents' => '0']);
    $this->postJson('/api/storefront/orders', $this->payload)->assertConflict()
        ->assertJsonPath('quote.items.0.price_cents', 16000)->assertJsonPath('quote.total_cents', 32000);
    $this->variant->update(['stock' => 1]);
    $this->postJson('/api/storefront/orders', $this->payload)->assertConflict()
        ->assertJsonPath('quote.items.0.issue', 'insufficient_stock');
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseCount('stock_movements', 0);
    $this->assertDatabaseCount('customers', 0);
});

it('aligns publication with sellability and requires a configured fee', function () {
    $this->category->update(['active' => false]);
    $this->getJson('/api/storefront/products')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/storefront/products/refill')->assertNotFound();
    $this->postJson('/api/storefront/quote', ['items' => $this->items])->assertOk()
        ->assertJsonPath('items.0.issue', 'unavailable');
    config(['storefront.delivery_cents' => null]);
    $this->postJson('/api/storefront/quote', ['items' => $this->items])->assertServiceUnavailable();
    $this->postJson('/api/storefront/orders', $this->payload)->assertServiceUnavailable();
    $this->assertDatabaseCount('orders', 0);
});

it('reuses a customer across phone formats and preserves each order contact snapshot', function () {
    $this->variant->update(['stock' => 4]);
    $first = $this->postJson('/api/storefront/orders', $this->payload)->assertOk()->json('reference');
    $second = $this->postJson('/api/storefront/orders', [
        ...$this->payload, 'phone' => '+8801700000000', 'customer_name' => 'New delivery name',
        'address' => 'Chattogram, Bangladesh', 'email' => 'new@example.test',
        'submission_key' => (string) Str::uuid(), 'tracking_secret' => bin2hex(random_bytes(32)),
    ])->assertOk()->json('reference');
    $this->assertDatabaseCount('customers', 1);
    expect(Order::findOrFail($first)->customer_id)->toBe(Order::findOrFail($second)->customer_id)
        ->and(Order::findOrFail($first)->address)->toBe('Dhaka, Bangladesh')
        ->and(Order::findOrFail($second)->address)->toBe('Chattogram, Bangladesh')
        ->and(Customer::firstOrFail()->name)->toBe('Guest shopper');
});

it('links a manually saved customer without overwriting their details', function () {
    $customer = Customer::create(['name' => 'Saved customer', 'phone' => '+880 (1700) 000000', 'email' => 'saved@example.test', 'address' => 'Saved address']);
    $reference = $this->postJson('/api/storefront/orders', $this->payload)->assertOk()->json('reference');
    expect(Order::findOrFail($reference)->customer_id)->toBe($customer->id)
        ->and($customer->fresh()->name)->toBe('Saved customer')
        ->and($customer->fresh()->address)->toBe('Saved address');
    $this->assertDatabaseCount('customers', 1);
    $customer->delete();
    expect(Order::findOrFail($reference)->customer_id)->toBeNull()
        ->and(Order::findOrFail($reference)->customer_name)->toBe('Guest shopper');
});

it('saves customer details for staff-created orders too', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $payload = $this->payload;
    unset($payload['tracking_secret'], $payload['items'][0]['price_cents']);
    $this->actingAs($user, 'api')->postJson('/api/admin/orders', $payload)->assertSuccessful();
    $order = Order::firstOrFail();
    expect($order->source)->toBe('staff')
        ->and($order->customer_id)->toBe(Customer::firstOrFail()->id);
});

it('includes active variant IDs prices and stock in product cards for quick add to cart', function () {
    $this->variant->update(['stock' => 0]);
    $available = $this->variant->product->variants()->create([
        'sku' => 'REFILL-LARGE', 'name' => 'Large', 'attributes' => ['size' => '100 ml'],
        'combination' => hash('sha256', 'large'), 'price_cents' => 20000, 'stock' => 3, 'active' => true,
    ]);
    $this->variant->product->variants()->create([
        'sku' => 'REFILL-HIDDEN', 'attributes' => [], 'combination' => hash('sha256', 'hidden'),
        'price_cents' => 100, 'stock' => 3, 'active' => false,
    ]);
    $this->getJson('/api/storefront/products')->assertOk()->assertJsonCount(1, 'data')
        ->assertJsonCount(2, 'data.0.variants')->assertJsonPath('data.0.variants.0.id', $this->variant->id)
        ->assertJsonPath('data.0.variants.0.in_stock', false)
        ->assertJsonPath('data.0.variants.1.id', $available->id)
        ->assertJsonPath('data.0.variants.1.label', 'Large')
        ->assertJsonPath('data.0.variants.1.price_cents', 20000)
        ->assertJsonPath('data.0.variants.1.in_stock', true);
});
