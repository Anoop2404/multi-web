<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\AdmissionEnquiry;
use App\Models\Tenant;
use App\Services\Mail\SchoolSiteMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Http\Request;

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

        $sahodaya = $tenant->parent_id ? Tenant::query()->find($tenant->parent_id) : null;
        app(SchoolSiteMailer::class)->sendToSchoolContact(
            $tenant,
            "New Admission Enquiry – {$enquiry->student_name}",
            'emails.admission-enquiry',
            array_merge(
                EmailBranding::forTenant($sahodaya ?? $tenant),
                [
                    'enquiry'        => $enquiry,
                    'school'         => $tenant,
                    'headerTitle'    => 'New Admission Enquiry',
                    'headerSubtitle' => $tenant->name,
                    'headerEyebrow'  => 'Admissions',
                    'footerNote'     => 'Submitted via '.$tenant->name.' Admission Portal',
                ],
            ),
        );

        return back()->with('admission_success',
            'Thank you! Your enquiry has been received. We will contact you shortly.');
    }
}
