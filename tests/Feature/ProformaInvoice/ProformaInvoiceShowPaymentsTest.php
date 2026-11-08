<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function proformaInvoiceShowApiUser(): User
{
    return User::create([
        'name' => 'PI Show API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
}

it('shows payments directly under a proforma invoice through its invoices', function () {
    $user = proformaInvoiceShowApiUser();
    $customer = Customer::create(['name' => 'PI Payments Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PI-PAYMENTS',
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-PAYMENTS',
        'grand_total' => 1000,
        'paid_total' => 400,
        'due_total' => 600,
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'invoice_number' => 'INV-PI-PAYMENTS',
        'currency' => 'USD',
        'total_amount' => 1000,
        'payment_status' => 'partial',
    ]);

    Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 400,
        'payment_method' => 'bank_transfer',
        'reference_number' => 'PAY-PI-001',
        'payment_note' => 'PI payment demo.',
    ]);

    $this->actingAs($user, 'api')->getJson("/api/proforma-invoices/{$proformaInvoice->id}")
        ->assertOk()
        ->assertJsonPath('data.payments.0.reference_number', 'PAY-PI-001')
        ->assertJsonPath('data.payments.0.paid_amount', '400.00')
        ->assertJsonPath('data.payments.0.invoice.invoice_number', 'INV-PI-PAYMENTS');
});

it('recalculates pi paid total from only payments directly linked to that pi', function () {
    $user = proformaInvoiceShowApiUser();
    $customer = Customer::create(['name' => 'PI Direct Payment Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PI-DIRECT-PAYMENTS',
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-DIRECT-PAYMENTS',
        'grand_total' => 1000,
        'paid_total' => 999,
        'due_total' => 1,
        'status' => 'approved',
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'invoice_number' => 'INV-PI-DIRECT-PAYMENTS',
        'currency' => 'USD',
        'total_amount' => 1000,
        'payment_status' => 'partial',
    ]);

    Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 400,
        'payment_method' => 'bank_transfer',
        'reference_number' => 'PAY-PI-DIRECT',
    ]);

    Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => null,
        'payment_date' => '2026-07-18',
        'paid_amount' => 300,
        'payment_method' => 'cash',
        'reference_number' => 'PAY-NOT-PI-DIRECT',
    ]);

    $this->actingAs($user, 'api')->getJson("/api/proforma-invoices/{$proformaInvoice->id}")
        ->assertOk()
        ->assertJsonPath('data.paid_total', '400.00')
        ->assertJsonPath('data.due_total', '600.00')
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonPath('data.payments.0.reference_number', 'PAY-PI-DIRECT');
});
