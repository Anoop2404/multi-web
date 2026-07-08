<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Support\Training\TrainingProgramPayload;
use App\Models\Certificate;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Ledger\LedgerAccountSetupService;
use App\Services\Notifications\NotificationService;
use App\Services\Training\TrainingCertificateService;
use App\Services\Training\TrainingReportService;
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
            'venue'               => 'nullable|string|max:255',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'registration_open'   => 'nullable|date',
            'registration_close'  => 'nullable|date',
            'max_participants'    => 'nullable|integer|min:1',
            'allow_teacher_self_registration' => 'nullable|boolean',
            'fee_type'            => 'nullable|in:none,flat',
            'fee_amount'          => 'nullable|numeric|min:0',
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['academic_year_id'] = AcademicYear::activeId();
        $data = TrainingProgramPayload::applyDefaults($data);

        $program = TrainingProgram::create($data);

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.program.created',
            "Training program created: {$program->title}",
        );

        return redirect("/sahodaya-admin/{$this->sahodaya->id}/training/{$program->id}")
            ->with('success', 'Training program created.');
    }

    public function show(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load(['sessions', 'registrations.teacher', 'registrations.school', 'registrations.feeReceipt', 'registrations.certificate']);

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
            'venue'              => 'nullable|string|max:255',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'max_participants'   => 'nullable|integer|min:1',
            'allow_teacher_self_registration' => 'nullable|boolean',
            'status'             => 'required|in:draft,published,ongoing,completed,cancelled',
            'fee_type'           => 'nullable|in:none,flat',
            'fee_amount'         => 'nullable|numeric|min:0',
        ]);

        $data = TrainingProgramPayload::applyDefaults($data);

        $program->update($data);

        app(LedgerAccountSetupService::class)->ensureTrainingProgramHead($program->fresh());

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.program.updated',
            "Training program updated: {$program->title}",
            ['status' => $data['status'] ?? $program->status],
        );

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
                $registration->isFullyPaid(),
                422,
                'Training fee must be fully paid before confirming registration.'
            );
        }

        $registration->update(['status' => 'confirmed']);

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.registration.confirmed',
            "Training registration confirmed for {$registration->teacher?->name}",
            [
                'registration_id' => $registration->id,
                'school_id'       => $registration->school_id,
                'teacher_id'      => $registration->teacher_id,
            ],
            $registration,
        );

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

        $receipt = $registration->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $registration->feeReceipt;
        abort_unless($receipt && $receipt->status === 'uploaded', 422, 'No uploaded proof to approve.');

        $receipt->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        // Accumulate into amount_paid; fee_status becomes partial or approved.
        $registration->refresh();
        $registration->refreshPaidState('fee_status');
        $fullyPaid = $registration->fresh()->isFullyPaid();

        // Training no longer needs a separate confirmation step — settling the fee
        // auto-confirms the registration and unlocks the certificate/ID card.
        if ($fullyPaid && $registration->fresh()->status === 'registered') {
            $registration->update(['status' => 'confirmed']);
        }

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

        app(OfflineProgramFeeOrchestrator::class)->notifyApproved(
            $registration->school,
            $issued,
            'Training fee',
            $registration->program?->title ?? 'Training Program',
            adminPath: 'payments',
        );

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.fee.approved',
            "Training fee approved for {$registration->teacher?->name}",
            ['registration_id' => $registration->id, 'school_id' => $registration->school_id, 'fully_paid' => $fullyPaid],
            $registration,
        );

        $balance = $registration->fresh()->outstandingBalance();

        return back()->with('success', $fullyPaid
            ? 'Training fee fully paid — registration confirmed.'
            : 'Partial payment of ₹'.number_format((float) $receipt->amount, 2).' approved. Balance ₹'.number_format($balance, 2).' pending.');
    }

    public function rejectFee(Request $request, string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $receipt = $registration->receipts()->where('status', 'uploaded')->latest('id')->first()
            ?? $registration->feeReceipt;
        if ($receipt && $receipt->status === 'uploaded') {
            $receipt->update([
                'status'           => 'rejected',
                'rejection_reason' => $data['rejection_reason'] ?? null,
                'reviewed_by'      => $request->user()->id,
                'reviewed_at'      => now(),
            ]);
        }

        // Preserve any approved partial payments; only clear the pointer if nothing is paid.
        $registration->refresh();
        $registration->refreshPaidState('fee_status');
        if ((float) $registration->fresh()->amount_paid <= 0) {
            $registration->update(['fee_receipt_id' => null]);
        }

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.fee.rejected',
            "Training fee rejected for {$registration->teacher?->name}",
            [
                'registration_id' => $registration->id,
                'school_id'       => $registration->school_id,
                'reason'          => $data['rejection_reason'] ?? null,
            ],
            $registration,
        );

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

    public function attendance(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load(['sessions' => fn ($q) => $q->orderBy('scheduled_at'), 'registrations.teacher', 'registrations.school']);

        $attendanceMap = TrainingAttendance::whereIn(
            'registration_id',
            $program->registrations->pluck('id')
        )->get()->groupBy('session_id')->map(fn ($rows) => $rows->keyBy('registration_id'));

        return $this->inertia('Sahodaya/Training/Attendance', [
            'program'       => $program,
            'attendanceMap' => $attendanceMap,
            'rows'          => $reports->attendanceRows($program),
        ]);
    }

    public function exportAttendance(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($program);
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
        $render = app(TrainingCertificateService::class)->renderContext($registration, $this->sahodaya);

        return view('training.certificate', array_merge($render, [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $this->sahodaya,
            'fieldValues'  => $fieldValues,
        ]));
    }

    public function previewCertificate(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $render = app(TrainingCertificateService::class)->sampleRenderContext($program, $this->sahodaya);

        return view('training.certificate', array_merge($render, [
            'registration' => null,
            'sahodaya'     => $this->sahodaya,
            'isSample'     => true,
        ]));
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

            $render = $service->renderContext($registration, $this->sahodaya);

            $html = view('training.certificate', array_merge($render, [
                'registration' => $registration,
                'certificate'  => $certificate,
                'sahodaya'     => $this->sahodaya,
                'fieldValues'  => $service->resolveFieldValues($registration, $this->sahodaya),
            ]))->render();

            $filename = str($registration->teacher?->name ?? 'teacher-'.$registration->id)->slug().'.html';
            $zip->addFromString($filename, $html);
        }

        $zip->close();

        return response()->download($zipPath, str($program->title)->slug().'-certificates.zip')->deleteFileAfterSend();
    }

    public function ledger(string $tenantId, TrainingProgram $program, \App\Services\Ledger\LedgerReportingService $reporting)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        app(\App\Services\Ledger\LedgerAccountSetupService::class)->ensureTrainingProgramHead($program);

        $ledger = $reporting->trainingProgramPaymentLedger($program);

        return $this->inertia('Sahodaya/Training/FeeLedger', [
            'program'       => $program->only('id', 'title', 'status', 'fee_type', 'fee_amount'),
            'accountCode'   => $ledger['account_code'],
            'accountName'   => $ledger['account_name'],
            'transactions'  => $ledger['transactions'],
            'registrations' => $ledger['registrations'],
            'summary'       => $ledger['summary'],
        ]);
    }

    public function updateLedgerAccount(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $setup = app(\App\Services\Ledger\LedgerAccountSetupService::class);
        $head = $setup->ensureTrainingProgramHead($program);
        $setup->updateHeadName($head, $data['name']);

        return back()->with('success', 'Ledger account name saved.');
    }
}
