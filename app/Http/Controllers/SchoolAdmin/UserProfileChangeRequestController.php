<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\UserProfileChangeRequest;
use App\Services\Users\UserProfileChangeService;
use Illuminate\Http\Request;

class UserProfileChangeRequestController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $requests = UserProfileChangeRequest::where('school_id', $this->school->id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['user:id,name,email'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return $this->inertia('School/Users/ProfileChangeRequests', [
            'requests' => $requests,
            'filters'  => ['status' => $status],
        ]);
    }

    public function approve(Request $request, string $tenantId, UserProfileChangeRequest $changeRequest, UserProfileChangeService $service)
    {
        abort_if($changeRequest->school_id !== $this->school->id, 403);

        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $service->approveAtSchool($changeRequest, $request->user()?->id, $data['note'] ?? null);

        return back()->with('success', 'Profile change approved and applied.');
    }

    public function reject(Request $request, string $tenantId, UserProfileChangeRequest $changeRequest, UserProfileChangeService $service)
    {
        abort_if($changeRequest->school_id !== $this->school->id, 403);

        $data = $request->validate([
            'note' => 'nullable|string|max:500',
        ]);

        $service->rejectAtSchool($changeRequest, $request->user()?->id, $data['note'] ?? null);

        return back()->with('success', 'Profile change request rejected.');
    }
}
