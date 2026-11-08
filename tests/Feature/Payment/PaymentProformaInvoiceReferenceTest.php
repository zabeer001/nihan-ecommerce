<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function paymentPiReferenceApiUser(): User
{
    return User::create([
        'name' => 'Payment PI API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
}

function paymentPiReferenceInvoice(Customer $customer, string $piNumber, string $invoiceNumber): array
{
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => fake()->unique()->bothify('ORD-PAY-PI-####'),
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => $piNumber,
        'grand_total' => 1000,
        'paid_total' => 0,
        'due_total' => 1000,
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'invoice_number' => $invoiceNumber,
        'currency' => 'USD',
        'total_amount' => 1000,
        'payment_status' => 'unpaid',
    ]);

    return [$proformaInvoice, $invoice];
}

it('creates a payment directly against a proforma invoice without an invoice', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'Direct PI Payment Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-DIRECT-PI-PAYMENT',
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-DIRECT-PAYMENT',
        'grand_total' => 1000,
        'due_total' => 1000,
    ]);

    $this->actingAs($user, 'api')->postJson('/api/payments', [
        'proforma_invoice_id' => $proformaInvoice->id,
        'invoice_id' => null,
        'payment_date' => '2026-11-01',
        'paid_amount' => 250,
        'payment_method' => 'cash',
    ])->assertCreated()
        ->assertJsonPath('data.invoice_id', null)
        ->assertJsonPath('data.proforma_invoice_id', $proformaInvoice->id);

    $this->assertDatabaseHas('payments', [
        'invoice_id' => null,
        'proforma_invoice_id' => $proformaInvoice->id,
        'paid_amount' => 250,
    ]);
    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoice->id,
        'paid_total' => 250,
        'due_total' => 750,
    ]);

    $this->actingAs($user, 'api')->getJson('/api/payments?search=PI-DIRECT-PAYMENT')
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.invoice', null)
        ->assertJsonPath('data.data.0.proforma_invoice.pi_number', 'PI-DIRECT-PAYMENT')
        ->assertJsonPath('data.data.0.proforma_invoice.customer.name', 'Direct PI Payment Customer')
        ->assertJsonPath('data.data.0.proforma_invoice.order.order_number', 'ORD-DIRECT-PI-PAYMENT');
});

it('creates a payment with matching pi and optional invoice', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create([
        'name' => 'Payment PI Customer',
        'payment_terms' => '<p>Payment is due within 30 days.</p>',
    ]);
    [$proformaInvoice, $invoice] = paymentPiReferenceInvoice($customer, 'PI-PAY-CREATE', 'INV-PAY-CREATE');

    $this->actingAs($user, 'api')->postJson('/api/payments', [
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 250,
        'amount_in_words' => 'Two hundred fifty US dollars only',
        'payment_method' => 'cash',
        'reference_number' => 'PAY-CREATE-PI',
    ])->assertCreated()
        ->assertJsonPath('data.proforma_invoice_id', $proformaInvoice->id)
        ->assertJsonPath('data.amount_in_words', 'Two hundred fifty US dollars only')
        ->assertJsonPath('data.proforma_invoice.id', $proformaInvoice->id)
        ->assertJsonPath('data.proforma_invoice.customer.payment_terms', '<p>Payment is due within 30 days.</p>');

    $this->assertDatabaseHas('payments', [
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'reference_number' => 'PAY-CREATE-PI',
        'amount_in_words' => 'Two hundred fifty US dollars only',
    ]);
});

it('updates payment with a new pi and matching invoice', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'Payment PI Update Customer']);
    [$oldProformaInvoice, $oldInvoice] = paymentPiReferenceInvoice($customer, 'PI-PAY-OLD', 'INV-PAY-OLD');
    [$newProformaInvoice, $newInvoice] = paymentPiReferenceInvoice($customer, 'PI-PAY-NEW', 'INV-PAY-NEW');
    $payment = Payment::create([
        'invoice_id' => $oldInvoice->id,
        'proforma_invoice_id' => $oldProformaInvoice->id,
        'paid_amount' => 200,
        'reference_number' => 'PAY-UPDATE-PI',
    ]);

    $this->actingAs($user, 'api')->putJson("/api/payments/{$payment->id}", [
        'invoice_id' => $newInvoice->id,
        'proforma_invoice_id' => $newProformaInvoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 300,
        'amount_in_words' => 'Three hundred US dollars only',
        'payment_method' => 'bank_transfer',
        'reference_number' => 'PAY-UPDATE-PI',
    ])->assertOk()
        ->assertJsonPath('data.proforma_invoice_id', $newProformaInvoice->id)
        ->assertJsonPath('data.amount_in_words', 'Three hundred US dollars only')
        ->assertJsonPath('data.proforma_invoice.id', $newProformaInvoice->id);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'invoice_id' => $newInvoice->id,
        'proforma_invoice_id' => $newProformaInvoice->id,
    ]);
});

