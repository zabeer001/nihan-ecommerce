<?php

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemStyle;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function variantDeliveryUser(): User
{
    return User::create([
        'name' => 'Variant Delivery User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
}

/**
 * @return array{0: Customer, 1: Order, 2: OrderItemStyle, 3: OrderItemStyle}
 */
function variantDeliveryOrder(): array
{
    $customer = Customer::create(['name' => 'Variant Delivery Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => fake()->unique()->bothify('ORD-VARIANT-####'),
        'ordered_qty' => 150,
        'delivery_status' => 'pending',
    ]);
    ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => fake()->unique()->bothify('PI-VARIANT-####'),
        'status' => 'draft',
    ]);
    $item = OrderItem::create([
        'order_id' => $order->id,
        'item_code' => 'ITEM-1',
        'item_name' => 'Test Item',
        'quantity' => 150,
        'unit' => 'PC',
        'unit_price' => 1,
        'line_total' => 150,
    ]);
    $first = $item->styles()->create([
        'style_name' => 'Style 1',
        'style_code' => 'STYLE-1',
        'quantity' => 100,
        'custom_fields' => ['Color' => 'Red', 'Quantity' => '100'],
    ]);
    $second = $item->styles()->create([
        'style_name' => 'Style 2',
        'quantity' => 50,
        'custom_fields' => ['Color' => 'Blue', 'Quantity' => '50'],
    ]);

    return [$customer, $order, $first, $second];
}

it('requires a proforma invoice before creating a delivery challan', function () {
    $user = variantDeliveryUser();
    $customer = Customer::create(['name' => 'Customer Without PI']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-WITHOUT-PI',
        'ordered_qty' => 10,
        'delivery_status' => 'pending',
    ]);

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-WITHOUT-PI',
        'status' => 'draft',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proforma_invoice_id');

    $this->assertDatabaseMissing('delivery_challans', [
        'challan_number' => 'CH-WITHOUT-PI',
    ]);
});

it('stores variant allocations and derives challan totals', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first, $second] = variantDeliveryOrder();

    $response = $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-VARIANT-1',
        'challan_date' => '2026-07-29',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 60],
            ['order_item_style_id' => $second->id, 'quantity' => 20],
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.delivery_qty', '80.00')
        ->assertJsonPath('data.delivery_unit', 'PC')
        ->assertJsonCount(2, 'data.variant_lines');

    $this->assertDatabaseHas('delivery_challan_variant_lines', [
        'order_item_style_id' => $first->id,
        'quantity' => 60,
        'item_name_snapshot' => 'Test Item',
    ]);
    expect($order->fresh()->delivery_status)->toBe('partial');
});

it('counts only delivered challans and blocks variant over-delivery', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first] = variantDeliveryOrder();

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-DRAFT',
        'status' => 'draft',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 90],
        ],
    ])->assertCreated();

    expect($order->fresh()->delivery_status)->toBe('pending');

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-DELIVERED',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 70],
        ],
    ])->assertCreated();

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-OVER',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 31],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('variant_lines.0.quantity');
});

it('excludes the current challan during updates and synchronizes order status', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first, $second] = variantDeliveryOrder();

    $created = $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-EDIT',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 60],
            ['order_item_style_id' => $second->id, 'quantity' => 20],
        ],
    ])->assertCreated()->json('data');

    $this->actingAs($user, 'api')->putJson("/api/delivery-challans/{$created['id']}", [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-EDIT',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 100],
            ['order_item_style_id' => $second->id, 'quantity' => 50],
        ],
    ])->assertOk()
        ->assertJsonPath('data.delivery_qty', '150.00');

    expect($order->fresh()->delivery_status)->toBe('delivered');

    $this->actingAs($user, 'api')->putJson("/api/delivery-challans/{$created['id']}", [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-EDIT',
        'status' => 'cancelled',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 100],
            ['order_item_style_id' => $second->id, 'quantity' => 50],
        ],
    ])->assertOk();

    expect($order->fresh()->delivery_status)->toBe('pending');

    $this->actingAs($user, 'api')->putJson("/api/delivery-challans/{$created['id']}", [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-EDIT',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 100],
            ['order_item_style_id' => $second->id, 'quantity' => 50],
        ],
    ])->assertOk();

    expect($order->fresh()->delivery_status)->toBe('delivered');

    $this->actingAs($user, 'api')->deleteJson("/api/delivery-challans/{$created['id']}")
        ->assertOk();

    expect($order->fresh()->delivery_status)->toBe('pending');
});

