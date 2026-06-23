<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Models\AcademicYearRecord;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use Illuminate\Http\Request;

class TrainingProgramController extends SahodayaAdminController
{
    public function index()
    {
        $programs = TrainingProgram::where('tenant_id', $this->sahodaya->id)
            ->withCount(['registrations', 'sessions'])
            ->orderByDesc('registration_open')
            ->get();

        return $this->inertia('Sahodaya/Training/Index', compact('programs'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'registration_open'   => 'nullable|date',
            'registration_close'  => 'nullable|date',
            'max_participants'    => 'nullable|integer|min:1',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['academic_year_id'] = AcademicYearRecord::where('tenant_id', $this->sahodaya->id)
            ->where('is_active', true)->value('id');

        $program = TrainingProgram::create($data);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/training/{$program->id}")
            ->with('success', 'Training program created.');
    }

    public function show(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load(['sessions', 'registrations.teacher']);

        return $this->inertia('Sahodaya/Training/Show', compact('program'));
    }

    public function update(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'              => 'required|string|max:255',
            'description'        => 'nullable|string',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'max_participants'   => 'nullable|integer|min:1',
            'status'             => 'required|in:draft,published,ongoing,completed,cancelled',
        ]);

        $program->update($data);

        return back()->with('success', 'Program updated.');
    }

    public function storeSession(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'scheduled_at'     => 'nullable|date',
            'venue'            => 'nullable|string|max:255',
            'duration_minutes' => 'nullable|integer|min:15',
        ]);

        $data['program_id'] = $program->id;
        TrainingSession::create($data);

        return back()->with('success', 'Session added.');
    }

    public function confirmRegistration(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $registration->update(['status' => 'confirmed']);

        return back()->with('success', 'Registration confirmed.');
    }
}
