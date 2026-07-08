<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\FestEvent;
use App\Models\FestEventStaff;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFestDisciplineAdmin
{
    use RedirectsUnauthenticated;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $this->redirectToLogin($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenantId = $request->route('tenantId');
        if ($user->hasRole('sahodaya_admin') && $user->tenant_id === $tenantId) {
            return $next($request);
        }

        $event = $request->route('event');
        if (! $event instanceof FestEvent) {
            $eventId = $request->route('event');
            $event = is_numeric($eventId) ? FestEvent::find($eventId) : null;
        }

        if ($event && FestEventStaff::where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->where('duty', 'discipline')
            ->exists()) {
            return $next($request);
        }

        abort(403, 'Sports discipline admin access required.');
    }
}
