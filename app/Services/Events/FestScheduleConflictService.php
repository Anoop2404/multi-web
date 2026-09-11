<?php

namespace App\Services\Events;

use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestParticipant;
use App\Models\FestRegistration;
use App\Models\FestSchedule;
use App\Models\Student;
use App\Models\Tenant;
use App\Support\FestClassGroupScheme;
use App\Support\FestItemCategoryLabel;
use App\Support\FestTeamSquadRules;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FestScheduleConflictService
{
    /** @var array<string, string> */
    private array $classGroupLabels;

    public function __construct(public FestEvent $event)
    {
        $this->classGroupLabels = FestClassGroupScheme::labels(null, $event->rootEvent());
    }

    /** @return array{title: string, category: ?string, gender: ?string, type: string} */
    private function itemMeta(?FestEventItem $item, ?int $itemId): array
    {
        return [
            'title'    => $item?->title ?? "Item #{$itemId}",
            'category' => FestItemCategoryLabel::resolve($item, $this->classGroupLabels),
            'gender'   => $this->genderLabel($item?->gender),
            'type'     => FestTeamSquadRules::isMultiPerson($item?->participant_type) ? 'Group' : 'Individual',
        ];
    }

    private function genderLabel(?string $gender): ?string
    {
        return match (strtolower((string) $gender)) {
            'male', 'm', 'boy', 'boys' => 'Boys',
            'female', 'f', 'girl', 'girls' => 'Girls',
            'mixed', 'common' => 'Mixed',
            default => null,
        };
    }

    /** @return list<array<string, mixed>> */
    public function detectAll(?string $schoolId = null): array
    {
        $schedules = FestSchedule::where('event_id', $this->event->id)
            ->whereNotNull('scheduled_at')
            ->with([
                'item' => fn ($q) => $q->withCount(['registrations' => fn ($r) => $r->whereIn('status', FestRegistration::ACTIVE_STATUSES)]),
                'participant.student', 'participant.registration',
            ])
            ->get();

        $clashes = [];
        $seen = [];

        foreach ($schedules as $s1) {
            $start1 = $s1->scheduled_at;
            $end1 = $start1?->copy()->addMinutes($s1->item?->estimatedDurationMinutes() ?? 60);
            if (! $start1) {
                continue;
            }

            $students1 = $this->studentIdsForSchedule($s1);

            foreach ($schedules as $s2) {
                if ($s2->id <= $s1->id || ! $s2->scheduled_at) {
                    continue;
                }

                $start2 = $s2->scheduled_at;
                $end2 = $start2->copy()->addMinutes($s2->item?->estimatedDurationMinutes() ?? 60);

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
                    $item1 = $this->itemMeta($s1->item, $s1->item_id);
                    $item2 = $this->itemMeta($s2->item, $s2->item_id);

                    $clashes[] = [
                        'student_id'      => $studentId,
                        'student_name'    => $student?->name ?? "Student #{$studentId}",
                        'school_name'     => $schoolName,
                        'school_id'       => $entrySchoolId,
                        'event1'          => $item1['title'],
                        'event2'          => $item2['title'],
                        'item1_id'        => $s1->item_id,
                        'item2_id'        => $s2->item_id,
                        'item1_category'  => $item1['category'],
                        'item2_category'  => $item2['category'],
                        'item1_gender'    => $item1['gender'],
                        'item2_gender'    => $item2['gender'],
                        'item1_type'      => $item1['type'],
                        'item2_type'      => $item2['type'],
                        'item1_time'      => $start1->format('d M H:i'),
                        'item2_time'      => $start2->format('d M H:i'),
                        'time'            => $start1->format('d M H:i').' – '.$start2->format('d M H:i'),
                        'start_time1'     => $start1->timestamp,
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
            ->with([
                'item' => fn ($q) => $q->withCount(['registrations' => fn ($r) => $r->whereIn('status', FestRegistration::ACTIVE_STATUSES)]),
                'festStage.venue',
            ])
            ->get()
            ->filter(fn (FestSchedule $schedule) => $schedule->stage_id || filled($schedule->stage));

        $conflicts = [];
        $seen = [];

        foreach ($schedules as $s1) {
            $start1 = $s1->scheduled_at;
            if (! $start1) {
                continue;
            }

            $end1 = $start1->copy()->addMinutes($s1->item?->estimatedDurationMinutes() ?? 60);
            $stageKey1 = $this->stageKey($s1);

            foreach ($schedules as $s2) {
                if ($s2->id <= $s1->id || ! $s2->scheduled_at) {
                    continue;
                }

                if ($this->stageKey($s2) !== $stageKey1) {
                    continue;
                }

                $start2 = $s2->scheduled_at;
                $end2 = $start2->copy()->addMinutes($s2->item?->estimatedDurationMinutes() ?? 60);

                if ($start1->greaterThanOrEqualTo($end2) || $start2->greaterThanOrEqualTo($end1)) {
                    continue;
                }

                $pairKey = min($s1->id, $s2->id).'-'.max($s1->id, $s2->id);
                if (isset($seen[$pairKey])) {
                    continue;
                }
                $seen[$pairKey] = true;

                $item1 = $this->itemMeta($s1->item, $s1->item_id);
                $item2 = $this->itemMeta($s2->item, $s2->item_id);

                $conflicts[] = [
                    'stage'          => $s1->festStage?->name ?? $s1->stage ?? 'Stage',
                    'venue'          => $s1->festStage?->venue?->name,
                    'item1'          => $item1['title'],
                    'item2'          => $item2['title'],
                    'item1_id'       => $s1->item_id,
                    'item2_id'       => $s2->item_id,
                    'item1_category' => $item1['category'],
                    'item2_category' => $item2['category'],
                    'item1_gender'   => $item1['gender'],
                    'item2_gender'   => $item2['gender'],
                    'item1_type'     => $item1['type'],
                    'item2_type'     => $item2['type'],
                    'item1_time'     => $start1->format('d M H:i'),
                    'item2_time'     => $start2->format('d M H:i'),
                    'time'           => $start1->format('d M H:i').' – '.$start2->format('d M H:i'),
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
