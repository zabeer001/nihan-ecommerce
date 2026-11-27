<?php

use App\Http\Controllers\Api\Orders\Services\OrderWorkflowService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $product = Product::create(['name' => 'Refill', 'slug' => 'order-edit-refill', 'status' => 'active']);
    $product->categories()->attach(Category::create(['name' => 'Fragrance', 'active' => true]));
    $this->variant = $product->variants()->create([
        'sku' => 'EDIT-REFILL', 'attributes' => [], 'combination' => hash('sha256', 'edit-refill'),
        'price_cents' => 15000, 'stock' => 10, 'active' => true,
    ]);
    $this->payload = [
        'submission_key' => (string) Str::uuid(), 'customer_name' => 'Original customer',
        'phone' => '01700000000', 'email' => 'original@example.com', 'address' => 'Original address',
        'delivery_cents' => 8000, 'items' => [['variant_id' => $this->variant->id, 'quantity' => 2]],
    ];
    $this->order = app(OrderWorkflowService::class)->create($this->payload, $this->admin->id);
    $this->contact = ['customer_name' => 'Order recipient', 'phone' => '01900000000', 'email' => 'recipient@example.com', 'address' => 'New delivery address'];
    $this->actingAs($this->admin, 'api');
});

it('syncs order contact edits to its customer without changing another order, stock or totals', function () {
    $other = app(OrderWorkflowService::class)->create([
        ...$this->payload, 'submission_key' => (string) Str::uuid(),
    ], $this->admin->id);
    $customer = Customer::findOrFail($this->order->customer_id);
    $before = $this->order->fresh()->getAttributes();
    $otherBefore = $other->fresh()->getAttributes();
    $items = OrderItem::where('order_id', $this->order->id)->get()->toArray();
    $stock = $this->variant->fresh()->stock;
    $history = DB::table('order_status_events')->where('order_id', $this->order->id)->get()->toArray();
    $movements = DB::table('stock_movements')->count();

    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'status' => 'Cancelled', 'total_cents' => 1,
        'delivery_cents' => 0, 'customer_id' => null, 'tracking_hash' => 'changed', 'source' => 'storefront',
    ])->assertOk()->assertJsonPath('address', 'New delivery address');

    $after = $this->order->fresh()->getAttributes();
    foreach ($before as $key => $value) {
        if (! in_array($key, ['customer_name', 'phone', 'email', 'address', 'updated_at'])) expect($after[$key])->toBe($value);
    }
    expect($customer->fresh()->only(['name', 'phone', 'email', 'address']))->toBe([
        'name' => $this->contact['customer_name'], 'phone' => $this->contact['phone'],
        'email' => $this->contact['email'], 'address' => $this->contact['address'],
    ])
        ->and($customer->fresh()->normalized_phone)->toBe(Customer::normalizePhone($this->contact['phone']))
        ->and($other->fresh()->getAttributes())->toBe($otherBefore)
        ->and(OrderItem::where('order_id', $this->order->id)->get()->toArray())->toBe($items)
        ->and($this->variant->fresh()->stock)->toBe($stock)
        ->and(DB::table('stock_movements')->count())->toBe($movements)
        ->and(DB::table('order_status_events')->where('order_id', $this->order->id)->get()->toArray())->toEqual($history);
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertOk()
        ->assertJsonPath('order.address', $this->contact['address'])->assertJsonPath('editable', true)
        ->assertJsonPath('allowed_statuses', ['Confirmed', 'Cancelled']);
});

it('allows customer and address corrections at every status without changing that status', function (string $status) {
    $this->order->update(['status' => $status]);
    $this->putJson('/api/admin/orders/'.$this->order->id, $this->contact)->assertOk()->assertJsonPath('status', $status);
    expect($this->order->fresh()->address)->toBe($this->contact['address']);
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertJsonPath('editable', true);
})->with(['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered', 'Cancelled']);

it('preserves a newer status when saving customer corrections from a stale editor', function () {
    $id = $this->order->id;
    $this->getJson('/api/admin/orders/'.$id)->assertJsonPath('editable', true);
    foreach (['Confirmed', 'Processing', 'Shipped'] as $status) {
        $this->patchJson('/api/admin/orders/'.$id.'/status', ['status' => $status])->assertOk();
    }
    $this->putJson('/api/admin/orders/'.$id, $this->contact)->assertOk();
    expect($this->order->fresh()->status)->toBe('Shipped')
        ->and($this->order->fresh()->address)->toBe($this->contact['address']);
});

it('validates contact fields and allows clearing email', function () {
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        'customer_name' => '', 'phone' => str_repeat('1', 41), 'email' => 'invalid', 'address' => '',
    ])->assertUnprocessable()->assertJsonValidationErrors(['customer_name', 'phone', 'email', 'address']);
    $this->putJson('/api/admin/orders/'.$this->order->id, [...$this->contact, 'email' => null])
        ->assertOk()->assertJsonPath('email', null);
});

