<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SahodayaAdmin\Concerns\ScopesMembershipByRegion;
use App\Models\FestEvent;
use App\Models\Registration;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Audit\FestEventActivityService;
use App\Services\Membership\SahodayaSetupService;
use App\Support\ProgramRouteMap;
use App\Models\SahodayaProfile;
use App\Support\SahodayaNavVisibility;
use App\Support\TenancyDatabase;
use App\Support\TenantBranding;
use App\Support\TenantDomainSync;
use App\Support\TenantPublicSite;
use Illuminate\Http\Request;

abstract class SahodayaAdminController extends Controller
{
    use ScopesMembershipByRegion;

    protected Tenant $sahodaya;
    protected bool $isStaff = false;

    public function __construct(Request $request)
    {
        $tenantId = $request->route('tenantId');
        $this->sahodaya = Tenant::where('id', $tenantId)->where('type', 'sahodaya')->firstOrFail();
        $this->isStaff = (bool) $request->attributes->get('isSahodayaStaff', false);

        if ($this->isStaff && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $permission = \App\Support\TenantUserCatalog::writePermissionForPath($request->path());
            if ($permission === null || ! $request->user()?->can($permission)) {
                abort(403, 'View-only access. Contact your Sahodaya administrator.');
            }
        }

        // The check above only ever gated writes — a staff member with just fest.marks
        // could still browse Registrations, Finance, Settings, etc. by typing the URL
        // directly, since the nav hiding those links (staffCanSeeNavItem() in
        // sahodayaEventNavPermissions.js) is a UI convenience, not access control. Only
        // scoped to /events/{id} paths for now — see viewPermissionsForPath()'s docblock.
        if ($this->isStaff && in_array($request->method(), ['GET', 'HEAD'], true)) {
            $viewPermissions = \App\Support\TenantUserCatalog::viewPermissionsForPath($request->path());
            if ($viewPermissions !== null && ! $request->user()?->hasAnyPermission($viewPermissions)) {
                abort(403, 'You do not have access to this area. Contact your Sahodaya administrator.');
            }
        }
    }

    protected function assertStaffCan(string $permission): void
    {
        if ($this->isStaff && ! request()->user()?->can($permission)) {
            abort(403, 'You do not have permission for this action.');
        }
    }

