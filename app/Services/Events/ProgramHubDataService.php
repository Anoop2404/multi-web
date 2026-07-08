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
            ->visibleToSchool($school->id)
            ->withCount(['registrations' => fn ($q) => $q->where('school_id', $school->id)])
            ->orderByDesc('event_start')
            ->get();

        $schoolEvents = FestEvent::where('tenant_id', $sahodayaId)
            ->ofType($meta['eventType'])
            ->where('level_round', 'school')
            ->where('conducting_school_id', $school->id)
            ->withCount(['items', 'registrations'])
            ->orderByDesc('event_start')
            ->get();

        $openEvents = $sahodayaEvents->whereIn('status', ['published', 'registration_open', 'ongoing']);
        $eventIds = $sahodayaEvents->pluck('id');

        $schoolRegistrations = FestRegistration::where('school_id', $school->id)
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', ['submitted', 'approved'])
            ->count();

        $fees = FestSchoolEventFee::where('school_id', $school->id)
            ->whereIn('event_id', $eventIds)
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
        ], $extra);
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
                'fees_collected'    => (float) FestSchoolEventFee::whereIn('event_id', $eventIds)->where('status', 'approved')->sum('total_due'),
                'fees_pending'      => FestSchoolEventFee::whereIn('event_id', $eventIds)->whereIn('status', ['pending', 'proof_uploaded'])->count(),
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
                    ->limit(3)
                    ->get(['id', 'title', 'event_type', 'registration_close'])
                    ->map(fn ($e) => [
                        'type'  => 'fest',
                        'title' => $e->title,
                        'date'  => $e->registration_close?->toDateString(),
                        'url'   => '/school-admin/'.$school->id.'/'.ProgramRouteMap::prefixFromSlug(
                            match ($e->event_type) {
                                'kalolsavam' => 'kalotsav',
                                'sports' => 'sports-meet',
                                default => str_replace('_', '-', $e->event_type),
                            }
                        ).'/registration',
                    ])
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
                ->limit(5)
                ->get(['id', 'title', 'event_type'])
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
            'fest'       => (float) FestSchoolEventFee::whereIn('event_id', $festEventIds)->where('status', 'approved')->sum('total_due'),
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
            ->with('event:id,event_type')
            ->get()
            ->groupBy(fn ($fee) => $fee->event?->event_type ?? 'unknown')
            ->each(function ($fees, $eventType) use (&$actions, $school) {
                $slug = ProgramRouteMap::slugFromEventType($eventType) ?? str_replace('_', '-', $eventType);
                $label = ProgramRouteMap::labelForSlug($slug);
                $actions[] = [
                    'type'     => 'fest_fee',
                    'priority' => 1,
                    'count'    => $fees->count(),
                    'label'    => "{$label} fees awaiting upload",
                    'url'      => "/school-admin/{$school->id}/{$slug}/registration",
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
