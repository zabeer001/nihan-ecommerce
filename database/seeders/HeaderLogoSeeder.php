<?php

namespace Database\Seeders;

use App\Models\HeaderLogo;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class HeaderLogoSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            HeaderLogo::firstOrCreate(['id' => 1]);
            $logo = HeaderLogo::whereKey(1)->lockForUpdate()->firstOrFail();
            foreach (['before_hover' => 'before-hover', 'after_hover' => 'after-hover'] as $slot => $fixture) {
                $field = $slot.'_path';
                if ($logo->$field) continue;
                $path = 'header-logos/default-'.$fixture.'.svg';
                if (! Storage::disk('public')->exists($path)
                    && ! Storage::disk('public')->put($path, file_get_contents(__DIR__.'/fixtures/branding/'.$fixture.'.svg'))) {
                    throw new RuntimeException('Could not install the default header logo.');
                }
                $logo->$field = $path;
            }
            $logo->save();
        });
    }
}