    /**
     * Narrow a school-id list down to the current user's region(s), for controllers that
     * aren't reached through an /events/{event} route (so EnsureSahodayaAdmin's per-event
     * region_admin/phase_admin gate never runs) but still show school-level financial/
     * operational data. A no-op (returns $schoolIds unchanged) for sahodaya_admin and any
     * non-region-scoped, non-phase-scoped staff — 'regionAdminScopes'/'phaseAdminScopes'
     * are only ever set on the request when the current user actually holds the
     * region_admin/phase_admin role without a broader admin role.
     *
     * See docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md §2.4, Phase 3.
     *
     * REG-06 fix (functional audit, 2026-08-11/12): this previously always
     * resolved "today's" active academic year, regardless of which year the
     * data being scoped actually belongs to — for a caller looking at a past
     * record (a specific event's report, a prior year's payment), that
     * silently applied the CURRENT region roster instead of the roster as it
     * stood when the record was created, so a region_admin's access to old
     * data could be wrongly narrowed or widened whenever someone edits a
     * current-year region assignment. Callers that have a specific record to
     * scope by (an event, a payment, a food order) should now pass that
     * record's own academic year via $year; the default (current year) is
     * preserved for callers that are genuinely about "right now" operational
     * queues rather than a specific historical record.
     *
     * Phase admin fix (standalone phase_admin role): a phase_admin has no region_id at
     * all (their scope spans every region under their phase), so without this, a
     * phase_admin reaching one of these non-{event}-route pages would hit the `empty()`
     * check below and fall through to "unrestricted" — the opposite of what fest.finance/
     * fest.catering are supposed to grant them. Resolved to a region-id list via each
     * assigned phase's own FestPhaseRegion rows, then merged into the same
     * SchoolRegionAssignment lookup region_admin already uses — a phase_admin's schools
     * are just the union of every region actually enabled for their phase(s).
     *
     * @param  list<string>  $schoolIds
     * @return list<string>
     */
    protected function regionScopedSchoolIds(array $schoolIds, ?string $year = null): array
    {
        $scopes = request()->attributes->get('regionAdminScopes');
        $phaseScopes = request()->attributes->get('phaseAdminScopes');

        if (empty($scopes) && empty($phaseScopes)) {
            return $schoolIds;
        }

        $regionIds = collect($scopes)->pluck('region_id')->filter()->unique()->values()->all();

        if (! empty($phaseScopes)) {
            $phaseIds = collect($phaseScopes)->pluck('source_phase_id')->filter()->unique()->values()->all();
            $phaseRegionIds = \App\Models\FestPhaseRegion::whereIn('phase_id', $phaseIds)
                ->where('enabled', true)
                ->pluck('region_id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $regionIds = array_values(array_unique(array_merge($regionIds, $phaseRegionIds)));
        }

        if ($regionIds === []) {
            // Assigned a region/phase-scoped duty but nothing resolves to a real region —
            // fail closed, not open.
            return [];
        }

        $year ??= \App\Support\AcademicYear::forSahodaya($this->sahodaya->id);

        $regionSchoolIds = \App\Models\SchoolRegionAssignment::forTenant($this->sahodaya->id)
            ->forYear($year)
            ->whereIn('region_id', $regionIds)
            ->pluck('school_id')
            ->all();

        return array_values(array_intersect($schoolIds, $regionSchoolIds));
    }

    /**
     * The sidebar (sahodayaAdminNav.js) shows every program hub (Kalotsav, Sports Meet,
     * English Fest, ...) plus "All events"/"Competition types" regardless of which specific
     * event an event/region/phase-scoped admin actually holds — those links either 403 (a
     * program hub redirects into a hub event they're not scoped for) or render empty ("All
     * events" is already filtered server-side to eventAdminEventIds, which is empty for a
     * pure region/phase admin — see FestEventController::index()/programIndex()). This
     * resolves the set of event_types such an admin can actually reach, so the sidebar can
     * hide the rest instead of advertising dead ends.
     *
     * Returns null for a full admin (not event/region/phase-scoped at all — unchanged, full
     * nav), or a (possibly empty) list of event_type strings when scoped.
     *
     * @return list<string>|null
     */
    /**
     * The set of FestEvent ids an event/region/phase-scoped admin can actually reach —
     * their directly-assigned event(s), plus (for a scope held on a hub) every child of that
     * hub their scope covers, mirroring EventRegionAdminScope::matchesRegionScope()/
     * matchesPhaseScope()'s own "hub reaches matching children" rule. Used to narrow
     * Sahodaya-wide dashboard counts (active fests, registrations, appeals, fee proofs) down
     * to just the admin's own event(s) instead of the whole Sahodaya's.
     *
     * Returns null for a full admin (not event/region/phase-scoped — unchanged, unfiltered).
     *
     * @return list<int>|null
     */
    protected function scopedFestEventIds(): ?array
    {
        $request = request();

        if (! $request->attributes->has('eventAdminEventIds')) {
            return null;
        }

        $regionScopes = $request->attributes->get('regionAdminScopes', []);
        $phaseScopes = $request->attributes->get('phaseAdminScopes', []);

        $eventIds = collect($request->attributes->get('eventAdminEventIds', []))
            ->merge(collect($regionScopes)->pluck('event_id'))
            ->merge(collect($phaseScopes)->pluck('event_id'))
            ->filter()
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return [];
        }

        $childIds = FestEvent::whereIn('parent_event_id', $eventIds->all())
            ->get(['id', 'parent_event_id', 'region_id', 'source_phase_id'])
            ->filter(fn (FestEvent $child) => \App\Support\EventRegionAdminScope::matchesRegionScope($child->id, $regionScopes)
                || \App\Support\EventRegionAdminScope::matchesPhaseScope($child->id, $phaseScopes))
            ->pluck('id');

        return $eventIds->merge($childIds)->unique()->values()->all();
    }

