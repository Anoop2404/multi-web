<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Models\FestEvent;
use App\Models\FestCompetitionArea;
use App\Models\FestSchoolEventFee;
use App\Models\FestEventItem;
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
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start');

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
        ]);
    }

    public function programIndex(string $tenantId, string $program)
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

            return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}");
        }


        if (FestEvent::isSingletonType($eventType, $this->sahodaya->id)) {
            // View-only staff: open the existing hub event if one exists, else fall through
            // to the (read-only) program hub so nothing is created on a GET.
            $event = app(\App\Services\Events\FestPrimaryEventResolver::class)
                ->resolve($this->sahodaya->id, $eventType);
            if ($event && $eventType !== 'sports') {
                return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}");
            }
        }

        $events = FestEvent::forTenant($this->sahodaya->id)
            ->ofType($eventType)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get();

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
        ]);

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

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/events/{$event->id}")
            ->with('success', "Event \"{$event->title}\" created.");
    }

    public function show(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);



        $event->load(['academicYear', 'childEvents', 'parentEvent']);
        $ctx = $this->eventPageContext($event);

        return $this->inertia('Sahodaya/Events/Overview', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::OVERVIEW),
            'stats'        => [
                'items'          => $event->items()->count(),
                'registrations'  => $event->registrations()->count(),
                'school_rounds'  => $ctx['schoolRoundCount'],
            ],
            'lifecycle'       => \App\Services\Events\FestLifecycleService::for($event)->checklist(),
            'suggestedStatus' => \App\Services\Events\FestLifecycleService::for($event)->suggestedStatus(),
        ]);
    }

    public function items(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Items live on each sport event (Chess, Aquatics, …).')) {
            return $redirect;
        }

        $event->load('items');
        $ctx = $this->eventPageContext($event);

        return $this->inertia('Sahodaya/Events/Items/Master', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::ITEMS),
        ]);
    }

    public function itemsList(string $tenantId, FestEvent $event)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);

        if ($redirect = $this->redirectSportsSeasonToHub($event, 'Item listing is per sport event — open Chess, Aquatics, etc. from Sports Meet.')) {
            return $redirect;
        }

        $event->load('items');
        $ctx = $this->eventPageContext($event);

        return $this->inertia('Sahodaya/Events/Items/List', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::ITEMS_LIST),
        ]);
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

        return $this->inertia('Sahodaya/Events/Levels', $ctx + [
            'activityLogs' => $this->pageActivityLogs($event, FestPageActivity::LEVELS),
            'conductMode' => $partitionService->conductMode($event),
            'isPartitionedHub' => $partitionService->isPartitionedHub($event),
            'partitions' => $partitionService->partitions($event)->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'partition_key' => $p->partition_key ?? $p->cluster_key,
                'partition_role' => $p->partition_role ?? 'cluster',
                'cluster_label' => $p->cluster_label,
            ])->values(),
            'conductPresets' => array_keys(config('fest_conduct_presets', [])),
            'memberSchools' => $memberSchools,
            'schoolPartitions' => $schoolPartitionService->assignmentsForHub($event),
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
            unset($data['title'], $data['event_type']);
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
        $event->update($data);

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
            'preset' => 'required|string|in:mcs_kalotsav',
        ]);

        $created = app(\App\Services\Events\FestPartitionService::class)
            ->spawnFromPreset($event, $data['preset']);

        $audit->festEvent($event, FestPageActivity::LEVELS, 'fest.levels.preset_applied', "Conduct preset applied: {$data['preset']}", [
            'preset' => $data['preset'],
            'count'  => count($created),
        ]);

        return back()->with('success', count($created).' partition(s) created from preset.');
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
        abort_if($schoolEvent->level_round !== 'school', 422);
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

        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if($item->isStateCatalog(), 422, 'State catalog items cannot be edited here.');

        $registry = app(FestTaxonomyRegistry::class)->forTenant($this->sahodaya->id);
        $registry->ensureDefaults();

        $data = $request->validate(array_merge([
            'title'          => 'required|string|max:255',
            'qualify_count'  => 'nullable|integer|min:1',
            'max_per_school' => 'nullable|integer|min:1',
            'fee_amount'     => 'nullable|numeric|min:0',
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

        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(string $tenantId, FestEvent $event, FestEventItem $item, PlatformAuditLogger $audit)
    {
        abort_if($event->tenant_id !== $this->sahodaya->id, 403);
        abort_if($item->event_id !== $event->id, 403);
        abort_if($item->isStateCatalog(), 422, 'State catalog items cannot be removed here.');
        $title = $item->title;
        $item->delete();

        $audit->festEvent($event, FestPageActivity::ITEMS, 'fest.item.deleted', "Item removed: {$title}");

        return back()->with('success', 'Item removed.');
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

        return back()->with('success', "{$count} standard item(s) imported.");
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
            'ageRuleSummary' => $event->event_type === 'sports' ? FestSportsAgeGroup::ageRuleSummary($event) : null,
            'suggestedAgeCutoff' => $event->event_type === 'sports'
                ? FestSportsAgeGroup::cutoffDate($event)->format('Y-m-d')
                : null,
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
        abort_unless($event->event_type === 'sports', 422);
        abort_unless($event->parent_event_id === null, 422);

        // Explicit admin action: allowed to create missing sport events.
        $result = app(\App\Services\Events\FestSportsEventSyncService::class)->syncSeason($event, createMissing: true);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/sports")
            ->with('success', "Sport events synced ({$result['created']} created, {$result['updated']} updated).");
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
