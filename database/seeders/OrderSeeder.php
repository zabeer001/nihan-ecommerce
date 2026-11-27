<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Orders\Services\OrderWorkflowService;
use App\Models\Order;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class OrderSeeder extends Seeder
{
    public function run(OrderWorkflowService $orders): void
    {
        $staffId = User::where('is_frozen', false)->where(function ($query) {
            $query->whereIn('role', config('rbac.administrators'))
                ->orWhereIn('email', config('rbac.software_engineer_emails', []));
        })->orderBy('id')->value('id');

        if (! $staffId) {
            $this->command?->warn('OrderSeeder needs an active administrator or configured software engineer. No orders were created.');
            return;
        }

        $cities = ['Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna', 'Barishal',
            'Rangpur', 'Mymensingh', 'Cumilla', 'Gazipur', 'Narayanganj', 'Cox\'s Bazar'];
        $paths = [
            [], ['Confirmed'], ['Confirmed', 'Processing'], ['Confirmed', 'Processing', 'Shipped'],
            ['Confirmed', 'Processing', 'Shipped', 'Delivered'], ['Confirmed', 'Cancelled'],
        ];
        $counts = ['created' => 0, 'existing' => 0, 'skipped' => 0];

        for ($index = 0; $index < 24; $index++) {
            $key = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'nihan-demo/order-seeder/v1/'.$index);
            $result = DB::transaction(function () use ($staffId, $key, $index, $cities, $paths, $orders) {
                // Serialize concurrent runs and let the normal workflow manage stock and history.
                User::whereKey($staffId)->lockForUpdate()->firstOrFail();
                if (Order::where('submission_key', $key)->exists()) {
                    return 'existing';
                }
                $variants = Variant::where('active', true)->where('stock', '>', 0)
                    ->whereHas('product', fn ($query) => $query->where('status', 'active')
                        ->whereHas('categories', fn ($category) => $category->where('active', true)))
                    ->orderBy('id')->limit(1 + $index % 3)->lockForUpdate()->get();
                if ($variants->isEmpty()) {
                    return 'skipped';
                }
                $customer = $index % count($cities);
                $order = $orders->create([
                    'submission_key' => $key,
                    'customer_name' => 'Demo Order Customer '.($customer + 1),
                    'phone' => '01799000'.str_pad((string) $customer, 3, '0', STR_PAD_LEFT),
                    'email' => 'order-customer-'.($customer + 1).'@example.test',
                    'address' => 'Demo delivery address, '.$cities[$customer].', Bangladesh',
                    'delivery_cents' => [6000, 8000, 10000][$index % 3],
                    'items' => $variants->map(fn ($variant) => [
                        'variant_id' => $variant->id,
                        'quantity' => min(1 + $index % 2, $variant->stock),
                    ])->all(),
                ], $staffId);
                foreach ($paths[$index % count($paths)] as $status) {
                    $orders->status($order->id, $status, $staffId);
                }

                return 'created';
            }, 3);
            $counts[$result]++;
        }

        $this->command?->info("Orders: {$counts['created']} created, {$counts['existing']} already seeded, {$counts['skipped']} skipped (no stocked active products).");
    }
}
