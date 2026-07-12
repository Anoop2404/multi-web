<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Api\ApiController;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Membership\MembershipNotifier;
use App\Services\Mail\SahodayaMailer;
use App\Support\SchoolApplicationForm;
use App\Support\TenantBranding;
use App\Support\TenancyDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SchoolApplicationApiController extends ApiController
{
    public function form(Request $request)
    {
        $sahodaya = $this->resolveSahodaya($request);

        $profile = $this->resolveProfile($sahodaya);
        $fields  = SchoolApplicationForm::resolve($profile);
        $logoPath = TenantBranding::logoUrl($sahodaya);
        $logoUrl = $logoPath
            ? (str_starts_with($logoPath, 'http') ? $logoPath : url($logoPath))
            : null;

        $hasSchoolStep = collect($fields)->where('group', 'school')->where('enabled', true)->isNotEmpty()
            || ($fields['school_name']['enabled'] ?? true);
        $hasStep2 = collect($fields)->whereIn('group', ['principal', 'leadership', 'account'])->where('enabled', true)->isNotEmpty();

        return $this->ok([
            'tenant_name'           => $sahodaya->name,
            'logo_url'              => $logoUrl,
            'fields'                => $fields,
            'highest_class_options' => SchoolApplicationForm::highestClassOptions(),
            'two_step'              => $hasSchoolStep && $hasStep2,
        ]);
    }

    public function validateField(Request $request)
    {
        $sahodaya = $this->resolveSahodaya($request);

        $data = $request->validate([
            'field' => 'required|in:school_email,school_prefix,cbse_affiliation',
            'value' => 'required|string|max:255',
        ]);

        $message = $this->fieldValidationMessage($sahodaya, $data['field'], $data['value']);

        if ($message) {
            throw ValidationException::withMessages([
                $data['field'] => [$message],
            ]);
        }

        return $this->ok(['valid' => true]);
    }

    public function store(Request $request, MembershipNotifier $notifier)
    {
        $sahodaya = $this->resolveSahodaya($request);

        $profile = $this->resolveProfile($sahodaya);
        $fields  = SchoolApplicationForm::resolve($profile);

        $data = $request->validate(SchoolApplicationForm::validationRules($fields, $sahodaya));

        $email = strtolower(trim($data['school_email'] ?? ''));

        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'school_email' => ['An account with this Gmail address already exists.'],
            ]);
        }

        $payload = SchoolApplicationForm::buildPayload($data, $fields);
        $payload['school_name'] = $data['school_name'];

        $schoolPrefix = strtoupper(trim($data['school_prefix'] ?? ''));

        [$school, $user, $plainPassword] = DB::transaction(function () use ($data, $payload, $schoolPrefix, $sahodaya, $email) {
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
                'tenant_id' => $school->id,
                'name'      => $data['school_name'],
                'email'     => $email,
                'password'  => Hash::make($plainPassword),
            ]);
            $user->assignRole('school_admin');

            return [$school, $user, $plainPassword];
        });

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

        $message = $mailFailed
            ? 'Application submitted and your account was created, but we could not send email. Contact the Sahodaya office for your login password.'
            : 'Application submitted. Check your Gmail for a verification link and login password. Your application is pending Sahodaya approval.';

        return $this->message($message, 201, [
            'school_id'   => $school->id,
            'school_name' => $school->name,
            'email'       => $user->email,
        ]);
    }

    private function resolveSahodaya(Request $request): Tenant
    {
        $sahodaya = TenantBranding::resolveTenant($request);

        if (! $sahodaya || $sahodaya->type !== 'sahodaya') {
            abort(404, 'School registration is not available for this portal.');
        }

        return $sahodaya;
    }

    private function fieldValidationMessage(Tenant $sahodaya, string $field, string $value): ?string
    {
        return match ($field) {
            'school_email' => ! SchoolApplicationForm::isGmailAddress($value)
                ? 'Login email must be a valid Gmail address (@gmail.com).'
                : (User::where('email', strtolower(trim($value)))->exists()
                    ? 'An account with this Gmail address already exists.'
                    : null),
            'school_prefix' => SchoolApplicationForm::prefixIsTaken($sahodaya, $value)
                ? 'This school code is already in use within this Sahodaya.'
                : null,
            'cbse_affiliation' => SchoolApplicationForm::affiliationIsTaken($sahodaya, $value)
                ? 'This CBSE affiliation number is already registered.'
                : null,
            default => null,
        };
    }

    private function resolveProfile(Tenant $sahodaya): ?SahodayaProfile
    {
        return TenancyDatabase::whenDatabaseReady(
            $sahodaya,
            fn () => SahodayaProfile::firstOrCreate(['tenant_id' => $sahodaya->id]),
        );
    }
}
