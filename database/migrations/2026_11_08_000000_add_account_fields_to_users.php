<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_frozen')) {
            Schema::table('users', fn (Blueprint $t) => $t->boolean('is_frozen')->default(false));
        }
        if (! Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('avatar_path')->nullable());
        }
    }

    public function down(): void {}
};
