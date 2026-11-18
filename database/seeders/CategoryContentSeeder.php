<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategoryContentSeeder extends Seeder
{
    public function run(): void
    {
        $fragrance = Category::where('name', 'Fragrance')->first();
        if ($fragrance) {
            $this->defaults($fragrance, [
                ['label' => 'Woody', 'title' => 'Oud Noir', 'description' => 'Smoked oud, amber and a trace of leather.', 'color' => '#c9915a'],
                ['label' => 'Floral', 'title' => 'Padma', 'description' => 'Water lily, cool musk and green tea.', 'color' => '#b8c4bd'],
                ['label' => 'Green', 'title' => 'Mango Leaf', 'description' => 'Crushed leaves and sun-warmed fruit.', 'color' => '#8fa66b'],
                ['label' => 'Earthy', 'title' => 'Monsoon', 'description' => 'Wet earth, cardamom and soft vanilla.', 'color' => '#819995'],
            ], [
                'heading' => 'A fresh start for every journey', 'button_text' => 'Explore fragrances', 'button_url' => '/products?category='.$fragrance->id,
                'steps' => [
                    ['title' => 'Choose your fragrance', 'description' => 'Explore woody, floral, green and earthy notes to find your favourite.'],
                    ['title' => 'Refill carefully', 'description' => 'Switch off the diffuser and follow the instructions supplied with your device before refilling.'],
                    ['title' => 'Find your setting', 'description' => 'Start with a low intensity and adjust to suit your space.'],
                ],
            ]);
        }
        $accessories = Category::where('name', 'Accessories')->first();
        if ($accessories) {
            $this->defaults($accessories, [
                ['label' => 'Your space', 'title' => 'Made for the drive', 'description' => 'Choose a diffuser finish that feels at home in your car.', 'color' => '#c2a06b'],
                ['label' => 'Your routine', 'title' => 'Refill and go', 'description' => 'Keep your favourite fragrance ready for the next journey.', 'color' => '#819995'],
            ], [
                'heading' => 'Get to know your diffuser', 'button_text' => 'Shop diffusers', 'button_url' => '/products?category='.$accessories->id,
                'steps' => [
                    ['title' => 'Unpack', 'description' => 'Check the supplied parts and read the setup instructions for your model.'],
                    ['title' => 'Set up', 'description' => 'Position the device securely and add a compatible refill as directed.'],
                    ['title' => 'Make it yours', 'description' => 'Choose your preferred intensity and keep the diffuser clean between refills.'],
                ],
            ]);
        }
    }

    private function defaults(Category $category, array $cards, array $guide): void
    {
        // An empty array is an intentional admin choice; only fill missing values.
        if ($category->content_cards === null) $category->content_cards = $cards;
        if ($category->usage_guide === null) $category->usage_guide = $guide;
        if ($category->isDirty()) $category->save();
    }
}
