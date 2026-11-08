<?php

use App\Models\CompanySetting;
use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\ItemSeeder;
use Database\Seeders\OrderDemoSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

it('counts every non-cancelled order that is not fully delivered as open', function () {
    $user = User::create([
        'name' => 'Dashboard Open Orders User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Dashboard Open Orders Customer']);
    CompanySetting::create(['usd_to_bdt_rate' => 120]);

    foreach ([
        ['order_number' => 'ORD-DASH-PENDING', 'status' => 'confirmed', 'delivery_status' => 'pending', 'total_amount' => 1000, 'currency' => 'BDT'],
        ['order_number' => 'ORD-DASH-PARTIAL', 'status' => 'processing', 'delivery_status' => 'partial', 'total_amount' => 2000, 'currency' => 'USD'],
        ['order_number' => 'ORD-DASH-COMPLETED-PENDING', 'status' => 'completed', 'delivery_status' => 'pending', 'total_amount' => 3000, 'currency' => 'BDT'],
        ['order_number' => 'ORD-DASH-DELIVERED', 'status' => 'processing', 'delivery_status' => 'delivered', 'total_amount' => 4500, 'currency' => 'USD'],
        ['order_number' => 'ORD-DASH-CANCELLED', 'status' => 'cancelled', 'delivery_status' => 'pending', 'total_amount' => 5000, 'currency' => 'USD'],
        ['order_number' => 'ORD-DASH-CANCELLED-DELIVERED', 'status' => 'cancelled', 'delivery_status' => 'delivered', 'total_amount' => 6000, 'currency' => 'BDT'],
    ] as $attributes) {
        Order::create([
            'customer_id' => $customer->id,
            ...$attributes,
        ]);
    }

    $this->actingAs($user, 'api')
        ->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.stats.open_orders', 3)
        ->assertJsonPath('data.stats.open_order_amount_bdt', 244000)
        ->assertJsonPath('data.stats.usd_to_bdt_rate', 120)
        ->assertJsonPath('data.stats.challan_completed_amount', 540000);
});

