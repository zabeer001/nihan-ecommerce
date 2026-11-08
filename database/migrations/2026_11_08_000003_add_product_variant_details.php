<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('product_code')->nullable()->unique();
            foreach (['brand', 'model', 'color', 'unit', 'product_type', 'tracking_type'] as $field) {
                $table->string($field)->nullable();
            }
        });
        Schema::table('variants', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('barcode')->nullable();
            foreach (['purchase_price_cents', 'dealer_price_cents', 'corporate_price_cents'] as $field) {
                $table->unsignedBigInteger($field)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('variants', fn (Blueprint $table) => $table->dropColumn(['name', 'barcode', 'purchase_price_cents', 'dealer_price_cents', 'corporate_price_cents']));
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['product_code']);
            $table->dropColumn(['product_code', 'brand', 'model', 'color', 'unit', 'product_type', 'tracking_type']);
        });
    }
};
