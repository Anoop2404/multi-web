<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Support\TenantUserCatalog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TenantUserProvisioner
{
    public function __construct(
        private UsernameGenerator $usernameGenerator,
        private UserCredentialService $credentials,
    ) {}

    /**
     * @param  list<string>  $roles
     * @param  list<string>  $permissions
     * @return array{user: User, password: ?string}
     */
    public function upsert(
        string $tenantId,
        array $roles,
        array $permissions,
        string $name,
        ?string $email,
        ?string $password = null,
        ?int $userId = null,
        ?array $groupClasses = null,
        ?int $schoolHouseId = null,
        ?int $createdByUserId = null,
        ?string $username = null,
    ): array {
        $email = $email !== null && trim($email) !== '' ? strtolower(trim($email)) : null;
        $plainPassword = null;

        $user = $userId
            ? User::query()->where('tenant_id', $tenantId)->findOrFail($userId)
            : new User(['tenant_id' => $tenantId]);

        $user->fill([
            'name'              => $name,
            'email'             => $email,
            'email_verified_at' => $email ? ($user->email_verified_at ?? now()) : null,
        ]);

        if ($password !== null) {
            $user->password = Hash::make($password);
        } elseif (! $user->exists) {
            $plainPassword = $this->credentials->generateTemporaryPassword();
            $user->password = Hash::make($plainPassword);
            $user->must_change_password = true;
        }

        if ($groupClasses !== null) {
            $user->group_classes = array_values(array_map('intval', $groupClasses));
        }

        if (in_array('house_admin', $roles, true)) {
            $user->school_house_id = $schoolHouseId;
        } elseif (! in_array('house_admin', $roles, true)) {
            $user->school_house_id = null;
        }

        if ($createdByUserId !== null) {
            $user->created_by_user_id = $createdByUserId;
        }

        $user->save();

        // An admin-supplied username always wins (create or edit) — trimmed and
        // checked for uniqueness against every other user, since that's a login
        // identifier. If left blank, fall back to a name-based username ("Anoop
        // John" -> "anoop.john") rather than the old role-code/sequence pattern
        // (e.g. "SAH/ADM/003"), which nobody could remember or type back in.
        $requestedUsername = $username !== null ? trim($username) : null;

        if ($requestedUsername !== null && $requestedUsername !== '' && $requestedUsername !== $user->username) {
            $taken = User::where('username', $requestedUsername)->where('id', '!=', $user->id)->exists();
            if ($taken) {
                throw ValidationException::withMessages(['username' => 'That username is already taken.']);
            }
            $user->update(['username' => $requestedUsername]);
        } elseif (! $user->username) {
            $user->update(['username' => $this->usernameGenerator->fromName($name, $user->id)]);
        }

        $user->syncRoles($roles);

        if ($user->hasRole('school_staff') || $user->hasAnyRole(TenantUserCatalog::sahodayaPermissionRoles())) {
            $user->syncPermissions($permissions);
        } else {
            $user->syncPermissions([]);
        }

        return ['user' => $user->fresh(), 'password' => $plainPassword];
    }

    public function destroy(User $user, string $tenantId, array $allowedRoles): void
    {
        if ($user->tenant_id !== $tenantId || ! $user->hasAnyRole($allowedRoles)) {
            throw ValidationException::withMessages(['user' => 'User not found for this tenant.']);
        }

        if ($user->hasAnyRole(['school_admin', 'school_principal', 'school_vice_principal', 'sahodaya_admin'])) {
            throw ValidationException::withMessages(['user' => 'Primary admin accounts are managed from the superadmin panel.']);
        }

        $user->delete();
    }

    /** @return list<string> */
    public function defaultPermissionsForRoles(array $roles, string $tenantType): array
    {
        return TenantUserCatalog::mergedDefaultPermissions($roles, $tenantType);
    }
}
