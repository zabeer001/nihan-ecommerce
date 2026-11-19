<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\Dashboard\Services\DashboardSummaryService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DashboardMonthlyActivityTest extends TestCase
{
    public function test_orders_use_creation_month_and_sales_use_delivery_month(): void
    {
        config(['database.default' => 'dashboard_test', 'database.connections.dashboard_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]]);
        $this->travelTo(now()->setDate(2026, 2, 15));
        try {
            Schema::create('products', fn (Blueprint $table) => $table->id());
            Schema::create('variants', function (Blueprint $table) {
                $table->id(); $table->boolean('active'); $table->integer('stock');
            });
            Schema::create('orders', function (Blueprint $table) {
                $table->id(); $table->string('status'); $table->integer('total_cents'); $table->timestamps();
            });
            Schema::create('order_status_events', function (Blueprint $table) {
                $table->id(); $table->integer('order_id'); $table->string('status'); $table->timestamp('created_at');
            });
            DB::table('orders')->insert([
                ['id' => 1, 'status' => 'Delivered', 'total_cents' => 10000, 'created_at' => '2025-08-31 12:00:00'],
                ['id' => 2, 'status' => 'Delivered', 'total_cents' => 25000, 'created_at' => '2025-12-10 12:00:00'],
                ['id' => 3, 'status' => 'Pending', 'total_cents' => 90000, 'created_at' => '2026-01-10 12:00:00'],
                ['id' => 4, 'status' => 'Cancelled', 'total_cents' => 90000, 'created_at' => '2026-02-10 12:00:00'],
                ['id' => 5, 'status' => 'Pending', 'total_cents' => 90000, 'created_at' => '2026-03-01 00:00:00'],
            ]);
            DB::table('order_status_events')->insert([
                ['order_id' => 1, 'status' => 'Delivered', 'created_at' => '2025-09-01 00:00:00'],
                ['order_id' => 2, 'status' => 'Delivered', 'created_at' => '2026-01-15 12:00:00'],
                ['order_id' => 2, 'status' => 'Delivered', 'created_at' => '2026-01-16 12:00:00'],
                ['order_id' => 3, 'status' => 'Pending', 'created_at' => '2026-01-10 12:00:00'],
            ]);
            $months = app(DashboardSummaryService::class)->handle()['monthly_activity'];
            $this->assertSame(['Sep 2025', 'Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026', 'Feb 2026'], array_column($months, 'month'));
            $this->assertSame([0, 0, 0, 1, 1, 1], array_column($months, 'orders'));
            $this->assertSame([10000, 0, 0, 0, 25000, 0], array_column($months, 'sales_cents'));
        } finally {
            $this->travelBack();
            DB::purge('dashboard_test');
        }
    }
}
