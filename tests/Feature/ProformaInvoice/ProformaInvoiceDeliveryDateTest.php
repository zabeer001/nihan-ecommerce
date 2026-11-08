<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function proformaInvoiceDeliveryDateApiUser(): User
{
    return User::create([
        'name' => 'PI Delivery Date API User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
}

function proformaInvoiceDeliveryDateOrder(Customer $customer): Order
{
    return Order::create([
        'customer_id' => $customer->id,
        'order_number' => fake()->unique()->bothify('ORD-PI-DATE-####'),
        'delivery_date' => '2026-08-10',
    ]);
}

it('stores delivery date when creating a proforma invoice', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Delivery Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);

    $this->actingAs($user, 'api')->postJson('/api/proforma-invoices', [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-DELIVERY-CREATE',
        'pi_date' => '2026-07-18',
        'delivery_date' => '2026-08-15',
        'currency' => 'BDT',
        'grand_total' => 1200,
    ])->assertCreated()
        ->assertJsonPath('data.delivery_date', '2026-08-15');

    $this->assertDatabaseHas('proforma_invoices', [
        'order_id' => $order->id,
        'pi_number' => 'PI-DELIVERY-CREATE',
        'delivery_date' => '2026-08-15',
    ]);
});

it('updates delivery date on a proforma invoice', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Delivery Update Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-DELIVERY-UPDATE',
        'pi_date' => '2026-07-18',
        'delivery_date' => '2026-08-15',
        'currency' => 'BDT',
        'grand_total' => 1200,
    ]);

    $this->actingAs($user, 'api')->putJson("/api/proforma-invoices/{$proformaInvoice->id}", [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-DELIVERY-UPDATE',
        'pi_date' => '2026-07-18',
        'delivery_date' => '2026-08-20',
        'currency' => 'BDT',
        'grand_total' => 1200,
    ])->assertOk()
        ->assertJsonPath('data.delivery_date', '2026-08-20');

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoice->id,
        'delivery_date' => '2026-08-20',
    ]);
});

it('calculates PI total weight from item weight in grams times ordered quantity', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Weight Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);

    OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Locket',
        'weight' => 1,
        'quantity' => 300,
        'unit' => 'pcs',
    ]);

    $this->actingAs($user, 'api')->postJson('/api/proforma-invoices', [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-WEIGHT-CREATE',
        'currency' => 'BDT',
        'total_weight' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.total_weight', fn ($weight) => (float) $weight === 300.0);

    $this->assertDatabaseHas('proforma_invoices', [
        'order_id' => $order->id,
        'total_weight' => 300,
    ]);
});

it('recalculates PI total weight when its order is updated', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Weight Update Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);

    OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Locket',
        'weight' => 2,
        'quantity' => 125,
        'unit' => 'pcs',
    ]);

    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-WEIGHT-UPDATE',
        'currency' => 'BDT',
        'total_weight' => 2,
    ]);

    $this->actingAs($user, 'api')->putJson("/api/proforma-invoices/{$proformaInvoice->id}", [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-WEIGHT-UPDATE',
        'currency' => 'BDT',
        'total_weight' => 2,
    ])->assertOk()
        ->assertJsonPath('data.total_weight', fn ($weight) => (float) $weight === 250.0);

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoice->id,
        'total_weight' => 250,
    ]);
});

it('stores PI summary amounts with the global two decimal precision', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Global Amount Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);
    $order->update(['total_amount' => 10.12678]);

    $response = $this->actingAs($user, 'api')->postJson('/api/proforma-invoices', [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-GLOBAL-AMOUNT',
        'currency' => 'BDT',
        'subtotal' => 10.12678,
        'bank_document_charge' => 0.00499,
        'freight_courier_charge' => 0.01501,
        'grand_total' => 99999,
    ])->assertCreated();

    $proformaInvoiceId = $response->json('data.id');

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoiceId,
        'subtotal' => 10.13,
        'bank_document_charge' => 0,
        'freight_courier_charge' => 0.02,
        'grand_total' => 10.15,
    ]);

    $order->update(['total_amount' => 20.99499]);

    $this->actingAs($user, 'api')->putJson("/api/proforma-invoices/{$proformaInvoiceId}", [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-GLOBAL-AMOUNT',
        'currency' => 'BDT',
        'subtotal' => 20.99499,
        'bank_document_charge' => 0,
        'freight_courier_charge' => 0,
        'grand_total' => 99999,
    ])->assertOk();

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoiceId,
        'subtotal' => 20.99,
        'grand_total' => 20.99,
    ]);
});

