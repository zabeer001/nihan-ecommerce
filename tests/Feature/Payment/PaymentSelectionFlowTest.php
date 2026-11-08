<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('returns only customer pis and invoices with an outstanding payment balance', function () {
    $user = User::create([
        'name' => 'Payment Selection User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create([
        'name' => 'Payment Selection Customer',
        'payment_type' => 'lc_60',
    ]);
    $otherCustomer = Customer::create(['name' => 'Other Payment Customer']);
    $customerOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PAYMENT-SELECTION',
    ]);
    $paidCustomerOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PAID-PAYMENT',
    ]);
    $otherOrder = Order::create([
        'customer_id' => $otherCustomer->id,
        'order_number' => 'ORD-OTHER-PAYMENT',
    ]);

    $payablePi = ProformaInvoice::create([
        'order_id' => $customerOrder->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-PAYABLE',
        'grand_total' => 1000,
    ]);
    $paidPi = ProformaInvoice::create([
        'order_id' => $paidCustomerOrder->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-PAID',
        'grand_total' => 500,
    ]);
    $otherPi = ProformaInvoice::create([
        'order_id' => $otherOrder->id,
        'customer_id' => $otherCustomer->id,
        'pi_number' => 'PI-OTHER-CUSTOMER',
        'grand_total' => 700,
    ]);

    $payableInvoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $payablePi->id,
        'invoice_number' => 'INV-PAYABLE',
        'total_amount' => 1000,
        'payment_status' => 'partial',
    ]);
    $paidInvoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $paidPi->id,
        'invoice_number' => 'INV-PAID',
        'total_amount' => 500,
        'payment_status' => 'unpaid',
    ]);
    Invoice::create([
        'customer_id' => $otherCustomer->id,
        'proforma_invoice_id' => $otherPi->id,
        'invoice_number' => 'INV-OTHER-CUSTOMER',
        'total_amount' => 700,
        'payment_status' => 'unpaid',
    ]);

    Payment::create([
        'invoice_id' => $payableInvoice->id,
        'proforma_invoice_id' => $payablePi->id,
        'paid_amount' => 250,
    ]);
    Payment::create([
        'invoice_id' => $paidInvoice->id,
        'proforma_invoice_id' => $paidPi->id,
        'paid_amount' => 500,
    ]);

    $this->actingAs($user, 'api')
        ->getJson("/api/proforma-invoices?customer_id={$customer->id}&with_payable_invoice=1&per_page=100")
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $payablePi->id)
        ->assertJsonPath('data.data.0.pi_number', 'PI-PAYABLE');

    $this->actingAs($user, 'api')
        ->getJson("/api/invoices?customer_id={$customer->id}&proforma_invoice_id={$payablePi->id}&payable_only=1&per_page=100")
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.id', $payableInvoice->id)
        ->assertJsonPath('data.data.0.due_total', 750);
});
