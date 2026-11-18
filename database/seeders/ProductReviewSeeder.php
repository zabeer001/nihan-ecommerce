<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductReviewSeeder extends Seeder
{
    public function run(): void
    {
        $samples = [
            ['Amina (Demo)', 5, 'A pleasant daily companion', 'Demo review: the fragrance feels balanced during a daily commute.', 'published'],
            ['Nabil (Demo)', 4, 'Easy to add to my routine', 'Demo review: straightforward to use, with a finish that suits my car.', 'published'],
            ['Farah (Demo)', 5, 'A thoughtful gift', 'Demo review: a useful gift idea for someone who enjoys a fresh car interior.', 'published'],
            ['Imran (Demo)', 4, 'Enjoying the fragrance', 'Demo review: I started on the lowest setting and adjusted from there.', 'published'],
            ['Samira (Demo)', 3, 'Waiting for moderation', 'Demo review: a little stronger than expected. Testing the pending review queue.', 'pending'],
            ['Review moderation (Demo)', 1, 'Moderation sample', 'Demo review: rejected sample for testing the moderation filter.', 'rejected'],
        ];
        foreach (Product::where('product_code', 'like', 'DEMO-NIHAN-%')->get() as $product) {
            foreach ($samples as $index => [$author, $rating, $title, $body, $status]) {
                // Stable identity survives edits to review text, status and ratings.
                $product->reviews()->firstOrCreate(['author_name' => $author, 'source' => 'admin'], [
                    'rating' => $rating, 'title' => $title, 'body' => $body, 'status' => $status,
                    'created_at' => now()->subDays(18 - $index * 2),
                    'updated_at' => now()->subDays(18 - $index * 2),
                ]);
            }
        }
    }
}
