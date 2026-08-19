<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use Illuminate\Validation\Rule;
use App\Models\FestEvent;
use App\Models\FestVenue;
use App\Models\FestCompetitionArea;
use App\Models\FestSchoolEventFee;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Services\Audit\PlatformAuditLogger;
use App\Support\Fest\FestEventItemPayload;
use App\Support\Fest\FestEventPayload;
use App\Support\FestConductLevels;
use App\Support\FestCatalogSections;
use App\Support\FestPageActivity;
use App\Support\FestSportsAgeGroup;
use App\Support\FestTeamSquadRules;
use App\Support\FestClassGroupScheme;
use App\Support\ProgramRouteMap;
use App\Services\Events\FestEventNotifier;
use App\Services\Events\FestCatalogService;
use App\Services\Events\FestItemCatalogService;
use App\Services\Events\FestQualificationService;
use App\Services\Events\FestTaxonomyRegistry;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FestEventController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $type = $request->query('type');
        $slugMap = ['kalolsavam' => 'kalotsav', 'sports' => 'sports', 'kids_fest' => 'kids-fest', 'teacher_fest' => 'teacher-fest', 'english_fest' => 'english-fest', 'science_fest' => 'science-fest', 'custom' => 'custom'];
        if ($type && isset($slugMap[$type])) {
            $prefix = $slugMap[$type] === 'custom' ? 'programs/custom' : $slugMap[$type];

            return redirect("/sahodaya-admin/{$this->sahodaya->id}/{$prefix}");
        }

        $q = FestEvent::forTenant($this->sahodaya->id)
            ->with(['parentEvent:id,title', 'parent:id,title'])
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start');

        if ($request->attributes->has('eventAdminEventIds')) {
            $q->whereIn('id', $request->attributes->get('eventAdminEventIds'));
        }

        $events = $q->get();
        $activeStatuses = ['published', 'registration_open', 'ongoing'];

        return $this->inertia('Sahodaya/Events/Index', [
            'events' => $events,
            'eventTypes' => $this->eventTypes(),
            'levelLabels' => FestEvent::levelLabels(),
            'stats' => [
                'events'        => $events->count(),
                'active_events' => $events->whereIn('status', $activeStatuses)->count(),
                'registrations' => (int) $events->sum('registrations_count'),
                'items'         => (int) $events->sum('items_count'),
            ],
            'schoolOptions' => \App\Models\Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function programIndex(Request $request, string $tenantId, string $program)
    {
        $registry = app(\App\Services\Events\FestCompetitionTypeRegistry::class)
            ->forTenant($this->sahodaya->id);
        $programs = $registry->programsForNav();
        abort_unless(isset($programs[$program]), 404);

        $programMeta = $programs[$program];
        $eventType = $programMeta['eventType'];
        $catalogService = app(FestCatalogService::class);
        $catalogService->ensureSeeded($this->sahodaya->id, $eventType);

        // Sports: season hub lists discipline events (heads-as-events). Other singletons
        // still open the one yearly hub event directly.
        if (FestEvent::isSingletonType($eventType, $this->sahodaya->id) && $eventType !== 'sports' && ! $this->isStaff) {
            $event = app(\App\Services\Events\FestPrimaryEventResolver::class)
                ->resolveOrCreate($this->sahodaya, $eventType, $programMeta['label']);

            // First-ever visit auto-creates this singleton's hub event with no form step
            // (there's no "create" screen for Kalotsav/English Fest/etc. — the program IS
            // the event). Land the admin on Settings > Participation so the on-stage/
            // off-stage/team/total per-student limits get set right away instead of
            // silently defaulting to "no limit."
            if ($event->wasRecentlyCreated) {
                return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/settings/participation")
                    ->with('success', "{$programMeta['label']} created — set participation limits (on-stage / off-stage / team items per student) below to get started.");
            }

            return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}");
        }


        if (FestEvent::isSingletonType($eventType, $this->sahodaya->id)) {
            // View-only staff: open the existing hub event if one exists, else fall through
            // to the (read-only) program hub so nothing is created on a GET.
            $event = app(\App\Services\Events\FestPrimaryEventResolver::class)
                ->resolve($this->sahodaya->id, $eventType);
            if ($event && $eventType !== 'sports') {
                // An event/region/phase-scoped admin (region_admin etc.) reaches this branch
                // (isStaff=true skips the branch above) — the hub redirect below is
                // unconditional, so a region-only admin landed on a hub they can't open and
                // got bounced back to the Dashboard with "You are not assigned to this event."
                // (bootstrap/app.php's Inertia 403->flash handler). Redirect to their own
                // scoped child event instead when the hub itself isn't reachable.
                if ($request->attributes->has('eventAdminEventIds')) {
                    $landing = \App\Support\EventRegionAdminScope::resolveScopedLandingEvent(
                        $event,
                        $request->attributes->get('eventAdminEventIds', []),
                        $request->attributes->get('regionAdminScopes', []),
                        $request->attributes->get('phaseAdminScopes', []),
                    );
                    if ($landing) {
                        $event = $landing;
                    }
                }

                return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}");
            }
        }

        $eventsQuery = FestEvent::forTenant($this->sahodaya->id)
            ->ofType($eventType)
            ->whereNull('parent_event_id')
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start');

        if ($request->attributes->has('eventAdminEventIds')) {
            $eventsQuery->whereIn('id', $request->attributes->get('eventAdminEventIds'));
        }

        $events = $eventsQuery->get();

        $events->each(function (FestEvent $ev) {
            $ev->has_sports_fees_configured = $ev->hasSportsFeesConfigured();
        });

        $dashboard = app(\App\Services\Events\ProgramHubDataService::class)
            ->sahodayaProgramDashboard($this->sahodaya, $program, $eventType);

        return $this->inertia('Sahodaya/Events/ProgramIndex', [
            'program' => $programMeta,
            'events' => $events,
            'levelLabels' => FestEvent::levelLabels(),
            'stats' => $dashboard['stats'],
            'schoolParticipation' => $dashboard['schoolParticipation'],
            'eventsByLevel' => $dashboard['eventsByLevel'],
            'catalogSummary' => $catalogService->summary($this->sahodaya->id, $eventType),
            'catalogSections' => FestCatalogSections::summaries($this->sahodaya->id, $eventType),
            'activityLogs' => $this->programActivityLogs($program),
            'schoolOptions' => \App\Models\Tenant::where('parent_id', $this->sahodaya->id)
                ->where('type', 'school')
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'event_type'         => ['required', app(\App\Services\Events\FestCompetitionTypeRegistry::class)->forTenant($this->sahodaya->id)->validationRule()],
            'level_round'        => 'nullable|in:state,sahodaya,school',
            'conduct_levels'     => 'nullable|array',
            'conduct_levels.*'   => 'in:state,sahodaya,school',
            'academic_year_id'   => 'nullable|exists:academic_years,id',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'event_start'        => 'nullable|date',
            'event_end'          => 'nullable|date',
            'venue'              => 'nullable|string|max:255',
            'fee_type'           => 'nullable|in:none,flat_school,per_participant,per_item',
            'fee_amount'         => 'nullable|numeric|min:0',
            'description'        => 'nullable|string',
            'max_total_per_student'    => 'nullable|integer|min:0',
            'max_onstage_per_student'  => 'nullable|integer|min:0',
            'max_offstage_per_student' => 'nullable|integer|min:0',
            'max_group_per_student'    => 'nullable|integer|min:0',
            'food_payee_type'          => ['nullable', Rule::in(['sahodaya', 'host_school'])],
            'food_host_school_id'      => [
                Rule::requiredIf(($request->input('food_payee_type') ?? 'sahodaya') === 'host_school'),
                'nullable',
                Rule::exists('tenants', 'id')->where('parent_id', $this->sahodaya->id)->where('type', 'school'),
            ],
        ]);

        $data['food_payee_type'] = $data['food_payee_type'] ?? 'sahodaya';
        $data['food_host_school_id'] = $data['food_payee_type'] === 'host_school' ? ($data['food_host_school_id'] ?? null) : null;

        // Participation limits (on-stage/off-stage/team caps per student) aren't
        // fest_events columns — they live on FestParticipationPolicy. Pull them out
        // of $data before FestEvent::create() and apply after the event exists.
        $participationLimits = array_filter([
            'max_total_per_student'    => $data['max_total_per_student'] ?? null,
            'max_onstage_per_student'  => $data['max_onstage_per_student'] ?? null,
            'max_offstage_per_student' => $data['max_offstage_per_student'] ?? null,
            'max_group_per_student'    => $data['max_group_per_student'] ?? null,
        ], fn ($v) => $v !== null);
        unset($data['max_total_per_student'], $data['max_onstage_per_student'], $data['max_offstage_per_student'], $data['max_group_per_student']);


        $levelRound = $data['level_round'] ?? 'sahodaya';
        $eventType = $data['event_type'];

        // Enforce one primary hub event per Sahodaya per year for singleton fest types.
        if ($levelRound === 'sahodaya' && FestEvent::isSingletonType($eventType, $this->sahodaya->id)) {
            $existing = app(\App\Services\Events\FestPrimaryEventResolver::class)
                ->resolve($this->sahodaya->id, $eventType);
            if ($existing) {
                return redirect(
                    $eventType === 'sports'
                        ? "/sahodaya-admin/{$this->sahodaya->id}/events/{$existing->id}/setup"
                        : "/sahodaya-admin/{$this->sahodaya->id}/events/{$existing->id}"
                )->with('info', "There is already a {$this->eventTypes()[$eventType]} for this year. Only one is allowed per Sahodaya each academic year.");
            }
        }

        $conductLevels = FestConductLevels::normalize(
            $data['conduct_levels'] ?? [$levelRound],
            $eventType
        );
        if ($conductLevels === []) {
            $conductLevels = FestConductLevels::defaultsFor($eventType);
        }
        unset($data['level_round'], $data['conduct_levels']);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['level_round'] = $levelRound;
        $data['conduct_levels'] = $conductLevels;
        $data['status'] = 'draft';

        if (empty($data['academic_year_id'])) {
            $data['academic_year_id'] = AcademicYear::activeId();
        }

        $data = FestEventPayload::applyDefaults($data);

        $event = FestEvent::create($data);

        if ($participationLimits !== []) {
            \App\Models\FestParticipationPolicy::updateOrCreate(
                ['event_id' => $event->id, 'class_group' => null],
                array_merge($participationLimits, [
                    'tenant_id'    => $this->sahodaya->id,
                    'scope'        => 'event',
                    'level_round'  => $levelRound,
                    'is_active'    => true,
                ])
            );
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::OVERVIEW,
            'fest.event.created',
            "Event created: {$event->title}",
        );

        // If regions are already configured for this Sahodaya, auto-create regional
        // partition sub-events immediately — no manual "Sync regions" click needed.
        app(\App\Services\Events\FestRegionPartitionService::class)
            ->autoSyncIfApplicable($event);

        // Sports: a new top-level event is the season container — land the admin on
        // its Setup hub where "+ Add sport" lives (sports are added explicitly now).
        if ($event->event_type === 'sports' && $event->parent_event_id === null) {
            return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}/setup")
                ->with('success', "Season \"{$event->title}\" created — set age groups & cutoff, then add each sport with \"+ Add sport\".");
        }

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}")
            ->with('success', "Event \"{$event->title}\" created.");
    }

    public function show(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);



        $event->load(['academicYear', 'childEvents', 'parentEvent']);
        $ctx = $this->eventPageContext($event);

        $stats = [
            'items'          => $event->items()->count(),
            'registrations'  => $event->registrations()->count(),
            'school_rounds'  => $ctx['schoolRoundCount'],
        ];

        if ($event->event_type === 'sports') {
            $regs = $event->registrations()
                ->whereIn('status', FestRegistration::ACTIVE_STATUSES)
                ->with('participants')
                ->get();
            $stats['schools_count'] = $regs->pluck('school_id')->unique()->count();
            $stats['athletes_count'] = $regs->flatMap(fn($r) => $r->participants ?? [])->filter(fn($p) => $p->participant_role !== 'standby')->count();
        }

        return $this->inertia('Sahodaya/Events/Overview', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::OVERVIEW),
            'stats'        => $stats,
            'lifecycle'       => \App\Services\Events\FestLifecycleService::for($event)->checklist(),
            'suggestedStatus' => \App\Services\Events\FestLifecycleService::for($event)->suggestedStatus(),
            'mistakenSeasonIssue' => $this->mistakenSeasonIssue($event),
        ]);
    }

    /**
     * Detects a standalone sport event that's stuck hidden/mistagged from a past
     * season-hub mix-up, so the Overview page can offer a one-click "Fix
     * visibility" action instead of requiring shell access. Never flags a
     * genuine season hub with real (registered) children.
     */
    private function mistakenSeasonIssue(FestEvent $event): ?array
    {
        if ($event->event_type !== 'sports' || $event->parent_event_id !== null || $event->conduct_mode === 'partitioned') {
            return null;
        }

        $looksMistaken = $event->partition_role === 'sports_season' || $event->nav_hidden;
        if (! $looksMistaken) {
            return null;
        }

        $children = FestEvent::where('parent_event_id', $event->id)->withCount('registrations')->get();
        $busyChildren = $children->filter(fn (FestEvent $c) => $c->registrations_count > 0);

        if ($busyChildren->isNotEmpty()) {
            // Genuine season hub with real registrations under it — not a mistake.
            return null;
        }

        return [
            'children' => $children->count(),
            'emptyChildren' => $children->count() - $busyChildren->count(),
            'navHidden' => (bool) $event->nav_hidden,
            'partitionRole' => $event->partition_role,
        ];
    }

    /**
     * One-click fix for the Overview page: resets a standalone sport event
     * mistakenly tagged/hidden as a season hub. Same safe logic as the
     * fest:unmark-mistaken-season command — refuses to touch anything if a
     * child event has real registrations.
     */
    public function fixMistakenSeason(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $deleteEmpty = $request->boolean('delete_empty_children');

        $result = app(\App\Services\Events\FestSportsEventSyncService::class)
            ->repairMistakenSeason($event, $deleteEmpty);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function items(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Items live on each sport event (Chess, Aquatics, …).')) {
            return $redirect;
        }

        // registrations_count was previously never annotated here (unlike the sibling
        // itemsList()/itemsCaps() actions), so the "N registered" badge and the delete
        // guard below always read 0 regardless of the item's real registrations.
        $event->load(['items' => function ($q) {
            $q->withCount(['registrations' => fn ($r) => $r->whereIn('status', \App\Models\FestRegistration::ACTIVE_STATUSES)]);
        }]);
        $ctx = $this->eventPageContext($event);
        $trashedItems = FestEventItem::onlyTrashed()->where('event_id', $event->id)->orderBy('deleted_at', 'desc')->get(['id', 'title', 'item_code', 'owner_level', 'deleted_at']);

        return $this->inertia('Sahodaya/Events/Items/Master', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::ITEMS),
            'trashedItems' => $trashedItems,
        ]);
    }

    public function itemsList(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Item listing is per sport event — open Chess, Aquatics, etc. from Sports Meet.')) {
            return $redirect;
        }

        $event->load(['items' => function ($q) {
            $q->withCount(['registrations' => fn ($r) => $r->whereIn('status', \App\Models\FestRegistration::ACTIVE_STATUSES)]);
        }]);
        $ctx = $this->eventPageContext($event);
        $trashedItems = FestEventItem::onlyTrashed()->where('event_id', $event->id)->orderBy('deleted_at', 'desc')->get(['id', 'title', 'item_code', 'owner_level', 'deleted_at']);

        return $this->inertia('Sahodaya/Events/Items/List', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::ITEMS_LIST),
            'trashedItems' => $trashedItems,
        ]);
    }

    public function itemsCaps(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Item limits are per sport event — open Chess, Aquatics, etc.')) {
            return $redirect;
        }

        $event->load(['items' => function ($q) {
            $q->withCount(['registrations' => fn ($r) => $r->whereIn('status', \App\Models\FestRegistration::ACTIVE_STATUSES)]);
        }]);
        $ctx = $this->eventPageContext($event);

        return $this->inertia('Sahodaya/Events/Items/Caps', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::ITEMS),
        ]);
    }

    public function bulkUpdateItemCaps(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'items'                   => 'required|array',
            'items.*.id'             => 'required|integer|exists:fest_event_items,id',
            'items.*.max_per_school' => 'nullable|integer|min:1',
            'items.*.qualify_count'  => 'nullable|integer|min:1',
            'items.*.min_group_size' => 'nullable|integer|min:1',
            'items.*.max_group_size' => 'nullable|integer|min:1',
            'items.*.min_playing'    => 'nullable|integer|min:1',
            'items.*.max_subs'       => 'nullable|integer|min:0',
            'items.*.standbys'       => 'nullable|integer|min:0',
        ]);

        $updatedCount = 0;

        DB::transaction(function () use ($data, $event, &$updatedCount) {
            foreach ($data['items'] as $itemData) {
                $item = FestEventItem::where('event_id', $event->id)->find($itemData['id']);
                if (! $item) {
                    continue;
                }

                $updates = [];
                if (array_key_exists('max_per_school', $itemData)) {
                    $val = $itemData['max_per_school'];
                    $updates['max_per_school'] = ($val !== null && $val !== '') ? (int) $val : null;
                }
                if (array_key_exists('qualify_count', $itemData)) {
                    $val = $itemData['qualify_count'];
                    $updates['qualify_count'] = ($val !== null && $val !== '') ? (int) $val : null;
                }

                if (FestTeamSquadRules::isMultiPerson($item->participant_type)) {
                    $squadInput = [
                        'min_playing' => $itemData['min_playing'] ?? null,
                        'max_subs'    => $itemData['max_subs'] ?? ($itemData['standbys'] ?? null),
                        'max_squad'   => $itemData['max_group_size'] ?? null,
                        'min_squad'   => $itemData['min_group_size'] ?? null,
                        'standbys'    => $itemData['standbys'] ?? null,
                    ];
                    $hasSquadInput = collect($squadInput)->contains(fn ($v) => $v !== null && $v !== '');

                    if ($hasSquadInput) {
                        $merged = FestTeamSquadRules::mergeIntoItem($squadInput);
                        if (! empty($merged['criteria_json'])) {
                            $updates['criteria_json'] = array_merge($item->criteria_json ?? [], $merged['criteria_json']);
                        }
                        if (array_key_exists('min_group_size', $itemData) || $merged['min_group_size'] !== null) {
                            $updates['min_group_size'] = $merged['min_group_size'];
                        }
                        if (array_key_exists('max_group_size', $itemData) || $merged['max_group_size'] !== null) {
                            $updates['max_group_size'] = $merged['max_group_size'];
                        }
                    } elseif (array_key_exists('min_group_size', $itemData) || array_key_exists('max_group_size', $itemData)) {
                        $minVal = $itemData['min_group_size'] ?? null;
                        $maxVal = $itemData['max_group_size'] ?? null;
                        $updates['min_group_size'] = ($minVal !== null && $minVal !== '') ? (int) $minVal : null;
                        $updates['max_group_size'] = ($maxVal !== null && $maxVal !== '') ? (int) $maxVal : null;
                    }
                }

                if (! empty($updates)) {
                    $item->update($updates);
                    $updatedCount++;
                }
            }
        });

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.items.bulk_caps_updated', "Bulk updated limit caps for {$updatedCount} item(s)");

        $this->syncItemToExistingPartitions($event);

        return back()->with('success', "Updated limit caps for {$updatedCount} item(s).");
    }

    public function levels(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $event->load(['childEvents', 'parentEvent']);
        $ctx = $this->eventPageContext($event);
        $partitionService = app(\App\Services\Events\FestPartitionService::class);
        $schoolPartitionService = app(\App\Services\Events\FestSchoolPartitionService::class);

        $memberSchools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Old-system partitions only, excluding phased-system leaf children (which also
        // carry partition_key/cluster_key for unrelated reasons) -- see
        // FestPartitionService::legacyPartitions() docblock. isPartitionedHub/partitions/
        // regionDrillDown below drive this page's OWN region-partition management UI, so
        // they must reflect only what THIS page's actions actually own; a phased-only
        // event must not show up here as if it had legacy partitions to manage.
        $hasLegacyPartitions = $partitionService->hasLegacyPartitions($event);
        $phaseCount = \App\Models\FestEventPhase::where('event_id', $event->id)->count();
        $batchCount = \App\Models\FestRegistrationBatch::where('event_id', $event->id)->count();

        // Which conduct system (if either) this event has already committed to, driven by
        // the EXACT same predicate FestPartitionService::assertLegacyPartitioningAllowed()
        // blocks on ($phaseCount/$batchCount, not just workflow_mode) -- otherwise this page
        // could still offer "Choose Region Split" on an event the backend would already
        // reject that action for, since phases/batches can exist before the first batch
        // ever flips workflow_mode.
        $conductSystemLocked = match (true) {
            $event->usesPhasedRegionalBilling() || $phaseCount > 0 || $batchCount > 0 => 'phased',
            $hasLegacyPartitions => 'partitioned',
            default => null,
        };

        return $this->inertia('Sahodaya/Events/Levels', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::LEVELS),
            'conductMode' => $partitionService->conductMode($event),
            'isPartitionedHub' => $hasLegacyPartitions,
            'conductSystemLocked' => $conductSystemLocked,
            'phaseCount' => $phaseCount,
            'batchCount' => $batchCount,
            'partitions' => $partitionService->legacyPartitions($event)->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'partition_key' => $p->partition_key ?? $p->cluster_key,
                'partition_role' => $p->partition_role ?? 'cluster',
                'cluster_label' => $p->cluster_label,
            ])->values(),
            'conductPresets' => array_keys(config('fest_conduct_presets', [])),
            'memberSchools' => $memberSchools,
            'schoolPartitions' => $schoolPartitionService->assignmentsForHub($event),
            // Hub drill-down panel — Phase 4 / §2.5 of
            // docs/REGION_SCOPED_ADMIN_AND_EVENT_FLOW_PLAN.md. Only meaningful (and
            // only computed) when this event is actually a partitioned hub with
            // region children; empty array otherwise so the panel stays hidden.
            'regionDrillDown' => $hasLegacyPartitions ? $partitionService->regionDrillDownSummary($event) : [],
        ]);
    }

    public function update(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $rules = [
            'title'              => 'required|string|max:255',
            'event_type'         => ['sometimes', 'required', app(\App\Services\Events\FestCompetitionTypeRegistry::class)->forTenant($this->sahodaya->id)->validationRule()],
            'academic_year_id'   => 'nullable|exists:academic_years,id',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'event_start'        => 'nullable|date',
            'event_end'          => 'nullable|date',
            'venue'              => 'nullable|string|max:255',
            'fee_type'           => 'nullable|in:none,flat_school,per_participant,per_item',
            'fee_amount'         => 'nullable|numeric|min:0',
            'status'             => 'required|in:draft,published,registration_open,ongoing,completed,cancelled',
            'results_published'  => 'boolean',
            'description'        => 'nullable|string',
        ];

        if (! $event->isStateProgram()) {
            $rules['conduct_levels'] = 'nullable|array';
            $rules['conduct_levels.*'] = 'in:state,sahodaya,school';
        }

        $data = $request->validate($rules);

        if ($event->isStateProgram()) {
            unset($data['event_type']);
        }

        if (isset($data['conduct_levels'])) {
            $data['conduct_levels'] = FestConductLevels::normalize(
                $data['conduct_levels'],
                $event->event_type
            );
            if ($data['conduct_levels'] === []) {
                $data['conduct_levels'] = FestConductLevels::defaultsFor($event->event_type);
            }
        }

        $data = FestEventPayload::applyDefaults($data);

        $newStatus = $data['status'] ?? $event->status;

        // LIFE-01 fix (functional audit, 2026-08-11/12): this endpoint (the
        // main "Edit Event" settings-form save) is one of two code paths that
        // can change $event->status — quickStatus() below already enforces
        // StatusTransitionGuard's transition matrix, but this one didn't, so
        // a stale/replayed form submission could push the event through a
        // transition quickStatus() would have rejected (e.g. completed →
        // draft, or completed → ongoing). Enforcing the same guard here
        // closes that gap; it throws a ValidationException, which Laravel
        // converts into the usual redirect-back-with-errors response for a
        // web request — same as quickStatus() relies on below. It's a no-op
        // when $newStatus equals the event's current status (the common case
        // of saving the form without touching the status field).
        \App\Support\StatusTransitionGuard::assert(
            $event,
            $newStatus,
            \App\Support\StatusTransitionGuard::FEST_EVENT_TRANSITIONS,
        );

        if (in_array($newStatus, ['published', 'registration_open'], true)
            && ! in_array($event->status, ['published', 'registration_open', 'ongoing', 'completed'], true)) {
            try {
                \App\Services\Events\EventLifecycleGate::assertCanPublishEvent(
                    $event,
                    $data['venue'] ?? null,
                    $data['event_start'] ?? null,
                );
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                return back()->withErrors(['status' => $e->getMessage()]);
            }
        }

        $previousStatus = $event->status;

        if ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
            app(\App\Services\Events\FestEventStatusService::class)
                ->transitionToCancelled($event, $request->boolean('confirm_credit_all'));
            // Remove status from data so it's not updated again below.
            unset($data['status']);
        }

        $event->update($data);

        // STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN — Set 1, Item 3
        // Stamp sahodaya_customized_at when a Sahodaya Admin edits any state-seeded
        // field on a state-linked event. This drives the customization indicator badge
        // on the Sahodaya event page and the State Admin propagation view.
        if ($event->isStateProgram()) {
            $stateSeededFields = [
                'title', 'registration_open', 'registration_close',
                'event_start', 'event_end', 'venue',
                'fee_type', 'fee_amount', 'description',
            ];
            if (! empty(array_intersect_key($data, array_flip($stateSeededFields)))) {
                $event->updateQuietly(['sahodaya_customized_at' => now()]);
            }
        }

        // Cascade whichever lifecycle fields this save actually touched (registration
        // window, status, results_published) down onto every region child — see
        // FestRegionPartitionService::cascadeLifecycleToChildren(). No-op unless this
        // event is a partitioned hub.
        app(\App\Services\Events\FestRegionPartitionService::class)
            ->cascadeLifecycleToChildren($event, $data);

        if (in_array($newStatus, ['published', 'registration_open'], true)) {
            app(\App\Services\Events\FestRegionPartitionService::class)
                ->autoSyncIfApplicable($event->fresh());
        }

        // Season hub: keep child sport events in sync (open status + item placement).
        if ($event->event_type === 'sports' && $event->isSportsSeasonEvent()) {
            app(\App\Services\Events\FestSportsEventSyncService::class)->syncSeason($event->fresh());
        }

        if (($data['status'] ?? null) === 'registration_open' && $previousStatus !== 'registration_open') {
            try {
                app(FestEventNotifier::class)->registrationOpened($event->fresh());
            } catch (\Throwable) {
                // Notifications must never block event updates.
            }
        }

        if (($data['status'] ?? null) === 'completed' && $previousStatus !== 'completed') {
            try {
                app(FestEventNotifier::class)->eventCompleted($event->fresh());
            } catch (\Throwable) {
                // Notifications must never block event updates.
            }
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::OVERVIEW,
            'fest.event.updated',
            "Event updated: {$event->title}",
            ['status' => $event->status],
        );

        return back()->with('success', 'Event updated.');
    }

    public function destroy(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($event->isStateProgram(), 422, 'State programs cannot be deleted from Sahodaya admin.');

        // Guard rails: deleting an event with registrations would orphan school data.
        // A sports season hub whose whole tree is registration-free deletes as a unit
        // (children first — items/fees/level-registrations cascade per event).
        $childIds = FestEvent::where('parent_event_id', $event->id)->pluck('id');

        $registrationCount = \App\Models\FestRegistration::whereIn(
            'event_id',
            $childIds->concat([$event->id]),
        )->count();
        abort_if(
            $registrationCount > 0,
            422,
            "This event (or its child events) has {$registrationCount} registration(s). Hide it from schools instead of deleting, or clear registrations first.",
        );

        if ($childIds->isNotEmpty() && $event->event_type !== 'sports') {
            abort(422, 'This event has child events — delete or move them first, or hide this event instead.');
        }

        $title = $event->title;
        FestEvent::where('parent_event_id', $event->id)->get()->each->delete();
        $event->delete();

        $audit->log('fest.event.deleted', "Event deleted: {$title}", properties: [
            'tenant_id' => $this->sahodaya->id,
            'page'      => FestPageActivity::OVERVIEW,
        ]);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events")
            ->with('success', 'Event deleted.');
    }

    public function spawnCascade(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $child = app(\App\Services\Events\FestCascadeService::class)
            ->spawnChildEvent($event, $data['title']);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.child_spawned', "Child event created: {$child->title}", [
            'child_event_id' => $child->id,
        ]);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$child->id}")
            ->with('success', 'Child event created from parent.');
    }

    public function spawnCluster(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'cluster_key'   => 'nullable|string|max:64',
            'cluster_label' => 'nullable|string|max:255',
            'venue'         => 'nullable|string|max:255',
            'event_start'   => 'nullable|date',
            'event_end'     => 'nullable|date',
        ]);

        $child = app(\App\Services\Events\FestKidsFestClusterService::class)
            ->spawnCluster($event, $data);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.cluster_spawned', "Kids Fest cluster created: {$child->title}", [
            'child_event_id' => $child->id,
            'cluster_key'    => $child->cluster_key,
        ]);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$child->id}")
            ->with('success', 'Cluster event created.');
    }

    public function spawnPartition(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'          => 'required|string|max:255',
            'partition_key'  => 'nullable|string|max:64',
            'cluster_key'    => 'nullable|string|max:64',
            'cluster_label'  => 'nullable|string|max:255',
            'partition_role' => 'nullable|in:region,finale,cluster,digi_fest',
            'venue'          => 'nullable|string|max:255',
            'event_start'    => 'nullable|date',
            'event_end'      => 'nullable|date',
        ]);

        $child = app(\App\Services\Events\FestPartitionService::class)->spawnPartition($event, $data);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.partition_spawned', "Partition created: {$child->title}", [
            'child_event_id' => $child->id,
            'partition_key'  => $child->partition_key ?? $child->cluster_key,
        ]);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$child->id}")
            ->with('success', 'Partition event created.');
    }

    public function applyConductPreset(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'preset' => 'required|string|in:mcs_kalotsav,generic_region_sync,regional_cluster,standard',
        ]);

        if ($data['preset'] === 'generic_region_sync' || $data['preset'] === 'regional_cluster') {
            $event->update(['conduct_mode' => 'partitioned']);
            $result = app(\App\Services\Events\FestRegionPartitionService::class)->syncPartitionsFromRegions($event);
            return back()->with('success', "{$result['partitions_created']} region partition(s) created from membership regions.");
        }

        $created = app(\App\Services\Events\FestPartitionService::class)
            ->spawnFromPreset($event, $data['preset']);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.preset_applied', "Conduct preset applied: {$data['preset']}", [
            'preset' => $data['preset'],
            'count'  => count($created),
        ]);

        return back()->with('success', count($created).' partition(s) created from preset.');
    }

    public function updateConductTopology(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'conduct_mode'              => 'required|string|in:standard,partitioned',
            'combine_regions_at_finale' => 'nullable|boolean',
        ]);

        // Switching TO standard is always safe; only switching to partitioned needs the
        // guard, and it must run before the update below, not after -- a blocked switch
        // must not leave conduct_mode persisted with zero children to back it up.
        if ($data['conduct_mode'] === 'partitioned') {
            app(\App\Services\Events\FestPartitionService::class)->assertLegacyPartitioningAllowed($event);
        }

        $event->update([
            'conduct_mode'              => $data['conduct_mode'],
            'combine_regions_at_finale' => $data['combine_regions_at_finale'] ?? true,
        ]);

        if ($data['conduct_mode'] === 'partitioned') {
            app(\App\Services\Events\FestRegionPartitionService::class)
                ->syncPartitionsFromRegions($event);
        }

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.topology_updated', 'Conduct topology updated', $data);

        return back()->with('success', 'Conduct topology updated.');
    }

    public function assignSchoolPartitions(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'assignments'               => 'required|array',
            'assignments.*.school_id'   => 'required|string',
            'assignments.*.partition_key' => 'required|string|max:64',
        ]);

        $service = app(\App\Services\Events\FestSchoolPartitionService::class);
        $map = [];
        foreach ($data['assignments'] as $row) {
            $map[$row['school_id']] = $row['partition_key'];
        }

        $count = $service->bulkAssign($event, $map, $request->user()?->id);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.partitions_assigned', "Assigned {$count} school partition(s)");

        return back()->with('success', "{$count} school region assignment(s) saved.");
    }

    public function syncRegionPartitions(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $result = app(\App\Services\Events\FestRegionPartitionService::class)
            ->syncPartitionsFromRegions($event);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.regions_synced',
            'Kalotsav partitions synced from membership regions', $result);

        return back()->with('success',
            "{$result['partitions_created']} region partition(s) created, {$result['schools_assigned']} school assignment(s) synced.");
    }

    public function spawnSchoolRounds(string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $created = app(\App\Services\Events\FestStateProgramService::class)
            ->spawnSchoolRounds($event);

        $count = count($created);

        if ($count > 0) {
            $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.school_rounds_spawned', "Created {$count} school round(s)", [
                'count' => $count,
            ]);
        }

        return back()->with('success', $count > 0
            ? "{$count} school-level round(s) created."
            : 'All school rounds already exist.');
    }

    public function linkSchoolRound(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if(($event->level_round ?? 'sahodaya') === 'school', 422, 'Link school rounds to a Sahodaya parent event.');

        $data = $request->validate([
            'school_event_id' => 'required|exists:fest_events,id',
        ]);

        $schoolEvent = FestEvent::findOrFail($data['school_event_id']);
        abort_if($schoolEvent->level_round !== 'school', 422, 'Only a school-round event can be linked here.');
        abort_if($schoolEvent->tenant_id !== $this->sahodaya->id, 403);

        $schoolEvent->update(['parent_event_id' => $event->id]);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.school_linked', "Linked school event {$schoolEvent->title}", [
            'school_event_id' => $schoolEvent->id,
        ]);

        return back()->with('success', 'School event linked to this parent.');
    }

    public function promoteAllSchoolRounds(
        string $tenantId,
        FestEvent $event,
        FestQualificationService $qualService,
        FestEventNotifier $notifier,
        PlatformAuditLogger $audit,
    ) {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $result = $qualService->promoteAllSchoolRounds($event);

        if ($result['promoted'] > 0) {
            $notifier->promotionCompleted($event, $result['promoted']);
            $audit->festPromotionCompleted($event, $result['promoted'], [
                'page'               => FestPageActivity::LEVELS,
                'bulk_school_rounds' => true,
                'rounds_processed'   => $result['roundsProcessed'],
            ]);
        }

        return back()->with('success', "{$result['promoted']} promoted from {$result['roundsProcessed']} school round(s). "
            ."{$result['skipped']} skipped. {$result['roundsSkipped']} round(s) skipped (results not published).");
    }

    public function storeItem(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Add items on the Chess / Aquatics (sport) event, not on the season hub.')) {
            return $redirect;
        }

        $registry = app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id);
        $registry->ensureDefaults();

        $data = $request->validate(array_merge([
            'title'                => 'required|string|max:255',
            'item_code'            => 'nullable|string|max:20',
            'duration_minutes'     => 'nullable|integer|min:1|max:480',
            'total_marks'          => 'nullable|numeric|min:0',
            'max_per_school'       => 'nullable|integer|min:1',
            'min_group_size'       => 'nullable|integer|min:1',
            'max_group_size'       => 'nullable|integer|min:1',
            'min_playing'          => 'nullable|integer|min:1',
            'max_playing'          => 'nullable|integer|min:1',
            'max_subs'             => 'nullable|integer|min:0',
            'max_squad'            => 'nullable|integer|min:1',
            'min_squad'            => 'nullable|integer|min:1',
            'standbys'             => 'nullable|integer|min:0',
            'qualify_count'        => 'nullable|integer|min:1',
            'fee_amount'           => 'nullable|numeric|min:0',
            'head_id'              => 'nullable|exists:fest_item_heads,id',
            'area_id'              => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('fest_competition_areas', 'id')->where('event_id', $event->id),
            ],
            'tiebreak_mode'        => 'nullable|in:none,include_all_ties,exclude_ties,lot_draw,manual,secondary_score',
            'tiebreak_secondary'   => 'nullable|string|max:40',
            'quota_eligible'       => 'nullable|boolean',
        ], $this->taxonomyValidationRules($registry, $event)));

        $data['participant_type'] = $data['participant_type'] ?? 'individual';
        $data = FestEventItemPayload::applyDefaults($data);

        if (FestTeamSquadRules::isMultiPerson($data['participant_type'])) {
            $merged = FestTeamSquadRules::mergeIntoItem($request->only([
                'min_playing', 'max_playing', 'max_subs', 'max_squad', 'min_squad', 'standbys',
            ]));
            if ($merged['criteria_json']) {
                $data['criteria_json'] = $merged['criteria_json'];
            }
            if ($merged['min_group_size']) {
                $data['min_group_size'] = $merged['min_group_size'];
            }
            if ($merged['max_group_size']) {
                $data['max_group_size'] = $merged['max_group_size'];
            }
            $fixed = FestTeamSquadRules::defaultSizeFor($data['participant_type']);
            if ($fixed && empty($data['min_group_size']) && empty($data['max_group_size'])) {
                $data['min_group_size'] = $fixed;
                $data['max_group_size'] = $fixed;
            }
        }

        unset($data['min_playing'], $data['max_playing'], $data['max_subs'], $data['max_squad'], $data['min_squad'], $data['standbys']);

        $data['event_id'] = $event->id;
        $data['display_order'] = ($event->items()->max('display_order') ?? 0) + 1;
        $data['owner_level'] = 'sahodaya';

        $item = FestEventItem::create($data);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.item.created', "Item added: {$item->title}", [
            'item_id' => $item->id,
        ], $item);

        $this->syncItemToExistingPartitions($event);

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 403);
        // State-catalog items were previously read-only here, but every Sahodaya is the one
        // actually conducting these items locally (the state->Sahodaya->school cascade means
        // there's no separate "state runs it" mode) — so full edit access is required. Deletion
        // of a state-catalog item stays blocked below in destroyItem(), since removing it would
        // drop it from the state cascade entirely, which is a different, more destructive action.

        $registry = app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id);
        $registry->ensureDefaults();

        $data = $request->validate(array_merge([
            'title'          => 'required|string|max:255',
            'item_code'      => 'nullable|string|max:20',
            'qualify_count'  => 'nullable|integer|min:1',
            'max_per_school' => 'nullable|integer|min:1',
            'fee_amount'     => 'nullable|numeric|min:0',
            'total_marks'    => 'nullable|numeric|min:0',
            'is_enabled'     => 'nullable|boolean',
            'head_id'        => 'nullable|exists:fest_item_heads,id',
            'area_id'        => [
                'nullable', 'integer',
                \Illuminate\Validation\Rule::exists('fest_competition_areas', 'id')->where('event_id', $event->id),
            ],
            'tiebreak_mode'  => 'nullable|in:none,include_all_ties,exclude_ties,lot_draw,manual,secondary_score',
            'tiebreak_secondary' => 'nullable|string|max:40',
            'quota_eligible' => 'nullable|boolean',
            'min_group_size' => 'nullable|integer|min:1',
            'max_group_size' => 'nullable|integer|min:1',
            'min_playing'    => 'nullable|integer|min:1',
            'max_playing'    => 'nullable|integer|min:1',
            'max_subs'       => 'nullable|integer|min:0',
            'max_squad'      => 'nullable|integer|min:1',
            'min_squad'      => 'nullable|integer|min:1',
            'standbys'       => 'nullable|integer|min:0',
        ], $this->taxonomyValidationRules($registry, $event)));

        $participantType = $data['participant_type'] ?? $item->participant_type;

        if (FestTeamSquadRules::isMultiPerson($participantType)) {
            $squadInput = $request->only([
                'min_playing', 'max_playing', 'max_subs', 'max_squad', 'min_squad', 'standbys',
            ]);
            $hasSquadInput = collect($squadInput)->contains(fn ($v) => $v !== null && $v !== '');

            if ($hasSquadInput) {
                $merged = FestTeamSquadRules::mergeIntoItem($squadInput);
                if ($merged['criteria_json']) {
                    $data['criteria_json'] = $merged['criteria_json'];
                }
                if ($merged['min_group_size']) {
                    $data['min_group_size'] = $merged['min_group_size'];
                }
                if ($merged['max_group_size']) {
                    $data['max_group_size'] = $merged['max_group_size'];
                }
            } elseif ($request->has('min_group_size') || $request->has('max_group_size')) {
                $data['min_group_size'] = $request->input('min_group_size');
                $data['max_group_size'] = $request->input('max_group_size');

                // criteria_json['min_squad']/['max_squad'] take precedence over the
                // min_group_size/max_group_size columns in FestTeamSquadRules::fromItem(),
                // so an edit here has to keep both in sync or the squad-rules summary
                // (and "register N–M students" text) silently keeps the stale values.
                $criteria = $item->criteria_json ?? [];
                if ($data['min_group_size'] !== null) {
                    $criteria['min_squad'] = (int) $data['min_group_size'];
                } else {
                    unset($criteria['min_squad']);
                }
                if ($data['max_group_size'] !== null) {
                    $criteria['max_squad'] = (int) $data['max_group_size'];
                } else {
                    unset($criteria['max_squad']);
                }
                $data['criteria_json'] = $criteria;
            } else {
                $fixed = FestTeamSquadRules::defaultSizeFor($participantType);
                if ($fixed && empty($data['min_group_size']) && empty($item->min_group_size)) {
                    $data['min_group_size'] = $fixed;
                    $data['max_group_size'] = $fixed;
                }
            }
        }

        unset($data['min_playing'], $data['max_playing'], $data['max_subs'], $data['max_squad'], $data['min_squad'], $data['standbys']);

        $data = FestEventItemPayload::applyDefaults($data, $item);

        $item->update($data);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.item.updated', "Item updated: {$item->title}", [
            'item_id' => $item->id,
        ], $item);

        $this->syncItemToExistingPartitions($event);

        return back()->with('success', 'Item updated.');
    }

    /** Soft delete (FestEventItem uses SoftDeletes) — see restoreItem() for undoing this. */
    public function destroyItem(string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if($item->isStateCatalog(), 422, 'State catalog items cannot be removed here.');
        abort_if($item->registrations()->exists(), 422, 'This item has registrations — withdraw or move them first, or disable the item instead of deleting it.');
        $title = $item->title;
        $itemId = $item->id;
        $item->delete();

        // Deleting a hub item previously left its already-copied region/finale/cluster
        // children in place with no way to reach them from here anymore — see
        // FestItemSyncService::removeItemFromPartitions() (Phase 6 audit). Also soft
        // deletes now (same trait), so a child disabled-not-deleted for having
        // registrations is unaffected, and a child deleted here is restorable the same
        // way as the hub item itself.
        app(\App\Services\Events\FestItemSyncService::class)->removeItemFromPartitions($itemId);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.item.deleted', "Item removed: {$title}");

        return back()->with('success', 'Item removed.');
    }

    /** Undo destroyItem() — also restores any partition children that were deleted alongside it (mirrors removeItemFromPartitions()'s own cascade). */
    public function restoreItem(string $tenantId, FestEvent $event, int $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $trashedItem = FestEventItem::withTrashed()->where('id', $item)->firstOrFail();
        abort_if($trashedItem->event_id !== $event->id, 403);
        abort_unless($trashedItem->trashed(), 422, 'This item is not deleted.');

        $trashedItem->restore();
        FestEventItem::onlyTrashed()->where('inherited_from_item_id', $trashedItem->id)->restore();

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.item.restored', "Item restored: {$trashedItem->title}");

        return back()->with('success', 'Item restored.');
    }

    public function importCatalog(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Import catalog items into each sport event, not the season hub.')) {
            return $redirect;
        }

        $data = $request->validate([
            'class_groups'   => 'nullable|array',
            'class_groups.*' => 'in:lp,up,hs,hss,open',
        ]);

        $count = app(FestCatalogService::class)->importEnabledToEvent(
            $event,
            $data['class_groups'] ?? null,
        );

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.items.imported', "Imported {$count} catalog item(s)", [
            'count' => $count,
        ]);

        $this->syncItemToExistingPartitions($event);

        return back()->with('success', "{$count} standard item(s) imported.");
    }

    public function resyncItemsToPartitions(Request $request, string $tenantId, FestEvent $event, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $count = app(\App\Services\Events\FestItemSyncService::class)->resyncAllItemsToPartitions($event);

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.items.resynced_partitions', "Resynced item configurations to child regions/partitions", ['count' => $count]);

        return back()->with('success', "Item configurations resynced across all regional child events.");
    }

    /**
     * Keep item definitions, limits, and fees across the item catalogs of existing partition children current.
     */
    private function syncItemToExistingPartitions(FestEvent $event): void
    {
        $partitions = app(\App\Services\Events\FestPartitionService::class);

        if (! $partitions->isPartitionedHub($event)) {
            return;
        }

        $sync = app(\App\Services\Events\FestItemSyncService::class);
        foreach ($partitions->partitions($event) as $child) {
            $role = $partitions->partitionRole($child) ?? 'region';
            $sync->copyItemsToPartition($event, $child, $role);
        }
    }

    /** @return array<string, mixed> */
    private function eventPageContext(FestEvent $event): array
    {
        if (! $event->relationLoaded('items')) {
            $event->load('items');
        }

        $catalogService = app(FestItemCatalogService::class);
        $masterCatalog = app(FestCatalogService::class);
        $masterCatalog->ensureSeeded($this->sahodaya->id, $event->event_type);
        $catalogSummary = $masterCatalog->summary($this->sahodaya->id, $event->event_type);
        $program = $this->programSlugFor($event);

        $feeSchedule = app(\App\Services\Events\FestSchoolEventFeeService::class)->resolveSchedule($event);
        $classGroupScheme = FestClassGroupScheme::resolveForEvent($event, $feeSchedule);
        $taxonomyRegistry = app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id);
        $taxonomyRegistry->ensureDefaults();
        $taxonomy = $taxonomyRegistry->allLabels();
        $taxonomy['class_group'] = FestClassGroupScheme::taxonomyClassGroups($classGroupScheme, $event);

        // Sports (Head = Event): pages group by sport events, not FestItemHead rows.
        // Keep the passive sync (heals partition_role / hub visibility, never creates)
        // but stop shipping head rows to the UI.
        $itemHeads = [];
        if ($event->event_type === 'sports') {
            app(\App\Services\Events\FestItemHeadService::class)->syncEventHeads($event);
        }

        $competitionAreas = [];
        if ($event->event_type !== 'sports' && \Illuminate\Support\Facades\Schema::hasTable('fest_competition_areas')) {
            $competitionAreas = FestCompetitionArea::where('event_id', $event->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->all();
        }

        return [
            'event'         => $event,
            'isPartitionedHub' => app(\App\Services\Events\FestPartitionService::class)->isPartitionedHub($event),
            'groupedItems'  => $catalogService->groupForDisplay($event->items, $event->event_type),
            'taxonomy'      => $taxonomy,
            'itemHeads'     => $itemHeads,
            'competitionAreas' => $competitionAreas,
            'taxonomyMastersUrl' => "/sahodaya-admin/{$this->sahodaya->id}/taxonomy-masters",
            'classGroupScheme' => $classGroupScheme,
            'classGroupSchemeOptions' => FestClassGroupScheme::options(),
            'catalogSummary' => $catalogSummary,
            'catalogUrl'    => ProgramRouteMap::sahodayaCatalogBase($this->sahodaya->id, $program).'/assign',
            'levelLabels'   => FestEvent::levelLabels(),
            'schoolRoundCount'=> $event->childEvents()->where('level_round', 'school')->count(),
            'academicYearOptions' => \App\Models\AcademicYearRecord::orderByDesc('start_date')->get(['id', 'label', 'status']),
            'sportsAgeGroupsUrl' => "/sahodaya-admin/{$this->sahodaya->id}/sports/age-groups",
            'itemsByLevel'  => [
                'state'    => $event->items->where('owner_level', 'state')->values(),
                'sahodaya' => $event->items->where('owner_level', 'sahodaya')->values(),
                'school'   => $event->items->where('owner_level', 'school')->values(),
            ],
            'ownerLevelLabels' => [
                'state'    => 'State catalog',
                'sahodaya' => 'Sahodaya custom',
                'school'   => 'School custom',
            ],
            'feeTypes'      => config('fest_fees.fee_models'),
            'levelFeeLabels'=> config('fest_fees.payer_labels'),
            'feeSchedule'   => $feeSchedule,
            'conductMode'   => $event->conduct_mode ?? 'standard',
            'partitions'    => FestEvent::where('parent_event_id', $event->id)->get(),
            'regions'       => \App\Models\Region::forTenant($this->sahodaya->id)->active()->orderBy('sort_order')->orderBy('name')->get(['id', 'name']),
            'venues'        => \App\Models\FestVenue::where('event_id', $event->id)->with('region:id,name')->orderBy('name')->get(),
            'ageRuleSummary' => $event->event_type === 'sports' ? FestSportsAgeGroup::ageRuleSummary($event) : null,
            'suggestedAgeCutoff' => $event->event_type === 'sports'
                ? FestSportsAgeGroup::cutoffDate($event)->format('Y-m-d')
                : null,
            'stateProgramSync' => $this->stateProgramSyncInfo($event),
        ];
    }

    /**
     * Set 1 item 3 (STATE_SAHODAYA_RULE_BOUNDARY_FIX_PLAN_2026_08_13.md) — informational
     * only, never a lock: tells the Sahodaya when the State program this event was seeded
     * from has been edited since. The event's own title/dates/venue/fee/description/
     * participation policy are never auto-updated (Set 1 items 1-2), so this is purely a
     * "State's template moved on" notice, not a "your event is out of date" warning.
     */
    private function stateProgramSyncInfo(FestEvent $event): ?array
    {
        if (! $event->isStateProgram()) {
            return null;
        }

        $propagation = \App\Models\FestStateProgramPropagation::where('tenant_event_id', $event->id)
            ->where('sahodaya_id', $this->sahodaya->id)
            ->first();

        if (! $propagation) {
            return null;
        }

        return [
            'synced_at' => $propagation->program_updated_at_when_synced?->toIso8601String(),
            'diverged'  => $propagation->isDivergedFromState(),
        ];
    }

    private function programSlugFor(FestEvent $event): string
    {
        return app(\App\Services\Events\FestCompetitionTypeRegistry::class)
            ->forTenant($this->sahodaya->id)
            ->slugForEventType($event->event_type)
            ?? 'custom';
    }

    /** @return array<string, mixed> */
    private function taxonomyValidationRules(FestTaxonomyRegistry $registry, FestEvent $event): array
    {
        $ageKeys = array_keys(FestSportsAgeGroup::labels($this->sahodaya->id));
        $classKeys = array_keys(FestClassGroupScheme::labels(null, $event));
        $kidsKeys = array_keys(\App\Support\FestKidsFestBand::labels());

        // 'sports' was a valid value under the old hardcoded category enum
        // (nullable|in:music,dance,drama,literary,sports,general) but isn't part of
        // the arts_category taxonomy's default set. Keep accepting it so items saved
        // before this change (on any event type, including pre-existing custom
        // events) can still be edited/re-saved without a validation failure — every
        // other old value (music/dance/drama/literary/general) is already covered by
        // arts_category's defaults.
        $categoryKeys = array_unique(array_merge($registry->activeKeys('arts_category'), ['sports']));

        return [
            'category'           => ['nullable', \Illuminate\Validation\Rule::in($categoryKeys)],
            'stage_type'         => ['nullable', $registry->validationRule('stage_type')],
            'venue_type'         => ['nullable', $registry->validationRule('venue_type')],
            'competition_format' => ['nullable', $registry->validationRule('competition_format')],
            'sport_discipline'   => ['nullable', $registry->validationRule('sport_discipline')],
            'participant_type'   => ['nullable', $registry->validationRule('participant_type')],
            'result_method'      => ['nullable', $registry->validationRule('result_method')],
            'gender'             => ['nullable', $registry->validationRule('gender')],
            'class_group'        => ['nullable', \Illuminate\Validation\Rule::in($classKeys)],
            'age_group'          => ['nullable', \Illuminate\Validation\Rule::in($ageKeys)],
            'kids_band'          => ['nullable', \Illuminate\Validation\Rule::in($kidsKeys)],
        ];
    }

    /**
     * Season hub is config-only when sport events exist — send admins to /sports.
     */
    private function redirectSportsSeasonToHub(FestEvent $event, string $message): ?\Illuminate\Http\RedirectResponse
    {
        if ($event->event_type !== 'sports' || ! $event->isSportsSeasonEvent()) {
            return null;
        }

        if (! FestEvent::where('parent_event_id', $event->id)->exists()) {
            return null;
        }

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/sports")
            ->with('info', $message.' Open a sport from the Sports Meet list.');
    }

    private function eventTypes(): array
    {
        return app(\App\Services\Events\FestCompetitionTypeRegistry::class)
            ->forTenant($this->sahodaya->id)
            ->labels(true);
    }

    /**
     * Legacy promote route — now syncs sport child events (Head = Event).
     */
    public function promoteDisciplineEvents(string $tenantId, FestEvent $event)
    {
        abort_unless($event->tenant_id === $this->sahodaya->id, 404);
        abort_unless($event->event_type === 'sports', 422, 'Only sports events support this.');
        abort_unless($event->parent_event_id === null, 422, 'This can only be done on a top-level event, not a sub-event.');

        // Explicit admin action: allowed to create missing sport events.
        $result = app(\App\Services\Events\FestSportsEventSyncService::class)->syncSeason($event, createMissing: true);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/sports")
            ->with('success', "Sport events synced ({$result['created']} created, {$result['updated']} updated).");
    }

    /**
     * Lightweight status-only transition, for one-click "Mark as completed" /
     * "Apply suggested status" actions (e.g. from EventLifecyclePanel) that
     * shouldn't need to submit the full event-settings form.
     */
    public function quickStatus(Request $request, string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'status' => 'required|in:draft,published,registration_open,ongoing,completed,cancelled',
        ]);

        $newStatus = $data['status'];

        \App\Support\StatusTransitionGuard::assert(
            $event,
            $newStatus,
            \App\Support\StatusTransitionGuard::FEST_EVENT_TRANSITIONS,
        );

        if (in_array($newStatus, ['published', 'registration_open'], true)
            && ! in_array($event->status, ['published', 'registration_open', 'ongoing', 'completed'], true)) {
            try {
                \App\Services\Events\EventLifecycleGate::assertCanPublishEvent(
                    $event,
                    $event->venue,
                    $event->event_start,
                );
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                return back()->withErrors(['status' => $e->getMessage()]);
            }
        }

        $previousStatus = $event->status;

        if ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
            app(\App\Services\Events\FestEventStatusService::class)
                ->transitionToCancelled($event, $request->boolean('confirm_credit_all'));
        } else {
            $event->update(['status' => $newStatus]);
        }

        app(\App\Services\Events\FestRegionPartitionService::class)
            ->cascadeLifecycleToChildren($event, ['status' => $event->status]);

        if ($event->event_type === 'sports' && $event->isSportsSeasonEvent()) {
            app(\App\Services\Events\FestSportsEventSyncService::class)->syncSeason($event->fresh());
        }

        if ($newStatus === 'registration_open' && $previousStatus !== 'registration_open') {
            try {
                app(FestEventNotifier::class)->registrationOpened($event->fresh());
            } catch (\Throwable) {
                // Notifications must never block event updates.
            }
        }

        if ($newStatus === 'completed' && $previousStatus !== 'completed') {
            try {
                app(FestEventNotifier::class)->eventCompleted($event->fresh());
            } catch (\Throwable) {
                // Notifications must never block event updates.
            }
        }

        app(PlatformAuditLogger::class)->festEvent(
            $event,
            FestPageActivity::OVERVIEW,
            'fest.event.updated',
            "Event status changed: {$event->title} → {$newStatus}",
            ['status' => $newStatus],
        );

        return back()->with('success', "Status updated to \"{$newStatus}\".");
    }

    public function toggleNavHidden(FestEvent $event)
    {
        abort_unless($event->tenant_id === $this->sahodaya->id, 404);

        $event->update(['nav_hidden' => ! $event->nav_hidden]);

        return back()->with(
            'success',
            $event->nav_hidden
                ? "'{$event->title}' hidden from sidebar navigation."
                : "'{$event->title}' shown in sidebar navigation.",
        );
    }
}