it('updates the global usd to bdt conversion rate', function () {
    $user = User::create([
        'name' => 'Dashboard Conversion Settings User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'admin',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/company-settings')
        ->assertOk()
        ->assertJsonPath('data.usd_to_bdt_rate', 120);

    $this->actingAs($user, 'api')
        ->patchJson('/api/company-settings', ['usd_to_bdt_rate' => 125.5])
        ->assertOk()
        ->assertJsonPath('data.usd_to_bdt_rate', 125.5);

    expect((float) CompanySetting::query()->value('usd_to_bdt_rate'))->toBe(125.5);

    $customer = Customer::create(['name' => 'Dashboard Conversion Customer']);
    Order::create([
        'customer_id' => $customer->id,
        'order_number' => 'ORD-DASH-CONVERSION',
        'status' => 'completed',
        'delivery_status' => 'delivered',
        'currency' => 'USD',
        'total_amount' => 10,
    ]);

    $this->actingAs($user, 'api')
        ->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.stats.challan_completed_amount', 1255)
        ->assertJsonPath('data.stats.usd_to_bdt_rate', 125.5);
});

it('returns monthly delivered challan amounts for the current year in bdt', function () {
    Carbon::setTestNow('2026-07-31 10:00:00');

    try {
        $user = User::create([
            'name' => 'Dashboard Monthly Orders User',
            'email' => fake()->unique()->safeEmail(),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $customer = Customer::create(['name' => 'Dashboard Monthly Orders Customer']);
        CompanySetting::create(['usd_to_bdt_rate' => 125]);

        $orders = collect([
            ['order_date' => '2025-12-05', 'ordered_qty' => 100, 'currency' => 'BDT', 'total_amount' => 1000, 'status' => 'confirmed'],
            ['order_date' => '2026-01-15', 'ordered_qty' => 10, 'currency' => 'USD', 'total_amount' => 10, 'status' => 'completed'],
            ['order_date' => '2026-07-10', 'ordered_qty' => 10, 'currency' => 'BDT', 'total_amount' => 500, 'status' => 'cancelled'],
        ])->map(function (array $attributes, int $index) use ($customer): Order {
            return Order::create([
                'customer_id' => $customer->id,
                'order_number' => "ORD-MONTHLY-{$index}",
                ...$attributes,
            ]);
        });

        foreach ([
            ['order' => 0, 'number' => 'CH-FEB-PARTIAL', 'date' => '2026-02-05', 'qty' => 40, 'status' => 'delivered'],
            ['order' => 0, 'number' => 'CH-FEB-REMAINDER', 'date' => '2026-02-20', 'qty' => 60, 'status' => 'delivered'],
            ['order' => 1, 'number' => 'CH-JUN-PAID-ORDER', 'date' => '2026-06-15', 'qty' => 10, 'status' => 'delivered'],
            ['order' => 0, 'number' => 'CH-JUL-DRAFT', 'date' => '2026-07-10', 'qty' => 50, 'status' => 'draft'],
            ['order' => 2, 'number' => 'CH-JUL-CANCELLED-ORDER', 'date' => '2026-07-11', 'qty' => 10, 'status' => 'delivered'],
            ['order' => 0, 'number' => 'CH-JUL-CANCELLED-CHALLAN', 'date' => '2026-07-12', 'qty' => 50, 'status' => 'cancelled'],
        ] as $attributes) {
            $order = $orders[$attributes['order']];

            DeliveryChallan::create([
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'challan_number' => $attributes['number'],
                'challan_date' => $attributes['date'],
                'delivery_qty' => $attributes['qty'],
                'status' => $attributes['status'],
            ]);
        }

        $usdInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DASH-PAYMENT-USD',
            'currency' => 'USD',
            'conversion_rate' => 125,
        ]);
        $bdtInvoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'INV-DASH-PAYMENT-BDT',
            'currency' => 'BDT',
        ]);

        Payment::create([
            'invoice_id' => $usdInvoice->id,
            'payment_date' => '2026-02-12',
            'paid_amount' => 2,
        ]);
        Payment::create([
            'invoice_id' => $bdtInvoice->id,
            'payment_date' => '2026-07-15',
            'paid_amount' => 300,
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonCount(12, 'data.yearly_order_amounts')
            ->assertJsonPath('data.yearly_order_amounts.1.label', 'Feb 2026')
            ->assertJsonPath('data.yearly_order_amounts.1.amount', 1000)
            ->assertJsonPath('data.yearly_order_amounts.1.payment_amount', 250)
            ->assertJsonPath('data.yearly_order_amounts.5.label', 'Jun 2026')
            ->assertJsonPath('data.yearly_order_amounts.5.amount', 1250)
            ->assertJsonPath('data.yearly_order_amounts.6.label', 'Jul 2026')
            ->assertJsonPath('data.yearly_order_amounts.6.amount', 500)
            ->assertJsonPath('data.yearly_order_amounts.6.payment_amount', 300)
            ->assertJsonPath('data.yearly_order_amounts.11.label', 'Dec 2026');
    } finally {
        Carbon::setTestNow();
    }
});

it('returns current month order received and delivered challan amounts', function () {
    Carbon::setTestNow('2026-07-31 10:00:00');

    try {
        $user = User::create([
            'name' => 'Dashboard Current Month User',
            'email' => fake()->unique()->safeEmail(),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $customer = Customer::create(['name' => 'Dashboard Current Month Customer']);
        CompanySetting::create(['usd_to_bdt_rate' => 125]);

        $bdtOrder = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CURRENT-BDT',
            'order_date' => '2026-07-05',
            'ordered_qty' => 100,
            'total_amount' => 1000,
            'currency' => 'BDT',
            'status' => 'confirmed',
        ]);
        $usdOrder = Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-CURRENT-USD',
            'order_date' => '2026-07-10',
            'ordered_qty' => 10,
            'total_amount' => 10,
            'currency' => 'USD',
            'status' => 'confirmed',
        ]);
        Order::create([
            'customer_id' => $customer->id,
            'order_number' => 'ORD-PREVIOUS-MONTH',
            'order_date' => '2026-06-30',
            'ordered_qty' => 10,
            'total_amount' => 500,
            'currency' => 'BDT',
            'status' => 'confirmed',
        ]);

        DeliveryChallan::create([
            'customer_id' => $customer->id,
            'order_id' => $bdtOrder->id,
            'challan_number' => 'CH-CURRENT-BDT',
            'challan_date' => '2026-07-20',
            'delivery_qty' => 40,
            'status' => 'delivered',
        ]);
        DeliveryChallan::create([
            'customer_id' => $customer->id,
            'order_id' => $usdOrder->id,
            'challan_number' => 'CH-CURRENT-USD',
            'challan_date' => '2026-07-21',
            'delivery_qty' => 5,
            'status' => 'delivered',
        ]);
        DeliveryChallan::create([
            'customer_id' => $customer->id,
            'order_id' => $bdtOrder->id,
            'challan_number' => 'CH-CURRENT-DRAFT',
            'challan_date' => '2026-07-22',
            'delivery_qty' => 50,
            'status' => 'draft',
        ]);

        $this->actingAs($user, 'api')
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.stats.current_month_order_amount_bdt', 2250)
            ->assertJsonPath('data.stats.current_month_challan_amount_bdt', 1025);
    } finally {
        Carbon::setTestNow();
    }
});

it('seeds internally consistent current-month demo orders and challans', function () {
    Carbon::setTestNow('2026-08-18 10:00:00');

    try {
        $this->seed([
            UserSeeder::class,
            ItemSeeder::class,
            OrderDemoSeeder::class,
        ]);

        $admin = User::query()->where('email', 'admin@gmail.com')->firstOrFail();

        $this->actingAs($admin, 'api')
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.stats.current_month_order_amount_bdt', 840000)
            ->assertJsonPath('data.stats.current_month_challan_amount_bdt', 804000);

        expect(DeliveryChallan::query()
            ->whereIn('challan_number', ['CH-001', 'CH-002', 'CH-003', 'CH-004'])
            ->whereBetween('challan_date', ['2026-08-01', '2026-08-31'])
            ->where('status', 'delivered')
            ->count())->toBe(4)
            ->and((float) Order::query()->where('order_number', 'ORD-101')->value('material_amount'))->toBe(6200.0)
            ->and((float) Order::query()->where('order_number', 'ORD-102')->value('material_amount'))->toBe(800.0);
    } finally {
        Carbon::setTestNow();
    }
});

