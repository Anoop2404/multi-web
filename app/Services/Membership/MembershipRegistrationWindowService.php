<?php

namespace App\Services\Membership;

use App\Models\SahodayaRegistrationWindow;
use App\Models\Tenant;
use Carbon\CarbonInterface;

class MembershipRegistrationWindowService
{
    public function forSchool(Tenant $school, string $academicYear): ?SahodayaRegistrationWindow
    {
        $sahodaya = $school->parent;
        if (! $sahodaya) {
            return null;
        }

        return SahodayaRegistrationWindow::where('sahodaya_id', $sahodaya->id)
            ->where('academic_year', $academicYear)
            ->first();
    }

    /** Human-readable reason when registration cannot start, or null when allowed. */
    public function blockReason(?SahodayaRegistrationWindow $window, ?CarbonInterface $now = null): ?string
    {
        if (! $window) {
            return null;
        }

        $now ??= now();
        $today = $now->copy()->startOfDay();

        if ($window->registration_starts_at && $today->lt($window->registration_starts_at)) {
            return 'Registration has not opened yet. Opens on '.$window->registration_starts_at->format('d M Y').'.';
        }

        if ($window->registration_ends_at && $today->gt($window->registration_ends_at)) {
            return 'Registration window closed on '.$window->registration_ends_at->format('d M Y').'. Contact your Sahodaya office.';
        }

        return null;
    }
}
