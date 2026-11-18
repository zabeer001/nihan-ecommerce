<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homepage_contents', function (Blueprint $table) {
            $table->id();
            $table->string('content_type', 32);
            $table->string('placement', 40);
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_published')->default(false);
            $table->string('media_type', 16);
            $table->string('media_path');
            $table->string('poster_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('heading')->nullable();
            $table->text('description')->nullable();
            $table->string('button_text')->nullable();
            $table->string('button_url')->nullable();
            $table->timestamps();
            $table->index(['is_published', 'placement', 'position']);
        });

        $now = now();
        DB::table('permissions')->updateOrInsert(
            ['name' => 'homepage.manage', 'guard_name' => 'api'],
            ['created_at' => $now, 'updated_at' => $now],
        );
        $permissionId = DB::table('permissions')->where('name', 'homepage.manage')->where('guard_name', 'api')->value('id');
        $admins = DB::table('roles')->where('guard_name', 'api')->whereIn('name', config('rbac.administrators'))->pluck('id');
        foreach ($admins as $roleId) {
            DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId]);
        }

        $disk = Storage::disk('public');
        foreach ([
            ['hero.mp4', 'homepage/hero.mp4'],
            ['cat.jpeg', 'homepage/cat.jpeg'],
        ] as [$fixture, $path]) {
            if (! $disk->exists($path)) {
                $disk->put($path, file_get_contents(database_path('seeders/fixtures/homepage/'.$fixture)));
            }
        }
        $now = now();
        DB::table('homepage_contents')->insert([
            ['content_type' => 'hero_slide', 'placement' => 'hero', 'position' => 0, 'is_published' => true,
                'media_type' => 'video', 'media_path' => 'homepage/hero.mp4', 'poster_path' => null, 'alt_text' => null,
                'heading' => null, 'description' => null, 'button_text' => null, 'button_url' => null,
                'created_at' => $now, 'updated_at' => $now],
            ['content_type' => 'image_block', 'placement' => 'collection_interlude', 'position' => 0, 'is_published' => true,
                'media_type' => 'image', 'media_path' => 'homepage/cat.jpeg', 'poster_path' => null,
                'alt_text' => 'Illustration of a sleeping orange cat beside four colorful fragrance bottles',
                'heading' => null, 'description' => null, 'button_text' => null, 'button_url' => null,
                'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('homepage_contents');
        $permissionId = DB::table('permissions')->where('name', 'homepage.manage')->where('guard_name', 'api')->value('id');
        if ($permissionId) {
            DB::table('role_has_permissions')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
