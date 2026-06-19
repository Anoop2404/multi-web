<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\MembershipNotifier;
use App\Services\Mail\SahodayaMailer;
use App\Support\SahodayaHomepageContent;
use App\Support\SchoolApplicationForm;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
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
                'school_email' => 'An account with this Gmail address already exists.',
            ]);
        }

        $payload = SchoolApplicationForm::buildPayload($data, $fields);
        $payload['school_name'] = $data['school_name'];

        $schoolPrefix = strtoupper(trim($data['school_prefix'] ?? ''));

        $school = Tenant::create([
            'id'                  => (string) Str::uuid(),
            'type'                => 'school',
            'name'                => $data['school_name'],
            'parent_id'           => $sahodaya->id,
            'subdomain'           => $data['requested_subdomain'] ?? null,
            'school_prefix'       => $schoolPrefix,
            'membership_status'   => 'pending',
            'is_active'           => true,
            'application_payload' => $payload,
        ]);

        $plainPassword = Str::password(12);

        $user = User::create([
            'tenant_id'      => $school->id,
            'name'           => $data['school_name'],
            'email'          => $email,
            'password'       => Hash::make($plainPassword),
            'plain_password' => $plainPassword,
        ]);
        $user->assignRole('school_admin');

        $mailer = SahodayaMailer::for($sahodaya->id);
        $mailFailed = false;

        try {
            $mailer->sendVerification($user);
        } catch (\Throwable $e) {
            report($e);
            $mailFailed = true;
        }

        try {
            $notifier->schoolCredentialsIssued($user, $plainPassword, $school);
        } catch (\Throwable $e) {
            report($e);
            $mailFailed = true;
        }

        try {
            $notifier->schoolApplicationSubmitted($school);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($mailFailed) {
            return back()->with(
                'success',
                'Application submitted and your account was created, but we could not send email. Contact the Sahodaya office for your login password, or try again after mail settings are fixed.',
            );
        }

        return back()->with('success', 'Application submitted. Check your Gmail for a verification link and login password. Your application is pending Sahodaya approval.');
    }
}
