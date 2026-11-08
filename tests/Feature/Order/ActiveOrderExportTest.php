<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ProformaInvoice;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports challans for orders regardless of order or delivery status', function () {
    $user = User::create([
        'name' => 'Order Export User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Export Customer']);
    CompanySetting::create(['usd_to_bdt_rate' => 120]);

    $activeOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-ACTIVE',
        'production_order_number' => 'PROD-ACTIVE',
        'order_date' => '2026-07-01',
        'delivery_date' => '2026-08-01',
        'po_number' => 'PO-1',
        'work_order_number' => 'WO-1',
        'reference_number' => 'REF-1',
        'currency' => 'USD',
        'pp_qty' => 12,
        'yarn_qty' => 8,
        'material_amount' => 500,
        'status' => 'processing',
        'delivery_status' => 'partial',
    ]);
    OrderItem::create([
        'order_id' => $activeOrder->id,
        'item_code' => 'ITEM-1',
        'item_name' => 'Locket',
        'description' => 'Silver finish',
        'item_type' => 'Metal',
        'currency' => 'USD',
        'quantity' => 100,
        'unit_price' => 2.5,
        'line_total' => 250,
    ]);
    $proformaInvoice = ProformaInvoice::create([
        'order_id' => $activeOrder->id,
        'customer_id' => $customer->id,
        'pi_number' => 'PI-1',
        'pi_date' => '2026-07-05',
    ]);
    DeliveryChallan::create([
        'customer_id' => $customer->id,
        'order_id' => $activeOrder->id,
        'proforma_invoice_id' => $proformaInvoice->id,
        'challan_number' => 'DC-1',
        'challan_date' => '2026-07-20',
        'delivery_qty' => 25,
    ]);

    foreach ([
        ['order_number' => 'ORD-CANCELLED', 'status' => 'cancelled', 'delivery_status' => 'pending'],
        ['order_number' => 'ORD-DELIVERED', 'status' => 'processing', 'delivery_status' => 'delivered'],
    ] as $index => $attributes) {
        $order = Order::create(['customer_id' => $customer->id, ...$attributes]);
        DeliveryChallan::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'challan_number' => 'DC-STATUS-'.($index + 1),
            'challan_date' => '2026-07-21',
            'delivery_qty' => 1,
        ]);
    }

    $response = $this->actingAs($user, 'api')->get('/api/delivery-challans/export/order-challans');

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $csv = $response->streamedContent();
    $temporaryFile = tempnam(sys_get_temp_dir(), 'challan-export-');
    file_put_contents($temporaryFile, $csv);
    $spreadsheet = IOFactory::load($temporaryFile);
    unlink($temporaryFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $exportedValues = collect($worksheet->toArray())->flatten();

    expect($worksheet->getCell('A1')->getValue())->toBe('Order Number')
        ->and($worksheet->getCell('B1')->getValue())->toBe('Order Received Date')
        ->and($worksheet->getCell('J1')->getValue())->toBe('Currency')
        ->and($worksheet->getCell('M1')->getValue())->toBe('Amount (BDT)')
        ->and($worksheet->getCell('N1')->getValue())->toBe('Amount (USD)')
        ->and($worksheet->getCell('O1')->getValue())->toBe('Total (BDT)')
        ->and($worksheet->getCell('S1')->getValue())->toBe('Material Amount (USD)')
        ->and($worksheet->getCell('A2')->getValue())->toBe('ORD-ACTIVE')
        ->and($worksheet->getCell('F2')->getValue())->toBe('Locket - Silver finish')
        ->and($worksheet->getCell('H2')->getValue())->toBe('PO-1 / WO-1 / REF-1')
        ->and((float) $worksheet->getCell('N2')->getValue())->toBe(250.0)
        ->and((float) $worksheet->getCell('O2')->getValue())->toBe(30000.0)
        ->and($exportedValues)->toContain('DC-1')
        ->and($exportedValues)->toContain('PI-1')
        ->and($exportedValues)->toContain('ORD-CANCELLED')
        ->and($exportedValues)->toContain('ORD-DELIVERED')
        ->and($exportedValues)->not->toContain('PROD-ACTIVE');

    $spreadsheet->disconnectWorksheets();
});

it('filters the export by customer and challan date', function () {
    $user = User::factory()->create(['status' => 'active']);
    $includedCustomer = Customer::create(['name' => 'Included Customer']);
    $excludedCustomer = Customer::create(['name' => 'Excluded Customer']);

    foreach ([
        [$includedCustomer, 'ORD-IN-RANGE', '2026-07-15'],
        [$includedCustomer, 'ORD-OUT-OF-RANGE', '2026-06-30'],
        [$excludedCustomer, 'ORD-WRONG-CUSTOMER', '2026-07-15'],
    ] as [$customer, $orderNumber, $challanDate]) {
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => $orderNumber,
            'status' => 'processing',
            'delivery_status' => 'partial',
        ]);
        DeliveryChallan::create([
            'customer_id' => $customer->id,
            'order_id' => $order->id,
            'challan_number' => "DC-{$orderNumber}",
            'challan_date' => $challanDate,
            'delivery_qty' => 1,
        ]);
    }

    $response = $this->actingAs($user, 'api')->get(
        "/api/delivery-challans/export/order-challans?customer_id={$includedCustomer->id}&challan_date_from=2026-07-01&challan_date_to=2026-07-31"
    );

    $csv = $response->assertOk()->streamedContent();

    expect($csv)
        ->toContain('ORD-IN-RANGE')
        ->not->toContain('ORD-OUT-OF-RANGE')
        ->not->toContain('ORD-WRONG-CUSTOMER');
});
