<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\RawMaterial;
use App\Models\RawMaterialStockLedger;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function apiUser(): User
{
    return User::create([
        'name' => 'API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
}

it('creates a cash local material purchase and records stock receipt', function () {
    $user = apiUser();
    $supplier = Supplier::create(['company_name' => 'Local Trims Ltd.', 'supplier_type' => 'local']);
    $material = RawMaterial::create(['name' => 'Button', 'type' => 'accessory', 'unit' => 'pcs']);

    $this->actingAs($user, 'api')->postJson('/api/raw-material-purchases', [
        'supplier_id' => $supplier->id,
        'purchase_number' => 'RMP-001',
        'source_type' => 'local_purchase',
        'payment_mode' => 'cash',
        'purchase_date' => now()->toDateString(),
        'currency' => 'BDT',
        'status' => 'received',
        'items' => [
            ['raw_material_id' => $material->id, 'quantity' => 1000, 'received_quantity' => 800, 'unit_price' => 2],
        ],
    ])->assertCreated()
        ->assertJsonPath('data.purchase_number', 'RMP-001')
        ->assertJsonPath('data.items.0.raw_material_id', $material->id);

    expect((float) RawMaterialStockLedger::where('raw_material_id', $material->id)->sum('quantity_in'))->toBe(800.0);
});

it('requires own lc fields for own lc purchases', function () {
    $user = apiUser();
    $supplier = Supplier::create(['company_name' => 'Foreign Fabric Co.', 'supplier_type' => 'foreign']);
    $material = RawMaterial::create(['name' => 'Cotton Fabric', 'type' => 'fabric', 'unit' => 'yds']);

    $this->actingAs($user, 'api')->postJson('/api/raw-material-purchases', [
        'supplier_id' => $supplier->id,
        'purchase_number' => 'RMP-LC-001',
        'source_type' => 'import',
        'payment_mode' => 'lc',
        'lc_type' => 'own_lc',
        'currency' => 'USD',
        'status' => 'draft',
        'items' => [
            ['raw_material_id' => $material->id, 'quantity' => 100, 'received_quantity' => 0, 'unit_price' => 3],
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['lc_number', 'lc_bank_name', 'lc_value', 'lc_currency']);
});

it('prevents allocation beyond available stock', function () {
    $user = apiUser();
    $supplier = Supplier::create(['company_name' => 'Local Yarn Ltd.', 'supplier_type' => 'local']);
    $material = RawMaterial::create(['name' => 'Yarn', 'type' => 'yarn', 'unit' => 'kg']);
    $customer = Customer::create(['name' => 'ABC Garments Ltd.']);
    $order = Order::create(['customer_id' => $customer->id, 'order_number' => 'ORD-RM-001']);

    $this->actingAs($user, 'api')->postJson('/api/raw-material-purchases', [
        'supplier_id' => $supplier->id,
        'purchase_number' => 'RMP-YARN-001',
        'source_type' => 'local_purchase',
        'payment_mode' => 'cash',
        'currency' => 'BDT',
        'status' => 'received',
        'items' => [
            ['raw_material_id' => $material->id, 'quantity' => 10, 'received_quantity' => 10, 'unit_price' => 100],
        ],
    ])->assertCreated();

    $this->actingAs($user, 'api')->postJson('/api/raw-material-allocations', [
        'raw_material_id' => $material->id,
        'order_id' => $order->id,
        'allocated_quantity' => 11,
        'status' => 'reserved',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['allocated_quantity']);
});

it('shows raw material allocations on order detail', function () {
    $user = apiUser();
    $supplier = Supplier::create(['company_name' => 'Packaging Ltd.', 'supplier_type' => 'local']);
    $material = RawMaterial::create(['name' => 'Poly Bag', 'type' => 'packaging', 'unit' => 'pcs']);
    $customer = Customer::create(['name' => 'ABC Garments Ltd.']);
    $order = Order::create(['customer_id' => $customer->id, 'order_number' => 'ORD-RM-002']);
    $orderItem = OrderItem::create(['order_id' => $order->id, 'item_name' => 'T-Shirt', 'quantity' => 100, 'unit_price' => 10, 'line_total' => 1000]);

    $purchaseResponse = $this->actingAs($user, 'api')->postJson('/api/raw-material-purchases', [
        'supplier_id' => $supplier->id,
        'purchase_number' => 'RMP-PACK-001',
        'source_type' => 'local_purchase',
        'payment_mode' => 'cash',
        'currency' => 'BDT',
        'status' => 'received',
        'items' => [
            ['raw_material_id' => $material->id, 'quantity' => 100, 'received_quantity' => 100, 'unit_price' => 1],
        ],
    ])->assertCreated();

    $purchaseItemId = $purchaseResponse->json('data.items.0.id');

    $this->actingAs($user, 'api')->postJson('/api/raw-material-allocations', [
        'raw_material_purchase_item_id' => $purchaseItemId,
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'allocated_quantity' => 25,
        'consumed_quantity' => 10,
        'status' => 'reserved',
    ])->assertCreated();

    $this->actingAs($user, 'api')->getJson("/api/orders/{$order->id}")
        ->assertOk()
        ->assertJsonPath('data.sections.raw_material_allocations.0.raw_material.name', 'Poly Bag')
        ->assertJsonPath('data.raw_material_summary.allocated_quantity', 25);
});
