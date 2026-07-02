<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\SchoolLockOverride;
use App\Models\Tenant;
use Illuminate\Http\Request;

class SchoolLockOverrideController extends SahodayaAdminController
{
    public function index(string $tenantId, Tenant $school)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $overrides = SchoolLockOverride::where('school_id', $school->id)
            ->orderByDesc('id')
            ->paginate(20);

        $windowState = app(\App\Services\Students\StudentEditLockService::class)->resolveWindowState($school);

        return $this->inertia('Sahodaya/Schools/LockOverrides', [
            'school'       => $school->only('id', 'name', 'school_prefix'),
            'overrides'    => $overrides,
            'windowState'  => $windowState,
            'overrideTypes'=> [
                'unlock_add'  => 'Unlock add only',
                'unlock_edit' => 'Unlock edit only',
                'unlock_all'  => 'Unlock add and edit',
                'lock_add'    => 'Lock add only',
                'lock_edit'   => 'Lock edit only',
                'lock_all'    => 'Lock add and edit',
            ],
        ]);
    }

    public function store(Request $request, string $tenantId, Tenant $school)
    {
        abort_if($school->parent_id !== $this->sahodaya->id || $school->type !== 'school', 404);

        $data = $request->validate([
            'override_type' => 'required|in:unlock_add,unlock_edit,lock_add,lock_edit,unlock_all,lock_all',
            'reason'        => 'nullable|string|max:2000',
            'expires_at'    => 'nullable|date|after:now',
        ]);

        if (str_starts_with($data['override_type'], 'unlock_') && empty($data['expires_at'])) {
            return back()->withErrors(['expires_at' => 'Expiry date is required for unlock overrides.']);
        }

        SchoolLockOverride::create([
            'sahodaya_id'         => $this->sahodaya->id,
            'school_id'           => $school->id,
            'override_type'       => $data['override_type'],
            'reason'              => $data['reason'] ?? null,
            'expires_at'          => $data['expires_at'] ?? null,
            'created_by_user_id'  => $request->user()?->id,
        ]);

        return back()->with('success', 'Lock override saved.');
    }
}
