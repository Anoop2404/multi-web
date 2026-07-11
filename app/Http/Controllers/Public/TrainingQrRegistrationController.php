<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingSession;
use App\Services\Training\TrainingAttendanceCheckInService;
use App\Services\Training\TrainingPublicRegistrationService;
use App\Services\Training\TrainingQrService;
use App\Support\TenantBranding;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrainingQrRegistrationController extends Controller
{
    public function show(string $token, TrainingQrService $qr, TrainingPublicRegistrationService $service, \App\Services\Membership\EffectiveMasterDataResolver $resolver)
    {
        $program = TrainingProgram::where('qr_registration_token', $token)->firstOrFail();
        $sahodaya = Tenant::findOrFail($program->tenant_id);
        $open = $qr->isRegistrationOpen($program);

        return view('public.training.register', [
            'program'  => $program,
            'sahodaya' => $sahodaya,
            'logoUrl'  => TenantBranding::logoUrl($sahodaya),
            'open'     => $open,
            'token'    => $token,
            'schools'  => $service->listSchools($program),
            'teachingTypes' => $resolver->teachingTypes($sahodaya->id)
                ->map(fn ($t) => ['id' => $t->id, 'code' => $t->code, 'label' => $t->label])
                ->values()
                ->all(),
            'designations' => $resolver->designations($sahodaya->id)
                ->map(fn ($d) => ['id' => $d->id, 'label' => $d->label])
                ->values()
                ->all(),
        ]);
    }

    public function searchSchools(Request $request, string $token, TrainingPublicRegistrationService $service)
    {
        $program = TrainingProgram::where('qr_registration_token', $token)->firstOrFail();
        $q = (string) $request->query('q', '');

        return response()->json([
            'schools' => $q === ''
                ? $service->listSchools($program)
                : $service->searchSchools($program, $q),
        ]);
    }

    public function store(Request $request, string $token, TrainingPublicRegistrationService $service, TrainingQrService $qr)
    {
        $program = TrainingProgram::where('qr_registration_token', $token)->firstOrFail();

        if (! $qr->isRegistrationOpen($program)) {
            return back()->withErrors(['registration' => 'QR registration is closed for this training programme.']);
        }

        $data = $request->validate([
            'name'                => 'required|string|max:150',
            'email'               => 'required|email|max:150',
            'phone'               => 'nullable|string|max:20',
            'dob'                 => 'nullable|date|before:today',
            'gender'              => 'required|in:male,female,other',
            'school_id'           => 'nullable|string',
            'manual_school_name'  => 'nullable|string|max:255',
            'manual_school_code'  => 'nullable|string|max:50',
            'teaching_type_id'    => [
                'required',
                'integer',
                Rule::exists((new \App\Models\TeachingType)->getConnectionName().'.teaching_types', 'id'),
            ],
            'designation_id'      => [
                'nullable',
                'integer',
                Rule::exists((new \App\Models\Designation)->getConnectionName().'.designations', 'id'),
            ],
            'department'          => 'nullable|string|max:120',
            'experience'          => 'nullable|integer|min:0|max:50',
            'photo'               => 'nullable|image|max:2048',
            'consent'             => 'accepted',
        ]);

        $allowedTypeIds = app(\App\Services\Membership\EffectiveMasterDataResolver::class)
            ->teachingTypes($program->tenant_id)
            ->pluck('id')
            ->all();
        if (! in_array((int) $data['teaching_type_id'], array_map('intval', $allowedTypeIds), true)) {
            return back()->withInput()->withErrors([
                'teaching_type_id' => 'Select a valid teacher category for this Sahodaya.',
            ]);
        }

        if (empty($data['school_id']) && empty($data['manual_school_name'])) {
            return back()->withInput()->withErrors([
                'school_id' => 'Select a school or enter your school name manually.',
            ]);
        }

        $result = $service->register($program, $data, $request->file('photo'));

        return redirect()
            ->route('tenant.training.register.success', ['token' => $token])
            ->with('registration_id', $result['registration']->id)
            ->with('teacher_created', $result['teacher_created'])
            ->with('pending_school', (bool) $result['pending_school']);
    }

    public function success(string $token)
    {
        $program = TrainingProgram::where('qr_registration_token', $token)->firstOrFail();
        $sahodaya = Tenant::findOrFail($program->tenant_id);

        return view('public.training.register-success', [
            'program'         => $program,
            'sahodaya'        => $sahodaya,
            'logoUrl'         => TenantBranding::logoUrl($sahodaya),
            'registrationId'  => session('registration_id'),
            'teacherCreated'  => (bool) session('teacher_created'),
            'pendingSchool'   => (bool) session('pending_school'),
        ]);
    }

    public function attendanceSession(string $token)
    {
        $session = TrainingSession::where('attendance_token', $token)->with('program')->firstOrFail();
        $program = $session->program;
        $sahodaya = Tenant::findOrFail($program->tenant_id);

        return view('public.training.attendance', [
            'program'  => $program,
            'session'  => $session,
            'sahodaya' => $sahodaya,
            'logoUrl'  => TenantBranding::logoUrl($sahodaya),
            'token'    => $token,
            'mode'     => 'session',
        ]);
    }

    public function attendanceProgram(string $token)
    {
        $program = TrainingProgram::where('attendance_qr_token', $token)->with(['sessions' => fn ($q) => $q->orderBy('scheduled_at')])->firstOrFail();
        $sahodaya = Tenant::findOrFail($program->tenant_id);

        return view('public.training.attendance', [
            'program'  => $program,
            'session'  => null,
            'sahodaya' => $sahodaya,
            'logoUrl'  => TenantBranding::logoUrl($sahodaya),
            'token'    => $token,
            'mode'     => 'program',
        ]);
    }

    public function storeAttendance(
        Request $request,
        string $token,
        TrainingAttendanceCheckInService $checkIn,
    ) {
        $session = TrainingSession::where('attendance_token', $token)->with('program')->first();

        if ($session) {
            $program = $session->program;
            $data = $request->validate([
                'lookup' => 'required|string|max:150',
            ]);
        } else {
            $program = TrainingProgram::where('attendance_qr_token', $token)->firstOrFail();
            $data = $request->validate([
                'session_id' => ['required', Rule::exists('training_sessions', 'id')->where('program_id', $program->id)],
                'lookup'     => 'required|string|max:150',
            ]);
            $session = TrainingSession::where('program_id', $program->id)->findOrFail($data['session_id']);
        }

        $attendance = $checkIn->checkIn($program, $session, $data['lookup']);
        $attendance->load('registration.teacher');

        return back()->with('success', 'Attendance marked present for '.($attendance->registration?->teacher?->name ?? 'participant').'.');
    }
}
