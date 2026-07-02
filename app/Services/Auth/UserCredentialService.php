<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserCredentialService
{
    public function generateTemporaryPassword(): string
    {
        $first = Str::upper(Str::random(1));
        $rest = Str::lower(Str::random(7));

        return $first.$rest;
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
            'password'             => Hash::make($plain),
            'must_change_password' => $mustChange,
        ];

        if ($username !== null) {
            $updates['username'] = $username;
        }

        if ($createdByUserId !== null) {
            $updates['created_by_user_id'] = $createdByUserId;
        }

        $user->update($updates);

        return ['password' => $plain, 'user' => $user->fresh()];
    }

    /** @return array{password: string, user: User} */
    public function resetPassword(User $user, ?int $resetByUserId = null): array
    {
        return $this->assignCredentials($user, password: null, mustChange: true, createdByUserId: $resetByUserId);
    }
}
