<?php

namespace App\Http\Middleware;

use App\Support\Monitoring\RequestTrace;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MonitorRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        if (RequestTrace::until() > time()) {
            // Exclude query strings, credentials, headers and request bodies.
            $trace = new RequestTrace($request->method(), $request->url());
            $request->attributes->set('_monitoring_trace', $trace);
            RequestTrace::write([
                'type' => 'request_started', 'request_id' => $trace->id,
                'method' => $trace->method, 'url' => $trace->url,
            ]);
        }

        $response = $next($request);
        if ($request->attributes->get('_monitoring_trace') instanceof RequestTrace) {
            $request->attributes->set('_monitoring_response_ms', round((hrtime(true) - $request->attributes->get('_monitoring_trace')->started) / 1e6, 3));
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $trace = $request->attributes->get('_monitoring_trace');
        if (! $trace instanceof RequestTrace) {
            return;
        }

        RequestTrace::write([
            'type' => 'request', 'request_id' => $trace->id,
            'method' => $trace->method, 'url' => $trace->url,
            'route' => $request->route()?->getName(), 'status' => $response->getStatusCode(),
            'duration_ms' => round((hrtime(true) - $trace->started) / 1e6, 3),
            'response_duration_ms' => $request->attributes->get('_monitoring_response_ms'),
            'query_count' => $trace->queryCount, 'query_duration_ms' => round($trace->queryTimeMs, 3),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ]);
        $request->attributes->remove('_monitoring_trace');
        $request->attributes->remove('_monitoring_response_ms');
    }
}
