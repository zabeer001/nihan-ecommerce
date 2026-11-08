<?php

namespace App\Http\Controllers\Api\Dashboard\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\Variant;

class DashboardSummaryService
{
    public function handle(): array
    {
        return [
            'products' => Product::count(),
            'low_stock' => Variant::where('active', true)->where('stock', '<=', 5)->count(),
            'orders' => Order::selectRaw('status, count(*) as count')->groupBy('status')->pluck('count', 'status'),
            'delivered_sales_cents' => (int) Order::where('status', 'Delivered')->sum('total_cents'),
        ];
    }
}
