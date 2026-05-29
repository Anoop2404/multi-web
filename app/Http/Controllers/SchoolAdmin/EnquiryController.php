<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\AdmissionEnquiry;
use Illuminate\Http\Request;

class EnquiryController extends SchoolAdminController
{
    public function index()
    {
        $enquiries = AdmissionEnquiry::where('tenant_id', $this->school->id)
            ->orderByDesc('created_at')
            ->paginate(30);

        $counts = [
            'new'        => AdmissionEnquiry::where('tenant_id', $this->school->id)->where('status', 'new')->count(),
            'reviewed'   => AdmissionEnquiry::where('tenant_id', $this->school->id)->where('status', 'reviewed')->count(),
            'shortlisted'=> AdmissionEnquiry::where('tenant_id', $this->school->id)->where('status', 'shortlisted')->count(),
        ];

        return $this->inertia('School/Enquiries/Index', compact('enquiries', 'counts'));
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
