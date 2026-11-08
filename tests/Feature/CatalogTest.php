<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->admin, 'api');
});
function catalogFixture()
{
    $c = Category::create(['name' => 'Clothing']);
    $p = Product::create(['name' => 'Shirt', 'slug' => 'shirt', 'status' => 'active']);

    $p->categories()->attach($c->id);

    return Variant::create(['product_id' => $p->id, 'sku' => 'SHIRT-M', 'attributes' => ['size' => 'M'], 'combination' => hash('sha256', 'fixture'), 'price_cents' => 12345, 'stock' => 5]);
}
function orderPayload($v)
{
    return ['submission_key' => (string) Str::uuid(), 'customer_name' => 'Customer', 'phone' => '01700000000', 'address' => 'Dhaka', 'delivery_cents' => 6000, 'items' => [['variant_id' => $v->id, 'quantity' => 2]]];
}
it('blocks customers and guests', function () {
    $this->actingAs(User::factory()->create(['role' => 'user']), 'api')->getJson('/api/admin/products')->assertForbidden();
    auth('api')->forgetUser();
    $this->getJson('/api/admin/products')->assertUnauthorized();
});
it('keeps categories flat and prevents deleting assigned categories', function () {
    $variant = catalogFixture();
    $category = $variant->product_id ? Product::find($variant->product_id)->categories()->first() : null;
    $this->putJson("/api/admin/categories/$category->id", ['name' => 'Updated', 'active' => true])->assertSuccessful();
    $this->deleteJson("/api/admin/categories/$category->id")->assertUnprocessable();
});
it('validates variants and retains opening stock history', function () {
    $v = catalogFixture();
    $url = "/api/admin/products/$v->product_id/variants";
    $data = ['sku' => 'NEW', 'attributes' => ['color' => 'Red'], 'price_cents' => 100, 'active' => true, 'opening_stock' => 4];
    $this->postJson($url, $data)->assertSuccessful();
    $this->assertDatabaseHas('stock_movements', ['quantity' => 4, 'reason' => 'Opening stock']);
    $this->postJson($url, [...$data, 'sku' => 'OTHER'])->assertUnprocessable();
    $this->postJson($url, [...$data, 'attributes' => ['color' => 'Blue']])->assertUnprocessable();
});
it('deducts once, snapshots prices and restores only once', function () {
    $v = catalogFixture();
    $data = orderPayload($v);
    $id = $this->postJson('/api/admin/orders', $data)->assertSuccessful()->json('id');
    $this->postJson('/api/admin/orders', $data)->assertSuccessful()->assertJsonPath('id', $id);
    expect($v->fresh()->stock)->toBe(3);
    $this->assertDatabaseCount('orders', 1);
    $this->assertDatabaseHas('orders', ['total_cents' => 30690]);
    $v->update(['price_cents' => 1]);
    $this->assertDatabaseHas('order_items', ['price_cents' => 12345, 'sku' => 'SHIRT-M']);
    $this->patchJson("/api/admin/orders/$id/status", ['status' => 'Delivered'])->assertUnprocessable();
    $this->patchJson("/api/admin/orders/$id/status", ['status' => 'Cancelled'])->assertSuccessful();
    $this->patchJson("/api/admin/orders/$id/status", ['status' => 'Cancelled'])->assertSuccessful();
    expect($v->fresh()->stock)->toBe(5);
    $this->patchJson("/api/admin/orders/$id/status", ['status' => 'Confirmed'])->assertUnprocessable();
});
it('rolls back insufficient stock and negative adjustments', function () {
    $v = catalogFixture();
    $d = orderPayload($v);
    $d['items'][0]['quantity'] = 6;
    $this->postJson('/api/admin/orders', $d)->assertUnprocessable();
    expect($v->fresh()->stock)->toBe(5);
    $this->assertDatabaseCount('orders', 0);
    $this->postJson("/api/admin/variants/$v->id/adjust", ['quantity' => -6, 'reason' => 'Correction'])->assertUnprocessable();
});
it('rejects changed duplicate submissions and terminal transitions', function () {
    $v = catalogFixture();
    $d = orderPayload($v);
    $id = $this->postJson('/api/admin/orders', $d)->json('id');
    $this->postJson('/api/admin/orders', [...$d, 'address' => 'Different'])->assertUnprocessable();
    foreach (['Confirmed', 'Processing', 'Shipped', 'Delivered'] as $s) {
        $this->patchJson("/api/admin/orders/$id/status", ['status' => $s])->assertSuccessful();
    }$this->patchJson("/api/admin/orders/$id/status", ['status' => 'Cancelled'])->assertUnprocessable();
    expect($v->fresh()->stock)->toBe(3);
});
it('uploads only images and maintains primary selection', function () {
    Storage::fake('public');
    $v = catalogFixture();
    $url = "/api/admin/products/$v->product_id/images";
    $this->postJson($url, ['image' => UploadedFile::fake()->create('bad.txt'), 'position' => 0, 'primary' => true])->assertUnprocessable();
    $id = $this->postJson($url, ['image' => UploadedFile::fake()->image('shirt.jpg'), 'position' => 0, 'primary' => true])->assertSuccessful()->json('id');
    $this->getJson("/api/admin/products/$v->product_id")->assertJsonPath('data.images.0.primary', true);
    $this->deleteJson("/api/admin/images/$id")->assertNoContent();
});

