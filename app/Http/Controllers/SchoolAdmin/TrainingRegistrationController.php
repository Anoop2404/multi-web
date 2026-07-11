<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Models\FeeReceipt;
use App\Models\Teacher;
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
        $teacher = Teacher::with('teachingType')->findOrFail($data['teacher_id']);
        abort_if($teacher->tenant_id !== $this->school->id, 403);

        $eligibility->assertTeacherEligible($program, $teacher);

        $this->registerTeacher($program, $teacher);

        return back()->with('success', 'Teacher registered for training.');
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
        $lifecycle = app(TrainingRegistrationLifecycle::class);

        return TrainingRegistration::firstOrCreate(
            ['program_id' => $program->id, 'teacher_id' => $teacher->id],
            [
                'school_id'           => $this->school->id,
                'status'              => $lifecycle->initialStatus($program),
                'registration_source' => 'school',
            ]
        );
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
            'status' => 'required|in:present,absent',
        ]);

        $attendance = TrainingAttendance::updateOrCreate(
            ['session_id' => $session->id, 'registration_id' => $registration->id],
            array_merge($data, ['marked_by' => $request->user()->id, 'marked_at' => now()])
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

        $outstanding = $registration->outstandingBalance();
        abort_if($outstanding <= 0, 422, 'This training fee is already fully paid.');

        $data = $request->validate([
            'payment_proof'   => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'transaction_ref' => 'nullable|string|max:100',
            'amount'          => 'nullable|numeric|min:1|max:'.$outstanding,
        ]);

        $path = TenantStorage::storeUploadedFile(
            $request->file('payment_proof'),
            "training-payments/{$this->school->id}"
        );

        $amount = round((float) ($data['amount'] ?? $outstanding), 2);

        FeeReceipt::supersedePriorForFeeable($registration);

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
}
