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
            // Admin preview responses can contain unpublished results and must never be
            // stored by a shared edge cache. Anonymous festival GETs do not need a PHP
            // session; dropping Laravel's Set-Cookie lets Cloudflare cache them.
            if ($request->user() ?? auth()->user()) {
                $response->headers->set('Cache-Control', 'private, no-store');

                return $response;
            }

            if ($request->is('fest') || $request->is('fest/*')) {
                $response->headers->remove('Set-Cookie');
            }

            if ($request->is('fest/*/live/data') || $request->is('fest/*/scoreboard/data')) {
                $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage=10, stale-while-revalidate=30');
            } elseif ($request->is('fest/*/live') || $request->is('fest/*/scoreboard') || $request->is('fest/*/tv')) {
                $response->headers->set('Cache-Control', 'public, max-age=10, s-maxage=30, stale-while-revalidate=60');
            } elseif (
                $request->is('fest/*/results')
                || $request->is('fest/*/items/*/results')
                // The event's own item-finder page (GET /fest/{event}) shows each item's
                // live publish state (a "Results" button vs "Not yet published") the same
                // way the dedicated /results page does — it was falling into the generic
                // 1-hour bucket below, so unpublishing an item's results left a stale
                // "Results" button visible to visitors (and any CDN in front of the site)
                // for up to an hour after the admin action, even though the underlying
                // FestEventItem row was updated immediately.
                || preg_match('#^fest/\d+$#', $request->path()) === 1
            ) {
                $response->headers->set('Cache-Control', 'public, max-age=30, s-maxage=60, stale-while-revalidate=120');
            } else {
                $response->headers->set('Cache-Control', 'public, max-age=3600, s-maxage=3600, stale-while-revalidate=86400');
            }
        }

        return $response;
    }
}
