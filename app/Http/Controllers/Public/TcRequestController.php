<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\TcRequest;
use App\Models\Tenant;
use App\Services\Mail\SchoolSiteMailer;
use App\Support\Mail\EmailBranding;
use Illuminate\Http\Request;

class TcRequestController extends Controller
{
    public function store(Request $request)
    {
        $tenant = tenancy()->tenant;

        abort_if(!$tenant, 404);

        $data = $request->validate([
            'student_name'    => 'required|string|max:255',
            'admission_number'=> 'required|string|max:100',
            'class'           => 'required|string|max:20',
            'division'        => 'nullable|string|max:10',
            'dob'             => 'required|date',
            'parent_name'     => 'required|string|max:255',
            'phone'           => 'required|string|max:30',
            'email'           => 'nullable|email|max:255',
            'reason'          => 'required|string|max:2000',
        ]);

        $data['tenant_id']     = $tenant->id;
        $data['status']        = 'pending';
        $data['academic_year'] = now()->year . '-' . (now()->year + 1);

        $tcRequest = TcRequest::create($data);

        $sahodaya = $tenant->parent_id ? Tenant::query()->find($tenant->parent_id) : null;
        app(SchoolSiteMailer::class)->sendToSchoolContact(
            $tenant,
            "TC Request – {$tcRequest->student_name} (Adm: {$tcRequest->admission_number})",
            'emails.tc-request',
            array_merge(
                EmailBranding::forTenant($sahodaya ?? $tenant),
                [
                    'tcRequest'      => $tcRequest,
                    'school'         => $tenant,
                    'headerTitle'    => 'Transfer Certificate Request',
                    'headerSubtitle' => $tenant->name,
                    'headerEyebrow'  => 'TC Portal',
                    'footerNote'     => 'Submitted via '.$tenant->name.' TC Portal',
                ],
            ),
        );

        return back()->with('tc_success',
            'Your TC request has been submitted. We will notify you when it is ready for collection.');
    }
}
