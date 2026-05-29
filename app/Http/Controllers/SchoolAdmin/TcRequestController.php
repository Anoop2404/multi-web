<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\TcRequest;
use Illuminate\Http\Request;

class TcRequestController extends SchoolAdminController
{
    public function index()
    {
        $requests = TcRequest::where('tenant_id', $this->school->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        $counts = [
            'pending'    => TcRequest::where('tenant_id', $this->school->id)->where('status', 'pending')->count(),
            'processing' => TcRequest::where('tenant_id', $this->school->id)->where('status', 'processing')->count(),
            'ready'      => TcRequest::where('tenant_id', $this->school->id)->where('status', 'ready')->count(),
        ];

        return $this->inertia('School/TcRequests/Index', compact('requests', 'counts'));
    }

    public function update(Request $request, string $tenantId, TcRequest $tcRequest)
    {
        abort_if($tcRequest->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'status'      => 'required|in:pending,processing,ready,issued',
            'admin_notes' => 'nullable|string|max:2000',
            'issued_date' => 'nullable|date',
        ]);

        $tcRequest->update($data);

        return back()->with('success', 'TC request updated.');
    }
}
