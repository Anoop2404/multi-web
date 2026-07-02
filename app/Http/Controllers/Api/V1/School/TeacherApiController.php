<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\Teacher;
use App\Support\AcademicYear;
use App\Services\Portal\TeacherPortalProvisioner;
use Illuminate\Http\Request;

class TeacherApiController extends SchoolApiController
{
    public function index()
    {
        $teachers = Teacher::where('tenant_id', $this->school->id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $teachers]);
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
            app(TeacherPortalProvisioner::class)->provision($teacher, $data['email'], $data['password']);
        }

        return response()->json(['data' => $teacher->fresh()], 201);
    }

    public function destroy(Teacher $teacher)
    {
        abort_if($teacher->tenant_id !== $this->school->id, 403);
        $teacher->delete();

        return response()->json(['ok' => true]);
    }
}
