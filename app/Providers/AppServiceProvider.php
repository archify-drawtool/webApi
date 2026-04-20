<?php

namespace App\Providers;

use App\Services\ArucoService;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Prometheus\Facades\Prometheus;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ArucoService::class);
    }

    public function boot(): void
    {
        // Actieve tokens — dit is een gauge die altijd de huidige waarde toont
        Prometheus::addGauge('active_tokens_total')
            ->helpText('Aantal actieve sanctum tokens')
            ->value(fn () => PersonalAccessToken::count());
    }
}
