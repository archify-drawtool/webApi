<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Prometheus\Facades\Prometheus;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $route = $request->route()?->getName() ?? $request->path();
        $method = $request->method();
        $status = $response->getStatusCode();
        $duration = microtime(true) - $startTime;

        // Totaal aantal requests per endpoint en methode
        Prometheus::getCounter('http_requests_total')
            ->labels([$method, $route, $status])
            ->increment();

        // Response tijd per endpoint
        Prometheus::getHistogram('http_response_time_seconds')
            ->labels([$method, $route])
            ->observe($duration);

        return $response;
    }
}
