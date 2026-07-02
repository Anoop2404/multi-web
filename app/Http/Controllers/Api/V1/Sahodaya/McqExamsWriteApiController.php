<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\McqExam;
use App\Support\Mcq\McqExamPayload;
use App\Models\McqMark;
use App\Models\McqRegistration;
use Illuminate\Http\Request;

class McqExamsWriteApiController extends SahodayaApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'scheduled_at'     => 'nullable|date',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'total_questions'  => 'nullable|integer|min:1',
            'pass_mark'        => 'nullable|integer|min:0',
            'fee_amount'       => 'required|numeric|min:0.01',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['fee_type'] = 'flat';
        $data['next_hall_ticket_no'] = 100;
        $data = McqExamPayload::applyDefaults($data);

        $exam = McqExam::create($data);

        return response()->json(['data' => $exam], 201);
    }

    public function storeMark(Request $request, McqExam $exam, McqRegistration $registration)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->exam_id !== $exam->id, 403);

        $data = $request->validate([
            'correct_count'    => 'required|integer|min:0',
            'wrong_count'      => 'required|integer|min:0',
            'unanswered_count' => 'required|integer|min:0',
            'score'            => 'required|numeric|min:0',
            'grade'            => 'nullable|in:A,B,C,D,F',
        ]);

        McqMark::updateOrCreate(['registration_id' => $registration->id], array_merge($data, [
            'locked_by' => $request->user()->id,
            'locked_at' => now(),
        ]));

        $registration->update(['status' => 'submitted', 'submitted_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
