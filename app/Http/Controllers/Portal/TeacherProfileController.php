<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class TeacherProfileController extends Controller
{
    public function edit(Request $request, string $tenantId)
    {
        $user   = $request->user();
        $school = Tenant::findOrFail($tenantId);
        $teacher = Teacher::where('tenant_id', $tenantId)->where('user_id', $user->id)->first();

        return inertia('Portal/Teacher/Profile', [
            'school'  => $school->only('id', 'name'),
            'teacher' => $teacher
                ? $teacher->only('id', 'name', 'email', 'phone', 'designation', 'subject')
                : null,
            'user'    => $user->only('id', 'name', 'email'),
        ]);
    }

    public function update(Request $request, string $tenantId)
    {
        $user    = $request->user();
        $teacher = Teacher::where('tenant_id', $tenantId)->where('user_id', $user->id)->first();

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'email'       => 'required|email|max:255',
            'phone'       => 'nullable|string|max:20',
            'designation' => 'nullable|string|max:100',
        ]);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);

        if ($teacher) {
            $teacher->update([
                'name'        => $data['name'],
                'email'       => $data['email'],
                'phone'       => $data['phone'] ?? $teacher->phone,
                'designation' => $data['designation'] ?? $teacher->designation,
            ]);
        }

        return back()->with('success', 'Profile updated.');
    }

    public function updatePassword(Request $request, string $tenantId)
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->forceFill([
            'password'             => $data['password'],
            'plain_password'       => null,
            'must_change_password' => false,
        ])->save();

        return back()->with('success', 'Password updated.');
    }
}
