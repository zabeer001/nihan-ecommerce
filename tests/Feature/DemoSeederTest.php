<?php

use App\Models\Category;
use App\Models\Customer;
use App\Models\HeaderLogo;
use App\Models\HomepageContent;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\Variant;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

it('seeds a complete storefront and reruns without duplicating data or resetting edits', function () {
    Storage::fake('public');
    $admin = User::factory()->create(['email' => 'binzabirtareq@gmail.com', 'name' => 'Existing administrator', 'role' => 'admin']);
    $password = $admin->password;
    $this->seed(DatabaseSeeder::class);

    expect(Product::count())->toBe(13)->and(Variant::count())->toBe(20)
        ->and(ProductReview::count())->toBe(78)->and(Order::count())->toBe(28)
        ->and(Customer::count())->toBe(16)->and(HomepageContent::count())->toBe(4);
    expect(Order::distinct()->pluck('status')->sort()->values()->all())
        ->toBe(['Cancelled', 'Confirmed', 'Delivered', 'Pending', 'Processing', 'Shipped']);
    expect(Order::where('source', 'storefront')->count())->toBe(12)
        ->and(Order::whereNull('customer_id')->count())->toBe(0)
        ->and(Variant::where('stock', '<', 0)->count())->toBe(0)
        ->and(Product::where('status', 'draft')->count())->toBe(1)
        ->and(Variant::whereNotNull('compare_at_price_cents')->count())->toBeGreaterThan(0);
    foreach (Variant::all() as $variant) {
        expect((int) DB::table('stock_movements')->where('variant_id', $variant->id)->sum('quantity'))->toBe($variant->stock);
    }
    foreach (Order::all() as $order) {
        expect((int) DB::table('order_items')->where('order_id', $order->id)->sum('total_cents'))->toBe($order->subtotal_cents)
            ->and($order->total_cents)->toBe($order->subtotal_cents + $order->delivery_cents);
        expect(DB::table('order_status_events')->where('order_id', $order->id)->latest('id')->value('status'))->toBe($order->status);
    }
    $logos = HeaderLogo::findOrFail(1);
    Storage::disk('public')->assertExists([$logos->before_hover_path, $logos->after_hover_path]);
    foreach (HomepageContent::with('media')->get() as $block) {
        foreach ($block->media as $media) Storage::disk('public')->assertExists($media->media_path);
    }
    expect(Category::where('name', 'Fragrance')->first()->content_cards)->toHaveCount(4)
        ->and($admin->fresh()->password)->toBe($password)
        ->and($admin->fresh()->name)->toBe('Existing administrator');

    // Simulate admin edits before running all seeders again.
    $product = Product::where('product_code', 'DEMO-NIHAN-DIFFUSER')->firstOrFail();
    $product->update(['name' => 'Staff-edited diffuser', 'status' => 'draft']);
    $variant = Variant::where('sku', 'DEMO-NIHAN-REFILL-DUO-WOODY')->firstOrFail();
    $variant->update(['price_cents' => 123400, 'compare_at_price_cents' => null]);
    Category::where('name', 'Fragrance')->update(['content_cards' => '[]', 'usage_guide' => '[]']);
    $review = ProductReview::firstOrFail();
    $review->update(['title' => 'Edited review', 'rating' => 2, 'status' => 'rejected']);
    $logos->update(['before_hover_path' => 'custom/light.png', 'after_hover_path' => 'custom/dark.png']);
    $hero = HomepageContent::where('placement', 'hero')->firstOrFail();
    $hero->update(['heading' => 'Our custom campaign', 'is_published' => false]);
    $role = Role::where('name', 'demo-catalog-editor')->firstOrFail();
    $role->syncPermissions(['products.view']);
    $tables = ['products', 'variants', 'customers', 'orders', 'order_items', 'stock_movements', 'order_status_events', 'product_reviews', 'homepage_contents', 'homepage_media', 'header_logos'];
    $counts = collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    $stocks = Variant::orderBy('id')->pluck('stock', 'id')->all();
    $orders = Order::orderBy('id')->get()->toArray();

    $this->seed(DatabaseSeeder::class);
    foreach ($counts as $table => $count) expect(DB::table($table)->count())->toBe($count);
    expect(Variant::orderBy('id')->pluck('stock', 'id')->all())->toBe($stocks)
        ->and(Order::orderBy('id')->get()->toArray())->toBe($orders)
        ->and($product->fresh()->name)->toBe('Staff-edited diffuser')
        ->and($product->fresh()->status)->toBe('draft')
        ->and($variant->fresh()->price_cents)->toBe(123400)
        ->and($variant->fresh()->compare_at_price_cents)->toBeNull()
        ->and(Category::where('name', 'Fragrance')->first()->content_cards)->toBe([])
        ->and(Category::where('name', 'Fragrance')->first()->usage_guide)->toBe([])
        ->and($logos->fresh()->before_hover_path)->toBe('custom/light.png')
        ->and($hero->fresh()->heading)->toBe('Our custom campaign')
        ->and($hero->fresh()->is_published)->toBeFalse()
        ->and($review->fresh()->title)->toBe('Edited review')
        ->and($review->fresh()->status)->toBe('rejected')
        ->and($role->fresh()->permissions->pluck('name')->all())->toBe(['products.view']);
});
