<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

function invoiceExportRows(string $content): array
{
    $temporaryFile = tempnam(sys_get_temp_dir(), 'invoice-export-');
    file_put_contents($temporaryFile, $content);
    $spreadsheet = IOFactory::load($temporaryFile);
    unlink($temporaryFile);
    $rows = $spreadsheet->getActiveSheet()->toArray();
    $spreadsheet->disconnectWorksheets();

    return $rows;
}

it('exports every invoice in the selected period with related order documents', function () {
    $user = User::factory()->create(['status' => 'active']);
    CompanySetting::create(['usd_to_bdt_rate' => 125]);
    $customer = Customer::create(['name' => 'Invoice Export Customer']);
    $order = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-INV-1',
        'order_date' => '2026-06-01',
        'delivery_date' => '2026-07-01',
        'po_number' => 'PO-INV',
        'work_order_number' => 'WO-INV',
        'reference_number' => 'REF-INV',
        'currency' => 'USD',
        'pp_qty' => 12,
        'yarn_qty' => 8,
        'material_amount' => 500,
        'status' => 'cancelled',
        'delivery_status' => 'delivered',
    ]);
    OrderItem::create([
        'order_id' => $order->id,
        'item_code' => 'ITEM-INV',
        'item_name' => 'Invoice Locket',
        'description' => 'Gold finish',
        'item_type' => 'Metal',
        'currency' => 'USD',
        'quantity' => 100,
        'unit_price' => 2.5,
        'line_total' => 250,
    ]);
    $pi = ProformaInvoice::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'pi_number' => 'PI-INV-1',
        'pi_date' => '2026-06-05',
    ]);
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'proforma_invoice_id' => $pi->id,
        'challan_number' => 'DC-INV-1',
        'challan_date' => '2026-06-20',
        'delivery_qty' => 25,
    ]);
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'proforma_invoice_id' => $pi->id,
        'challan_number' => 'DC-INV-2',
        'challan_date' => '2026-06-25',
        'delivery_qty' => 35,
    ]);
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $order->id,
        'proforma_invoice_id' => $pi->id,
        'challan_number' => 'DC-INV-3',
        'challan_date' => '2026-06-30',
        'delivery_qty' => 40,
    ]);
    $invoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $pi->id,
        'invoice_number' => 'INV-IN-PERIOD',
        'invoice_date' => '2026-07-15',
        'currency' => 'USD',
        'total_amount' => 1000,
        'lc_no' => 'LC-100',
        'lc_expiry_date' => '2026-07-18',
        'status' => 'cancelled',
        'payment_status' => 'unpaid',
    ]);
    Payment::create([
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-07-20',
        'paid_amount' => 250,
    ]);
    Payment::create([
        'invoice_id' => $invoice->id,
        'payment_date' => '2026-08-05',
        'paid_amount' => 100,
    ]);
    $invoice->orders()->attach($order->id);
    Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $pi->id,
        'invoice_number' => 'INV-OTHER-STATUS',
        'invoice_date' => '2026-07-20',
        'status' => 'void',
        'payment_status' => 'paid',
    ]);
    $bdtInvoice = Invoice::create([
        'customer_id' => $customer->id,
        'proforma_invoice_id' => $pi->id,
        'invoice_number' => 'INV-BDT-AS-IS',
        'invoice_date' => '2026-07-22',
        'currency' => 'BDT',
        'total_amount' => 54321,
    ]);
    Payment::create([
        'invoice_id' => $bdtInvoice->id,
        'payment_date' => '2026-07-23',
        'paid_amount' => 12345,
    ]);
    Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-OUTSIDE-PERIOD',
        'invoice_date' => '2026-08-01',
    ]);

    $content = $this->actingAs($user, 'api')
        ->get('/api/invoices/export/period?date_from=2026-07-01&date_to=2026-07-31')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();

    $rows = invoiceExportRows($content);
    $headers = $rows[0];
    $exportedValues = collect($rows)->flatten();
    $invoiceNumberColumn = array_search('Invoice No', $headers, true);
    $invoiceNumbers = collect(array_slice($rows, 1))->pluck($invoiceNumberColumn)->filter();

    expect($headers)
        ->toContain('Order No', 'Invoice No', 'Invoice Currency', 'Invoice Total (BDT Conversion)', 'Invoice Total (USD)', 'Paid Amount (BDT Conversion)', 'Paid Amount (USD)', 'Due Amount (BDT Conversion)', 'Due Amount (USD)', 'LC Expiry Date')
        ->not->toContain('Delivery Challan No', 'Challan Date', 'Challan Qty')
        ->and($exportedValues)->toContain('ORD-INV-1', 'Invoice Locket - Gold finish', 'PO-INV / WO-INV / REF-INV', 'PI-INV-1', 'INV-IN-PERIOD', 'LC-100', 'INV-BDT-AS-IS', 'INV-OTHER-STATUS')
        ->not->toContain('DC-INV-1', 'DC-INV-2', 'DC-INV-3', 'INV-OUTSIDE-PERIOD')
        ->and($invoiceNumbers->filter(fn ($number) => $number === 'INV-IN-PERIOD')->count())->toBe(1);

    $invoiceRow = collect($rows)->first(fn (array $row) => ($row[$invoiceNumberColumn] ?? null) === 'INV-IN-PERIOD');
    expect((float) $invoiceRow[array_search('Invoice Total (BDT Conversion)', $headers, true)])->toBe(125000.0)
        ->and((float) $invoiceRow[array_search('Paid Amount (BDT Conversion)', $headers, true)])->toBe(43750.0)
        ->and((float) $invoiceRow[array_search('Due Amount (BDT Conversion)', $headers, true)])->toBe(81250.0)
        ->and((float) $invoiceRow[array_search('Paid Amount (USD)', $headers, true)])->toBe(350.0)
        ->and((float) $invoiceRow[array_search('Due Amount (USD)', $headers, true)])->toBe(650.0);

    $dueContent = $this->actingAs($user, 'api')
        ->get('/api/invoices/export/period?date_from=2026-07-01&date_to=2026-07-31&payment_filter=due')
        ->assertOk()
        ->streamedContent();

    $dueRows = invoiceExportRows($dueContent);
    expect($dueRows[0])
        ->toContain('Due Amount (BDT Conversion)', 'Due Amount (USD)')
        ->and(collect($dueRows)->flatten())->toContain('INV-IN-PERIOD', 'INV-BDT-AS-IS')
        ->not->toContain('INV-OTHER-STATUS');

    $paidContent = $this->actingAs($user, 'api')
        ->get('/api/invoices/export/period?date_from=2026-07-01&date_to=2026-07-31&payment_filter=paid')
        ->assertOk()
        ->streamedContent();

    $paidRows = invoiceExportRows($paidContent);
    expect($paidRows[0])
        ->not->toContain('Due Amount (BDT Conversion)', 'Due Amount (USD)')
        ->and(collect($paidRows)->flatten())->toContain('INV-OTHER-STATUS')
        ->not->toContain('INV-BDT-AS-IS', 'INV-IN-PERIOD');
});

