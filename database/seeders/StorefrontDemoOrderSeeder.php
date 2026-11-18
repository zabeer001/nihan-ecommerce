<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Orders\Services\OrderWorkflowService;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Ramsey\Uuid\Uuid;

class StorefrontDemoOrderSeeder extends Seeder
{
    public function run(OrderWorkflowService $orders): void
    {
        $staffId = User::whereIn('role', config('rbac.administrators'))->where('is_frozen', false)->orderBy('id')->value('id');
        if (! $staffId) {
            $this->command?->warn('Skipped extended demo orders: an active administrator is needed for status changes.');
            return;
        }
        $customers = [
            ['Rafi Demo', 'Dhaka'], ['Tania Demo', 'Chattogram'], ['Sadia Demo', 'Sylhet'], ['Arif Demo', 'Rajshahi'],
            ['Nusrat Demo', 'Khulna'], ['Hasan Demo', 'Barishal'], ['Maliha Demo', 'Rangpur'], ['Sakib Demo', 'Mymensingh'],
            ['Raisa Demo', 'Cumilla'], ['Fahim Demo', 'Gazipur'], ['Nadia Demo', 'Narayanganj'], ['Omar Demo', 'Cox’s Bazar'],
        ];
        $paths = [[], ['Confirmed'], ['Confirmed', 'Processing'], ['Confirmed', 'Processing', 'Shipped'],
            ['Confirmed', 'Processing', 'Shipped', 'Delivered'], ['Confirmed', 'Cancelled']];
        $skus = ['OUD-NOIR', 'PADMA', 'MANGO-LEAF', 'MONSOON', 'REFILL-DUO-WOODY', 'DISCOVERY-SET-GRAPHITE-OUD'];
        for ($index = 0; $index < 24; $index++) {
            $key = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, 'nihan-demo/extended-orders/'.$index);
            if (Order::where('submission_key', $key)->exists()) continue;
            [$name, $city] = $customers[$index % count($customers)];
            $phone = '017000001'.str_pad((string) ($index % count($customers)), 2, '0', STR_PAD_LEFT);
            $email = 'customer-'.($index % count($customers) + 1).'@nihan-demo.example';
            $sku = 'DEMO-NIHAN-'.$skus[$index % count($skus)];
            $quantity = 1 + ($index % 2);
            $variant = Variant::where('sku', $sku)->where('active', true)->where('stock', '>=', $quantity)
                ->whereHas('product', fn ($query) => $query->where('status', 'active')->whereHas('categories', fn ($q) => $q->where('active', true)))
                ->first();
            if (! $variant) {
                $this->command?->warn("Skipped extended demo order $index: $sku is unavailable.");
                continue;
            }
            DB::transaction(function () use ($orders, $staffId, $index, $key, $name, $city, $phone, $email, $variant, $quantity, $paths) {
                Customer::firstOrCreate(['normalized_phone' => Customer::normalizePhone($phone)], [
                    'name' => $name, 'phone' => $phone, 'email' => $email, 'address' => "Sample delivery address, $city, Bangladesh",
                ]);
                $storefront = $index % 2 === 0;
                $order = $orders->create([
                    'submission_key' => $key, 'customer_name' => $name, 'phone' => $phone, 'email' => $email,
                    'address' => "Sample delivery address, $city, Bangladesh", 'delivery_cents' => $storefront ? app(\App\Services\Orders\StorefrontQuote::class)->deliveryCents() : 8000,
                    'items' => [['variant_id' => $variant->id, 'quantity' => $quantity, 'price_cents' => $variant->price_cents]],
                ], $storefront ? null : $staffId, $storefront ? hash('sha256', 'nihan-demo-tracking-'.$index) : null);
                foreach ($paths[$index % count($paths)] as $status) $orders->status($order->id, $status, $staffId);
                $date = now()->subDays(7 + $index * 2)->startOfDay()->addHours(10);
                $events = DB::table('order_status_events')->where('order_id', $order->id)->orderBy('id')->get();
                foreach ($events as $step => $event) {
                    DB::table('order_status_events')->where('id', $event->id)->update(['created_at' => $date->copy()->addHours($step * 6)]);
                }
                $order->timestamps = false;
                $order->forceFill(['created_at' => $date, 'updated_at' => $date->copy()->addHours(($events->count() - 1) * 6)])->save();
            });
        }
    }
}
