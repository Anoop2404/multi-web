<?php

namespace App\Services\State\Fest;

use App\Models\State\StateAttendance;
use App\Models\State\StateConductAudit;
use App\Models\State\StateFestEvent;
use App\Models\State\StateFestMark;
use App\Models\State\StateFestRegistration;
use App\Models\State\StateJudgeAssignment;
use App\Models\State\StateJudgeScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 6 of the State Kalotsav module — conducting the event: attendance, judges, mark entry.
 *
 * Two rules run through all of it.
 *
 * An absent competitor cannot be scored. Marking someone absent and then entering a mark for them is
 * the commonest way a disputed result is created, so the mark is refused rather than quietly stored.
 *
 * Every change after the first is recorded. Attendance corrected from absent to present, or a mark
 * edited after entry, is exactly what an appeal turns on — so both append to state_conduct_audits
 * with the old value, the new one and who made the change.
 */
class StateConductService
{
    public const ATTENDANCE_STATUSES = ['present', 'absent', 'late', 'withdrawn', 'disqualified'];

    /** Statuses that mean the competitor did not compete, so no mark may exist. */
    public const NON_COMPETING = ['absent', 'withdrawn', 'disqualified'];

    public function __construct(private StateEventSettings $settings) {}

    /**
     * Mark attendance for one registration. Re-marking is a correction and is recorded as one.
     *
     * @param  array{status: string, reason?: ?string, user_id?: ?int, user_name?: ?string}  $data
     */
    public function markAttendance(StateFestEvent $event, StateFestRegistration $registration, array $data): StateAttendance
    {
        if (! in_array($data['status'], self::ATTENDANCE_STATUSES, true)) {
            throw ValidationException::withMessages(['status' => 'Unknown attendance status.']);
        }

        return DB::connection('state')->transaction(function () use ($event, $registration, $data) {
            $existing = StateAttendance::where('state_event_id', $event->id)
                ->where('registration_id', $registration->id)
                ->first();

            $previous = $existing?->status;

            $attendance = StateAttendance::updateOrCreate(
                ['state_event_id' => $event->id, 'registration_id' => $registration->id],
                [
                    'item_id' => $registration->item_id,
                    'item_code' => $registration->item_code,
                    // Attendance is taken per entry, not per person — a team reports together and
                    // is present or absent as one. The table requires a participant, so the entry is
                    // anchored to whoever leads it, falling back to its first competing member.
                    'participant_id' => $this->anchorParticipantId($registration),
                    'status' => $data['status'],
                    'marked_by' => $data['user_id'] ?? null,
                    'marked_at' => now(),
                ],
            );

            // Only a change is a correction; marking for the first time is just marking.
            if ($previous !== null && $previous !== $data['status']) {
                $this->audit($event, 'attendance', [
                    'registration_id' => $registration->id,
                    'item_id' => $registration->item_id,
                    'item_code' => $registration->item_code,
                    'value_from' => $previous,
                    'value_to' => $data['status'],
                    'reason' => $data['reason'] ?? null,
                ], $data);
            }

            return $attendance;
        });
    }

    /**
     * Mark a whole item at once — the normal case, since a stage marshal works down a list.
     *
     * @param  array<int, string>  $statuses  registration id => status
     * @return array{marked: int, corrected: int}
     */
    public function markAttendanceBulk(StateFestEvent $event, array $statuses, array $context = []): array
    {
        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->whereIn('id', array_keys($statuses))->get();

        $marked = 0;
        $corrected = 0;

        foreach ($registrations as $registration) {
            $before = StateAttendance::where('state_event_id', $event->id)
                ->where('registration_id', $registration->id)->value('status');

            $this->markAttendance($event, $registration, ['status' => $statuses[$registration->id]] + $context);

            $marked++;
            if ($before !== null && $before !== $statuses[$registration->id]) {
                $corrected++;
            }
        }

        return ['marked' => $marked, 'corrected' => $corrected];
    }

