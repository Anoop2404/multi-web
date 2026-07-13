<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\UserProfileChangeRequest;
use App\Services\Users\UserProfileChangeService;
use Illuminate\Http\Request;

class UserProfileChangeRequestController extends SahodayaAdminController
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'pending');

        $base = UserProfileChangeRequest::query()
            ->forSahodaya($this->sahodaya->id);

        $requests = (clone $base)
            ->when($status === 'pending', fn ($q) => $q->where('status', 'sahodaya_pending'))
            ->when($status === 'approved', fn ($q) => $q->where('status', 'approved'))
            ->when($status === 'rejected', fn ($q) => $q->whereIn('status', ['rejected', 'school_rejected']))
            ->with(['user:id,name,email', 'school:id,name'])
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'pending'  => (clone $base)->where('status', 'sahodaya_pending')->count(),
            'approved' => (clone $base)->where('status', 'approved')->count(),
            'rejected' => (clone $base)->whereIn('status', ['rejected', 'school_rejected'])->count(),
        ];

        return $this->inertia('Sahodaya/Users/ProfileChangeRequests', [
            'requests'     => $requests,
            'counts'       => $counts,
            'filterStatus' => $status,
        ]);
    }

    public function approve(Request $request, string $tenantId, UserProfileChangeRequest $changeRequest, UserProfileChangeService $service)
    {
        abort_if($changeRequest->school?->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'resolution_note' => 'nullable|string|max:2000',
        ]);

        $service->approveAtSahodaya($changeRequest, $request->user()?->id, $data['resolution_note'] ?? null);

        return back()->with('success', 'Profile change approved and applied.');
    }

    public function reject(Request $request, string $tenantId, UserProfileChangeRequest $changeRequest, UserProfileChangeService $service)
    {
        abort_if($changeRequest->school?->parent_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'resolution_note' => 'nullable|string|max:2000',
        ]);

        $service->rejectAtSahodaya($changeRequest, $request->user()?->id, $data['resolution_note'] ?? null);

        return back()->with('success', 'Profile change rejected.');
    }
}
