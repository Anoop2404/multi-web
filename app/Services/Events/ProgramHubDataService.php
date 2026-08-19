<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Support\FestSportsAgeGroup;
use App\Support\ProgramRouteMap;
use App\Support\SchoolFestProgram;
use Illuminate\Support\Collection;

class ProgramHubDataService
{
    /** @return array<string, mixed> */
    public function schoolFestHub(Tenant $school, string $programSlug): array
    {
        $meta = SchoolFestProgram::meta($programSlug);
        $sahodayaId = $school->parent_id;
        $prefix = ProgramRouteMap::prefixFromSlug($programSlug);

        $sahodayaEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($meta['eventType'])
            ->listedForSchool($school->id, $meta['eventType'])
            ->withCount(['registrations' => fn ($q) => $q->where('school_id', $school->id)])
            ->orderByDesc('event_start')
            ->get();

        // Non-sports partitioned fests (English Fest, Kalotsav/Kids Fest with regions, …):
        // listedForSchool() alone returns the hub AND every region child as separate rows,
        // so without this every school's hub dashboard showed the empty hub plus every
        // other school's (irrelevant) region as its own "Open Sahodaya events" entry. See
        // FestSchoolPartitionService::filterVisibleToSchool() and
        // FestRegistrationController::filterPartitionedEventsForSchool() for the identical
        // fix on the registration listing page.
        $sahodayaEvents = app(FestSchoolPartitionService::class)->filterVisibleToSchool($sahodayaEvents, $school->id);

        $schoolEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($meta['eventType'])
            ->where('level_round', 'school')
            ->where('conducting_school_id', $school->id)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get();

        $openStatuses = $meta['eventType'] === 'sports'
            ? ['registration_open', 'ongoing']
            : ['published', 'registration_open', 'ongoing'];
        $openEvents = $sahodayaEvents->whereIn('status', $openStatuses);
        $eventIds = $sahodayaEvents->pluck('id');

        $schoolRegistrations = FestRegistration::where('school_id', $school->id)
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        $fees = FestSchoolEventFee::where('school_id', $school->id)
            ->whereIn('event_id', $eventIds)
            ->forAmountAggregation()
            ->get();

        $extra = [];
        if ($meta['eventType'] === 'sports') {
            $extra['ageGroups'] = FestSportsAgeGroup::labels();
            $extra['registeredAgeGroups'] = FestRegistration::query()
                ->where('fest_registrations.school_id', $school->id)
                ->whereIn('fest_registrations.event_id', $eventIds)
                ->whereIn('fest_registrations.status', ['submitted', 'approved'])
                ->join('fest_event_items', 'fest_registrations.item_id', '=', 'fest_event_items.id')
                ->distinct()
                ->pluck('fest_event_items.age_group')
                ->filter()
                ->values()
                ->all();
        }

        return array_merge([
            'programPrefix' => $prefix,
            'schoolEvents'  => $schoolEvents->map(fn (FestEvent $e) => [
                'id'                  => $e->id,
                'title'               => $e->title,
                'status'              => $e->status,
                'items_count'         => $e->items_count,
                'registrations_count' => $e->registrations_count,
                'results_published'   => $e->results_published,
                'url'                 => $meta['eventType'] === 'sports'
                    ? "/school-admin/{$school->id}/sports/my-event/{$e->id}"
                    : "/school-admin/{$school->id}/fest-programs/{$e->id}",
            ])->values()->all(),
            'stats' => [
                'open_events'       => $openEvents->count(),
                'school_events'     => $schoolEvents->count(),
                'registrations'     => $schoolRegistrations,
                'results_available' => $sahodayaEvents->where('results_published', true)->count(),
                'fees_due'          => (float) $fees->whereIn('status', ['pending', 'proof_uploaded'])->sum('total_due'),
                'fees_paid'         => (float) $fees->where('status', 'approved')->sum('total_due'),
                'fees_awaiting'     => $fees->where('status', 'proof_uploaded')->count(),
            ],
            'events' => $openEvents->take(6)->map(fn (FestEvent $e) => [
                'id'                  => $e->id,
                'title'               => $e->title,
                'status'              => $e->status,
                'level_round'         => $e->level_round,
                'level_label'         => config("fest_fees.level_labels.{$e->level_round}", $e->level_round),
                'registrations_count' => $e->registrations_count,
                'results_published'   => $e->results_published,
            ])->values()->all(),
            'regionOptions' => $this->resolveRegionOptionsForSchool($school, $meta['eventType']),
        ], $extra);
    }

