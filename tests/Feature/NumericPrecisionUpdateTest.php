<?php

use App\Models\CompanySetting;
use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanVariantLine;
use App\Models\Item;
use App\Models\OrderItemStyle;
use App\Models\RawMaterialPurchase;
use App\Models\RawMaterialPurchaseItem;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('preserves high precision numerical values when editing a record', function () {
    $user = User::create([
        'name' => 'Numeric Precision User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $item = Item::create([
        'item_code' => 'PRECISION-ITEM',
        'item_name' => 'Precision Item',
        'price' => 1,
        'weight' => 1,
        'currency' => 'BDT',
    ]);

    $this->actingAs($user, 'api')->putJson("/api/items/{$item->id}", [
        'item_code' => 'PRECISION-ITEM',
        'item_name' => 'Precision Item',
        'price' => 9.1276312323,
        'weight' => 1.1234567890,
        'currency' => 'BDT',
    ])->assertOk();

    $item->refresh();

    expect($item->price)->toBe('9.1276312323')
        ->and($item->weight)->toBe('1.1234567890');
});

it('does not round numerical values through model decimal casts', function () {
    $modelsAndAttributes = [
        [new CompanySetting, 'usd_to_bdt_rate'],
        [new DeliveryChallan, 'delivery_qty'],
        [new DeliveryChallan, 'gross_weight'],
        [new DeliveryChallan, 'net_weight'],
        [new DeliveryChallanVariantLine, 'quantity'],
        [new OrderItemStyle, 'quantity'],
        [new OrderItemStyle, 'unit_price'],
        [new OrderItemStyle, 'line_total'],
        [new RawMaterialPurchase, 'exchange_rate'],
        [new RawMaterialPurchase, 'grand_total'],
        [new RawMaterialPurchaseItem, 'quantity'],
        [new RawMaterialPurchaseItem, 'received_quantity'],
    ];

    foreach ($modelsAndAttributes as [$model, $attribute]) {
        $model->{$attribute} = '9.1276312323';

        expect($model->{$attribute})->toBe('9.1276312323');
    }
});
