<?php

namespace Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderCustomerBackfillTest extends TestCase
{
    public function test_existing_orders_are_linked_and_latest_contact_details_are_imported_without_overwriting_saved_customers(): void
    {
        config(['database.default' => 'customer_backfill', 'database.connections.customer_backfill' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]]);
        try {
            Schema::create('customers', function (Blueprint $table) {
                $table->id(); $table->string('name'); $table->string('phone');
                $table->string('email')->nullable(); $table->text('address')->nullable(); $table->timestamps();
            });
            Schema::create('orders', function (Blueprint $table) {
                $table->id(); $table->string('customer_name'); $table->string('phone');
                $table->string('email')->nullable(); $table->text('address'); $table->timestamps();
            });
            $savedId = DB::table('customers')->insertGetId(['name' => 'Saved', 'phone' => '+880 (1712) 345678', 'address' => 'Saved address']);
            $common = ['email' => 'buyer@example.test', 'updated_at' => '2026-11-18 10:00:00'];
            DB::table('orders')->insert([
                [...$common, 'customer_name' => 'Changed name', 'phone' => '01712345678', 'address' => 'Order address', 'created_at' => '2026-11-18 09:00:00'],
                [...$common, 'customer_name' => 'Older name', 'phone' => '01812345678', 'address' => 'Older address', 'created_at' => '2026-11-18 09:01:00'],
                [...$common, 'customer_name' => 'Latest name', 'phone' => '+8801812345678', 'address' => 'Latest address', 'created_at' => '2026-11-18 09:02:00'],
            ]);
            $migration = require database_path('migrations/2026_11_18_000004_link_orders_to_customers.php');
            $migration->up();
            $this->assertSame(2, DB::table('customers')->count());
            $saved = DB::table('customers')->find($savedId);
            $this->assertSame('Saved', $saved->name);
            $this->assertSame('Saved address', $saved->address);
            $orders = DB::table('orders')->orderBy('id')->get();
            $this->assertSame($savedId, $orders[0]->customer_id);
            $this->assertSame($orders[1]->customer_id, $orders[2]->customer_id);
            $imported = DB::table('customers')->find($orders[1]->customer_id);
            $this->assertSame('Latest name', $imported->name);
            $this->assertSame('Latest address', $imported->address);
            $this->assertSame('Older address', $orders[1]->address);
            $migration->down();
            $this->assertSame(2, DB::table('customers')->count());
            $this->assertSame(3, DB::table('orders')->count());
        } finally {
            DB::purge('customer_backfill');
        }
    }
}
