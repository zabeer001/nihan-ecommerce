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
    Product::findOrFail($v->product_id)->update(['status' => 'draft']);
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
        'name' => 'Monitor', 'slug' => 'monitor', 'category_ids' => [$category->id], 'status' => 'draft',
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

function stagedCatalogPhotos($test, int $count, ?int $productId = null): array
{
    $url = '/api/admin/products'.($productId ? "/$productId" : '').'/image-uploads';

    return array_map(function (int $index) use ($test, $url) {
        $upload = $test->post($url, ['image' => UploadedFile::fake()->image("photo-$index.jpg")], ['Accept' => 'application/json'])
            ->assertCreated()->json('upload_id');

        return ['upload_id' => $upload, 'primary' => $index === 0];
    }, range(0, $count - 1));
}

it('saves a simple product with one stock SKU and a product photo gallery', function () {
    Storage::fake('public');
    $category = Category::create(['name' => 'Clothing']);
    $payload = ['name' => 'Y-shirt', 'slug' => 'y-shirt', 'category_ids' => [$category->id], 'status' => 'active'];
    $id = $this->postJson('/api/admin/products', [
        ...$payload, 'gallery' => ['items' => stagedCatalogPhotos($this, 5)],
    ])->assertCreated()->json('data.id');

    $details = $this->getJson("/api/admin/products/$id")->assertSuccessful()
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonCount(0, 'data.variants.0.images')
        ->assertJsonCount(5, 'data.images')
        ->assertJsonPath('data.images.0.primary', true)
        ->assertJsonPath('data.images.4.primary', false)
        ->json('data');
    expect(Variant::where('product_id', $id)->sole()->attributes)->toBe([]);

    $items = array_map(fn ($photo) => ['id' => $photo['id'], 'primary' => false], array_slice($details['images'], 1));
    $this->putJson("/api/admin/products/$id", [...$payload, 'gallery' => [
        'items' => $items,
        'removed_ids' => [$details['images'][0]['id']],
    ]])->assertSuccessful();
    $this->getJson("/api/admin/products/$id")->assertJsonCount(4, 'data.images')->assertJsonPath('data.images.0.primary', false);
});

it('isolates variant galleries and retries staged photos after an incomplete save', function () {
    Storage::fake('public');
    $category = Category::create(['name' => 'Clothing']);
    $variant = fn ($sku, $size, $design) => [
        'sku' => $sku, 'name' => "$size / $design", 'attributes' => ['size' => $size, 'design' => $design],
        'price_cents' => 1200, 'active' => true,
    ];
    $variants = [$variant('Z-M-LUFFY', 'M', 'Luffy'), $variant('Z-L-NAMI', 'L', 'Nami')];
    $payload = ['name' => 'Z-shirt', 'slug' => 'z-shirt', 'category_ids' => [$category->id], 'status' => 'draft', 'variants' => $variants];
    $id = $this->postJson('/api/admin/products', $payload)->assertCreated()->json('data.id');
    $url = "/api/admin/products/$id";

    // Existing product-level uploads still appear in the shared gallery.
    $shared = $this->post($url.'/images', [
        'image' => UploadedFile::fake()->image('shared.jpg'), 'primary' => true, 'position' => 0,
    ], ['Accept' => 'application/json'])->assertSuccessful()->json('id');

    foreach ($this->getJson($url)->json('data.variants') as $index => $saved) {
        $payload['variants'][$index]['id'] = $saved['id'];
    }
    $payload['status'] = 'active';
    $payload['variants'][0]['gallery'] = ['items' => stagedCatalogPhotos($this, 4, $id)];
    $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.1.gallery');
    $this->getJson($url)->assertJsonPath('data.product.status', 'draft')->assertJsonCount(0, 'data.variants.0.images');

    $payload['variants'][1]['gallery'] = ['items' => stagedCatalogPhotos($this, 2, $id)];
    $this->putJson($url, $payload)->assertSuccessful();
    $saved = $this->getJson($url)
        ->assertJsonCount(1, 'data.images')->assertJsonPath('data.images.0.id', $shared)
        ->assertJsonCount(4, 'data.variants.0.images')->assertJsonPath('data.variants.0.images.0.primary', true)
        ->assertJsonCount(2, 'data.variants.1.images')->assertJsonPath('data.variants.1.images.0.primary', true)
        ->json('data.variants');

    $luffy = $saved[0]['images'];
    $nami = $saved[1]['images'];
    unset($payload['variants'][1]['gallery']);
    $items = array_map(fn ($photo) => ['id' => $photo['id'], 'primary' => false], array_reverse(array_slice($luffy, 1)));
    $payload['variants'][0]['gallery'] = ['items' => $items, 'removed_ids' => [$luffy[0]['id']]];
    $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.gallery');

    $payload['variants'][0]['gallery']['items'][0]['id'] = $nami[1]['id'];
    $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('variants.0.gallery');
    $payload['variants'][0]['gallery']['items'] = [...stagedCatalogPhotos($this, 1, $id), ...$items];
    $this->putJson($url, $payload)->assertSuccessful();
    $this->getJson($url)
        ->assertJsonPath('data.variants.0.images.1.id', $luffy[3]['id'])
        ->assertJsonPath('data.variants.1.images', $nami)
        ->assertJsonPath('data.images.0.id', $shared);
    Storage::disk('public')->assertMissing($luffy[0]['path']);
});

