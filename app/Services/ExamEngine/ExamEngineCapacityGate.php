<?php

namespace App\Services\ExamEngine;

use App\Models\McqExam;
use App\Models\McqRegistration;

/**
 * When an online cohort is large enough, prefer the Go exam-engine path over Laravel
 * draft autosave (see docs/ONLINE_EXAM_RUNBOOK.md Stage A/B).
 */
class ExamEngineCapacityGate
{
    public function forceThreshold(): int
    {
        return max(1, (int) config('exam_engine.force_engine_threshold', 500));
    }

    public function registeredCount(McqExam $exam): int
    {
        return McqRegistration::where('exam_id', $exam->id)->active()->count();
    }

    public function shouldForceEngine(McqExam $exam): bool
    {
        if (! $exam->isOnlineDelivery()) {
            return false;
        }

        return $this->registeredCount($exam) >= $this->forceThreshold();
    }

    /**
     * Ensure the exam is flagged for the engine when over threshold.
     * Does not arm the engine (ops still arm before T0).
     *
     * @return array{forced: bool, already: bool, count: int, threshold: int}
     */
    public function ensureEnginePreferred(McqExam $exam): array
    {
        $count = $this->registeredCount($exam);
        $threshold = $this->forceThreshold();
        $already = ExamEngineRun::usesEngine($exam);

        if (! $exam->isOnlineDelivery() || $count < $threshold) {
            return ['forced' => false, 'already' => $already, 'count' => $count, 'threshold' => $threshold];
        }

        if ($already) {
            return ['forced' => false, 'already' => true, 'count' => $count, 'threshold' => $threshold];
        }

        $exam->update([
            'settings_json' => array_merge($exam->settings_json ?? [], [
                'engine' => 'engine',
                'engine_forced_by_capacity' => true,
                'engine_forced_at' => now()->toIso8601String(),
                'engine_forced_count' => $count,
            ]),
        ]);

        app(ExamEngineBookingService::class)->sync($exam->fresh());

        return ['forced' => true, 'already' => false, 'count' => $count, 'threshold' => $threshold];
    }

    /** @return array{required: bool, uses_engine: bool, count: int, threshold: int, message: ?string} */
    public function panel(McqExam $exam): array
    {
        $count = $this->registeredCount($exam);
        $threshold = $this->forceThreshold();
        $required = $exam->isOnlineDelivery() && $count >= $threshold;
        $uses = ExamEngineRun::usesEngine($exam);

        $message = null;
        if ($required && ! $uses) {
            $message = "This exam has {$count} enrolled candidates (threshold {$threshold}). Enable the online exam engine before the window — Laravel portal delivery is not suitable at this size.";
        } elseif ($required && $uses) {
            $message = "Engine path required: {$count} enrolled (threshold {$threshold}). Arm the exam before T0 per the runbook.";
        }

        return [
            'required'    => $required,
            'uses_engine' => $uses,
            'count'       => $count,
            'threshold'   => $threshold,
            'message'     => $message,
        ];
    }
}
