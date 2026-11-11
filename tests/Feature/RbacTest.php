<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('assigns a custom role and enforces changes to its permissions', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $permission = Permission::findByName('products.view', 'api');
    $this->actingAs($admin, 'api');

    $id = $this->postJson('/api/admin/roles', [
        'name' => 'catalog-reader',
        'permission_ids' => [$permission->id],
    ])->assertCreated()->json('data.id');

    $this->getJson('/api/admin/users?search='.rawurlencode($staff->email))
        ->assertOk()->assertJsonPath('data.data', [[
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'role' => $staff->role,
            'can_change_role' => true,
        ]]);

    $this->putJson('/api/admin/roles/assignment', ['email' => $staff->email, 'role_id' => $id])->assertOk();
    $this->getJson('/api/admin/users?search='.rawurlencode($staff->email))
        ->assertOk()->assertJsonPath('data.data.0.role', 'catalog-reader');
    $this->deleteJson("/api/admin/roles/{$id}")->assertUnprocessable();

    $this->actingAs($staff->fresh(), 'api');
    $this->getJson('/api/profile')->assertJsonPath('data.permissions', ['products.view']);
    $this->getJson('/api/admin/products')->assertOk();
    $this->postJson('/api/admin/products')->assertForbidden();
    $this->getJson('/api/admin/orders')->assertForbidden();
    $this->getJson('/api/admin/roles')->assertForbidden();
    $this->getJson('/api/admin/users')->assertForbidden();

    $this->actingAs($admin, 'api')->putJson("/api/admin/roles/{$id}", [
        'name' => 'catalog-staff', 'permission_ids' => [],
    ])->assertOk();

    expect($staff->fresh()->role)->toBe('catalog-staff');
    $this->actingAs($staff->fresh(), 'api')->getJson('/api/admin/products')->assertForbidden();

    $this->actingAs($admin, 'api');
    $this->putJson('/api/admin/roles/assignment', [
        'email' => $staff->email, 'role_id' => Role::findByName('user', 'api')->id,
    ])->assertOk();
    $this->deleteJson("/api/admin/roles/{$id}")->assertOk();
});

it('protects administrative roles and blocks frozen accounts', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $this->actingAs($admin, 'api');

    $this->postJson('/api/admin/roles', [
        'name' => 'privileged',
        'permission_ids' => [Permission::findByName('roles.manage', 'api')->id],
    ])->assertUnprocessable();

    $this->putJson('/api/admin/roles/assignment', [
        'email' => $staff->email, 'role_id' => Role::findByName('superadmin', 'api')->id,
    ])->assertForbidden();

    $this->deleteJson('/api/admin/roles/'.Role::findByName('admin', 'api')->id)->assertForbidden();

    $admin->update(['is_frozen' => true]);
    $this->getJson('/api/admin/roles')->assertForbidden();
});
