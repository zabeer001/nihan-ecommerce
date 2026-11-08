<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
    ) {}

    public function create(array $data, int $userId): Order
    {
        $hash = hash('sha256', json_encode($data));

        return DB::transaction(function () use ($data, $userId, $hash) {
            // Lock the creator first: serializes identical submissions, including initial inserts.
            DB::table('users')->where('id', $userId)->lockForUpdate()->first();
            $existing = Order::where('submission_key', $data['submission_key'])->first();
            if ($existing) {
                if ($existing->created_by !== $userId || $existing->request_hash !== $hash) {
                    throw ValidationException::withMessages(['submission_key' => 'This submission key was already used.']);
                }

                return $existing;
            }
            $items = collect($data['items'])->sortBy('variant_id');
            $lines = [];
            $subtotal = 0;
            foreach ($items as $item) {
                $variant = Variant::whereKey($item['variant_id'])->lockForUpdate()->firstOrFail();
                $product = Product::findOrFail($variant->product_id);
                if (! $variant->active || $product->status !== 'active' || ! $product->categories()->where('active', true)->exists()) {
                    throw ValidationException::withMessages(['items' => 'Select active products and variants.']);
                }
                if ($variant->stock < $item['quantity']) {
                    throw ValidationException::withMessages(['items' => "Insufficient stock for {$variant->sku}."]);
                }
                $total = $variant->price_cents * $item['quantity'];
                $subtotal += $total;
                $lines[] = [
                    'variant' => $variant,
                    'quantity' => $item['quantity'],
                    'product_name' => $product->name,
                    'sku' => $variant->sku,
                    'attributes' => $variant->attributes,
                    'price_cents' => $variant->price_cents,
                    'total_cents' => $total,
                ];
            }
            $order = Order::create(collect($data)->except('items')->all() + ['request_hash' => $hash, 'created_by' => $userId, 'subtotal_cents' => $subtotal, 'total_cents' => $subtotal + $data['delivery_cents']]);
            foreach ($lines as $line) {
                $variant = $line['variant'];
                unset($line['variant']);
                OrderItem::create($line + ['order_id' => $order->id, 'variant_id' => $variant->id]);
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
