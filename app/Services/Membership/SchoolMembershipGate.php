<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use App\Support\AcademicYear;

/**
 * Programs (fest events, teacher training, Talent Search / MCQ) only open up for a
 * school once its Sahodaya membership is settled. A school is considered "paid" when
 * the Sahodaya has approved its membership (approval follows fee verification) or a
 * verified membership payment exists for the active academic year.
 */
class SchoolMembershipGate
{
    public function isPaid(Tenant $school): bool
    {
        if (! $school->is_active || $school->membership_status === 'rejected') {
            return false;
        }

        if ($school->membership_status === 'approved') {
            return true;
        }

        $year = AcademicYear::forSchool($school);

        if (MembershipPayment::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->where('status', 'verified')
            ->exists()) {
            return true;
        }

        return Registration::where('school_id', $school->id)
            ->where('academic_year', $year)
            ->where('registration_status', 'completed')
            ->exists();
    }

    public function blockReason(Tenant $school): ?string
    {
        if (! $school->is_active) {
            return 'This school account is inactive.';
        }

        if ($school->membership_status === 'rejected') {
            return 'Your school application was rejected.';
        }

        if ($this->isPaid($school)) {
            return null;
        }

        return 'Complete your Sahodaya membership payment to unlock events, training and Talent Search.';
    }

    public function assertPaid(Tenant $school): void
    {
        $reason = $this->blockReason($school);
        if ($reason !== null) {
            abort(422, $reason);
        }
    }
}
