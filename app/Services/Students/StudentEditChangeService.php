<?php

namespace App\Services\Students;

use App\Models\Student;
use App\Models\StudentEditChangeRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentEditChangeService
{
    public function __construct(
        private StudentEditLockService $lockService,
        private StudentRecordCreator $recordCreator,
    ) {}

    /** @return array<string, mixed> */
    public function validatedChanges(Request $request, Tenant $school, bool $requireDob = false): array
    {
        return $request->validate([
            'school_class_id' => [
                'required',
                Rule::exists('school_classes', 'id')->where('tenant_id', $school->id),
            ],
            'name'         => 'required|string|max:255',
            'gender'       => 'required|in:male,female,other',
            'dob'          => ($requireDob ? 'required' : 'nullable').'|date|before:today',
            'parent_email' => 'nullable|email|max:255',
            'photo'        => 'nullable|image|max:2048',
            'reason'       => 'required|string|max:2000',
        ]);
    }

    public function submitUpdate(
        Request $request,
        Tenant $school,
        Student $student,
        ?int $userId = null,
        ?string $submittedByRole = null,
    ): StudentEditChangeRequest {
        $this->assertChangeRequestAllowed($school, 'edit');

        abort_if($student->tenant_id !== $school->id, 403);

        $data = $this->validatedChanges($request, $school);
        $reason = $data['reason'];
        unset($data['reason']);

        $photoPath = $this->storePhotoIfPresent($request, $school);

        $this->assertNoPendingForStudent($school, $student->id);

        return $this->createRequest($school, [
            'student_id'   => $student->id,
            'change_type'  => 'update',
            'changes_json' => collect($data)->except('photo')->all(),
            'photo_path'   => $photoPath,
            'reason'       => $reason,
        ], $userId, $submittedByRole);
    }

    public function submitCreate(
        Request $request,
        Tenant $school,
        ?int $userId = null,
        ?string $submittedByRole = null,
    ): StudentEditChangeRequest {
        $this->assertChangeRequestAllowed($school, 'add');

        $data = $this->validatedChanges($request, $school, requireDob: true);
        $reason = $data['reason'];
        unset($data['reason']);

        if (! $request->hasFile('photo')) {
            throw ValidationException::withMessages([
                'photo' => 'Profile photo is required for new student requests.',
            ]);
        }

        $photoPath = $this->storePhotoIfPresent($request, $school);

        $pendingCreate = StudentEditChangeRequest::where('school_id', $school->id)
            ->where('change_type', 'create')
            ->where('status', 'pending')
            ->where('changes_json->name', $data['name'])
            ->exists();

        if ($pendingCreate) {
            throw ValidationException::withMessages([
                'reason' => 'A pending new-student request with this name already exists.',
            ]);
        }

        return $this->createRequest($school, [
            'student_id'   => null,
            'change_type'  => 'create',
            'changes_json' => collect($data)->except('photo')->all(),
            'photo_path'   => $photoPath,
            'reason'       => $reason,
        ], $userId, $submittedByRole);
    }

    public function approveAtSchool(StudentEditChangeRequest $request, ?int $reviewerId = null, ?string $note = null): StudentEditChangeRequest
    {
        abort_if($request->school_approval_status !== 'pending_school', 422, 'Request is not pending school review.');
        abort_if($request->status !== 'pending', 422, 'Request is not pending.');

        $state = $this->lockService->resolveWindowState(Tenant::findOrFail($request->school_id));
        $needsSahodaya = ! $state['can_edit'] && ! $state['can_add'];

        if (! $needsSahodaya) {
            $student = $this->applyChanges($request, $reviewerId, $note);

            $request->update([
                'school_approval_status' => 'school_approved',
                'school_approved_by'     => $reviewerId,
                'school_approved_at'     => now(),
                'status'                 => 'approved',
                'reviewed_by_user_id'    => $reviewerId,
                'reviewed_at'            => now(),
                'resolution_note'        => $note,
                'student_id'             => $student->id,
            ]);

            return $request->fresh();
        }

        $request->update([
            'school_approval_status' => 'school_approved',
            'school_approved_by'     => $reviewerId,
            'school_approved_at'     => now(),
            'escalation_type'        => 'via_school_principal',
        ]);

        return $request->fresh();
    }

    public function rejectAtSchool(StudentEditChangeRequest $request, ?int $reviewerId = null, ?string $note = null): void
    {
        abort_if($request->school_approval_status !== 'pending_school', 422, 'Request is not pending school review.');

        $request->update([
            'school_approval_status' => 'school_rejected',
            'school_approved_by'     => $reviewerId,
            'school_approved_at'     => now(),
            'school_rejection_note'  => $note,
            'status'                 => 'rejected',
            'reviewed_by_user_id'    => $reviewerId,
            'reviewed_at'            => now(),
            'resolution_note'        => $note,
        ]);
    }

    public function approve(StudentEditChangeRequest $request, ?int $reviewerId = null, ?string $note = null): Student
    {
        abort_if($request->status !== 'pending', 422, 'Request is not pending.');
        abort_if($request->school_approval_status === 'pending_school', 422, 'Awaiting school-level approval first.');

        $student = $this->applyChanges($request, $reviewerId, $note);

        $request->update([
            'status'              => 'approved',
            'student_id'          => $student->id,
            'resolution_note'     => $note,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at'         => now(),
        ]);

        return $student;
    }

    public function reject(StudentEditChangeRequest $request, ?int $reviewerId = null, ?string $note = null): void
    {
        abort_if($request->status !== 'pending', 422, 'Request is not pending.');

        $request->update([
            'status'              => 'rejected',
            'resolution_note'     => $note,
            'reviewed_by_user_id' => $reviewerId,
            'reviewed_at'         => now(),
        ]);
    }

    public static function submittedByRole(?User $user): ?string
    {
        if (! $user) {
            return 'school_staff';
        }

        if ($user->hasRole('school_vice_principal')) {
            return 'school_vice_principal';
        }
        if ($user->hasRole('school_principal')) {
            return 'school_principal';
        }
        if ($user->hasRole('school_admin')) {
            return 'school_admin';
        }
        if ($user->hasRole('student')) {
            return 'student';
        }
        if ($user->hasRole('teacher')) {
            return 'teacher';
        }

        return 'school_staff';
    }

    private function createRequest(Tenant $school, array $payload, ?int $userId, ?string $submittedByRole): StudentEditChangeRequest
    {
        $state = $this->lockService->resolveWindowState($school);

        return StudentEditChangeRequest::create(array_merge($payload, [
            'school_id'              => $school->id,
            'status'                 => 'pending',
            'school_approval_status' => 'pending_school',
            'requested_by_user_id'   => $userId,
            'submitted_by_role'      => $submittedByRole ?? 'school_staff',
            'escalation_type'        => ($state['can_add'] || $state['can_edit'])
                ? 'direct_to_sahodaya'
                : 'via_school_principal',
        ]));
    }

    private function applyChanges(StudentEditChangeRequest $request, ?int $reviewerId, ?string $note): Student
    {
        $school = Tenant::findOrFail($request->school_id);
        $changes = $request->changes_json ?? [];

        if ($request->change_type === 'update') {
            $student = Student::where('tenant_id', $school->id)->findOrFail($request->student_id);
            $before = $student->only(array_keys($changes));

            if ($request->photo_path) {
                $changes['photo'] = $request->photo_path;
            }

            $student->update($changes);

            app(DataChangeLogger::class)->updated(
                $student,
                "Student updated via approved change request #{$request->id}",
                DataChangeLogger::diff($before, $student->only(array_keys($changes))),
                $school->id,
                'students',
                ['change_request_id' => $request->id],
            );
        } elseif ($request->change_type === 'create') {
            if ($request->photo_path) {
                $changes['photo'] = $request->photo_path;
            }

            $student = $this->recordCreator->create($school, $changes);

            app(DataChangeLogger::class)->created(
                $student,
                "Student created via approved change request #{$request->id}",
                $school->id,
                'students',
                ['change_request_id' => $request->id],
            );
        } else {
            throw ValidationException::withMessages(['change_type' => 'Unsupported change type.']);
        }

        return $student;
    }

    private function assertChangeRequestAllowed(Tenant $school, string $action): void
    {
        $state = $this->lockService->resolveWindowState($school);

        if ($state['source'] === 'emergency_lock') {
            throw ValidationException::withMessages([
                'reason' => 'Record edits are currently frozen. Contact your school admin.',
            ]);
        }

        if ($action === 'add' && $state['can_add']) {
            throw ValidationException::withMessages([
                'reason' => 'Adding students is open — add the student directly.',
            ]);
        }

        if ($action === 'edit' && $state['can_edit']) {
            throw ValidationException::withMessages([
                'reason' => 'Records are not locked — edit the student directly.',
            ]);
        }
    }

    private function assertNoPendingForStudent(Tenant $school, int $studentId): void
    {
        $pending = StudentEditChangeRequest::where('school_id', $school->id)
            ->where('student_id', $studentId)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            throw ValidationException::withMessages([
                'reason' => 'A pending change request already exists for this student.',
            ]);
        }
    }

    private function storePhotoIfPresent(Request $request, Tenant $school): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        return TenantStorage::storeStudentPhoto($request->file('photo'), $school->id);
    }
}
