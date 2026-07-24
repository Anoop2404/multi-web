<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\TrainingAttendance;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Notifications\SahodayaAdminNotifier;
use App\Services\Spreadsheet\SpreadsheetWriter;
use App\Services\Training\TeacherTrainingEligibilityService;
use App\Services\Training\TrainingRegistrationCsvImporter;
use App\Services\Training\TrainingRegistrationLifecycle;
use App\Support\TenantStorage;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingRegistrationController extends SchoolAdminController
{
    public function index(TeacherTrainingEligibilityService $eligibility)
    {
        $sahodayaId = $this->school->parent_id;

        $programs = TrainingProgram::where('tenant_id', $sahodayaId)
            ->whereIn('status', ['published', 'ongoing', 'completed'])
            ->with(['sessions' => fn ($q) => $q->orderBy('scheduled_at')])
            ->withCount(['sessions'])
            ->orderByDesc('registration_open')
            ->get();

        $registrations = TrainingRegistration::where('school_id', $this->school->id)
            ->whereIn('program_id', $programs->pluck('id'))
            ->with(['teacher:id,name,email,designation,verified_at,tenant_id', 'feeReceipt'])
            ->get()
            ->groupBy('program_id');

        $schoolFees = \App\Models\TrainingSchoolFee::where('school_id', $this->school->id)
            ->whereIn('program_id', $programs->pluck('id'))
            ->with('feeReceipt')
            ->get()
            ->mapWithKeys(fn (\App\Models\TrainingSchoolFee $f) => [
                $f->program_id => [
                    'id' => $f->id,
                    'program_id' => $f->program_id,
                    'teacher_count' => (int) $f->teacher_count,
                    'total_due' => (float) $f->total_due,
                    'amount_paid' => (float) ($f->amount_paid ?? 0),
                    'outstanding' => $f->outstandingBalance(),
                    'status' => $f->status,
                    'fee_receipt' => $f->feeReceipt ? [
                        'id' => $f->feeReceipt->id,
                        'status' => $f->feeReceipt->status,
                        'amount' => (float) $f->feeReceipt->amount,
                        'transaction_ref' => $f->feeReceipt->transaction_ref,
                    ] : null,
                ],
            ]);

        $allTeachers = Teacher::where('tenant_id', $this->school->id)
            ->where('status', 'active')
            ->with(['teachingType'])
            ->orderBy('name')
            ->get();

        $subjectLabelMap = \App\Models\Subject::forSahodaya($this->school->parent_id)
            ->pluck('label', 'id');

        $eligibleByProgram = [];
        foreach ($programs as $program) {
            $eligibleByProgram[$program->id] = $eligibility
                ->eligibleTeachers($program, $allTeachers)
                ->map(fn (Teacher $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'category' => $t->teachingType?->label,
                    'is_verified' => $t->isVerified(),
                    'subjects' => collect($t->subject_ids ?? [])->map(fn ($id) => $subjectLabelMap->get($id))->filter()->values()->all(),
                ])
                ->values()
                ->all();
        }

        return $this->inertia('School/Training/Index', [
            'programs'          => $programs,
            'registrations'     => $registrations,
            'schoolFees'        => $schoolFees,
            'eligibleByProgram' => $eligibleByProgram,
        ]);
    }

    public function store(Request $request, TeacherTrainingEligibilityService $eligibility)
    {
        $data = $request->validate([
            'program_id'  => 'required|exists:training_programs,id',
            'teacher_id'  => 'required|exists:teachers,id',
        ]);

        app(\App\Services\Membership\SchoolMembershipGate::class)->assertPaid($this->school);

        $program = $this->assertProgramOpen($data['program_id']);
        abort_unless($program->allow_school_nomination ?? true, 422, 'School nomination is disabled for this programme.');
        $teacher = Teacher::with('teachingType')->findOrFail($data['teacher_id']);
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $eligibility->assertTeacherEligible($program, $teacher);

        $this->registerTeacher($program, $teacher);

        return back()->with('success', 'Teacher registered for training.');
    }

    /**
     * School cancels one of their training registrations.
     * Blocks if the fee has already been approved (must go through Sahodaya admin).
     * Frees the seat and promotes the next waitlisted teacher.
     */
    public function cancel(Request $request, string $tenantId, TrainingRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);

        $registration->loadMissing('program');
        $program = $registration->program;
        abort_unless($program, 404, 'Training programme not found.');
        abort_if($program->tenant_id !== $this->school->parent_id, 403);

        if ($registration->status === 'cancelled') {
            return back()->with('success', 'Registration is already cancelled.');
        }

        // Block if the school batch fee is approved — at that point the money has been
        // accepted and only a Sahodaya admin cancel (with credit/refund) makes sense.
        abort_if(
            $program->usesSchoolBatchFee() && $registration->school_id,
            fn () => \App\Models\TrainingSchoolFee::where('program_id', $program->id)
                ->where('school_id', $registration->school_id)
                ->where('status', 'approved')
                ->exists(),
            422,
            'This registration\'s fee has been approved. Contact your Sahodaya office to cancel a paid registration.',
        );

        // School cancel never reaches an admin-typed reason — the batch-fee-approved case
        // is already blocked above, so the only money this can free is a partial payment.
        // Auto-generate a reason so that (rare) case still gets credited instead of
        // silently requiring the guard above to be the only thing standing between a
        // school and stranded money. Mirrors McqRegistrationController::cancel()'s
        // auto-generated $cancelReason for the same situation.
        $cancelReason = "Training registration cancelled by school for {$registration->teacher?->name} in {$program->title}";

        app(\App\Services\Training\TrainingWaitlistService::class)->cancelAndPromote(
            $registration,
            $cancelReason,
            $request->user()?->id,
        );

        app(PlatformAuditLogger::class)->log(
            'training.registration.cancelled_by_school',
            "School cancelled training registration for teacher #{$registration->teacher_id} in program {$program->title}",
            $registration,
        );

        try {
            app(SahodayaAdminNotifier::class)->notifyAdmins(
                $program->tenant_id,
                'training.registration.cancelled_by_school',
                [
                    'program_title' => $program->title,
                    'school_name'   => $this->school->name,
                ],
            );
        } catch (\Throwable) {
            // non-blocking
        }

        return back()->with('success', 'Registration cancelled.');
    }

    public function bulkStore(Request $request, TeacherTrainingEligibilityService $eligibility)
    {
        $data = $request->validate([
            'program_id'    => 'required|exists:training_programs,id',
            'teacher_ids'   => 'required|array|min:1|max:500',
            'teacher_ids.*' => 'integer|exists:teachers,id',
        ]);

        app(\App\Services\Membership\SchoolMembershipGate::class)->assertPaid($this->school);

        $program = $this->assertProgramOpen($data['program_id']);
        abort_unless($program->allow_school_nomination ?? true, 422, 'School nomination is disabled for this programme.');
        $teacherIds = array_values(array_unique($data['teacher_ids']));

        $teachers = Teacher::with('teachingType')
            ->where('tenant_id', $this->school->id)
            ->whereIn('id', $teacherIds)
            ->get()
            ->keyBy('id');

        $registered = 0;
        $skipped = 0;
        $errors = [];

        foreach ($teacherIds as $teacherId) {
            $teacher = $teachers->get($teacherId);
            if (! $teacher) {
                $errors[] = "Teacher #{$teacherId} not found in this school.";
                continue;
            }

            if (TrainingRegistration::where('program_id', $program->id)->where('teacher_id', $teacher->id)->exists()) {
                $skipped++;
                continue;
            }

            if (! $eligibility->isEligible($program, $teacher)) {
                $reason = $eligibility->ineligibilityReason($program, $teacher) ?? 'not eligible';
                $errors[] = "{$teacher->name}: {$reason}";
                continue;
            }

            $this->registerTeacher($program, $teacher);
            $registered++;
        }

        if ($registered === 0 && $errors !== []) {
            return back()->with('error', 'No teachers registered. '.implode(' ', array_slice($errors, 0, 3)));
        }

        $parts = ["Registered {$registered} teacher(s)."];
        if ($skipped > 0) {
            $parts[] = "{$skipped} already nominated.";
        }
        if ($errors !== []) {
            $parts[] = count($errors).' skipped with errors.';
        }

        return back()->with('success', implode(' ', $parts));
    }

    public function importTemplate(Request $request)
    {
        $importer = new TrainingRegistrationCsvImporter(
            $this->school,
            app(TeacherTrainingEligibilityService::class),
            app(TrainingRegistrationLifecycle::class),
        );

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(
                fn () => print("\xEF\xBB\xBF".$importer->templateCsv()),
                'training-nomination-template.csv',
                ['Content-Type' => 'text/csv; charset=UTF-8'],
            );
        }

        return response()->streamDownload(
            fn () => print $importer->templateXlsx(),
            'training-nomination-template.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    public function importStore(Request $request, TeacherTrainingEligibilityService $eligibility)
    {
        $data = $request->validate([
            'program_id' => 'required|exists:training_programs,id',
            'file'       => 'required|file|mimes:csv,txt,xlsx|max:5120',
        ]);

        app(\App\Services\Membership\SchoolMembershipGate::class)->assertPaid($this->school);

        $program = $this->assertProgramOpen($data['program_id']);
        abort_unless($program->allow_school_nomination ?? true, 422, 'School nomination is disabled for this programme.');

        $importer = new TrainingRegistrationCsvImporter($this->school, $eligibility, app(TrainingRegistrationLifecycle::class));
        $result = $importer->import($request->file('file'), $program);

        if (! $result['success'] && $result['imported'] === 0) {
            return back()
                ->with('importResult', $result)
                ->with('error', 'Import rejected: fix the error(s) below and re-upload.');
        }

        if ($result['imported'] === 0 && $result['errors'] === []) {
            return back()
                ->with('importResult', $result)
                ->with('info', 'No new nominations — all matched teachers were already registered.');
        }

        $message = "Imported {$result['imported']} nomination(s).";
        if ($result['errors'] !== []) {
            $message .= ' '.count($result['errors']).' row(s) had errors.';
        }

        return back()
            ->with('importResult', $result)
            ->with($result['errors'] === [] ? 'success' : 'warning', $message);
    }

    public function export(Request $request, string $tenantId, TrainingProgram $program): StreamedResponse
    {
        abort_if($program->tenant_id !== $this->school->parent_id, 403);

        $importer = new TrainingRegistrationCsvImporter(
            $this->school,
            app(TeacherTrainingEligibilityService::class),
            app(TrainingRegistrationLifecycle::class),
        );
        $rows = $importer->exportRows($program);

        $format = $request->query('format') === 'csv' ? 'csv' : 'xlsx';
        $slug = str($program->title)->slug()->limit(40, '')->toString() ?: 'training';
        $filename = "{$slug}-nominations-".now()->format('Y-m-d').".{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $xlsx = SpreadsheetWriter::xlsx($rows);

        return response()->streamDownload(
            fn () => print $xlsx,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        );
    }

    private function assertProgramOpen(int|string $programId): TrainingProgram
    {
        $program = TrainingProgram::findOrFail($programId);
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_if(! in_array($program->status, ['published', 'ongoing'], true), 422, 'Registration is closed.');

        return $program;
    }

    private function registerTeacher(TrainingProgram $program, Teacher $teacher): TrainingRegistration
    {
        $waitlist = app(\App\Services\Training\TrainingWaitlistService::class);
        $seat = $waitlist->resolveCreateAttributes($program, 'school');

        $registration = TrainingRegistration::firstOrCreate(
            ['program_id' => $program->id, 'teacher_id' => $teacher->id],
            array_merge([
                'school_id'           => $this->school->id,
                'registration_source' => 'school',
                'fee_status'          => $program->usesSchoolBatchFee() && $seat['status'] !== 'waitlisted'
                    ? 'auto_approved'
                    : null,
            ], $seat)
        );

        if ($program->usesSchoolBatchFee() && $registration->status !== 'waitlisted') {
            app(\App\Services\Training\TrainingSchoolFeeService::class)->syncForSchool($program, $this->school);
        }

        return $registration;
    }

    public function attendance(string $tenantId, TrainingProgram $program, TrainingRegistrationLifecycle $lifecycle)
    {
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->allow_school_attendance ?? true, 403, 'School attendance marking is disabled for this programme.');

        $program->load(['sessions' => fn ($q) => $q->orderBy('scheduled_at')]);

        $registrations = TrainingRegistration::where('program_id', $program->id)
            ->where('school_id', $this->school->id)
            ->with('teacher')
            ->orderBy('id')
            ->get()
            ->filter(fn (TrainingRegistration $r) => $lifecycle->canMarkAttendance($r, $program))
            ->values();

        $attendanceMap = TrainingAttendance::whereIn('registration_id', $registrations->pluck('id'))
            ->get()
            ->groupBy('session_id')
            ->map(fn ($rows) => $rows->keyBy('registration_id'));

        return $this->inertia('School/Training/Attendance', [
            'program' => $program->only(
                'id', 'title', 'status', 'venue', 'start_date', 'end_date',
                'allow_school_attendance', 'require_verified_teachers'
            ) + [
                'sessions' => $program->sessions,
            ],
            'registrations'  => $registrations->map(fn (TrainingRegistration $r) => [
                'id'     => $r->id,
                'status' => $r->status,
                'teacher'=> $r->teacher ? array_merge(
                    $r->teacher->only('id', 'name', 'email', 'designation', 'verified_at'),
                    ['is_verified' => $r->teacher->isVerified()],
                ) : null,
            ]),
            'attendanceMap' => $attendanceMap,
        ]);
    }

    public function updateAttendance(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingSession $session,
        TrainingRegistration $registration,
        TrainingRegistrationLifecycle $lifecycle,
        PlatformAuditLogger $audit,
    ) {
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->allow_school_attendance ?? true, 403, 'School attendance marking is disabled for this programme.');
        abort_if($session->program_id !== $program->id, 404);
        abort_if($registration->program_id !== $program->id, 404);
        abort_if($registration->school_id !== $this->school->id, 403);
        abort_unless($lifecycle->canMarkAttendance($registration, $program), 422, 'This registration cannot be marked for attendance yet.');

        $data = $request->validate([
            'status' => 'required|in:present,absent,late,with_permission',
            'correction_reason' => 'nullable|string|max:500',
        ]);

        $attendance = app(\App\Services\Training\TrainingAttendanceService::class)->updateAttendance(
            $session,
            $registration,
            [
                'status' => $data['status'],
                'correction_reason' => $data['correction_reason'] ?? null,
                'require_approval' => true,
            ],
            $request->user()->id,
        );

        $audit->training(
            $program,
            'training.school.attendance',
            "School attendance: {$registration->teacher?->name} · {$session->title} · {$data['status']}",
            [
                'session_id'      => $session->id,
                'registration_id' => $registration->id,
                'school_id'       => $this->school->id,
                'status'          => $data['status'],
                'approval_status' => $attendance->approval_status,
            ],
            $attendance,
        );

        return back()->with('success', 'Attendance updated.');
    }

    public function markAllPresent(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingSession $session,
        TrainingRegistrationLifecycle $lifecycle,
        PlatformAuditLogger $audit,
    ) {
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->allow_school_attendance ?? true, 403);
        abort_if($session->program_id !== $program->id, 404);

        $registrations = TrainingRegistration::where('program_id', $program->id)
            ->where('school_id', $this->school->id)
            ->with('teacher')
            ->get()
            ->filter(fn (TrainingRegistration $r) => $lifecycle->canMarkAttendance($r, $program));

        foreach ($registrations as $registration) {
            TrainingAttendance::updateOrCreate(
                ['session_id' => $session->id, 'registration_id' => $registration->id],
                ['status' => 'present', 'marked_by' => $request->user()->id, 'marked_at' => now()]
            );
        }

        $audit->training(
            $program,
            'training.school.attendance_bulk',
            "School marked all present for {$session->title} ({$registrations->count()} teachers)",
            ['session_id' => $session->id, 'school_id' => $this->school->id, 'count' => $registrations->count()],
        );

        return back()->with('success', 'Marked '.$registrations->count().' teacher(s) present.');
    }

    public function uploadPayment(Request $request, string $tenantId, TrainingRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);

        $program = $registration->program;
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->hasFee(), 422, 'This program does not require a fee.');
        abort_if($program->usesSchoolBatchFee(), 422, 'This programme uses a school batch fee — upload payment from the program school fee section.');

        $outstanding = $registration->outstandingBalance();
        abort_if($outstanding <= 0, 422, 'This training fee is already fully paid.');

        // payment_proof accepts up to 5 images for ONE payment — see
        // docs/FLOW_GAP_FIX_PLAN.md multi-image upload feature. First file is the
        // receipt's primary file_path (unchanged behavior); rest become attachments.
        $data = $request->validate([
            'payment_proof'    => 'required|array|min:1|max:'.\App\Services\Fees\FeeReceiptAttachmentService::MAX_FILES,
            'payment_proof.*'  => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref'  => 'nullable|string|max:100',
            'amount'           => 'nullable|numeric|min:1|max:'.$outstanding,
        ]);

        $proofFiles = $request->file('payment_proof');
        $path = TenantStorage::storeUploadedFile(
            $proofFiles[0],
            "training-payments/{$this->school->id}"
        );

        $amount = round((float) ($data['amount'] ?? $outstanding), 2);

        FeeReceipt::supersedePriorForFeeable($registration);

        app(\App\Services\Training\TrainingInvoiceService::class)->ensureForRegistration($registration);

        $receipt = FeeReceipt::create([
            'feeable_type'        => TrainingRegistration::class,
            'feeable_id'          => $registration->id,
            'file_path'           => $path,
            'transaction_ref'     => $data['transaction_ref'] ?? null,
            'payment_date'        => now()->toDateString(),
            'amount'              => $amount,
            'status'              => 'uploaded',
            'uploaded_by_user_id' => $request->user()->id,
        ]);

        if (count($proofFiles) > 1) {
            app(\App\Services\Fees\FeeReceiptAttachmentService::class)
                ->attachExtra($receipt, array_slice($proofFiles, 1), "training-payments/{$this->school->id}");
        }

        $registration->update(['fee_receipt_id' => $receipt->id, 'fee_status' => 'proof_uploaded']);

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            [
                'school_name'   => $this->school->name,
                'context_label' => $program->title.' training fee',
            ],
            "/sahodaya-admin/{$this->school->parent_id}/training/{$program->id}/payments"
        );

        return back()->with('success', 'Payment proof uploaded.');
    }

    public function downloadInvoice(string $tenantId, TrainingRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);
        $program = $registration->program;
        abort_if(! $program || $program->tenant_id !== $this->school->parent_id, 403);

        $invoices = app(\App\Services\Training\TrainingInvoiceService::class);
        $invoice = $invoices->ensureForRegistration($registration);
        abort_unless($invoice, 404, 'No invoice for this registration.');

        return $invoices->download($invoice, Tenant::find($this->school->parent_id));
    }

    public function downloadSchoolInvoice(string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->usesSchoolBatchFee(), 422, 'This programme does not use a school batch fee.');

        $feeService = app(\App\Services\Training\TrainingSchoolFeeService::class);
        $schoolFee = $feeService->syncForSchool($program, $this->school);
        $invoices = app(\App\Services\Training\TrainingInvoiceService::class);
        $invoice = $schoolFee->fresh(['invoice'])->invoice
            ?? $invoices->ensureForSchoolFee($schoolFee);
        abort_unless($invoice, 404, 'No invoice for this school fee.');

        return $invoices->download($invoice, Tenant::find($this->school->parent_id));
    }

    public function downloadIdCard(string $tenantId, TrainingRegistration $registration)
    {
        abort_if($registration->school_id !== $this->school->id, 403);
        $program = $registration->program;
        abort_if(! $program || $program->tenant_id !== $this->school->parent_id, 403);
        abort_if(in_array($registration->status, ['cancelled', 'rejected'], true), 422, 'ID card not available for this registration.');

        return app(\App\Services\Training\TrainingIdCardService::class)
            ->download($registration, Tenant::find($this->school->parent_id));
    }

    public function uploadSchoolPayment(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->school->parent_id, 403);
        abort_unless($program->usesSchoolBatchFee(), 422, 'This programme does not use a school batch fee.');

        $feeService = app(\App\Services\Training\TrainingSchoolFeeService::class);
        $schoolFee = $feeService->syncForSchool($program, $this->school);
        abort_if($schoolFee->total_due <= 0, 422, 'No fee due for this programme.');

        $outstanding = $schoolFee->outstandingBalance();
        abort_if($outstanding <= 0, 422, 'This school batch fee is already fully paid.');

        $data = $request->validate([
            'payment_proof'    => 'required|array|min:1|max:'.\App\Services\Fees\FeeReceiptAttachmentService::MAX_FILES,
            'payment_proof.*'  => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref'  => 'nullable|string|max:100',
            'amount'           => 'nullable|numeric|min:1|max:'.$outstanding,
        ]);

        $proofFiles = $request->file('payment_proof');
        $path = TenantStorage::storeUploadedFile(
            $proofFiles[0],
            "training-payments/{$this->school->id}"
        );

        $amount = round((float) ($data['amount'] ?? $outstanding), 2);

        $receipt = $feeService->attachPaymentProof(
            $schoolFee,
            $path,
            $data['transaction_ref'] ?? null,
            $amount,
            $request->user()->id,
        );

        if (count($proofFiles) > 1) {
            app(\App\Services\Fees\FeeReceiptAttachmentService::class)
                ->attachExtra($receipt, array_slice($proofFiles, 1), "training-payments/{$this->school->id}");
        }

        app(SahodayaAdminNotifier::class)->notifyAdmins(
            $this->school->parent_id,
            'payment.proof.uploaded',
            [
                'school_name'   => $this->school->name,
                'context_label' => $program->title.' training batch fee',
            ],
            "/sahodaya-admin/{$this->school->parent_id}/training/{$program->id}/payments"
        );

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.school_fee.proof_uploaded',
            "School {$this->school->name} uploaded training batch fee proof for {$program->title}",
            ['school_id' => $this->school->id, 'school_fee_id' => $schoolFee->id, 'amount' => $amount],
            $schoolFee,
        );

        return back()->with('success', 'School batch fee proof uploaded.');
    }
}
