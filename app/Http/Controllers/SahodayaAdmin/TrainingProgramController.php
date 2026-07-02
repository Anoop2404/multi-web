<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Support\Training\TrainingProgramPayload;
use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use App\Services\Fees\ProgramFeeReceiptMailer;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Notifications\NotificationService;
use App\Services\Training\TrainingCertificateService;
use App\Support\TenantStorage;
use Illuminate\Http\Request;

class TrainingProgramController extends SahodayaAdminController
{
    public function index()
    {
        $programs = TrainingProgram::where('tenant_id', $this->sahodaya->id)
            ->withCount(['registrations', 'sessions'])
            ->orderByDesc('registration_open')
            ->get();

        $openStatuses = ['published', 'registration_open', 'ongoing'];

        return $this->inertia('Sahodaya/Training/Index', [
            'programs' => $programs,
            'stats'    => [
                'programs'      => $programs->count(),
                'open'          => $programs->filter(fn ($p) => $p->registration_open && (! $p->registration_close || $p->registration_close >= now()))->count(),
                'registrations' => (int) $programs->sum('registrations_count'),
                'sessions'      => (int) $programs->sum('sessions_count'),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'               => 'required|string|max:255',
            'description'         => 'nullable|string',
            'registration_open'   => 'nullable|date',
            'registration_close'  => 'nullable|date',
            'max_participants'    => 'nullable|integer|min:1',
            'fee_type'            => 'nullable|in:none,flat',
            'fee_amount'          => 'nullable|numeric|min:0',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['academic_year_id'] = AcademicYear::activeId();
        $data = TrainingProgramPayload::applyDefaults($data);

        $program = TrainingProgram::create($data);

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/training/{$program->id}")
            ->with('success', 'Training program created.');
    }

    public function show(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load(['sessions', 'registrations.teacher', 'registrations.feeReceipt', 'registrations.certificate']);

        $attendanceMap = TrainingAttendance::whereIn(
            'registration_id',
            $program->registrations->pluck('id')
        )->get()->groupBy('session_id')->map(fn ($rows) => $rows->keyBy('registration_id'));

        return $this->inertia('Sahodaya/Training/Show', compact('program', 'attendanceMap'));
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
            'fee_type'           => 'nullable|in:none,flat',
            'fee_amount'         => 'nullable|numeric|min:0',
        ]);

        $data = TrainingProgramPayload::applyDefaults($data);

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
        abort_unless($registration->status === 'registered', 422, 'Only registered participants can be confirmed.');

        if ($program->hasFee()) {
            abort_unless(
                $registration->feeReceipt?->status === 'approved',
                422,
                'Training fee must be approved before confirming registration.'
            );
        }

        $registration->update(['status' => 'confirmed']);

        $registration->load('teacher', 'program');
        $teacherUser = $registration->teacher?->user_id
            ? \App\Models\User::find($registration->teacher->user_id)
            : null;
        if ($teacherUser) {
            app(NotificationService::class)->notifyFromTemplate(
                $teacherUser,
                'training.registration.confirmed',
                [
                    'program_title' => $program->title,
                    'teacher_name'  => $registration->teacher->name,
                ]
            );
        }

        return back()->with('success', 'Registration confirmed.');
    }

    public function approveFee(Request $request, string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $registration->loadMissing(['program', 'teacher', 'school']);

        $receipt = $registration->feeReceipt;
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        $receipt->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $issued = app(ProgramFeeReceiptService::class)->issueTraining(
            $registration->fresh(['program', 'teacher', 'school']),
            $receipt->fresh(),
        );

        $registration->loadMissing('program');
        $schoolId = $registration->school_id;
        $service = app(\App\Services\Notifications\NotificationService::class);
        foreach (\App\Models\User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'training.fee.approved', [
                'program_title' => $registration->program->title,
            ], "/school-admin/{$schoolId}/training");
        }

        app(ProgramFeeReceiptMailer::class)->sendApproved(
            $registration->school,
            $issued,
            'Training fee',
            $registration->program?->title ?? 'Training Program',
            adminPath: 'payments',
        );

        return back()->with('success', 'Training fee approved.');
    }

