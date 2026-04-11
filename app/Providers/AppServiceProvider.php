<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Prometheus\Facades\Prometheus;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Requests tellen
        Prometheus::addCounter('http_requests_total')
            ->label('method')
            ->label('route')
            ->label('status');

        // Response tijd (gauge ipv histogram — pakket ondersteunt geen histogram)
        Prometheus::addGauge('http_response_time_seconds')
            ->label('method')
            ->label('route');

        // Actieve tokens
        Prometheus::addGauge('active_tokens_total')
            ->helpText('Aantal actieve sanctum tokens')
            ->value(fn () => PersonalAccessToken::count());

        // Inlogpogingen — wordt verhoogd in AuthController
        Prometheus::addCounter('login_attempts_total')
            ->label('status');
    }
}
