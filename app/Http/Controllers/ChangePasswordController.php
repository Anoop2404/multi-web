<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $user?->loadMissing('tenant');
        $tenant = $user?->tenant;
        $roleName = $user?->getRoleNames()->first();

        return inertia('Auth/ChangePassword', [
            'organizationName' => $tenant?->name,
            'roleLabel'        => $roleName
                ? ucwords(str_replace('_', ' ', $roleName))
                : null,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'password'              => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password'             => Hash::make($data['password']),
            'must_change_password' => false,
        ]);

        if (PortalWelcomeController::shouldShowForUser($user->fresh())) {
            return redirect()->route('portal.welcome');
        }

        $home = \App\Http\Controllers\Admin\AuthController::homeFor($user);

        return redirect($home ?? '/')->with('success', 'Password updated successfully.');
    }
}