it('requires update permission and returns not found for a missing order', function () {
    $role = Role::create(['name' => 'order-read-only', 'guard_name' => 'api']);
    $role->givePermissionTo('orders.view');
    $viewer = User::factory()->create(['role' => $role->name]);
    $this->actingAs($viewer, 'api')->getJson('/api/admin/orders/'.$this->order->id)->assertOk();
    $this->putJson('/api/admin/orders/'.$this->order->id, $this->contact)->assertForbidden();
    $this->patchJson('/api/admin/orders/'.$this->order->id.'/status', ['status' => 'Confirmed'])->assertForbidden();
    $this->actingAs($this->admin, 'api')->putJson('/api/admin/orders/999999', $this->contact)->assertNotFound();
    auth('api')->forgetUser();
    $this->putJson('/api/admin/orders/'.$this->order->id, $this->contact)->assertUnauthorized();
});

it('preserves status transitions, history and exactly-once cancellation stock restoration', function () {
    $id = $this->order->id;
    $this->patchJson('/api/admin/orders/'.$id.'/status', ['status' => 'Delivered'])->assertUnprocessable();
    $this->patchJson('/api/admin/orders/'.$id.'/status', ['status' => 'Confirmed'])->assertOk();
    $this->getJson('/api/admin/orders/'.$id)->assertJsonPath('allowed_statuses', ['Processing', 'Cancelled']);
    $this->patchJson('/api/admin/orders/'.$id.'/status', ['status' => 'Cancelled'])->assertOk();
    $this->patchJson('/api/admin/orders/'.$id.'/status', ['status' => 'Cancelled'])->assertOk();
    $this->getJson('/api/admin/orders/'.$id)->assertJsonPath('allowed_statuses', [])->assertJsonCount(3, 'history');
    expect($this->variant->fresh()->stock)->toBe(10);
});

it('renders each dedicated order page at its own URL', function (string $mode) {
    $url = $mode === 'create' ? '/dashboard/orders/create' : '/dashboard/orders/'.$this->order->id.'/'.$mode;
    $this->get($url)->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('backend/pages/orders/OrderPage', false)->where('mode', $mode));
})->with(['create', 'edit', 'invoice']);


it('retains order list filtering and pagination', function () {
    $id = $this->order->id;
    $this->getJson('/api/admin/orders?reference='.$id.'&source=staff&status=Pending&page=1')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id)->assertJsonPath('current_page', 1);
    $this->getJson('/api/admin/orders?source=storefront')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/admin/orders?status=Shipped')->assertOk()->assertJsonCount(0, 'data');
});

it('keeps creation available to create-only staff without granting invoice access', function () {
    $role = Role::create(['name' => 'order-creator-only', 'guard_name' => 'api']);
    $role->givePermissionTo('orders.create');
    $creator = User::factory()->create(['role' => $role->name]);
    $this->actingAs($creator, 'api');
    $this->postJson('/api/admin/orders', [...$this->payload, 'submission_key' => (string) Str::uuid()])
        ->assertCreated()->assertJsonPath('customer_name', 'Original customer');
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertForbidden();
    $this->putJson('/api/admin/orders/'.$this->order->id, $this->contact)->assertForbidden();
});


it('redirects old status page links to the orders list', function () {
    $this->get('/dashboard/orders/'.$this->order->id.'/status')->assertRedirect('/dashboard/orders');
});

it('includes allowed status dropdown options in the orders list', function () {
    $this->getJson('/api/admin/orders')->assertOk()->assertJsonPath('data.0.allowed_statuses', ['Confirmed', 'Cancelled']);
    $this->order->update(['status' => 'Cancelled']);
    $this->getJson('/api/admin/orders')->assertJsonPath('data.0.allowed_statuses', []);
});

it('creates a linked customer when an order no longer has one', function () {
    $this->order->customer()->firstOrFail()->delete();
    $this->putJson('/api/admin/orders/'.$this->order->id, $this->contact)->assertOk();
    $customer = $this->order->fresh()->customer;
    expect($customer)->not->toBeNull();
    expect($customer->name)->toBe($this->contact['customer_name'])->and($customer->address)->toBe($this->contact['address']);
});

it('does not update the customer when order contact validation fails', function () {
    $before = $this->order->customer->getAttributes();
    $this->putJson('/api/admin/orders/'.$this->order->id, [...$this->contact, 'address' => ''])->assertUnprocessable();
    expect($this->order->customer->fresh()->getAttributes())->toBe($before);
});

