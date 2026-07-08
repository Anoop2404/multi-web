<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqGradeBand;
use App\Models\McqGradeMaster;
use App\Models\McqMark;
use Illuminate\Support\Facades\Schema;

class McqGradeService
{
    /** @return list<array{label: string, min_percentage: float, max_percentage: float, is_pass: bool, rank_eligible: bool}> */
    public function bandsForExam(McqExam $exam): array
    {
        $master = $this->resolveMaster($exam);
        if (! $master) {
            return $this->defaultBands();
        }

        return $master->bands->map(fn (McqGradeBand $band) => [
            'label'           => $band->label,
            'min_percentage'  => (float) $band->min_percentage,
            'max_percentage'  => (float) $band->max_percentage,
            'is_pass'         => (bool) $band->is_pass,
            'rank_eligible'   => (bool) $band->rank_eligible,
        ])->values()->all();
    }

    public function gradeForPercentage(McqExam $exam, float $percentage): string
    {
        foreach ($this->bandsForExam($exam) as $band) {
            if ($percentage >= $band['min_percentage'] && $percentage <= $band['max_percentage']) {
                return $band['label'];
            }
        }

        return 'F';
    }

    public function applyGradeToMark(McqMark $mark, McqExam $exam): McqMark
    {
        $percentage = (float) ($mark->percentage ?? 0);
        $mark->update(['grade' => $this->gradeForPercentage($exam, $percentage)]);

        return $mark->fresh();
    }

    public function allowedGradeLabels(McqExam $exam): array
    {
        return collect($this->bandsForExam($exam))->pluck('label')->all();
    }

    private function resolveMaster(McqExam $exam): ?McqGradeMaster
    {
        if (! Schema::hasTable('mcq_grade_masters')) {
            return null;
        }

        if ($exam->grade_master_id) {
            return McqGradeMaster::with('bands')->find($exam->grade_master_id);
        }

        return McqGradeMaster::where('tenant_id', $exam->tenant_id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->with('bands')
            ->first();
    }

    /** @return list<array{label: string, min_percentage: float, max_percentage: float, is_pass: bool, rank_eligible: bool}> */
    private function defaultBands(): array
    {
        return [
            ['label' => 'A+', 'min_percentage' => 95, 'max_percentage' => 100, 'is_pass' => true, 'rank_eligible' => true],
            ['label' => 'A', 'min_percentage' => 90, 'max_percentage' => 94.99, 'is_pass' => true, 'rank_eligible' => true],
            ['label' => 'B', 'min_percentage' => 75, 'max_percentage' => 89.99, 'is_pass' => true, 'rank_eligible' => true],
            ['label' => 'C', 'min_percentage' => 60, 'max_percentage' => 74.99, 'is_pass' => true, 'rank_eligible' => true],
            ['label' => 'D', 'min_percentage' => 40, 'max_percentage' => 59.99, 'is_pass' => true, 'rank_eligible' => false],
            ['label' => 'F', 'min_percentage' => 0, 'max_percentage' => 39.99, 'is_pass' => false, 'rank_eligible' => false],
        ];
    }
}
