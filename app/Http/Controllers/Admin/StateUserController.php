<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformState;
use App\Models\PlatformUser;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Spreadsheet\SpreadsheetWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class StateUserController extends Controller
{
    public function index()
    {
        $roles = ['state_admin', 'state_staff'];

        $users = PlatformUser::query()
            ->whereNull('tenant_id')
            ->where(function ($query) use ($roles) {
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
                    ->orWhereIn('id', function ($sub) use ($roles) {
                        $sub->select('model_id')
                            ->from('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereIn('roles.name', $roles);
                    });
            })
            ->orderBy('name')
            ->get()
            ->map(function (PlatformUser $u) use ($roles) {
                $userRoles = \Illuminate\Support\Facades\DB::table('model_has_roles')
                    ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                    ->where('model_has_roles.model_id', $u->id)
                    ->whereIn('roles.name', $roles)
                    ->pluck('roles.name')
                    ->unique()
                    ->values()
                    ->all();

                return [
                    'id'        => $u->id,
                    'name'      => $u->name,
                    'email'     => $u->email,
                    'roles'     => $userRoles,
                    'is_active' => $u->is_active,
                    'state_id'  => $u->state_id,
                    'state_name'=> $u->state?->name,
                ];
            });

        return inertia('State/Users/Index', [
            'users'           => $users,
            'states'          => PlatformState::orderBy('name')->get(['id', 'name', 'code']),
            'assignableRoles' => collect($roles)->map(fn ($r) => [
                'value' => $r,
                'label' => $r === 'state_admin' ? 'State admin' : 'State staff (view only)',
            ])->values(),
        ]);
    }

    public function store(Request $request, PlatformAuditLogger $audit)
    {
        $roles = ['state_admin', 'state_staff'];

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'roles'    => 'required|array|min:1',
            'roles.*'  => ['string', Rule::in($roles)],
            'state_id' => 'nullable|uuid|exists:states,id',
        ]);

        $user = PlatformUser::create([
            'name'              => $data['name'],
            'email'             => strtolower(trim($data['email'])),
            'password'          => Hash::make($data['password']),
            'email_verified_at' => now(),
            'tenant_id'         => null,
            'state_id'          => $data['state_id'] ?? null,
        ]);
        foreach ($data['roles'] as $roleName) {
            \App\Models\PlatformRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
        $user->syncRoles($data['roles']);
        if ($centralUser = \App\Models\User::find($user->id)) {
            $centralUser->syncRoles($data['roles']);
        }

        $audit->userCreated($user);

        return back()->with('success', 'State user created.');
    }

    public function update(Request $request, PlatformUser $user, PlatformAuditLogger $audit)
    {
        abort_unless($user->tenant_id === null && ($user->hasAnyRole(['state_admin', 'state_staff']) || $user->isStateUser()), 404);

        $roles = ['state_admin', 'state_staff'];

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'roles'    => 'required|array|min:1',
            'roles.*'  => ['string', Rule::in($roles)],
            'state_id' => 'nullable|uuid|exists:states,id',
        ]);

        $user->fill([
            'name'     => $data['name'],
            'email'    => strtolower(trim($data['email'])),
            'state_id' => $data['state_id'] ?? null,
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        foreach ($data['roles'] as $roleName) {
            \App\Models\PlatformRole::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
        $user->syncRoles($data['roles']);
        if ($centralUser = \App\Models\User::find($user->id)) {
            $centralUser->syncRoles($data['roles']);
        }

        $audit->userUpdated($user);

        return back()->with('success', 'State user updated.');
    }

    public function destroy(PlatformUser $user, PlatformAuditLogger $audit)
    {
        abort_unless($user->tenant_id === null && ($user->hasAnyRole(['state_admin', 'state_staff']) || $user->isStateUser()), 404);
        abort_if($user->id === auth()->id(), 403, 'You cannot delete your own account.');

        $audit->userDeleted($user);
        $user->delete();

        return back()->with('success', 'State user removed.');
    }

    public function toggleActive(PlatformUser $user, PlatformAuditLogger $audit)
    {
        abort_unless($user->tenant_id === null && ($user->hasAnyRole(['state_admin', 'state_staff']) || $user->isStateUser()), 404);
        abort_if($user->id === auth()->id(), 403, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->is_active]);
        $audit->userUpdated($user);

        return back()->with('success', $user->is_active ? 'State user activated.' : 'State user deactivated.');
    }

    public function exportCredentials(PlatformAuditLogger $audit)
    {
        $roles = ['state_admin', 'state_staff'];

        $users = PlatformUser::query()
            ->whereNull('tenant_id')
            ->where(function ($query) use ($roles) {
                $query->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
                    ->orWhereIn('id', function ($sub) use ($roles) {
                        $sub->select('model_id')
                            ->from('model_has_roles')
                            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                            ->whereIn('roles.name', $roles);
                    });
            })
            ->orderBy('name')
            ->get();

        $rows = [['Name', 'Email', 'Temporary password', 'Roles', 'Status']];

        foreach ($users as $u) {
            $userRoles = \Illuminate\Support\Facades\DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $u->id)
                ->whereIn('roles.name', $roles)
                ->pluck('roles.name')
                ->unique()
                ->implode(', ');

            $rows[] = [
                $u->name,
                $u->email,
                'Set directly by admin — not stored',
                $userRoles,
                $u->is_active ? 'Active' : 'Inactive',
            ];
        }

        $audit->log(
            'state.user_credentials.exported',
            "Exported {$users->count()} state user credential row(s)",
            null,
            ['count' => $users->count()],
        );

        $filename = 'state-user-credentials-'.now()->format('Y-m-d').'.xlsx';
        $xlsx = SpreadsheetWriter::xlsx($rows);

        return response()->streamDownload(
            fn () => print $xlsx,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }
}
