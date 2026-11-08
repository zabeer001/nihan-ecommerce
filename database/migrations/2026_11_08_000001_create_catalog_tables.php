<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('status')->default('draft');
            $t->timestamps();
        });
        Schema::create('variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('sku')->unique();
            $t->json('attributes');
            $t->string('combination', 64);
            $t->unsignedBigInteger('price_cents');
            $t->unsignedInteger('stock')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['product_id', 'combination']);
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->unsignedInteger('position')->default(0);
            $t->boolean('primary')->default(false);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('submission_key')->unique();
            $t->string('request_hash', 64);
            $t->foreignId('created_by')->constrained('users');
            $t->string('customer_name');
            $t->string('phone');
            $t->string('email')->nullable();
            $t->text('address');
            $t->string('status')->default('Pending');
            $t->string('currency')->default('BDT');
            $t->string('payment_method')->default('COD');
            $t->unsignedBigInteger('subtotal_cents');
            $t->unsignedBigInteger('delivery_cents');
            $t->unsignedBigInteger('total_cents');
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->string('product_name');
            $t->string('sku');
            $t->json('attributes');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('price_cents');
            $t->unsignedBigInteger('total_cents');
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('variant_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->foreignId('order_id')->nullable()->constrained();
            $t->integer('quantity');
            $t->unsignedInteger('balance');
            $t->string('reason');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('order_status_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained();
            $t->foreignId('user_id')->constrained();
            $t->string('status');
            $t->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        foreach (['order_status_events', 'stock_movements', 'order_items', 'orders', 'product_images', 'variants', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
