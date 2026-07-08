<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Str;

class UserCredentialService
{
    public function generateTemporaryPassword(): string
    {
        $first = Str::upper(Str::random(1));
        $rest = Str::lower(Str::random(7));

        return $first.$rest;
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
        $plain = $password ?? $this->generateTemporaryPassword();

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
