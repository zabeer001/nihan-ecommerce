<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('stores updates and returns invoice LC fields', function () {
    $user = User::create([
        'name' => 'Invoice LC API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Invoice LC Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INVOICE-LC-FIELDS',
        'delivery_status' => 'delivered',
    ]);

    $createResponse = $this->actingAs($user, 'api')->postJson('/api/invoices', [
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'invoice_number' => 'INV-LC-FIELDS',
        'invoice_date' => '2026-07-29',
        'lc_no' => 'LC-001-2026',
        'lc_expiry_date' => '2026-07-30',
        'total_amount' => 1000,
    ])->assertCreated()
        ->assertJsonPath('data.lc_no', 'LC-001-2026')
        ->assertJsonPath('data.lc_expiry_date', '2026-07-30');

    $invoiceId = $createResponse->json('data.id');

    $this->actingAs($user, 'api')->putJson("/api/invoices/{$invoiceId}", [
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-LC-FIELDS',
        'invoice_date' => '2026-07-29',
        'lc_no' => 'LC-002-2026',
        'lc_expiry_date' => '2026-07-31',
        'total_amount' => 1000,
    ])->assertOk()
        ->assertJsonPath('data.lc_no', 'LC-002-2026')
        ->assertJsonPath('data.lc_expiry_date', '2026-07-31');

    $this->actingAs($user, 'api')->getJson("/api/invoices/{$invoiceId}")
        ->assertOk()
        ->assertJsonPath('data.lc_no', 'LC-002-2026')
        ->assertJsonPath('data.lc_expiry_date', '2026-07-31');

    $this->assertDatabaseHas('invoices', [
        'id' => $invoiceId,
        'lc_no' => 'LC-002-2026',
        'lc_expiry_date' => '2026-07-31',
    ]);
});