    /**
     * The "Select Sport Event / Region" / "Select Phase / Region" switcher options for
     * $event, narrowed to what the acting admin can actually open. Picking an option is a
     * hard navigation to that event's own id (see e.g. Attendance.vue's
     * switchSportEvent()) — for an event/region/phase-scoped admin, an out-of-scope
     * option isn't a dead end, it's a 403 (EnsureSahodayaAdmin denies the resulting
     * request), which looks exactly like "the region/phase switcher doesn't work."
     * Was previously reimplemented ad hoc per-controller (first fixed in
     * FestRegistrationReviewController, then FestChestNumberController,
     * FestAttendanceController) — centralized here so every caller of
     * FestEvent::sportEventDropdownOptions() for this exact purpose gets it for free.
     *
     * @return list<array<string, mixed>>
     */
    protected function scopedChildEventOptions(FestEvent $event): array
    {
        $options = $event->sportEventDropdownOptions();

        if (($scopedEventIds = $this->scopedFestEventIds()) === null) {
            return $options;
        }

        return array_values(array_filter(
            $options,
            fn (array $option) => in_array($option['id'], $scopedEventIds, true),
        ));
    }

    /**
     * Blocks a scoped admin (event_admin/region_admin/phase_admin) from a whole program
     * (Sports Meet, Kids Fest, ...) when they hold zero assignments anywhere in it. A full
     * admin (scopedFestEventIds() === null) is always allowed through unchanged.
     *
     * Program-overview and cross-event pages (dashboards, championship/rankings/results
     * roll-ups) never carry a single {event} route parameter for EnsureSahodayaAdmin's own
     * containment check to act on, so without this, a scoped admin can browse any program
     * they have no assignment in at all — see Documents/Path_breaks.md.
     */
    protected function assertProgramAccess(string $eventType): void
    {
        $scopedEventIds = $this->scopedFestEventIds();
        if ($scopedEventIds === null) {
            return;
        }

        $hasAssignmentInProgram = FestEvent::forTenant($this->sahodaya->id)
            ->ofType($eventType)
            ->whereIn('id', $scopedEventIds)
            ->exists();

        abort_unless($hasAssignmentInProgram, 403, 'You are not assigned to any event in this program.');
    }

    protected function sidebarEventScope(): ?array
    {
        $request = request();

        if (! $request->attributes->has('eventAdminEventIds')) {
            return null;
        }

        $eventIds = collect($request->attributes->get('eventAdminEventIds', []))
            ->merge(collect($request->attributes->get('regionAdminScopes', []))->pluck('event_id'))
            ->merge(collect($request->attributes->get('phaseAdminScopes', []))->pluck('event_id'))
            ->filter()
            ->unique()
            ->values();

        if ($eventIds->isEmpty()) {
            return [];
        }

        return FestEvent::whereIn('id', $eventIds)->pluck('event_type')->unique()->values()->all();
    }

