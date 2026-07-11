<?php

namespace App\Services\Mcq;

use App\Models\MasterClass;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\Students\StudentVerificationGate;
use App\Services\Teachers\TeacherVerificationGate;
use App\Support\FestStudentClassResolver;
use App\Support\Mcq\McqExamEligibilityConfig;
use Illuminate\Support\Collection;

class McqEligibilityService
{
    /** @var array<string, array<int, string>> */
    private array $masterClassNameCache = [];

    public function __construct(
        private StudentVerificationGate $verificationGate,
        private TeacherVerificationGate $teacherVerificationGate,
    ) {}

    /**
     * Resolve which of a school's own class IDs are eligible for this exam so that
     * student queries can be scoped at the database level instead of loading every
     * student (a school may have 300–2000 students).
     *
     * Returns null when there is no class-level restriction (all classes may apply,
     * with only gender/verification filtered per student), in which case the caller
     * should not constrain the student query by class.
     *
     * @return list<int>|null
     */
    public function eligibleSchoolClassIds(McqExam $exam, string $schoolId): ?array
    {
        $config = McqExamEligibilityConfig::normalize($exam->eligibility_config);
        $assignmentType = $config['assignment_type'] ?? 'all';
        $categoryIds = $config['class_category_ids'];
        $masterClassIds = $config['master_class_ids'];
        $classGroups = $config['class_groups'];

        $noClassRestriction = $classGroups === [] && (
            $assignmentType === 'all'
            || ($assignmentType === 'category' && $categoryIds === [])
            || ($assignmentType === 'class' && $masterClassIds === [])
        );

        if ($noClassRestriction) {
            return null;
        }

        $classes = SchoolClass::where('tenant_id', $schoolId)
            ->get(['id', 'name', 'class_category_id']);

        $masterNames = $masterClassIds !== [] ? $this->masterClassNames($masterClassIds) : [];
        $normalizedMasterNames = array_map(fn ($n) => $this->normalizeClassName((string) $n), $masterNames);
        $masterNumbers = array_filter(array_map(
            fn ($n) => FestStudentClassResolver::classNumberFromName((string) $n),
            $masterNames
        ), fn ($n) => $n !== null);

        $ids = [];

        foreach ($classes as $class) {
            $passes = false;

            if ($assignmentType === 'category' && $categoryIds !== []) {
                $passes = (int) ($class->class_category_id ?? 0) > 0
                    && in_array((int) $class->class_category_id, $categoryIds, true);
            }

            if (! $passes && $assignmentType === 'class' && $masterClassIds !== []) {
                $className = trim((string) $class->name);
                if ($className !== '') {
                    $normalized = $this->normalizeClassName($className);
                    $classNumber = FestStudentClassResolver::classNumberFromName($className);
                    $passes = in_array($normalized, $normalizedMasterNames, true)
                        || ($classNumber !== null && in_array($classNumber, $masterNumbers, true));
                }
            }

            if (! $passes && $classGroups !== []) {
                $group = FestStudentClassResolver::kalolsavClassGroup(
                    FestStudentClassResolver::classNumberFromName((string) $class->name)
                );
                $passes = $group !== null && in_array($group, $classGroups, true);
            }

            if ($passes) {
                $ids[] = (int) $class->id;
            }
        }

        return $ids;
    }

    public function isEligible(McqExam $exam, Student $student): bool
    {
        return $this->ineligibilityReason($exam, $student) === null;
    }

    public function isTeacherEligible(McqExam $exam, Teacher $teacher): bool
    {
        return $this->teacherIneligibilityReason($exam, $teacher) === null;
    }

    /** @return Collection<int, Teacher> */
    public function eligibleTeachers(McqExam $exam, Collection $teachers): Collection
    {
        return $teachers->filter(fn (Teacher $t) => $this->isTeacherEligible($exam, $t))->values();
    }

