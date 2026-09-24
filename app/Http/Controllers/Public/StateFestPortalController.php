<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\State\StateFestEvent;
use App\Models\State\StateSahodaya;
use App\Services\State\Fest\StatePublicPortalService;
use Illuminate\Http\Request;

/**
 * Phase 7 of the State Kalotsav module — the public face of the event.
 *
 * No authentication and no State scoping: these pages are for parents, schools and the press. Every
 * page asks the service whether its section is published before rendering anything, so a page that
 * has not been released 404s rather than rendering empty — "not published yet" and "no results" are
 * different answers and the public should not have to guess which one they are looking at.
 */
class StateFestPortalController extends Controller
{
    public function __construct(private StatePublicPortalService $portal) {}

    public function home(Request $request)
    {
        $events = $this->portal->publicEvents();

        return view('state.public.home', [
            'events' => $events->map(fn (StateFestEvent $e) => [
                'id' => $e->id,
                'name' => $e->name,
                'starts_on' => $e->starts_on?->format('d M Y'),
                'ends_on' => $e->ends_on?->format('d M Y'),
                'visibility' => $this->portal->visibility($e),
            ]),
        ]);
    }

    public function schedule(StateFestEvent $event)
    {
        $this->portal->assertVisible($event, 'schedule');

        return view('state.public.schedule', [
            'event' => $event,
            'days' => $this->portal->schedule($event),
            'visibility' => $this->portal->visibility($event),
        ]);
    }

    public function results(Request $request, StateFestEvent $event)
    {
        $this->portal->assertVisible($event, 'results');

        return view('state.public.results', [
            'event' => $event,
            'items' => $this->portal->results($event, $request->query('item')),
            'visibility' => $this->portal->visibility($event),
        ]);
    }

    public function ranking(StateFestEvent $event)
    {
        $this->portal->assertVisible($event, 'ranking');

        return view('state.public.ranking', [
            'event' => $event,
            'ranking' => $this->portal->ranking($event),
            'visibility' => $this->portal->visibility($event),
        ]);
    }

    /** A Sahodaya's own page. Reachable from the ranking, and gated by the same setting. */
    public function sahodaya(StateFestEvent $event, StateSahodaya $sahodaya)
    {
        $this->portal->assertVisible($event, 'ranking');

        return view('state.public.sahodaya', $this->portal->sahodaya($event, $sahodaya) + [
            'event' => $event,
            'visibility' => $this->portal->visibility($event),
        ]);
    }

    /** Certificate verification — by QR, or by typing the code printed on the certificate. */
    public function verify(Request $request, ?string $code = null)
    {
        $code = $code ?? $request->query('code');

        return view('state.public.verify', [
            'code' => $code,
            'result' => $code ? $this->portal->verify($code) : null,
        ]);
    }
}
