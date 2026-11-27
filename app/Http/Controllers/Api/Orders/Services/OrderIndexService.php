<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Models\Order;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class OrderIndexService
{
    public function handle(Request $request): LengthAwarePaginator
    {
        $filters = $request->validate([
            'reference' => 'sometimes|nullable|integer|min:1',
            'source' => 'sometimes|nullable|in:staff,storefront',
            'status' => 'sometimes|nullable|in:Pending,Confirmed,Processing,Shipped,Delivered,Cancelled',
        ]);

        return Order::query()
            ->when($filters['reference'] ?? null, fn ($query, $reference) => $query->whereKey($reference))
            ->when($filters['source'] ?? null, fn ($query, $source) => $query->where('source', $source))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')
            ->paginate(20)->through(fn (Order $order) => [
                ...$order->toArray(),
                'allowed_statuses' => OrderWorkflowService::allowedStatuses($order->status),
            ]);
    }
}
