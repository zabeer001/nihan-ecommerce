<?php

use App\Models\Item;
use App\Models\Customer;
use App\Models\User;

it('filters searchable items by customer payment currency', function () {
    $user = User::factory()->create(['status' => 'active']);
    Item::create(['item_code' => 'ITEM-BDT', 'item_name' => 'BDT Item', 'currency' => 'BDT']);
    Item::create(['item_code' => 'ITEM-USD', 'item_name' => 'USD Item', 'currency' => 'USD']);

    $this->actingAs($user, 'api')
        ->getJson('/api/items?search=ITEM&currency=USD&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.item_code', 'ITEM-USD')
        ->assertJsonPath('data.data.0.currency', 'USD');

    $this->actingAs($user, 'api')
        ->getJson('/api/items?search=ITEM&currency=BDT&per_page=10')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.item_code', 'ITEM-BDT')
        ->assertJsonPath('data.data.0.currency', 'BDT');
});

it('allows an order item whose catalog currency differs from the customer currency', function () {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'BDT Customer', 'payment_currency' => 'BDT']);
    Item::create(['item_code' => 'USD-ONLY', 'item_name' => 'USD Item', 'currency' => 'USD']);

    $this->actingAs($user, 'api')
        ->postJson('/api/orders', [
            'customer_id' => $customer->id,
            'currency' => 'BDT',
            'items' => [[
                'item_code' => 'USD-ONLY',
                'item_name' => 'USD Item',
                'currency' => 'USD',
                'quantity' => 1,
                'unit_price' => 10,
            ]],
        ])
        ->assertCreated();

    $this->assertDatabaseHas('order_items', [
        'item_code' => 'USD-ONLY',
        'currency' => 'USD',
    ]);
});
