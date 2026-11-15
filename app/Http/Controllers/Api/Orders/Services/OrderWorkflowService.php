<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Variant;
use App\Services\Orders\StorefrontQuote;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly StorefrontQuote $storefrontQuote,
    ) {}

    public function create(array $data, ?int $userId, ?string $trackingHash = null): Order
    {
        $source = $userId === null ? 'storefront' : 'staff';
        $hash = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
        $replay = function (Order $existing) use ($source, $userId, $hash, $trackingHash): Order {
            if ($existing->source !== $source || $existing->created_by !== $userId
                || $existing->request_hash !== $hash || $existing->tracking_hash !== $trackingHash) {
                throw ValidationException::withMessages(['submission_key' => 'This submission key was already used.']);
            }

            return $existing;
        };

        try {
            return DB::transaction(function () use ($data, $userId, $trackingHash, $source, $hash, $replay) {
                if ($userId !== null) {
                    DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                }

                $existing = Order::where('submission_key', $data['submission_key'])->first();
                if ($existing) {
                    return $replay($existing);
                }

                // Reserve the unique submission before reading stock so a concurrent retry
                // can recover the original order even if it consumed the last unit.
                $order = Order::create(collect($data)->only(['submission_key', 'customer_name', 'phone', 'email', 'address'])->all() + [
                    'source' => $source,
                    'tracking_hash' => $trackingHash,
                    'request_hash' => $hash,
                    'created_by' => $userId,
                    'subtotal_cents' => 0,
                    'delivery_cents' => 0,
                    'total_cents' => 0,
                ]);
                $items = $data['items'];
                $variants = $this->storefrontQuote->variants($items, lock: true);
                $delivery = $trackingHash === null ? $data['delivery_cents'] : $this->storefrontQuote->deliveryCents();
                $quote = $this->storefrontQuote->quote($items, $variants, $delivery);

                if ($trackingHash !== null) {
                    $expectedPrices = array_column($items, 'price_cents');
                    $currentPrices = array_column($quote['items'], 'price_cents');
                    if (! $quote['available'] || $delivery !== $data['delivery_cents'] || $currentPrices !== $expectedPrices) {
                        throw new HttpResponseException(response()->json([
                            'message' => $quote['available'] ? 'Prices or delivery fees changed. Review the new total.' : 'Some cart items are no longer available in the requested quantity.',
                            'quote' => $quote,
                        ], 409));
                    }
                } elseif (! $quote['available']) {
                    foreach ($quote['items'] as $line) {
                        if ($line['issue'] !== null) {
                            $message = $line['issue'] === 'unavailable'
                                ? 'Select active products and variants.'
                                : "Insufficient stock for {$variants[$line['variant_id']]->sku}.";
                            throw ValidationException::withMessages(['items' => $message]);
                        }
                    }
                }

                $order->update(collect($quote)->only(['subtotal_cents', 'delivery_cents', 'total_cents'])->all());
                $lines = collect($quote['items'])->keyBy('variant_id');
                foreach ($variants as $variant) {
                    $line = $lines[$variant->id];
                    OrderItem::create([
                        'order_id' => $order->id,
                        'variant_id' => $variant->id,
                        'quantity' => $line['quantity'],
                        'product_name' => $line['name'],
                        'sku' => $variant->sku,
                        'attributes' => $variant->attributes,
                        'price_cents' => $line['price_cents'],
                        'total_cents' => $line['total_cents'],
                    ]);
                    $this->stockMovements->record($variant, -$line['quantity'], 'Order created', $userId, $order->id);
                }
                DB::table('order_status_events')->insert([
                    'order_id' => $order->id,
                    'user_id' => $userId,
                    'status' => 'Pending',
                    'created_at' => now(),
                ]);

                return $order;
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = Order::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                return $replay($existing);
            }

            if ($trackingHash !== null && Order::where('tracking_hash', $trackingHash)->exists()) {
                throw ValidationException::withMessages(['tracking_secret' => 'This tracking secret was already used.']);
            }

            throw $exception;
        }
    }

    public function status(int $id, string $status, int $userId): Order
    {
        return DB::transaction(function () use ($id, $status, $userId) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($order->status === $status) {
                return $order;
            }
            $nextStatuses = [
                'Pending' => 'Confirmed',
                'Confirmed' => 'Processing',
                'Processing' => 'Shipped',
                'Shipped' => 'Delivered',
            ];
            if (in_array($order->status, ['Delivered', 'Cancelled']) || ($status !== 'Cancelled' && ($nextStatuses[$order->status] ?? null) !== $status)) {
                throw ValidationException::withMessages(['status' => 'Invalid status transition.']);
            }
            if ($status === 'Cancelled') {
                foreach (OrderItem::where('order_id', $id)->orderBy('variant_id')->get() as $line) {
                    $variant = Variant::whereKey($line->variant_id)->lockForUpdate()->firstOrFail();
                    $this->stockMovements->record($variant, $line->quantity, 'Order cancelled', $userId, $id);
                }
            }
            $order->update(['status' => $status]);
            DB::table('order_status_events')->insert([
                'order_id' => $id,
                'user_id' => $userId,
                'status' => $status,
                'created_at' => now(),
            ]);

            return $order;
        }, 3);
    }
}
