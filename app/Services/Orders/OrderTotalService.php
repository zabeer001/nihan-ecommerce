<?php

namespace App\Services\Orders;

use App\Models\CompanySetting;
use App\Models\Order;
use App\Http\Controllers\Api\Payment\Services\PaymentBalanceService;
use App\Services\ProformaInvoices\ProformaInvoiceAmountService;

class OrderTotalService
{
    public function __construct(
        private readonly ProformaInvoiceAmountService $proformaInvoiceAmountService,
        private readonly PaymentBalanceService $paymentBalanceService,
    ) {
    }

    public function recalculate(Order $order): float
    {
        $orderCurrency = $this->currency($order->currency);
        $usdToBdtRate = (float) (CompanySetting::query()->value('usd_to_bdt_rate') ?: 120);
        $total = $order->items()->get(['currency', 'line_total'])->sum(function ($item) use ($orderCurrency, $usdToBdtRate): float {
            $amount = (float) ($item->line_total ?? 0);
            $itemCurrency = $this->currency($item->currency ?: $orderCurrency);

            if ($itemCurrency === $orderCurrency) {
                return $amount;
            }

            return $itemCurrency === 'USD'
                ? $amount * $usdToBdtRate
                : $amount / $usdToBdtRate;
        });

        $order->update(['total_amount' => $total]);
        $this->syncProformaInvoices($order);

        return $total;
    }

    private function syncProformaInvoices(Order $order): void
    {
        $proformaInvoices = $order->proformaInvoices()->get()
            ->push($order->proformaInvoice()->first())
            ->filter()
            ->unique('id')
            ->values();

        foreach ($proformaInvoices as $proformaInvoice) {
            $this->proformaInvoiceAmountService->recalculate($proformaInvoice);
            $this->paymentBalanceService->syncProformaInvoiceTotals($proformaInvoice);

            $proformaInvoice->orders()->get()
                ->push($proformaInvoice->order()->first())
                ->filter()
                ->unique('id')
                ->each(fn (Order $linkedOrder) => $this->paymentBalanceService->syncOrderProformaTotals($linkedOrder));
        }
    }

    private function currency(?string $currency): string
    {
        return strtoupper((string) $currency) === 'USD' ? 'USD' : 'BDT';
    }
}
