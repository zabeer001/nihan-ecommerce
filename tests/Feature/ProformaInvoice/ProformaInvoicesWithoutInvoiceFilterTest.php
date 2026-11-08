<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('can return only proforma invoices whose pi and orders have not been invoiced', function () {
    $user = User::create([
        'name' => 'Available PI Filter User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Available PI Filter Customer']);

    $makeOrder = fn (string $number) => Order::create([
        'customer_id' => $customer->id,
        'order_number' => $number,
    ]);
    $makePi = fn (Order $order, string $number) => ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => $number,
        'currency' => 'BDT',
        'grand_total' => 1000,
    ]);
    $makeInvoice = fn (string $number, ?ProformaInvoice $pi = null) => Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $pi?->id,
        'invoice_number' => $number,
        'total_amount' => 1000,
    ]);

    $availablePi = $makePi($makeOrder('ORD-PI-AVAILABLE'), 'PI-AVAILABLE');

    $directlyInvoicedPi = $makePi($makeOrder('ORD-PI-DIRECT-INVOICE'), 'PI-DIRECT-INVOICE');
    $makeInvoice('INV-DIRECT-PI', $directlyInvoicedPi);

    $primaryOrder = $makeOrder('ORD-PI-PRIMARY-INVOICE');
    $primaryOrderPi = $makePi($primaryOrder, 'PI-PRIMARY-ORDER-INVOICE');
    $primaryOrder->update(['invoice_id' => $makeInvoice('INV-PRIMARY-ORDER')->id]);

    $additionalPrimaryOrder = $makeOrder('ORD-PI-ADDITIONAL-PRIMARY');
    $additionalOrder = $makeOrder('ORD-PI-ADDITIONAL-INVOICE');
    $additionalOrderPi = $makePi($additionalPrimaryOrder, 'PI-ADDITIONAL-ORDER-INVOICE');
    $additionalOrderPi->orders()->attach($additionalOrder->id);
    $additionalOrderInvoice = $makeInvoice('INV-ADDITIONAL-ORDER');
    $additionalOrderInvoice->orders()->attach($additionalOrder->id);

    $response = $this->actingAs($user, 'api')
        ->getJson('/api/proforma-invoices?without_invoice=1&per_page=100')
        ->assertOk();

    $proformaInvoiceIds = collect($response->json('data.data'))->pluck('id');

    expect($proformaInvoiceIds)
        ->toContain($availablePi->id)
        ->not->toContain($directlyInvoicedPi->id)
        ->not->toContain($primaryOrderPi->id)
        ->not->toContain($additionalOrderPi->id);
});
