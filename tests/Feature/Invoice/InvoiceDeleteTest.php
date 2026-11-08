<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function invoiceDeleteApiUser(): User
{
    return User::create([
        'name' => 'Invoice Delete API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
}

it('deletes linked payments and invoice items and detaches related orders when deleting an invoice', function () {
    $user = invoiceDeleteApiUser();
    $customer = Customer::create(['name' => 'Invoice Delete Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INV-DELETE',
        'due_amount' => 0,
    ]);
    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Demo Item',
        'quantity' => 10,
        'unit_price' => 100,
        'line_total' => 1000,
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-INV-DELETE',
        'grand_total' => 1000,
        'paid_total' => 1000,
        'due_total' => 0,
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'invoice_number' => 'INV-DELETE',
        'total_amount' => 1000,
        'payment_status' => 'paid',
    ]);

    $invoice->orders()->sync([$order->id]);
    $order->update(['invoice_id' => $invoice->id]);

    InvoiceItem::create([
        'invoice_id' => $invoice->id,
        'order_item_id' => $orderItem->id,
        'description' => 'Demo Item',
        'quantity' => 10,
        'unit_price' => 100,
        'line_total' => 1000,
    ]);
    Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-07-18',
        'paid_amount' => 1000,
        'payment_method' => 'cash',
    ]);

    $this->actingAs($user, 'api')->deleteJson("/api/invoices/{$invoice->id}")
        ->assertOk()
        ->assertJsonPath('message', 'Invoice deleted successfully. Linked payments and invoice items were deleted, and linked orders were detached.');

    $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
    $this->assertDatabaseMissing('payments', ['invoice_id' => $invoice->id]);
    $this->assertDatabaseMissing('invoice_items', ['invoice_id' => $invoice->id]);
    $this->assertDatabaseMissing('invoice_order', ['invoice_id' => $invoice->id]);
    $this->assertDatabaseHas('orders', ['id' => $order->id, 'invoice_id' => null]);
    $this->assertDatabaseHas('proforma_invoices', ['id' => $proformaInvoice->id, 'paid_total' => 0, 'due_total' => 1000]);
});
