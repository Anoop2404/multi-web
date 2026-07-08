<?php

namespace App\Services\Students;

use App\Models\SahodayaProfile;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolLockOverride;
use App\Models\Tenant;
use App\Support\AcademicYear;
use Carbon\CarbonInterface;

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
            'lock_at'             => null,
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

        $global = $this->stateFromGlobalWindow($school->parent_id);
        if ($global) {
            return $global;
        }

        return [
            'can_add'             => false,
            'can_edit'            => false,
            'source'              => 'global_window',
            'message'             => 'Student add/edit windows are closed. Submit a change request or contact Sahodaya.',
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

    /** @return array{can_add: bool, can_edit: bool, source: string, message: ?string, override_expires_at: ?string}|null */
    private function stateFromGlobalWindow(?string $sahodayaId): ?array
    {
        if (! $sahodayaId) {
            return null;
        }

        $year = AcademicYear::forSahodaya($sahodayaId);
        $window = SahodayaRegistrationWindow::where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $year)
            ->first();

        if (! $window) {
            return null;
        }

        $hasStudentWindow = $window->add_open || $window->add_close || $window->edit_open || $window->edit_close;
        if (! $hasStudentWindow) {
            return null;
        }

        $now = now();
        $canAdd = $this->withinWindow($window->add_open, $window->add_close, $now);
        $canEdit = $this->withinWindow($window->edit_open, $window->edit_close, $now);

        $message = null;
        if (! $canAdd && ! $canEdit) {
            $message = 'Student add/edit windows are closed for '.$year.'. Submit a change request or contact Sahodaya.';
        } elseif (! $canAdd) {
            $message = 'Adding new students is closed for '.$year.'.';
        } elseif (! $canEdit) {
            $message = 'Editing existing students is closed for '.$year.'.';
        }

        return [
            'can_add'             => $canAdd,
            'can_edit'            => $canEdit,
            'source'              => 'global_window',
            'message'             => $message,
            'override_expires_at' => null,
        ];
    }

    private function withinWindow(?CarbonInterface $open, ?CarbonInterface $close, CarbonInterface $now): bool
    {
        if (! $open && ! $close) {
            return false;
        }

        if ($open && $now->lt($open)) {
            return false;
        }

        if ($close && $now->gt($close)) {
            return false;
        }

        return true;
    }
}
