<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\Events\PublicEventVisitorMonitor;
use Illuminate\Support\Str;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Symfony\Component\HttpFoundation\Response;

class LimitPublicEventVisitors
{
    public function handle(Request $request, Closure $next): Response
    {
        $eventId = $request->route('event');
        if (! $eventId || $request->routeIs('tenant.fest.tv', 'tenant.fest.tv-heartbeat')) {
            return $next($request);
        }

        $request->attributes->set('event_visitor_limit', true);
        $visitor = $request->cookie('fest_visitor');
        if (! is_string($visitor) || ! Str::isUuid($visitor)) {
            $visitor = (string) Str::uuid();
        }
        $key = 'fest-active-visitors:'.tenant('id').':'.$eventId;
        $cache = PublicEventVisitorMonitor::cache();
        try {
            $admitted = $cache->lock($key.':lock', 10)->block(3, function () use ($cache, $key, $visitor) {
                $now = time();
                $active = array_filter($cache->get($key, []), fn ($seen) => $seen > $now - 300);
                if (! isset($active[$visitor]) && count($active) >= 200) {
                    return false;
                }
                $active[$visitor] = $now;
                $cache->put($key, $active, 310);

                return true;
            });
        } catch (LockTimeoutException) {
            $admitted = false;
        }

        if ($admitted) {
            PublicEventVisitorMonitor::register($key, (string) tenant('id'), (int) $eventId);
        }

        if (! $admitted) {
            $message = 'We’ll open this page for you as soon as a place is available.';
            $response = $request->expectsJson()
                ? response()->json(['message' => $message], 429)
                : response()->view('public.fest.visitor-limit', ['message' => $message], 429);
            $response->headers->set('Retry-After', '30');
        } else {
            $response = $next($request);
            if ($response->isSuccessful() && str_contains($response->headers->get('Content-Type', 'text/html'), 'text/html')) {
                $url = json_encode('/fest/'.(int) $eventId.'/visitor-heartbeat', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
                $script = '<script>setInterval(async()=>{if(document.visibilityState!=="visible")return;try{const r=await fetch('.$url.',{headers:{Accept:"application/json"},cache:"no-store"});if(r.status===429)location.reload();}catch(e){}},60000);</script>';
                $response->setContent(str_replace('</body>', $script.'</body>', $response->getContent()));
            }
            $response->headers->setCookie(cookie('fest_visitor', $visitor, 1440, '/', null, $request->isSecure(), true, false, 'lax'));
        }
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
