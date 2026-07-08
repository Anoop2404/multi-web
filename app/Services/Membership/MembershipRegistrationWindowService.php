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

    /** Human-readable reason when new registration cannot start, or null when allowed. */
    public function blockReason(?SahodayaRegistrationWindow $window, ?CarbonInterface $now = null): ?string
    {
        if (! $window) {
            return null;
        }

        $now ??= now();
        $start = $window->registration_starts_at ?? $window->add_open;
        $end = $window->registration_ends_at ?? $window->add_close;

        if ($start && $now->lt($start)) {
            return 'Registration has not opened yet. Opens on '.$start->format('d M Y').'.';
        }

        if ($end && $now->gt($end)) {
            return 'Registration window closed on '.$end->format('d M Y').'. Contact your Sahodaya office.';
        }

        return null;
    }

    /** Human-readable reason when submission edits are blocked, or null when allowed. */
    public function editBlockReason(?SahodayaRegistrationWindow $window, ?CarbonInterface $now = null): ?string
    {
        if (! $window) {
            return null;
        }

        if (! $window->edit_open && ! $window->edit_close) {
            return null;
        }

        $now ??= now();

        if ($window->edit_open && $now->lt($window->edit_open)) {
            return 'Registration edits are not open yet. Opens on '.$window->edit_open->format('d M Y').'.';
        }

        if ($window->edit_close && $now->gt($window->edit_close)) {
            return 'Registration edit window closed on '.$window->edit_close->format('d M Y').'. Contact your Sahodaya office.';
        }

        return null;
    }

    public function isEditWindowOpen(?SahodayaRegistrationWindow $window, ?CarbonInterface $now = null): bool
    {
        return $this->editBlockReason($window, $now) === null;
    }

    /** Normalize V1/V2 window columns for school-facing UI. */
    public function displayPayload(?SahodayaRegistrationWindow $window): ?array
    {
        if (! $window) {
            return null;
        }

        return array_merge($window->toArray(), [
            'display_starts_at' => ($window->registration_starts_at ?? $window->add_open)?->toIso8601String(),
            'display_ends_at'   => ($window->registration_ends_at ?? $window->add_close)?->toIso8601String(),
        ]);
    }
}