it('exports only fully delivered pi orders and shows their outstanding balance', function (string $deliveryStatus, int $paidAmount) {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'PI Only Customer']);
    $piOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-PI-ONLY',
        'delivery_status' => $deliveryStatus,
        'order_date' => '2026-07-05',
        'currency' => 'BDT',
    ]);
    OrderItem::create([
        'order_id' => $piOrder->id,
        'item_code' => 'ITEM-PI-ONLY',
        'item_name' => 'PI Only Item',
        'currency' => 'BDT',
        'quantity' => 10,
        'unit_price' => 50,
        'line_total' => 500,
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'customer_id' => $customer->id,
        'order_id' => $piOrder->id,
        'pi_number' => 'PI-WITHOUT-INVOICE',
        'pi_date' => '2026-07-06',
        'currency' => 'BDT',
        'grand_total' => 500,
    ]);
    Payment::create([
        'proforma_invoice_id' => $proformaInvoice->id,
        'payment_date' => '2026-07-07',
        'paid_amount' => $paidAmount,
    ]);
    Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-WITHOUT-PI-OR-INVOICE',
        'order_date' => '2026-07-08',
        'currency' => 'BDT',
    ]);

    $withoutPiInvoice = Invoice::create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-WITHOUT-PI',
        'invoice_date' => '2026-07-09',
    ]);
    $withoutPiInvoice->orders()->attach(Order::where('order_number', 'ORD-WITHOUT-PI-OR-INVOICE')->value('id'));

    $content = $this->actingAs($user, 'api')
        ->get('/api/invoices/export/period?date_from=2026-07-01&date_to=2026-07-31&payment_filter=due')
        ->assertOk()
        ->streamedContent();

    $rows = invoiceExportRows($content);
    $headers = $rows[0];
    $piColumn = array_search('PI No', $headers, true);
    $invoiceNumberColumn = array_search('Invoice No', $headers, true);
    $invoiceDateColumn = array_search('Invoice Date', $headers, true);
    $invoiceTotalColumn = array_search('Invoice Total (BDT Conversion)', $headers, true);
    $piRow = collect($rows)->first(fn (array $row) => ($row[$piColumn] ?? null) === 'PI-WITHOUT-INVOICE');

    if ($deliveryStatus !== 'delivered') {
        expect($piRow)->toBeNull();
        expect(collect($rows)->flatten())->not->toContain('ORD-PI-ONLY');
        return;
    }

    expect(collect($rows)->flatten())
        ->toContain('ORD-PI-ONLY', 'ITEM-PI-ONLY', 'PI-WITHOUT-INVOICE')
        ->not->toContain('ORD-WITHOUT-PI-OR-INVOICE', 'INV-WITHOUT-PI')
        ->and($piRow[$invoiceNumberColumn])->toBeNull()
        ->and($piRow[$invoiceDateColumn])->toBeNull()
        ->and((float) $piRow[$invoiceTotalColumn])->toBe(500.0)
        ->and((float) $piRow[array_search('Paid Amount (BDT Conversion)', $headers, true)])->toBe((float) $paidAmount)
        ->and((float) $piRow[array_search('Due Amount (BDT Conversion)', $headers, true)])->toBe((float) (500 - $paidAmount));
})->with([
    'fully delivered unpaid' => ['delivered', 0],
    'fully delivered partially paid' => ['delivered', 200],
    'partially delivered' => ['partial', 0],
    'pending delivery' => ['pending', 0],
]);

it('requires a valid invoice date period', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user, 'api')
        ->getJson('/api/invoices/export/period?date_from=2026-07-31&date_to=2026-07-01')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date_to');
});

it('requires a supported invoice export payment filter', function () {
    $user = User::factory()->create(['status' => 'active']);

    $this->actingAs($user, 'api')
        ->getJson('/api/invoices/export/period?date_from=2026-07-01&date_to=2026-07-31&payment_filter=overdue')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('payment_filter');
});