    public function assignJudge(StateFestEvent $event, string $itemId, ?string $itemCode, int $userId): StateJudgeAssignment
    {
        return StateJudgeAssignment::firstOrCreate([
            'state_event_id' => $event->id,
            'item_id' => $itemId,
            'user_id' => $userId,
        ], ['item_code' => $itemCode]);
    }

    public function unassignJudge(StateFestEvent $event, int $assignmentId): void
    {
        $assignment = StateJudgeAssignment::where('state_event_id', $event->id)->findOrFail($assignmentId);

        // A judge who has already scored cannot simply be removed: their scores are part of the
        // panel that produced a result, and dropping them silently changes every aggregate.
        $hasScores = StateJudgeScore::where('state_event_id', $event->id)
            ->where('item_id', $assignment->item_id)
            ->where('judge_user_id', $assignment->user_id)
            ->exists();

        if ($hasScores) {
            throw ValidationException::withMessages([
                'judge' => 'This judge has already entered scores. Remove the scores first, or leave the panel as it stands.',
            ]);
        }

        $assignment->delete();
    }

    /**
     * Record one judge's score for one participant.
     *
     * @param  array{score: float, grade?: ?string, notes?: ?string, user_id?: ?int, user_name?: ?string, reason?: ?string}  $data
     */
    public function enterJudgeScore(StateFestEvent $event, StateFestRegistration $registration, int $participantId, int $judgeUserId, array $data): StateJudgeScore
    {
        $this->assertScoringOpen($event);
        $this->assertCompeting($event, $registration);

        $settings = $this->markSettings($event);

        if ($data['score'] < $settings['min_score'] || $data['score'] > $settings['max_score']) {
            throw ValidationException::withMessages([
                'score' => "Score must be between {$settings['min_score']} and {$settings['max_score']}.",
            ]);
        }

        return DB::connection('state')->transaction(function () use ($event, $registration, $participantId, $judgeUserId, $data) {
            $existing = StateJudgeScore::where('state_event_id', $event->id)
                ->where('item_id', $registration->item_id)
                ->where('participant_id', $participantId)
                ->where('judge_user_id', $judgeUserId)
                ->first();

            $previous = $existing?->score;

            $score = StateJudgeScore::updateOrCreate(
                [
                    'state_event_id' => $event->id,
                    'item_id' => $registration->item_id,
                    'participant_id' => $participantId,
                    'judge_user_id' => $judgeUserId,
                ],
                [
                    'item_code' => $registration->item_code,
                    'score' => $data['score'],
                    'grade' => $data['grade'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ],
            );

            if ($previous !== null && (float) $previous !== (float) $data['score']) {
                $this->audit($event, 'mark', [
                    'registration_id' => $registration->id,
                    'participant_id' => $participantId,
                    'item_id' => $registration->item_id,
                    'item_code' => $registration->item_code,
                    'value_from' => (string) $previous,
                    'value_to' => (string) $data['score'],
                    'reason' => $data['reason'] ?? null,
                ], $data);
            }

            return $score;
        });
    }

    /**
     * Combine a panel's scores into one mark per participant.
     *
     * The averaging rules are the event's, not a constant: a three-judge panel usually averages all
     * three, a five-judge panel often drops the highest and lowest first. Dropping is skipped when
     * it would leave fewer than one score, so a two-judge panel configured to drop does not produce
     * nothing.
     *
     * @return array{aggregated: int, incomplete: list<string>}
     */
    public function aggregateItem(StateFestEvent $event, string $itemId): array
    {
        $this->assertScoringOpen($event);

        $settings = $this->markSettings($event);
        $expectedJudges = (int) $settings['judge_count'];

        $registrations = StateFestRegistration::where('state_event_id', $event->id)
            ->where('item_id', $itemId)->where('status', 'approved')->with('participants')->get();

        $scores = StateJudgeScore::where('state_event_id', $event->id)->where('item_id', $itemId)->get()
            ->groupBy('participant_id');

        $attendance = StateAttendance::where('state_event_id', $event->id)
            ->whereIn('registration_id', $registrations->pluck('id'))->pluck('status', 'registration_id');

        $aggregated = 0;
        $incomplete = [];

        foreach ($registrations as $registration) {
            // A competitor who did not compete gets no mark, however many judges scored them.
            if (in_array($attendance[$registration->id] ?? null, self::NON_COMPETING, true)) {
                continue;
            }

            foreach ($registration->participants as $participant) {
                if (! $participant->isCompeting()) {
                    continue;
                }

                $given = ($scores->get($participant->id) ?? collect())->pluck('score')->map(fn ($s) => (float) $s)->sort()->values();

                if ($given->isEmpty()) {
                    continue;
                }

                if ($expectedJudges > 0 && $given->count() < $expectedJudges) {
                    // Aggregated anyway — a panel that lost a judge still has to produce a result —
                    // but named, so the office decides whether to accept it.
                    $incomplete[] = $participant->student_name;
                }

                $final = $this->combine($given, $settings);

                StateFestMark::updateOrCreate(
                    [
                        'state_event_id' => $event->id,
                        'registration_id' => $registration->id,
                        'participant_id' => $participant->id,
                    ],
                    [
                        'score' => $final,
                        'grade' => app(\App\Services\State\StateGradePointService::class)->resolveGradeFromScore($event, $final),
                        'status' => 'aggregated',
                    ],
                );

                $aggregated++;
            }
        }

        return ['aggregated' => $aggregated, 'incomplete' => array_values(array_unique($incomplete))];
    }

    /** @param  \Illuminate\Support\Collection<int, float>  $given  ascending */
    private function combine($given, array $settings): float
    {
        $values = $given;

        // Only drop when something is left to average.
        if (($settings['drop_high_low'] ?? false) && $values->count() >= 3) {
            $values = $values->slice(1, $values->count() - 2)->values();
        }

        $final = $settings['averaging'] === 'best'
            ? $values->max()
            : $values->avg();

        return round((float) $final, (int) $settings['decimals']);
    }

    /** @return array<string, mixed> */
    public function markSettings(StateFestEvent $event): array
    {
        $stored = $this->settings->all($event);

        return [
            'judge_count'   => $stored['judge_count'] ?? 3,
            'averaging'     => $stored['averaging'] ?? 'mean',
            'drop_high_low' => (bool) ($stored['drop_high_low'] ?? false),
            'decimals'      => $stored['decimals'] ?? 2,
            'min_score'     => $stored['min_score'] ?? 0,
            'max_score'     => $stored['max_score'] ?? 100,
        ];
    }

    private function assertScoringOpen(StateFestEvent $event): void
    {
        if ($event->scoring_locked) {
            throw ValidationException::withMessages([
                'score' => 'Scoring is locked for this event. Unlock it in Settings before entering or changing marks.',
            ]);
        }
    }

    private function assertCompeting(StateFestEvent $event, StateFestRegistration $registration): void
    {
        $status = StateAttendance::where('state_event_id', $event->id)
            ->where('registration_id', $registration->id)->value('status');

        if (in_array($status, self::NON_COMPETING, true)) {
            throw ValidationException::withMessages([
                'score' => "This entry is marked {$status}. Correct the attendance before entering a score.",
            ]);
        }
    }

    private function anchorParticipantId(StateFestRegistration $registration): ?int
    {
        $participants = $registration->relationLoaded('participants')
            ? $registration->participants
            : $registration->participants()->get();

        return $participants->firstWhere('is_leader', true)?->id
            ?? $participants->first(fn ($p) => $p->isCompeting())?->id
            ?? $participants->first()?->id;
    }

    /** @param array<string, mixed> $row */
    private function audit(StateFestEvent $event, string $kind, array $row, array $context): void
    {
        StateConductAudit::create($row + [
            'state_event_id' => $event->id,
            'state_id' => $event->state_id,
            'kind' => $kind,
            'changed_by_user_id' => $context['user_id'] ?? null,
            'changed_by_name' => $context['user_name'] ?? null,
            'created_at' => now(),
        ]);
    }
}
