<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\StudentEditChangeRequest;
use App\Services\Students\StudentEditChangeService;
use Illuminate\Http\Request;

class StudentChangeRequestController extends SchoolAdminController
{
    public function index()
    {
        abort_unless($this->canReview(), 403);

        $requests = StudentEditChangeRequest::where('school_id', $this->school->id)
            ->where('school_approval_status', 'pending_school')
            ->where('status', 'pending')
            ->with(['student:id,name,reg_no'])
            ->latest()
            ->paginate(20);

        return $this->inertia('School/Students/PendingChangeRequests', [
            'requests'        => $requests,
            'studentEditLock' => app(\App\Services\Students\StudentEditLockService::class)->metaForSchool($this->school),
        ]);
    }

    public function approve(Request $request, string $tenantId, StudentEditChangeRequest $changeRequest, StudentEditChangeService $service)
    {
        abort_unless($this->canReview(), 403);
        abort_if($changeRequest->school_id !== $this->school->id, 403);

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $service->approveAtSchool($changeRequest, $request->user()?->id, $data['resolution_note'] ?? null);

        $message = $changeRequest->fresh()->status === 'approved'
            ? 'Change request approved and applied.'
            : 'Change request approved at school level and sent to Sahodaya for final review.';

        return back()->with('success', $message);
    }

    public function reject(Request $request, string $tenantId, StudentEditChangeRequest $changeRequest, StudentEditChangeService $service)
    {
        abort_unless($this->canReview(), 403);
        abort_if($changeRequest->school_id !== $this->school->id, 403);

        $data = $request->validate(['resolution_note' => 'nullable|string|max:2000']);

        $service->rejectAtSchool($changeRequest, $request->user()?->id, $data['resolution_note'] ?? null);

        return back()->with('success', 'Change request rejected.');
    }

    private function canReview(): bool
    {
        $user = request()->user();

        return $user && $user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal']);
    }
}
