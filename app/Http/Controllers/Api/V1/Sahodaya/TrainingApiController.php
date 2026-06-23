<?php

namespace App\Http\Controllers\Api\V1\Sahodaya;

use App\Models\TrainingProgram;

class TrainingApiController extends SahodayaApiController
{
    public function index()
    {
        $programs = TrainingProgram::where('tenant_id', $this->sahodaya->id)
            ->withCount(['registrations', 'sessions'])
            ->orderByDesc('registration_open')
            ->get();

        return response()->json(['data' => $programs]);
    }

    public function show(TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return response()->json(['data' => $program->load(['sessions', 'registrations.teacher'])]);
    }
}
