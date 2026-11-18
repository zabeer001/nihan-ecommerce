<?php

namespace Database\Seeders;

use App\Models\HomepageContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HomepageContentSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['placement' => 'hero', 'position' => 0, 'media_type' => 'video', 'fixture' => 'homepage/hero.mp4', 'path' => 'homepage/hero.mp4',
                'alt_text' => null, 'heading' => 'A fresh feeling for every journey', 'description' => 'Explore NIHAN diffusers and fragrances for your everyday drive.',
                'button_text' => 'Explore the collection', 'button_url' => '/products', 'is_published' => true],
            ['placement' => 'collection_interlude', 'position' => 0, 'media_type' => 'image', 'fixture' => 'homepage/cat.jpeg', 'path' => 'homepage/cat.jpeg',
                'alt_text' => 'Illustration of a sleeping orange cat beside four colorful fragrance bottles',
                'heading' => 'Find your favourite notes', 'description' => 'From smoky oud to fresh green leaves, discover a fragrance that feels like you.',
                'button_text' => 'Shop fragrances', 'button_url' => '/products', 'is_published' => true],
            ['placement' => 'collection_interlude', 'position' => 1, 'media_type' => 'image', 'fixture' => 'nihan/starter-kit.webp', 'path' => 'homepage/demo-starter-kit.webp',
                'alt_text' => 'NIHAN diffuser starter kit with a fragrance refill',
                'heading' => 'Start with a set', 'description' => 'Pair a diffuser with a refill and make the next journey your own.',
                'button_text' => 'Shop starter kits', 'button_url' => '/products/demo-nihan-starter-kit', 'is_published' => true],
            ['placement' => 'collection_interlude', 'position' => 2, 'media_type' => 'image', 'fixture' => 'nihan/monsoon.webp', 'path' => 'homepage/demo-seasonal-preview.webp',
                'alt_text' => 'Monsoon fragrance refill used as a demo campaign preview',
                'heading' => 'Seasonal campaign preview', 'description' => 'Unpublished demo block for trying a new homepage campaign.',
                'button_text' => null, 'button_url' => null, 'is_published' => false],
        ] as $sample) {
            $fixture = $sample['fixture']; $path = $sample['path'];
            unset($sample['fixture'], $sample['path']);
            // A hero is a singleton; interludes have independent positions.
            $identity = ['placement' => $sample['placement']];
            if ($sample['placement'] !== 'hero') $identity['position'] = $sample['position'];
            $content = HomepageContent::firstOrCreate($identity, [
                ...$sample, 'content_type' => $sample['placement'] === 'hero' ? 'hero_carousel' : 'image_block',
            ]);
            $seedMedia = $content->media()->where('media_path', $path)->exists();
            if (! $content->wasRecentlyCreated && ! $seedMedia) continue;
            $disk = Storage::disk('public');
            if (! $disk->exists($path) && ! $disk->put($path, file_get_contents(__DIR__.'/fixtures/'.$fixture))) {
                throw new RuntimeException("Could not install homepage fixture $fixture.");
            }
            $content->media()->firstOrCreate(['media_path' => $path], [
                'media_type' => $sample['media_type'], 'position' => 0, 'is_published' => true,
            ]);
            // Upgrade untouched legacy demo blocks without replacing admin content.
            foreach (['heading', 'description', 'button_text', 'button_url'] as $field) {
                if ($content->$field === null) $content->$field = $sample[$field];
            }
            if ($content->isDirty()) $content->save();
        }
    }
}
