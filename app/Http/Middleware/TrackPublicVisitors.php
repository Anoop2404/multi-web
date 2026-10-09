<?php

namespace App\Http\Middleware;

use App\Services\Events\PublicEventVisitorMonitor;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackPublicVisitors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET')) return $next($request);
        $isTv = $request->routeIs('tenant.fest.tv', 'tenant.fest.tv-heartbeat');
        $key = $isTv ? 'tv-active-visitors' : 'public-active-visitors';
        $visitor = $request->cookie('fest_visitor');
        if (! is_string($visitor) || ! Str::isUuid($visitor)) $visitor = (string) Str::uuid();
        // Share the identifier with the event limiter on this first request.
        $request->cookies->set('fest_visitor', $visitor);
        $request->attributes->set('public_visitor_tracking', true);
        $response = $next($request);
        if (! $response->isSuccessful()) return $response;

        $cache = PublicEventVisitorMonitor::cache();
        try {
            $cache->lock($key.':lock', 10)->block(3, function () use ($cache, $visitor, $key) {
                $active = array_filter($cache->get($key, []), fn ($seen) => $seen > time() - 300);
                $active[(string) tenant('id').':'.$visitor] = time();
                $cache->put($key, $active, 310);
            });
        } catch (LockTimeoutException) {
            // Analytics must not prevent access to the public website.
        }
        if (str_contains($response->headers->get('Content-Type', 'text/html'), 'text/html')) {
            $heartbeatUrl = json_encode($isTv ? '/fest/'.(int) $request->route('event').'/tv/visitor-heartbeat' : '/visitor-heartbeat', JSON_UNESCAPED_SLASHES);
            $script = '<script>setInterval(()=>{if(document.visibilityState==="visible")fetch('.$heartbeatUrl.',{headers:{Accept:"application/json"},cache:"no-store"}).catch(()=>{});},60000);</script>';
            $response->setContent(str_replace('</body>', $script.'</body>', $response->getContent()));
        }
        $response->headers->setCookie(cookie('fest_visitor', $visitor, 1440, '/', null, $request->isSecure(), true, false, 'lax'));
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
