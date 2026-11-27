<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\StorefrontQuote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderUpdateService
{
    public function __construct(
        private readonly StockMovementService $stockMovements,
        private readonly StorefrontQuote $quote,
    ) {}

    public function handle(Request $request, int $id): Order
    {
        $data = $request->validate([
            'customer_name' => 'required|string|max:255',
            'phone' => 'required|string|max:40',
            'email' => 'nullable|email|max:255',
            'address' => 'required|string|max:2000',
            'items' => 'sometimes|required|array|min:1|max:100',
            'items.*.id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000000',
            'new_items' => 'sometimes|array|max:100',
            'new_items.*.variant_id' => 'required|integer|distinct|exists:variants,id',
            'new_items.*.quantity' => 'required|integer|min:1|max:1000000',
        ]);

        return DB::transaction(function () use ($request, $id, $data) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            if (! empty($data['new_items']) && ! in_array($order->status, OrderWorkflowService::PRODUCT_ADDITION_STATUSES, true)) {
                throw ValidationException::withMessages(['new_items' => 'Products can be added only to Pending, Confirmed, or Processing orders.']);
            }
            if (isset($data['items']) || ! empty($data['new_items'])) {
                $this->updateItems($order, $data['items'] ?? null, $data['new_items'] ?? [], $request->user()->id);
            }
            unset($data['items'], $data['new_items']);
            $data += ['email' => null];
            $customerData = [
                'name' => $data['customer_name'], 'phone' => $data['phone'],
                'email' => $data['email'], 'address' => $data['address'],
            ];
            $customer = $order->customer_id
                ? Customer::whereKey($order->customer_id)->lockForUpdate()->firstOrFail()
                : Customer::create($customerData);
            $customer->update($customerData);
            $order->update($data + ['customer_id' => $customer->id]);

            return $order;
        }, 3);
    }

    // The order row must be locked before editing its lines or reserved stock.
    private function updateItems(Order $order, ?array $items, array $newItems, int $userId): void
    {
        $lines = OrderItem::where('order_id', $order->id)->orderBy('variant_id')->orderBy('id')->lockForUpdate()->get();
        $quantities = $items === null ? $lines->keyBy('id') : collect($items)->keyBy('id');
        if ($quantities->count() !== $lines->count() || $lines->contains(fn ($line) => ! $quantities->has($line->id))) {
            throw ValidationException::withMessages(['items' => 'The order items changed. Reload the order and try again.']);
        }
        if ($lines->count() + count($newItems) > 100) {
            throw ValidationException::withMessages(['new_items' => 'An order can contain at most 100 products.']);
        }
        $existingVariants = $lines->pluck('variant_id');
        foreach ($newItems as $item) {
            if ($existingVariants->contains($item['variant_id'])) {
                throw ValidationException::withMessages(['new_items' => 'This product is already in the order. Change its quantity instead.']);
            }
        }

        // Lock all variants in the same order used by stock and cancellation workflows.
        $variants = $this->quote->variants([
            ...$lines->map(fn ($line) => ['variant_id' => $line->variant_id])->all(), ...$newItems,
        ], lock: true);
        foreach ($lines as $line) {
            $quantity = (int) $quantities[$line->id]['quantity'];
            $difference = $line->quantity - $quantity;
            if ($difference !== 0 && $order->status !== 'Cancelled') {
                $this->stockMovements->record($variants[$line->variant_id], $difference, 'Order quantity updated', $userId, $order->id);
            }
            $line->update(['quantity' => $quantity, 'total_cents' => $quantity * $line->price_cents]);
        }
        foreach ($newItems as $item) {
            $variant = $variants->get($item['variant_id']);
            $product = $variant?->product;
            if (! $variant?->active || $product?->status !== 'active' || ! $product?->has_active_category) {
                throw ValidationException::withMessages(['new_items' => 'Select active products and variants in an active category.']);
            }
            $quantity = (int) $item['quantity'];
            if ($order->status !== 'Cancelled') {
                $this->stockMovements->record($variant, -$quantity, 'Order product added', $userId, $order->id);
            }
            OrderItem::create([
                'order_id' => $order->id, 'variant_id' => $variant->id, 'quantity' => $quantity,
                'product_name' => $product->name, 'sku' => $variant->sku, 'attributes' => $variant->attributes,
                'price_cents' => $variant->price_cents, 'total_cents' => $quantity * $variant->price_cents,
            ]);
        }
        $subtotal = (int) OrderItem::where('order_id', $order->id)->sum('total_cents');
        $order->subtotal_cents = $subtotal;
        $order->total_cents = $subtotal + $order->delivery_cents;
    }
}