    public function teacherIneligibilityReason(McqExam $exam, Teacher $teacher): ?string
    {
        if (! McqExamEligibilityConfig::allowsTeachers($exam->eligibility_config)) {
            return 'This exam is not open to teachers.';
        }

        if ($teacher->status !== 'active') {
            return 'Teacher is not active.';
        }

        if ($reason = $this->teacherVerificationGate->ineligibilityReason($teacher, $exam->tenant_id)) {
            return $reason;
        }

        $config = McqExamEligibilityConfig::normalize($exam->eligibility_config);

        $typeIds = $config['teaching_type_ids'];
        if ($typeIds !== [] && ! in_array((int) $teacher->teaching_type_id, $typeIds, true)) {
            return 'Teacher does not match required teaching category.';
        }

        $subjectIds = $config['subject_ids'];
        if ($subjectIds !== []) {
            $teacherSubjectIds = array_map('intval', $teacher->subject_ids ?? []);
            if ($teacherSubjectIds === [] || array_intersect($teacherSubjectIds, $subjectIds) === []) {
                return 'Teacher does not teach a required subject.';
            }
        }

        $excluded = $config['excluded_designation_ids'];
        if ($excluded !== [] && $teacher->designation_id !== null
            && in_array((int) $teacher->designation_id, $excluded, true)) {
            return 'This designation is not eligible for this exam.';
        }

        $minYears = $config['min_experience_years'];
        if ($minYears !== null) {
            $years = (int) ($teacher->experience_years ?? 0);
            if ($years < $minYears) {
                return "Teacher must have at least {$minYears} year(s) of experience.";
            }
        }

        return null;
    }

    /** @return Collection<int, Student> */
    public function eligibleStudents(McqExam $exam, Collection $students): Collection
    {
        return $students->filter(fn (Student $student) => $this->isEligible($exam, $student))->values();
    }

    public function ineligibilityReason(McqExam $exam, Student $student): ?string
    {
        if (! McqExamEligibilityConfig::allowsStudents($exam->eligibility_config)) {
            return 'This exam is not open to students.';
        }

        if (! $this->verificationGate->isEligible($student, null, $exam->tenant_id, $exam)) {
            return $this->verificationGate
                ->ineligibilityReason($student, null, $exam->tenant_id, $exam)
                ?? 'Student is not verified.';
        }

        if (! $this->passesBasicConfig($exam, $student)) {
            return 'Student does not match class/gender eligibility for this exam.';
        }

        if ((int) ($exam->exam_level ?? 1) <= 1) {
            return null;
        }

        return $this->parentExamIneligibilityReason($exam, $student);
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

        $names = $this->masterClassNames($masterClassIds);
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

    /**
     * Cached lookup of master class names, keyed by the requested id set, so
     * per-student eligibility checks do not issue one query per student.
     *
     * @param  list<int>  $masterClassIds
     * @return array<int, string>
     */
    private function masterClassNames(array $masterClassIds): array
    {
        sort($masterClassIds);
        $key = implode(',', $masterClassIds);

        return $this->masterClassNameCache[$key] ??= MasterClass::whereIn('id', $masterClassIds)
            ->pluck('name')
            ->all();
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
        return $this->parentExamIneligibilityReason($exam, $student) === null;
    }

    private function parentExamIneligibilityReason(McqExam $exam, Student $student): ?string
    {
        $mode = $exam->eligibility_mode ?? 'open';
        if ($mode === 'open') {
            return null;
        }

        if ($mode === 'manual') {
            $ids = $exam->promoted_student_ids ?? [];

            return in_array($student->id, $ids, true)
                ? null
                : 'Student is not on the Level 2 promotion list.';
        }

        if (! $exam->parent_exam_id) {
            return 'Level 2 exam is missing a parent Level 1 exam.';
        }

        $parentExam = McqExam::find($exam->parent_exam_id);
        if (! $parentExam) {
            return 'Parent Level 1 exam was not found.';
        }

        if (! $parentExam->results_published) {
            return 'Level 1 results are not published yet.';
        }

        if (! $parentExam->promotion_locked) {
            return 'Level 2 qualifier list is not locked yet.';
        }

        $registration = McqRegistration::where('exam_id', $exam->parent_exam_id)
            ->where('student_id', $student->id)
            ->whereIn('status', ['registered', 'submitted'])
            ->first();

        if (! $registration) {
            return 'Student was not registered for Level 1.';
        }

        if ($registration->attendance_status === 'absent') {
            return 'Student was absent in Level 1.';
        }

        if ($registration->status !== 'submitted') {
            return 'Student did not complete Level 1 exam.';
        }

        $mark = McqMark::where('registration_id', $registration->id)->first();
        if (! $mark) {
            return 'Level 1 marks are not available for this student.';
        }

        $qualified = match ($mode) {
            'cutoff_marks' => (float) $mark->score >= (float) ($exam->cutoff_score ?? 0),
            'top_rank'     => $mark->rank !== null && (int) $mark->rank <= (int) ($exam->top_rank_count ?? 0),
            default        => false,
        };

        if ($qualified) {
            return null;
        }

        return match ($mode) {
            'cutoff_marks' => 'Score below Level 2 cutoff.',
            'top_rank'     => 'Rank outside Level 2 qualifier limit.',
            default        => 'Student did not qualify for Level 2.',
        };
    }
}
