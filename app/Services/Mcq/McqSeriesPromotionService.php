<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class McqSeriesPromotionService
{
    public function __construct(
        private McqEligibilityService $eligibility,
    ) {}

    public function canPromote(McqExam $childExam): bool
    {
        if ((int) ($childExam->exam_level ?? 1) <= 1 || ! $childExam->parent_exam_id) {
            return false;
        }

        $parent = McqExam::find($childExam->parent_exam_id);

        return $parent
            && $parent->results_published
            && ! $childExam->promotion_locked;
    }

    /** @return Collection<int, array{student_id: int, student_name: ?string, reg_no: ?string, school_name: ?string, score: ?float, rank: ?int}> */
    public function qualifiers(McqExam $childExam): Collection
    {
        $parent = McqExam::find($childExam->parent_exam_id);
        if (! $parent) {
            return collect();
        }

        $registrations = McqRegistration::where('exam_id', $parent->id)
            ->whereIn('status', ['registered', 'submitted'])
            ->with(['student.schoolClass', 'school', 'mark'])
            ->get();

        $students = Student::whereIn('id', $registrations->pluck('student_id')->filter())->get();

        return $this->eligibility->eligibleStudents($childExam, $students)
            ->map(function (Student $student) use ($registrations) {
                $reg = $registrations->firstWhere('student_id', $student->id);
                $mark = $reg?->mark;

                return [
                    'student_id'   => $student->id,
                    'student_name' => $student->name,
                    'reg_no'       => $student->reg_no,
                    'class_name'   => $student->schoolClass?->name,
                    'school_name'  => $reg?->school?->name,
                    'score'        => $mark?->score !== null ? (float) $mark->score : null,
                    'rank'         => $mark?->rank,
                ];
            })
            ->sortBy([
                ['rank', 'asc'],
                ['score', 'desc'],
                ['student_name', 'asc'],
            ])
            ->values();
    }

    public function lockPromotionList(McqExam $childExam, ?int $userId = null): McqExam
    {
        if ($childExam->promotion_locked) {
            throw ValidationException::withMessages([
                'promotion' => 'Promotion list is already locked for this level.',
            ]);
        }

        if (! $this->canPromote($childExam)) {
            throw ValidationException::withMessages([
                'promotion' => 'Parent exam results must be published before locking promotion.',
            ]);
        }

        $ids = $this->qualifiers($childExam)->pluck('student_id')->all();

        $childExam->update([
            'promoted_student_ids' => $ids,
            'promotion_locked'     => true,
            'eligibility_mode'     => 'manual',
        ]);

        app(PlatformAuditLogger::class)->mcq(
            $childExam->fresh(),
            'mcq.promotion.locked',
            count($ids).' student(s) locked for Level '.($childExam->exam_level ?? 2).' promotion',
            ['student_count' => count($ids)],
        );

        return $childExam->fresh();
    }

    public function promotionSummary(McqExam $childExam): array
    {
        $parent = McqExam::find($childExam->parent_exam_id);
        $qualifiers = $this->qualifiers($childExam);

        return [
            'can_promote'       => $this->canPromote($childExam),
            'promotion_locked'  => (bool) $childExam->promotion_locked,
            'promoted_count'    => count($childExam->promoted_student_ids ?? []),
            'qualifier_count'   => $qualifiers->count(),
            'parent_title'      => $parent?->title,
            'parent_published'  => (bool) $parent?->results_published,
            'eligibility_mode'  => $childExam->eligibility_mode,
            'cutoff_score'      => $childExam->cutoff_score,
            'top_rank_count'    => $childExam->top_rank_count,
            'qualifiers'        => $qualifiers->take(100)->all(),
        ];
    }
}