it('requires every active challan and the full order quantity before marking an order delivered', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first, $second] = variantDeliveryOrder();

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-FIRST-HALF',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 50],
            ['order_item_style_id' => $second->id, 'quantity' => 25],
        ],
    ])->assertCreated();

    $draft = $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-SECOND-HALF',
        'status' => 'draft',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 50],
            ['order_item_style_id' => $second->id, 'quantity' => 25],
        ],
    ])->assertCreated()->json('data');

    expect($order->fresh()->delivery_status)->toBe('partial');

    $this->actingAs($user, 'api')->putJson("/api/delivery-challans/{$draft['id']}", [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-SECOND-HALF',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 50],
            ['order_item_style_id' => $second->id, 'quantity' => 25],
        ],
    ])->assertOk();

    expect($order->fresh()->delivery_status)->toBe('delivered');

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-AFTER-COMPLETE',
        'status' => 'draft',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 1],
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('order_id');
});

it('ignores cancelled challans when determining whether every active challan is delivered', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first, $second] = variantDeliveryOrder();

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-CANCELLED-IGNORED',
        'status' => 'cancelled',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 10],
        ],
    ])->assertCreated();

    $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-ACTIVE-COMPLETE',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $first->id, 'quantity' => 100],
            ['order_item_style_id' => $second->id, 'quantity' => 50],
        ],
    ])->assertCreated();

    expect($order->fresh()->delivery_status)->toBe('delivered');
});

it('recalculates both orders when a challan is reassigned', function () {
    $user = variantDeliveryUser();
    [$firstCustomer, $firstOrder, $firstStyle, $firstSecondStyle] = variantDeliveryOrder();
    [$secondCustomer, $secondOrder, $secondStyle, $secondSecondStyle] = variantDeliveryOrder();

    $challan = $this->actingAs($user, 'api')->postJson('/api/delivery-challans', [
        'customer_id' => $firstCustomer->id,
        'order_id' => $firstOrder->id,
        'challan_number' => 'CH-REASSIGNED',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $firstStyle->id, 'quantity' => 100],
            ['order_item_style_id' => $firstSecondStyle->id, 'quantity' => 50],
        ],
    ])->assertCreated()->json('data');

    expect($firstOrder->fresh()->delivery_status)->toBe('delivered')
        ->and($secondOrder->fresh()->delivery_status)->toBe('pending');

    $this->actingAs($user, 'api')->putJson("/api/delivery-challans/{$challan['id']}", [
        'customer_id' => $secondCustomer->id,
        'order_id' => $secondOrder->id,
        'challan_number' => 'CH-REASSIGNED',
        'status' => 'delivered',
        'variant_lines' => [
            ['order_item_style_id' => $secondStyle->id, 'quantity' => 100],
            ['order_item_style_id' => $secondSecondStyle->id, 'quantity' => 50],
        ],
    ])->assertOk();

    expect($firstOrder->fresh()->delivery_status)->toBe('pending')
        ->and($secondOrder->fresh()->delivery_status)->toBe('delivered');
});

