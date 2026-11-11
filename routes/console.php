<?php

use App\Http\Controllers\Api\Product\Services\ProductImageUploadService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('catalog:prune-image-uploads', function (ProductImageUploadService $uploads) {
    $uploads->prune();
})->purpose('Delete expired product photo uploads');

Schedule::command('catalog:prune-image-uploads')->hourly();
