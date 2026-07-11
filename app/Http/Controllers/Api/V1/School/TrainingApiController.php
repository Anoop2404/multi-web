<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\Teacher;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Training\TeacherTrainingEligibilityService;
use Illuminate\Http\Request;

class TrainingApiController extends SchoolApiController
{
    public function index()
    {
        $programs = TrainingProgram::where('tenant_id', $this->school->parent_id)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('registration_open')
            ->get();

        $registrations = TrainingRegistration::where('school_id', $this->school->id)
            ->whereIn('program_id', $programs->pluck('id'))
            ->with(['program', 'teacher'])
            ->get();

        return response()->json(['data' => ['programs' => $programs, 'registrations' => $registrations]]);
    }

    public function store(Request $request, TeacherTrainingEligibilityService $eligibility)
    {
        $data = $request->validate([
            'program_id' => 'required|exists:training_programs,id',
            'teacher_id' => 'required|exists:teachers,id',
        ]);

        $program = TrainingProgram::findOrFail($data['program_id']);
        abort_if($program->tenant_id !== $this->school->parent_id, 403);

        $teacher = Teacher::findOrFail($data['teacher_id']);
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $eligibility->assertTeacherEligible($program, $teacher);

        $seat = app(\App\Services\Training\TrainingWaitlistService::class)
            ->resolveCreateAttributes($program, 'school');

        $registration = TrainingRegistration::firstOrCreate(
            ['program_id' => $program->id, 'teacher_id' => $teacher->id],
            array_merge([
                'school_id'           => $this->school->id,
                'registration_source' => 'school',
            ], $seat)
        );

        return response()->json(['data' => $registration->load(['program', 'teacher'])], 201);
    }
}
