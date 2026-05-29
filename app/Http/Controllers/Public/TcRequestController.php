<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\TcRequestReceived;
use App\Models\TcRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

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

        // Notify school admin
        $contactEmail = $tenant->settings()->where('key', 'contact')->first()?->value['email'] ?? null;
        if ($contactEmail) {
            Mail::to($contactEmail)->queue(new TcRequestReceived($tcRequest, $tenant));
        }

        return back()->with('tc_success',
            'Your TC request has been submitted. We will notify you when it is ready for collection.');
    }
}
