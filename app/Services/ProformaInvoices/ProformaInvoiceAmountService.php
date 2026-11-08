<?php

namespace App\Services\ProformaInvoices;

use App\Models\Order;
use App\Models\ProformaInvoice;

class ProformaInvoiceAmountService
{
    public function recalculate(ProformaInvoice $proformaInvoice): void
    {
        $orderIds = $proformaInvoice->orders()
            ->pluck('orders.id')
            ->push($proformaInvoice->order_id)
            ->filter()
            ->map(fn ($orderId) => (int) $orderId)
            ->unique()
            ->values()
            ->all();
        $amounts = $this->applyOrderTotals([
            'bank_document_charge' => $proformaInvoice->bank_document_charge,
            'freight_courier_charge' => $proformaInvoice->freight_courier_charge,
        ], $orderIds);

        $proformaInvoice->forceFill([
            'subtotal' => $amounts['subtotal'],
            'bank_document_charge' => $amounts['bank_document_charge'],
            'freight_courier_charge' => $amounts['freight_courier_charge'],
            'grand_total' => $amounts['grand_total'],
        ])->save();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, int> $orderIds
     * @return array<string, mixed>
     */
    public function applyOrderTotals(array $data, array $orderIds): array
    {
        $orders = Order::query()
            ->whereIn('id', collect($orderIds)->map(fn ($orderId) => (int) $orderId)->unique()->all())
            ->with('items:id,order_id,line_total')
            ->lockForUpdate()
            ->get();

        $subtotal = round((float) $orders->sum(function (Order $order): float {
            if ($order->total_amount !== null) {
                return (float) $order->total_amount;
            }

            return (float) $order->items->sum('line_total');
        }), 2);
        $bankDocumentCharge = round((float) ($data['bank_document_charge'] ?? 0), 2);
        $freightCourierCharge = round((float) ($data['freight_courier_charge'] ?? 0), 2);

        $data['subtotal'] = $subtotal;
        $data['bank_document_charge'] = $bankDocumentCharge;
        $data['freight_courier_charge'] = $freightCourierCharge;
        $data['grand_total'] = round($subtotal + $bankDocumentCharge + $freightCourierCharge, 2);

        return $data;
    }
}
