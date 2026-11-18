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
            'status' => $code === 'seasonal-preview' ? 'draft' : 'active',
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
                'compare_at_price_cents' => in_array($code, ['starter-kit', 'refill-duo', 'discovery-set'], true) ? $price + 20000 : null,
                'purchase_price_cents' => (int) round($price * 0.55),
                'dealer_price_cents' => (int) round($price * 0.8),
                'corporate_price_cents' => (int) round($price * 0.9),
                'barcode' => 'DEMO-NIHAN-'.strtoupper($code).$suffix,
                'stock' => 0,
                'active' => $code !== 'seasonal-preview',
            ]);
            if ($quantity > 0) $stock->record($variant, $quantity, 'Demo opening stock', $staffId);
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
        $file = match ($file) {
            'oud-noir-100' => 'oud-noir', 'padma-100' => 'padma',
            'mango-leaf-100' => 'mango-leaf', 'monsoon-100', 'seasonal-preview' => 'monsoon',
            'refill-duo', 'discovery-set' => 'starter-kit',
            default => $file,
        };
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
            'oud-noir-100' => ['Oud Noir Refill · 100 ml', 'A larger refill of smoked oud, amber and leather for your daily drive.', ['Fragrance'], [
                ['', '100 ml', ['size' => '100 ml'], 159000, 30, null],
            ]],
            'padma-100' => ['Padma Refill · 100 ml', 'Water lily, cool musk and green tea in a larger refill.', ['Fragrance'], [
                ['', '100 ml', ['size' => '100 ml'], 139000, 28, null],
            ]],
            'mango-leaf-100' => ['Mango Leaf Refill · 100 ml', 'A larger refill with green leaves and sun-warmed fruit.', ['Fragrance'], [
                ['', '100 ml', ['size' => '100 ml'], 139000, 3, null],
            ]],
            'monsoon-100' => ['Monsoon Refill · 100 ml', 'Wet earth, cardamom and vanilla for the longer journey.', ['Fragrance'], [
                ['', '100 ml', ['size' => '100 ml'], 149000, 0, null],
            ]],
            'refill-duo' => ['NIHAN Refill Duo', 'Two 50 ml refills for alternating your everyday fragrance. Demo bundle; image is illustrative.', ['Fragrance'], [
                ['-WOODY', 'Oud Noir + Monsoon', ['pair' => 'Oud Noir + Monsoon'], 159000, 20, null],
                ['-FRESH', 'Padma + Mango Leaf', ['pair' => 'Padma + Mango Leaf'], 145000, 18, null],
            ]],
            'discovery-set' => ['NIHAN Discovery Starter Set', 'A diffuser and a choice of refill, in Graphite or Sand Gold. Demo bundle; image is illustrative.', ['Accessories', 'Fragrance'], [
                ['-GRAPHITE-OUD', 'Graphite / Oud Noir', ['color' => 'Graphite', 'fragrance' => 'Oud Noir'], 469000, 15, null],
                ['-GRAPHITE-PADMA', 'Graphite / Padma', ['color' => 'Graphite', 'fragrance' => 'Padma'], 459000, 12, null],
                ['-SAND-OUD', 'Sand Gold / Oud Noir', ['color' => 'Sand Gold', 'fragrance' => 'Oud Noir'], 489000, 10, 'diffuser-sand'],
                ['-SAND-PADMA', 'Sand Gold / Padma', ['color' => 'Sand Gold', 'fragrance' => 'Padma'], 479000, 0, 'diffuser-sand'],
            ]],
            'seasonal-preview' => ['NIHAN Seasonal Preview', 'Unpublished demo product for testing the draft workflow.', ['Fragrance'], [
                ['', 'Preview', [], 99000, 0, null],
            ]],
            'starter-kit' => ['NIHAN Diffuser Starter Kit', 'A Graphite diffuser with your choice of a 50 ml fragrance refill.', ['Accessories', 'Fragrance'], [
                ['-OUD', 'Oud Noir', ['fragrance' => 'Oud Noir'], 459000, 12, null],
                ['-PADMA', 'Padma', ['fragrance' => 'Padma'], 449000, 8, null],
            ]],
        ];
    }
}
