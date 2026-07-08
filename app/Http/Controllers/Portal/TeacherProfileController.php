<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Support\TenantStorage;
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
                ? array_merge(
                    $teacher->only('id', 'name', 'email', 'mobile', 'designation', 'subject', 'reg_no'),
                    ['photo_url' => $teacher->portalPhotoUrl()],
                )
                : null,
            'user'    => $user->only('id', 'name', 'email'),
        ]);
    }

    public function photo(Request $request, string $tenantId)
    {
        $teacher = $request->attributes->get('portalTeacher');
        abort_unless($teacher->photo, 404);

        $school = Tenant::findOrFail($tenantId);

        try {
            return TenantStorage::downloadResponse($school, $teacher->photo);
        } catch (\Throwable) {
            abort(404, 'Photo not found.');
        }
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
                'mobile'      => $data['phone'] ?? $teacher->mobile,
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
