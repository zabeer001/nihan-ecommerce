<?php

namespace App\Services\DeliveryChallans;

use App\Models\DeliveryChallan;
use App\Models\DeliveryChallanVariantLine;
use App\Models\Order;
use App\Models\OrderItemStyle;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VariantInventoryService
{
    /**
     * @return array{variants: array<int, array<string, mixed>>, legacy_unallocated: array<int, array<string, mixed>>}
     */
    public function forOrder(Order $order): array
    {
        $order->loadMissing(['items.styles']);

        $lines = DeliveryChallanVariantLine::query()
            ->whereHas('deliveryChallan', fn ($query) => $query->where('order_id', $order->id))
            ->with('deliveryChallan:id,order_id,challan_number,challan_date,status')
            ->orderBy('id')
            ->get();

        $linesByVariant = $lines
            ->whereNotNull('order_item_style_id')
            ->groupBy('order_item_style_id');

        $variants = [];

        foreach ($order->items as $item) {
            foreach ($item->styles as $index => $variant) {
                $history = ($linesByVariant->get($variant->id) ?? collect())
                    ->map(fn (DeliveryChallanVariantLine $line): array => [
                        'challan_id' => $line->delivery_challan_id,
                        'challan_number' => $line->deliveryChallan?->challan_number,
                        'challan_date' => $line->deliveryChallan?->challan_date,
                        'status' => $line->deliveryChallan?->status,
                        'quantity' => (float) $line->quantity,
                        'unit' => $line->unit,
                    ])
                    ->values();
                $deliveredQty = (float) $history
                    ->where('status', 'delivered')
                    ->sum('quantity');
                $orderedQty = (float) $variant->quantity;

                $variants[] = [
                    'order_item_style_id' => $variant->id,
                    'order_item_id' => $item->id,
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'variant_number' => $index + 1,
                    'variant_name' => $variant->style_name,
                    'variant_code' => $variant->style_code,
                    'custom_fields' => $variant->custom_fields ?? [],
                    'custom_fields_order' => $variant->custom_fields_order ?? [],
                    'unit' => $item->unit,
                    'ordered_qty' => $orderedQty,
                    'delivered_qty' => $deliveredQty,
                    'remaining_qty' => max($orderedQty - $deliveredQty, 0),
                    'history' => $history->all(),
                ];
            }
        }

        $legacy = $lines
            ->where('legacy_unallocated', true)
            ->map(fn (DeliveryChallanVariantLine $line): array => [
                'line_id' => $line->id,
                'challan_id' => $line->delivery_challan_id,
                'challan_number' => $line->deliveryChallan?->challan_number,
                'challan_date' => $line->deliveryChallan?->challan_date,
                'status' => $line->deliveryChallan?->status,
                'quantity' => (float) $line->quantity,
                'unit' => $line->unit,
            ])
            ->values()
            ->all();

        return [
            'variants' => $variants,
            'legacy_unallocated' => $legacy,
        ];
    }

    /**
     * @param array<int, array{order_item_style_id: int, quantity: float|int|string}> $variantLines
     */
    public function syncLines(DeliveryChallan $challan, array $variantLines): void
    {
        $variants = OrderItemStyle::query()
            ->whereHas('orderItem', fn ($query) => $query->where('order_id', $challan->order_id))
            ->with('orderItem')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($variants->isEmpty()) {
            $challan->variantLines()->delete();

            return;
        }

        $positiveLines = collect($variantLines)
            ->map(fn (array $line): array => [
                'order_item_style_id' => (int) ($line['order_item_style_id'] ?? 0),
                'quantity' => (float) ($line['quantity'] ?? 0),
            ])
            ->filter(fn (array $line): bool => $line['quantity'] > 0)
            ->values();

        if ($positiveLines->isEmpty()) {
            throw ValidationException::withMessages([
                'variant_lines' => 'At least one variant quantity is required for this order.',
            ]);
        }

        if ($positiveLines->pluck('order_item_style_id')->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variant_lines' => 'Each variant may appear only once in a challan.',
            ]);
        }

        foreach ($positiveLines as $index => $line) {
            if (! $variants->has($line['order_item_style_id'])) {
                throw ValidationException::withMessages([
                    "variant_lines.$index.order_item_style_id" => 'The selected variant does not belong to this order.',
                ]);
            }
        }

        if ($challan->status === 'delivered') {
            $this->validateAvailability($challan, $positiveLines, $variants);
        }

        $challan->variantLines()->delete();

        foreach ($positiveLines as $line) {
            /** @var OrderItemStyle $variant */
            $variant = $variants->get($line['order_item_style_id']);
            $item = $variant->orderItem;

            $challan->variantLines()->create([
                'order_item_style_id' => $variant->id,
                'quantity' => $line['quantity'],
                'unit' => $item->unit,
                'item_code_snapshot' => $item->item_code,
                'item_name_snapshot' => $item->item_name,
                'variant_name_snapshot' => $variant->style_name,
                'custom_fields_snapshot' => $variant->custom_fields,
                'legacy_unallocated' => false,
            ]);
        }

        $this->syncHeaderTotals($challan);
    }

    public function syncOrderDeliveryStatus(Order $order): void
    {
        $order->loadMissing('items.styles');
        $orderedQty = (float) $order->items->flatMap->styles->sum('quantity');

        if ($orderedQty <= 0) {
            $orderedQty = (float) ($order->ordered_qty ?? $order->items->sum('quantity'));
        }

        $deliveredQty = (float) $order->deliveryChallans()
            ->where('status', 'delivered')
            ->sum('delivery_qty');

        $activeChallans = $order->deliveryChallans()
            ->where(function ($query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', 'cancelled');
            });
        $hasActiveChallans = (clone $activeChallans)->exists();
        $hasUndeliveredChallans = (clone $activeChallans)
            ->where(function ($query): void {
                $query
                    ->whereNull('status')
                    ->orWhere('status', '!=', 'delivered');
            })
            ->exists();
        $isFullyDelivered = $hasActiveChallans
            && ! $hasUndeliveredChallans
            && $orderedQty > 0
            && $deliveredQty >= $orderedQty;

        $status = $deliveredQty <= 0
            ? 'pending'
            : ($isFullyDelivered ? 'delivered' : 'partial');

        if ($order->delivery_status !== $status) {
            $order->update(['delivery_status' => $status]);
        }
    }

    private function validateAvailability(DeliveryChallan $challan, Collection $lines, Collection $variants): void
    {
        $variantIds = $lines->pluck('order_item_style_id')->all();
        $previousLines = DeliveryChallanVariantLine::query()
            ->whereIn('order_item_style_id', $variantIds)
            ->where('delivery_challan_id', '!=', $challan->id)
            ->whereHas('deliveryChallan', fn ($query) => $query->where('status', 'delivered'))
            ->lockForUpdate()
            ->get()
            ->groupBy('order_item_style_id');

        foreach ($lines as $index => $line) {
            /** @var OrderItemStyle $variant */
            $variant = $variants->get($line['order_item_style_id']);
            $previouslyDelivered = (float) ($previousLines->get($variant->id)?->sum('quantity') ?? 0);
            $remaining = max((float) $variant->quantity - $previouslyDelivered, 0);

            if ($line['quantity'] > $remaining) {
                throw ValidationException::withMessages([
                    "variant_lines.$index.quantity" => "Only {$remaining} {$variant->orderItem->unit} remains for this variant.",
                ]);
            }
        }
    }

    private function syncHeaderTotals(DeliveryChallan $challan): void
    {
        $lines = $challan->variantLines()->get();
        $units = $lines->pluck('unit')->filter()->unique()->values();

        $challan->update([
            'delivery_qty' => (float) $lines->sum('quantity'),
            'delivery_unit' => $units->count() === 1 ? $units->first() : ($units->isEmpty() ? null : 'MIXED'),
        ]);
    }
}
