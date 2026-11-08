<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns the number of orders that have no proforma invoice', function () {
    $user = User::create([
        'name' => 'PI Summary API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'PI Summary Customer']);

    $pendingOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PI-PENDING',
    ]);
    $primaryOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PI-PRIMARY',
    ]);
    $additionalOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PI-ADDITIONAL',
    ]);

    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $primaryOrder->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-SUMMARY-001',
        'currency' => 'BDT',
        'grand_total' => 1200,
    ]);
    $proformaInvoice->orders()->attach([$primaryOrder->id, $additionalOrder->id]);

    $this->actingAs($user, 'api')
        ->getJson('/api/proforma-invoices')
        ->assertOk()
        ->assertJsonPath('data.pending_pi_orders', 1);

    $this->actingAs($user, 'api')
        ->getJson("/api/proforma-invoices?order_id={$additionalOrder->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $proformaInvoice->id);

    $this->actingAs($user, 'api')
        ->getJson("/api/proforma-invoices?order_id={$pendingOrder->id}")
        ->assertOk()
        ->assertJsonCount(0, 'data.data');

    expect($pendingOrder->exists)->toBeTrue();
});
