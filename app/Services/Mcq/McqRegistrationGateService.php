<?php

namespace App\Services\Mcq;

use App\Models\McqExam;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Students\StudentVerificationGate;

class McqRegistrationGateService
{
    public function assertCanRegister(McqExam $exam, Tenant $school, Student $student): void
    {
        $reason = $this->blockReason($exam, $school, $student);
        if ($reason) {
            abort(422, $reason);
        }
    }

    public function blockReason(McqExam $exam, Tenant $school, ?Student $student = null): ?string
    {
        $membershipReason = app(\App\Services\Membership\SchoolMembershipGate::class)->blockReason($school);
        if ($membershipReason !== null) {
            return $membershipReason;
        }

        if (! in_array($exam->status, ['published', 'ongoing'], true)) {
            return 'Registration is closed for this exam.';
        }

        if ($student) {
            $verificationGate = app(StudentVerificationGate::class);
            if (! $verificationGate->isEligible($student, null, $exam->tenant_id)) {
                return $verificationGate->ineligibilityReason($student, null, $exam->tenant_id)
                    ?? 'Student is not verified.';
            }

            $eligibility = app(McqEligibilityService::class);
            if (! $eligibility->isEligible($exam, $student)) {
                return $eligibility->ineligibilityReason($exam, $student)
                    ?? 'Student is not eligible for this exam.';
            }
        }

        return null;
    }

    public function assertSchoolCanAccess(Tenant $school): void
    {
        if ($school->membership_status === 'rejected') {
            abort(422, 'Your school application was rejected.');
        }
    }

    /** @return array{blocked: bool, reason: ?string, links: array<string, string>} */
    public function schoolGatePayload(Tenant $school): array
    {
        $reason = $this->blockReasonWithoutStudent($school);

        return [
            'blocked' => $reason !== null,
            'reason'  => $reason,
            'links'   => [
                'membership'   => "/school-admin/{$school->id}/registration",
                'students'     => "/school-admin/{$school->id}/students",
                'verification' => "/school-admin/{$school->id}/students",
            ],
        ];
    }

    private function blockReasonWithoutStudent(Tenant $school): ?string
    {
        return app(\App\Services\Membership\SchoolMembershipGate::class)->blockReason($school);
    }
}
