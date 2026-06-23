<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqRegistration;
use Illuminate\Http\Request;

class McqExamController extends SahodayaAdminController
{
    public function index()
    {
        $exams = McqExam::where('tenant_id', $this->sahodaya->id)
            ->withCount('registrations')
            ->orderByDesc('scheduled_at')
            ->get();

        return $this->inertia('Sahodaya/Mcq/Index', compact('exams'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'             => 'required|string|max:255',
            'exam_type'         => 'nullable|in:practice,assessment,competitive',
            'scheduled_at'      => 'nullable|date',
            'duration_minutes'  => 'nullable|integer|min:5|max:480',
            'total_questions'   => 'nullable|integer|min:1',
            'pass_mark'         => 'nullable|integer|min:0',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['academic_year_id'] = AcademicYearRecord::where('tenant_id', $this->sahodaya->id)
            ->where('is_active', true)->value('id');

        $exam = McqExam::create($data);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/mcq-exams/{$exam->id}")
            ->with('success', 'Exam created.');
    }

    public function show(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $registrations = McqRegistration::where('exam_id', $exam->id)
            ->with('mark')
            ->get();

        return $this->inertia('Sahodaya/Mcq/Show', compact('exam', 'registrations'));
    }

    public function update(Request $request, string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'scheduled_at'     => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'total_questions'  => 'nullable|integer|min:1',
            'pass_mark'        => 'nullable|integer|min:0',
            'status'           => 'required|in:draft,published,ongoing,completed,cancelled',
        ]);

        $exam->update($data);

        return back()->with('success', 'Exam updated.');
    }

    public function storeMark(Request $request, string $tenantId, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $data = $request->validate([
            'correct_count'     => 'required|integer|min:0',
            'wrong_count'       => 'required|integer|min:0',
            'unanswered_count'  => 'required|integer|min:0',
            'score'             => 'required|numeric|min:0',
            'grade'             => 'nullable|in:A,B,C,D,F',
        ]);

        $total = $data['correct_count'] + $data['wrong_count'] + $data['unanswered_count'];
        $data['percentage'] = $total > 0 ? round(($data['score'] / max($exam->total_questions, 1)) * 100, 2) : 0;
        $data['locked_by'] = $request->user()->id;
        $data['locked_at'] = now();

        McqMark::updateOrCreate(['registration_id' => $registration->id], $data);
        $registration->update(['status' => 'submitted', 'submitted_at' => now()]);

        return back()->with('success', 'Marks saved.');
    }
}
