<?php

namespace Tests\Unit;

use App\Http\Middleware\LimitPublicEventVisitors;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicEventVisitorLimitTest extends TestCase
{
    private function visit(int $event, ?string $visitor = null, bool $tv = false)
    {
        $request = Request::create('/fest/'.$event.'/'.($tv ? 'tv' : 'results'), 'GET', [], $visitor ? ['fest_visitor' => $visitor] : []);
        $route = new Route('GET', 'fest/{event}/'.($tv ? 'tv' : 'results'), fn () => null);
        $route->name($tv ? 'tenant.fest.tv' : 'tenant.fest.results');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);

        return (new LimitPublicEventVisitors)->handle($request, fn () => response('<html><body>Results</body></html>'));
    }

    public function test_cap_admits_existing_visitors_and_exempts_tv_and_other_events(): void
    {
        config(['cache.default' => 'array']);
        $active = [];
        for ($i = 0; $i < 200; $i++) {
            $active[(string) Str::uuid()] = time();
        }
        $key = 'fest-active-visitors:'.tenant('id').':4';
        Cache::put($key, $active, 310);

        $this->assertSame(429, $this->visit(4)->getStatusCode());
        $this->assertSame(200, $this->visit(4, array_key_first($active))->getStatusCode());
        $this->assertCount(200, Cache::get($key));
        $this->assertSame(200, $this->visit(4, tv: true)->getStatusCode());
        $this->assertCount(200, Cache::get($key));
        $this->assertSame(200, $this->visit(5)->getStatusCode());
    }

    public function test_expired_visitors_release_places_and_responses_cannot_be_shared_cached(): void
    {
        config(['cache.default' => 'array']);
        $key = 'fest-active-visitors:'.tenant('id').':6';
        Cache::put($key, array_fill_keys(range(1, 200), time() - 301), 310);
        $response = $this->visit(6);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, Cache::get($key));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('visitor-heartbeat', $response->getContent());
        $this->assertCount(1, $response->headers->getCookies());
    }
    public function test_monitor_deduplicates_a_browser_across_events_and_ignores_expired_visitors(): void
    {
        config(['cache.default' => 'array']);
        $cache = \App\Services\Events\PublicEventVisitorMonitor::cache();
        $browser = (string) Str::uuid();
        $cache->put('event-a', [$browser => time(), 'expired' => time() - 301], 310);
        $cache->put('event-b', [$browser => time()], 310);
        \App\Services\Events\PublicEventVisitorMonitor::register('event-a', 'tenant-a', 1);
        \App\Services\Events\PublicEventVisitorMonitor::register('event-b', 'tenant-a', 2);

        $cache->put('public-active-visitors', ['tenant-a:'.$browser => time(), 'expired' => time() - 301], 310);
        $snapshot = \App\Services\Events\PublicEventVisitorMonitor::snapshot();
        $this->assertSame(1, $snapshot['active_visitors']);
        $this->assertCount(2, $snapshot['events']);
        $this->assertSame([1, 1], array_column($snapshot['events'], 'active'));
    }

    public function test_tv_tracking_is_separate_and_uses_a_tv_heartbeat(): void
    {
        config(['cache.default' => 'array']);
        $request = Request::create('/fest/4/tv');
        $route = new Route('GET', 'fest/{event}/tv', fn () => null);
        $route->name('tenant.fest.tv')->bind($request);
        $request->setRouteResolver(fn () => $route);
        $response = (new \App\Http\Middleware\TrackPublicVisitors)->handle($request, fn () => response('<html><body>TV</body></html>'));
        $snapshot = \App\Services\Events\PublicEventVisitorMonitor::snapshot();

        $this->assertSame(0, $snapshot['active_visitors']);
        $this->assertSame(1, $snapshot['active_tv_screens']);
        $this->assertStringContainsString('/fest/4/tv/visitor-heartbeat', $response->getContent());
        $this->assertNull(\App\Services\Events\PublicEventVisitorMonitor::cache()->get('fest-active-visitors:'.tenant('id').':4'));
    }

}
