<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

function customerDueApiUser(): User
{
    return User::factory()->create(['status' => 'active']);
}

function attachCustomerDueInvoiceToOrder(Invoice $invoice, Customer $customer, string $orderNumber, string $deliveryStatus = 'delivered'): Order
{
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => $orderNumber,
        'delivery_status' => $deliveryStatus,
    ]);
    $invoice->orders()->attach($order);

    return $order;
}

it('calculates customer due as invoice totals minus all invoice payments', function () {
    $user = customerDueApiUser();
    $customer = Customer::create([
        'name' => 'Due Customer',
        'mobile' => '01700000000',
        'email' => 'due@example.com',
        'payment_currency' => 'BDT',
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-DUE-001',
        'invoice_date' => '2026-07-02',
        'currency' => 'BDT',
        'total_amount' => 10000,
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
    ]);
    attachCustomerDueInvoiceToOrder($invoice, $customer, 'ORD-DUE-001');
    Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => 3000, 'payment_date' => '2026-07-03']);
    Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => 1000, 'payment_date' => '2026-07-04']);

    $this->actingAs($user, 'api')->getJson('/api/customers')
        ->assertOk()
        ->assertJsonPath('data.data.0.id', $customer->id)
        ->assertJsonPath('data.data.0.invoice_total_amount', 10000)
        ->assertJsonPath('data.data.0.payment_total_amount', 4000)
        ->assertJsonPath('data.data.0.due_amount', 6000)
        ->assertJsonPath('data.data.0.due_currency', 'BDT');
});

it('includes every invoice and payment without lc date or status conditions', function () {
    $user = customerDueApiUser();
    $customer = Customer::create(['name' => 'All Invoice Customer', 'payment_currency' => 'BDT']);

    foreach ([
        ['number' => 'INV-PAID', 'total' => 5000, 'paid' => 5000, 'status' => 'paid'],
        ['number' => 'INV-CANCELLED', 'total' => 3000, 'paid' => 500, 'status' => 'cancelled'],
    ] as $data) {
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => $data['number'],
            'invoice_date' => null,
            'total_amount' => $data['total'],
            'status' => $data['status'],
        ]);
        attachCustomerDueInvoiceToOrder($invoice, $customer, 'ORD-'.$data['number']);
        Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => $data['paid']]);
    }

    $this->actingAs($user, 'api')->getJson('/api/customers')
        ->assertOk()
        ->assertJsonPath('data.data.0.invoice_total_amount', 8000)
        ->assertJsonPath('data.data.0.payment_total_amount', 5500)
        ->assertJsonPath('data.data.0.due_amount', 2500);
});

it('shows invoice payment details and customer totals', function () {
    $user = customerDueApiUser();
    $customer = Customer::create(['name' => 'Show Customer', 'payment_currency' => 'BDT']);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-SHOW-001',
        'invoice_date' => '2026-07-02',
        'currency' => 'BDT',
        'total_amount' => 10000,
    ]);
    attachCustomerDueInvoiceToOrder($invoice, $customer, 'ORD-SHOW-001');
    Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => 3000, 'payment_date' => '2026-07-03']);
    Payment::create(['invoice_id' => $invoice->id, 'paid_amount' => 1000, 'payment_date' => '2026-07-04']);

    $this->actingAs($user, 'api')->getJson("/api/customers/{$customer->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.orders')
        ->assertJsonMissingPath('data.proforma_invoices')
        ->assertJsonPath('data.invoice_total_amount', 10000)
        ->assertJsonPath('data.payment_total_amount', 4000)
        ->assertJsonPath('data.due_amount', 6000)
        ->assertJsonPath('data.invoices.0.id', $invoice->id)
        ->assertJsonPath('data.invoices.0.payment_total_amount', 4000)
        ->assertJsonPath('data.invoices.0.due_amount', 6000)
        ->assertJsonPath('data.invoices.0.payments_count', 2)
        ->assertJsonCount(2, 'data.invoices.0.payments');
});

it('does not report an invoice balance as due until every linked order is fully delivered', function () {
    $user = customerDueApiUser();
    $customer = Customer::create(['name' => 'Partial Delivery Customer', 'payment_currency' => 'BDT']);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-PARTIAL-DELIVERY',
        'currency' => 'BDT',
        'total_amount' => 100000,
    ]);
    $order = attachCustomerDueInvoiceToOrder($invoice, $customer, 'ORD-PARTIAL-DELIVERY', 'partial');

    $this->actingAs($user, 'api')->getJson('/api/customers')
        ->assertOk()
        ->assertJsonPath('data.data.0.due_amount', 0);

    $order->update(['delivery_status' => 'delivered']);

    $this->actingAs($user, 'api')->getJson('/api/customers')
        ->assertOk()
        ->assertJsonPath('data.data.0.due_amount', 100000);
});
