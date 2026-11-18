<?php

use App\Models\HeaderLogo;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('public');
});

function logoAdmin(): User
{
    $permission = Permission::findOrCreate('homepage.manage', 'api');
    Role::findOrCreate('logo-editor', 'api')->givePermissionTo($permission);
    return User::factory()->create(['role' => 'logo-editor']);
}

it('returns empty logo slots before configuration', function () {
    $this->getJson('/api/storefront/header-logos')->assertOk()
        ->assertJsonPath('data.before_hover_url', null)->assertJsonPath('data.after_hover_url', null);
});

it('uploads both logos and replaces one without changing the other', function () {
    $this->actingAs(logoAdmin(), 'api')->postJson('/api/admin/header-logos', [
        'before_hover' => UploadedFile::fake()->image('light.png'),
        'after_hover' => UploadedFile::fake()->image('dark.png'),
    ])->assertOk();
    $logo = HeaderLogo::findOrFail(1);
    $before = $logo->before_hover_path;
    $after = $logo->after_hover_path;
    Storage::disk('public')->assertExists([$before, $after]);
    $this->getJson('/api/storefront/header-logos')->assertOk()
        ->assertJsonPath('data.before_hover_url', Storage::disk('public')->url($before))
        ->assertJsonPath('data.after_hover_url', Storage::disk('public')->url($after));

    $this->postJson('/api/admin/header-logos', [
        'before_hover' => UploadedFile::fake()->image('replacement.png'),
    ])->assertOk();
    expect($logo->fresh()->after_hover_path)->toBe($after)
        ->and(HeaderLogo::count())->toBe(1);
    Storage::disk('public')->assertMissing($before);
    Storage::disk('public')->assertExists([$logo->fresh()->before_hover_path, $after]);
});

it('rejects unsupported files and empty submissions', function () {
    $this->actingAs(logoAdmin(), 'api')->postJson('/api/admin/header-logos', [
        'before_hover' => UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
    ])->assertUnprocessable()->assertJsonValidationErrors('before_hover');
    $this->postJson('/api/admin/header-logos')->assertUnprocessable();
    expect(HeaderLogo::count())->toBe(0);
});

it('protects logo management with authentication and permission', function () {
    $this->postJson('/api/admin/header-logos')->assertUnauthorized();
    $this->actingAs(User::factory()->create(), 'api')->postJson('/api/admin/header-logos')->assertForbidden();
    $this->getJson('/api/admin/header-logos')->assertForbidden();
});
