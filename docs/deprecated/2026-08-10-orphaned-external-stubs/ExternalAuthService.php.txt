<?php

namespace App\Services\External;

use App\Models\ExternalSahodaya;
use App\Models\ExternalSchool;
use App\Models\User;
use Illuminate\Support\Str;

class ExternalAuthService
{
    /**
     * Create an expiring invitation for a named external user.
     *
     * @return array{invitation_token: string, expires_at: string}
     */
    public function createInvitation(string $email, string $organizationType, string $organizationId): array
    {
        $token = Str::random(32);
        $expiresAt = now()->addDays(3);

        return [
            'email'             => $email,
            'organization_type' => $organizationType,
            'organization_id'   => $organizationId,
            'invitation_token'  => $token,
            'expires_at'        => $expiresAt->toIso8601String(),
        ];
    }

    /**
     * Activate user account from invitation token.
     */
    public function activateAccount(array $invitation, string $password): User
    {
        if (now()->parse($invitation['expires_at'])->isPast()) {
            throw new \InvalidArgumentException('Invitation token has expired.');
        }

        return User::create([
            'name'     => explode('@', $invitation['email'])[0],
            'email'    => $invitation['email'],
            'password' => bcrypt($password),
        ]);
    }

    /**
     * Verify organization membership scope.
     */
    public function verifyOrganizationAccess(User $user, string $organizationType, string $organizationId): bool
    {
        if ($organizationType === 'sahodaya') {
            return ExternalSahodaya::where('id', $organizationId)->where('status', 'active')->exists();
        }

        if ($organizationType === 'school') {
            return ExternalSchool::where('id', $organizationId)->where('status', 'active')->exists();
        }

        return false;
    }
}
