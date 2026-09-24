<?php

namespace App\Http\Middleware;

use App\Models\State\StateFestEvent;
use App\Support\StateScope;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 11 of the State Kalotsav module — the cutover.
 *
 * While state.module_switched is false this does nothing and both stacks stay reachable, which is
 * what lets an event already under way finish on the screens its operators know. Once it is on, the
 * old /admin/state-workspace/* paths redirect into the module, so bookmarks, emailed links and
 * browser history keep working instead of landing on a screen nobody maintains any more.
 *
 * Only paths with a module equivalent redirect. A workspace screen the module does not have would
 * otherwise redirect to an overview that silently isn't what was asked for, which is worse than
 * leaving the old screen in place.
 */
class RedirectStateWorkspaceToModule
{
    /** Old path tail => module path tail. Anything not listed is left alone. */
    private const MAP = [
        '' => '',
        '/attendance' => '/attendance',
        '/marks' => '/marks',
        '/results' => '/results',
        '/judges' => '/marks',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('state.module_switched') || ! $request->isMethod('GET')) {
            return $next($request);
        }

        $target = $this->moduleUrlFor($request);

        // 302, not 301: a permanent redirect is cached by browsers and would survive the setting
        // being turned back off mid-event, which is the one thing this switch exists to allow.
        return $target ? redirect($target, 302) : $next($request);
    }

    private function moduleUrlFor(Request $request): ?string
    {
        $path = '/'.trim($request->path(), '/');

        if (! str_starts_with($path, '/admin/state-workspace/fest')) {
            return null;
        }

        $tail = substr($path, strlen('/admin/state-workspace/fest'));

        // The event list has no module equivalent — the module is always entered through one event —
        // so it resolves to the most recent event this user can see, or is left alone if there is none.
        if ($tail === '') {
            $event = StateScope::apply(StateFestEvent::query())->orderByDesc('starts_on')->first();

            return $event ? "/admin/state/fest/{$event->id}" : null;
        }

        if (! preg_match('#^/(\d+)(/.*)?$#', $tail, $matches)) {
            return null;
        }

        $eventId = $matches[1];
        $screen = $matches[2] ?? '';

        if (! array_key_exists($screen, self::MAP)) {
            return null;
        }

        return "/admin/state/fest/{$eventId}".self::MAP[$screen];
    }
}
