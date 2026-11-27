<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefront_settings', fn (Blueprint $table) => $table->string('gtm_container_id', 64)->nullable());
    }

    public function down(): void
    {
        Schema::table('storefront_settings', fn (Blueprint $table) => $table->dropColumn('gtm_container_id'));
    }
};
