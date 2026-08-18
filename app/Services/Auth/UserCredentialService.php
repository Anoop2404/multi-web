<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\TenantUserCatalog;
use Illuminate\Support\Str;

class UserCredentialService
{
    /** Higher-privilege roles (see TenantUserCatalog::passwordTierForRole()) get a longer, mixed-class password — still fully random, never role-derived. */
    public function generateTemporaryPassword(?string $role = null): string
    {
        return TenantUserCatalog::passwordTierForRole($role) === 'leadership'
            ? $this->leadershipPassword()
            : $this->standardPassword();
    }

    private function standardPassword(): string
    {
        $first = Str::upper(Str::random(1));
        $rest = Str::lower(Str::random(7));

        return $first.$rest;
    }

    /** 12 chars, at least one upper/lower/digit/symbol, ambiguous characters (0/O, 1/l/I) excluded. */
    private function leadershipPassword(): string
    {
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $lower = 'abcdefghijkmnpqrstuvwxyz';
        $digits = '23456789';
        $symbols = '!@#$%*';
        $pool = $upper.$lower.$digits.$symbols;

        $chars = [
            $upper[random_int(0, strlen($upper) - 1)],
            $lower[random_int(0, strlen($lower) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
            $symbols[random_int(0, strlen($symbols) - 1)],
        ];

        for ($i = 0; $i < 8; $i++) {
            $chars[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }

    /** Persist hashed password and a copy for admin credential lookup. */
    public function storePassword(User $user, string $plain, bool $mustChange = true): User
    {
        $user->forceFill([
            'password'             => $plain,
            'plain_password'       => $plain,
            'must_change_password' => $mustChange,
        ])->save();

        return $user->fresh();
    }

    public function clearStoredPlainPassword(User $user): User
    {
        if ($user->plain_password === null) {
            return $user;
        }

        $user->forceFill(['plain_password' => null])->save();

        return $user->fresh();
    }

    /** @return array{password: string, user: User} */
    public function assignCredentials(
        User $user,
        ?string $username = null,
        ?string $password = null,
        bool $mustChange = true,
        ?int $createdByUserId = null,
    ): array {
        $plain = $password ?? $this->generateTemporaryPassword($user->getRoleNames()->first());

        $updates = [
            'password'             => $plain,
            'plain_password'       => $plain,
            'must_change_password' => $mustChange,
        ];

        if ($username !== null) {
            $updates['username'] = $username;
        }

        if ($createdByUserId !== null) {
            $updates['created_by_user_id'] = $createdByUserId;
        }

        $user->forceFill($updates)->save();

        return ['password' => $plain, 'user' => $user->fresh()];
    }

    /** @return array{password: string, user: User} */
    public function resetPassword(User $user, ?int $resetByUserId = null): array
    {
        return $this->assignCredentials($user, password: null, mustChange: true, createdByUserId: $resetByUserId);
    }
}