it('creates a default sellable variant when a product is added', function () {
    $category = Category::create(['name' => 'Accessories']);
    $payload = ['name' => 'Bag', 'slug' => 'bag', 'category_ids' => [$category->id],
        'status' => 'active', 'price_cents' => 15000, 'opening_stock' => 8];
    $id = $this->postJson('/api/admin/products', $payload)->assertSuccessful()->json('data.id');
    $variant = Variant::where('product_id', $id)->sole();
    expect($variant->attributes)->toBe([]);
    expect($variant->stock)->toBe(8);
    expect((int) $variant->price_cents)->toBe(15000);
    expect($variant->sku)->toStartWith('PRD-');
    $this->assertDatabaseHas('stock_movements', ['variant_id' => $variant->id, 'quantity' => 8]);
    $this->putJson("/api/admin/products/$id", $payload)->assertSuccessful();
    expect(Variant::where('product_id', $id)->count())->toBe(1);
    $this->postJson('/api/admin/orders', orderPayload($variant))->assertSuccessful();
    expect($variant->fresh()->stock)->toBe(6);
});

it('syncs multiple categories and rejects missing or duplicate selections', function () {
    $first = Category::create(['name' => 'First']);
    $second = Category::create(['name' => 'Second']);
    $payload = ['name' => 'Multi', 'slug' => 'multi', 'status' => 'active', 'category_ids' => [$first->id, $second->id]];
    $id = $this->postJson('/api/admin/products', $payload)->assertSuccessful()->json('data.id');
    expect(Product::find($id)->categories()->count())->toBe(2);
    $this->putJson("/api/admin/products/$id", [...$payload, 'category_ids' => [$second->id]])->assertSuccessful();
    expect(Product::find($id)->categories()->pluck('categories.id')->all())->toBe([$second->id]);
    $this->putJson("/api/admin/products/$id", [...$payload, 'category_ids' => []])->assertUnprocessable();
    $this->putJson("/api/admin/products/$id", [...$payload, 'category_ids' => [$first->id, $first->id]])->assertUnprocessable();
});

it('saves multiple variants with nullable details from the product form', function () {
    $category = Category::create(['name' => 'Electronics']);
    $variant = fn ($sku, $color) => [
        'sku' => $sku, 'name' => null, 'barcode' => null, 'attributes' => ['color' => $color],
        'price_cents' => 12000, 'purchase_price_cents' => null, 'dealer_price_cents' => null,
        'corporate_price_cents' => null, 'active' => true,
    ];
    $payload = [
        'name' => 'Monitor', 'slug' => 'monitor', 'category_ids' => [$category->id], 'status' => 'active',
        'brand' => null, 'model' => null, 'color' => null, 'unit' => null,
        'product_type' => null, 'tracking_type' => null,
        'variants' => [$variant('MON-BLACK', 'Black'), $variant('MON-WHITE', 'White')],
    ];
    $response = $this->postJson('/api/admin/products', $payload)->assertSuccessful();
    $id = $response->json('data.id');
    $variants = $response->json('data.variants');
    expect($variants)->toHaveCount(2);
    $this->assertDatabaseHas('variants', ['sku' => 'MON-BLACK', 'purchase_price_cents' => null]);
    $this->postJson('/api/admin/variants/'.$variants[0]['id'].'/adjust', ['quantity' => 7, 'reason' => 'Received'])->assertSuccessful();
    foreach ($payload['variants'] as $index => &$item) {
        $item['id'] = $variants[$index]['id'];
    }
    unset($item);
    $payload['variants'][0]['dealer_price_cents'] = 10000;
    $this->putJson("/api/admin/products/$id", $payload)->assertSuccessful();
    expect(Variant::find($variants[0]['id'])->stock)->toBe(7);
    expect(Product::find($id)->variants()->count())->toBe(2);
});

it('rolls back the entire product if any inline variant is invalid', function () {
    $category = Category::create(['name' => 'Test']);
    $variant = ['sku' => 'DUPLICATE', 'attributes' => [], 'price_cents' => 0, 'active' => true];
    $this->postJson('/api/admin/products', [
        'name' => 'Invalid', 'slug' => 'invalid', 'category_ids' => [$category->id], 'status' => 'active',
        'variants' => [$variant, $variant],
    ])->assertUnprocessable();
    $this->assertDatabaseMissing('products', ['slug' => 'invalid']);
    $this->assertDatabaseMissing('variants', ['sku' => 'DUPLICATE']);
});

it('archives products without deleting variants or their stock history', function () {
    $variant = catalogFixture();
    $this->deleteJson('/api/admin/products/'.$variant->product_id)
        ->assertSuccessful()->assertJsonPath('status', 'success');
    $this->assertDatabaseHas('products', ['id' => $variant->product_id, 'status' => 'draft']);
    $this->assertDatabaseHas('variants', ['id' => $variant->id, 'active' => false, 'stock' => 5]);
});
