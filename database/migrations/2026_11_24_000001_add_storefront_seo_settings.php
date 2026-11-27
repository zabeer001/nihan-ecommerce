<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_pixel_id', 30)->nullable();
        });
        Schema::create('storefront_settings', function (Blueprint $table) {
            $table->id();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('google_site_verification')->nullable();
            $table->timestamps();
        });
        DB::table('storefront_settings')->insert([
            'id' => 1,
            'meta_title' => 'NIHAN – Car Fragrance',
            'meta_description' => 'Explore the NIHAN AI Car Aroma Diffuser and refillable car fragrances. Delivering across Bangladesh.',
            'google_site_verification' => '02FRVzx5AfvYrAPdlvIKxOh5XneV0BXojF1QKzY6TFY',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_settings');
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['meta_title', 'meta_description', 'meta_pixel_id']));
    }
};
