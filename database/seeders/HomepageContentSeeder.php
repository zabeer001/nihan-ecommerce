<?php

namespace Database\Seeders;

use App\Models\HomepageContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class HomepageContentSeeder extends Seeder
{
    public function run(): void
    {
        if (HomepageContent::query()->exists()) return;

        foreach ([
            ['hero', 'hero_slide', 'video', 'homepage/hero.mp4', 'hero.mp4', null, 0],
            ['collection_interlude', 'image_block', 'image', 'homepage/cat.jpeg', 'cat.jpeg', 'Illustration of a sleeping orange cat beside four colorful fragrance bottles', 0],
        ] as [$placement, $type, $mediaType, $path, $fixture, $alt, $position]) {
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                $contents = file_get_contents(database_path('seeders/fixtures/homepage/'.$fixture));
                $disk->put($path, $contents);
            }
            HomepageContent::query()->create([
                    'placement' => $placement,
                    'position' => $position,
                    'content_type' => $type,
                    'is_published' => true,
                    'media_type' => $mediaType,
                    'media_path' => $path,
                    'alt_text' => $alt,
                    'poster_path' => null,
                    'heading' => null,
                    'description' => null,
                    'button_text' => null,
                    'button_url' => null,
                ],
            );
        }
    }
}