it('uses authoritative order totals and ignores submitted PI totals', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Order Total Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);
    $order->update(['total_amount' => 27000]);

    OrderItem::create([
        'order_id' => $order->id,
        'item_name' => 'Legacy item amount',
        'quantity' => 1,
        'unit_price' => 100,
        'line_total' => 100,
    ]);

    $response = $this->actingAs($user, 'api')->postJson('/api/proforma-invoices', [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-ORDER-TOTAL',
        'currency' => 'BDT',
        'subtotal' => 1,
        'bank_document_charge' => 100,
        'freight_courier_charge' => 50,
        'grand_total' => 1,
    ])->assertCreated();

    $proformaInvoiceId = $response->json('data.id');

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoiceId,
        'subtotal' => 27000,
        'grand_total' => 27150,
    ]);

    $order->update(['total_amount' => 28000]);

    $this->actingAs($user, 'api')->putJson("/api/proforma-invoices/{$proformaInvoiceId}", [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-ORDER-TOTAL',
        'currency' => 'BDT',
        'subtotal' => 1,
        'bank_document_charge' => 100,
        'freight_courier_charge' => 50,
        'grand_total' => 1,
    ])->assertOk();

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoiceId,
        'subtotal' => 28000,
        'grand_total' => 28150,
    ]);
});

it('sums each selected order once when deriving PI totals', function () {
    $user = proformaInvoiceDeliveryDateApiUser();
    $customer = Customer::create(['name' => 'PI Multiple Order Customer']);
    $firstOrder = proformaInvoiceDeliveryDateOrder($customer);
    $secondOrder = proformaInvoiceDeliveryDateOrder($customer);
    $firstOrder->update(['total_amount' => 12000]);
    $secondOrder->update(['total_amount' => 15000]);

    $this->actingAs($user, 'api')->postJson('/api/proforma-invoices', [
        'order_id' => $firstOrder->id,
        'order_ids' => [$firstOrder->id, $secondOrder->id],
        'customer_id' => $customer->id,
        'pi_number' => 'PI-MULTIPLE-ORDER-TOTAL',
        'currency' => 'BDT',
        'subtotal' => 1,
        'grand_total' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.subtotal', '27000.00')
        ->assertJsonPath('data.grand_total', '27000.00');
});

it('recalculates existing PI and due totals without changing payments', function () {
    $customer = Customer::create(['name' => 'PI Existing Total Customer']);
    $order = proformaInvoiceDeliveryDateOrder($customer);
    $order->update(['total_amount' => 27000]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-EXISTING-ORDER-TOTAL',
        'subtotal' => 1,
        'bank_document_charge' => 100,
        'freight_courier_charge' => 50,
        'grand_total' => 1,
        'paid_total' => 1,
        'due_total' => 0,
        'status' => 'approved',
    ]);
    $proformaInvoice->orders()->sync([$order->id]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'invoice_number' => 'INV-EXISTING-ORDER-TOTAL',
        'currency' => 'BDT',
        'total_amount' => 27000,
        'payment_status' => 'partial',
    ]);
    $payment = Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-08-26',
        'paid_amount' => 7000,
        'payment_method' => 'bank_transfer',
        'reference_number' => 'PAY-EXISTING-ORDER-TOTAL',
    ]);

    $migration = require database_path('migrations/2026_08_26_000002_recalculate_proforma_invoice_amounts_from_orders.php');
    $migration->up();

    $this->assertDatabaseHas('proforma_invoices', [
        'id' => $proformaInvoice->id,
        'subtotal' => 27000,
        'grand_total' => 27150,
        'paid_total' => 7000,
        'due_total' => 20150,
        'status' => 'approved',
    ]);
    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'paid_amount' => 7000,
    ]);
    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'due_amount' => 20150,
    ]);
});
