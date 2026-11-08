<?php

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;

function paymentExportRows(string $content): array
{
    $path = tempnam(sys_get_temp_dir(), 'payment-export-');
    file_put_contents($path, $content);
    try {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        $spreadsheet->disconnectWorksheets();
        return $rows;
    } finally {
        unlink($path);
    }
}

it('exports payments in the selected period with all related documents', function () {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'Payment Export Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PAY-1',
        'order_date' => '2026-06-01',
        'delivery_date' => '2026-07-01',
        'po_number' => 'PO-PAY',
        'work_order_number' => 'WO-PAY',
        'reference_number' => 'REF-PAY',
        'currency' => 'USD',
        'pp_qty' => 12,
        'yarn_qty' => 8,
        'material_amount' => 500,
        'status' => 'cancelled',
        'delivery_status' => 'delivered',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'item_code' => 'ITEM-PAY',
        'item_name' => 'Payment Locket',
        'description' => 'Silver finish',
        'item_type' => 'Metal',
        'currency' => 'USD',
        'quantity' => 100,
        'unit_price' => 2.5,
        'line_total' => 250,
    ]);
    $pi = ProformaInvoice::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'pi_number' => 'PI-PAY-1',
        'pi_date' => '2026-06-05',
    ]);
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'proforma_invoice_id' => $pi->id,
        'challan_number' => 'DC-PAY-1',
        'challan_date' => '2026-06-20',
        'delivery_qty' => 25,
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $pi->id,
        'invoice_number' => 'INV-PAY-1',
        'invoice_date' => '2026-07-15',
        'currency' => 'USD',
        'lc_no' => 'LC-PAY-1',
        'lc_expiry_date' => '2026-07-18',
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
    ]);
    $invoice->orders()->attach($order->id);
    Payment::create([
        'invoice_id' => $invoice->id,
        'proforma_invoice_id' => $pi->id,
        'payment_date' => '2026-07-25',
        'payment_method' => 'Bank Transfer',
        'reference_number' => 'PAY-REF-1',
        'paid_amount' => 100,
    ]);
    Payment::create([
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-08-01',
        'payment_method' => 'Cash',
        'reference_number' => 'PAY-OUTSIDE',
        'paid_amount' => 50,
    ]);

    $content = $this->actingAs($user, 'api')
        ->get('/api/payments/export/period?date_from=2026-07-01&date_to=2026-07-31')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();

    $rows = paymentExportRows($content);
    $content = implode(' ', array_map(fn ($row) => implode(' ', $row), $rows));

    expect($content)
        ->toContain('Order No')
        ->toContain('Document Submission Date')
        ->toContain('Payment Date')
        ->toContain('Payment Reference No')
        ->toContain('Invoice Currency')
        ->toContain('Payment Amount (BDT)')
        ->toContain('Payment Amount (USD)')
        ->toContain('ORD-PAY-1')
        ->toContain('Payment Locket - Silver finish')
        ->toContain('PI-PAY-1')
        ->toContain('INV-PAY-1')
        ->toContain('LC-PAY-1')
        ->toContain('Bank Transfer')
        ->toContain('PAY-REF-1')
        ->toContain('USD')
        ->toContain('100')
        ->not->toContain('PAY-OUTSIDE');
});

it('requires a valid payment date period', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user, 'api')
        ->getJson('/api/payments/export/period?date_from=2026-07-31&date_to=2026-07-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_to');
});

it('exports pi order details for payments without an invoice', function (bool $legacy) {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'Direct PI Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-DIRECT-EXPORT',
        'currency' => 'USD',
        'po_number' => 'PO-DIRECT-EXPORT',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'item_code' => 'ITEM-DIRECT',
        'item_name' => 'Direct PI Item',
        'quantity' => 20,
        'unit_price' => 5,
        'line_total' => 100,
    ]);
    $pi = ProformaInvoice::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'pi_number' => 'PI-DIRECT-EXPORT',
        'currency' => 'USD',
    ]);
    if (! $legacy) {
        $pi->orders()->attach($order->id);
    }
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'proforma_invoice_id' => $pi->id,
        'challan_number' => 'DC-DIRECT-EXPORT',
        'delivery_qty' => 20,
    ]);
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'proforma_invoice_id' => $pi->id,
        'challan_number' => 'DC-DIRECT-SECOND',
        'delivery_qty' => 10,
    ]);
    Payment::create([
        'invoice_id' => null,
        'proforma_invoice_id' => $pi->id,
        'payment_date' => '2026-07-25',
        'paid_amount' => 75,
    ]);

    $content = $this->actingAs($user, 'api')
        ->get('/api/payments/export/period?date_from=2026-07-01&date_to=2026-07-31')
        ->assertOk()->streamedContent();

    $rows = paymentExportRows($content);
    $content = implode(' ', array_map(fn ($row) => implode(' ', $row), $rows));

    expect($content)->toContain('ORD-DIRECT-EXPORT')
        ->toContain('Direct PI Customer')
        ->toContain('ITEM-DIRECT')
        ->toContain('Direct PI Item')
        ->toContain('PO-DIRECT-EXPORT')
        ->toContain('PI-DIRECT-EXPORT')
        ->toContain('USD');
    expect($rows)->toHaveCount(2);
    expect($rows[0])->not->toContain('Delivery Challan No', 'Challan Date', 'Challan Qty');
    expect($content)->not->toContain('DC-DIRECT-EXPORT', 'DC-DIRECT-SECOND');
    expect($rows[1][28])->toBeNull();
    expect((float) $rows[1][29])->toBe(75.0);
})->with(['pivot orders' => false, 'legacy order' => true]);
