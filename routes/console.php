<?php

use App\Services\PhotoPreviewService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(PhotoPreviewService::class)->cleanupExpired())
    ->everyFiveMinutes()
    ->name('photo-previews:cleanup');
