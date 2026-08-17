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
            $event = FestEvent::find($event);
            if (! $event) {
                return $next($request);
            }
        }

        $expectedType = SchoolFestProgram::eventType($program);
        $actualType = $event->event_type;

        $isKalotsavMatch = in_array($expectedType, ['kalolsavam', 'kalotsav'], true) &&
            in_array($actualType, ['kalolsavam', 'kalotsav', 'art_fest', 'co_curricular', 'general'], true);

        $isSportsMatch = in_array($expectedType, ['sports', 'sports_meet'], true) &&
            in_array($actualType, ['sports', 'sports_meet', 'athletics'], true);

        $matches = $actualType === $expectedType || $isKalotsavMatch || $isSportsMatch;

        if (! $matches) {
            $tenantId = $request->route('tenant') ?? $request->route('tenantId') ?? $request->segment(2);
            $correctSlug = SchoolFestProgram::slugForEventType($actualType);

            if ($tenantId && $request->isMethod('GET')) {
                return redirect("/school-admin/{$tenantId}/{$correctSlug}/events/{$event->id}/overview");
            }

            abort_unless(
                $matches,
                404,
                'This event is not available under that program.',
            );
        }

        return $next($request);
    }
}
