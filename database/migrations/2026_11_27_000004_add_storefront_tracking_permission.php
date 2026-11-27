<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $permission = Permission::findOrCreate('storefront.manage', 'api');
        foreach (Role::where('guard_name', 'api')->whereIn('name', ['admin', 'superadmin'])->get() as $role) {
            $role->givePermissionTo($permission);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('name', 'storefront.manage')->where('guard_name', 'api')->get()->each->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