it('shows all outstanding pi balances without applying due-date conditions', function () {
    Carbon::setTestNow('2026-07-30 10:00:00');

    try {
        $user = User::create([
            'name' => 'Dashboard Customer Due User',
            'email' => fake()->unique()->safeEmail(),
            'role' => 'user',
            'status' => 'active',
            'email_verified_at' => now(),
            'password' => Hash::make('password'),
        ]);
        $customer = Customer::create([
            'name' => 'Dashboard Due Customer',
            'payment_type' => 'cash',
            'payment_currency' => 'BDT',
        ]);

        foreach ([
            ['number' => 'PI-DASH-DUE', 'delivery_date' => '2026-07-01', 'due_total' => 750, 'status' => 'issued', 'delivery_status' => 'delivered'],
            ['number' => 'PI-DASH-FUTURE', 'delivery_date' => '2026-08-01', 'due_total' => 900, 'status' => 'issued', 'delivery_status' => 'delivered'],
            ['number' => 'PI-DASH-PAID', 'delivery_date' => '2026-07-01', 'due_total' => 500, 'status' => 'paid', 'delivery_status' => 'delivered'],
            ['number' => 'PI-DASH-PARTIAL', 'delivery_date' => '2026-07-01', 'due_total' => 1200, 'status' => 'issued', 'delivery_status' => 'partial'],
        ] as $index => $attributes) {
            $order = Order::create([
                'customer_id' => $customer->id,
                'order_number' => "ORD-DASH-DUE-{$index}",
                'delivery_date' => $attributes['delivery_date'],
                'delivery_status' => $attributes['delivery_status'],
            ]);

            $proformaInvoice = ProformaInvoice::create([
                'order_id' => $order->id,
                'customer_id' => $customer->id,
                'pi_number' => $attributes['number'],
                'delivery_date' => $attributes['delivery_date'],
                'currency' => 'BDT',
                'payment_type' => 'cash',
                'grand_total' => 1000,
                'due_total' => $attributes['due_total'],
                'status' => $attributes['status'],
            ]);
            $proformaInvoice->orders()->attach($order);
        }

        $this->actingAs($user, 'api')
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.stats.customer_due_amount', 1650)
            ->assertJsonPath('data.stats.unpaid_due', 1650)
            ->assertJsonCount(1, 'data.customer_dues')
            ->assertJsonPath('data.customer_dues.0.customer_id', $customer->id)
            ->assertJsonPath('data.customer_dues.0.customer_name', 'Dashboard Due Customer')
            ->assertJsonPath('data.customer_dues.0.outstanding_pi_count', 2)
            ->assertJsonPath('data.customer_dues.0.due_amount', 1650)
            ->assertJsonPath('data.customer_dues.0.currency', 'BDT');
    } finally {
        Carbon::setTestNow();
    }
});

it('converts usd customer dues to bdt using the pi rate with a global fallback', function () {
    $user = User::create([
        'name' => 'Dashboard Currency Due User',
        'email' => fake()->unique()->safeEmail(),
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => now(),
        'password' => Hash::make('password'),
    ]);
    $customer = Customer::create(['name' => 'Dashboard Mixed Currency Customer']);
    CompanySetting::create(['usd_to_bdt_rate' => 120]);

    foreach ([
        ['currency' => 'BDT', 'due_total' => 500, 'conversion_rate' => null],
        ['currency' => 'USD', 'due_total' => 10, 'conversion_rate' => 125],
        ['currency' => 'USD', 'due_total' => 5, 'conversion_rate' => null],
    ] as $index => $attributes) {
        $order = Order::create([
            'customer_id' => $customer->id,
            'order_number' => "ORD-DASH-CURRENCY-DUE-{$index}",
            'currency' => $attributes['currency'],
            'delivery_status' => 'delivered',
        ]);

        $proformaInvoice = ProformaInvoice::create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'pi_number' => "PI-DASH-CURRENCY-DUE-{$index}",
            'currency' => $attributes['currency'],
            'conversion_rate' => $attributes['conversion_rate'],
            'grand_total' => $attributes['due_total'],
            'due_total' => $attributes['due_total'],
            'status' => 'issued',
        ]);
        $proformaInvoice->orders()->attach($order);
    }

    $this->actingAs($user, 'api')
        ->getJson('/api/dashboard/summary')
        ->assertOk()
        ->assertJsonPath('data.stats.customer_due_amount', 2350)
        ->assertJsonPath('data.stats.unpaid_due', 2350)
        ->assertJsonPath('data.customer_dues.0.customer_id', $customer->id)
        ->assertJsonPath('data.customer_dues.0.outstanding_pi_count', 3)
        ->assertJsonPath('data.customer_dues.0.due_amount', 2350)
        ->assertJsonPath('data.customer_dues.0.currency', 'BDT');
});
