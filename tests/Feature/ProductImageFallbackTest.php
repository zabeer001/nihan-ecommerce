<?php

use App\Http\Controllers\Api\Product\Services\ProductImageDeleteService;
use App\Http\Controllers\Api\Product\Services\ProductImageStoreService;
use App\Http\Controllers\Api\Product\Services\ProductImageUploadService;
use App\Http\Controllers\Api\Product\Services\ProductUpdateService;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\ProductImageFallbackSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('public');
});

function fallbackProduct(string $name, string $category = 'Fragrance'): Product
{
    $product = Product::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(6), 'status' => 'draft']);
    $product->categories()->attach(Category::firstOrCreate(['name' => $category], ['active' => true]));
    return $product;
}

function fallbackStage(Product $product, User $user): string
{
    $request = Request::create('/', 'POST', [], [], ['image' => UploadedFile::fake()->image('real.png')]);
    $request->setUserResolver(fn () => $user);
    return app(ProductImageUploadService::class)->handle($request, $product->id)->getData(true)['upload_id'];
}

function fallbackSave(Product $product, User $user, array $data): void
{
    $request = Request::create('/', 'PUT', [
        'name' => $product->name, 'slug' => $product->slug, 'status' => $product->status,
        'category_ids' => $product->categories()->pluck('categories.id')->all(), ...$data,
    ]);
    $request->setUserResolver(fn () => $user);
    app(ProductUpdateService::class)->handle($request, $product);
}

it('matches local assets and keeps selections and product data stable on reruns', function () {
    $products = [
        'Oud Noir refill' => ['Fragrance', 'oud-noir.webp'],
        'Padma' => ['Fragrance', 'padma.webp'],
        'Mango Leaf 100 ml' => ['Fragrance', 'mango-leaf.webp'],
        'Monsoon' => ['Fragrance', 'monsoon.webp'],
        'Sand Gold diffuser' => ['Accessories', 'diffuser-sand.webp'],
        'Car Diffuser' => ['Accessories', 'diffuser.webp'],
        'Oud Noir Starter Kit' => ['Accessories', 'starter-kit.webp'],
        'Unrelated item' => ['Other', 'placeholder.svg'],
    ];
    $expected = [];
    foreach ($products as $name => [$category, $asset]) {
        $product = fallbackProduct($name, $category);
        $expected[$product->id] = ProductImage::SEED_FALLBACK_PREFIX.$asset;
    }
    $fragrance = fallbackProduct('New release');
    $accessory = fallbackProduct('New accessory', 'Accessories');
    $before = Product::orderBy('id')->get()->toArray();
    $this->seed(ProductImageFallbackSeeder::class);
    foreach ($expected as $id => $path) {
        expect(Product::findOrFail($id)->images()->sole()->path)->toBe($path);
        Storage::disk('public')->assertExists($path);
    }
    expect($fragrance->images()->sole()->path)->toBeIn(array_map(fn ($file) => 'seed-fallbacks/'.$file.'.webp', ['oud-noir', 'padma', 'mango-leaf', 'monsoon']));
    expect($accessory->images()->sole()->path)->toBeIn(['seed-fallbacks/diffuser.webp', 'seed-fallbacks/diffuser-sand.webp', 'seed-fallbacks/starter-kit.webp']);
    $images = ProductImage::orderBy('id')->get()->toArray();
    $this->seed(ProductImageFallbackSeeder::class);
    expect(ProductImage::orderBy('id')->get()->toArray())->toBe($images)
        ->and(Product::orderBy('id')->get()->toArray())->toBe($before);
});

it('preserves both product images and variant-only images including missing files', function () {
    $shared = fallbackProduct('Existing shared');
    $shared->images()->create(['path' => 'products/upload.png', 'primary' => true]);
    $variantOnly = fallbackProduct('Variant-only');
    $variant = $variantOnly->variants()->create(['sku' => 'VARIANT-ONLY', 'attributes' => [], 'combination' => hash('sha256', '[]'), 'price_cents' => 10000]);
    $variant->images()->create(['product_id' => $variantOnly->id, 'path' => 'products/variant.png', 'primary' => true]);
    $before = ProductImage::orderBy('id')->get()->toArray();
    $this->seed(ProductImageFallbackSeeder::class);
    expect(ProductImage::orderBy('id')->get()->toArray())->toBe($before);
});

it('replaces a fallback on direct upload and keeps the shared asset for other products', function () {
    $first = fallbackProduct('Padma one');
    $second = fallbackProduct('Padma two');
    $this->seed(ProductImageFallbackSeeder::class);
    $request = Request::create('/', 'POST', ['position' => 0, 'primary' => false], [], ['image' => UploadedFile::fake()->image('real.png')]);
    $image = app(ProductImageStoreService::class)->handle($request, $first->id);
    expect($first->images()->sole()->id)->toBe($image->id)->and($image->primary)->toBeTrue();
    expect($second->images()->sole()->path)->toBe('seed-fallbacks/padma.webp');
    Storage::disk('public')->assertExists(['seed-fallbacks/padma.webp', $image->path]);
    $this->seed(ProductImageFallbackSeeder::class);
    expect($first->images()->sole()->id)->toBe($image->id);
});

