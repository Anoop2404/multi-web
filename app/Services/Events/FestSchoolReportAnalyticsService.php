<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;

class FestSchoolReportAnalyticsService
{
    public function __construct(
        public FestEvent $event,
        public string $schoolId,
    ) {}

    /** @return array<string, mixed>|null */
    public function feeSummary(): ?array
    {
        $fee = FestSchoolEventFee::where('event_id', $this->event->id)
            ->where('school_id', $this->schoolId)
            ->with('feeReceipt')
            ->first();

        if (! $fee) {
            return null;
        }

        return [
            'total_due'  => (float) $fee->total_due,
            'paid'       => (float) ($fee->feeReceipt?->amount ?? 0),
            'status'     => $fee->status,
            'receipt_no' => $fee->feeReceipt?->receipt_number,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function disciplineParticipationRows(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->disciplineRegistrationRows($this->schoolId);
    }

    /** @return array{participant: list<array<string, mixed>>, stage: list<array<string, mixed>>} */
    public function scheduleClashes(): array
    {
        return (new FestReportService($this->event))->scheduleClashRows($this->schoolId);
    }

    /** @return array{summary: array<string, int>, rows: list<array<string, mixed>>} */
    public function markEntryStatus(): array
    {
        return (new FestReportService($this->event))->markEntryStatusSummary($this->schoolId);
    }

    /** @return list<array<string, mixed>> */
    public function headWiseSummary(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->headWiseSummary($this->schoolId);
    }

    /** @return list<array<string, mixed>> */
    public function headWiseParticipantRows(?int $headId = null): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->headWiseParticipantRows($headId, $this->schoolId);
    }

    /** @return array{gold: int, silver: int, bronze: int, total_score: float, items: list<array<string, mixed>>} */
    public function resultsSummary(): array
    {
        $marks = FestMark::where('event_id', $this->event->id)
            ->whereNotNull('position')
            ->whereHas('participant.registration', fn ($q) => $q
                ->where('school_id', $this->schoolId)
                ->where('status', 'approved'))
            ->with(['item:id,title', 'participant.student:id,name', 'participant.teacher:id,name'])
            ->get();

        $items = $marks->map(fn ($m) => [
            'item'     => $m->item?->title,
            'participant' => $m->participant?->student?->name ?? $m->participant?->teacher?->name,
            'position' => $m->position,
            'grade'    => $m->grade,
            'score'    => $m->score,
        ])->values()->all();

        return [
            'gold'         => $marks->where('position', 1)->count(),
            'silver'       => $marks->where('position', 2)->count(),
            'bronze'       => $marks->where('position', 3)->count(),
            'total_score'  => (float) $marks->sum('score'),
            'items'        => $items,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function schoolItemRegistrationRows(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->itemRegistrationRows($this->schoolId);
    }

    /** @param list<array<string, mixed>> $rows */
    public function itemRegistrationTotals(array $rows): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->itemRegistrationTotals($rows);
    }

    /** @return list<array<string, mixed>> */
    public function headRegistrationSummary(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->headRegistrationSummary($this->schoolId);
    }

    /** @return list<array<string, mixed>> */
    public function assignmentCompletenessRows(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->assignmentCompletenessRows($this->schoolId);
    }

    /** @param list<array<string, mixed>> $rows */
    public function assignmentCompletenessTotals(array $rows): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->assignmentCompletenessTotals($rows);
    }

    /** @return list<array<string, mixed>> */
    public function numberingRegisterRows(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->numberingRegisterRows($this->schoolId);
    }

    /** @return list<array<string, mixed>> */
    public function pendingApprovalRows(): array
    {
        return app(FestEventReportAnalyticsService::class, ['event' => $this->event])
            ->pendingApprovalRows($this->schoolId);
    }

    /** @return list<array<string, mixed>> */
    public function attendanceRows(?int $headId = null, ?int $itemId = null): array
    {
        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->where('school_id', $this->schoolId)
                ->active()
                ->when($itemId, fn ($q2) => $q2->where('item_id', $itemId))
                ->when($headId, fn ($q2) => $q2->whereHas(
                    'item',
                    fn ($i) => $i->where('head_id', $headId),
                )))
            ->with([
                'student:id,name,reg_no,photo,tenant_id',
                'student.schoolClass:id,name',
                'registration.item:id,title,head_id',
                'registration.item.head:id,name',
            ])
            ->orderBy('student_id')
            ->get();

        $byStudent = [];
        foreach ($participants as $p) {
            if (! $p->student) {
                continue;
            }
            $sid = $p->student_id;
            $byStudent[$sid] ??= [
                'student_id' => $sid,
                'student'    => $p->student->name,
                'reg_no'     => $p->student->reg_no,
                'class'      => $p->student->schoolClass?->name,
                'photo_url'  => $p->student->photoUrl(),
                'fest_id'    => $p->level_registration_number,
                'chest_no'   => $p->chest_no,
                'items'      => [],
            ];
            if ($p->level_registration_number) {
                $byStudent[$sid]['fest_id'] = $p->level_registration_number;
            }
            if ($p->chest_no) {
                $byStudent[$sid]['chest_no'] = $p->chest_no;
            }
            $byStudent[$sid]['items'][] = [
                'item_id'   => $p->registration?->item_id,
                'head_id'   => $p->registration?->item?->head_id,
                'head_name' => $p->registration?->item?->head?->name,
                'item'      => $p->registration?->item?->title,
                'item_reg'  => $p->item_registration_number,
                'chest_no'  => $p->chest_no,
            ];
        }

        return array_values($byStudent);
    }