it('updates quantities and totals at the original unit price and adjusts stock once', function (string $status) {
    $this->order->update(['status' => $status]);
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $this->variant->update(['price_cents' => 20000]);
    $payload = [...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 3, 'price_cents' => 1]], 'total_cents' => 1];
    $this->putJson('/api/admin/orders/'.$this->order->id, $payload)->assertOk()
        ->assertJsonPath('subtotal_cents', 45000)->assertJsonPath('total_cents', 53000)
        ->assertJsonPath('delivery_cents', 8000)->assertJsonPath('status', $status);
    expect($this->variant->fresh()->stock)->toBe(7)
        ->and($line->fresh()->price_cents)->toBe(15000)->and($line->fresh()->quantity)->toBe(3);
    $this->putJson('/api/admin/orders/'.$this->order->id, $payload)->assertOk();
    expect($this->variant->fresh()->stock)->toBe(7);
    $this->assertDatabaseCount('stock_movements', 2);
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertJsonPath('items.0.quantity', 3)->assertJsonPath('items.0.total_cents', 45000);
})->with(['Pending', 'Confirmed', 'Processing', 'Shipped', 'Delivered']);

it('returns stock when reducing quantity and cancellation restores only the new quantity', function () {
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 1]],
    ])->assertOk()->assertJsonPath('total_cents', 23000);
    expect($this->variant->fresh()->stock)->toBe(9);
    $this->patchJson('/api/admin/orders/'.$this->order->id.'/status', ['status' => 'Cancelled'])->assertOk();
    expect($this->variant->fresh()->stock)->toBe(10);
});

it('allows quantity corrections to cancelled orders without touching stock', function () {
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $this->patchJson('/api/admin/orders/'.$this->order->id.'/status', ['status' => 'Cancelled'])->assertOk();
    $movementCount = DB::table('stock_movements')->count();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 50]],
    ])->assertOk()->assertJsonPath('total_cents', 758000)->assertJsonPath('status', 'Cancelled');
    expect($this->variant->fresh()->stock)->toBe(10)->and(DB::table('stock_movements')->count())->toBe($movementCount);
    $this->patchJson('/api/admin/orders/'.$this->order->id.'/status', ['status' => 'Cancelled'])->assertOk();
    expect($this->variant->fresh()->stock)->toBe(10);
});

it('rolls back customer, order and quantity edits when stock is insufficient', function () {
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $customer = $this->order->customer->getAttributes();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 11]],
    ])->assertUnprocessable()->assertJsonValidationErrors('stock');
    expect($line->fresh()->quantity)->toBe(2)->and($this->variant->fresh()->stock)->toBe(8)
        ->and($this->order->fresh()->address)->toBe('Original address')
        ->and($this->order->customer->fresh()->getAttributes())->toBe($customer);
    $this->assertDatabaseCount('stock_movements', 1);
});

it('rejects invalid quantities', function ($quantity) {
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $line->id, 'quantity' => $quantity]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
})->with([0, -1, 1.5, 1000001]);

it('rejects foreign or missing order items instead of adding or removing them', function () {
    $other = app(OrderWorkflowService::class)->create([
        ...$this->payload, 'submission_key' => (string) Str::uuid(),
    ], $this->admin->id);
    $foreign = OrderItem::where('order_id', $other->id)->firstOrFail();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $foreign->id, 'quantity' => 3]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');
    $this->putJson('/api/admin/orders/'.$this->order->id, [...$this->contact, 'items' => []])
        ->assertUnprocessable()->assertJsonValidationErrors('items');
    expect($foreign->fresh()->quantity)->toBe(2);
});

function orderAdditionVariant($existing)
{
    return $existing->product->variants()->create([
        'sku' => 'ADDITIONAL-REFILL', 'attributes' => ['size' => 'large'], 'combination' => hash('sha256', 'additional'),
        'price_cents' => 7000, 'stock' => 5, 'active' => true,
    ]);
}

it('adds products using catalog snapshots and updates invoice totals and reserved stock', function (string $status) {
    $this->order->update(['status' => $status]);
    $new = orderAdditionVariant($this->variant);
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'new_items' => [['variant_id' => $new->id, 'quantity' => 2, 'price_cents' => 1, 'product_name' => 'Forged']],
    ])->assertOk()->assertJsonPath('subtotal_cents', 44000)->assertJsonPath('total_cents', 52000);
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertJsonCount(2, 'items')
        ->assertJsonPath('items.1.sku', $new->sku)->assertJsonPath('items.1.quantity', 2)
        ->assertJsonPath('items.1.price_cents', 7000)->assertJsonPath('items.1.product_name', 'Refill')
        ->assertJsonPath('items.1.attributes.size', 'large');
    expect($new->fresh()->stock)->toBe(3)->and($this->variant->fresh()->stock)->toBe(8);
    $this->patchJson('/api/admin/orders/'.$this->order->id.'/status', ['status' => 'Cancelled'])->assertOk();
    expect($new->fresh()->stock)->toBe(5)->and($this->variant->fresh()->stock)->toBe(10);
})->with(['Pending', 'Confirmed', 'Processing']);

