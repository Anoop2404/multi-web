<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Services\Events\FestGradePointService;
use App\Services\State\Fest\StateGradingService;

/**
 * Grades and points for a State event, in precedence order:
 *
 *   1. the event's own grade bands and point rules (Grade Master / Grade & Rank Points),
 *   2. its `scoring_preset`, if one is set,
 *   3. the Kalotsavam manual's standard table.
 *
 * The third tier is the important one. Until it existed this service read `scoring_preset` and
 * nothing else, and nothing in the State module ever sets a preset — so every State event scored
 * **every mark at zero points** and derived no grade from any score, silently. A Sahodaya ranking of
 * all zeros looks like "nobody has been marked yet", which is why it survived.
 *
 * Falling back to the manual rather than to zero is defensible because the manual is the rule the
 * State actually competes under: config/fest_confed_kalotsav_scoring.php records that it applies at
 * every level. An event that wants different numbers sets its own on the Grade Master tab, and those
 * win.
 */
class StateGradePointService
{
    public function __construct(
        private FestGradePointService $tenantService,
        private StateGradingService $grading,
    ) {}

    public function resolveGradeFromScore(StateFestEvent $event, float $score, ?string $itemId = null): ?string
    {
        // The event's own bands first: an operator who set a scale expects it used.
        if ($grade = $this->grading->gradeFor($event, $score, $itemId)) {
            return $grade;
        }

        return match ($event->scoring_preset) {
            'mcs_kalotsav'    => $this->tenantService->resolveMcsGradeFromScore($score),
            'confed_kalotsav' => $this->tenantService->resolveConfedGradeFromScore($score),
            // No bands and no preset: the manual's standard scale, so a score still resolves.
            default           => $this->manualGrade($score),
        };
    }

    public function pointsForGradePosition(StateFestEvent $event, ?string $grade, ?int $position, bool $isGroup = false): int
    {
        if ($this->grading->rules($event)->isNotEmpty()) {
            return $this->grading->pointsFor($event, $grade, $position, $isGroup);
        }

        $configKey = match ($event->scoring_preset) {
            'mcs_kalotsav'    => 'fest_mcs_scoring',
            'confed_kalotsav' => StateGradingService::CONFED_SCORING,
            default           => StateGradingService::CONFED_SCORING,
        };

        if (! $grade || ! $position) {
            return 0;
        }

        $table = config($configKey.($isGroup ? '.group_points' : '.individual_points'), []);
        $normalizedGrade = $this->normalizeGrade($grade);

        return (int) ($table[$normalizedGrade][(string) $position] ?? 0);
    }

    /** Which tier is actually in force, so a screen can say so rather than leaving it to be guessed. */
    public function sourceFor(StateFestEvent $event): array
    {
        if ($this->grading->rules($event)->isNotEmpty()) {
            return ['source' => 'event', 'label' => "This event's own point rules"];
        }

        return match ($event->scoring_preset) {
            'mcs_kalotsav' => ['source' => 'preset', 'label' => 'MCS Kalotsav preset'],
            'confed_kalotsav' => ['source' => 'preset', 'label' => 'Confederation Kalotsavam preset'],
            default => [
                'source' => 'manual',
                'label' => 'Kalotsavam manual standard (no rules set on this event)',
            ],
        };
    }

    private function manualGrade(float $score): ?string
    {
        foreach (config(StateGradingService::CONFED_SCORING.'.grades', []) as $key => $grade) {
            if ($score >= (float) $grade['min']) {
                return $grade['label'] ?? $key;
            }
        }

        return null;
    }

    private function normalizeGrade(?string $grade): string
    {
        return match (strtoupper((string) $grade)) {
            'A+', 'A_PLUS', 'A' => 'A',
            'B' => 'B',
            default => 'C',
        };
    }
}