    /** @return array<string, mixed> */
    public function resolveRegionOptionsForSchool(Tenant $school, string $eventType): array
    {
        $sahodayaId = $school->parent_id;
        if (! $sahodayaId) {
            return ['has_regions' => false, 'regions' => [], 'assignments' => []];
        }

        $regionService = app(FestRegionPartitionService::class);
        if (! $regionService->regionsApply($sahodayaId)) {
            return ['has_regions' => false, 'regions' => [], 'assignments' => []];
        }

        $hasRegionalEvent = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($eventType)
            ->where(function ($q) {
                $q->whereNotNull('region_id')
                  ->orWhere('conduct_mode', 'partitioned')
                  ->orWhereHas('phases', fn ($pq) => $pq->where('is_regional', true));
            })
            ->exists();

        if (! $hasRegionalEvent) {
            return ['has_regions' => false, 'regions' => [], 'assignments' => []];
        }

        $activeRegions = \App\Models\Region::forTenant($sahodayaId)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description']);

        if ($activeRegions->isEmpty()) {
            return ['has_regions' => false, 'regions' => [], 'assignments' => []];
        }

        $venues = \App\Models\FestVenue::where('tenant_id', $sahodayaId)
            ->where('is_active', true)
            ->get()
            ->groupBy('region_id');

        $childEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->whereNotNull('region_id')
            ->with('conductingSchool:id,name')
            ->get()
            ->groupBy('region_id');

        $regionList = $activeRegions->map(function (\App\Models\Region $r) use ($venues, $childEvents) {
            $venue = $venues->get($r->id)?->first();
            $eventWithVenue = $childEvents->get($r->id)?->first();

            $venueName = $venue?->name
                ?: $eventWithVenue?->conductingSchool?->name
                ?: $eventWithVenue?->location_name
                ?: null;

            $location = $venue?->location
                ?: ($eventWithVenue?->conductingSchool?->name && $eventWithVenue?->location_name ? $eventWithVenue->location_name : null)
                ?: null;

            return [
                'id'          => $r->id,
                'name'        => $r->name,
                'code'        => $r->code,
                'description' => $r->description,
                'venue_name'  => $venueName ?: 'Venue to be announced',
                'location'    => $location,
            ];
        })->values()->all();

        $year = \App\Support\AcademicYear::forSahodaya($sahodayaId);
        $hasGroupCol = \Illuminate\Support\Facades\Schema::hasColumn('school_region_assignments', 'partition_group');

        $assignments = \App\Models\SchoolRegionAssignment::forTenant($sahodayaId)
            ->forYear($year)
            ->where('school_id', $school->id)
            ->get($hasGroupCol ? ['partition_group', 'region_id'] : ['region_id'])
            ->mapWithKeys(fn ($a) => [(($hasGroupCol ? $a->partition_group : null) ?? 'default') => $a->region_id])
            ->all();

        $regionalGroups = \App\Models\FestEventPhase::query()
            ->whereNotNull('region_partition_group')
            ->whereHas('event', fn ($q) => $q->where('tenant_id', $sahodayaId)->ofType($eventType)->whereNull('parent_event_id'))
            ->orderBy('sort_order')
            ->get(['region_partition_group', 'name'])
            ->unique('region_partition_group')
            ->map(fn (\App\Models\FestEventPhase $phase) => [
                'key'   => $phase->region_partition_group,
                'label' => $phase->name,
            ])
            ->values()
            ->all();

        $phasedEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($eventType)
            ->whereNull('parent_event_id')
            ->whereIn('status', ['published', 'registration_open', 'ongoing'])
            ->where('workflow_mode', \App\Services\Events\FestPhasedWorkflowService::MODE)
            ->get();

        $phaseRegionOptions = [];
        foreach ($phasedEvents as $phasedHub) {
            $selections = \App\Models\FestSchoolPhaseRegionSelection::where('event_id', $phasedHub->id)
                ->where('school_id', $school->id)
                ->get()
                ->keyBy('phase_id');

            $options = $phasedHub->phases()
                ->where('is_regional', true)
                ->with('allowedRegions.region')
                ->get()
                ->map(fn (\App\Models\FestEventPhase $phase) => [
                    'event_id'    => $phasedHub->id,
                    'event_title' => $phasedHub->title,
                    'phase_id'    => $phase->id,
                    'phase_name'  => $phase->name,
                    'selection'   => ($selection = $selections->get($phase->id)) ? [
                        'region_id' => $selection->region_id,
                        'locked'    => $selection->locked_at !== null,
                    ] : null,
                    'regions'     => $phase->allowedRegions->where('enabled', true)->map(fn ($allowed) => [
                        'id'               => $allowed->region_id,
                        'name'             => $allowed->region?->name,
                        'code'             => $allowed->region?->code,
                        'venue'            => $allowed->venue,
                        'conduct_start_at' => $allowed->conduct_start_at?->toIso8601String(),
                    ])->values()->all(),
                ])->values()->all();

            $phaseRegionOptions = array_merge($phaseRegionOptions, $options);
        }

        $hasRegions = ! empty($phaseRegionOptions)
            || ! empty($regionalGroups)
            || FestEvent::where('tenant_id', $sahodayaId)
                ->ofType($eventType)
                ->where(fn ($q) => $q->whereNotNull('region_id')->orWhere('conduct_mode', 'partitioned'))
                ->whereHas('phases', fn ($pq) => $pq->where('is_regional', true))
                ->exists();

        return [
            'has_regions'          => $hasRegions,
            'regions'              => $hasRegions ? $regionList : [],
            'assignments'          => $assignments,
            'regional_groups'      => $regionalGroups,
            'phase_region_options' => $phaseRegionOptions,
        ];
    }

