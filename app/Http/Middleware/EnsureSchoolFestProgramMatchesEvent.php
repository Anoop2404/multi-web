<?php

namespace App\Http\Middleware;

use App\Models\FestEvent;
use App\Support\SchoolFestProgram;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolFestProgramMatchesEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $program = $request->route('program');
        $event = $request->route('event');

        if (! is_string($program) || $program === '' || $event === null) {
            return $next($request);
        }

        if (! $event instanceof FestEvent) {
            $event = FestEvent::findOrFail($event);
        }

        abort_unless(
            $event->event_type === SchoolFestProgram::eventType($program),
            404,
            'This event is not available under that program.',
        );

        return $next($request);
    }
}
