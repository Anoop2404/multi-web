<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestMark;
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
}