    public function rejectFee(Request $request, string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $receipt = $registration->feeReceipt;
        if ($receipt) {
            $receipt->update([
                'status'           => 'rejected',
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'reviewed_by'      => $request->user()->id,
                'reviewed_at'      => now(),
            ]);
        }

        $registration->update(['fee_receipt_id' => null]);

        $registration->loadMissing('program');
        $schoolId = $registration->school_id;
        $service = app(\App\Services\Notifications\NotificationService::class);
        foreach (\App\Models\User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'training.fee.rejected', [
                'program_title' => $registration->program->title,
                'reason'        => $data['rejection_reason'] ?? 'Contact your Sahodaya for details.',
            ], "/school-admin/{$schoolId}/training");
        }

        return back()->with('success', 'Training fee rejected. School can re-upload.');
    }

    public function feeProof(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $path = $registration->feeReceipt?->file_path;
        abort_unless($path, 404);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $path);
    }

    public function storeSessionAttendance(string $tenantId, TrainingProgram $program, TrainingSession $session)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($session->program_id !== $program->id, 403);

        $registrations = TrainingRegistration::where('program_id', $program->id)
            ->where('status', 'confirmed')
            ->get();

        foreach ($registrations as $registration) {
            TrainingAttendance::updateOrCreate(
                ['session_id' => $session->id, 'registration_id' => $registration->id],
                ['status' => 'present', 'marked_by' => auth()->id(), 'marked_at' => now()]
            );
        }

        return back()->with('success', 'Attendance marked for '.$registrations->count().' participant(s).');
    }

    public function updateAttendance(Request $request, string $tenantId, TrainingProgram $program, TrainingSession $session, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($session->program_id !== $program->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $data = $request->validate([
            'status' => 'required|in:present,absent',
        ]);

        TrainingAttendance::updateOrCreate(
            ['session_id' => $session->id, 'registration_id' => $registration->id],
            array_merge($data, ['marked_by' => auth()->id(), 'marked_at' => now()])
        );

        return back()->with('success', 'Attendance updated.');
    }

    public function issueCertificate(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        app(TrainingCertificateService::class)->issue($registration);

        return back()->with('success', 'Certificate issued.');
    }

    public function printCertificate(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $registration->load(['program', 'teacher']);
        $certificate = \App\Models\Certificate::where('entity_type', TrainingRegistration::class)
            ->where('entity_id', $registration->id)
            ->firstOrFail();

        $fieldValues = app(TrainingCertificateService::class)->resolveFieldValues($registration, $this->sahodaya);

        return view('training.certificate', [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $this->sahodaya,
            'fieldValues'  => $fieldValues,
        ]);
    }

    public function exportCertificatesZip(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $registrations = TrainingRegistration::where('program_id', $program->id)
            ->where('status', 'confirmed')
            ->with(['teacher', 'program'])
            ->get();

        abort_if($registrations->isEmpty(), 422, 'No confirmed registrations to export.');

        $service = app(TrainingCertificateService::class);
        $zipPath = storage_path('app/tmp/training-certs-'.$program->id.'-'.time().'.zip');
        @mkdir(dirname($zipPath), 0755, true);

        $zip = new \ZipArchive;
        $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        foreach ($registrations as $registration) {
            $certificate = Certificate::where('entity_type', TrainingRegistration::class)
                ->where('entity_id', $registration->id)
                ->first();

            if (! $certificate) {
                $certificate = $service->issue($registration);
            }

            $html = view('training.certificate', [
                'registration' => $registration,
                'certificate'  => $certificate,
                'sahodaya'     => $this->sahodaya,
                'fieldValues'  => $service->resolveFieldValues($registration, $this->sahodaya),
            ])->render();

            $filename = str($registration->teacher?->name ?? 'teacher-'.$registration->id)->slug().'.html';
            $zip->addFromString($filename, $html);
        }

        $zip->close();

        return response()->download($zipPath, str($program->title)->slug().'-certificates.zip')->deleteFileAfterSend();
    }
}
