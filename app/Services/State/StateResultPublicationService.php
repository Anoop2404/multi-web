<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class StateResultPublicationService
{
    public function __construct(private StateGradePointService $gradePoints) {}

    /** @return array{items: int, marks: int} */
    public function publish(StateFestEvent $event): array
    {
        StateEventLifecycleGate::allowPublishResults($event);

        return DB::connection('state')->transaction(function () use ($event) {
            $lockedEvent = StateFestEvent::whereKey($event->id)->lockForUpdate()->firstOrFail();
            StateEventLifecycleGate::allowPublishResults($lockedEvent);

            $marks = StateFestMark::where('state_event_id', $lockedEvent->id)
                ->whereHas('registration', fn ($query) => $query->where('status', 'approved'))
                ->with('registration')
                ->get();

            $approvedRegistrationCount = $lockedEvent->registrations()
                ->where('status', 'approved')
                ->count();

            if ($approvedRegistrationCount === 0) {
                throw new HttpException(422, 'No approved State participants are available for result publication.');
            }

            $scoredRegistrationIds = $marks->whereNotNull('score')->pluck('registration_id')->filter()->unique();
            if ($scoredRegistrationIds->count() < $approvedRegistrationCount) {
                throw new HttpException(422, "State mark entry is incomplete ({$scoredRegistrationIds->count()}/{$approvedRegistrationCount}).");
            }

            $groups = $marks->groupBy(fn (StateFestMark $mark) => (string) ($mark->registration?->item_id ?: $mark->registration?->item_code));

            foreach ($groups as $itemMarks) {
                $ordered = $itemMarks->sortByDesc(fn (StateFestMark $mark) => (float) $mark->score)->values();
                $previousScore = null;
                $previousPosition = 0;

                foreach ($ordered as $index => $mark) {
                    $score = (float) $mark->score;
                    $position = $previousScore !== null && $score === $previousScore
                        ? $previousPosition
                        : $index + 1;
                    $grade = $mark->grade ?: $this->gradePoints->resolveGradeFromScore($lockedEvent, $score);

                    $mark->update([
                        'position' => $position,
                        'grade' => $grade,
                        'points' => $this->gradePoints->pointsForGradePosition(
                            $lockedEvent,
                            $grade,
                            $position,
                            in_array($mark->registration?->meta['participant_type'] ?? null, ['group', 'team'], true)
                                || $mark->registration?->participants()->count() > 1,
                        ),
                        'status' => 'published',
                    ]);

                    $previousScore = $score;
                    $previousPosition = $position;
                }
            }

            $lockedEvent->update([
                'results_published' => true,
                'scoring_locked' => true,
                'status' => 'completed',
            ]);

            return ['items' => $groups->count(), 'marks' => $marks->count()];
        });
    }

    /** @return list<array{school_id: string, school_name: string, points: int, firsts: int, seconds: int, thirds: int}> */
    public function schoolRankings(StateFestEvent $event): array
    {
        if (! $event->results_published) {
            return [];
        }

        return StateFestMark::where('state_event_id', $event->id)
            ->where('status', 'published')
            ->with('registration')
            ->get()
            ->groupBy(fn (StateFestMark $mark) => $mark->registration?->school_id ?? 'unknown')
            ->map(function ($marks, string $schoolId) {
                $registration = $marks->first()?->registration;

                return [
                    'school_id' => $schoolId,
                    'school_name' => $registration?->school_name ?: $schoolId,
                    'points' => (int) $marks->sum('points'),
                    'firsts' => $marks->where('position', 1)->count(),
                    'seconds' => $marks->where('position', 2)->count(),
                    'thirds' => $marks->where('position', 3)->count(),
                ];
            })
            ->sortBy([
                ['points', 'desc'],
                ['firsts', 'desc'],
                ['seconds', 'desc'],
                ['thirds', 'desc'],
                ['school_name', 'asc'],
            ])
            ->values()
            ->all();
    }
}