    /** @return array{published: bool, gold: int, silver: int, bronze: int, total_score: float, items: list<array<string, mixed>>} */
    public function publishedResultsRows(?int $headId = null, ?int $itemId = null): array
    {
        $eventPublished = (bool) $this->event->results_published;
        $anyItemPublished = \App\Models\FestEventItem::query()
            ->where('event_id', $this->event->id)
            ->whereNotNull('results_published_at')
            ->exists();

        $marks = FestMark::where('event_id', $this->event->id)
            ->where(fn ($q) => $q->whereNotNull('position')->orWhereNotNull('score')->orWhereNotNull('grade'))
            ->whereHas('participant.registration', fn ($q) => $q
                ->where('school_id', $this->schoolId)
                ->where('status', 'approved')
                ->when($itemId, fn ($q2) => $q2->where('item_id', $itemId))
                ->when($headId, fn ($q2) => $q2->whereHas(
                    'item',
                    fn ($i) => $i->where('head_id', $headId),
                )))
            ->when(! $eventPublished, fn ($q) => $q->whereHas(
                'item',
                fn ($i) => $i->whereNotNull('results_published_at'),
            ))
            ->with([
                'item:id,title,head_id,results_published_at',
                'item.head:id,name',
                'participant.student:id,name,reg_no,photo,tenant_id',
                'participant.student.schoolClass:id,name',
                'participant.teacher:id,name,reg_no',
            ])
            ->get();

        $items = $marks->map(fn ($m) => [
            'head_id'     => $m->item?->head_id,
            'head_name'   => $m->item?->head?->name,
            'item_id'     => $m->item_id,
            'item'        => $m->item?->title,
            'student_id'  => $m->participant?->student_id,
            'participant' => $m->participant?->student?->name ?? $m->participant?->teacher?->name,
            'reg_no'      => $m->participant?->student?->reg_no ?? $m->participant?->teacher?->reg_no,
            'class'       => $m->participant?->student?->schoolClass?->name,
            'photo_url'   => $m->participant?->student?->photoUrl(),
            'fest_id'     => $m->participant?->level_registration_number,
            'chest_no'    => $m->participant?->chest_no,
            'position'    => $m->position,
            'grade'       => $m->grade,
            'score'       => $m->score,
        ])->values()->all();

        return [
            'published'   => $eventPublished || $anyItemPublished,
            'event_published' => $eventPublished,
            'gold'        => $marks->where('position', 1)->count(),
            'silver'      => $marks->where('position', 2)->count(),
            'bronze'      => $marks->where('position', 3)->count(),
            'total_score' => (float) $marks->sum('score'),
            'items'       => $items,
        ];
    }

    /** @return array{summary: array<string, int>, rows: list<array<string, mixed>>} */
    public function resultsPublishStatus(): array
    {
        $all = collect(app(FestItemResultsService::class)->itemSummaries($this->event));

        $schoolItemIds = FestRegistration::query()
            ->where('event_id', $this->event->id)
            ->where('school_id', $this->schoolId)
            ->where('status', 'approved')
            ->whereNotNull('item_id')
            ->distinct()
            ->pluck('item_id')
            ->all();

        $rows = $all
            ->filter(fn (array $r) => in_array($r['item_id'], $schoolItemIds, true))
            ->map(fn (array $r) => [
                'item_id'              => $r['item_id'],
                'head_id'              => $r['head_id'],
                'head_name'            => $r['head_name'],
                'title'                => $r['title'],
                'item_code'            => $r['item_code'] ?? null,
                'age_group'            => $r['age_group'] ?? null,
                'class_group'          => $r['class_group'] ?? null,
                'sport_discipline'     => $r['sport_discipline'] ?? null,
                'competition_start'    => $r['competition_start'],
                'competition_end'      => $r['competition_end'],
                'marks_ready'          => $r['marks_ready'],
                'marks_entered'        => $r['marks_entered'],
                'marks_pending'        => $r['marks_pending'],
                'performers'           => $r['performers'],
                'results_published'    => $r['results_published'],
                'results_published_at' => $r['results_published_at'],
            ])
            ->values()
            ->all();

        return [
            'summary' => [
                'items'     => count($rows),
                'published' => collect($rows)->where('results_published', true)->count(),
                'pending'   => collect($rows)->where('results_published', false)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /** @return array{item: array<string, mixed>, participants: list<array<string, mixed>>} */
    public function itemParticipantDetails(int $itemId): array
    {
        $item = \App\Models\FestEventItem::query()
            ->where('event_id', $this->event->id)
            ->where('id', $itemId)
            ->with('head:id,name')
            ->firstOrFail();

        $participants = FestParticipant::query()
            ->whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->where('school_id', $this->schoolId)
                ->where('item_id', $itemId)
                ->active())
            ->with([
                'student:id,name,reg_no,photo,tenant_id,gender',
                'student.schoolClass:id,name',
                'teacher:id,name,reg_no',
                'registration:id,status',
            ])
            ->orderBy('participant_role')
            ->orderBy('id')
            ->get()
            ->map(fn (FestParticipant $p) => [
                'student_id' => $p->student_id,
                'name'       => $p->student?->name ?? $p->teacher?->name,
                'reg_no'     => $p->student?->reg_no ?? $p->teacher?->reg_no,
                'class'      => $p->student?->schoolClass?->name,
                'photo_url'  => $p->student?->photoUrl(),
                'fest_id'    => $p->level_registration_number,
                'item_reg'   => $p->item_registration_number,
                'chest_no'   => $p->chest_no,
                'status'     => $p->registration?->status,
            ])
            ->values()
            ->all();

        return [
            'item' => [
                'id'        => $item->id,
                'title'     => $item->title,
                'head_name' => $item->head?->name,
                'age_group' => $item->age_group,
            ],
            'participants' => $participants,
        ];
    }
}
