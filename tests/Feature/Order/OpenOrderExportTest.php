<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\IOFactory;

it('exports open orders with order columns and excludes cancelled and delivered orders', function () {
    $user = User::factory()->create(['status' => 'active']);
    $customer = Customer::create(['name' => 'Open Order Customer']);
    CompanySetting::create(['usd_to_bdt_rate' => 120]);
    $openOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-OPEN',
        'production_order_number' => 'PROD-OPEN',
        'order_date' => '2026-07-01',
        'delivery_date' => '2026-08-01',
        'po_number' => 'PO-10',
        'work_order_number' => 'WO-10',
        'reference_number' => 'REF-10',
        'currency' => 'USD',
        'pp_qty' => 12,
        'yarn_qty' => 8,
        'material_amount' => 500,
        'status' => 'processing',
        'delivery_status' => 'partial',
    ]);
    OrderItem::create([
        'order_id' => $openOrder->id,
        'item_code' => 'ITEM-10',
        'item_name' => 'Locket',
        'description' => 'Silver finish',
        'item_type' => 'Metal',
        'currency' => 'USD',
        'quantity' => 100,
        'unit_price' => 2.5,
        'line_total' => 250,
    ]);

    $bdtOrder = Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-OPEN-BDT',
        'currency' => 'BDT',
        'status' => 'processing',
        'delivery_status' => 'pending',
    ]);
    OrderItem::create([
        'order_id' => $bdtOrder->id,
        'item_name' => 'BDT Item',
        'currency' => 'BDT',
        'quantity' => 1,
        'unit_price' => 54000,
        'line_total' => 54000,
    ]);

    foreach ([
        ['order_number' => 'ORD-CANCELLED', 'status' => 'cancelled', 'delivery_status' => 'pending'],
        ['order_number' => 'ORD-DELIVERED', 'status' => 'processing', 'delivery_status' => 'delivered'],
    ] as $attributes) {
        Order::create(['customer_id' => $customer->id, ...$attributes]);
    }

    $content = $this->actingAs($user, 'api')
        ->get('/api/orders/export/open-orders')
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->streamedContent();

    $temporaryFile = tempnam(sys_get_temp_dir(), 'open-orders-export-');
    file_put_contents($temporaryFile, $content);
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
        ->and($worksheet->getCell('A2')->getValue())->toBe('ORD-OPEN')
        ->and($worksheet->getCell('D2')->getValue())->toBe('Open Order Customer')
        ->and($worksheet->getCell('F2')->getValue())->toBe('Locket - Silver finish')
        ->and($worksheet->getCell('H2')->getValue())->toBe('PO-10 / WO-10 / REF-10')
        ->and($worksheet->getCell('J2')->getValue())->toBe('USD')
        ->and((float) $worksheet->getCell('N2')->getValue())->toBe(250.0)
        ->and((float) $worksheet->getCell('O2')->getValue())->toBe(30000.0)
        ->and($worksheet->getCell('A3')->getValue())->toBe('ORD-OPEN-BDT')
        ->and($worksheet->getCell('J3')->getValue())->toBe('BDT')
        ->and((float) $worksheet->getCell('M3')->getValue())->toBe(54000.0)
        ->and((float) $worksheet->getCell('O3')->getValue())->toBe(54000.0)
        ->and($exportedValues)->not->toContain('PROD-OPEN')
        ->and($exportedValues)->not->toContain('ORD-CANCELLED')
        ->and($exportedValues)->not->toContain('ORD-DELIVERED');

    $spreadsheet->disconnectWorksheets();
});