    /** @return array<string, mixed> */
    public function sahodayaProgramDashboard(Tenant $sahodaya, string $programSlug, string $eventType): array
    {
        $activeStatuses = ['published', 'registration_open', 'ongoing'];
        $events = FestEvent::forTenant($sahodaya->id)
            ->ofType($eventType)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get();

        $eventIds = $events->pluck('id');
        $schoolIds = Tenant::where('parent_id', $sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $registeredSchoolIds = FestRegistration::whereIn('event_id', $eventIds)
            ->whereIn('status', ['submitted', 'approved'])
            ->distinct()
            ->pluck('school_id');

        $feePaidSchoolIds = FestSchoolEventFee::whereIn('event_id', $eventIds)
            ->where('status', 'approved')
            ->distinct()
            ->pluck('school_id');

        $schoolParticipation = $schoolIds->map(function ($schoolId) use ($registeredSchoolIds, $feePaidSchoolIds) {
            $school = Tenant::find($schoolId);

            return [
                'id'           => $schoolId,
                'name'         => $school?->name,
                'registered'   => $registeredSchoolIds->contains($schoolId),
                'fee_paid'     => $feePaidSchoolIds->contains($schoolId),
            ];
        })->sortBy('name')->values()->all();

        $eventsByLevel = [
            'school'   => $events->where('level_round', 'school')->count(),
            'sahodaya' => $events->where('level_round', 'sahodaya')->count(),
            'state'    => $events->where('level_round', 'state')->count(),
        ];

        return [
            'schoolParticipation' => $schoolParticipation,
            'eventsByLevel'       => $eventsByLevel,
            'stats'               => [
                'events'            => $events->count(),
                'active_events'     => $events->whereIn('status', $activeStatuses)->count(),
                'registrations'     => (int) $events->sum('registrations_count'),
                'items'             => (int) $events->sum('items_count'),
                'results_published' => $events->where('results_published', true)->count(),
                'fees_collected'    => (float) FestSchoolEventFee::whereIn('event_id', $eventIds)->forAmountAggregation()->where('status', 'approved')->sum('total_due'),
                // Only rows with an actual amount owed: ₹0 placeholder rows are
                // auto-created when a school merely opens a registration page and
                // must not show up as "pending fees".
                'fees_pending'      => FestSchoolEventFee::whereIn('event_id', $eventIds)->forAmountAggregation()->whereIn('status', ['pending', 'proof_uploaded'])->where('total_due', '>', 0)->count(),
                'schools_registered'=> $registeredSchoolIds->count(),
                'schools_total'     => $schoolIds->count(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function schoolDashboardExtras(Tenant $school): array
    {
        $sahodayaId = $school->parent_id;
        if (! $sahodayaId) {
            return [];
        }

        $examIds = McqExam::where('tenant_id', $sahodayaId)->pluck('id');
        $programIds = TrainingProgram::where('tenant_id', $sahodayaId)->pluck('id');

        $festEventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');
        $pendingFees = FestSchoolEventFee::where('school_id', $school->id)
            ->whereIn('event_id', $festEventIds)
            ->forAmountAggregation()
            ->whereIn('status', ['pending', 'proof_uploaded'])
            ->sum('total_due');

        $mcqFees = McqSchoolFee::where('school_id', $school->id)
            ->whereIn('exam_id', $examIds)
            ->whereIn('status', ['pending', 'proof_uploaded'])
            ->sum('total_due');

        $upcoming = collect()
            ->merge(
                McqExam::where('tenant_id', $sahodayaId)
                    ->whereIn('status', ['published', 'ongoing'])
                    ->where('scheduled_at', '>=', now())
                    ->orderBy('scheduled_at')
                    ->limit(3)
                    ->get(['id', 'title', 'scheduled_at'])
                    ->map(fn ($e) => ['type' => 'mcq', 'title' => $e->title, 'date' => $e->scheduled_at?->toDateString(), 'url' => "/school-admin/{$school->id}/mcq"])
            )
            ->merge(
                FestEvent::where('tenant_id', $sahodayaId)
                    ->whereIn('status', ['published', 'registration_open'])
                    ->where('registration_close', '>=', now())
                    ->orderBy('registration_close')
                    ->get(['id', 'title', 'event_type', 'parent_event_id', 'conduct_mode', 'cluster_label', 'registration_close'])
                    ->pipe(fn ($events) => app(FestSchoolPartitionService::class)->filterVisibleToSchool($events, $school->id))
                    ->take(5)
                    ->map(function ($e) use ($school) {
                        $displayTitle = $e->title;
                        if ($e->parent_event_id) {
                            $parentTitle = FestEvent::where('id', $e->parent_event_id)->value('title');
                            if ($parentTitle) {
                                $label = $e->cluster_label ?: \Illuminate\Support\Str::after($e->title, '— ');
                                $displayTitle = "{$parentTitle} ({$label})";
                            }
                        }

                        return [
                            'type'  => 'fest',
                            'title' => $displayTitle,
                            'date'  => $e->registration_close?->toDateString(),
                            'url'   => '/school-admin/'.$school->id.'/'.ProgramRouteMap::prefixFromSlug(
                                match ($e->event_type) {
                                    'kalolsavam' => 'kalotsav',
                                    'sports' => 'sports-meet',
                                    default => str_replace('_', '-', $e->event_type),
                                }
                            ).'/events/'.$e->id.'/registration',
                        ];
                    })
            )
            ->sortBy('date')
            ->take(5)
            ->values()
            ->all();

        return [
            'teacherCount'    => Teacher::where('tenant_id', $school->id)->count(),
            'mcqRegistered'   => McqRegistration::where('school_id', $school->id)->whereIn('exam_id', $examIds)->count(),
            'trainingRegistered' => TrainingRegistration::where('school_id', $school->id)->whereIn('program_id', $programIds)->count(),
            'pendingPayments' => [
                'fest'     => (float) $pendingFees,
                'mcq'      => (float) $mcqFees,
                'total'    => (float) $pendingFees + (float) $mcqFees,
            ],
            'pendingActions'  => $this->schoolPendingActions($school, $festEventIds, $examIds, $programIds),
            'upcoming'        => $upcoming,
            'recentResults'   => FestEvent::where('tenant_id', $sahodayaId)
                ->where('results_published', true)
                ->orderByDesc('updated_at')
                ->get(['id', 'title', 'event_type', 'parent_event_id', 'conduct_mode'])
                ->pipe(fn ($events) => app(FestSchoolPartitionService::class)->filterVisibleToSchool($events, $school->id))
                ->take(5)
                ->map(fn ($e) => ['title' => $e->title, 'type' => $e->event_type])
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function sahodayaDashboardExtras(Tenant $sahodaya): array
    {
        $schoolIds = Tenant::where('parent_id', $sahodaya->id)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        $festEventIds = FestEvent::where('tenant_id', $sahodaya->id)->pluck('id');
        $activeStatuses = ['published', 'registration_open', 'ongoing'];

        $programTypes = [
            ['key' => 'kalotsav', 'label' => 'Kalotsav', 'type' => 'kalolsavam', 'prefix' => 'kalotsav'],
            ['key' => 'sports', 'label' => 'Sports Meet', 'type' => 'sports', 'prefix' => 'sports'],
            ['key' => 'kids-fest', 'label' => 'Kids Fest', 'type' => 'kids_fest', 'prefix' => 'kids-fest'],
            ['key' => 'teacher-fest', 'label' => 'Teacher Fest', 'type' => 'teacher_fest', 'prefix' => 'teacher-fest'],
        ];

        $programStatus = collect($programTypes)->map(function (array $p) use ($sahodaya, $activeStatuses) {
            $events = FestEvent::where('tenant_id', $sahodaya->id)->ofType($p['type'])->get();

            return [
                'key'            => $p['key'],
                'type'           => $p['type'],
                'label'          => $p['label'],
                'prefix'         => $p['prefix'],
                'open_events'    => $events->whereIn('status', $activeStatuses)->count(),
                'registrations'  => FestRegistration::whereIn('event_id', $events->pluck('id'))->whereIn('status', ['submitted', 'approved'])->count(),
                'results_pending'=> $events->where('status', 'completed')->where('results_published', false)->count(),
                'hub_url'        => "/sahodaya-admin/{$sahodaya->id}/{$p['prefix']}",
            ];
        })->all();

        $financeSummary = [
            'membership' => (float) \App\Models\MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'approved')->sum('amount'),
            'fest'       => (float) FestSchoolEventFee::whereIn('event_id', $festEventIds)->forAmountAggregation()->where('status', 'approved')->sum('total_due'),
            'mcq'        => (float) McqSchoolFee::whereHas('exam', fn ($q) => $q->where('tenant_id', $sahodaya->id))->where('status', 'approved')->sum('total_due'),
            'training'   => (float) \App\Models\FeeReceipt::query()
                ->where('status', 'approved')
                ->where('feeable_type', TrainingRegistration::class)
                ->whereIn(
                    'feeable_id',
                    TrainingRegistration::query()
                        ->whereHas('program', fn ($q) => $q->where('tenant_id', $sahodaya->id))
                        ->pluck('id')
                )
                ->sum('amount'),
        ];

        $registeredSchoolIds = FestRegistration::whereIn('event_id', $festEventIds)
            ->whereIn('status', ['submitted', 'approved'])
            ->distinct()
            ->pluck('school_id');

        $schoolActivity = Tenant::whereIn('id', $schoolIds)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Tenant $s) => [
                'id'       => $s->id,
                'name'     => $s->name,
                'active'   => $registeredSchoolIds->contains($s->id),
            ])
            ->sortByDesc('active')
            ->values()
            ->all();

        return [
            'programStatus'  => $programStatus,
            'financeSummary' => $financeSummary,
            'schoolActivity' => $schoolActivity,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function crossEventHouseStandings(Tenant $sahodaya): array
    {
        $events = FestEvent::forTenant($sahodaya->id)
            ->ofType('sports')
            ->where('results_published', true)
            ->get();

        $totals = [];

        foreach ($events as $event) {
            foreach (EventContext::for($event)->scoreboardByHouse() as $row) {
                $id = $row['house_id'];
                if (! isset($totals[$id])) {
                    $totals[$id] = [
                        'house_id'     => $id,
                        'house_name'   => $row['house_name'],
                        'color'        => $row['color'] ?? null,
                        'total_points' => 0,
                        'events_count' => 0,
                    ];
                }
                $totals[$id]['total_points'] += $row['total_points'];
                $totals[$id]['events_count']++;
            }
        }

        $sorted = collect($totals)->sortByDesc('total_points')->values();
        $rank = 0;
        $last = null;

        return $sorted->map(function (array $row, int $i) use (&$rank, &$last) {
            if ($last !== $row['total_points']) {
                $rank = $i + 1;
                $last = $row['total_points'];
            }
            $row['rank'] = $rank;

            return $row;
        })->all();
    }

    /** @return list<array{type: string, count: int, label: string, url?: string}> */
    private function schoolPendingActions(Tenant $school, $festEventIds, $examIds, $programIds): array
    {
        $actions = [];
        $academicYear = \App\Support\AcademicYear::forSchool($school);

        FestSchoolEventFee::query()
            ->where('school_id', $school->id)
            ->whereIn('event_id', $festEventIds)
            ->where('status', 'pending')
            ->where('total_due', '>', 0)
            ->with('event:id,title,event_type')
            ->get()
            ->each(function ($fee) use (&$actions, $school) {
                if (! $fee->event) {
                    return;
                }
                $slug = ProgramRouteMap::slugFromEventType($fee->event->event_type) ?? str_replace('_', '-', $fee->event->event_type);
                $prefix = ProgramRouteMap::prefixFromSlug($slug);
                $actions[] = [
                    'type'     => 'fest_fee',
                    'priority' => 1,
                    'count'    => 1,
                    'label'    => "{$fee->event->title} fees awaiting upload",
                    'url'      => "/school-admin/{$school->id}/{$prefix}/events/{$fee->event_id}/registration?tab=fees",
                ];
            });

        $mcqFeesPending = McqSchoolFee::where('school_id', $school->id)
            ->whereIn('exam_id', $examIds)
            ->where('status', 'pending')
            ->count();
        if ($mcqFeesPending > 0) {
            $actions[] = [
                'type'     => 'mcq_fee',
                'priority' => 1,
                'count'    => $mcqFeesPending,
                'label'    => 'Talent Search fees awaiting payment proof',
                'url'      => "/school-admin/{$school->id}/mcq",
            ];
        }

        $mcqProofsAwaiting = McqSchoolFee::where('school_id', $school->id)
            ->whereIn('exam_id', $examIds)
            ->where('status', 'proof_uploaded')
            ->count();
        if ($mcqProofsAwaiting > 0) {
            $actions[] = [
                'type'     => 'mcq_fee_review',
                'priority' => 2,
                'count'    => $mcqProofsAwaiting,
                'label'    => 'Talent Search fee proofs awaiting Sahodaya approval',
                'url'      => "/school-admin/{$school->id}/mcq",
            ];
        }

        $membershipStatus = \App\Models\Registration::where('school_id', $school->id)
            ->where('academic_year', $academicYear)
            ->value('registration_status');

        if (! $membershipStatus || in_array($membershipStatus, ['payment_pending', 'data_pending', 'draft'], true)) {
            $actions[] = [
                'type'     => 'membership',
                'priority' => 1,
                'count'    => 1,
                'label'    => 'Annual membership incomplete',
                'url'      => "/school-admin/{$school->id}/registration",
            ];
        }

        $openMcq = McqExam::where('tenant_id', $school->parent_id)
            ->whereIn('status', ['published', 'ongoing'])
            ->whereIn('id', $examIds)
            ->count();
        if ($openMcq > 0 && McqRegistration::where('school_id', $school->id)->whereIn('exam_id', $examIds)->count() === 0) {
            $actions[] = [
                'type'     => 'mcq_register',
                'priority' => 1,
                'count'    => $openMcq,
                'label'    => 'Talent Search exams open for registration',
                'url'      => "/school-admin/{$school->id}/mcq",
            ];
        }

        usort($actions, fn ($a, $b) => ($a['priority'] ?? 9) <=> ($b['priority'] ?? 9));

        return $actions;
    }
}
