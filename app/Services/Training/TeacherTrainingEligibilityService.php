<?php

namespace App\Services\Training;

use App\Models\SchoolRegionAssignment;
use App\Models\Teacher;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Teachers\TeacherVerificationGate;
use App\Support\Training\TrainingProgramEligibilityConfig;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TeacherTrainingEligibilityService
{
    public function __construct(private TeacherVerificationGate $verificationGate) {}

    public function isEligible(TrainingProgram $program, Teacher $teacher): bool
    {
        return $this->ineligibilityReason($program, $teacher) === null;
    }

    /**
     * Throw when the teacher cannot register for the programme.
     *
     * @throws ValidationException
     */
    public function assertTeacherEligible(TrainingProgram $program, Teacher $teacher): void
    {
        $reason = $this->ineligibilityReason($program, $teacher);
        if ($reason === null) {
            return;
        }

        throw ValidationException::withMessages([
            'eligibility' => $reason,
        ]);
    }

    public function ineligibilityReason(TrainingProgram $program, Teacher $teacher): ?string
    {
        if ($teacher->status !== 'active') {
            return 'Teacher is not active.';
        }

        if ($program->require_verified_teachers && ($reason = $this->verificationGate->ineligibilityReason($teacher, $program->tenant_id))) {
            return $reason;
        }

        if (! $this->registrationWindowOpen($program)) {
            return 'Training registration is closed.';
        }

        // Capacity is handled via waitlist (TrainingWaitlistService), not hard-reject.

        return $this->configIneligibilityReason($program, $teacher);
    }

    /** @return Collection<int, Teacher> */
    public function eligibleTeachers(TrainingProgram $program, Collection $teachers): Collection
    {
        return $teachers->filter(fn (Teacher $t) => $this->isEligible($program, $t))->values();
    }

    private function configIneligibilityReason(TrainingProgram $program, Teacher $teacher): ?string
    {
        $config = TrainingProgramEligibilityConfig::normalize($program->eligibility_config);

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
            return 'This designation is not eligible for this training.';
        }

        $minYears = $config['min_experience_years'];
        if ($minYears !== null) {
            $years = (int) ($teacher->experience_years ?? 0);
            if ($years < $minYears) {
                return "Teacher must have at least {$minYears} year(s) of experience.";
            }
        }

        if ($config['prior_training']['required'] && ! $this->hasCompletedPriorTraining($teacher, $config['prior_training']['program_id'])) {
            return $config['prior_training']['program_id']
                ? 'Teacher must have completed the required prior training programme.'
                : 'Teacher must have completed a prior training programme.';
        }

        $regionIds = $config['region_ids'];
        if ($regionIds !== [] && ! $this->schoolInRegions((string) $teacher->tenant_id, $program->tenant_id, $regionIds)) {
            return 'Teacher\'s school is not in an eligible region.';
        }

        return null;
    }

    private function hasCompletedPriorTraining(Teacher $teacher, ?int $programId): bool
    {
        $query = TrainingRegistration::query()
            ->where('teacher_id', $teacher->id)
            ->whereIn('status', ['confirmed', 'completed']);

        if ($programId !== null) {
            $query->where('program_id', $programId);
        }

        return $query->exists();
    }

    /** @param  list<int>  $regionIds */
    private function schoolInRegions(string $schoolId, string $sahodayaId, array $regionIds): bool
    {
        return SchoolRegionAssignment::query()
            ->where('tenant_id', $sahodayaId)
            ->where('school_id', $schoolId)
            ->whereIn('region_id', $regionIds)
            ->exists();
    }

    private function registrationWindowOpen(TrainingProgram $program): bool
    {
        if (! in_array($program->status, ['published', 'ongoing'], true)) {
            return false;
        }

        $today = now()->toDateString();

        if ($program->registration_open && $today < $program->registration_open->toDateString()) {
            return false;
        }

        if ($program->registration_close && $today > $program->registration_close->toDateString()) {
            return false;
        }

        return true;
    }

    /** Seated capacity only — waitlisted rows do not occupy seats. */
    public function hasCapacity(TrainingProgram $program): bool
    {
        return app(TrainingWaitlistService::class)->hasOpenSeat($program);
    }
}
