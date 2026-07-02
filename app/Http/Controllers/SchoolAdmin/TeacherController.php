<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\Teacher;
use App\Support\AcademicYear;
use App\Support\TenantStorage;
use App\Services\Portal\TeacherPortalProvisioner;
use Illuminate\Http\Request;

class TeacherController extends SchoolAdminController
{
    public function index()
    {
        $teachers = Teacher::where('tenant_id', $this->school->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Teacher $t) => [
                ...$t->only('id', 'name', 'email', 'designation', 'user_id'),
                'photo_url' => $t->photoUrl(),
            ]);

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

        $yearId = AcademicYear::activeId();

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
            app(TeacherPortalProvisioner::class)->provision(
                $teacher,
                $data['email'],
                $data['password']
            );
        }

        return back()->with('success', 'Teacher added.');
    }

    public function destroy(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);
        $teacher->delete();

        return back()->with('success', 'Teacher removed.');
    }

    public function showPhoto(string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);
        abort_unless($teacher->photo, 404);

        try {
            return TenantStorage::downloadResponse($this->school, $teacher->photo);
        } catch (\Throwable) {
            abort(404, 'Photo not found.');
        }
    }

    public function updatePhoto(Request $request, string $tenantId, Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $request->validate([
            'photo' => 'required|image|max:2048',
        ]);

        $teacher->update([
            'photo' => TenantStorage::storeTeacherPhoto($request->file('photo'), $this->school->id),
        ]);

        return back()->with('success', 'Teacher photo updated.');
    }
}
