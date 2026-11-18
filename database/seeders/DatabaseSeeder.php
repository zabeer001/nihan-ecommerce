<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            HomepageContentSeeder::class,
            HeaderLogoSeeder::class,
            NihanDemoSeeder::class,
            ProductImageFallbackSeeder::class,
            CategoryContentSeeder::class,
            ProductReviewSeeder::class,
            StorefrontDemoOrderSeeder::class,
        ]);
    }
}
