<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const PERMISSIONS = ['customers.view', 'customers.create', 'customers.update', 'customers.delete'];

    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        $roleIds = DB::table('roles')->where('guard_name', 'api')
            ->whereIn('name', config('rbac.administrators'))->pluck('id');

        foreach (self::PERMISSIONS as $name) {
            $permissionId = DB::table('permissions')->insertGetId([
                'name' => $name, 'guard_name' => 'api', 'created_at' => now(), 'updated_at' => now(),
            ]);

            DB::table('role_has_permissions')->insert($roleIds->map(fn ($roleId) => [
                'role_id' => $roleId, 'permission_id' => $permissionId,
            ])->all());
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('guard_name', 'api')->whereIn('name', self::PERMISSIONS)->delete();
        Schema::dropIfExists('customers');
    }
};
