<?php

namespace App\Services\Mcq;

use App\Models\MasterClass;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Support\FestStudentClassResolver;
use App\Support\Mcq\McqExamEligibilityConfig;
use Illuminate\Support\Collection;

class McqEligibilityService
{
    public function isEligible(McqExam $exam, Student $student): bool
    {
        if (! $this->passesBasicConfig($exam, $student)) {
            return false;
        }

        if ((int) ($exam->exam_level ?? 1) <= 1) {
            return true;
        }

        return $this->passesParentExamEligibility($exam, $student);
    }

    /** @return Collection<int, Student> */
    public function eligibleStudents(McqExam $exam, Collection $students): Collection
    {
        return $students->filter(fn (Student $student) => $this->isEligible($exam, $student))->values();
    }

    /** @return list<int> */
    public function eligibleStudentIds(McqExam $exam, string $schoolId): array
    {
        $students = Student::where('tenant_id', $schoolId)->active()->get();

        return $this->eligibleStudents($exam, $students)->pluck('id')->all();
    }

    public function previewEligibleCount(McqExam $exam): int
    {
        if ((int) ($exam->exam_level ?? 1) <= 1 || ! $exam->parent_exam_id) {
            return 0;
        }

        $parentExam = McqExam::find($exam->parent_exam_id);
        if (! $parentExam) {
            return 0;
        }

        $studentIds = McqRegistration::where('exam_id', $parentExam->id)
            ->whereIn('status', ['registered', 'submitted'])
            ->pluck('student_id')
            ->filter()
            ->unique();

        $students = Student::whereIn('id', $studentIds)->get();

        return $this->eligibleStudents($exam, $students)->count();
    }

    private function passesBasicConfig(McqExam $exam, Student $student): bool
    {
        $config = McqExamEligibilityConfig::normalize($exam->eligibility_config);

        if ($config['scope'] === 'all' || ($config['assignment_type'] ?? 'all') === 'all') {
            return $this->passesGender($config, $student);
        }

        $categoryIds = $config['class_category_ids'];
        $masterClassIds = $config['master_class_ids'];
        $classGroups = $config['class_groups'];
        $assignmentType = $config['assignment_type'] ?? 'all';

        if ($assignmentType === 'category' && $categoryIds === []
            || $assignmentType === 'class' && $masterClassIds === []
            || ($assignmentType === 'all' && $classGroups === [])) {
            return $this->passesGender($config, $student);
        }

        $passesScope = false;

        if ($assignmentType === 'category' && $categoryIds !== []) {
            $student->loadMissing('schoolClass');
            $catId = (int) ($student->schoolClass?->class_category_id ?? 0);
            $passesScope = $catId > 0 && in_array($catId, $categoryIds, true);
        }

        if ($assignmentType === 'class' && $masterClassIds !== []) {
            $passesScope = $this->matchesMasterClasses($student, $masterClassIds);
        }

        if (! $passesScope && $classGroups !== []) {
            $group = FestStudentClassResolver::kalolsavClassGroupForStudent($student);
            if ($group && in_array($group, $classGroups, true)) {
                $passesScope = true;
            }
        }

        if (! $passesScope) {
            return false;
        }

        return $this->passesGender($config, $student);
    }

    /** @param  list<int>  $masterClassIds */
    private function matchesMasterClasses(Student $student, array $masterClassIds): bool
    {
        $student->loadMissing('schoolClass');
        $className = trim($student->schoolClass?->name ?? '');
        if ($className === '') {
            return false;
        }

        $names = MasterClass::whereIn('id', $masterClassIds)->pluck('name');
        $normalizedStudent = $this->normalizeClassName($className);
        $studentClassNum = FestStudentClassResolver::classNumberFromName($className);

        foreach ($names as $masterName) {
            if ($this->normalizeClassName((string) $masterName) === $normalizedStudent) {
                return true;
            }

            $masterClassNum = FestStudentClassResolver::classNumberFromName((string) $masterName);
            if ($studentClassNum !== null && $studentClassNum === $masterClassNum) {
                return true;
            }
        }

        return false;
    }

    private function normalizeClassName(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? trim($name));
    }

    /** @param  array<string, mixed>  $config */
    private function passesGender(array $config, Student $student): bool
    {
        if (($config['gender'] ?? 'open') === 'open') {
            return true;
        }

        return strtolower((string) $student->gender) === strtolower((string) $config['gender']);
    }

    private function passesParentExamEligibility(McqExam $exam, Student $student): bool
    {
        $mode = $exam->eligibility_mode ?? 'open';
        if ($mode === 'open') {
            return true;
        }

        if ($mode === 'manual') {
            $ids = $exam->promoted_student_ids ?? [];

            return in_array($student->id, $ids, true);
        }

        if (! $exam->parent_exam_id) {
            return false;
        }

        $registration = McqRegistration::where('exam_id', $exam->parent_exam_id)
            ->where('student_id', $student->id)
            ->whereIn('status', ['registered', 'submitted'])
            ->first();

        if (! $registration) {
            return false;
        }

        $mark = McqMark::where('registration_id', $registration->id)->first();
        if (! $mark) {
            return false;
        }

        return match ($mode) {
            'cutoff_marks' => (float) $mark->score >= (float) ($exam->cutoff_score ?? 0),
            'top_rank'     => $mark->rank !== null && (int) $mark->rank <= (int) ($exam->top_rank_count ?? 0),
            default        => false,
        };
    }
}
