<?php

namespace App\Services\Membership;

use App\Models\MembershipFeeSlab;
use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\SchoolYearStudentCount;
use App\Models\SchoolYearSubmission;
use App\Models\Student;
use App\Models\Tenant;

class MembershipFeeCalculator
{
    public function calculateAndApply(Registration $registration, SahodayaProfile $profile, SchoolYearSubmission $submission): void
    {
        $school = $registration->school;
        $amount = $this->amountForSchool($profile, $school, $registration->academic_year, $submission);

        if ($registration->fee_override && isset($registration->fee_override['override_amount'])) {
            $amount = (float) $registration->fee_override['override_amount'];
        }

        $registration->update([
            'membership_fee_amount' => $amount,
            'registration_status'   => $profile->requiresMembershipPaymentForSchool($school) && $amount > 0
                ? 'payment_pending'
                : 'completed',
        ]);
    }

    public function amountForSchool(
        SahodayaProfile $profile,
        ?Tenant $school,
        string $academicYear,
        ?SchoolYearSubmission $submission = null,
    ): float {
        if ($school?->is_non_affiliated && $profile->allow_non_affiliated_schools) {
            return match ($profile->non_affiliated_membership_fee_type ?? 'fixed') {
                'none' => 0.0,
                default => (float) ($profile->non_affiliated_fixed_membership_fee_amount ?? 0),
            };
        }

        return match ($profile->membership_fee_type) {
            'none' => 0.0,
            'fixed' => (float) ($profile->fixed_membership_fee_amount ?? 0),
            'variable_by_student_count' => $this->fromSlabs(
                $school?->parent_id ?? $profile->tenant_id,
                $academicYear,
                $submission
                    ? $this->totalStudents($profile, $submission)
                    : ($school ? $this->estimateStudentCount($school, $academicYear) : 0)
            ),
            default => 0.0,
        };
    }

    public function totalStudents(SahodayaProfile $profile, SchoolYearSubmission $submission): int
    {
        return match ($profile->student_data_mode) {
            'full_records' => $submission->students()->count(),
            'counts_only'  => (int) $submission->counts()->sum('total_count'),
            default        => 0,
        };
    }

    private function fromSlabs(string $sahodayaId, string $academicYear, int $total): float
    {
        $slab = MembershipFeeSlab::where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $academicYear)
            ->where('min_students', '<=', $total)
            ->where(function ($q) use ($total) {
                $q->whereNull('max_students')->orWhere('max_students', '>=', $total);
            })
            ->orderByDesc('min_students')
            ->first();

        return $slab ? (float) $slab->amount : 0.0;
    }

    public function estimateFeeForSchool(Tenant $school, string $academicYear): float
    {
        $profile = SahodayaProfile::where('tenant_id', $school->parent_id)->first();

        if (! $profile) {
            return 0.0;
        }

        return $this->amountForSchool($profile, $school, $academicYear);
    }

    public function estimateStudentCount(Tenant $school, string $academicYear): int
    {
        $activeCount = Student::where('tenant_id', $school->id)
            ->where('status', 'active')
            ->count();

        if ($activeCount > 0) {
            return $activeCount;
        }

        $priorYear = SchoolYearStudentCount::query()
            ->whereHas('submission', fn ($q) => $q
                ->where('school_id', $school->id)
                ->where('academic_year', '!=', $academicYear))
            ->sum('total_count');

        return (int) $priorYear;
    }
}
