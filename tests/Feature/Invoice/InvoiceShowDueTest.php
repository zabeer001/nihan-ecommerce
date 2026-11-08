<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;

it('shows invoice paid and due amounts in the invoice currency', function () {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'Invoice Show Customer']);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-SHOW-DUE',
        'invoice_date' => '2026-08-01',
        'currency' => 'USD',
        'total_amount' => 200,
    ]);
    Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => 50]);
    Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => 25]);

    $this->actingAs($user, 'api')
        ->getJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.total_amount', '200.0000000000')
        ->assertJsonPath('data.paid_total', 75)
        ->assertJsonPath('data.due_total', 125);
});
