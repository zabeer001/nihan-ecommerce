<?php

use App\Models\Customer;
use App\Models\User;

it('derives order and item quantities only from variants', function () {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'Variant Quantity Customer']);

    $response = $this->actingAs($user, 'api')->postJson('/api/orders', [
        'customer_id' => $customer->id,
        'order_number' => 'ORD-VARIANT-QTY',
        'ordered_qty' => 999,
        'items' => [[
            'item_name' => 'Variant Item',
            'quantity' => 888,
            'unit_price' => 10,
            'styles' => [
                ['style_name' => 'Small', 'quantity' => 25],
                ['style_name' => 'Large', 'quantity' => 35],
            ],
        ]],
    ])->assertCreated();

    $orderId = $response->json('data.id');

    $this->assertDatabaseHas('orders', [
        'id' => $orderId,
        'ordered_qty' => 60,
    ]);
    $this->assertDatabaseHas('order_items', [
        'order_id' => $orderId,
        'quantity' => 60,
        'line_total' => 600,
    ]);
});
