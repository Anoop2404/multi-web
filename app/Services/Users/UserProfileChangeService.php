<?php

namespace App\Services\Users;

use App\Models\User;
use App\Models\UserProfileChangeRequest;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserProfileChangeService
{
    public function __construct(
        private \App\Services\Students\StudentEditLockService $lockService,
    ) {}

    /** @return array<string, mixed> */
    public function validatedSelfEdit(Request $request): array
    {
        return $request->validate([
            'name'   => 'required|string|max:255',
            'email'  => 'required|email|max:255',
            'phone'  => 'nullable|string|max:30',
            'photo'  => 'nullable|image|max:2048',
            'reason' => 'nullable|string|max:2000',
        ]);
    }

    public function submit(User $user, Request $request, ?string $schoolId = null): UserProfileChangeRequest
    {
        if ($schoolId) {
            $school = \App\Models\Tenant::findOrFail($schoolId);
            $state = $this->lockService->resolveWindowState($school);
            if ($state['source'] === 'emergency_lock') {
                throw ValidationException::withMessages([
                    'reason' => 'Record edits are currently frozen. Contact your school admin.',
                ]);
            }
        }

        $data = $this->validatedSelfEdit($request);
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = TenantStorage::storeStudentPhoto($request->file('photo'), $schoolId ?? $user->tenant_id);
            $data['photo_path'] = $photoPath;
        }

        unset($data['photo']);

        return UserProfileChangeRequest::create([
            'user_id'                => $user->id,
            'school_id'              => $schoolId ?? $user->tenant_id,
            'changes_json'           => $data,
            'reason'                 => $reason,
            'status'                 => 'pending_school',
            'school_approval_status' => 'pending',
        ]);
    }

    public function approveAtSchool(UserProfileChangeRequest $changeRequest, ?int $reviewerId = null, ?string $note = null): UserProfileChangeRequest
    {
        abort_if($changeRequest->status !== 'pending_school', 422, 'Request is not pending school review.');

        $school = $changeRequest->school_id
            ? \App\Models\Tenant::find($changeRequest->school_id)
            : null;

        $needsSahodaya = $school && ! $this->lockService->resolveWindowState($school)['can_edit'];

        if (! $needsSahodaya) {
            $this->apply($changeRequest);
            $changeRequest->update([
                'status'                 => 'approved',
                'school_approval_status' => 'approved',
                'school_approved_by'     => $reviewerId,
                'school_approved_at'     => now(),
                'resolution_note'        => $note,
            ]);

            return $changeRequest->fresh();
        }

        $changeRequest->update([
            'status'                 => 'sahodaya_pending',
            'school_approval_status' => 'approved',
            'school_approved_by'     => $reviewerId,
            'school_approved_at'     => now(),
        ]);

        return $changeRequest->fresh();
    }

    public function rejectAtSchool(UserProfileChangeRequest $changeRequest, ?int $reviewerId = null, ?string $note = null): void
    {
        abort_if($changeRequest->status !== 'pending_school', 422, 'Request is not pending school review.');

        $changeRequest->update([
            'status'                 => 'school_rejected',
            'school_approval_status' => 'rejected',
            'school_approved_by'     => $reviewerId,
            'school_approved_at'     => now(),
            'resolution_note'        => $note,
        ]);
    }

    public function approveAtSahodaya(UserProfileChangeRequest $changeRequest, ?int $reviewerId = null, ?string $note = null): User
    {
        abort_if($changeRequest->status !== 'sahodaya_pending', 422, 'Request is not pending Sahodaya review.');

        $user = $this->apply($changeRequest);

        $changeRequest->update([
            'status'               => 'approved',
            'sahodaya_approved_by' => $reviewerId,
            'sahodaya_approved_at' => now(),
            'resolution_note'      => $note,
        ]);

        return $user;
    }

    public function rejectAtSahodaya(UserProfileChangeRequest $changeRequest, ?int $reviewerId = null, ?string $note = null): void
    {
        abort_if($changeRequest->status !== 'sahodaya_pending', 422, 'Request is not pending Sahodaya review.');

        $changeRequest->update([
            'status'               => 'rejected',
            'sahodaya_approved_by' => $reviewerId,
            'sahodaya_approved_at' => now(),
            'resolution_note'      => $note,
        ]);
    }

    private function apply(UserProfileChangeRequest $changeRequest): User
    {
        $user = User::findOrFail($changeRequest->user_id);
        $changes = $changeRequest->changes_json ?? [];

        $updates = [];
        foreach (['name', 'email'] as $field) {
            if (isset($changes[$field])) {
                $updates[$field] = $field === 'email'
                    ? strtolower(trim((string) $changes[$field]))
                    : $changes[$field];
            }
        }

        if (! empty($updates)) {
            $user->update($updates);
        }

        return $user->fresh();
    }
}
