<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\Inventory\Services\StockMovementService;
use App\Http\Controllers\Api\Orders\Services\OrderWorkflowService;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;
use RuntimeException;

class NihanDemoSeeder extends Seeder
{
    public function run(StockMovementService $stock, OrderWorkflowService $orders): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('NIHAN demo data can only be seeded in local and testing environments.');
        }

        $staffId = User::whereIn('role', config('rbac.administrators'))
            ->where('is_frozen', false)->orderBy('id')->value('id');

        DB::transaction(function () use ($stock, $orders, $staffId) {
            $categories = [];
            foreach (['Accessories', 'Fragrance'] as $name) {
                $categories[$name] = Category::firstOrCreate(['name' => $name], ['active' => true]);
            }

            foreach ($this->catalog() as $code => [$name, $description, $sections, $variants]) {
                $this->seedProduct($code, $name, $description, $sections, $variants, $categories, $stock, $staffId);
            }

            $this->seedOrders($orders, $staffId);
        });

        $this->command?->info('NIHAN demo catalog and customers are ready.');
    }

    private function seedProduct(
        string $code, string $name, string $description, array $sections, array $variants, array $categories,
        StockMovementService $stock, ?int $staffId,
    ): void {
        $product = Product::firstOrCreate(['product_code' => 'DEMO-NIHAN-'.strtoupper($code)], [
            'name' => $name,
            'slug' => 'demo-nihan-'.$code,
            'description' => $description,
            'brand' => 'NIHAN',
            'unit' => 'piece',
            'product_type' => 'physical',
            'tracking_type' => 'quantity',
            'status' => 'active',
        ]);

        if (! $product->wasRecentlyCreated) {
            // The first seed is transactional. Preserve changes staff made to the catalog afterward.
            return;
        }

        $product->categories()->attach(array_map(fn ($section) => $categories[$section]->id, $sections));
        $product->images()->create(['path' => $this->photo($code), 'primary' => true]);

        foreach ($variants as [$suffix, $label, $attributes, $price, $quantity, $image]) {
            ksort($attributes);
            $variant = $product->variants()->create([
                'sku' => 'DEMO-NIHAN-'.strtoupper($code).$suffix,
                'name' => $label,
                'attributes' => $attributes,
                'combination' => hash('sha256', json_encode(array_map('strtolower', $attributes), JSON_THROW_ON_ERROR)),
                'price_cents' => $price,
                'stock' => 0,
                'active' => true,
            ]);
            $stock->record($variant, $quantity, 'Demo opening stock', $staffId);
            if ($image !== null) {
                $variant->images()->create([
                    'product_id' => $product->id,
                    'path' => $this->photo($image),
                    'primary' => true,
                ]);
            }
        }
    }

    private function photo(string $file): string
    {
        $path = "demo/nihan/$file.webp";
        $disk = Storage::disk('public');
        if (! $disk->exists($path) && ! $disk->put($path, file_get_contents(__DIR__."/fixtures/nihan/$file.webp"))) {
            throw new RuntimeException("Could not install the demo photo $file.");
        }

        return $path;
    }

    private function seedOrders(OrderWorkflowService $orders, ?int $staffId): void
    {
        $samples = [
            ['Amina Demo', '01700000001', 'amina', 'Dhaka', 'OUD-NOIR', ['Confirmed', 'Processing']],
            ['Nabil Demo', '01700000002', 'nabil', 'Chattogram', 'PADMA', ['Confirmed', 'Processing', 'Shipped', 'Delivered']],
            ['Farah Demo', '01700000003', 'farah', 'Rajshahi', 'MANGO-LEAF', ['Cancelled']],
            ['Imran Demo', '01700000004', 'imran', 'Sylhet', 'MONSOON', []],
        ];
        foreach ($samples as [$name, $phone, $handle, $city, $sku, $statuses]) {
            $email = "$handle@nihan-demo.example";
            Customer::firstOrCreate(['email' => $email], ['name' => $name, 'phone' => $phone, 'address' => "Demo delivery address, $city"]);

            if ($staffId === null) {
                continue;
            }

            $key = (string) Uuid::uuid5(Uuid::NAMESPACE_URL, "nihan-demo/orders/$handle");
            if (Order::where('submission_key', $key)->exists()) {
                continue;
            }
            $variant = Variant::where('sku', "DEMO-NIHAN-$sku")->where('active', true)->where('stock', '>', 0)
                ->whereHas('product', fn ($product) => $product->where('status', 'active')
                    ->whereHas('categories', fn ($category) => $category->where('active', true)))
                ->first();
            if (! $variant) {
                $this->command?->warn("Skipped the sample order for $sku because the demo variant is unavailable.");

                continue;
            }

            $order = $orders->create([
                'submission_key' => $key,
                'customer_name' => $name,
                'phone' => $phone,
                'email' => $email,
                'address' => "Demo delivery address, $city",
                'delivery_cents' => 8000,
                'items' => [['variant_id' => $variant->id, 'quantity' => 1]],
            ], $staffId);
            foreach ($statuses as $status) {
                $orders->status($order->id, $status, $staffId);
            }
        }

        if ($staffId === null) {
            $this->command?->warn('Sample orders need an active staff account. Rerun this seeder after setting up staff.');
        }
    }

    private function catalog(): array
    {
        return [
            'diffuser' => ['NIHAN AI Car Aroma Diffuser', 'A refillable car diffuser with a 50 ml reservoir, three intensity settings, and a soft starlight glow.', ['Accessories'], [
                ['-GRAPHITE', 'Graphite', ['color' => 'Graphite'], 399000, 24, null],
                ['-SAND', 'Sand Gold', ['color' => 'Sand Gold'], 419000, 16, 'diffuser-sand'],
                ['-FOREST', 'Forest Green', ['color' => 'Forest Green'], 419000, 0, null],
            ]],
            'oud-noir' => ['Oud Noir Refill · 50 ml', 'Smoked oud, amber, and a trace of leather.', ['Fragrance'], [
                ['', null, [], 89000, 45, null],
            ]],
            'padma' => ['Padma Refill · 50 ml', 'Water lily, cool musk, and green tea.', ['Fragrance'], [
                ['', null, [], 79000, 32, null],
            ]],
            'mango-leaf' => ['Mango Leaf Refill · 50 ml', 'Crushed leaves and sun-warmed fruit.', ['Fragrance'], [
                ['', null, [], 79000, 34, null],
            ]],
            'monsoon' => ['Monsoon Refill · 50 ml', 'Wet earth, cardamom, and soft vanilla.', ['Fragrance'], [
                ['', null, [], 85000, 20, null],
            ]],
            'starter-kit' => ['NIHAN Diffuser Starter Kit', 'A Graphite diffuser with your choice of a 50 ml fragrance refill.', ['Accessories', 'Fragrance'], [
                ['-OUD', 'Oud Noir', ['fragrance' => 'Oud Noir'], 459000, 12, null],
                ['-PADMA', 'Padma', ['fragrance' => 'Padma'], 449000, 8, null],
            ]],
        ];
    }
}
