<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->primary(['category_id', 'product_id']);
        });

        // Preserve all current assignments before removing the single-category column.
        DB::table('category_product')->insertUsing(
            ['category_id', 'product_id'],
            DB::table('products')->select('category_id', 'id')->whereNotNull('category_id'),
        );
        Schema::table('products', fn (Blueprint $table) => $table->dropConstrainedForeignId('category_id'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
    }

    public function down(): void
    {
        // Rolling back would discard multiple category assignments.
        throw new RuntimeException('Restore a database backup to roll back the category relationship safely.');
    }
};
