<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('allows invoice creation while a related order is partially delivered', function () {
    $user = User::create([
        'name' => 'Invoice Delivery Guard User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Invoice Delivery Guard Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INVOICE-NOT-DELIVERED',
        'delivery_status' => 'partial',
    ]);

    $this->actingAs($user, 'api')
        ->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'invoice_number' => 'INV-PARTIAL-DELIVERY',
            'total_amount' => 1000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'INV-PARTIAL-DELIVERY');

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => 'INV-PARTIAL-DELIVERY',
    ]);
});

it('allows invoice creation after the related order is fully delivered', function () {
    $user = User::create([
        'name' => 'Invoice Delivered Order User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Invoice Delivered Order Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INVOICE-DELIVERED',
        'delivery_status' => 'delivered',
    ]);

    $this->actingAs($user, 'api')
        ->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'invoice_number' => 'INV-DELIVERY-COMPLETE',
            'total_amount' => 1000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'INV-DELIVERY-COMPLETE');

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => 'INV-DELIVERY-COMPLETE',
    ]);
});

it('allows a pi invoice when related orders have mixed delivery statuses', function () {
    $user = User::create([
        'name' => 'Invoice Multi Order Delivery User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Invoice Multi Order Customer']);
    $deliveredOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INVOICE-MULTI-DELIVERED',
        'delivery_status' => 'delivered',
    ]);
    $partialOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INVOICE-MULTI-PARTIAL',
        'delivery_status' => 'partial',
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $deliveredOrder->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-INVOICE-MULTI-ORDER',
        'grand_total' => 2000,
    ]);
    $proformaInvoice->orders()->sync([$deliveredOrder->id, $partialOrder->id]);

    $this->actingAs($user, 'api')
        ->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'proforma_invoice_id' => $proformaInvoice->id,
            'invoice_number' => 'INV-MULTI-MIXED-DELIVERY',
            'total_amount' => 2000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.invoice_number', 'INV-MULTI-MIXED-DELIVERY');

    $this->assertDatabaseHas('invoices', [
        'invoice_number' => 'INV-MULTI-MIXED-DELIVERY',
    ]);
});
