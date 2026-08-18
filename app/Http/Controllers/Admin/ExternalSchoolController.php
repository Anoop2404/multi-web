<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\FestStateProgram;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\UserCredentialService;
use App\Support\StateScope;

/**
 * State-admin view of one ExternalSahodaya's schools and their portal login credentials.
 * ExternalSchool is not a tenant User, so UserCredentialService's hashing/storage methods
 * (hard type-hinted to User) aren't reused here — only its pure password generator is.
 */
class ExternalSchoolController extends Controller
{
    public function index(ExternalSahodaya $externalSahodaya)
    {
        StateScope::assertOwns(FestStateProgram::find($externalSahodaya->state_program_id)?->state_id);
        $schools = $externalSahodaya->schools()
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'status', 'contact_name', 'contact_phone', 'created_at'])
            ->map(fn (ExternalSchool $school) => [
                'id'            => $school->id,
                'name'          => $school->name,
                'username'      => $school->username,
                'status'        => $school->status,
                'has_login'     => $school->hasLogin(),
                'contact_name'  => $school->contact_name,
                'contact_phone' => $school->contact_phone,
                'created_at'    => $school->created_at,
            ]);

        return inertia('ExternalSchools/Index', [
            'sahodaya' => $externalSahodaya->only('id', 'name'),
            'schools'  => $schools,
        ]);
    }

    public function resetPassword(ExternalSchool $externalSchool, UserCredentialService $credentials, PlatformAuditLogger $audit)
    {
        $externalSchool->loadMissing('sahodaya');
        StateScope::assertOwns(FestStateProgram::find($externalSchool->sahodaya?->state_program_id)?->state_id);

        $plainPassword = $credentials->generateTemporaryPassword();

        $externalSchool->forceFill([
            'password'       => $plainPassword,
            'plain_password' => $plainPassword,
        ])->save();

        $audit->log(
            'external_school.password_reset',
            "Portal password reset for external school: {$externalSchool->name}",
            $externalSchool,
            ['school_id' => $externalSchool->id, 'username' => $externalSchool->username],
        );

        return back()->with([
            'success'        => 'Password reset.',
            'newCredentials' => [
                'username' => $externalSchool->username,
                'password' => $plainPassword,
            ],
        ]);
    }
}
