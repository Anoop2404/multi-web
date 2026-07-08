<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use App\Services\Audit\PlatformAuditLogger;
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
            ->whereHas('roles', fn ($q) => $q->whereIn('name', $roles))
            ->with('roles', 'permissions')
            ->orderBy('name')
            ->get()
            ->map(fn (PlatformUser $u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'email' => $u->email,
                'roles' => $u->getRoleNames()->values()->all(),
            ]);

        return inertia('State/Users/Index', [
            'users'           => $users,
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
        ]);

        $user = PlatformUser::create([
            'name'              => $data['name'],
            'email'             => strtolower(trim($data['email'])),
            'password'          => Hash::make($data['password']),
            'email_verified_at' => now(),
            'tenant_id'         => null,
        ]);
        $user->syncRoles($data['roles']);

        $audit->userCreated($user);

        return back()->with('success', 'State user created.');
    }

    public function update(Request $request, PlatformUser $user, PlatformAuditLogger $audit)
    {
        abort_unless($user->tenant_id === null && $user->hasAnyRole(['state_admin', 'state_staff']), 404);

        $roles = ['state_admin', 'state_staff'];

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'roles'    => 'required|array|min:1',
            'roles.*'  => ['string', Rule::in($roles)],
        ]);

        $user->fill([
            'name'  => $data['name'],
            'email' => strtolower(trim($data['email'])),
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles($data['roles']);

        $audit->userUpdated($user);

        return back()->with('success', 'State user updated.');
    }

    public function destroy(PlatformUser $user, PlatformAuditLogger $audit)
    {
        abort_unless($user->tenant_id === null && $user->hasAnyRole(['state_admin', 'state_staff']), 404);
        abort_if($user->id === auth()->id(), 403, 'You cannot delete your own account.');

        $audit->userDeleted($user);
        $user->delete();

        return back()->with('success', 'State user removed.');
    }
}
