<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Symfony\Component\HttpFoundation\Response;

class PrometheusMetrics
{
    public function __construct(private CollectorRegistry $registry) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $route = $request->route()?->getName() ?? $request->path();
        $method = $request->method();
        $status = (string) $response->getStatusCode();
        $duration = microtime(true) - $startTime;

        // Requests tellen per endpoint en methode
        $this->registry
            ->getOrRegisterCounter('app', 'http_requests_total', 'Totaal aantal requests', ['method', 'route', 'status'])
            ->inc([$method, $route, $status]);

        // Response tijd per endpoint
        $this->registry
            ->getOrRegisterGauge('app', 'http_response_time_seconds', 'Response tijd in seconden', ['method', 'route'])
            ->set($duration, [$method, $route]);

        return $response;
    }
}
