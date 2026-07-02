<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestParticipant;
use App\Models\FestSchedule;
use App\Models\Student;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FestScheduleConflictService
{
    public function __construct(public FestEvent $event) {}

    /** @return list<array<string, mixed>> */
    public function detectAll(?string $schoolId = null): array
    {
        $schedules = FestSchedule::where('event_id', $this->event->id)
            ->whereNotNull('scheduled_at')
            ->with(['item', 'participant.student', 'participant.registration'])
            ->get();

        $clashes = [];
        $seen = [];

        foreach ($schedules as $s1) {
            $start1 = $s1->scheduled_at;
            $end1 = $start1?->copy()->addMinutes($s1->item?->duration_minutes ?? 60);
            if (! $start1) {
                continue;
            }

            $students1 = $this->studentIdsForSchedule($s1);

            foreach ($schedules as $s2) {
                if ($s2->id <= $s1->id || ! $s2->scheduled_at) {
                    continue;
                }

                $start2 = $s2->scheduled_at;
                $end2 = $start2->copy()->addMinutes($s2->item?->duration_minutes ?? 60);

                if ($start1->greaterThanOrEqualTo($end2) || $start2->greaterThanOrEqualTo($end1)) {
                    continue;
                }

                $students2 = $this->studentIdsForSchedule($s2);
                $common = $students1->intersect($students2);

                foreach ($common as $studentId) {
                    $key = "{$studentId}-{$s1->id}-{$s2->id}";
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;

                    $student = Student::find($studentId);
                    $entrySchoolId = $student?->tenant_id;

                    if ($schoolId !== null && $entrySchoolId !== $schoolId) {
                        continue;
                    }

                    $schoolName = Tenant::where('id', $entrySchoolId)->value('name') ?? '—';

                    $clashes[] = [
                        'student_name' => $student?->name ?? "Student #{$studentId}",
                        'school_name'  => $schoolName,
                        'school_id'    => $entrySchoolId,
                        'event1'       => $s1->item?->title ?? "Item #{$s1->item_id}",
                        'event2'       => $s2->item?->title ?? "Item #{$s2->item_id}",
                        'time'         => $start1->format('d M H:i').' – '.$start2->format('d M H:i'),
                        'start_time1'  => $start1->timestamp,
                    ];
                }
            }
        }

        return $clashes;
    }

    /** @return list<array<string, mixed>> */
    public function detectStageConflicts(): array
    {
        $schedules = FestSchedule::where('event_id', $this->event->id)
            ->whereNotNull('scheduled_at')
            ->with(['item', 'festStage.venue'])
            ->get()
            ->filter(fn (FestSchedule $schedule) => $schedule->stage_id || filled($schedule->stage));

        $conflicts = [];
        $seen = [];

        foreach ($schedules as $s1) {
            $start1 = $s1->scheduled_at;
            if (! $start1) {
                continue;
            }

            $end1 = $start1->copy()->addMinutes($s1->item?->duration_minutes ?? 60);
            $stageKey1 = $this->stageKey($s1);

            foreach ($schedules as $s2) {
                if ($s2->id <= $s1->id || ! $s2->scheduled_at) {
                    continue;
                }

                if ($this->stageKey($s2) !== $stageKey1) {
                    continue;
                }

                $start2 = $s2->scheduled_at;
                $end2 = $start2->copy()->addMinutes($s2->item?->duration_minutes ?? 60);

                if ($start1->greaterThanOrEqualTo($end2) || $start2->greaterThanOrEqualTo($end1)) {
                    continue;
                }

                $pairKey = min($s1->id, $s2->id).'-'.max($s1->id, $s2->id);
                if (isset($seen[$pairKey])) {
                    continue;
                }
                $seen[$pairKey] = true;

                $conflicts[] = [
                    'stage'  => $s1->festStage?->name ?? $s1->stage ?? 'Stage',
                    'venue'  => $s1->festStage?->venue?->name,
                    'item1'  => $s1->item?->title ?? "Item #{$s1->item_id}",
                    'item2'  => $s2->item?->title ?? "Item #{$s2->item_id}",
                    'time'   => $start1->format('d M H:i').' – '.$start2->format('d M H:i'),
                ];
            }
        }

        return $conflicts;
    }

    /** @return list<array<string, mixed>> */
    public function allConflicts(?string $schoolId = null): array
    {
        return array_merge($this->detectAll($schoolId), $this->detectStageConflicts());
    }

    private function stageKey(FestSchedule $schedule): string
    {
        if ($schedule->stage_id) {
            return 'id:'.$schedule->stage_id;
        }

        return 'text:'.strtolower(trim($schedule->stage ?? ''));
    }

    private function studentIdsForSchedule(FestSchedule $schedule): Collection
    {
        if ($schedule->participant_id) {
            $p = $schedule->participant;
            if ($p?->student_id) {
                return collect([$p->student_id]);
            }

            $reg = $p?->registration;
            if ($reg) {
                return FestParticipant::where('registration_id', $reg->id)
                    ->whereNotNull('student_id')
                    ->pluck('student_id');
            }
        }

        if ($schedule->item_id) {
            return FestParticipant::whereHas('registration', fn ($q) => $q
                ->where('event_id', $this->event->id)
                ->where('item_id', $schedule->item_id)
                ->where('status', 'approved'))
                ->whereNotNull('student_id')
                ->pluck('student_id');
        }

        return collect();
    }
}
