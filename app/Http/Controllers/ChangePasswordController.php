<?php

namespace App\Http\Controllers;

use App\Models\PlatformUser;
use Illuminate\Http\Request;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $organization = null;

        if ($user instanceof PlatformUser) {
            $user->loadMissing('state');
            $organization = $user->state;
        } elseif ($user) {
            $user->loadMissing('tenant');
            $organization = $user->tenant;
        }

        $roleName = $user?->getRoleNames()->first();

        return inertia('Auth/ChangePassword', [
            'organizationName' => $organization?->name,
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

        $user->forceFill([
            'password'             => $data['password'],
            'plain_password'       => null,
            'must_change_password' => false,
        ])->save();

        if (PortalWelcomeController::shouldShowForUser($user->fresh())) {
            return redirect()->route('portal.welcome');
        }

        $home = \App\Http\Controllers\Admin\AuthController::homeFor($user);

        return redirect($home ?? '/')->with('success', 'Password updated successfully.');
    }
}
