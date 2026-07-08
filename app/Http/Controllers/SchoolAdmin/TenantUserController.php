<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\SchoolHouse;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\TenantUserProvisioner;
use App\Services\Auth\UserCredentialService;
use App\Services\Notifications\NotificationService;
use App\Services\School\SchoolUserScopeService;
use App\Support\TenantDomainSync;
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
            'coordinatorContact'=> $this->coordinatorContactPayload($scopes),
            'leadershipContacts'=> $this->leadershipContactsPayload($scopes),
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

    public function updateCoordinatorContact(Request $request)
    {
        return $this->updateLeadershipContact($request, 'event_coordinator');
    }

    public function updateLeadershipContact(Request $request, string $roleKey)
    {
        $assignable = TenantUserCatalog::assignableRolesFor($request->user());
        abort_if($assignable === [], 403);
        $config = $this->leadershipRoleConfig($roleKey);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:30',
        ]);

        $payload = $this->school->application_payload ?? [];
        $payload[$config['prefix'].'_name'] = $data['name'];
        $payload[$config['prefix'].'_email'] = strtolower(trim($data['email']));
        $payload[$config['prefix'].'_phone'] = $data['phone'] ?? null;

        $this->school->update(['application_payload' => $payload]);

        return back()->with('success', "{$config['label']} contact updated.");
    }

    public function provisionCoordinatorFromContact(
        Request $request,
        TenantUserProvisioner $provisioner,
        UserCredentialService $credentials,
        NotificationService $notifications,
        SchoolUserScopeService $scopes,
        PlatformAuditLogger $audit,
    ) {
        return $this->provisionLeadershipLogin($request, 'event_coordinator', $provisioner, $credentials, $notifications, $scopes, $audit);
    }

    public function provisionLeadershipLogin(
        Request $request,
        string $roleKey,
        TenantUserProvisioner $provisioner,
        UserCredentialService $credentials,
        NotificationService $notifications,
        SchoolUserScopeService $scopes,
        PlatformAuditLogger $audit,
    ) {
        $assignable = TenantUserCatalog::assignableRolesFor($request->user());
        $config = $this->leadershipRoleConfig($roleKey);
        $role = $config['role'];
        $canProvision = in_array($role, $assignable, true)
            || (in_array($role, ['school_principal', 'school_vice_principal'], true) && $assignable !== []);
        abort_unless($canProvision, 403);

        $rules = [
            'event_scopes' => $config['scoped'] ? 'required|array|min:1' : 'nullable|array',
            'event_scopes.*.program_slug' => 'required_with:event_scopes|string|max:50',
            'event_scopes.*.scope_type'   => 'required_with:event_scopes|in:program,fest_event,mcq_exam,training_program',
            'event_scopes.*.event_id'     => 'nullable|integer',
        ];
        if (! $config['scoped']) {
            $rules['event_scopes'] = 'nullable|array';
        }

        $data = $request->validate($rules);

        $payload = $this->school->application_payload ?? [];
        $name = trim((string) ($payload[$config['prefix'].'_name'] ?? ''));
        $email = strtolower(trim((string) ($payload[$config['prefix'].'_email'] ?? '')));

        if ($name === '' || $email === '') {
            return back()->withErrors([
                'leadership' => "Add the {$config['label']} name and email before creating the login.",
            ]);
        }

        $existing = User::query()
            ->where('tenant_id', $this->school->id)
            ->where('email', $email)
            ->first();

        if ($existing && $config['scoped'] && ! $existing->hasRole($role)) {
            return back()->withErrors([
                'leadership' => 'A non-coordinator user already uses this email. Edit that user or choose another email.',
            ]);
        }

        if (! $existing) {
            $emailTaken = User::query()->where('email', $email)->exists();
            if ($emailTaken) {
                return back()->withErrors([
                    'leadership' => 'This email is already used by another account.',
                ]);
            }
        }

        $result = $provisioner->upsert(
            $this->school->id,
            [$role],
            $provisioner->defaultPermissionsForRoles([$role], 'school'),
            $name,
            $email,
            null,
            $existing?->id,
            null,
            null,
            $request->user()?->id,
        );

        $user = $result['user'];
        $password = $result['password'];
        if ($existing || ! $password) {
            $reset = $credentials->resetPassword($user, $request->user()?->id);
            $user = $reset['user'];
            $password = $reset['password'];
        }

        if ($config['scoped']) {
            $scopes->sync($user, $this->school->id, $data['event_scopes'] ?? [], $request->user()?->id);
        } else {
            $scopes->sync($user, $this->school->id, [], $request->user()?->id);
        }

        $this->sendLeadershipCredentials($notifications, $user, $password, $config['label']);

        $audit->userUpdated($user);

        return back()->with([
            'success' => "{$config['label']} login created/updated and credentials emailed.",
            'newCredentials' => [
                'username' => $user->username,
                'password' => $password,
            ],
        ]);
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
            'mcq.view' => 'Talent Search — view',
            'mcq.manage' => 'Talent Search — register students',
            'website.view' => 'Website — view',
            'website.news' => 'Website — news',
            'website.manage' => 'Website — manage content',
            'users.manage' => 'Users — manage',
        ];
    }

    private function coordinatorContactPayload(SchoolUserScopeService $scopes): array
    {
        return $this->leadershipContactPayload('event_coordinator', $scopes);
    }

    private function leadershipContactsPayload(SchoolUserScopeService $scopes): array
    {
        return [
            'principal' => $this->leadershipContactPayload('principal', $scopes),
            'vice_principal' => $this->leadershipContactPayload('vice_principal', $scopes),
            'event_coordinator' => $this->leadershipContactPayload('event_coordinator', $scopes),
        ];
    }

    private function leadershipContactPayload(string $roleKey, SchoolUserScopeService $scopes): array
    {
        $config = $this->leadershipRoleConfig($roleKey);
        $payload = $this->school->application_payload ?? [];
        $email = strtolower(trim((string) ($payload[$config['prefix'].'_email'] ?? '')));
        $user = $email !== ''
            ? User::role($config['role'])
                ->where('tenant_id', $this->school->id)
                ->where('email', $email)
                ->first()
            : null;

        return [
            'key' => $roleKey,
            'label' => $config['label'],
            'role' => $config['role'],
            'scoped' => $config['scoped'],
            'name' => $payload[$config['prefix'].'_name'] ?? '',
            'email' => $payload[$config['prefix'].'_email'] ?? '',
            'phone' => $payload[$config['prefix'].'_phone'] ?? '',
            'loginUser' => $user ? [
                'id' => $user->id,
                'username' => $user->username,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'event_scopes' => $scopes->scopesForUser($user->id, $this->school->id),
            ] : null,
        ];
    }

    /** @return array{prefix: string, label: string, role: string, scoped: bool} */
    private function leadershipRoleConfig(string $roleKey): array
    {
        return match ($roleKey) {
            'principal' => [
                'prefix' => 'principal',
                'label' => 'Principal',
                'role' => 'school_principal',
                'scoped' => false,
            ],
            'vice_principal' => [
                'prefix' => 'vice_principal',
                'label' => 'Vice Principal',
                'role' => 'school_vice_principal',
                'scoped' => false,
            ],
            'event_coordinator' => [
                'prefix' => 'event_coordinator',
                'label' => 'Events Coordinator',
                'role' => 'school_event_coordinator',
                'scoped' => true,
            ],
            default => abort(404),
        };
    }

    private function sendLeadershipCredentials(NotificationService $notifications, User $user, string $password, string $label): void
    {
        $loginUrl = $this->schoolLoginUrl();
        $body = implode("\n", [
            "Dear {$user->name},",
            '',
            "Your {$label} login has been created for {$this->school->name}.",
            '',
            "Use the existing school login page: {$loginUrl}",
            "Username: {$user->username}",
            "Temporary password: {$password}",
            '',
            'Please sign in and change this password when prompted.',
        ]);

        $notifications->notifyEmailOnly(
            $user,
            "Your school {$label} login",
            $body,
            'school.leadership.credentials',
        );
    }

    private function schoolLoginUrl(): string
    {
        $sahodaya = $this->school->parent;
        $base = $sahodaya ? TenantDomainSync::publicUrl($sahodaya) : url('/');

        return rtrim($base, '/').'/school-login';
    }
}
