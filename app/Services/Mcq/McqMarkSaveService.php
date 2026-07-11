<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use Illuminate\Validation\ValidationException;

class McqMarkSaveService
{
    public function __construct(
        private McqGradeService $grades,
    ) {}

    /** @param  array<string, mixed>  $data */
    public function save(McqExam $exam, McqRegistration $registration, array $data, int $userId): McqMark
    {
        if ($registration->blocksScoring()) {
            throw ValidationException::withMessages([
                'attendance' => 'Marks cannot be entered for students marked '.$registration->attendanceStatusLabel().'.',
            ]);
        }

        if ($exam->results_published) {
            throw ValidationException::withMessages([
                'results' => 'Results are published for this exam. Unpublish results before editing marks.',
            ]);
        }

        if ($exam->isOfflineDelivery() && $registration->attendance_status !== 'present') {
            throw ValidationException::withMessages([
                'attendance' => 'Mark attendance as present before entering marks.',
            ]);
        }

        $correct = (int) ($data['correct_count'] ?? 0);
        $wrong = (int) ($data['wrong_count'] ?? 0);
        $unanswered = (int) ($data['unanswered_count'] ?? 0);
        $score = (float) ($data['score'] ?? $correct);
        $total = $correct + $wrong + $unanswered;
        $denominator = max($exam->total_questions ?: $total, 1);
        $percentage = $total > 0 ? round(($score / $denominator) * 100, 2) : 0;

        $grade = filled($data['grade'] ?? null)
            ? (string) $data['grade']
            : $this->grades->gradeForPercentage($exam, $percentage);

        $allowed = $this->grades->allowedGradeLabels($exam);
        if ($allowed !== [] && ! in_array($grade, $allowed, true)) {
            throw ValidationException::withMessages([
                'grade' => 'Invalid grade label for this exam.',
            ]);
        }

        $mark = McqMark::updateOrCreate(
            ['registration_id' => $registration->id],
            [
                'correct_count'    => $correct,
                'wrong_count'      => $wrong,
                'unanswered_count' => $unanswered,
                'score'            => $score,
                'percentage'       => $percentage,
                'grade'            => $grade,
                'locked_by'        => $userId,
                'locked_at'        => now(),
            ],
        );

        $registration->update(['status' => 'submitted', 'submitted_at' => now()]);

        return $mark;
    }
}
