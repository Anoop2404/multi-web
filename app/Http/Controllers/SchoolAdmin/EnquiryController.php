<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\AdmissionEnquiry;
use Illuminate\Http\Request;

class EnquiryController extends SchoolAdminController
{
    public function index(Request $request)
    {
        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString();

        $enquiries = AdmissionEnquiry::where('tenant_id', $this->school->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested->where('student_name', 'like', "%{$search}%")
                        ->orWhere('parent_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('class_applying', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['new', 'reviewed', 'shortlisted', 'rejected'], true), fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $counts = AdmissionEnquiry::where('tenant_id', $this->school->id)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return $this->inertia('School/Enquiries/Index', [
            'enquiries' => $enquiries,
            'counts' => $counts,
            'filters' => compact('search', 'status'),
        ]);
    }

    public function update(Request $request, string $tenantId, AdmissionEnquiry $enquiry)
    {
        abort_if($enquiry->tenant_id !== $this->school->id, 403);

        $data = $request->validate([
            'status'      => 'required|in:new,reviewed,shortlisted,rejected',
            'admin_notes' => 'nullable|string|max:2000',
        ]);

        $enquiry->update($data);

        return back()->with('success', 'Status updated.');
    }
}
