<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'item_code' => 'ZIP-NYL-STD-BLK',
                'item_name' => 'Nylon Zipper',
                'item_type' => 'Zipper',
                'size' => 'Standard',
                'color' => 'Black',
                'unit_measurement' => 'PC',
                'price' => 0.62,
                'currency' => 'USD',
                'hh_code' => '9607.11.00',
                'weight' => 0.041,
            ],
            [
                'item_code' => 'ZIP-NYL-05-BLK',
                'item_name' => 'Nylon Zipper #5',
                'item_type' => 'Zipper',
                'size' => '#5',
                'color' => 'Black',
                'unit_measurement' => 'PC',
                'price' => 0.58,
                'currency' => 'USD',
                'hh_code' => '9607.11.00',
                'weight' => 0.038,
            ],
            [
                'item_code' => 'ZIP-NYL-03-WHT',
                'item_name' => 'Nylon Zipper #3',
                'item_type' => 'Zipper',
                'size' => '#3',
                'color' => 'White',
                'unit_measurement' => 'PC',
                'price' => 0.42,
                'currency' => 'USD',
                'hh_code' => '9607.11.00',
                'weight' => 0.026,
            ],
            [
                'item_code' => 'SLD-MTL-SM-BLK',
                'item_name' => 'Metal Slider',
                'item_type' => 'Slider',
                'size' => 'Small',
                'color' => 'Black Nickel',
                'unit_measurement' => 'PC',
                'price' => 0.20,
                'currency' => 'USD',
                'hh_code' => '9607.20.00',
                'weight' => 0.006,
            ],
            [
                'item_code' => 'SLD-MTL-MD-SIL',
                'item_name' => 'Metal Slider',
                'item_type' => 'Slider',
                'size' => 'Medium',
                'color' => 'Silver',
                'unit_measurement' => 'PC',
                'price' => 0.24,
                'currency' => 'USD',
                'hh_code' => '9607.20.00',
                'weight' => 0.008,
            ],
            [
                'item_code' => 'LCK-REG-BLK',
                'item_name' => 'Locket',
                'item_type' => 'Locket',
                'size' => 'Regular',
                'color' => 'Black',
                'unit_measurement' => 'PC',
                'price' => 0.10,
                'currency' => 'USD',
                'hh_code' => '9607.20.00',
                'weight' => 0.004,
            ],
            [
                'item_code' => 'LCK-REG-GLD',
                'item_name' => 'Locket',
                'item_type' => 'Locket',
                'size' => 'Regular',
                'color' => 'Gold',
                'unit_measurement' => 'PC',
                'price' => 0.13,
                'currency' => 'USD',
                'hh_code' => '9607.20.00',
                'weight' => 0.004,
            ],
            [
                'item_code' => 'PUL-RBR-STD-BLK',
                'item_name' => 'Rubber Puller',
                'item_type' => 'Puller',
                'size' => 'Standard',
                'color' => 'Black',
                'unit_measurement' => 'PC',
                'price' => 0.16,
                'currency' => 'USD',
                'hh_code' => '9607.20.00',
                'weight' => 0.005,
            ],
            [
                'item_code' => 'TAP-NYL-20MM-BLK',
                'item_name' => 'Nylon Tape',
                'item_type' => 'Tape',
                'size' => '20mm',
                'color' => 'Black',
                'unit_measurement' => 'MTR',
                'price' => 0.08,
                'currency' => 'USD',
                'hh_code' => '5806.32.00',
                'weight' => 0.012,
            ],
            [
                'item_code' => 'BAG-POLY-STD-CLR',
                'item_name' => 'Poly Bag',
                'item_type' => 'Packaging',
                'size' => 'Standard',
                'color' => 'Clear',
                'unit_measurement' => 'PC',
                'price' => 0.03,
                'currency' => 'USD',
                'hh_code' => '3923.21.00',
                'weight' => 0.003,
            ],
        ];

        foreach ($items as $item) {
            Item::query()->updateOrCreate(
                ['item_code' => $item['item_code']],
                $item
            );
        }
    }
}
