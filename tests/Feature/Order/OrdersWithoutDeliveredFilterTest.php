<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('can return only orders that are not fully delivered', function () {
    $user = User::create([
        'name' => 'Delivery Status Filter User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Delivery Status Filter Customer']);

    $pendingOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-DELIVERY-PENDING',
        'delivery_status' => 'pending',
    ]);
    $partialOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-DELIVERY-PARTIAL',
        'delivery_status' => 'partial',
    ]);
    $deliveredOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-DELIVERY-COMPLETE',
        'delivery_status' => 'delivered',
    ]);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/orders?without_delivered=1&per_page=100')
        ->assertOk();

    $orderIds = collect($response->json('data.data'))->pluck('id');

    expect($orderIds)
        ->toContain($pendingOrder->id)
        ->toContain($partialOrder->id)
        ->not->toContain($deliveredOrder->id);
});
