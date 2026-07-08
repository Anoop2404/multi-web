<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\SahodayaProfile;
use App\Models\User;
use App\Services\Audit\DataChangeLogger;
use App\Services\Mail\SahodayaMailer;
use App\Support\SchoolApplicationForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileApiController extends SchoolApiController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $sahodaya = $this->school->parent;
        $profile = $sahodaya
            ? SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
            : null;
        $fields = SchoolApplicationForm::resolve($profile);
        $payload = $this->school->application_payload ?? [];

        $editableFields = collect(SchoolApplicationForm::editableFieldKeys())
            ->filter(fn (string $key) => $fields[$key]['enabled'] ?? false)
            ->map(fn (string $key) => [
                'key'         => $key,
                'label'       => $fields[$key]['label'],
                'placeholder' => $fields[$key]['placeholder'] ?? null,
                'hint'        => $this->profileFieldHint($key),
                'required'    => in_array($key, ['school_prefix', 'cbse_affiliation'], true)
                    ? false
                    : ($fields[$key]['required'] ?? false),
                'group'       => $fields[$key]['group'],
                'disabled'    => $key === 'school_prefix' && $this->school->prefixes_locked && filled($this->school->school_prefix),
            ])
            ->values()
            ->all();

        $readOnly = [
            ['label' => 'School Name', 'value' => $this->school->name],
            ['label' => 'School Code', 'value' => $this->school->school_prefix ?: '—'],
            ['label' => 'CBSE Affiliation', 'value' => SchoolApplicationForm::schoolAffiliation($this->school) ?: '—'],
            ['label' => 'Membership Status', 'value' => ucfirst($this->school->membership_status ?? 'pending')],
        ];

        $profileData = [];
        foreach (SchoolApplicationForm::editableFieldKeys() as $key) {
            if ($fields[$key]['enabled'] ?? false) {
                $profileData[$key] = match ($key) {
                    'school_prefix'    => $this->school->school_prefix ?? '',
                    'cbse_affiliation' => SchoolApplicationForm::schoolAffiliation($this->school) ?? '',
                    default            => $payload[$key] ?? '',
                };
            }
        }

        return $this->ok([
            'profile_data'         => $profileData,
            'editable_fields'      => $editableFields,
            'read_only_fields'     => $readOnly,
            'highest_class_options'=> SchoolApplicationForm::highestClassOptions(),
            'account'              => [
                'name'           => $user->name,
                'email'          => $user->email,
                'email_verified' => (bool) $user->email_verified_at,
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $sahodaya = $this->school->parent;
        abort_unless($sahodaya, 422);

        $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first();
        $fields = SchoolApplicationForm::resolve($profile);

        $data = $request->validate(SchoolApplicationForm::schoolProfileValidationRules($this->school, $fields));

        $before = $this->school->application_payload ?? [];
        $payload = SchoolApplicationForm::mergeProfileUpdate($before, $data, $fields);

        $updates = ['application_payload' => $payload];
        if (
            array_key_exists('school_prefix', $data)
            && filled($data['school_prefix'])
            && ! ($this->school->prefixes_locked && filled($this->school->school_prefix))
        ) {
            $updates['school_prefix'] = strtoupper(trim((string) $data['school_prefix']));
        }

        $this->school->update($updates);

        app(DataChangeLogger::class)->updated(
            $this->school,
            'School registration details updated',
            DataChangeLogger::diff($before, $payload),
            $this->school->id,
            'school_registration',
        );

        return $this->message('Registration details saved.');
    }

    private function profileFieldHint(string $key): ?string
    {
        return match ($key) {
            'school_prefix' => $this->school->prefixes_locked && filled($this->school->school_prefix)
                ? 'Locked because student registration numbers already use this code.'
                : 'Used as the short school code in student registration numbers.',
            'cbse_affiliation' => 'Edit if the school affiliation number is corrected or updated.',
            default => null,
        };
    }

    public function updateAccount(Request $request)
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->tenant_id === $this->school->id, 403);

        $data = $request->validate(SchoolApplicationForm::accountValidationRules($user));

        $before = $user->only('name', 'email');
        $emailChanged = strtolower(trim($data['email'])) !== strtolower($user->email);

        $userUpdates = [
            'name'  => $data['name'] ?? $user->name,
            'email' => strtolower(trim($data['email'])),
        ];

        if (! empty($data['password'])) {
            $userUpdates['password'] = Hash::make($data['password']);
        }

        $user->update($userUpdates);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        $payload = $this->school->application_payload ?? [];
        $payload['school_email'] = $userUpdates['email'];
        $payload['contact_email'] = $userUpdates['email'];
        $payload['updated_at'] = now()->toIso8601String();
        $this->school->update(['application_payload' => $payload]);

        app(DataChangeLogger::class)->updated(
            $user,
            'School login account updated',
            DataChangeLogger::diff($before, $user->only('name', 'email')),
            $this->school->id,
            'school_account',
        );

        if ($emailChanged && $this->school->parent_id) {
            SahodayaMailer::for($this->school->parent_id)->sendVerification($user->fresh());
        }

        $message = $emailChanged
            ? 'Login email updated. Check your new inbox for a verification link.'
            : (! empty($data['password']) ? 'Password updated.' : 'Account details saved.');

        return $this->message($message);
    }
}
