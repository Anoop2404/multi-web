<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\StudentEditChangeRequest;
use App\Services\Students\StudentEditChangeService;
use Illuminate\Http\Request;

class StudentEditChangeRequestController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $requests = StudentEditChangeRequest::query()
            ->forSahodaya($this->sahodaya->id)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($status === 'pending', fn ($q) => $q->whereIn('school_approval_status', ['school_approved', 'bypassed']))
            ->with(['student.schoolClass', 'school:id,name'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $base = StudentEditChangeRequest::forSahodaya($this->sahodaya->id);

        $counts = [
            'pending'  => (clone $base)->where('status', 'pending')
                ->whereIn('school_approval_status', ['school_approved', 'bypassed'])
                ->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];

        return $this->inertia('Sahodaya/Students/ChangeRequests', [
            'requests'     => $requests,
            'counts'       => $counts,
            'filterStatus' => $status,
        ]);
    }

    public function approve(Request $request, string $tenantId, StudentEditChangeRequest $changeRequest, StudentEditChangeService $service)
    {
        abort_if($changeRequest->school?->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'resolution_note' => 'nullable|string|max:2000',
        ]);

        $service->approve($changeRequest, $request->user()?->id, $data['resolution_note'] ?? null);

        return back()->with('success', 'Change request approved and applied.');
    }

    public function reject(Request $request, string $tenantId, StudentEditChangeRequest $changeRequest, StudentEditChangeService $service)
    {
        abort_if($changeRequest->school?->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'resolution_note' => 'nullable|string|max:2000',
        ]);

        $service->reject($changeRequest, $request->user()?->id, $data['resolution_note'] ?? null);

        return back()->with('success', 'Change request rejected.');
    }
}
