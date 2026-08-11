<?php

namespace App\Services\State;

use App\Models\State\StateFestEvent;
use App\Services\Events\FestGradePointService;

/**
 * State-level analog of App\Services\Events\FestGradePointService. Not a direct reuse:
 * FestGradePointService's methods are strictly typed to the tenant FestEvent/FestMark models,
 * and its non-mcs/confed path reads FestGradeConfig off the default (tenant) database
 * connection, which can't resolve for a State event. Delegates the two grade-from-score
 * calculations that ARE connection-agnostic (bare float in, no event needed) to
 * FestGradePointService directly; duplicates the small mcs/confed points-table lookup here
 * since those are private methods there.
 *
 * Deliberately does NOT support a generic per-event grade-config path the way the tenant
 * service does — State currently only needs mcs_kalotsav/confed_kalotsav presets (the two
 * this platform is actually built around). A custom-config path can be added if a future
 * State program needs one.
 */
class StateGradePointService
{
    public function __construct(private FestGradePointService $tenantService) {}

    public function resolveGradeFromScore(StateFestEvent $event, float $score): ?string
    {
        return match ($event->scoring_preset) {
            'mcs_kalotsav'    => $this->tenantService->resolveMcsGradeFromScore($score),
            'confed_kalotsav' => $this->tenantService->resolveConfedGradeFromScore($score),
            default           => null,
        };
    }

    public function pointsForGradePosition(StateFestEvent $event, ?string $grade, ?int $position, bool $isGroup = false): int
    {
        $configKey = match ($event->scoring_preset) {
            'mcs_kalotsav'    => 'fest_mcs_scoring',
            'confed_kalotsav' => 'fest_confed_kalotsav_scoring',
            default           => null,
        };

        if (! $configKey || ! $grade || ! $position) {
            return 0;
        }

        $table = config($configKey.($isGroup ? '.group_points' : '.individual_points'), []);
        $normalizedGrade = $this->normalizeGrade($grade);
        $pos = (string) $position;

        return (int) ($table[$normalizedGrade][$pos] ?? 0);
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
