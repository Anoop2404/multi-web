<?php

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\SahodayaMailer;
use Illuminate\Validation\ValidationException;

class SchoolPrincipalLoginSyncService
{
    public function syncEmail(Tenant $school, ?string $email): ?User
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === null) {
            return null;
        }

        $principal = User::query()
            ->where('tenant_id', $school->id)
            ->whereHas('roles', fn ($query) => $query->where('name', 'school_principal'))
            ->orderBy('id')
            ->first();

        if (! $principal) {
            return null;
        }

        if ($this->normalizeEmail($principal->email) === $normalized) {
            return $principal;
        }

        $collision = User::query()
            ->where('email', $normalized)
            ->where('id', '!=', $principal->id)
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'principal_email' => 'This email is already used by another account.',
            ]);
        }

        $principal->update([
            'email' => $normalized,
        ]);

        $principal->forceFill(['email_verified_at' => null])->save();

        if ($school->parent_id) {
            SahodayaMailer::for($school->parent_id)->sendVerification($principal->fresh());
        } else {
            $principal->fresh()->sendEmailVerificationNotification();
        }

        return $principal->fresh();
    }

    private function normalizeEmail(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));

        return $email !== '' ? $email : null;
    }
}
