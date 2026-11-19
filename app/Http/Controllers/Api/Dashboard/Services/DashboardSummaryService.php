<?php

namespace App\Http\Controllers\Api\Dashboard\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class DashboardSummaryService
{
    public function handle(): array
    {
        return [
            'products' => Product::count(),
            'low_stock' => Variant::where('active', true)->where('stock', '<=', 5)->count(),
            'orders' => Order::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'delivered_sales_cents' => (int) Order::where('status', 'Delivered')->sum('total_cents'),
            'monthly_activity' => $this->monthlyActivity(),
        ];
    }

    private function monthlyActivity(): array
    {
        $start = now()->startOfMonth()->subMonths(5);
        $end = $start->copy()->addMonths(6);
        $orders = Order::where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->selectRaw('SUBSTR(created_at, 1, 7) as month, COUNT(*) as total')
            ->groupByRaw('SUBSTR(created_at, 1, 7)')->pluck('total', 'month');

        $deliveries = DB::table('order_status_events')->where('status', 'Delivered')
            ->selectRaw('order_id, MIN(created_at) as delivered_at')->groupBy('order_id');
        $sales = DB::table('orders')->joinSub($deliveries, 'deliveries', function ($join) {
            $join->on('orders.id', '=', 'deliveries.order_id');
        })->where('orders.status', 'Delivered')
            ->where('delivered_at', '>=', $start)->where('delivered_at', '<', $end)
            ->selectRaw('SUBSTR(delivered_at, 1, 7) as month, SUM(orders.total_cents) as total')
            ->groupByRaw('SUBSTR(delivered_at, 1, 7)')->pluck('total', 'month');

        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $date = $start->copy()->addMonths($i);
            $key = $date->format('Y-m');
            $months[] = [
                'month' => $date->format('M Y'),
                'orders' => (int) ($orders[$key] ?? 0),
                'sales_cents' => (int) ($sales[$key] ?? 0),
            ];
        }

        return $months;
    }
}
