<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetPublicCacheHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->isSuccessful()) {
            if ($request->is('fest/*/live/data')) {
                $response->headers->set('Cache-Control', 'no-store');
            } elseif ($request->is('fest/*/live') || $request->is('fest/*/scoreboard')) {
                $response->headers->set('Cache-Control', 'no-cache, max-age=0, must-revalidate');
            } elseif ($request->is('fest/*/results') || $request->is('fest/*/items/*/results')) {
                $response->headers->set('Cache-Control', 'public, max-age=30, s-maxage=60, stale-while-revalidate=120');
            } else {
                $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400');
            }
        }

        return $response;
    }
}
