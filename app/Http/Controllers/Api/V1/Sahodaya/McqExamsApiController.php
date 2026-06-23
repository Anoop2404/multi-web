<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\McqExam;

class McqExamsApiController extends SahodayaApiController
{
    public function index()
    {
        $exams = McqExam::where('tenant_id', $this->sahodaya->id)
            ->withCount('registrations')
            ->orderByDesc('scheduled_at')
            ->get();

        return response()->json(['data' => $exams]);
    }

    public function show(McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return response()->json(['data' => $exam->load(['registrations.mark'])]);
    }
}
