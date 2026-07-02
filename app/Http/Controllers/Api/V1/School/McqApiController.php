<?php

namespace App\Http\Controllers\Api\V1\School;

use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\Student;
use App\Support\Mcq\McqResultPresenter;
use Illuminate\Http\Request;

class McqApiController extends SchoolApiController
{
    public function index()
    {
        $exams = McqExam::where('tenant_id', $this->school->parent_id)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->orderByDesc('scheduled_at')
            ->get();

        $registrations = McqRegistration::where('school_id', $this->school->id)
            ->whereIn('exam_id', $exams->pluck('id'))
            ->with(['exam', 'mark', 'student'])
            ->get()
            ->map(function (McqRegistration $reg) {
                return array_merge(
                    McqResultPresenter::forExamList($reg->exam, $reg),
                    [
                        'student' => $reg->student?->only('id', 'name', 'reg_no'),
                    ]
                );
            });

        return response()->json(['data' => ['exams' => $exams, 'registrations' => $registrations]]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_id'    => 'required|exists:mcq_exams,id',
            'student_id' => 'required|exists:students,id',
        ]);

        $exam = McqExam::findOrFail($data['exam_id']);
        abort_if($exam->tenant_id !== $this->school->parent_id, 403);

        $student = Student::findOrFail($data['student_id']);
        abort_if($student->tenant_id !== $this->school->id, 403);

        $registration = McqRegistration::firstOrCreate(
            ['exam_id' => $exam->id, 'student_id' => $student->id],
            ['school_id' => $this->school->id, 'status' => 'registered']
        );

        return response()->json(['data' => $registration->load('exam')], 201);
    }
}
