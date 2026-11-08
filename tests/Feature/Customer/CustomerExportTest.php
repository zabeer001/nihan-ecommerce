<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;

it('exports only customers with a positive invoice payment due', function () {
    $user = User::factory()->create(['status' => 'active']);

    $dueCustomer = Customer::create([
        'name' => 'Due Customer',
        'mobile' => '01700000001',
        'email' => 'due-customer@example.com',
    ]);
    $dueInvoice = Invoice::create([
        'customer_id' => $dueCustomer->id,
        'invoice_number' => 'INV-DUE-CUSTOMER',
        'currency' => 'BDT',
        'total_amount' => 10000,
        'status' => 'cancelled',
    ]);
    $dueOrder = Order::create(['customer_id' => $dueCustomer->id, 'order_number' => 'ORD-DUE-CUSTOMER', 'delivery_status' => 'delivered']);
    $dueInvoice->orders()->attach($dueOrder);
    Payment::create(['invoice_id' => $dueInvoice->id, 'paid_amount' => 3000]);
    Payment::create(['invoice_id' => $dueInvoice->id, 'paid_amount' => 1000]);
    $usdInvoice = Invoice::create([
        'customer_id' => $dueCustomer->id,
        'invoice_number' => 'INV-DUE-CUSTOMER-USD',
        'currency' => 'USD',
        'total_amount' => 200,
    ]);
    $usdOrder = Order::create(['customer_id' => $dueCustomer->id, 'order_number' => 'ORD-DUE-CUSTOMER-USD', 'delivery_status' => 'delivered']);
    $usdInvoice->orders()->attach($usdOrder);
    Payment::create(['invoice_id' => $usdInvoice->id, 'paid_amount' => 50]);

    $paidCustomer = Customer::create(['name' => 'Fully Paid Customer']);
    $paidInvoice = Invoice::create(['customer_id' => $paidCustomer->id, 'total_amount' => 5000]);
    $paidOrder = Order::create(['customer_id' => $paidCustomer->id, 'order_number' => 'ORD-PAID-CUSTOMER', 'delivery_status' => 'delivered']);
    $paidInvoice->orders()->attach($paidOrder);
    Payment::create(['invoice_id' => $paidInvoice->id, 'paid_amount' => 5000]);

    Customer::create(['name' => 'Customer Without Invoice']);

    $content = $this->actingAs($user, 'api')
        ->get('/api/customers/export/due')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();

    expect($content)
        ->toContain('<th>Customer Name</th>')
        ->toContain('<th>Phone</th>')
        ->toContain('<th>Email</th>')
        ->toContain('<th>Due Amount (BDT)</th>')
        ->toContain('<th>Due Amount (USD)</th>')
        ->toContain('Due Customer')
        ->toContain('01700000001')
        ->toContain('due-customer@example.com')
        ->toContain('<td>6000</td>')
        ->toContain('<td>150</td>')
        ->not->toContain('Fully Paid Customer')
        ->not->toContain('Customer Without Invoice');
});
