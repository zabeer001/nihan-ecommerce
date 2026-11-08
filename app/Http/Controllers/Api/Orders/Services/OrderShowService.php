<?php

namespace App\Http\Controllers\Api\Orders\Services;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;

class OrderShowService
{
    public function handle(int $id): array
    {
        return [
            'order' => Order::findOrFail($id),
            'items' => OrderItem::where('order_id', $id)->get(),
            'history' => DB::table('order_status_events')->where('order_id', $id)->orderBy('id')->get(),
        ];
    }
}
