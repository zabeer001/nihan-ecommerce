<?php

use App\Http\Controllers\Api\Product\Services\ProductUpdateService;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function seoSettingsAdmin(): User
{
    Role::findOrCreate('seo-editor', 'api')->givePermissionTo(Permission::findOrCreate('storefront.manage', 'api'));
    return User::factory()->create(['role' => 'seo-editor']);
}

it('publishes editable storefront metadata including removal of the verification token', function () {
    $data = ['gtm_container_id' => 'GTM-ABC123', 'meta_title' => 'My store', 'meta_pixel_id' => '123456789012345', 'meta_description' => 'My store description.', 'google_site_verification' => 'google_token-123'];
    $this->actingAs(seoSettingsAdmin(), 'api')->putJson('/api/admin/settings/storefront', $data)->assertOk();
    $this->getJson('/api/storefront/settings')->assertOk()->assertJsonPath('data', $data);
    $data['google_site_verification'] = '';
    $this->putJson('/api/admin/settings/storefront', $data)->assertOk()->assertJsonPath('data.google_site_verification', null);
});

it('protects site settings and rejects HTML in the verification token', function () {
    $this->putJson('/api/admin/settings/storefront')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->getJson('/api/admin/settings/storefront')->assertForbidden();
    $this->putJson('/api/admin/settings/storefront')->assertForbidden();
    $this->actingAs(seoSettingsAdmin(), 'api')->putJson('/api/admin/settings/storefront', [
        'meta_title' => 'Store', 'meta_description' => null, 'google_site_verification' => '<script>alert(1)</script>',
    ])->assertUnprocessable()->assertJsonValidationErrors('google_site_verification');
});

it('changes only the signed-in users password after verifying the current password', function () {
    $user = User::factory()->create(['password' => 'old-password']);
    $other = User::factory()->create(['password' => 'other-password']);
    $this->actingAs($user, 'api')->putJson('/api/admin/settings/password', [
        'current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'new-password',
        'user_id' => $other->id,
    ])->assertOk();
    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('other-password', $other->fresh()->password))->toBeTrue();
});

it('rejects unauthenticated password changes', function () {
    $this->putJson('/api/admin/settings/password')->assertUnauthorized();
});

it('rejects invalid password changes', function (array $data, string $field) {
    $user = User::factory()->create(['password' => 'old-password']);
    $this->actingAs($user, 'api')->putJson('/api/admin/settings/password', $data)
        ->assertUnprocessable()->assertJsonValidationErrors($field);
    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
})->with([
    [['current_password' => 'wrong', 'password' => 'new-password', 'password_confirmation' => 'new-password'], 'current_password'],
    [['current_password' => 'old-password', 'password' => 'new-password', 'password_confirmation' => 'different'], 'password'],
    [['current_password' => 'old-password', 'password' => 'short', 'password_confirmation' => 'short'], 'password'],
]);

it('saves and clears product SEO and pixel values and exposes them on the storefront', function () {
    $user = User::factory()->create();
    $category = Category::create(['name' => 'Fragrances', 'active' => true]);
    $product = Product::create(['name' => 'Diffuser', 'slug' => 'diffuser', 'status' => 'active']);
    $product->categories()->attach($category);
    $base = ['name' => $product->name, 'slug' => $product->slug, 'status' => 'active', 'category_ids' => [$category->id]];
    $seo = ['meta_title' => 'Diffuser SEO title', 'meta_description' => 'Diffuser SEO description', 'meta_pixel_id' => '123456789012345'];
    $request = Request::create('/', 'PUT', [...$base, ...$seo]);
    $request->setUserResolver(fn () => $user);
    app(ProductUpdateService::class)->handle($request, $product);
    $this->getJson('/api/storefront/products/diffuser')->assertOk()
        ->assertJsonPath('data.meta_title', $seo['meta_title'])
        ->assertJsonPath('data.meta_description', $seo['meta_description'])
        ->assertJsonPath('data.meta_pixel_id', $seo['meta_pixel_id']);
    $request = Request::create('/', 'PUT', [...$base, 'meta_title' => null, 'meta_description' => null, 'meta_pixel_id' => null]);
    $request->setUserResolver(fn () => $user);
    app(ProductUpdateService::class)->handle($request, $product);
    expect($product->fresh()->meta_title)->toBeNull()->and($product->fresh()->meta_pixel_id)->toBeNull();
});

