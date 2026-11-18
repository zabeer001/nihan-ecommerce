<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('variants', fn (Blueprint $table) => $table->unsignedBigInteger('compare_at_price_cents')->nullable());
    }

    public function down(): void
    {
        Schema::table('variants', fn (Blueprint $table) => $table->dropColumn('compare_at_price_cents'));
    }
};
