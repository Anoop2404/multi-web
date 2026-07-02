<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SchoolHouse;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\TenantUserProvisioner;
use App\Services\Auth\UserCredentialService;
use App\Services\School\SchoolUserScopeService;
use App\Support\TenantUserCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantUserController extends SchoolAdminController
{
    public function index(SchoolUserScopeService $scopes)
    {
        $actor = request()->user();
        $assignable = TenantUserCatalog::assignableRolesFor($actor);
        abort_if($assignable === [], 403);

        $visibleRoles = $actor->hasRole('school_principal')
            ? TenantUserCatalog::schoolPanelRoles()
            : array_merge($assignable, TenantUserCatalog::schoolManagementRoles());

        $users = User::query()
            ->where('tenant_id', $this->school->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $visibleRoles))
            ->with('roles', 'permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'            => $u->id,
                'name'          => $u->name,
                'email'         => $u->email,
                'username'      => $u->username,
                'last_login_at' => $u->last_login_at?->toIso8601String(),
                'roles'         => $u->getRoleNames()->values()->all(),
                'permissions'   => $u->getPermissionNames()->values()->all(),
                'group_classes' => $u->group_classes ?? [],
                'school_house_id' => $u->school_house_id,
                'event_scopes'  => $scopes->scopesForUser($u->id, $this->school->id),
            ]);

        return $this->inertia('School/Users/Index', [
            'users'             => $users,
            'classes'           => $this->schoolClasses(),
            'houses'            => SchoolHouse::forSchool($this->school->id)->orderBy('name')->get(['id', 'name']),
            'assignableRoles'   => $this->roleOptions($assignable),
            'scopeOptions'      => $scopes->scopeOptionsForSchool($this->school->id, $this->school->parent_id),
            'canManageAdmins'   => $actor->hasRole('school_principal'),
            'permissions'       => TenantUserCatalog::allPermissions(),
            'permissionLabels'  => $this->permissionLabels(),
            'newCredentials'    => session('newCredentials'),
        ]);
    }

    public function store(Request $request, TenantUserProvisioner $provisioner, PlatformAuditLogger $audit, SchoolUserScopeService $scopes)
    {
        $assignable = TenantUserCatalog::assignableRolesFor($request->user());
        abort_if($assignable === [], 403);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|max:255|unique:users,email',
            'password'        => 'nullable|string|min:8',
            'roles'           => 'required|array|min:1',
            'roles.*'         => ['string', Rule::in($assignable)],
            'permissions'     => 'array',
            'permissions.*'   => ['string', Rule::in(TenantUserCatalog::allPermissions())],
            'group_classes'   => 'array',
            'group_classes.*' => 'integer',
            'school_house_id' => [
                'nullable',
                Rule::exists(SchoolHouse::class, 'id')->where('tenant_id', $this->school->id),
            ],
            'event_scopes'              => 'array',
            'event_scopes.*.program_slug' => 'required_with:event_scopes|string|max:50',
            'event_scopes.*.scope_type'   => 'required_with:event_scopes|in:program,fest_event,mcq_exam,training_program',
            'event_scopes.*.event_id'     => 'nullable|integer',
        ]);

        $this->assertRoleCombinationAllowed($request->user(), $data['roles']);

        $perms = $data['permissions'] ?? $provisioner->defaultPermissionsForRoles($data['roles'], 'school');
        $groupClasses = in_array('group_admin', $data['roles'], true)
            ? ($data['group_classes'] ?? [])
            : null;
        $houseId = in_array('house_admin', $data['roles'], true)
            ? ($data['school_house_id'] ?? null)
            : null;

        if (in_array('house_admin', $data['roles'], true) && ! $houseId) {
            return back()->withErrors(['school_house_id' => 'Select a house for house admin accounts.']);
        }

        $result = $provisioner->upsert(
            $this->school->id,
            $data['roles'],
            $perms,
            $data['name'],
            $data['email'],
            $data['password'] ?? null,
            null,
            $groupClasses,
            $houseId,
            $request->user()?->id,
        );

        if (in_array('school_event_coordinator', $data['roles'], true)) {
            $scopes->sync($result['user'], $this->school->id, $data['event_scopes'] ?? [], $request->user()?->id);
        }

        $audit->userCreated($result['user']);

        $flash = ['success' => 'User account created.'];
        if ($result['password']) {
            $flash['newCredentials'] = [
                'username' => $result['user']->username,
                'password' => $result['password'],
            ];
        }

        return back()->with($flash);
    }

    public function update(Request $request, string $tenantId, User $user, TenantUserProvisioner $provisioner, PlatformAuditLogger $audit, SchoolUserScopeService $scopes)
    {
        abort_if($user->tenant_id !== $this->school->id, 403);

        $assignable = TenantUserCatalog::assignableRolesFor($request->user());
        abort_if($assignable === [], 403);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'        => 'nullable|string|min:8',
            'roles'           => 'required|array|min:1',
            'roles.*'         => ['string', Rule::in($assignable)],
            'permissions'     => 'array',
            'permissions.*'   => ['string', Rule::in(TenantUserCatalog::allPermissions())],
            'group_classes'   => 'array',
            'group_classes.*' => [
                'integer',
                Rule::exists(SchoolClass::class, 'id')->where('tenant_id', $this->school->id),
            ],
            'school_house_id' => [
                'nullable',
                Rule::exists(SchoolHouse::class, 'id')->where('tenant_id', $this->school->id),
            ],
            'event_scopes'              => 'array',
            'event_scopes.*.program_slug' => 'required_with:event_scopes|string|max:50',
            'event_scopes.*.scope_type'   => 'required_with:event_scopes|in:program,fest_event,mcq_exam,training_program',
            'event_scopes.*.event_id'     => 'nullable|integer',
        ]);

        $this->assertRoleCombinationAllowed($request->user(), $data['roles'], $user);

        $password = $data['password'] ?? null;
        $perms = $data['permissions'] ?? $provisioner->defaultPermissionsForRoles($data['roles'], 'school');
        $groupClasses = in_array('group_admin', $data['roles'], true)
            ? ($data['group_classes'] ?? [])
            : [];
        $houseId = in_array('house_admin', $data['roles'], true)
            ? ($data['school_house_id'] ?? null)
            : null;

        if (in_array('house_admin', $data['roles'], true) && ! $houseId) {
            return back()->withErrors(['school_house_id' => 'Select a house for house admin accounts.']);
        }

        $result = $provisioner->upsert(
            $this->school->id,
            $data['roles'],
            $perms,
            $data['name'],
            $data['email'],
            $password,
            $user->id,
            $groupClasses,
            $houseId,
        );

        if (in_array('school_event_coordinator', $data['roles'], true)) {
            $scopes->sync($result['user'], $this->school->id, $data['event_scopes'] ?? [], $request->user()?->id);
        } else {
            $scopes->sync($result['user'], $this->school->id, [], $request->user()?->id);
        }

        $audit->userUpdated($result['user']);

        return back()->with('success', 'User updated.');
    }

    public function resetPassword(string $tenantId, User $user, UserCredentialService $credentials, PlatformAuditLogger $audit)
    {
        abort_if($user->tenant_id !== $this->school->id, 403);
        abort_if($user->hasAnyRole(TenantUserCatalog::schoolManagementRoles()), 422, 'Reset primary admin accounts from Sahodaya panel.');

        $result = $credentials->resetPassword($user, request()->user()?->id);
        $audit->userUpdated($result['user']);

        return back()->with([
            'success'         => 'Password reset.',
            'newCredentials'  => [
                'username' => $result['user']->username,
                'password' => $result['password'],
            ],
        ]);
    }

    public function destroy(string $tenantId, User $user, TenantUserProvisioner $provisioner, PlatformAuditLogger $audit)
    {
        abort_if($user->tenant_id !== $this->school->id, 403);
        abort_if($user->hasAnyRole(TenantUserCatalog::schoolManagementRoles()) && ! request()->user()?->hasRole('school_principal'), 403);

        $audit->userDeleted($user);
        $provisioner->destroy($user, $this->school->id, TenantUserCatalog::schoolPanelRoles());

        return back()->with('success', 'User removed.');
    }

    /** @param  list<string>  $roles */
    private function assertRoleCombinationAllowed(User $actor, array $roles, ?User $target = null): void
    {
        if ($target && $target->hasAnyRole(TenantUserCatalog::schoolManagementRoles()) && ! $actor->hasRole('school_principal')) {
            abort(403, 'Only the principal can change admin accounts.');
        }

        foreach (['school_admin', 'school_vice_principal'] as $mgmtRole) {
            if (in_array($mgmtRole, $roles, true) && ! $actor->hasRole('school_principal')) {
                abort(403, 'Only the principal can assign admin or vice principal roles.');
            }
        }
    }

    /** @param  list<string>  $roles */
    private function roleOptions(array $roles): array
    {
        $labels = TenantUserCatalog::roleLabels();

        return collect($roles)->map(fn ($r) => [
            'value' => $r,
            'label' => $labels[$r] ?? $r,
        ])->values()->all();
    }

    /** @return array<string, string> */
    private function permissionLabels(): array
    {
        return [
            'fest.view' => 'Fest — view',
            'fest.manage' => 'Fest — register students',
            'fest.catering' => 'Fest — meal requests',
            'mcq.view' => 'MCQ — view',
            'mcq.manage' => 'MCQ — register students',
            'website.view' => 'Website — view',
            'website.news' => 'Website — news',
            'website.manage' => 'Website — manage content',
            'users.manage' => 'Users — manage',
        ];
    }
}
