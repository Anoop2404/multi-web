<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Response;

class ChangePasswordController extends Controller
{
    public function show(): Response
    {
        return inertia('Auth/ChangePassword');
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

        $home = \App\Http\Controllers\Admin\AuthController::homeFor($user);

        return redirect($home ?? '/')->with('success', 'Password updated successfully.');
    }
}
