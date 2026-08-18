<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\MembershipNotifier;
use App\Support\SahodayaHomepageContent;
use App\Support\SchoolApplicationForm;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolApplicationController extends Controller
{
    public function create()
    {
        $tenant = tenant();

        if ($tenant->type !== 'sahodaya') {
            abort(404);
        }

        $profile  = SahodayaProfile::firstOrCreate(['tenant_id' => $tenant->id]);
        $branding = SahodayaHomepageContent::get($tenant);
        $fields   = SchoolApplicationForm::resolve($profile);

        return view('public.school-application', [
            'sahodaya'            => $tenant,
            'logoUrl'             => TenantBranding::logoUrl($tenant),
            'eyebrow'             => $branding['eyebrow'] ?? null,
            'tagline'             => $branding['tagline'] ?? null,
            'motto'               => $branding['motto'] ?? null,
            'phone'               => $branding['phone'] ?? null,
            'email'               => $branding['email'] ?? null,
            'fields'              => $fields,
            'highestClassOptions' => SchoolApplicationForm::highestClassOptions(),
        ]);
    }

    public function store(Request $request, MembershipNotifier $notifier)
    {
        $sahodaya = tenant();

        if ($sahodaya->type !== 'sahodaya') {
            abort(404);
        }

        $profile = SahodayaProfile::firstOrCreate(['tenant_id' => $sahodaya->id]);
        $fields  = SchoolApplicationForm::resolve($profile);

        $data = $request->validate(SchoolApplicationForm::validationRules($fields, $sahodaya));

        $email = strtolower(trim($data['school_email'] ?? ''));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'school_email' => 'An account with this email address already exists.',
            ]);
        }

        $payload = SchoolApplicationForm::buildPayload($data, $fields);
        $payload['school_name'] = $data['school_name'];
        // Always captured regardless of the form's field-visibility config — approval
        // needs a reliable email to create the login with, not a conditionally-stored one.
        $payload['school_email'] = $email;

        $schoolPrefix = strtoupper(trim($data['school_prefix'] ?? ''));

        // No User account (and no credentials) is created here — only after a Sahodaya
        // admin approves the application (see MemberSchoolsController::approveSchool()).
        // Previously this created the login and emailed the password immediately on
        // submission, before anyone had reviewed the application.
        $school = Tenant::create([
            'id'                  => (string) Str::uuid(),
            'type'                => 'school',
            'name'                => $data['school_name'],
            'parent_id'           => $sahodaya->id,
            'subdomain'           => $data['requested_subdomain'] ?? null,
            'school_prefix'       => $schoolPrefix,
            'membership_status'   => 'pending',
            'is_non_affiliated'   => ($data['school_category'] ?? 'affiliated') === 'non_affiliated',
            'is_active'           => true,
            'application_payload' => $payload,
        ]);

        try {
            $notifier->schoolApplicationSubmitted($school);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Application submitted. The Sahodaya office will review it — your login and password will be emailed once your school is approved.');
    }
}
