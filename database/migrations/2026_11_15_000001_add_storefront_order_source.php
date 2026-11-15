<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('source')->default('staff');
            $table->string('tracking_hash', 64)->nullable()->unique();
            $table->unsignedBigInteger('created_by')->nullable()->change();
        });

        foreach (['stock_movements', 'order_status_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('orders')->where('source', 'storefront')->exists()) {
            throw new \RuntimeException('Storefront orders must be preserved. Restore a pre-migration backup to roll back this schema.');
        }

        foreach (['stock_movements', 'order_status_events'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('user_id')->nullable(false)->change();
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable(false)->change();
            $table->dropUnique(['tracking_hash']);
            $table->dropColumn(['source', 'tracking_hash']);
        });
    }
};
