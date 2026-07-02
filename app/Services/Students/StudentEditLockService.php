<?php

namespace App\Services\Students;

use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolLockOverride;
use App\Models\Tenant;
use App\Support\AcademicYear;

class StudentEditLockService
{
    public function profileForSchool(Tenant $school): ?SahodayaProfile
    {
        if (! $school->parent_id) {
            return null;
        }

        return SahodayaProfile::where('tenant_id', $school->parent_id)->first();
    }

    public function isLockedForSchool(Tenant $school): bool
    {
        $state = $this->resolveWindowState($school);

        return ! $state['can_add'] && ! $state['can_edit'];
    }

    /** @return array{locked: bool, lock_at: ?string, manual_lock: bool, message: ?string, can_add: bool, can_edit: bool, source: string, override_expires_at: ?string} */
    public function metaForSchool(Tenant $school): array
    {
        $state = $this->resolveWindowState($school);
        $profile = $this->profileForSchool($school);

        return [
            'locked'              => ! $state['can_add'] && ! $state['can_edit'],
            'lock_at'             => $profile?->student_edit_lock_at?->toIso8601String(),
            'manual_lock'         => (bool) ($profile?->student_edit_lock_enabled ?? false),
            'message'             => $state['message'],
            'can_add'             => $state['can_add'],
            'can_edit'            => $state['can_edit'],
            'source'              => $state['source'],
            'override_expires_at' => $state['override_expires_at'],
        ];
    }

    /**
     * @return array{can_add: bool, can_edit: bool, source: string, message: ?string, override_expires_at: ?string}
     */
    public function resolveWindowState(Tenant $school): array
    {
        $profile = $this->profileForSchool($school);

        if ($profile?->student_edit_lock_enabled) {
            return [
                'can_add'             => false,
                'can_edit'            => false,
                'source'              => 'emergency_lock',
                'message'             => 'Student records are frozen by Sahodaya. No edits or additions are allowed.',
                'override_expires_at' => null,
            ];
        }

        $override = SchoolLockOverride::where('school_id', $school->id)
            ->active()
            ->latest('id')
            ->first();

        if ($override) {
            return $this->stateFromOverride($override);
        }

        $window = $this->registrationWindowForSchool($school);
        if ($window) {
            return $this->stateFromGlobalWindow($window);
        }

        if ($profile?->student_edit_lock_at && now()->gte($profile->student_edit_lock_at)) {
            return [
                'can_add'             => false,
                'can_edit'            => false,
                'source'              => 'global_window',
                'message'             => 'Student edit window closed on '.$profile->student_edit_lock_at->timezone(config('app.timezone'))->format('d M Y, h:i A').'. Submit a change request for approval.',
                'override_expires_at' => null,
            ];
        }

        return [
            'can_add'             => false,
            'can_edit'            => false,
            'source'              => 'global_window',
            'message'             => 'Student registration and edit windows are closed. Submit a change request for approval.',
            'override_expires_at' => null,
        ];
    }

    public function assertCanAdd(Tenant $school): void
    {
        $state = $this->resolveWindowState($school);
        if (! $state['can_add']) {
            abort(422, $state['message'] ?? 'Adding students is not allowed right now.');
        }
    }

    public function assertCanEdit(Tenant $school): void
    {
        $state = $this->resolveWindowState($school);
        if (! $state['can_edit']) {
            abort(422, $state['message'] ?? 'Editing students is not allowed right now.');
        }
    }

    /** @deprecated Use assertCanAdd or assertCanEdit */
    public function assertEditable(Tenant $school): void
    {
        $state = $this->resolveWindowState($school);
        if (! $state['can_add'] && ! $state['can_edit']) {
            abort(422, $state['message'] ?? 'Student records are locked.');
        }
    }

    private function registrationWindowForSchool(Tenant $school): ?SahodayaRegistrationWindow
    {
        if (! $school->parent_id) {
            return null;
        }

        $year = AcademicYear::forSahodaya($school->parent_id);

        return SahodayaRegistrationWindow::where('sahodaya_id', $school->parent_id)
            ->where('academic_year', $year)
            ->first();
    }

    /** @return array{can_add: bool, can_edit: bool, source: string, message: ?string, override_expires_at: ?string} */
    private function stateFromOverride(SchoolLockOverride $override): array
    {
        $canAdd = in_array($override->override_type, ['unlock_add', 'unlock_all'], true);
        $canEdit = in_array($override->override_type, ['unlock_edit', 'unlock_all'], true);

        if (in_array($override->override_type, ['lock_add', 'lock_all'], true)) {
            $canAdd = false;
        }
        if (in_array($override->override_type, ['lock_edit', 'lock_all'], true)) {
            $canEdit = false;
        }

        $expires = $override->expires_at?->toIso8601String();

        return [
            'can_add'             => $canAdd,
            'can_edit'            => $canEdit,
            'source'              => 'school_override',
            'message'             => $canAdd || $canEdit
                ? 'Special access granted by Sahodaya.'.($expires ? ' Expires '.$override->expires_at->timezone(config('app.timezone'))->format('d M Y, h:i A').'.' : '')
                : 'This school is locked by Sahodaya override.',
            'override_expires_at' => $expires,
        ];
    }

    /** @return array{can_add: bool, can_edit: bool, source: string, message: ?string, override_expires_at: ?string} */
    private function stateFromGlobalWindow(SahodayaRegistrationWindow $window): array
    {
        $now = now();

        $addOpen = $window->add_open ?? $window->registration_starts_at;
        $addClose = $window->add_close ?? $window->registration_ends_at;
        $editOpen = $window->edit_open;
        $editClose = $window->edit_close;

        $canAdd = $this->isWithinWindow($addOpen, $addClose, $now);
        $canEdit = $this->isWithinWindow($editOpen, $editClose, $now);

        if (! $canAdd && ! $canEdit && $addOpen === null && $addClose === null && $editOpen === null && $editClose === null) {
            $canAdd = $this->isWithinWindow($window->registration_starts_at, $window->registration_ends_at, $now);
            $canEdit = $canAdd;
        }

        $message = null;
        if (! $canAdd && ! $canEdit) {
            $message = 'Student registration and edit windows are closed. Submit a change request for approval.';
        } elseif (! $canAdd) {
            $message = 'Adding new students is closed. You may still edit existing records or submit change requests.';
        } elseif (! $canEdit) {
            $message = 'Editing existing students is closed. You may still add new students or submit change requests.';
        }

        return [
            'can_add'             => $canAdd,
            'can_edit'            => $canEdit,
            'source'              => 'global_window',
            'message'             => $message,
            'override_expires_at' => null,
        ];
    }

    private function isWithinWindow(mixed $open, mixed $close, \Carbon\Carbon $now): bool
    {
        if ($open === null && $close === null) {
            return false;
        }

        if ($open !== null && $now->lt($open)) {
            return false;
        }

        if ($close !== null && $now->gt($close)) {
            return false;
        }

        return true;
    }
}
