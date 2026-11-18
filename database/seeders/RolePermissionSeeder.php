<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $newPermissions = [];
        $permissions = array_values(array_unique([...config('rbac.permissions'), 'customers.view', 'customers.create', 'customers.update', 'customers.delete']));
        foreach ($permissions as $name) {
            $permission = Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
            if ($permission->wasRecentlyCreated) $newPermissions[] = $name;
        }
        foreach (config('rbac.system_roles') as $name) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'api']);
            if (in_array($name, config('rbac.administrators'), true)) {
                // Preserve customized grants; new administrators get all permissions.
                $role->givePermissionTo($role->wasRecentlyCreated ? $permissions : $newPermissions);
            }
        }
        foreach ([
            'demo-catalog-editor' => ['dashboard.view', 'categories.view', 'categories.create', 'categories.update', 'products.view', 'products.create', 'products.update', 'inventory.view', 'homepage.manage'],
            'demo-order-manager' => ['dashboard.view', 'orders.view', 'orders.create', 'orders.update', 'customers.view', 'customers.create', 'customers.update', 'products.view', 'inventory.view'],
        ] as $name => $permissions) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'api'], ['description' => 'Demo role for testing staff access.']);
            if ($role->wasRecentlyCreated) $role->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
