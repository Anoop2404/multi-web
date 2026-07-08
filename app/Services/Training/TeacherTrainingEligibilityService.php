<?php

namespace App\Services\Training;

use App\Models\Teacher;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Teachers\TeacherVerificationGate;
use App\Support\Training\TrainingProgramEligibilityConfig;
use Illuminate\Support\Collection;

class TeacherTrainingEligibilityService
{
    public function __construct(private TeacherVerificationGate $verificationGate) {}

    public function isEligible(TrainingProgram $program, Teacher $teacher): bool
    {
        if ($teacher->status !== 'active') {
            return false;
        }

        if (! $this->verificationGate->isEligible($teacher, $program->tenant_id)) {
            return false;
        }

        if (! $this->registrationWindowOpen($program)) {
            return false;
        }

        if (! $this->hasCapacity($program)) {
            return false;
        }

        return $this->passesConfig($program, $teacher);
    }

    public function ineligibilityReason(TrainingProgram $program, Teacher $teacher): ?string
    {
        if ($teacher->status !== 'active') {
            return 'Teacher is not active.';
        }

        if ($reason = $this->verificationGate->ineligibilityReason($teacher, $program->tenant_id)) {
            return $reason;
        }

        if (! $this->registrationWindowOpen($program)) {
            return 'Training registration is closed.';
        }

        if (! $this->hasCapacity($program)) {
            return 'Maximum participant limit reached.';
        }

        if (! $this->passesConfig($program, $teacher)) {
            return 'Teacher does not match required category or subject.';
        }

        return null;
    }

    /** @return Collection<int, Teacher> */
    public function eligibleTeachers(TrainingProgram $program, Collection $teachers): Collection
    {
        return $teachers->filter(fn (Teacher $t) => $this->isEligible($program, $t))->values();
    }

    private function passesConfig(TrainingProgram $program, Teacher $teacher): bool
    {
        $config = TrainingProgramEligibilityConfig::normalize($program->eligibility_config);
        $typeIds = $config['teaching_type_ids'];
        $subjectIds = $config['subject_ids'];

        if ($typeIds !== [] && ! in_array((int) $teacher->teaching_type_id, $typeIds, true)) {
            return false;
        }

        if ($subjectIds === []) {
            return true;
        }

        $teacherSubjectIds = array_map('intval', $teacher->subject_ids ?? []);

        if ($teacherSubjectIds !== []) {
            return array_intersect($teacherSubjectIds, $subjectIds) !== [];
        }

        return false;
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

    private function hasCapacity(TrainingProgram $program): bool
    {
        if (! $program->max_participants) {
            return true;
        }

        $count = TrainingRegistration::where('program_id', $program->id)
            ->whereNotIn('status', ['cancelled', 'rejected'])
            ->count();

        return $count < (int) $program->max_participants;
    }
}