it('preserves a fallback when a direct upload fails validation', function () {
    $product = fallbackProduct('Monsoon');
    $this->seed(ProductImageFallbackSeeder::class);
    $id = $product->images()->sole()->id;
    $request = Request::create('/', 'POST', ['position' => 0, 'primary' => true], [], ['image' => UploadedFile::fake()->create('not-image.txt')]);
    expect(fn () => app(ProductImageStoreService::class)->handle($request, $product->id))->toThrow(ValidationException::class);
    expect($product->images()->sole()->id)->toBe($id);
});

it('removes a retained fallback after saving a staged product upload', function () {
    $product = fallbackProduct('Padma');
    $user = User::factory()->create();
    $this->seed(ProductImageFallbackSeeder::class);
    $fallback = $product->images()->sole();
    $upload = fallbackStage($product, $user);
    expect($product->images()->sole()->id)->toBe($fallback->id);
    fallbackSave($product, $user, ['gallery' => ['items' => [
        ['id' => $fallback->id, 'primary' => true], ['upload_id' => $upload, 'primary' => false],
    ]]]);
    $image = $product->images()->sole();
    expect($image->isSeedFallback())->toBeFalse()->and($image->primary)->toBeTrue();
    Storage::disk('public')->assertExists($fallback->path);
});

it('syncs variant and shared galleries before removing fallback references', function () {
    $product = fallbackProduct('Oud Noir');
    $user = User::factory()->create();
    $this->seed(ProductImageFallbackSeeder::class);
    $fallback = $product->images()->sole();
    $upload = fallbackStage($product, $user);
    fallbackSave($product, $user, [
        'variants' => [['sku' => 'REAL-VARIANT', 'attributes' => ['color' => 'Gold'], 'price_cents' => 15000, 'active' => true,
            'gallery' => ['items' => [['upload_id' => $upload, 'primary' => true]]]]],
        'gallery' => ['items' => [['id' => $fallback->id, 'primary' => true]]],
    ]);
    expect($product->images()->count())->toBe(0)
        ->and($product->variants()->sole()->images()->sole()->isSeedFallback())->toBeFalse();
    Storage::disk('public')->assertExists($fallback->path);
});

it('saves an active variant with only a featured image and removes the fallback', function () {
    $product = fallbackProduct('Monsoon');
    $product->update(['status' => 'active']);
    $user = User::factory()->create();
    $this->seed(ProductImageFallbackSeeder::class);
    $fallback = $product->images()->sole();
    $upload = fallbackStage($product, $user);
    fallbackSave($product, $user, [
        'variants' => [['sku' => 'FEATURED-ONLY', 'attributes' => ['color' => 'Gold'], 'price_cents' => 15000, 'active' => true,
            'gallery' => ['items' => [['upload_id' => $upload, 'primary' => true]]]]],
    ]);
    expect($product->images()->count())->toBe(0)
        ->and($product->variants()->sole()->images()->sole()->primary)->toBeTrue();
    Storage::disk('public')->assertExists($fallback->path);
    $this->assertDatabaseMissing('product_image_uploads', ['id' => $upload]);
});

it('saves active products and variants without photos or without a featured image', function (bool $galleryOnly) {
    $product = fallbackProduct('Optional photos');
    $user = User::factory()->create();
    $items = $galleryOnly ? [['upload_id' => fallbackStage($product, $user), 'primary' => false]] : [];
    fallbackSave($product, $user, [
        'status' => 'active',
        'gallery' => ['items' => []],
        'variants' => [
            ['sku' => 'OPTIONAL-GOLD', 'attributes' => ['color' => 'Gold'], 'price_cents' => 15000, 'active' => true,
                'gallery' => ['items' => $items]],
            ['sku' => 'OPTIONAL-SILVER', 'attributes' => ['color' => 'Silver'], 'price_cents' => 15000, 'active' => true],
        ],
    ]);
    expect($product->fresh()->status)->toBe('active')
        ->and($product->images()->count())->toBe(0)
        ->and($product->variants()->where('active', true)->count())->toBe(2)
        ->and(ProductImage::where('product_id', $product->id)->where('primary', true)->count())->toBe(0);
    if ($galleryOnly) {
        $variant = $product->variants()->where('sku', 'OPTIONAL-GOLD')->sole();
        $image = $variant->images()->sole();
        fallbackSave($product, $user, [
            'status' => 'active',
            'variants' => [['id' => $variant->id, 'sku' => $variant->sku, 'attributes' => $variant->attributes,
                'price_cents' => $variant->price_cents, 'active' => true,
                'gallery' => ['items' => [], 'removed_ids' => [$image->id]]]],
        ]);
        expect($variant->images()->count())->toBe(0);
    }
})->with([false, true]);

it('keeps shared fallback files when admins delete or replace their gallery records', function () {
    $first = fallbackProduct('Padma one');
    $second = fallbackProduct('Padma two');
    $this->seed(ProductImageFallbackSeeder::class);
    $path = $first->images()->sole()->path;
    app(ProductImageDeleteService::class)->handle($first->images()->sole()->id);
    Storage::disk('public')->assertExists($path);
    $user = User::factory()->create();
    $fallback = $second->images()->sole();
    $upload = fallbackStage($second, $user);
    fallbackSave($second, $user, ['gallery' => ['items' => [['upload_id' => $upload, 'primary' => true]], 'removed_ids' => [$fallback->id]]]);
    Storage::disk('public')->assertExists($path);
});
