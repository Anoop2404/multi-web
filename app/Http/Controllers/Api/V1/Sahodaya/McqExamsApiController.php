<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\McqExam;

class McqExamsApiController extends SahodayaApiController
{
    public function index()
    {
        // Active = excludes cancelled registrations, matching the Sahodaya admin dashboard/exam list.
        $exams = McqExam::where('tenant_id', $this->sahodaya->id)
            ->withCount(['registrations' => fn ($q) => $q->active()])
            ->orderByDesc('scheduled_at')
            ->get();

        return response()->json(['data' => $exams]);
    }

    public function show(string $tenantId, McqExam $exam)
    {
        abort_if($exam->tenant_id !== $this->sahodaya->id, 403);

        return response()->json(['data' => $exam->load(['registrations.mark'])]);
    }
}
