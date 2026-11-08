<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('can return only orders that do not have a proforma invoice', function () {
    $user = User::create([
        'name' => 'Order Filter API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Order Filter Customer']);

    $availableOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-AVAILABLE-FOR-PI',
    ]);
    $primaryPiOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PRIMARY-PI',
    ]);
    $additionalPiOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-ADDITIONAL-PI',
    ]);

    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $primaryPiOrder->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-ORDER-FILTER-001',
        'currency' => 'BDT',
        'grand_total' => 1200,
    ]);
    $proformaInvoice->orders()->attach($additionalPiOrder->id);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/orders?without_proforma_invoice=1&per_page=100')
        ->assertOk();

    $orderIds = collect($response->json('data.data'))->pluck('id');

    expect($orderIds)
        ->toContain($availableOrder->id)
        ->not->toContain($primaryPiOrder->id)
        ->not->toContain($additionalPiOrder->id);
});
