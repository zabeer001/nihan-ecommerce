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
            'can_delete' => true,
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

it('lets the configured software engineer manage and assign every role without being superadmin', function () {
    config(['rbac.software_engineer_emails' => ['binzabirtareq@gmail.com']]);
    $engineer = User::factory()->create(['email' => 'binzabirtareq@gmail.com', 'role' => 'user']);
    $staff = User::factory()->create();
    $this->actingAs($engineer, 'api');

    expect($this->getJson('/api/profile')->assertOk()->json('data.permissions'))->toContain('roles.manage');
    $this->getJson('/api/admin/users')->assertOk();
    $roles = $this->getJson('/api/admin/roles')->assertOk()->json('data');
    expect(collect($roles)->every(fn ($role) => $role['assignable']))->toBeTrue();

    foreach (['admin', 'superadmin'] as $name) {
        $role = Role::findByName($name, 'api');
        $this->putJson('/api/admin/roles/assignment', [
            'email' => $staff->email, 'role_id' => $role->id,
        ])->assertOk();
        expect($staff->fresh()->role)->toBe($name);
        $this->postJson('/api/admin/users', [
            'name' => 'New '.$name, 'email' => $name.'@example.com', 'role_id' => $role->id,
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertCreated()->assertJsonPath('data.role', $name);
    }

    $management = Permission::findByName('roles.manage', 'api');
    $this->getJson('/api/admin/roles/options')->assertOk()->assertJsonFragment(['name' => 'roles.manage']);
    $this->postJson('/api/admin/roles', [
        'name' => 'role-manager', 'permission_ids' => [$management->id],
    ])->assertCreated();

    $this->putJson('/api/admin/roles/assignment', [
        'email' => $engineer->email, 'role_id' => Role::findByName('superadmin', 'api')->id,
    ])->assertForbidden();
});

it('does not give other accounts engineer powers or let them change the engineer role', function () {
    config(['rbac.software_engineer_emails' => ['binzabirtareq@gmail.com']]);
    $engineer = User::factory()->create(['email' => 'binzabirtareq@gmail.com', 'role' => 'user']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api')->putJson('/api/admin/roles/assignment', [
        'email' => $engineer->email, 'role_id' => Role::findByName('editor', 'api')->id,
    ])->assertForbidden();
    config(['rbac.software_engineer_emails' => []]);
    expect($engineer->isSoftwareEngineer())->toBeFalse();
    $this->actingAs($engineer, 'api')->getJson('/api/admin/roles')->assertForbidden();
});


it('allows the software engineer to delete other users including superadmins', function () {
    config(['rbac.software_engineer_emails' => ['engineer@example.com']]);
    $engineer = User::factory()->create(['email' => 'engineer@example.com', 'role' => 'software_engineer']);
    $target = User::factory()->create(['role' => 'superadmin']);
    $this->actingAs($engineer, 'api')->deleteJson('/api/admin/users/'.$target->id)->assertOk();
    $this->assertDatabaseMissing('users', ['id' => $target->id]);
});

it('protects self deletion and engineer accounts', function () {
    config(['rbac.software_engineer_emails' => ['engineer@example.com']]);
    $engineer = User::factory()->create(['email' => 'engineer@example.com', 'role' => 'software_engineer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($engineer, 'api')->deleteJson('/api/admin/users/'.$engineer->id)->assertForbidden();
    $this->actingAs($admin, 'api')->deleteJson('/api/admin/users/'.$engineer->id)->assertForbidden();
    $this->deleteJson('/api/admin/users/'.$admin->id)->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $engineer->id]);
});

it('lets admins delete regular users but protects administrative roles', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $staff = User::factory()->create();
    $otherAdmin = User::factory()->create(['role' => 'admin']);
    $this->actingAs($admin, 'api')->deleteJson('/api/admin/users/'.$staff->id)->assertOk();
    $this->deleteJson('/api/admin/users/'.$otherAdmin->id)->assertForbidden();
    $this->assertDatabaseMissing('users', ['id' => $staff->id]);
});

it('denies user deletion without role management permission', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();
    $this->actingAs($user, 'api')->deleteJson('/api/admin/users/'.$target->id)->assertForbidden();
    $this->assertDatabaseHas('users', ['id' => $target->id]);
});


it('locks the software engineer role for every actor including the engineer', function () {
    config(['rbac.software_engineer_emails' => ['engineer@example.com', 'second-engineer@example.com']]);
    $engineer = User::factory()->create(['email' => 'engineer@example.com', 'role' => 'software_engineer']);
    $secondEngineer = User::factory()->create(['email' => 'second-engineer@example.com', 'role' => 'software_engineer']);
    $admin = User::factory()->create(['role' => 'admin']);
    $superadmin = User::factory()->create(['role' => 'superadmin']);

    foreach ([$engineer, $secondEngineer, $admin, $superadmin] as $actor) {
        $this->actingAs($actor, 'api');
        $this->getJson('/api/admin/users?search='.rawurlencode($engineer->email))
            ->assertOk()->assertJsonPath('data.data.0.can_change_role', false);
        $this->putJson('/api/admin/roles/assignment', [
            'email' => $engineer->email, 'role_id' => Role::findByName('admin', 'api')->id,
        ])->assertForbidden();
    }

    expect($engineer->fresh()->role)->toBe('software_engineer');
});
