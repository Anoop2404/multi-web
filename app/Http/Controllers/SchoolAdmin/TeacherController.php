<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Teacher;
use App\Models\User;
use App\Support\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class TeacherController extends SchoolAdminController
{
    public function index()
    {
        $teachers = Teacher::where('tenant_id', $this->school->id)
            ->orderBy('name')
            ->get();

        return $this->inertia('School/Teachers/Index', compact('teachers'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'designation'  => 'nullable|string|max:255',
            'subject'      => 'nullable|string|max:255',
            'create_login' => 'boolean',
            'password'     => 'nullable|string|min:8',
        ]);

        $yearId = \App\Models\AcademicYearRecord::where('tenant_id', $this->school->parent_id)
            ->where('is_active', true)->value('id');

        $teacher = Teacher::create([
            'tenant_id'        => $this->school->id,
            'academic_year_id' => $yearId,
            'name'             => $data['name'],
            'email'            => $data['email'] ?? null,
            'designation'      => $data['designation'] ?? null,
            'subject'          => $data['subject'] ?? null,
            'status'           => 'active',
        ]);

        if ($request->boolean('create_login') && ! empty($data['email']) && ! empty($data['password'])) {
            $user = User::create([
                'name'      => $data['name'],
                'email'     => strtolower($data['email']),
                'password'  => Hash::make($data['password']),
                'tenant_id' => $this->school->id,
            ]);
            Role::findByName('teacher', 'web');
            $user->assignRole('teacher');
            $teacher->update(['user_id' => $user->id]);
        }

        return back()->with('success', 'Teacher added.');
    }

    public function destroy(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);
        $teacher->delete();

        return back()->with('success', 'Teacher removed.');
    }
}
