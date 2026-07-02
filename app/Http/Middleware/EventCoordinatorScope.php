<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\McqExam;
use App\Models\SchoolUserEventScope;
use App\Services\School\SchoolUserScopeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventCoordinatorScope
{
    use RedirectsUnauthenticated;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('school_event_coordinator')) {
            return $next($request);
        }

        if ($user->isSuperAdmin() || $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal', 'sahodaya_admin'])) {
            return $next($request);
        }

        $tenantId = $request->route('tenantId');
        $path = $request->path();
        $programSlug = $this->inferProgramSlug($path);
        [$eventId, $scopeTypeHint] = $this->resolveEventContext($request, $path, $programSlug);

        $scopes = SchoolUserEventScope::where('user_id', $user->id)
            ->when($tenantId, fn ($q) => $q->where('school_id', $tenantId))
            ->get();

        if ($scopes->isEmpty()) {
            abort(403, 'No event scope assigned to this coordinator account.');
        }

        if ($programSlug === null) {
            return $next($request);
        }

        $matcher = app(SchoolUserScopeService::class);
        $allowed = $scopes->contains(
            fn (SchoolUserEventScope $scope) => $matcher->scopeMatchesRoute($scope, $programSlug, $eventId, $scopeTypeHint)
        );

        abort_unless($allowed, 403, 'You are not assigned to this program or event.');

        return $next($request);
    }

    /** @return array{0: ?int, 1: ?string} */
    private function resolveEventContext(Request $request, string $path, ?string $programSlug): array
    {
        if ($programSlug === 'mcq') {
            $exam = $request->route('exam');
            if ($exam instanceof McqExam) {
                return [$exam->id, 'mcq_exam'];
            }
            if (is_numeric($exam)) {
                return [(int) $exam, 'mcq_exam'];
            }
        }

        if ($programSlug === 'training') {
            $program = $request->route('program') ?? $request->route('trainingProgram');
            if (is_object($program) && isset($program->id)) {
                return [$program->id, 'training_program'];
            }
        }

        $event = $request->route('event') ?? $request->route('festProgram');
        if (is_object($event) && isset($event->id)) {
            return [$event->id, 'fest_event'];
        }

        return [null, null];
    }

    private function inferProgramSlug(string $path): ?string
    {
        if (str_contains($path, '/mcq')) {
            return 'mcq';
        }
        if (str_contains($path, '/training')) {
            return 'training';
        }

        foreach (['kalotsav', 'kids-fest', 'teacher-fest', 'custom'] as $slug) {
            if (str_contains($path, "/{$slug}")) {
                return $slug;
            }
        }

        if (str_contains($path, '/sports/') || str_contains($path, '/sports-meet')) {
            return 'sports-meet';
        }

        if (str_contains($path, '/fest/')) {
            return null;
        }

        return null;
    }
}
