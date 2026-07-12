<?php

namespace App\Services\Reports;

use App\Models\Certificate;
use App\Models\FestAppeal;
use App\Models\FestAthleticRecord;
use App\Models\FestCatalogItem;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestJudgeAssignment;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRecordBreak;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\FestSchoolEventFee;
use App\Models\FestStateProgramPropagation;
use App\Models\FestSubstitutionRequest;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class FestCrossEventReportService
{
    public function supports(string $reportId): bool
    {
        return str_starts_with($reportId, 'RPT-SPT-')
            || str_starts_with($reportId, 'RPT-KAL-')
            || str_starts_with($reportId, 'RPT-FST-');
    }

    /** @param  array<string, mixed>  $filters */
    public function rows(string $sahodayaId, string $reportId, array $filters = []): Collection
    {
        if (str_starts_with($reportId, 'RPT-FST-')) {
            return $this->festHubRows($sahodayaId, $reportId, $filters);
        }

        $eventType = str_starts_with($reportId, 'RPT-KAL-') ? 'kalolsavam' : 'sports';
        $eventIds = $this->eventIds($sahodayaId, $eventType, $filters);

        if ($eventIds->isEmpty()) {
            return collect();
        }

        if (str_starts_with($reportId, 'RPT-KAL-')) {
            return $this->kalRows($sahodayaId, $reportId, $eventIds, $filters);
        }

        return $this->sportsRows($sahodayaId, $reportId, $eventIds, $filters);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function sportsRows(string $sahodayaId, string $reportId, Collection $eventIds, array $filters): Collection
    {
        return match ($reportId) {
            'RPT-SPT-024' => $this->registrationWindowStatus($sahodayaId, 'sports', $filters),
            'RPT-SPT-037' => collect([['event' => '—', 'participant' => 'Gate log not configured', 'school' => '—', 'scanned_at' => '—']]),
            default       => $this->sportsRowsBySuffix((int) substr($reportId, -3), $sahodayaId, $eventIds, $filters),
        };
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function sportsRowsBySuffix(int $suffix, string $sahodayaId, Collection $eventIds, array $filters): Collection
    {
        return match ($suffix) {
            1       => $this->registrationsByItem($eventIds, $filters),
            2       => $this->schoolRegistrationSummary($eventIds),
            3       => $this->feeCollection($eventIds),
            4       => $this->scheduleByStage($eventIds, $filters),
            5       => $this->scheduleClashPlaceholder($eventIds),
            6       => $this->chestNumberList($eventIds, $filters),
            7, 14   => $this->participantAttendance($eventIds, $filters),
            8, 45   => $this->resultsByItem($eventIds, $filters),
            9, 10, 34 => $this->schoolPointsRanking($eventIds),
            11      => $this->athleticRecords($eventIds),
            12, 28  => $this->substitutionLog($eventIds),
            13      => $this->eligibilityExceptions($eventIds, $filters),
            15      => $this->headWiseSummary($eventIds, $filters),
            17      => $this->genderOrClassParticipation($eventIds, false),
            18      => $this->ageGroupMatrix($eventIds),
            19      => $this->schoolPointsDetail($eventIds, $filters),
            20      => $this->medalTally($eventIds),
            22      => $this->officialAssignments($eventIds),
            23      => $this->venueUtilization($eventIds),
            25      => $this->freeVsPaidBreakdown($eventIds),
            26      => $this->markEntryStatus($eventIds),
            27      => $this->unpublishedResults($eventIds),
            29, 30  => $suffix === 29 ? $this->certificateLog($eventIds) : $this->idCardIssuedLog($eventIds),
            31      => $this->levelRegistration($sahodayaId, 'sports'),
            32      => $this->clusterSummary($eventIds),
            33      => $this->topPerformers($eventIds, $filters),
            36      => $this->dailyScheduleBulletin($eventIds, $filters),
            38      => $this->cateringSummary($eventIds),
            39      => $this->itemFeeConfig($eventIds),
            40      => $this->pendingApprovals($eventIds, $filters),
            41      => $this->marksVerificationPending($eventIds),
            42      => $this->recordBreakLog($eventIds),
            43      => $this->teamRoster($eventIds, $filters),
            default => $this->registrationsByItem($eventIds, $filters),
        };
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function kalRows(string $sahodayaId, string $reportId, Collection $eventIds, array $filters): Collection
    {
        return match ($reportId) {
            'RPT-KAL-015' => $this->registrationWindowStatus($sahodayaId, 'kalolsavam', $filters),
            'RPT-KAL-019' => $this->catalogCategories($sahodayaId),
            'RPT-KAL-031' => $this->catalogMasterExport($sahodayaId),
            'RPT-KAL-029' => $this->levelPropagation($sahodayaId),
            default       => $this->kalRowsBySuffix((int) substr($reportId, -3), $sahodayaId, $eventIds, $filters),
        };
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function kalRowsBySuffix(int $suffix, string $sahodayaId, Collection $eventIds, array $filters): Collection
    {
        return match ($suffix) {
            1       => $this->registrationsByItem($eventIds, $filters),
            2       => $this->schoolRegistrationSummary($eventIds),
            3       => $this->judgeAssignments($eventIds),
            4       => $this->scoreSheets($eventIds, $filters),
            5       => $this->tabulationSheet($eventIds),
            6       => $this->rankListByItem($eventIds, $filters),
            7, 22   => $this->schoolPointsRanking($eventIds),
            8, 33   => $this->appealsRegister($eventIds),
            9       => $this->scheduleByStage($eventIds, $filters),
            10, 36  => $this->teamRoster($eventIds, $filters),
            11      => $this->feeCollection($eventIds),
            12      => $this->unpublishedResults($eventIds),
            13, 34, 35 => $this->certificateLog($eventIds),
            16      => $this->judgeScoreDetail($eventIds, $filters),
            17      => $this->multiJudgeAverage($eventIds, $filters),
            20      => $this->genderOrClassParticipation($eventIds, true),
            21      => $this->schoolPointsDetail($eventIds, $filters),
            23      => $this->headWiseSummary($eventIds, $filters),
            24      => $this->markEntryStatus($eventIds),
            25      => $this->resultPublishLog($eventIds),
            26      => $this->idCardIssuedLog($eventIds),
            28      => $this->substitutionLog($eventIds),
            30      => $this->stateSyncLog($sahodayaId),
            32      => $this->judgeLoginActivity($eventIds),
            37, 38, 39, 40, 41, 42, 43, 44, 45 => $this->eventMetrics($eventIds, $suffix),
            default => $this->registrationsByItem($eventIds, $filters),
        };
    }

    /** @param  array<string, mixed>  $filters */
    private function eventIds(string $sahodayaId, ?string $eventType, array $filters): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->when($eventType, fn ($q) => $q->where('event_type', $eventType))
            ->when(! empty($filters['event_id']), fn ($q) => $q->where('id', $filters['event_id']))
            ->pluck('id');
    }

    /** @param  array<string, mixed>  $filters */
    private function festHubRows(string $sahodayaId, string $reportId, array $filters): Collection
    {
        return match ($reportId) {
            'RPT-FST-001' => FestEvent::where('tenant_id', $sahodayaId)
                ->when(! empty($filters['event_id']), fn ($q) => $q->where('id', $filters['event_id']))
                ->withCount('registrations')
                ->orderByDesc('event_start')
                ->get()
                ->map(fn (FestEvent $e) => [
                    'event'         => $e->title,
                    'type'          => $e->event_type,
                    'status'        => $e->status,
                    'starts'        => $e->event_start?->toDateString(),
                    'registrations' => $e->registrations_count,
                ]),
            'RPT-FST-002' => FestSchoolEventFee::query()
                ->whereIn('event_id', $this->festEventIds($sahodayaId, $filters))
                ->forAmountAggregation()
                ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', $filters['school_id']))
                ->with(['event:id,title', 'school:id,name'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (FestSchoolEventFee $f) => [
                    'event'  => $f->event?->title,
                    'school' => $f->school?->name,
                    'amount' => (float) $f->total_due,
                    'status' => $f->status,
                ]),
            'RPT-FST-003' => FestAppeal::query()
                ->whereIn('event_id', $this->festEventIds($sahodayaId, $filters))
                ->when(! empty($filters['school_id']), function ($q) use ($filters) {
                    $q->whereHas('participant.registration', fn ($r) => $r->where('school_id', $filters['school_id']));
                })
                ->with(['event:id,title', 'participant.registration.school:id,name'])
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (FestAppeal $a) => [
                    'event'      => $a->event?->title,
                    'school'     => $a->participant?->registration?->school?->name ?? '—',
                    'item'       => $a->participant?->registration?->item_id ?? '—',
                    'status'     => $a->status,
                    'created_at' => $a->created_at?->format('j M Y'),
                ]),
            'RPT-FST-004' => Certificate::query()
                ->whereIn('entity_type', [FestRegistration::class, FestParticipant::class])
                ->orderByDesc('generated_at')
                ->limit(500)
                ->get()
                ->map(fn (Certificate $c) => [
                    'event'        => '—',
                    'school'       => '—',
                    'participant'  => (string) $c->entity_id,
                    'cert_type'    => $c->cert_type,
                    'generated_at' => $c->generated_at?->format('j M Y'),
                ]),
            default => FestEvent::where('tenant_id', $sahodayaId)->get()->map(fn (FestEvent $e) => [
                'event'             => $e->title,
                'type'              => $e->event_type,
                'status'            => $e->status,
                'export_count_note' => 'Open event workspace → Reports for full exports',
            ]),
        };
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function registrationsByItem(Collection $eventIds, array $filters): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', $filters['school_id']))
            ->with(['event:id,title', 'school:id,name', 'item:id,title,head_id', 'item.head:id,name', 'participants'])
            ->whereNotIn('status', ['withdrawn', 'rejected'])
            ->get()
            ->map(fn (FestRegistration $r) => [
                'event'        => $r->event?->title,
                'school'       => $r->school?->name,
                'item'         => $r->item?->title,
                'head'         => $r->item?->head?->name,
                'participants' => $r->participants->count(),
                'status'       => $r->status,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function schoolRegistrationSummary(Collection $eventIds): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'school:id,name'])
            ->get()
            ->groupBy(fn (FestRegistration $r) => $r->event_id.'|'.$r->school_id)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'event'         => $first->event?->title,
                    'school'        => $first->school?->name,
                    'registrations' => $group->count(),
                    'approved'      => $group->where('status', 'approved')->count(),
                    'pending'       => $group->where('status', 'submitted')->count(),
                ];
            })
            ->values();
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function feeCollection(Collection $eventIds): Collection
    {
        return FestSchoolEventFee::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'school:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FestSchoolEventFee $f) => [
                'event'  => $f->event?->title,
                'school' => $f->school?->name,
                'amount' => (float) $f->total_due,
                'status' => $f->status,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function scheduleByStage(Collection $eventIds, array $filters): Collection
    {
        return FestSchedule::whereIn('event_id', $eventIds)
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('scheduled_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('scheduled_at', '<=', $filters['to']))
            ->with(['event:id,title', 'item:id,title'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (FestSchedule $s) => [
                'event'        => $s->event?->title,
                'item'         => $s->item?->title,
                'stage'        => $s->stage ?? $s->festStage?->name ?? '—',
                'scheduled_at' => $s->scheduled_at?->format('j M Y H:i'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function scheduleClashPlaceholder(Collection $eventIds): Collection
    {
        return FestEvent::whereIn('id', $eventIds)->get()->map(fn (FestEvent $e) => [
            'event'      => $e->title,
            'school'     => '—',
            'item_a'     => 'Open event → Schedule clashes',
            'item_b'     => '—',
            'conflict_at'=> '—',
        ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function chestNumberList(Collection $eventIds, array $filters): Collection
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $eventIds)
            ->when(! empty($filters['school_id']), fn ($q2) => $q2->where('school_id', $filters['school_id'])))
            ->with(['registration.event:id,title', 'registration.school:id,name', 'registration.item:id,title', 'student:id,name'])
            ->whereNotNull('chest_no')
            ->get()
            ->map(fn (FestParticipant $p) => [
                'event'    => $p->registration?->event?->title,
                'school'   => $p->registration?->school?->name,
                'student'  => $p->student?->name,
                'chest_no' => $p->chest_no,
                'item'     => $p->registration?->item?->title,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function participantAttendance(Collection $eventIds, array $filters): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', $filters['school_id']))
            ->with(['event:id,title', 'school:id,name', 'item:id,title', 'participants'])
            ->get()
            ->map(fn (FestRegistration $r) => [
                'event'        => $r->event?->title,
                'item'         => $r->item?->title,
                'school'       => $r->school?->name,
                'participants' => $r->participants->count(),
                'present'      => $r->participants->whereNull('disqualified_at')->count(),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function resultsByItem(Collection $eventIds, array $filters): Collection
    {
        return FestMark::whereIn('event_id', $eventIds)
            ->when(! empty($filters['school_id']), fn ($q) => $q->whereHas('participant.registration', fn ($q2) => $q2->where('school_id', $filters['school_id'])))
            ->with(['event:id,title', 'item:id,title', 'participant.registration.school:id,name', 'participant.student:id,name'])
            ->orderBy('item_id')
            ->get()
            ->map(fn (FestMark $m) => [
                'event'       => $m->event?->title,
                'item'        => $m->item?->title,
                'school'      => $m->participant?->registration?->school?->name,
                'participant' => $m->participant?->student?->name,
                'mark'        => $m->score,
                'rank'        => $m->position,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function schoolPointsRanking(Collection $eventIds): Collection
    {
        return FestMark::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'participant.registration.school:id,name'])
            ->get()
            ->groupBy(fn (FestMark $m) => $m->event_id.'|'.($m->participant?->registration?->school_id ?? 'unknown'))
            ->map(function ($group, $key) {
                $first = $group->first();

                return [
                    'event'  => $first->event?->title,
                    'school' => $first->participant?->registration?->school?->name ?? '—',
                    'points' => round($group->sum(fn (FestMark $m) => (float) $m->score), 2),
                    'rank'   => '—',
                ];
            })
            ->sortByDesc('points')
            ->values()
            ->map(function (array $row, int $i) {
                $row['rank'] = $i + 1;

                return $row;
            });
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function athleticRecords(Collection $eventIds): Collection
    {
        return FestAthleticRecord::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'item:id,title'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FestAthleticRecord $r) => [
                'event'  => $r->event?->title,
                'item'   => $r->item?->title,
                'record' => $r->record_value ?? $r->notes,
                'holder' => $r->holder_name,
                'value'  => $r->record_value,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function substitutionLog(Collection $eventIds): Collection
    {
        return FestSubstitutionRequest::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'school:id,name', 'registration.item:id,title'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FestSubstitutionRequest $s) => [
                'event'  => $s->event?->title,
                'school' => $s->school?->name,
                'item'   => $s->registration?->item?->title,
                'reason' => $s->reason,
                'status' => $s->status,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function eligibilityExceptions(Collection $eventIds, array $filters): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->where('status', 'rejected')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', $filters['school_id']))
            ->with(['event:id,title', 'school:id,name', 'item:id,title', 'participants.student:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FestRegistration $r) => [
                'event'      => $r->event?->title,
                'school'     => $r->school?->name,
                'student'    => $r->participants->first()?->student?->name ?? '—',
                'item'       => $r->item?->title,
                'reason'     => 'Registration rejected',
                'created_at' => $r->updated_at?->format('j M Y'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function headWiseSummary(Collection $eventIds, array $filters = []): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'item.head:id,name', 'participants'])
            ->get()
            ->when(! empty($filters['head_id']), function ($c) use ($filters) {
                $headId = (int) $filters['head_id'];

                return $c->filter(fn (FestRegistration $r) => (int) ($r->item?->head_id ?? 0) === $headId);
            })
            ->groupBy(fn (FestRegistration $r) => $r->event_id.'|'.($r->item?->head_id ?? 0))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'event'        => $first->event?->title,
                    'head'         => $first->item?->head?->name ?? '—',
                    'participants' => $group->sum(fn (FestRegistration $r) => $r->participants->count()),
                    'schools'      => $group->pluck('school_id')->unique()->count(),
                ];
            })
            ->values();
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function genderOrClassParticipation(Collection $eventIds, bool $byGender): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'item:id,class_group,gender', 'participants'])
            ->get()
            ->groupBy(fn (FestRegistration $r) => $r->event_id.'|'.($byGender ? ($r->item?->gender ?? 'open') : ($r->item?->class_group ?? '—')))
            ->map(function ($group, $key) use ($byGender) {
                $first = $group->first();
                $parts = explode('|', $key);

                return [
                    'event'        => $first->event?->title,
                    $byGender ? 'gender' : 'class_group' => $parts[1] ?? '—',
                    'participants' => $group->sum(fn (FestRegistration $r) => $r->participants->count()),
                ];
            })
            ->values();
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function ageGroupMatrix(Collection $eventIds): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'school:id,name', 'item:id,age_group', 'participants'])
            ->get()
            ->groupBy(fn (FestRegistration $r) => $r->event_id.'|'.$r->school_id.'|'.($r->item?->age_group ?? '—'))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'event'     => $first->event?->title,
                    'age_group' => $first->item?->age_group ?? '—',
                    'school'    => $first->school?->name,
                    'count'     => $group->sum(fn (FestRegistration $r) => $r->participants->count()),
                ];
            })
            ->values();
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function schoolPointsDetail(Collection $eventIds, array $filters): Collection
    {
        return $this->resultsByItem($eventIds, $filters);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function medalTally(Collection $eventIds): Collection
    {
        return FestMark::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'participant.registration.school:id,name'])
            ->get()
            ->groupBy(fn (FestMark $m) => $m->event_id.'|'.($m->participant?->registration?->school_id ?? 'unknown'))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'event'  => $first->event?->title,
                    'school' => $first->participant?->registration?->school?->name ?? '—',
                    'gold'   => $group->where('position', 1)->count(),
                    'silver' => $group->where('position', 2)->count(),
                    'bronze' => $group->where('position', 3)->count(),
                    'total'  => $group->count(),
                ];
            })
            ->values();
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function judgeAssignments(Collection $eventIds): Collection
    {
        return FestJudgeAssignment::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'item:id,title', 'user:id,name'])
            ->get()
            ->map(fn (FestJudgeAssignment $a) => [
                'event' => $a->event?->title,
                'judge' => $a->user?->name,
                'item'  => $a->item?->title,
                'school'=> '—',
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function officialAssignments(Collection $eventIds): Collection
    {
        return $this->judgeAssignments($eventIds)->map(fn (array $row) => [
            'event'    => $row['event'],
            'official' => $row['judge'],
            'role'     => 'Judge',
            'item'     => $row['item'],
            'school'   => '—',
        ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function venueUtilization(Collection $eventIds): Collection
    {
        return FestSchedule::whereIn('event_id', $eventIds)
            ->get()
            ->groupBy(fn (FestSchedule $s) => $s->event_id.'|'.($s->stage ?? $s->stage_id ?? 'unknown'))
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'event' => FestEvent::find($first->event_id)?->title,
                    'stage' => $first->stage ?? '—',
                    'slots' => $group->count(),
                    'hours' => round($group->count() * 0.5, 1),
                ];
            })
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function festEventIds(string $sahodayaId, array $filters): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->when(! empty($filters['event_id']), fn ($q) => $q->where('id', $filters['event_id']))
            ->pluck('id');
    }

    /** @param  array<string, mixed>  $filters */
    private function registrationWindowStatus(string $sahodayaId, ?string $eventType, array $filters = []): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->when($eventType, fn ($q) => $q->where('event_type', $eventType))
            ->when(! empty($filters['event_id']), fn ($q) => $q->where('id', $filters['event_id']))
            ->withCount('registrations')
            ->orderByDesc('registration_open')
            ->get()
            ->map(fn (FestEvent $e) => [
                'event'               => $e->title,
                'type'                => $e->event_type,
                'status'              => $e->status,
                'registration_opens'  => $e->registration_open?->toDateString(),
                'registration_closes' => $e->registration_close?->toDateString(),
                'registrations'       => $e->registrations_count,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function freeVsPaidBreakdown(Collection $eventIds): Collection
    {
        return FestEvent::whereIn('id', $eventIds)
            ->with('items:id,event_id,fee_amount')
            ->get()
            ->map(function (FestEvent $e) {
                $items = $e->items;
                $free = $items->where(fn ($i) => (float) $i->fee_amount <= 0)->count();
                $paid = $items->where(fn ($i) => (float) $i->fee_amount > 0)->count();

                return [
                    'event'      => $e->title,
                    'free_items' => $free,
                    'paid_items' => $paid,
                    'total_fee'  => round($items->sum(fn ($i) => (float) $i->fee_amount), 2),
                ];
            });
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function markEntryStatus(Collection $eventIds): Collection
    {
        return FestEventItem::whereIn('event_id', $eventIds)
            ->get()
            ->map(function (FestEventItem $item) {
                $entered = FestMark::where('item_id', $item->id)->count();
                $total = max(1, FestRegistration::where('item_id', $item->id)->whereNotIn('status', ['withdrawn', 'rejected'])->count());

                return [
                    'event'         => FestEvent::find($item->event_id)?->title,
                    'item'          => $item->title,
                    'marks_entered' => $entered,
                    'total'         => $total - 1 + 1,
                    'pct'           => round($entered / $total * 100, 1).'%',
                ];
            });
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function unpublishedResults(Collection $eventIds): Collection
    {
        return FestEventItem::whereIn('event_id', $eventIds)
            ->get()
            ->groupBy('event_id')
            ->map(function ($items, $eventId) {
                $published = $items->whereNotNull('results_published_at')->count();
                $pending = $items->whereNull('results_published_at')->count();

                return [
                    'event'     => FestEvent::find($eventId)?->title,
                    'item'      => 'All items',
                    'published' => $published,
                    'pending'   => $pending,
                ];
            })
            ->values();
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function appealsRegister(Collection $eventIds): Collection
    {
        return FestAppeal::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'participant.registration.school:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (FestAppeal $a) => [
                'event'        => $a->event?->title,
                'school'       => $a->participant?->registration?->school?->name ?? '—',
                'item'         => (string) ($a->participant?->registration?->item_id ?? '—'),
                'status'       => $a->status,
                'outcome'      => $a->resolution_note ?? $a->status,
                'resolved_at'  => $a->resolved_at?->format('j M Y'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function certificateLog(Collection $eventIds): Collection
    {
        return Certificate::query()
            ->where('entity_type', FestParticipant::class)
            ->orderByDesc('generated_at')
            ->limit(500)
            ->get()
            ->map(fn (Certificate $c) => [
                'event'        => '—',
                'school'       => '—',
                'participant'  => (string) $c->entity_id,
                'cert_type'    => $c->cert_type,
                'generated_at' => $c->generated_at?->format('j M Y'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function idCardIssuedLog(Collection $eventIds): Collection
    {
        return FestParticipant::whereHas('registration', fn ($q) => $q->whereIn('event_id', $eventIds))
            ->where(fn ($q) => $q->whereNotNull('chest_no')->orWhereNotNull('item_registration_number'))
            ->with(['registration.event:id,title', 'registration.school:id,name', 'student:id,name'])
            ->limit(500)
            ->get()
            ->map(fn (FestParticipant $p) => [
                'event'      => $p->registration?->event?->title,
                'school'     => $p->registration?->school?->name,
                'participant'=> $p->student?->name,
                'id_type'    => $p->chest_no ? 'Chest No' : 'Item Reg ID',
                'issued_at'  => $p->updated_at?->format('j M Y'),
            ]);
    }

    private function levelRegistration(string $sahodayaId, string $eventType): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->where('event_type', $eventType)
            ->withCount('registrations')
            ->get()
            ->map(fn (FestEvent $e) => [
                'event'         => $e->title,
                'level'         => $e->level ?? 'local',
                'schools'       => FestRegistration::where('event_id', $e->id)->distinct('school_id')->count('school_id'),
                'registrations' => $e->registrations_count,
            ]);
    }

    private function levelPropagation(string $sahodayaId): Collection
    {
        return FestStateProgramPropagation::where('sahodaya_id', $sahodayaId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FestStateProgramPropagation $p) => [
                'program'    => $p->level_round ?? 'state',
                'schools'    => '—',
                'status'     => $p->tenant_event_id ? 'synced' : 'pending',
                'updated_at' => $p->updated_at?->format('j M Y'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function clusterSummary(Collection $eventIds): Collection
    {
        return $this->schoolPointsRanking($eventIds);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function topPerformers(Collection $eventIds, array $filters): Collection
    {
        return FestMark::whereIn('event_id', $eventIds)
            ->when(! empty($filters['event_id']), fn ($q) => $q->where('event_id', $filters['event_id']))
            ->with(['event:id,title', 'item:id,title', 'participant.registration.school:id,name', 'participant.student:id,name'])
            ->orderByDesc('score')
            ->limit(200)
            ->get()
            ->map(fn (FestMark $m) => [
                'event'       => $m->event?->title,
                'item'        => $m->item?->title,
                'participant' => $m->participant?->student?->name,
                'school'      => $m->participant?->registration?->school?->name,
                'mark'        => $m->mark,
                'rank'        => $m->rank,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function dailyScheduleBulletin(Collection $eventIds, array $filters): Collection
    {
        $date = $filters['from'] ?? now()->toDateString();

        return FestSchedule::whereIn('event_id', $eventIds)
            ->whereDate('scheduled_at', $date)
            ->with(['event:id,title', 'item:id,title'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(fn (FestSchedule $s) => [
                'event' => $s->event?->title,
                'date'  => $date,
                'item'  => $s->item?->title,
                'stage' => $s->stage ?? '—',
                'time'  => $s->scheduled_at?->format('H:i'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function cateringSummary(Collection $eventIds): Collection
    {
        return collect([[
            'event'  => FestEvent::find($eventIds->first())?->title ?? '—',
            'school' => '—',
            'orders' => 0,
            'amount' => 0,
        ]]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function itemFeeConfig(Collection $eventIds): Collection
    {
        return FestEventItem::whereIn('event_id', $eventIds)
            ->with('event:id,title')
            ->orderBy('display_order')
            ->get()
            ->map(fn (FestEventItem $i) => [
                'event'            => $i->event?->title,
                'item'             => $i->title,
                'fee_amount'       => (float) $i->fee_amount,
                'participant_type' => $i->participant_type,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function pendingApprovals(Collection $eventIds, array $filters): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->where('status', 'submitted')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', $filters['school_id']))
            ->with(['event:id,title', 'school:id,name', 'item:id,title'])
            ->orderByDesc('submitted_at')
            ->get()
            ->map(fn (FestRegistration $r) => [
                'event'        => $r->event?->title,
                'school'       => $r->school?->name,
                'item'         => $r->item?->title,
                'status'       => $r->status,
                'submitted_at' => $r->submitted_at?->format('j M Y'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function marksVerificationPending(Collection $eventIds): Collection
    {
        return $this->markEntryStatus($eventIds)->map(fn (array $row) => [
            'event'    => $row['event'],
            'item'     => $row['item'],
            'verified' => 0,
            'pending'  => $row['marks_entered'],
        ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function recordBreakLog(Collection $eventIds): Collection
    {
        return FestRecordBreak::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'item:id,title', 'participant.student:id,name'])
            ->orderByDesc('broken_at')
            ->get()
            ->map(fn (FestRecordBreak $b) => [
                'event'       => $b->event?->title,
                'item'        => $b->item?->title,
                'participant' => $b->participant?->student?->name,
                'old_record'  => $b->previous_value,
                'new_record'  => $b->new_value,
                'broken_at'   => $b->broken_at?->format('j M Y'),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function teamRoster(Collection $eventIds, array $filters): Collection
    {
        return FestRegistration::whereIn('event_id', $eventIds)
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('school_id', $filters['school_id']))
            ->with(['event:id,title', 'school:id,name', 'item:id,title', 'participants.student:id,name'])
            ->get()
            ->map(fn (FestRegistration $r) => [
                'event'        => $r->event?->title,
                'school'       => $r->school?->name,
                'item'         => $r->item?->title,
                'participants' => $r->participants->map(fn ($p) => $p->student?->name)->filter()->join(', '),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function scoreSheets(Collection $eventIds, array $filters): Collection
    {
        return $this->resultsByItem($eventIds, $filters);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function judgeScoreDetail(Collection $eventIds, array $filters): Collection
    {
        return FestMark::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'item:id,title', 'participant.student:id,name'])
            ->get()
            ->map(fn (FestMark $m) => [
                'event'       => $m->event?->title,
                'item'        => $m->item?->title,
                'judge'       => '—',
                'participant' => $m->participant?->student?->name,
                'score'       => $m->score,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function tabulationSheet(Collection $eventIds): Collection
    {
        return FestEventItem::whereIn('event_id', $eventIds)
            ->withCount('marks')
            ->get()
            ->map(fn (FestEventItem $i) => [
                'event'        => FestEvent::find($i->event_id)?->title,
                'item'         => $i->title,
                'participants' => $i->registrations()->count(),
                'avg_mark'     => round((float) FestMark::where('item_id', $i->id)->avg('score'), 2),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function rankListByItem(Collection $eventIds, array $filters): Collection
    {
        return $this->resultsByItem($eventIds, $filters);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    /** @param  array<string, mixed>  $filters */
    private function multiJudgeAverage(Collection $eventIds, array $filters): Collection
    {
        return $this->tabulationSheet($eventIds)->map(fn (array $row) => [
            'event'       => $row['event'],
            'item'        => $row['item'],
            'participant' => '—',
            'avg_score'   => $row['avg_mark'],
            'rank'        => '—',
        ]);
    }

    private function catalogCategories(string $sahodayaId): Collection
    {
        return FestCatalogItem::where('tenant_id', $sahodayaId)
            ->selectRaw('category, count(*) as items, sum(case when is_enabled = 1 then 1 else 0 end) as enabled')
            ->groupBy('category')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'items'    => (int) $row->items,
                'enabled'  => (int) $row->enabled,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function resultPublishLog(Collection $eventIds): Collection
    {
        return FestEventItem::whereIn('event_id', $eventIds)
            ->whereNotNull('results_published_at')
            ->with('event:id,title')
            ->orderByDesc('results_published_at')
            ->get()
            ->map(fn (FestEventItem $i) => [
                'event'        => $i->event?->title,
                'item'         => $i->title,
                'published_at' => $i->results_published_at?->format('j M Y H:i'),
                'published_by' => '—',
            ]);
    }

    private function stateSyncLog(string $sahodayaId): Collection
    {
        return FestStateProgramPropagation::where('sahodaya_id', $sahodayaId)
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (FestStateProgramPropagation $p) => [
                'event'     => FestEvent::find($p->tenant_event_id)?->title ?? '—',
                'action'    => $p->level_round ?? 'propagate',
                'items'     => '—',
                'status'    => $p->tenant_event_id ? 'synced' : 'pending',
                'synced_at' => $p->updated_at?->format('j M Y'),
            ]);
    }

    private function catalogMasterExport(string $sahodayaId): Collection
    {
        return FestCatalogItem::where('tenant_id', $sahodayaId)
            ->orderBy('display_order')
            ->get()
            ->map(fn (FestCatalogItem $i) => [
                'code'             => $i->item_code,
                'title'            => $i->title,
                'category'         => $i->category,
                'participant_type' => $i->participant_type,
                'fee'              => (float) $i->fee_amount,
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function judgeLoginActivity(Collection $eventIds): Collection
    {
        $userIds = FestJudgeAssignment::whereIn('event_id', $eventIds)->pluck('user_id')->unique();

        return User::whereIn('id', $userIds)
            ->get()
            ->map(fn (User $u) => [
                'judge'          => $u->name,
                'event'          => '—',
                'last_login_at'  => $u->last_login_at?->toDateTimeString() ?? 'Never',
                'assignments'    => FestJudgeAssignment::whereIn('event_id', $eventIds)->where('user_id', $u->id)->count(),
            ]);
    }

    /** @param  Collection<int, int|string>  $eventIds */
    private function eventMetrics(Collection $eventIds, int $suffix): Collection
    {
        return FestEvent::whereIn('id', $eventIds)->get()->map(fn (FestEvent $e) => [
            'event'  => $e->title,
            'metric' => 'Report KAL-'.$suffix,
            'value'  => FestRegistration::where('event_id', $e->id)->count(),
        ]);
    }
}
