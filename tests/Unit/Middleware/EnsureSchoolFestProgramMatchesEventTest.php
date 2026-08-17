<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureSchoolFestProgramMatchesEvent;
use App\Models\FestEvent;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class EnsureSchoolFestProgramMatchesEventTest extends TestCase
{
    public function test_allows_matching_kalotsav_event(): void
    {
        $middleware = new EnsureSchoolFestProgramMatchesEvent();
        $event = new FestEvent(['id' => 50, 'event_type' => 'kalolsavam']);

        $request = Request::create('/school-admin/tenant123/kalotsav/events/50/overview');
        $route = (new Route('GET', '/school-admin/{tenant}/kalotsav/events/{event}/overview', []))
            ->bind($request);
        $route->setParameter('program', 'kalotsav');
        $route->setParameter('event', $event);
        $route->setParameter('tenant', 'tenant123');

        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_prevents_redirect_loop_when_on_correct_prefix(): void
    {
        $middleware = new EnsureSchoolFestProgramMatchesEvent();
        $event = new FestEvent(['id' => 50, 'event_type' => 'sports']);

        $request = Request::create('/school-admin/tenant123/sports/events/50/overview');
        $route = (new Route('GET', '/school-admin/{tenant}/sports/events/{event}/overview', []))
            ->bind($request);
        $route->setParameter('program', 'sports');
        $route->setParameter('event', $event);
        $route->setParameter('tenant', 'tenant123');

        $request->setRouteResolver(fn () => $route);

        $response = $middleware->handle($request, fn ($req) => response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }
}
