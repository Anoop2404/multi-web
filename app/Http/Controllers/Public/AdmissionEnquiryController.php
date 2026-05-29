<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\AdmissionEnquiryReceived;
use App\Models\AdmissionEnquiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdmissionEnquiryController extends Controller
{
    public function store(Request $request)
    {
        $tenant = tenancy()->tenant;

        abort_if(!$tenant, 404);

        $data = $request->validate([
            'student_name'  => 'required|string|max:255',
            'dob'           => 'required|date',
            'class_applying'=> 'required|string|max:20',
            'parent_name'   => 'required|string|max:255',
            'phone'         => 'required|string|max:30',
            'email'         => 'nullable|email|max:255',
            'address'       => 'nullable|string|max:1000',
            'message'       => 'nullable|string|max:2000',
        ]);

        $data['tenant_id']     = $tenant->id;
        $data['status']        = 'new';
        $data['academic_year'] = now()->year . '-' . (now()->year + 1);

        $enquiry = AdmissionEnquiry::create($data);

        // Notify school admin
        $contactEmail = $tenant->settings()->where('key', 'contact')->first()?->value['email'] ?? null;
        if ($contactEmail) {
            Mail::to($contactEmail)->queue(new AdmissionEnquiryReceived($enquiry, $tenant));
        }

        return back()->with('admission_success',
            'Thank you! Your enquiry has been received. We will contact you shortly.');
    }
}
