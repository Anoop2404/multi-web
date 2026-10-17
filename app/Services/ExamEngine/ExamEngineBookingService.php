<?php

namespace App\Services\ExamEngine;

use App\Models\ExamEngineBooking;
use App\Models\McqExam;
use App\Models\McqRegistration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Platform capacity for engine exams (plan §6, §9). Every Sahodaya schedules its own exams, so
 * each engine exam books its live window (waiting room → last deadline + grace) with its
 * candidate count; overlapping bookings are summed to refuse overbooking and to size scale-out.
 */
class ExamEngineBookingService
{
    /** Students one extra 4-vCPU engine node carries (plan §9.2 starting rule; calibrate with loadgen). */
    public const STUDENTS_PER_EXTRA_NODE = 20000;

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    public function window(McqExam $exam): array
    {
        $settings = ExamEngineRun::settings($exam);
        $start = CarbonImmutable::parse($exam->scheduled_at);
        $maxExtra = (int) McqRegistration::where('exam_id', $exam->id)->max('extra_time_minutes')
            + (int) ($exam->settings_json['engine_extended_minutes'] ?? 0);
        $lateEntry = $settings['timing'] === 'individual' ? (int) $settings['late_entry_minutes'] : 0;

        return [
            $start->subMinutes((int) $settings['waiting_room_minutes']),
            $start->addMinutes((int) ($exam->duration_minutes ?: 60) + $lateEntry + $maxExtra)->addSeconds((int) $settings['grace_seconds']),
        ];
    }

    public function expectedCount(McqExam $exam): int
    {
        return McqRegistration::where('exam_id', $exam->id)->active()->where('approval_status', 'approved')->count();
    }

    /** Creates or refreshes the exam's booking. Engine exams without a date get no booking. */
    public function sync(McqExam $exam, ?string $status = null): ?ExamEngineBooking
    {
        $existing = ExamEngineBooking::where('tenant_id', $exam->tenant_id)->where('exam_id', $exam->id)->first();

        if (! ExamEngineRun::usesEngine($exam) || ! $exam->scheduled_at || in_array($exam->status, ['cancelled', 'draft'], true)) {
            $existing?->update(['status' => 'cancelled']);

            return $existing;
        }

        [$start, $end] = $this->window($exam);

        return ExamEngineBooking::updateOrCreate(
            ['tenant_id' => $exam->tenant_id, 'exam_id' => $exam->id],
            [
                'title'          => $exam->title,
                'live_starts_at' => $start,
                'live_ends_at'   => $end,
                'expected_count' => $this->expectedCount($exam),
                'entry_mode'     => ExamEngineRun::settings($exam)['entry_mode'],
                'status'         => $status ?? ($existing && $existing->status !== 'cancelled' ? $existing->status : 'booked'),
            ],
        );
    }

    /** Peak number of students booked at the same time in [start, end), excluding one exam. */
    public function peakLoad(CarbonInterface $start, CarbonInterface $end, ?string $exceptTenantId = null, ?int $exceptExamId = null): int
    {
        $bookings = $this->overlapping($start, $end)
            ->reject(fn (ExamEngineBooking $b) => $b->tenant_id === $exceptTenantId && $b->exam_id === $exceptExamId);

        return $this->peak($bookings, $start, $end);
    }

    /** Refuses arming when this exam would push any moment of its window over platform capacity. */
    public function assertCapacity(McqExam $exam): void
    {
        [$start, $end] = $this->window($exam);
        $others = $this->peakLoad($start, $end, $exam->tenant_id, $exam->id);
        $mine = $this->expectedCount($exam);
        $capacity = (int) config('exam_engine.capacity');

        if ($others + $mine > $capacity) {
            throw ValidationException::withMessages(['engine' => sprintf(
                'Platform capacity for this time slot is %s students and %s are already booked; this exam adds %s. Pick another time or ask the platform team to raise capacity.',
                number_format($capacity), number_format($others), number_format($mine),
            )]);
        }
    }

    /**
     * Slots with how many students are live at once and how many engine nodes that needs
     * (consumed by exam-engine/deploy/scripts/plan-and-scale.sh).
     *
     * @return list<array{starts_at: string, ends_at: string, students: int, nodes: int, bookings: list<array<string, mixed>>}>
     */
    public function plan(CarbonInterface $from, CarbonInterface $to): array
    {
        $bookings = $this->overlapping($from, $to)->sortBy('live_starts_at')->values();
        $slots = [];

        // Group bookings into clusters of overlapping windows; each cluster is one scale-out slot.
        foreach ($bookings as $booking) {
            $last = array_key_last($slots);
            if ($last !== null && $booking->live_starts_at->lt($slots[$last]['end'])) {
                $slots[$last]['items'][] = $booking;
                $slots[$last]['end'] = $booking->live_ends_at->max($slots[$last]['end']);
            } else {
                $slots[] = ['start' => $booking->live_starts_at, 'end' => $booking->live_ends_at, 'items' => [$booking]];
            }
        }

        return array_map(function (array $slot) {
            $items = collect($slot['items']);
            $students = $this->peak($items, $slot['start'], $slot['end']);
            $passwordOnly = $items->contains(fn (ExamEngineBooking $b) => $b->entry_mode === 'portal_password');

            return [
                'starts_at' => $slot['start']->copy()->utc()->toIso8601String(),
                'ends_at'   => $slot['end']->copy()->utc()->toIso8601String(),
                'students'  => $students,
                'nodes'     => self::nodesFor($students, $passwordOnly),
                'bookings'  => $items->map(fn (ExamEngineBooking $b) => [
                    'tenant_id' => $b->tenant_id, 'exam_id' => $b->exam_id, 'title' => $b->title,
                    'expected_count' => $b->expected_count, 'entry_mode' => $b->entry_mode, 'status' => $b->status,
                ])->values()->all(),
            ];
        }, $slots);
    }

    /** Engine nodes for a slot: the always-on node, +1 per 20,000 above the threshold, +1 if password-only. */
    public static function nodesFor(int $students, bool $passwordOnly = false): int
    {
        $threshold = (int) config('exam_engine.scale_out_threshold');
        if ($students <= $threshold) {
            return 1;
        }

        return 1 + (int) ceil(($students - $threshold) / self::STUDENTS_PER_EXTRA_NODE) + ($passwordOnly ? 1 : 0);
    }

    /** @return Collection<int, ExamEngineBooking> */
    private function overlapping(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return ExamEngineBooking::whereIn('status', ExamEngineBooking::ACTIVE_STATUSES)
            ->where('live_starts_at', '<', $end)
            ->where('live_ends_at', '>', $start)
            ->get();
    }

    /** Highest sum of expected counts at any instant (checked at each booking start inside the range). */
    private function peak(Collection $bookings, CarbonInterface $start, CarbonInterface $end): int
    {
        $instants = $bookings->map(fn (ExamEngineBooking $b) => $b->live_starts_at->max($start))->push($start);

        return (int) $instants->map(fn (CarbonInterface $at) => $bookings
            ->filter(fn (ExamEngineBooking $b) => $b->live_starts_at->lte($at) && $b->live_ends_at->gt($at) && $at->lt($end))
            ->sum('expected_count'))
            ->max();
    }
}
