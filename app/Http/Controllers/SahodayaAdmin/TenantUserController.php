<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\User;
use App\Models\FestEvent;
use App\Models\FestEventStaff;
use App\Models\McqExam;
use App\Models\McqExamStaff;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Auth\TenantUserProvisioner;
use App\Support\TenantUserCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TenantUserController extends SahodayaAdminController
{
    public function index()
    {
        $roles = TenantUserCatalog::sahodayaAssignableRoles();

        $users = User::query()
            ->where('tenant_id', $this->sahodaya->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->with('roles', 'permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id'          => $u->id,
                'name'        => $u->name,
                'email'       => $u->email,
                'roles'       => $u->getRoleNames()->values()->all(),
                'permissions' => $u->getPermissionNames()->values()->all(),
                'fest_assignments' => FestEventStaff::where('user_id', $u->id)
                    ->with('event:id,title', 'stage:id,name')
                    ->get()
                    ->map(fn (FestEventStaff $s) => [
                        'event_id'    => $s->event_id,
                        'event_title' => $s->event?->title,
                        'duty'        => $s->duty,
                        'stage_id'    => $s->stage_id,
                        'stage_name'  => $s->stage?->name,
                    ])->values()->all(),
                'exam_assignments' => McqExamStaff::where('user_id', $u->id)
                    ->with('exam:id,title')
                    ->get()
                    ->map(fn (McqExamStaff $s) => [
                        'exam_id'    => $s->exam_id,
                        'exam_title' => $s->exam?->title,
                        'role'       => $s->role,
                    ])->values()->all(),
            ]);

        return $this->inertia('Sahodaya/Users/Index', [
            'users'           => $users,
            'assignableRoles' => $this->roleOptions($roles),
            'permissions'     => TenantUserCatalog::allPermissions(),
            'permissionLabels'=> $this->permissionLabels(),
            'permissionRoles' => TenantUserCatalog::sahodayaPermissionRoles(),
            'roleDefaultPermissions' => collect(TenantUserCatalog::sahodayaPermissionRoles())
                ->mapWithKeys(fn (string $role) => [
                    $role => TenantUserCatalog::defaultPermissionsForRole($role, 'sahodaya'),
                ])->all(),
            'festEvents'      => FestEvent::where('tenant_id', $this->sahodaya->id)
                ->whereIn('status', ['draft', 'published', 'registration_open', 'ongoing'])
                ->orderByDesc('event_start')
                ->get(['id', 'title', 'status']),
            'mcqExams'        => McqExam::where('tenant_id', $this->sahodaya->id)
                ->whereIn('status', ['draft', 'published', 'ongoing', 'completed'])
                ->orderByDesc('scheduled_at')
                ->get(['id', 'title', 'status']),
            'dutyOptions'     => collect(TenantUserCatalog::festEventDuties())->map(fn ($d) => [
                'value' => $d,
                'label' => TenantUserCatalog::dutyLabels()[$d] ?? $d,
            ])->values(),
        ]);
    }

    public function store(Request $request, TenantUserProvisioner $provisioner, PlatformAuditLogger $audit)
    {
        $roles = TenantUserCatalog::sahodayaAssignableRoles();

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'password'      => 'nullable|string|min:8',
            'roles'         => 'required|array|min:1',
            'roles.*'       => ['string', Rule::in($roles)],
            'permissions'   => 'array',
            'permissions.*' => ['string', Rule::in(TenantUserCatalog::allPermissions())],
            'fest_ops_event_id' => 'nullable|exists:fest_events,id',
            'fest_ops_duties'   => 'array',
            'fest_ops_duties.*' => ['string', Rule::in(TenantUserCatalog::festEventDuties())],
            'exam_staff_exam_id' => 'nullable|exists:mcq_exams,id',
            'exam_staff_role'    => 'nullable|in:controller,staff',
        ]);

        $perms = $data['permissions'] ?? $provisioner->defaultPermissionsForRoles($data['roles'], 'sahodaya');

        $result = $provisioner->upsert(
            $this->sahodaya->id,
            $data['roles'],
            $perms,
            $data['name'],
            $data['email'],
            $data['password'] ?? null,
            null,
            null,
            null,
            $request->user()?->id,
        );

        $user = $result['user'];
        $this->syncEventStaffAssignment($user, $data);
        $this->syncExamStaffAssignment($user, $data);

        $audit->userCreated($user);

        $flash = ['success' => 'User account created.'];
        if ($result['password']) {
            $flash['newCredentials'] = [
                'username' => $user->username,
                'password' => $result['password'],
            ];
        }

        return back()->with($flash);
    }

    public function update(Request $request, string $tenantId, User $user, TenantUserProvisioner $provisioner, PlatformAuditLogger $audit)
    {
        $roles = TenantUserCatalog::sahodayaAssignableRoles();

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password'      => 'nullable|string|min:8',
            'roles'         => 'required|array|min:1',
            'roles.*'       => ['string', Rule::in($roles)],
            'permissions'   => 'array',
            'permissions.*' => ['string', Rule::in(TenantUserCatalog::allPermissions())],
            'fest_ops_event_id' => 'nullable|exists:fest_events,id',
            'fest_ops_duties'   => 'array',
            'fest_ops_duties.*' => ['string', Rule::in(TenantUserCatalog::festEventDuties())],
            'exam_staff_exam_id' => 'nullable|exists:mcq_exams,id',
            'exam_staff_role'    => 'nullable|in:controller,staff',
        ]);

        $password = $data['password'] ?? null;
        $perms = $data['permissions'] ?? $provisioner->defaultPermissionsForRoles($data['roles'], 'sahodaya');

        $result = $provisioner->upsert(
            $this->sahodaya->id,
            $data['roles'],
            $perms,
            $data['name'],
            $data['email'],
            $password,
            $user->id,
        );

        $updated = $result['user'];
        $this->syncEventStaffAssignment($updated, $data);
        $this->syncExamStaffAssignment($updated, $data);

        $audit->userUpdated($updated);

        return back()->with('success', 'User updated.');
    }

    public function destroy(string $tenantId, User $user, TenantUserProvisioner $provisioner, PlatformAuditLogger $audit)
    {
        $audit->userDeleted($user);
        $provisioner->destroy($user, $this->sahodaya->id, TenantUserCatalog::sahodayaAssignableRoles());

        return back()->with('success', 'User removed.');
    }

    /** @param  array<string, mixed>  $data */
    private function syncEventStaffAssignment(User $user, array $data): void
    {
        $hasFestOps = in_array('fest_ops', $data['roles'] ?? [], true);
        $hasMarkCoordinator = in_array('mark_entry_coordinator', $data['roles'] ?? [], true);

        if (! $hasFestOps && ! $hasMarkCoordinator) {
            return;
        }

        $eventId = $data['fest_ops_event_id'] ?? null;
        $duties = $data['fest_ops_duties'] ?? [];
        if (! $eventId || $duties === []) {
            return;
        }

        FestEventStaff::where('user_id', $user->id)
            ->where('event_id', $eventId)
            ->whereNotIn('duty', $duties)
            ->delete();

        foreach ($duties as $duty) {
            FestEventStaff::firstOrCreate([
                'event_id' => $eventId,
                'user_id'  => $user->id,
                'duty'     => $duty,
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function syncExamStaffAssignment(User $user, array $data): void
    {
        $examRoles = ['exam_controller', 'exam_staff'];
        if (! array_intersect($examRoles, $data['roles'] ?? [])) {
            return;
        }

        $examId = $data['exam_staff_exam_id'] ?? null;
        if (! $examId) {
            return;
        }

        $role = $data['exam_staff_role'] ?? (in_array('exam_controller', $data['roles'], true) ? 'controller' : 'staff');

        McqExamStaff::updateOrCreate(
            ['exam_id' => $examId, 'user_id' => $user->id],
            ['role' => $role],
        );
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
            'fest.manage' => 'Fest — manage',
            'fest.marks' => 'Fest — marks',
            'fest.registrations' => 'Fest — registrations',
            'fest.results' => 'Fest — publish results',
            'fest.finance' => 'Fest — finance',
            'fest.settings' => 'Fest — settings',
            'fest.catering' => 'Fest — catering',
            'fest.schedule' => 'Fest — schedule',
            'fest.certificates' => 'Fest — certificates',
            'training.view' => 'Training — view',
            'training.manage' => 'Training — manage',
            'finance.view' => 'Finance — view',
            'mcq.view' => 'MCQ — view',
            'mcq.manage' => 'MCQ — manage',
            'mcq.attendance' => 'MCQ — attendance',
            'mcq.marks' => 'MCQ — marks',
            'membership.view' => 'Membership — view',
            'membership.manage' => 'Membership — manage',
            'website.view' => 'Website — view',
            'website.news' => 'Website — news',
            'website.manage' => 'Website — manage',
            'users.manage' => 'Users — manage',
        ];
    }
}
