<?php

use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Http\Controllers\Api\Orders\Services\OrderWorkflowService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Database\Seeders\OrderSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    config(['rbac.software_engineer_emails' => ['engineer@example.test']]);
    $this->engineer = User::factory()->create(['email' => 'engineer@example.test', 'role' => 'software_engineer']);
    $category = Category::create(['name' => 'Seeder catalog', 'active' => true]);
    $product = Product::create(['name' => 'Demo refill', 'slug' => 'seeder-refill', 'status' => 'active']);
    $product->categories()->attach($category);
    foreach ([10000, 15000, 20000] as $index => $price) {
        $variant = $product->variants()->create([
            'sku' => 'ORDER-SEED-'.$index, 'attributes' => ['size' => (string) $index],
            'combination' => hash('sha256', 'order-seed-'.$index),
            'price_cents' => $price, 'stock' => 0, 'active' => true,
        ]);
        DB::transaction(fn () => app(StockMovementService::class)->record($variant, 100, 'Opening stock', $this->engineer->id));
    }
});

it('seeds 24 orders with customer links, varied items, correct totals, all statuses and balanced stock', function () {
    $this->seed(OrderSeeder::class);
    expect(Order::count())->toBe(24)->and(Customer::count())->toBe(12)
        ->and(Order::whereNull('customer_id')->count())->toBe(0)
        ->and(Order::where('source', 'staff')->count())->toBe(24);
    expect(Order::distinct()->pluck('status')->sort()->values()->all())
        ->toBe(['Cancelled', 'Confirmed', 'Delivered', 'Pending', 'Processing', 'Shipped']);
    foreach (Order::all() as $order) {
        $items = DB::table('order_items')->where('order_id', $order->id)->get();
        expect($items->count())->toBeGreaterThanOrEqual(1)->toBeLessThanOrEqual(3);
        expect((int) $items->sum('total_cents'))->toBe($order->subtotal_cents)
            ->and($order->total_cents)->toBe($order->subtotal_cents + $order->delivery_cents);
        foreach ($items as $item) expect($item->total_cents)->toBe($item->quantity * $item->price_cents);
        expect(DB::table('order_status_events')->where('order_id', $order->id)->latest('id')->value('status'))->toBe($order->status);
    }
    foreach (Variant::all() as $variant) {
        expect($variant->stock)->toBeGreaterThanOrEqual(0)
            ->and((int) DB::table('stock_movements')->where('variant_id', $variant->id)->sum('quantity'))->toBe($variant->stock);
    }
});

it('can rerun without duplicating data, resetting edits or deducting stock again', function () {
    $this->seed(OrderSeeder::class);
    $order = Order::orderBy('id')->firstOrFail();
    $order->update(['address' => 'Staff corrected address']);
    $order->customer->update(['name' => 'Staff corrected customer']);
    app(OrderWorkflowService::class)->status($order->id, 'Confirmed', $this->engineer->id);
    $tables = ['orders', 'order_items', 'customers', 'stock_movements', 'order_status_events'];
    $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()]);
    $stocks = Variant::orderBy('id')->pluck('stock', 'id')->all();
    $this->seed(OrderSeeder::class);
    foreach ($counts as $table => $count) expect(DB::table($table)->count())->toBe($count);
    expect(Variant::orderBy('id')->pluck('stock', 'id')->all())->toBe($stocks)
        ->and($order->fresh()->address)->toBe('Staff corrected address')
        ->and($order->fresh()->status)->toBe('Confirmed')
        ->and($order->customer->fresh()->name)->toBe('Staff corrected customer');
});

it('skips unavailable catalog inventory without inventing stock or orders', function () {
    Variant::query()->update(['active' => false]);
    $this->seed(OrderSeeder::class);
    expect(Order::count())->toBe(0)->and(Customer::count())->toBe(0);
    $this->assertDatabaseCount('stock_movements', 3);
});

it('does not create staff accounts or orders when no authorized active staff exists', function () {
    $this->engineer->update(['is_frozen' => true]);
    $this->seed(OrderSeeder::class);
    expect(Order::count())->toBe(0)->and(User::count())->toBe(1);
});
