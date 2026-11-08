<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProformaInvoice;
use App\Models\User;
use App\Services\Orders\OrderTotalService;
use Illuminate\Support\Facades\Hash;

it('stores the order total in the order currency', function () {
    CompanySetting::create(['usd_to_bdt_rate' => 120]);
    $customer = Customer::create(['name' => 'Total Amount Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-TOTAL-AMOUNT',
        'currency' => 'BDT',
    ]);

    $order->items()->createMany([
        ['item_name' => 'BDT Item', 'currency' => 'BDT', 'line_total' => 1000],
        ['item_name' => 'USD Item', 'currency' => 'USD', 'line_total' => 25],
    ]);

    app(OrderTotalService::class)->recalculate($order);

    expect((float) $order->fresh()->total_amount)->toBe(4000.0);
});

it('converts bdt item totals when the order currency is usd', function () {
    CompanySetting::create(['usd_to_bdt_rate' => 120]);
    $customer = Customer::create(['name' => 'USD Total Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-USD-TOTAL',
        'currency' => 'USD',
    ]);

    $order->items()->createMany([
        ['item_name' => 'USD Item', 'currency' => 'USD', 'line_total' => 25],
        ['item_name' => 'BDT Item', 'currency' => 'BDT', 'line_total' => 1200],
    ]);

    app(OrderTotalService::class)->recalculate($order);

    expect((float) $order->fresh()->total_amount)->toBe(35.0);
});

it('derives item, order, and linked PI totals from quantity times unit price', function () {
    $user = User::create([
        'name' => 'Order Amount API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Automatic USD Total Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-AUTOMATIC-USD-TOTAL',
        'currency' => 'USD',
        'total_amount' => 10,
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Automatic total item',
        'currency' => 'USD',
        'quantity' => 2,
        'unit_price' => 5,
        'line_total' => 10,
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-AUTOMATIC-USD-TOTAL',
        'currency' => 'USD',
        'subtotal' => 10,
        'bank_document_charge' => 5,
        'grand_total' => 15,
    ]);
    $proformaInvoice->orders()->sync([$order->id]);

    $this->actingAs($user, 'api')->putJson("/api/order-items/{$item->id}", [
        'order_id' => $order->id,
        'item_name' => 'Automatic total item',
        'currency' => 'USD',
        'quantity' => 3000,
        'unit_price' => 9,
        'line_total' => 1,
    ])->assertOk()
        ->assertJsonPath('data.line_total', '27000.00');

    $this->assertDatabaseHas('order_items', [
        'id' => $item->id,
        'line_total' => 27000,
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'ordered_qty' => 3000,
        'total_amount' => 27000,
    ]);
    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoice->id,
        'subtotal' => 27000,
        'grand_total' => 27005,
        'due_total' => 27005,
    ]);
});