it('rolls back all quantity, customer and product changes when an added product lacks stock', function () {
    $new = orderAdditionVariant($this->variant);
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $customer = $this->order->customer->getAttributes();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 1]],
        'new_items' => [['variant_id' => $new->id, 'quantity' => 6]],
    ])->assertUnprocessable()->assertJsonValidationErrors('stock');
    expect($new->fresh()->stock)->toBe(5)->and($this->variant->fresh()->stock)->toBe(8)
        ->and($line->fresh()->quantity)->toBe(2)->and($this->order->customer->fresh()->getAttributes())->toBe($customer);
    $this->assertDatabaseCount('order_items', 1);
    $this->assertDatabaseCount('stock_movements', 1);
});

it('blocks adding products after shipping, delivery or cancellation without changing order, customer or stock', function (string $status) {
    $new = orderAdditionVariant($this->variant);
    $this->order->update(['status' => $status]);
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $customer = $this->order->customer->getAttributes();
    $count = DB::table('stock_movements')->count();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 1]],
        'new_items' => [['variant_id' => $new->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('new_items');
    expect($new->fresh()->stock)->toBe(5)->and($line->fresh()->quantity)->toBe(2)
        ->and($this->order->fresh()->address)->toBe('Original address')
        ->and($this->order->customer->fresh()->getAttributes())->toBe($customer)
        ->and(DB::table('stock_movements')->count())->toBe($count);
    $this->assertDatabaseCount('order_items', 1);
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertJsonPath('can_add_products', false);
})->with(['Shipped', 'Delivered', 'Cancelled']);

it('rejects unavailable new products', function (string $condition) {
    $new = orderAdditionVariant($this->variant);
    match ($condition) {
        'inactive-variant' => $new->update(['active' => false]),
        'archived-product' => $new->product->update(['status' => 'archived']),
        'inactive-category' => $new->product->categories()->update(['active' => false]),
    };
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'new_items' => [['variant_id' => $new->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('new_items');
    $this->assertDatabaseCount('order_items', 1);
})->with(['inactive-variant', 'archived-product', 'inactive-category']);

it('rejects duplicate additions and does not reserve stock twice on retries', function () {
    $new = orderAdditionVariant($this->variant);
    $line = OrderItem::where('order_id', $this->order->id)->firstOrFail();
    $payload = [...$this->contact, 'items' => [['id' => $line->id, 'quantity' => 2]],
        'new_items' => [['variant_id' => $new->id, 'quantity' => 1]]];
    $this->putJson('/api/admin/orders/'.$this->order->id, $payload)->assertOk();
    $this->putJson('/api/admin/orders/'.$this->order->id, $payload)->assertUnprocessable();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'new_items' => [['variant_id' => $this->variant->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('new_items');
    expect($new->fresh()->stock)->toBe(4);
    $this->assertDatabaseCount('order_items', 2);
});

it('lets order editors find and add products without granting order creation', function () {
    $role = Role::create(['name' => 'order-edit-only', 'guard_name' => 'api']);
    $role->givePermissionTo(['orders.view', 'orders.update']);
    $editor = User::factory()->create(['role' => $role->name]);
    $new = orderAdditionVariant($this->variant);
    $this->actingAs($editor, 'api');
    $this->getJson('/api/admin/orders/variants?search=ADDITIONAL')->assertOk()->assertJsonPath('data.0.id', $new->id);
    $this->postJson('/api/admin/orders', $this->payload)->assertForbidden();
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'new_items' => [['variant_id' => $new->id, 'quantity' => 1]],
    ])->assertOk();
});

it('validates quantities for added products', function ($quantity) {
    $new = orderAdditionVariant($this->variant);
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'new_items' => [['variant_id' => $new->id, 'quantity' => $quantity]],
    ])->assertUnprocessable()->assertJsonValidationErrors('new_items.0.quantity');
})->with([0, 1.5]);


it('blocks product additions if the order ships while the editor is open', function () {
    $new = orderAdditionVariant($this->variant);
    $this->getJson('/api/admin/orders/'.$this->order->id)->assertJsonPath('can_add_products', true);
    foreach (['Confirmed', 'Processing', 'Shipped'] as $status) {
        $this->patchJson('/api/admin/orders/'.$this->order->id.'/status', ['status' => $status])->assertOk();
    }
    $this->putJson('/api/admin/orders/'.$this->order->id, [
        ...$this->contact, 'new_items' => [['variant_id' => $new->id, 'quantity' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('new_items');
    expect($new->fresh()->stock)->toBe(5)->and($this->order->fresh()->status)->toBe('Shipped');
});
