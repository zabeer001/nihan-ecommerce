<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderShowService
{
    public function handle(int $id): array
    {
        $order = Order::findOrFail($id);

        return [
            'order' => $order,
            'editable' => true,
            'can_add_products' => in_array($order->status, OrderWorkflowService::PRODUCT_ADDITION_STATUSES, true),
            'allowed_statuses' => OrderWorkflowService::allowedStatuses($order->status),
            'items' => OrderItem::where('order_id', $id)->get(),
            'history' => DB::table('order_status_events')->where('order_id', $id)->orderBy('id')->get(),
        ];
    }
}
