<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Support\Training\TrainingProgramEligibilityConfig;
use App\Support\Training\TrainingProgramPayload;
use App\Models\Certificate;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\TrainingFeedback;
use App\Models\TrainingPendingSchool;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use App\Services\Fees\OfflineProgramFeeOrchestrator;
use App\Services\Fees\ProgramFeeReceiptService;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Ledger\LedgerAccountSetupService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Services\Notifications\NotificationService;
use App\Services\Training\TrainingCertificateService;
use App\Services\Training\TrainingFeedbackService;
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
            'min_attendance_percent' => 'nullable|integer|min:0|max:100',
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

    public function show(string $tenantId, TrainingProgram $program, \App\Services\Training\TrainingQrService $qr)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $qr->ensureProgramTokens($program);
        $program->load(['sessions', 'registrations.teacher', 'registrations.school', 'registrations.feeReceipt', 'registrations.certificate', 'registrations.pendingSchool']);

        $attendanceMap = TrainingAttendance::whereIn(
            'registration_id',
            $program->registrations->pluck('id')
        )->get()->groupBy('session_id')->map(fn ($rows) => $rows->keyBy('registration_id'));

        $registrationUrl = $qr->registrationUrl($program);
        $attendanceUrl = $qr->attendanceUrl($program);

        $resolver = app(EffectiveMasterDataResolver::class);
        $eligibilityPrograms = TrainingProgram::where('tenant_id', $this->sahodaya->id)
            ->where('id', '!=', $program->id)
            ->orderBy('title')
            ->get(['id', 'title', 'status']);

        return $this->inertia('Sahodaya/Training/Show', [
            'program' => array_merge($program->toArray(), [
                'eligibility_config' => TrainingProgramEligibilityConfig::normalize($program->eligibility_config),
            ]),
            'attendanceMap' => $attendanceMap,
            'eligibilityOptions' => [
                'teaching_types' => $resolver->teachingTypes($this->sahodaya->id)->map->only(['id', 'label'])->values(),
                'subjects' => $resolver->subjects($this->sahodaya->id)->map->only(['id', 'label'])->values(),
                'designations' => $resolver->designations($this->sahodaya->id)->map->only(['id', 'label'])->values(),
                'regions' => Region::forTenant($this->sahodaya->id)->active()->orderBy('sort_order')->orderBy('name')
                    ->get(['id', 'name']),
                'prior_programs' => $eligibilityPrograms,
            ],
            'qr' => [
                'registration_url' => $registrationUrl,
                'attendance_url'   => $attendanceUrl,
                'registration_open'=> $qr->isRegistrationOpen($program),
                'registration_png' => $qr->dataUri($registrationUrl),
                'attendance_png'   => $qr->dataUri($attendanceUrl),
                'session_urls'     => $program->sessions->mapWithKeys(function ($session) use ($qr, $program) {
                    $qr->ensureSessionToken($session);

                    return [$session->id => $qr->attendanceUrl($program, $session)];
                }),
            ],
        ]);
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
            'qr_registration_enabled' => 'nullable|boolean',
            'require_verified_teachers' => 'nullable|boolean',
            'allow_school_attendance' => 'nullable|boolean',
            'status'             => 'required|in:draft,published,ongoing,completed,cancelled',
            'fee_type'           => 'nullable|in:none,flat',
            'fee_amount'         => 'nullable|numeric|min:0',
            'min_attendance_percent' => 'nullable|integer|min:0|max:100',
            'eligibility_config' => 'nullable|array',
            'eligibility_config.teaching_type_ids' => 'nullable|array',
            'eligibility_config.teaching_type_ids.*' => 'integer',
            'eligibility_config.subject_ids' => 'nullable|array',
            'eligibility_config.subject_ids.*' => 'integer',
            'eligibility_config.excluded_designation_ids' => 'nullable|array',
            'eligibility_config.excluded_designation_ids.*' => 'integer',
            'eligibility_config.min_experience_years' => 'nullable|integer|min:0|max:60',
            'eligibility_config.prior_training' => 'nullable|array',
            'eligibility_config.prior_training.required' => 'nullable|boolean',
            'eligibility_config.prior_training.program_id' => 'nullable|integer',
            'eligibility_config.region_ids' => 'nullable|array',
            'eligibility_config.region_ids.*' => 'integer',
        ]);

        $data = TrainingProgramPayload::applyDefaults($data);
        $data['qr_registration_enabled'] = (bool) ($data['qr_registration_enabled'] ?? false);
        $data['require_verified_teachers'] = (bool) ($data['require_verified_teachers'] ?? false);
        $data['allow_school_attendance'] = (bool) ($data['allow_school_attendance'] ?? true);

        if (array_key_exists('eligibility_config', $data)) {
            if ($error = TrainingProgramEligibilityConfig::validationError($data['eligibility_config'])) {
                return back()->withErrors(['eligibility_config' => $error]);
            }
            $data['eligibility_config'] = TrainingProgramEligibilityConfig::normalize($data['eligibility_config']);
        }

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

    public function registrations(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load([
            'registrations' => fn ($q) => $q->latest('id'),
            'registrations.teacher.teachingType',
            'registrations.school',
            'registrations.feeReceipt',
            'registrations.certificate',
            'registrations.pendingSchool',
        ]);

        return $this->inertia('Sahodaya/Training/Registrations', [
            'program' => $program->only([
                'id', 'title', 'status', 'fee_type', 'fee_amount',
            ]),
            'registrations' => $program->registrations,
        ]);
    }

    public function exportRegistrations(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportRegistrations($program);
    }

    public function exportRegistrationsPdf(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportRegistrationsPdf($program, $this->sahodaya);
    }

    public function payments(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load([
            'registrations' => fn ($q) => $q->latest('id'),
            'registrations.teacher',
            'registrations.school',
            'registrations.feeReceipt',
            'registrations.pendingSchool',
        ]);

        $rows = $program->registrations->map(function (TrainingRegistration $r) use ($program) {
            $receipt = $r->feeReceipt;
            $outstanding = $r->outstandingBalance();

            return [
                'id' => $r->id,
                'teacher_name' => $r->teacher?->name,
                'teacher_email' => $r->teacher?->email,
                'school_name' => $r->school?->name ?? $r->pendingSchool?->school_name,
                'source' => $r->registration_source,
                'status' => $r->status,
                'fee_status' => $r->fee_status,
                'amount_due' => $r->feeTotalDue(),
                'amount_paid' => (float) ($r->amount_paid ?? 0),
                'outstanding' => $outstanding,
                'receipt' => $receipt ? [
                    'id' => $receipt->id,
                    'status' => $receipt->status,
                    'amount' => (float) $receipt->amount,
                    'transaction_ref' => $receipt->transaction_ref,
                    'payment_date' => $receipt->payment_date?->toDateString(),
                    'has_file' => filled($receipt->file_path),
                ] : null,
                'can_approve' => $receipt?->status === 'uploaded',
                'can_reject' => $receipt?->status === 'uploaded',
                'can_record' => $program->hasFee()
                    && $outstanding > 0
                    && (
                        (! $receipt || in_array($receipt->status, ['rejected', 'superseded'], true))
                        || $r->fee_status === 'auto_approved'
                    ),
            ];
        })->values();

        return $this->inertia('Sahodaya/Training/FeeApprovals', [
            'program' => $program->only(['id', 'title', 'status', 'fee_type', 'fee_amount']),
            'hasFee' => $program->hasFee(),
            'rows' => $rows,
            'counts' => [
                'awaiting_proof' => $rows->where('can_record', true)->count(),
                'pending_approval' => $rows->where('can_approve', true)->count(),
                'approved' => $rows->filter(fn ($r) => ($r['receipt']['status'] ?? null) === 'approved' || $r['status'] === 'confirmed')->count(),
            ],
        ]);
    }

    public function recordPayment(Request $request, string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);
        abort_unless($program->hasFee(), 422, 'This programme does not require a fee.');
        abort_unless(
            in_array($registration->status, ['registered', 'confirmed'], true),
            422,
            'Only registered or confirmed participants can be marked paid.'
        );

        $registration->loadMissing(['program', 'teacher', 'school']);
        $outstanding = $registration->outstandingBalance();
        abort_if($outstanding <= 0, 422, 'This training fee is already fully paid.');

        $data = $request->validate([
            'amount'          => 'nullable|numeric|min:1|max:'.$outstanding,
            'transaction_ref' => 'nullable|string|max:100',
            'payment_date'    => 'nullable|date',
            'note'            => 'nullable|string|max:255',
        ]);

        $amount = round((float) ($data['amount'] ?? $outstanding), 2);

        \App\Models\FeeReceipt::supersedePriorForFeeable($registration);

        $receipt = \App\Models\FeeReceipt::create([
            'feeable_type'        => TrainingRegistration::class,
            'feeable_id'          => $registration->id,
            'file_path'           => '',
            'transaction_ref'     => $data['transaction_ref'] ?? ($data['note'] ?? 'Recorded by Sahodaya'),
            'payment_date'        => $data['payment_date'] ?? now()->toDateString(),
            'amount'              => $amount,
            'status'              => 'approved',
            'uploaded_by_user_id' => $request->user()->id,
            'reviewed_by'         => $request->user()->id,
            'reviewed_at'         => now(),
        ]);

        $registration->update(['fee_receipt_id' => $receipt->id]);
        $registration->refresh();
        $registration->refreshPaidState('fee_status');
        $fullyPaid = $registration->fresh()->isFullyPaid();

        if ($fullyPaid && $registration->fresh()->status === 'registered') {
            $registration->update(['status' => 'confirmed']);
        }

        $issued = app(ProgramFeeReceiptService::class)->issueTraining(
            $registration->fresh(['program', 'teacher', 'school']),
            $receipt->fresh(),
        );

        app(OfflineProgramFeeOrchestrator::class)->notifyApproved(
            $registration->school,
            $issued,
            'Training fee',
            $registration->program?->title ?? 'Training Program',
            adminPath: 'payments',
        );

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.fee.recorded',
            "Training fee recorded for {$registration->teacher?->name}",
            [
                'registration_id' => $registration->id,
                'amount' => $amount,
                'fully_paid' => $fullyPaid,
            ],
            $registration,
        );

        return back()->with('success', $fullyPaid
            ? 'Venue payment recorded.'
            : 'Partial venue payment of ₹'.number_format($amount, 2).' recorded.');
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
            ->get()
            ->filter(fn (TrainingRegistration $r) => app(\App\Services\Training\TrainingRegistrationLifecycle::class)->canMarkAttendance($r, $program));

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
        abort_unless(
            app(\App\Services\Training\TrainingRegistrationLifecycle::class)->canMarkAttendance($registration, $program),
            422,
            'This registration cannot be marked for attendance yet.'
        );

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

    public function attendanceSheet(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load(['sessions', 'registrations']);
        $lifecycle = app(\App\Services\Training\TrainingRegistrationLifecycle::class);
        $attendeeCount = $program->registrations
            ->filter(fn (TrainingRegistration $r) => $lifecycle->canMarkAttendance($r, $program))
            ->count();

        return $this->inertia('Sahodaya/Training/AttendanceSheet', [
            'program' => $program->only(['id', 'title', 'status', 'venue', 'start_date', 'end_date']),
            'attendeeCount' => $attendeeCount,
            'sessionCount' => max(1, $program->sessions->count()),
        ]);
    }

    public function attendanceReport(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->load(['sessions' => fn ($q) => $q->orderBy('scheduled_at')]);

        return $this->inertia('Sahodaya/Training/AttendanceReport', [
            'program' => $program->only(['id', 'title', 'status', 'venue', 'start_date', 'end_date']),
            'sessions' => $program->sessions->map(fn ($s) => [
                'id' => $s->id,
                'title' => $s->title,
                'scheduled_at' => $s->scheduled_at,
            ]),
            'rows' => $reports->attendanceRows($program),
        ]);
    }

    public function exportAttendance(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendance($program);
    }

    public function exportAttendanceSheetPdf(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendanceSheetPdf($program, $this->sahodaya);
    }

    public function exportAttendanceReportPdf(string $tenantId, TrainingProgram $program, TrainingReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportAttendanceReportPdf($program, $this->sahodaya);
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

    public function downloadQr(string $tenantId, TrainingProgram $program, string $kind, string $format, \App\Services\Training\TrainingQrService $qr)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_unless(in_array($kind, ['registration', 'attendance'], true), 404);
        abort_unless(in_array($format, ['png', 'svg', 'pdf'], true), 404);

        $url = $kind === 'registration' ? $qr->registrationUrl($program) : $qr->attendanceUrl($program);
        $slug = str($program->title)->slug().'-'.$kind.'-qr';
        $isRegistration = $kind === 'registration';
        $branding = $qr->posterBranding(
            $this->sahodaya,
            $program,
            $url,
            $isRegistration ? 'Registration QR' : 'Attendance QR',
            $isRegistration ? 'Scan to register for this training' : 'Scan to mark attendance',
        );

        return $this->downloadBrandedQr($qr, $url, $branding, $format, $slug);
    }

    public function downloadSessionAttendanceQr(string $tenantId, TrainingProgram $program, TrainingSession $session, string $format, \App\Services\Training\TrainingQrService $qr)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($session->program_id !== $program->id, 404);
        abort_unless(in_array($format, ['png', 'svg', 'pdf'], true), 404);

        $url = $qr->attendanceUrl($program, $session);
        $slug = str($program->title)->slug().'-'.str($session->title)->slug().'-attendance-qr';
        $branding = $qr->posterBranding(
            $this->sahodaya,
            $program,
            $url,
            'Attendance · '.$session->title,
            'Scan to mark attendance for this session',
            $session,
        );

        return $this->downloadBrandedQr($qr, $url, $branding, $format, $slug);
    }

    /**
     * @param  array{
     *     org_name: string,
     *     logo_src: ?string,
     *     program_title: string,
     *     label: string,
     *     instruction: string,
     *     venue: ?string,
     *     dates: ?string,
     *     url: string
     * }  $branding
     */
    private function downloadBrandedQr(
        \App\Services\Training\TrainingQrService $qr,
        string $url,
        array $branding,
        string $format,
        string $slug,
    ) {
        if ($format === 'png') {
            return response($qr->brandedPng($url, $branding), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => "attachment; filename=\"{$slug}.png\"",
            ]);
        }

        if ($format === 'svg') {
            return response($qr->brandedSvg($url, $branding), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => "attachment; filename=\"{$slug}.svg\"",
            ]);
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('training.qr-download', [
            'orgName' => $branding['org_name'],
            'logoSrc' => $branding['logo_src'],
            'programTitle' => $branding['program_title'],
            'label' => $branding['label'],
            'instruction' => $branding['instruction'],
            'venue' => $branding['venue'],
            'dates' => $branding['dates'],
            'url' => $branding['url'],
            'qrDataUri' => $qr->dataUri($url, 400),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($slug.'.pdf');
    }

    public function regenerateQr(string $tenantId, TrainingProgram $program, PlatformAuditLogger $audit)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $program->forceFill([
            'qr_registration_token' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(40)),
            'attendance_qr_token'   => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(40)),
        ])->save();

        $audit->training($program, 'training.qr.regenerated', "QR tokens regenerated: {$program->title}");

        return back()->with('success', 'QR codes regenerated. Old links no longer work.');
    }

    public function qrReports(string $tenantId, TrainingProgram $program, \App\Services\Training\TrainingQrReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $schools = Tenant::where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'school_prefix']);

        return $this->inertia('Sahodaya/Training/QrReports', [
            'program' => $program->only('id', 'title', 'status'),
            'report'  => $reports->summary($program),
            'schools' => $schools,
        ]);
    }

    public function linkPendingSchool(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingPendingSchool $pendingSchool,
        \App\Services\Training\TrainingPendingSchoolResolver $resolver,
    ) {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($pendingSchool->program_id !== $program->id, 404);

        $data = $request->validate([
            'school_id' => 'required|string',
        ]);

        $school = Tenant::where('id', $data['school_id'])
            ->where('parent_id', $this->sahodaya->id)
            ->where('type', 'school')
            ->firstOrFail();

        $resolver->link($pendingSchool, $school);

        return back()->with('success', "Linked \"{$pendingSchool->school_name}\" to {$school->name}.");
    }

    public function rejectPendingSchool(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingPendingSchool $pendingSchool,
        \App\Services\Training\TrainingPendingSchoolResolver $resolver,
    ) {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($pendingSchool->program_id !== $program->id, 404);

        $data = $request->validate([
            'reason' => 'nullable|string|max:1000',
        ]);

        $resolver->reject($pendingSchool, $data['reason'] ?? null);

        return back()->with('success', "Rejected pending school \"{$pendingSchool->school_name}\".");
    }

    public function exportQrRegistrations(string $tenantId, TrainingProgram $program, \App\Services\Training\TrainingQrReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $reports->exportQrRegistrations($program);
    }

    public function feedback(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $rows = TrainingFeedback::where('program_id', $program->id)
            ->with(['teacher', 'registration.school'])
            ->latest('id')
            ->get()
            ->map(fn (TrainingFeedback $f) => [
                'id' => $f->id,
                'teacher_name' => $f->teacher?->name,
                'teacher_email' => $f->teacher?->email,
                'school_name' => $f->registration?->school?->name,
                'rating' => $f->rating,
                'content_rating' => $f->content_rating,
                'trainer_rating' => $f->trainer_rating,
                'venue_rating' => $f->venue_rating,
                'comments' => $f->comments,
                'status' => $f->status,
                'submitted_at' => $f->created_at?->toIso8601String(),
                'reviewed_at' => $f->reviewed_at?->toIso8601String(),
            ]);

        $submitted = $rows->count();
        $avgRating = $submitted > 0
            ? round($rows->avg('rating'), 1)
            : null;

        return $this->inertia('Sahodaya/Training/Feedback', [
            'program' => $program->only('id', 'title', 'status'),
            'feedback' => $rows,
            'stats' => [
                'submitted' => $submitted,
                'reviewed' => $rows->where('status', 'reviewed')->count(),
                'avg_rating' => $avgRating,
            ],
        ]);
    }

    public function markFeedbackReviewed(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingFeedback $feedback,
        TrainingFeedbackService $service,
    ) {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($feedback->program_id !== $program->id, 404);

        $service->markReviewed($feedback, $request->user()?->id);

        return back()->with('success', 'Feedback marked as reviewed.');
    }
}
