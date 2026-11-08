<?php

namespace App\Services\Customers;

use App\Models\Customer;
use App\Models\ProformaInvoice;
use Carbon\CarbonImmutable;

class CustomerDueService
{
    public function currentDueAmount(ProformaInvoice $proformaInvoice, Customer $customer, ?CarbonImmutable $today = null): float
    {
        $dueTotal = (float) ($proformaInvoice->due_total ?? 0);

        if ($dueTotal <= 0 || $proformaInvoice->status === 'paid') {
            return 0;
        }

        $dueDate = $this->dueDate($proformaInvoice, $customer);

        if (! $dueDate) {
            return 0;
        }

        $today ??= CarbonImmutable::today();

        return $today->greaterThan($dueDate) ? $dueTotal : 0;
    }

    public function dueDate(ProformaInvoice $proformaInvoice, Customer $customer): ?CarbonImmutable
    {
        $baseDate = $this->baseDate($proformaInvoice);

        if (! $baseDate) {
            return null;
        }

        $termDays = $this->termDays($proformaInvoice->payment_type ?: $customer->payment_type);

        if ($termDays === null) {
            return null;
        }

        return $baseDate->addDays($termDays);
    }

    private function baseDate(ProformaInvoice $proformaInvoice): ?CarbonImmutable
    {
        return $proformaInvoice->delivery_date
            ? CarbonImmutable::parse($proformaInvoice->delivery_date)
            : null;
    }

    private function termDays(?string $paymentType): ?int
    {
        return match ($paymentType) {
            'cash' => 0,
            'lc_30' => 30,
            'lc_60' => 60,
            'lc_90' => 90,
            default => null,
        };
    }
}
