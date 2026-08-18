<?php

namespace App\Http\Middleware;

use App\Http\Middleware\Concerns\RedirectsUnauthenticated;
use App\Models\McqExam;
use App\Models\SchoolUserEventScope;
use App\Services\School\SchoolUserScopeService;
use App\Support\TenantUserCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EventCoordinatorScope
{
    use RedirectsUnauthenticated;

    /**
     * Roles named after one specific fest program, but which share the same coarse-grained
     * `fest.*` permissions as every other program (Kalotsav, Sports, Kids Fest, Teacher Fest,
     * English Fest, Science Fest, Custom all use identical permission strings — there's no
     * per-program permission namespace to gate on). Without this map, e.g. a
     * school_sports_coordinator could view and write to Kalotsav/Kids Fest/etc too, despite
     * their role name and permission-set implying "Sports Meet only". See
     * Documents/Path_breaks.md.
     *
     * @var array<string, string>
     */
    private const SINGLE_PROGRAM_ROLES = [
        'school_sports_coordinator'     => 'sports-meet',
        'school_kalotsavam_coordinator' => 'kalotsav',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasRole('school_event_coordinator')) {
            foreach (self::SINGLE_PROGRAM_ROLES as $role => $allowedProgramSlug) {
                if (! $user->hasRole($role)) {
                    continue;
                }

                if ($user->isSuperAdmin() || $user->hasAnyRole(TenantUserCatalog::schoolManagementRoles()) || $user->hasRole('sahodaya_admin')) {
                    return $next($request);
                }

                $programSlug = $this->inferProgramSlug($request->path());

                // No program in the URL (e.g. the school dashboard) — nothing to check.
                abort_if(
                    $programSlug !== null && $programSlug !== $allowedProgramSlug,
                    403,
                    'Your account is scoped to one fest program only.',
                );

                return $next($request);
            }
        }

        if (! $user || ! $user->hasRole('school_event_coordinator')) {
            return $next($request);
        }

        if ($user->isSuperAdmin() || $user->hasAnyRole(TenantUserCatalog::schoolManagementRoles()) || $user->hasRole('sahodaya_admin')) {
            return $next($request);
        }

        $tenantId = $request->route('tenantId');
        $path = $request->path();
        $programSlug = $this->inferProgramSlug($path);
        [$eventId, $scopeTypeHint] = $this->resolveEventContext($request, $path, $programSlug);

        if ($request->filled('event')) {
            $eventId ??= (int) $request->query('event');
            $scopeTypeHint ??= 'fest_event';
        }

        $scopes = SchoolUserEventScope::where('user_id', $user->id)
            ->when($tenantId, fn ($q) => $q->where('school_id', $tenantId))
            ->get();

        // A zero-scope coordinator's own post-login landing URL IS this page (see
        // AuthController::homeFor() -> SchoolUserScopeService::homeUrlFor()) — aborting
        // here too meant they never saw the friendly "No Assigned Events" screen
        // DashboardController::unassigned() renders, only a raw 403. See Documents/Path_breaks.md.
        if ($scopes->isEmpty() && str_ends_with($path, '/unassigned')) {
            return $next($request);
        }

        if ($scopes->isEmpty()) {
            abort(403, 'No event scope assigned to this coordinator account.');
        }

        if ($programSlug === null) {
            abort_unless(
                $this->pathAllowedWithoutProgram($path, $scopes),
                403,
                'Event coordinators can only access assigned programs and events.',
            );

            return $next($request);
        }

        $matcher = app(SchoolUserScopeService::class);
        $allowed = $scopes->contains(
            fn (SchoolUserEventScope $scope) => $matcher->scopeMatchesRoute($scope, $programSlug, $eventId, $scopeTypeHint)
        );

        abort_unless($allowed, 403, 'You are not assigned to this program or event.');

        return $next($request);
    }

    /** @param  \Illuminate\Support\Collection<int, SchoolUserEventScope>  $scopes */
    private function pathAllowedWithoutProgram(string $path, $scopes): bool
    {
        if (preg_match('#/fest/hub(?:/|$)#', $path) || preg_match('#/fest/reports(?:/|$)#', $path)) {
            return $scopes->contains(fn (SchoolUserEventScope $s) => ! in_array($s->program_slug, ['mcq', 'training'], true));
        }

        return false;
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
        if (is_numeric($event)) {
            return [(int) $event, 'fest_event'];
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

        foreach (['kalotsav', 'kids-fest', 'teacher-fest', 'english-fest', 'science-fest', 'custom'] as $slug) {
            if (str_contains($path, "/{$slug}")) {
                return $slug;
            }
        }

        if (str_contains($path, '/sports/') || str_contains($path, '/sports-meet')) {
            return 'sports-meet';
        }

        return null;
    }
}