    protected function inertia(string $component, array $props = [])
    {
        $props = $this->withFestNavContext($props);

        $staffPermissions = null;
        if ($this->isStaff && ($user = request()->user())) {
            $staffPermissions = $user->getAllPermissions()->pluck('name')->values()->all();
        }

        return inertia($component, array_merge([
            'scopedEventTypes' => $this->sidebarEventScope(),
            'isStaff' => $this->isStaff,
            'staffPermissions' => $staffPermissions,
            'navVisibility' => SahodayaNavVisibility::forProfile(
                SahodayaProfile::where('tenant_id', $this->sahodaya->id)->first(),
                $this->sahodaya->nav_overrides,
            ),
            'sahodaya'               => array_merge(
                $this->sahodaya->only('id', 'name', 'type'),
                ['logo_url' => TenantBranding::logoUrl($this->sahodaya)]
            ),
            'publicUrl'              => TenantDomainSync::publicUrl($this->sahodaya),
            'approvedSchoolsCount'   => Tenant::where('parent_id', $this->sahodaya->id)
                                            ->where('type', 'school')
                                            ->where('membership_status', 'approved')
                                            ->count(),
            'pendingSchoolsCount'    => Tenant::where('parent_id', $this->sahodaya->id)
                                            ->where('type', 'school')
                                            ->where('membership_status', 'pending')
                                            ->count(),
            'pendingSubmissionsCount'=> Registration::query()
                ->whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                ->where('academic_year', \App\Support\AcademicYear::forSahodaya($this->sahodaya->id))
                ->whereIn('registration_status', ['data_pending', 'data_rejected'])
                ->count(),
            'pendingPaymentsCount'   => \App\Models\MembershipPayment::whereIn('school_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                                            ->where('status', 'submitted')->count(),
            'pendingChangeRequests'  => \App\Models\StudentEditChangeRequest::query()
                ->forSahodaya($this->sahodaya->id)
                ->where('status', 'pending')
                ->whereIn('school_approval_status', ['school_approved', 'bypassed'])
                ->count(),
            'unverifiedStudentsCount' => Student::query()
                ->whereIn('tenant_id', TenancyDatabase::schoolIdsFor($this->sahodaya->id))
                ->where('status', 'active')
                ->whereNull('verified_at')
                ->count(),
            'pendingFestAppealsCount' => \App\Models\FestAppeal::query()
                ->whereIn('event_id', \App\Models\FestEvent::where('tenant_id', $this->sahodaya->id)->pluck('id'))
                ->where('status', 'pending')
                ->count(),
            'activeAcademicYear'     => \App\Support\AcademicYear::forSahodaya($this->sahodaya->id),
            'stateRemittancesEnabled' => \App\Models\FestStateProgramPropagation::where('sahodaya_id', $this->sahodaya->id)->exists(),
            'setupIncompleteCount'    => $this->isStaff ? 0 : collect(app(SahodayaSetupService::class)->checklist($this->sahodaya))
                ->where('done', false)->count(),
            'competitionPrograms'     => app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                ->forTenant($this->sahodaya->id)
                ->programsForNav(),
            'publicWebsiteEnabled'    => TenantPublicSite::isEnabled($this->sahodaya),
        ], $props));
    }

    /** Program hub / catalog paths should keep program sidebar — ignore ?event_id= there. */
    protected function isProgramWorkspaceRequest(): bool
    {
        $path = parse_url(request()->getRequestUri(), PHP_URL_PATH) ?? '';

        return (bool) preg_match(
            '#/sahodaya-admin/[^/]+/(?:kalotsav|sports|kids-fest|teacher-fest|english-fest|science-fest)(?:/(?:catalog|age-groups|records|championship|results|rankings|school-rounds)(?:/|$)|(?:/|$)|$)#',
            $path,
        ) || str_contains($path, '/taxonomy-masters')
            || str_contains($path, '/competition-types')
            || (bool) preg_match('#/sahodaya-admin/[^/]+/programs/[^/]+#', $path);
    }

    /** @return array{program: array<string, mixed>, programEvents: list<\App\Models\FestEvent>} */
    protected function programNavProps(string $slug): array
    {
        $meta = app(\App\Services\Events\FestCompetitionTypeRegistry::class)
            ->forTenant($this->sahodaya->id)
            ->programMeta($slug);
        abort_unless($meta !== null, 404);

        $eventType = $meta['eventType'];

        return [
            'program' => [
                'slug'      => $meta['slug'],
                'eventType' => $eventType,
                'label'     => $meta['label'],
                'icon'      => $meta['icon'],
                'prefix'    => $meta['prefix'],
            ],
            'programEvents' => FestEvent::forTenant($this->sahodaya->id)
                ->ofType($eventType)
                ->visibleInNav()
                ->orderByDesc('event_start')
                ->get(['id', 'title', 'status'])
                ->all(),
        ];
    }

    /** @param  array<string, mixed>  $props */
    protected function withFestNavContext(array $props): array
    {
        $event = $props['event'] ?? null;
        $hasEvent = is_array($event) || $event instanceof FestEvent;
        $eventId = $hasEvent ? ($event['id'] ?? null) : null;

        if ((! $hasEvent || empty($eventId)) && request()->filled('event_id') && ! $this->isProgramWorkspaceRequest()) {
            $festEvent = FestEvent::forTenant($this->sahodaya->id)
                ->whereKey(request('event_id'))
                ->first(['id', 'title', 'event_type', 'status', 'level_round']);
            if ($festEvent) {
                $props['event'] = $festEvent->only(['id', 'title', 'event_type', 'status', 'level_round']);
                $event = $props['event'];
                $hasEvent = true;
                $eventId = $event['id'];
            }
        }

        if (! $hasEvent || empty($eventId)) {
            return $props;
        }

        $eventType = $event['event_type'] ?? null;

        if (! isset($props['programEvents'])) {
            $eventType ??= FestEvent::query()->whereKey($eventId)->value('event_type');
            if ($eventType) {
                $props['programEvents'] = FestEvent::forTenant($this->sahodaya->id)
                    ->ofType($eventType)
                    ->visibleInNav()
                    ->orderByDesc('event_start')
                    ->get(['id', 'title', 'status', 'event_start'])
                    ->all();
            }
        }

        if (! isset($props['program']) && ! empty($eventType)) {
            $slug = app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                ->forTenant($this->sahodaya->id)
                ->slugForEventType($eventType);
            $meta = $slug
                ? app(\App\Services\Events\FestCompetitionTypeRegistry::class)
                    ->forTenant($this->sahodaya->id)
                    ->programMeta($slug)
                : null;
            if ($meta) {
                $props['program'] = [
                    'slug' => $meta['slug'],
                    'label' => $meta['label'],
                    'icon' => $meta['icon'],
                    'eventType' => $eventType,
                    'prefix' => $meta['prefix'],
                ];
            }
        }

        if (! isset($props['eventHeadNav'])) {
            $festEvent = $event instanceof FestEvent
                ? $event
                : FestEvent::query()->whereKey($eventId)->where('tenant_id', $this->sahodaya->id)->first();
            if ($festEvent) {
                $nav = app(\App\Services\Events\FestHeadItemNavigationService::class);
                $props['eventHeadNav'] = $nav->slimNavigation($nav->navigationForEvent($festEvent));
            }
        }

        return $props;
    }

    /** @param  array<string, mixed>  $props */
    protected function withEventActivity(FestEvent $event, string $page, array $props = [], int $limit = 20): array
    {
        return array_merge($props, [
            'activityLogs' => $this->pageActivityLogs($event, $page, $limit),
        ]);
    }

    /** @return list<array<string, mixed>> */
    protected function pageActivityLogs(FestEvent $event, string $page, int $limit = 20): array
    {
        return app(FestEventActivityService::class)
            ->forPage($event, $page, $limit)
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function catalogActivityLogs(string $page, int $limit = 20): array
    {
        return app(FestEventActivityService::class)
            ->forCatalog($this->sahodaya->id, $page, $limit);
    }

    /** @return list<array<string, mixed>> */
    protected function programActivityLogs(string $program, int $limit = 20): array
    {
        return app(FestEventActivityService::class)
            ->forProgram($this->sahodaya->id, $program, $limit);
    }
}
