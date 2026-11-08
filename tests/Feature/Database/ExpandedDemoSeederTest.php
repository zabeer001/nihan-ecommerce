<?php

use App\Models\Customer;
use App\Models\DeliveryChallan;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ProformaInvoice;
use App\Models\RawMaterial;
use App\Models\RawMaterialPurchase;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;

it('seeds a varied and internally consistent operational demo dataset', function () {
    Carbon::setTestNow('2026-08-18 10:00:00');

    try {
        $this->seed();

        expect(User::query()->count())->toBeGreaterThanOrEqual(8)
            ->and(Customer::query()->count())->toBeGreaterThanOrEqual(9)
            ->and(Order::query()->count())->toBeGreaterThanOrEqual(10)
            ->and(ProformaInvoice::query()->count())->toBeGreaterThanOrEqual(7)
            ->and(DeliveryChallan::query()->count())->toBeGreaterThanOrEqual(11)
            ->and(Invoice::query()->count())->toBeGreaterThanOrEqual(5)
            ->and(Payment::query()->count())->toBeGreaterThanOrEqual(8)
            ->and(Supplier::query()->count())->toBeGreaterThanOrEqual(6)
            ->and(RawMaterial::query()->count())->toBeGreaterThanOrEqual(8)
            ->and(RawMaterialPurchase::query()->count())->toBeGreaterThanOrEqual(6);

        $inconsistentItems = Order::query()
            ->where('order_number', 'like', 'DEMO-ORD-%')
            ->with('items')
            ->get()
            ->filter(fn (Order $order): bool => abs(
                (float) $order->material_amount - (float) $order->items->sum('line_total')
            ) > 0.001);

        expect($inconsistentItems)->toHaveCount(0)
            ->and(Order::query()->where('delivery_status', 'pending')->exists())->toBeTrue()
            ->and(Order::query()->where('delivery_status', 'partial')->exists())->toBeTrue()
            ->and(Order::query()->where('delivery_status', 'delivered')->exists())->toBeTrue()
            ->and(Order::query()->where('status', 'cancelled')->exists())->toBeTrue()
            ->and(Invoice::query()->where('payment_status', 'unpaid')->exists())->toBeTrue()
            ->and(Invoice::query()->where('payment_status', 'partial')->exists())->toBeTrue()
            ->and(Invoice::query()->where('payment_status', 'paid')->exists())->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});