it('returns prior challan history and remaining inventory on the order', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first] = variantDeliveryOrder();
    $challan = DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-HISTORY',
        'status' => 'delivered',
        'delivery_qty' => 25,
        'delivery_unit' => 'PC',
    ]);
    $challan->variantLines()->create([
        'order_item_style_id' => $first->id,
        'quantity' => 25,
        'unit' => 'PC',
        'item_name_snapshot' => 'Test Item',
        'variant_name_snapshot' => 'Style 1',
    ]);

    $this->actingAs($user, 'api')->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.variant_delivery_inventory.variants.0.delivered_qty', 25)
        ->assertJsonPath('data.variant_delivery_inventory.variants.0.remaining_qty', 75)
        ->assertJsonPath('data.variant_delivery_inventory.variants.0.variant_name', 'Style 1')
        ->assertJsonPath('data.variant_delivery_inventory.variants.0.variant_code', 'STYLE-1')
        ->assertJsonPath('data.variant_delivery_inventory.variants.0.history.0.challan_number', 'CH-HISTORY');
});

it('preserves variant ids during order edits and prevents deleting delivered variants', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first, $second] = variantDeliveryOrder();
    $item = $first->orderItem;
    $challan = DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'challan_number' => 'CH-STABLE',
        'status' => 'delivered',
        'delivery_qty' => 10,
        'delivery_unit' => 'PC',
    ]);
    $challan->variantLines()->create([
        'order_item_style_id' => $first->id,
        'quantity' => 10,
        'unit' => 'PC',
    ]);

    $orderPayload = [
        'customer_id' => $customer->id,
        'order_number' => $order->order_number,
        'items' => [[
            'id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => 'Updated Item',
            'quantity' => 150,
            'unit' => 'PC',
            'unit_price' => 1,
            'styles' => [
                [
                    'id' => $first->id,
                    'style_name' => $first->style_name,
                    'quantity' => 100,
                    'custom_fields' => ['Color' => 'Scarlet', 'Quantity' => '100'],
                ],
                [
                    'id' => $second->id,
                    'style_name' => $second->style_name,
                    'quantity' => 50,
                    'custom_fields' => ['Color' => 'Blue', 'Quantity' => '50'],
                ],
            ],
        ]],
    ];

    $this->actingAs($user, 'api')->putJson("/api/orders/{$order->id}", $orderPayload)
        ->assertOk();

    expect(OrderItemStyle::find($first->id)?->custom_fields['Color'])->toBe('Scarlet');

    $orderPayload['items'][0]['styles'] = [[
        'id' => $second->id,
        'style_name' => $second->style_name,
        'quantity' => 50,
        'custom_fields' => ['Color' => 'Blue', 'Quantity' => '50'],
    ]];

    $this->actingAs($user, 'api')->putJson("/api/orders/{$order->id}", $orderPayload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('items');

    expect(OrderItemStyle::find($first->id))->not->toBeNull();
});

it('preserves custom field insertion order in edit and show responses', function () {
    $user = variantDeliveryUser();
    [$customer, $order, $first] = variantDeliveryOrder();
    $item = $first->orderItem;
    $customFields = [
        'ZABEER' => 'First field',
        'SI' => '45',
        'PO' => '1',
        'SIZE' => 'M',
        'COLOR' => 'Black',
        'QUANTITY' => '100',
    ];

    $this->actingAs($user, 'api')->putJson("/api/orders/{$order->id}", [
        'customer_id' => $customer->id,
        'order_number' => $order->order_number,
        'items' => [[
            'id' => $item->id,
            'item_code' => $item->item_code,
            'item_name' => $item->item_name,
            'quantity' => 100,
            'unit' => 'PC',
            'unit_price' => 1,
            'styles' => [[
                'id' => $first->id,
                'style_name' => $first->style_name,
                'quantity' => 100,
                'custom_fields' => $customFields,
            ]],
        ]],
    ])->assertOk();

    $savedStyle = OrderItemStyle::findOrFail($first->id);
    expect($savedStyle->custom_fields_order)->toBe(array_keys($customFields))
        ->and(array_keys($savedStyle->custom_fields))->toBe(array_keys($customFields));

    $shownFields = $this->actingAs($user, 'api')
        ->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->json('data.sections.order_items.0.styles.0.custom_fields');

    expect(array_keys($shownFields))->toBe(array_keys($customFields));
});