it('rejects script snippets in product pixel IDs', function () {
    $product = Product::create(['name' => 'Diffuser', 'slug' => 'diffuser', 'status' => 'active']);
    $request = Request::create('/', 'PUT', [
        'name' => 'Diffuser', 'slug' => 'diffuser', 'status' => 'active', 'category_ids' => [],
        'meta_pixel_id' => '<script>alert(1)</script>',
    ]);
    try {
        app(ProductUpdateService::class)->handle($request, $product);
        $this->fail('Invalid pixel ID was accepted.');
    } catch (\Illuminate\Validation\ValidationException $exception) {
        expect($exception->errors())->toHaveKey('meta_pixel_id');
    }
});


it('saves and clears the site-wide Meta Pixel and rejects snippets', function () {
    $this->actingAs(seoSettingsAdmin(), 'api');
    $base = ['meta_title' => 'Store', 'meta_description' => null, 'google_site_verification' => null];
    $this->putJson('/api/admin/settings/storefront', [...$base, 'meta_pixel_id' => '123456789012345'])
        ->assertOk()->assertJsonPath('data.meta_pixel_id', '123456789012345');
    $this->getJson('/api/storefront/settings')->assertOk()->assertJsonPath('data.meta_pixel_id', '123456789012345');
    $this->putJson('/api/admin/settings/storefront', [...$base, 'meta_pixel_id' => '<script>'])
        ->assertUnprocessable()->assertJsonValidationErrors('meta_pixel_id');
    $this->putJson('/api/admin/settings/storefront', [...$base, 'meta_pixel_id' => ''])
        ->assertOk()->assertJsonPath('data.meta_pixel_id', null);
});


it('validates and clears the global GTM container ID', function () {
    $this->actingAs(seoSettingsAdmin(), 'api');
    $base = ['meta_title' => 'Store', 'meta_description' => null, 'google_site_verification' => null];
    $this->putJson('/api/admin/settings/storefront', [...$base, 'gtm_container_id' => 'GTM-ABC123'])
        ->assertOk()->assertJsonPath('data.gtm_container_id', 'GTM-ABC123');
    $this->getJson('/api/storefront/settings')->assertOk()->assertJsonPath('data.gtm_container_id', 'GTM-ABC123');
    $this->putJson('/api/admin/settings/storefront', [...$base, 'gtm_container_id' => '<script>'])
        ->assertUnprocessable()->assertJsonValidationErrors('gtm_container_id');
    $this->putJson('/api/admin/settings/storefront', [...$base, 'gtm_container_id' => ''])
        ->assertOk()->assertJsonPath('data.gtm_container_id', null);
});


it('separates SEO and tracking access from homepage management', function () {
    Role::findOrCreate('homepage-only', 'api')->givePermissionTo(Permission::findOrCreate('homepage.manage', 'api'));
    $this->actingAs(User::factory()->create(['role' => 'homepage-only']), 'api');
    $this->getJson('/api/admin/settings/storefront')->assertForbidden();
    $this->putJson('/api/admin/settings/storefront', [
        'meta_title' => 'Unauthorized', 'meta_description' => null, 'google_site_verification' => null,
    ])->assertForbidden();
    $this->actingAs(seoSettingsAdmin(), 'api')->getJson('/api/admin/settings/storefront')->assertOk();
    $this->getJson('/api/admin/homepage-content')->assertForbidden();
});

it('offers the storefront permission in role management', function () {
    Role::findOrCreate('permission-manager', 'api')->givePermissionTo(Permission::findOrCreate('roles.manage', 'api'));
    $this->actingAs(User::factory()->create(['role' => 'permission-manager']), 'api');
    $permission = Permission::findOrCreate('storefront.manage', 'api');
    $this->getJson('/api/admin/roles/options')->assertOk()->assertJsonFragment([
        'id' => $permission->id, 'name' => 'storefront.manage',
    ]);
    $this->postJson('/api/admin/roles', ['name' => 'tracking-editor', 'permission_ids' => [$permission->id]])->assertCreated();
    $this->actingAs(User::factory()->create(['role' => 'tracking-editor']), 'api')
        ->getJson('/api/admin/settings/storefront')->assertOk();
});