it('rejects invalid and expired staged files and uploads staged for another product', function () {
    Storage::fake('public');
    $variant = catalogFixture();
    $categoryId = Product::findOrFail($variant->product_id)->categories()->first()->id;
    $this->post('/api/admin/products/image-uploads', [
        'image' => UploadedFile::fake()->create('not-a-photo.txt'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('image');

    $payload = ['name' => 'Simple', 'slug' => 'simple', 'category_ids' => [$categoryId], 'status' => 'active'];
    $this->postJson('/api/admin/products', [
        ...$payload, 'gallery' => ['items' => stagedCatalogPhotos($this, 1, $variant->product_id)],
    ])->assertUnprocessable()->assertJsonValidationErrors('gallery');

    $photos = stagedCatalogPhotos($this, 1);
    $this->travel(24)->hours();
    $this->postJson('/api/admin/products', [...$payload, 'gallery' => ['items' => $photos]])
        ->assertUnprocessable()->assertJsonValidationErrors('gallery');
    $this->artisan('catalog:prune-image-uploads')->assertSuccessful();
    $this->assertDatabaseCount('product_image_uploads', 0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
    $this->travelBack();
});

it('publishes only active catalog products and photos for their active variants', function () {
    $luffy = catalogFixture();
    $product = Product::findOrFail($luffy->product_id);
    $luffy->update(['name' => 'M / Luffy', 'purchase_price_cents' => 1000]);
    $nami = $product->variants()->create([
        'sku' => 'SHIRT-L', 'name' => 'L / Nami', 'attributes' => ['size' => 'L'],
        'combination' => hash('sha256', 'nami'), 'price_cents' => 2000, 'active' => false,
    ]);
    $photo = fn ($path, $variantId = null, $primary = false) => \App\Models\ProductImage::create([
        'product_id' => $product->id, 'variant_id' => $variantId, 'path' => $path, 'primary' => $primary,
    ]);
    $shared = $photo('products/shared.jpg');
    $extra = $photo('products/luffy-extra.jpg', $luffy->id);
    $featured = $photo('products/luffy-featured.jpg', $luffy->id, true);
    $photo('products/nami.jpg', $nami->id, true);
    $this->postJson('/api/admin/products', [
        'name' => 'Draft', 'slug' => 'draft', 'status' => 'draft',
        'category_ids' => [$product->categories()->first()->id],
    ])->assertCreated();
    $simple = $this->postJson('/api/admin/products', [
        'name' => 'Y-shirt', 'slug' => 'y-shirt', 'status' => 'active',
        'category_ids' => [$product->categories()->first()->id],
    ])->assertCreated()->json('data.id');
    $simplePhoto = Product::findOrFail($simple)->images()->create(['path' => 'products/y-shirt.jpg', 'primary' => true]);
    auth('api')->forgetUser();

    $category = $product->categories()->firstOrFail();
    $hidden = Category::create(['name' => 'Hidden', 'active' => false]);
    $product->categories()->attach($hidden);
    $this->getJson('/api/storefront/categories')->assertOk()
        ->assertExactJson(['data' => [['id' => $category->id, 'name' => $category->name]]]);
    $this->getJson("/api/storefront/products?category_id=$category->id")->assertOk()
        ->assertJsonCount(2, 'data');
    $this->getJson("/api/storefront/products?category_id=$hidden->id")->assertOk()
        ->assertJsonCount(0, 'data');
    $this->getJson('/api/storefront/products?category=clothing')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.1.price_cents', 12345)
        ->assertJsonPath('data.1.image.id', $shared->id);
    $this->getJson('/api/storefront/products/shirt')->assertOk()
        ->assertJsonPath('data.has_variants', true)
        ->assertJsonCount(1, 'data.variants')
        ->assertJsonPath('data.variants.0.label', 'M / Luffy')
        ->assertJsonPath('data.variants.0.images.0.id', $featured->id)
        ->assertJsonPath('data.variants.0.images.1.id', $extra->id)
        ->assertJsonMissingPath('data.variants.0.purchase_price_cents')
        ->assertJsonPath('data.images.0.id', $shared->id);
    $this->getJson('/api/storefront/products/y-shirt')->assertOk()
        ->assertJsonPath('data.has_variants', false)
        ->assertJsonPath('data.images.0.id', $simplePhoto->id);
    $this->getJson('/api/storefront/products/draft')->assertNotFound();
});
