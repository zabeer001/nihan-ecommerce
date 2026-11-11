<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->foreignId('variant_id')->nullable()->constrained()->cascadeOnDelete();
        });

        Schema::create('product_image_uploads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->index();
            $table->foreignId('product_id')->nullable();
            $table->string('path');
            $table->timestamp('expires_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_image_uploads');
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropConstrainedForeignId('variant_id');
        });
    }
};