it('filters payment index by proforma invoice id', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'Payment PI Filter Customer']);
    [$firstProformaInvoice, $firstInvoice] = paymentPiReferenceInvoice($customer, 'PI-PAY-FILTER-1', 'INV-PAY-FILTER-1');
    [$secondProformaInvoice, $secondInvoice] = paymentPiReferenceInvoice($customer, 'PI-PAY-FILTER-2', 'INV-PAY-FILTER-2');

    Payment::create([
        'invoice_id' => $firstInvoice->id,
        'proforma_invoice_id' => $firstProformaInvoice->id,
        'paid_amount' => 100,
        'reference_number' => 'PAY-FILTER-1',
    ]);
    Payment::create([
        'invoice_id' => $secondInvoice->id,
        'proforma_invoice_id' => $secondProformaInvoice->id,
        'paid_amount' => 200,
        'reference_number' => 'PAY-FILTER-2',
    ]);

    $this->actingAs($user, 'api')->getJson("/api/payments?proforma_invoice_id={$secondProformaInvoice->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.reference_number', 'PAY-FILTER-2')
        ->assertJsonPath('data.data.0.proforma_invoice.id', $secondProformaInvoice->id);
});

it('rejects a payment without a pi even when an invoice is provided', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'Standalone Invoice Customer']);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-STANDALONE-PAYMENT',
        'currency' => 'USD',
        'total_amount' => 500,
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($user, 'api')->postJson('/api/payments', [
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 125,
        'payment_method' => 'cash',
        'reference_number' => 'PAY-STANDALONE',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('proforma_invoice_id');

    $this->assertDatabaseMissing('payments', ['reference_number' => 'PAY-STANDALONE']);
});

it('marks proforma invoice paid when pi payment total reaches grand total', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'Payment PI Paid Customer']);
    [$proformaInvoice, $invoice] = paymentPiReferenceInvoice($customer, 'PI-PAY-STATUS', 'INV-PAY-STATUS');

    $this->actingAs($user, 'api')->postJson('/api/payments', [
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 1000,
        'payment_method' => 'cash',
        'reference_number' => 'PAY-STATUS-PI',
    ])->assertCreated();

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoice->id,
        'paid_total' => 1000,
        'due_total' => 0,
        'status' => 'paid',
    ]);
});

it('allows removing the optional invoice while retaining the required pi', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'PI Only Update Customer']);
    [$pi, $invoice] = paymentPiReferenceInvoice($customer, 'PI-UNLINK', 'INV-UNLINK');
    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $pi->id,
        'paid_amount' => 100,
    ]);

    $this->actingAs($user, 'api')->putJson("/api/payments/{$payment->id}", [
        'invoice_id' => null,
        'proforma_invoice_id' => $pi->id,
        'paid_amount' => 150,
    ])->assertOk()
        ->assertJsonPath('data.invoice_id', null)
        ->assertJsonPath('data.proforma_invoice_id', $pi->id);

    $this->actingAs($user, 'api')->putJson("/api/payments/{$payment->id}", [
        'invoice_id' => $invoice->id,
        'paid_amount' => 150,
    ])->assertUnprocessable()->assertJsonValidationErrors('proforma_invoice_id');
});

it('rejects an optional invoice belonging to a different pi', function () {
    $user = paymentPiReferenceApiUser();
    $customer = Customer::create(['name' => 'PI Mismatch Customer']);
    [$pi] = paymentPiReferenceInvoice($customer, 'PI-MATCH', 'INV-MATCH');
    [, $otherInvoice] = paymentPiReferenceInvoice($customer, 'PI-OTHER', 'INV-OTHER');

    $this->actingAs($user, 'api')->postJson('/api/payments', [
        'invoice_id' => $otherInvoice->id,
        'proforma_invoice_id' => $pi->id,
        'paid_amount' => 100,
    ])->assertUnprocessable()->assertJsonValidationErrors('invoice_id');
});
