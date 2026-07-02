<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class StudentProfileController extends Controller
{
    public function edit(Request $request, string $tenantId)
    {
        $student = $request->attributes->get('portalStudent');
        $school = Tenant::findOrFail($tenantId);

        return inertia('Portal/Student/Profile', [
            'school'  => $school->only('id', 'name'),
            'student' => $student->only('id', 'name', 'reg_no', 'parent_phone', 'email'),
            'user'    => $request->user()->only('id', 'name', 'email'),
        ]);
    }

    public function update(Request $request, string $tenantId)
    {
        $student = $request->attributes->get('portalStudent');
        abort_if($student->tenant_id !== $tenantId, 403);

        $data = $request->validate([
            'email'        => 'required|email|max:255',
            'parent_phone' => 'nullable|string|max:20',
        ]);

        $student->update([
            'email'        => $data['email'],
            'parent_phone' => $data['parent_phone'] ?? null,
        ]);

        $request->user()->update(['email' => $data['email']]);

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request, string $tenantId)
    {
        $request->attributes->get('portalStudent');

        $data = $request->validate([
            'current_password'      => 'required|current_password',
            'password'              => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
