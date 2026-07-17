<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Support\AcademicYear;
use App\Support\Training\TrainingProgramEligibilityConfig;
use App\Support\Training\TrainingProgramPayload;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Region;
use App\Models\Tenant;
use App\Models\TrainingCategory;
use App\Models\TrainingFeedback;
use App\Models\TrainingPendingSchool;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingResourcePerson;
use App\Models\TrainingSession;
use App\Models\TrainingAttendance;
use Illuminate\Validation\Rule;
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
    public function index(Request $request)
    {
        TrainingCategory::ensureDefaults($this->sahodaya->id);

        $categoryId = $request->integer('category_id') ?: null;

        $query = TrainingProgram::where('tenant_id', $this->sahodaya->id)
            ->with(['category:id,label,code'])
            ->withCount(['registrations', 'sessions'])
            ->orderByDesc('registration_open');

        if ($categoryId) {
            $query->where('category_id', $categoryId);
        }

        $programs = $query->get();

        $categories = TrainingCategory::forTenant($this->sahodaya->id)
            ->orderBy('display_order')
            ->orderBy('label')
            ->get(['id', 'code', 'label', 'is_active', 'display_order']);

        return $this->inertia('Sahodaya/Training/Index', [
            'programs' => $programs,
            'categories' => $categories,
            'filters' => [
                'category_id' => $categoryId,
            ],
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
            'code'                => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('training_programs', 'code')->where('tenant_id', $this->sahodaya->id),
            ],
            'description'         => 'nullable|string',
            'banner_image'        => 'nullable|image|max:5120',
            'venue'               => 'nullable|string|max:255',
            'start_date'          => 'nullable|date',
            'end_date'            => 'nullable|date|after_or_equal:start_date',
            'registration_open'   => 'nullable|date',
            'registration_close'  => 'nullable|date',
            'max_participants'    => 'nullable|integer|min:1',
            'allow_teacher_self_registration' => 'nullable|boolean',
            'allow_school_nomination' => 'nullable|boolean',
            'fee_type'            => 'nullable|in:none,flat,school',
            'fee_amount'          => 'nullable|numeric|min:0',
            'min_attendance_percent' => 'nullable|integer|min:0|max:100',
            'category_id'         => [
                'nullable',
                'integer',
                Rule::exists('training_categories', 'id')->where('tenant_id', $this->sahodaya->id),
            ],
            'certificate_type'    => ['nullable', 'string', Rule::in(TrainingProgram::CERTIFICATE_TYPES)],
            'certificate_template_id' => [
                'nullable',
                'integer',
                Rule::exists('certificate_templates', 'id')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->where('event_type', 'training'),
            ],
        ]);

        $data['tenant_id'] = $this->sahodaya->id;
        $data['conductor_level'] = 'sahodaya';
        $data['status'] = 'draft';
        $data['academic_year_id'] = AcademicYear::activeId();
        $data['category_id'] = $data['category_id'] ?: null;
        $data['code'] = filled($data['code'] ?? null) ? trim($data['code']) : null;
        $data['allow_school_nomination'] = (bool) ($data['allow_school_nomination'] ?? true);
        unset($data['banner_image']);
        $data = TrainingProgramPayload::applyDefaults($data);

        if ($request->hasFile('banner_image')) {
            $data['banner_image_path'] = TenantStorage::storeUploadedFile(
                $request->file('banner_image'),
                "training-banners/{$this->sahodaya->id}"
            );
        }

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

        TrainingCategory::ensureDefaults($this->sahodaya->id);

        $qr->ensureProgramTokens($program);
        $program->load([
            'category',
            'sessions.resourcePerson',
            'resourcePersons',
            'registrations.teacher',
            'registrations.school',
            'registrations.feeReceipt',
            'registrations.certificate',
            'registrations.pendingSchool',
        ]);

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

        $resourcePersons = TrainingResourcePerson::forTenant($this->sahodaya->id)
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'designation', 'email', 'mobile']);

        $categories = TrainingCategory::forTenant($this->sahodaya->id)
            ->active()
            ->orderBy('display_order')
            ->orderBy('label')
            ->get(['id', 'code', 'label']);

        $certificateTemplates = CertificateTemplate::where('tenant_id', $this->sahodaya->id)
            ->where('event_type', 'training')
            ->where('is_active', true)
            ->orderBy('certificate_type')
            ->orderByDesc('id')
            ->get(['id', 'title', 'certificate_type', 'background_path'])
            ->map(fn (CertificateTemplate $t) => [
                'id' => $t->id,
                'title' => $t->title ?: 'Certificate',
                'certificate_type' => $t->certificate_type,
                'has_background' => filled($t->background_path),
            ]);

        return $this->inertia('Sahodaya/Training/Show', [
            'program' => array_merge($program->toArray(), [
                'eligibility_config' => TrainingProgramEligibilityConfig::normalize($program->eligibility_config),
                'banner_image_url' => $program->banner_image_path
                    ? TenantStorage::assetUrl($this->sahodaya, $program->banner_image_path)
                    : null,
            ]),
            'categories' => $categories,
            'certificateTypes' => TrainingProgram::CERTIFICATE_TYPES,
            'certificateTemplates' => $certificateTemplates,
            'resourcePersons' => $resourcePersons,
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
            'code'               => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('training_programs', 'code')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->ignore($program->id),
            ],
            'description'        => 'nullable|string',
            'banner_image'       => 'nullable|image|max:5120',
            'remove_banner_image'=> 'nullable|boolean',
            'venue'              => 'nullable|string|max:255',
            'start_date'         => 'nullable|date',
            'end_date'           => 'nullable|date|after_or_equal:start_date',
            'registration_open'  => 'nullable|date',
            'registration_close' => 'nullable|date',
            'max_participants'   => 'nullable|integer|min:1',
            'allow_teacher_self_registration' => 'nullable|boolean',
            'allow_school_nomination' => 'nullable|boolean',
            'qr_registration_enabled' => 'nullable|boolean',
            'require_verified_teachers' => 'nullable|boolean',
            'allow_school_attendance' => 'nullable|boolean',
            'status'             => 'required|in:draft,published,ongoing,completed,cancelled',
            'fee_type'           => 'nullable|in:none,flat,school',
            'fee_amount'         => 'nullable|numeric|min:0',
            'min_attendance_percent' => 'nullable|integer|min:0|max:100',
            'category_id'        => [
                'nullable',
                'integer',
                Rule::exists('training_categories', 'id')->where('tenant_id', $this->sahodaya->id),
            ],
            'certificate_type'   => ['nullable', 'string', Rule::in(TrainingProgram::CERTIFICATE_TYPES)],
            'certificate_template_id' => [
                'nullable',
                'integer',
                Rule::exists('certificate_templates', 'id')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->where('event_type', 'training'),
            ],
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
        $data['category_id'] = $data['category_id'] ?: null;
        $data['code'] = filled($data['code'] ?? null) ? trim($data['code']) : null;
        $data['allow_teacher_self_registration'] = (bool) ($data['allow_teacher_self_registration'] ?? false);
        $data['allow_school_nomination'] = (bool) ($data['allow_school_nomination'] ?? true);
        $data['qr_registration_enabled'] = (bool) ($data['qr_registration_enabled'] ?? false);
        $data['require_verified_teachers'] = (bool) ($data['require_verified_teachers'] ?? false);
        $data['allow_school_attendance'] = (bool) ($data['allow_school_attendance'] ?? true);

        unset($data['banner_image'], $data['remove_banner_image']);

        if ($request->boolean('remove_banner_image') && $program->banner_image_path) {
            \Illuminate\Support\Facades\Storage::disk(TenantStorage::uploadDisk())->delete($program->banner_image_path);
            $data['banner_image_path'] = null;
        }

        if ($request->hasFile('banner_image')) {
            if ($program->banner_image_path) {
                \Illuminate\Support\Facades\Storage::disk(TenantStorage::uploadDisk())->delete($program->banner_image_path);
            }
            $data['banner_image_path'] = TenantStorage::storeUploadedFile(
                $request->file('banner_image'),
                "training-banners/{$this->sahodaya->id}"
            );
        }

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

    public function registrations(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $filters = $request->validate([
            'search'       => 'nullable|string|max:100',
            'status'       => 'nullable|string|max:40',
            'source'       => 'nullable|in:all,qr,portal,school',
            'verification' => 'nullable|in:all,verified,unverified',
            'school'       => 'nullable|in:all,assigned,pending,none',
            'sort'         => 'nullable|in:id,teacher,status,source',
            'dir'          => 'nullable|in:asc,desc',
            'per_page'     => 'nullable|integer|min:10|max:100',
        ]);

        $base = TrainingRegistration::query()->where('training_registrations.program_id', $program->id);

        $counts = [
            'total'      => (clone $base)->count(),
            'registered' => (clone $base)->where('training_registrations.status', 'registered')->count(),
            'confirmed'  => (clone $base)->whereIn('training_registrations.status', ['confirmed', 'completed'])->count(),
            'waitlisted' => (clone $base)->where('training_registrations.status', 'waitlisted')->count(),
            'qr'         => (clone $base)->where('training_registrations.registration_source', 'qr')->count(),
            'no_school'  => (clone $base)
                ->whereNull('training_registrations.school_id')
                ->whereNull('training_registrations.pending_school_id')
                ->count(),
        ];

        $sort = $filters['sort'] ?? 'id';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $perPage = (int) ($filters['per_page'] ?? 50);
        $status = $filters['status'] ?? 'all';
        $source = $filters['source'] ?? 'all';
        $verification = $filters['verification'] ?? 'all';
        $schoolFilter = $filters['school'] ?? 'all';
        $search = trim((string) ($filters['search'] ?? ''));

        $query = (clone $base)
            ->with([
                'teacher.teachingType',
                // Must include "type" — TrainingRegistration::displaySchoolName()
                // checks `$this->school->type === 'school'` before trusting the
                // name; with type not selected it's always null, so a perfectly
                // valid, correctly-linked school silently rendered as "—" here.
                'school:id,name,type',
                'feeReceipt',
                'certificate',
                'pendingSchool',
            ])
            ->when($status === 'confirmed', fn ($q) => $q->whereIn('training_registrations.status', ['confirmed', 'completed']))
            ->when(
                $status !== 'all' && $status !== '' && $status !== 'confirmed',
                fn ($q) => $q->where('training_registrations.status', $status)
            )
            ->when($source !== 'all', fn ($q) => $q->where('training_registrations.registration_source', $source))
            ->when($verification === 'verified', fn ($q) => $q->whereHas(
                'teacher',
                fn ($t) => $t->whereNotNull('verified_at')
            ))
            ->when($verification === 'unverified', fn ($q) => $q->whereHas(
                'teacher',
                fn ($t) => $t->whereNull('verified_at')
            ))
            ->when($schoolFilter === 'none', fn ($q) => $q
                ->whereNull('training_registrations.school_id')
                ->whereNull('training_registrations.pending_school_id'))
            ->when($schoolFilter === 'pending', fn ($q) => $q->whereNotNull('training_registrations.pending_school_id'))
            ->when($schoolFilter === 'assigned', fn ($q) => $q->whereNotNull('training_registrations.school_id'))
            ->when($search !== '', function ($q) use ($search) {
                $term = '%'.$search.'%';
                $matchedSchoolIds = Tenant::where('parent_id', $this->sahodaya->id)
                    ->where('type', 'school')
                    ->where('name', 'like', $term)
                    ->pluck('id');

                $q->where(function ($inner) use ($term, $matchedSchoolIds) {
                    $inner->whereHas('teacher', function ($t) use ($term) {
                        $t->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('reg_no', 'like', $term);
                    })
                        ->orWhereHas('pendingSchool', fn ($p) => $p->where('school_name', 'like', $term));

                    if ($matchedSchoolIds->isNotEmpty()) {
                        $inner->orWhereIn('training_registrations.school_id', $matchedSchoolIds);
                    }
                });
            });

        // School names live on the central tenants connection — avoid joining them here.
        if ($sort === 'teacher') {
            $query->leftJoin('teachers', 'teachers.id', '=', 'training_registrations.teacher_id')
                ->orderBy('teachers.name', $dir)
                ->orderBy('training_registrations.id', 'desc')
                ->select('training_registrations.*');
        } elseif ($sort === 'status') {
            $query->orderBy('training_registrations.status', $dir)->orderByDesc('training_registrations.id');
        } elseif ($sort === 'source') {
            $query->orderBy('training_registrations.registration_source', $dir)->orderByDesc('training_registrations.id');
        } else {
            $query->orderBy('training_registrations.id', $dir);
        }

        $registrations = $query
            ->paginate($perPage)
            ->withQueryString();

        return $this->inertia('Sahodaya/Training/Registrations', [
            'program' => $program->only([
                'id', 'title', 'status', 'fee_type', 'fee_amount',
            ]),
            'registrations' => $registrations,
            'counts' => $counts,
            'filters' => [
                'search'       => $search,
                'status'       => $status === '' ? 'all' : $status,
                'source'       => $source,
                'verification' => $verification,
                'school'       => $schoolFilter,
                'sort'         => $sort,
                'dir'          => $dir,
                'per_page'     => $perPage,
            ],
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
                'school_name' => $r->displaySchoolName() === '—' ? null : $r->displaySchoolName(),
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
                'can_approve' => ! $program->usesSchoolBatchFee() && $receipt?->status === 'uploaded',
                'can_reject' => ! $program->usesSchoolBatchFee() && $receipt?->status === 'uploaded',
                'can_record' => $program->usesPerTeacherFee()
                    && $outstanding > 0
                    && (
                        (! $receipt || in_array($receipt->status, ['rejected', 'superseded'], true))
                        || $r->fee_status === 'auto_approved'
                    ),
            ];
        })->values();

        $schoolFeeRows = collect();
        if ($program->usesSchoolBatchFee()) {
            $schoolFeeRows = \App\Models\TrainingSchoolFee::where('program_id', $program->id)
                ->with(['school', 'feeReceipt'])
                ->orderBy('school_id')
                ->get()
                ->map(function (\App\Models\TrainingSchoolFee $sf) {
                    $receipt = $sf->feeReceipt;

                    return [
                        'id' => $sf->id,
                        'school_name' => $sf->school?->name,
                        'teacher_count' => (int) $sf->teacher_count,
                        'total_due' => (float) $sf->total_due,
                        'amount_paid' => (float) ($sf->amount_paid ?? 0),
                        'outstanding' => $sf->outstandingBalance(),
                        'status' => $sf->status,
                        'receipt' => $receipt ? [
                            'id' => $receipt->id,
                            'status' => $receipt->status,
                            'amount' => (float) $receipt->amount,
                            'transaction_ref' => $receipt->transaction_ref,
                            'payment_date' => $receipt->payment_date?->toDateString(),
                            'has_file' => filled($receipt->file_path),
                            'rejection_reason' => $receipt->rejection_reason,
                        ] : null,
                        'can_approve' => $receipt?->status === 'uploaded',
                        'can_reject' => $receipt?->status === 'uploaded',
                    ];
                })->values();
        }

        return $this->inertia('Sahodaya/Training/FeeApprovals', [
            'program' => $program->only(['id', 'title', 'status', 'fee_type', 'fee_amount']),
            'hasFee' => $program->hasFee(),
            'usesSchoolBatchFee' => $program->usesSchoolBatchFee(),
            'rows' => $rows,
            'schoolFees' => $schoolFeeRows,
            'counts' => [
                'awaiting_proof' => $program->usesSchoolBatchFee()
                    ? $schoolFeeRows->filter(fn ($r) => in_array($r['status'], ['pending', 'rejected'], true) && ($r['outstanding'] ?? 0) > 0)->count()
                    : $rows->where('can_record', true)->count(),
                'pending_approval' => $program->usesSchoolBatchFee()
                    ? $schoolFeeRows->where('can_approve', true)->count()
                    : $rows->where('can_approve', true)->count(),
                'approved' => $program->usesSchoolBatchFee()
                    ? $schoolFeeRows->where('status', 'approved')->count()
                    : $rows->filter(fn ($r) => ($r['receipt']['status'] ?? null) === 'approved' || $r['status'] === 'confirmed')->count(),
            ],
        ]);
    }

    public function approveSchoolFee(Request $request, string $tenantId, TrainingProgram $program, \App\Models\TrainingSchoolFee $schoolFee)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolFee->program_id !== $program->id, 403);
        abort_unless($program->usesSchoolBatchFee(), 422, 'This programme does not use school batch fees.');

        $count = app(\App\Services\Training\TrainingSchoolFeeService::class)->approve($schoolFee, $request->user()->id);

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.school_fee.approved',
            "Training batch fee approved for {$schoolFee->school?->name}",
            ['school_fee_id' => $schoolFee->id, 'school_id' => $schoolFee->school_id, 'confirmed' => $count],
            $schoolFee,
        );

        return back()->with('success', $count > 0
            ? "School batch fee approved. {$count} registration(s) confirmed."
            : 'School batch fee approved.');
    }

    public function rejectSchoolFee(Request $request, string $tenantId, TrainingProgram $program, \App\Models\TrainingSchoolFee $schoolFee)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolFee->program_id !== $program->id, 403);

        $data = $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        app(\App\Services\Training\TrainingSchoolFeeService::class)->reject(
            $schoolFee,
            $request->user()->id,
            $data['rejection_reason'] ?? 'Contact your Sahodaya for details.',
        );

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.school_fee.rejected',
            "Training batch fee rejected for {$schoolFee->school?->name}",
            [
                'school_fee_id' => $schoolFee->id,
                'school_id' => $schoolFee->school_id,
                'reason' => $data['rejection_reason'] ?? null,
            ],
            $schoolFee,
        );

        $schoolId = $schoolFee->school_id;
        $service = app(NotificationService::class);
        foreach (\App\Models\User::role(['school_admin', 'school_staff'])->where('tenant_id', $schoolId)->get() as $user) {
            $service->notifyFromTemplate($user, 'training.fee.rejected', [
                'program_title' => $program->title,
                'reason'        => $data['rejection_reason'] ?? 'Contact your Sahodaya for details.',
            ], "/school-admin/{$schoolId}/training");
        }

        return back()->with('success', 'School batch fee rejected. School can re-upload.');
    }

    public function schoolFeeProof(string $tenantId, TrainingProgram $program, \App\Models\TrainingSchoolFee $schoolFee)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolFee->program_id !== $program->id, 403);

        $path = $schoolFee->feeReceipt?->file_path;
        abort_unless($path, 404);

        $disk = config('filesystems.upload_disk', 'shared');
        if (in_array($disk, ['s3', 'private'], true)) {
            return redirect(\Illuminate\Support\Facades\Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15)));
        }

        return TenantStorage::downloadResponse($this->sahodaya, $path);
    }

    public function recordPayment(Request $request, string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);
        abort_unless($program->hasFee(), 422, 'This programme does not require a fee.');
        abort_if($program->usesSchoolBatchFee(), 422, 'This programme uses a school batch fee — approve it from school fees.');
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

        app(\App\Services\Training\TrainingInvoiceService::class)->ensureForRegistration($registration);

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

        // This receipt is created directly with status "approved" (offline/manual
        // payment), so FeeReceiptObserver::updated() never fires — it only reacts
        // to a status *change* via ->update(), not ->create(). Post to the ledger
        // explicitly here, otherwise a manually recorded training fee never shows
        // up in accounts/ledger.
        app(\App\Services\Ledger\TrainingFeeLedgerService::class)->postApprovedReceipt($receipt->fresh());

        $issued = app(ProgramFeeReceiptService::class)->issueTraining(
            $registration->fresh(['program', 'teacher', 'school']),
            $receipt->fresh(),
        );

        if ($fullyPaid) {
            app(\App\Services\Training\TrainingInvoiceService::class)->markPaidForRegistration($registration);
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
            'resource_person_id' => [
                'nullable',
                'integer',
                Rule::exists('training_resource_persons', 'id')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->where('is_active', true),
            ],
        ]);

        $data['program_id'] = $program->id;
        $session = TrainingSession::create($data);

        if (! empty($data['resource_person_id'])) {
            $program->resourcePersons()->syncWithoutDetaching([$data['resource_person_id']]);
        }

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.session.created',
            "Training session created: {$session->title}",
            ['session_id' => $session->id],
            $session,
        );

        return back()->with('success', 'Session added.');
    }

    public function updateSession(Request $request, string $tenantId, TrainingProgram $program, TrainingSession $session)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($session->program_id !== $program->id, 404);

        $data = $request->validate([
            'title'            => 'required|string|max:255',
            'scheduled_at'     => 'nullable|date',
            'venue'            => 'nullable|string|max:255',
            'duration_minutes' => 'nullable|integer|min:15',
            'resource_person_id' => [
                'nullable',
                'integer',
                Rule::exists('training_resource_persons', 'id')
                    ->where('tenant_id', $this->sahodaya->id)
                    ->where('is_active', true),
            ],
        ]);

        $session->update($data);

        if (! empty($data['resource_person_id'])) {
            $program->resourcePersons()->syncWithoutDetaching([$data['resource_person_id']]);
        }

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.session.updated',
            "Training session updated: {$session->title}",
            ['session_id' => $session->id],
            $session,
        );

        return back()->with('success', 'Session updated.');
    }

    public function destroySession(string $tenantId, TrainingProgram $program, TrainingSession $session)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($session->program_id !== $program->id, 404);

        $title = $session->title;
        $sessionId = $session->id;
        $session->delete();

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.session.deleted',
            "Training session deleted: {$title}",
            ['session_id' => $sessionId],
        );

        return back()->with('success', 'Session deleted.');
    }

    public function confirmRegistration(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);
        abort_unless($registration->status === 'registered', 422, 'Only registered participants can be confirmed.');

        if ($program->hasFee()) {
            if ($program->usesSchoolBatchFee()) {
                $schoolFee = \App\Models\TrainingSchoolFee::where('program_id', $program->id)
                    ->where('school_id', $registration->school_id)
                    ->first();
                abort_unless(
                    $schoolFee?->isFullyPaid(),
                    422,
                    'School batch fee must be fully paid before confirming registration.'
                );
            } else {
                abort_unless(
                    $registration->isFullyPaid(),
                    422,
                    'Training fee must be fully paid before confirming registration.'
                );
            }
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

    public function cancelRegistration(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);
        abort_if(
            in_array($registration->status, ['cancelled', 'completed'], true),
            422,
            'This registration cannot be cancelled.'
        );

        app(\App\Services\Training\TrainingWaitlistService::class)->cancelAndPromote($registration);

        app(PlatformAuditLogger::class)->training(
            $program,
            'training.registration.cancelled',
            "Training registration cancelled for {$registration->teacher?->name}",
            [
                'registration_id' => $registration->id,
                'school_id'       => $registration->school_id,
                'teacher_id'      => $registration->teacher_id,
            ],
            $registration,
        );

        return back()->with('success', 'Registration cancelled. Waitlisted participants were promoted if a seat opened.');
    }

    public function approveFee(Request $request, string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);
        abort_if($program->usesSchoolBatchFee(), 422, 'This programme uses school batch fees.');

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

        if ($fullyPaid) {
            app(\App\Services\Training\TrainingInvoiceService::class)->markPaidForRegistration($registration);
        }

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
            'status' => 'required|in:present,absent,late,with_permission',
            'correction_reason' => 'nullable|string|max:500',
        ]);

        app(\App\Services\Training\TrainingAttendanceService::class)->updateAttendance(
            $session,
            $registration,
            [
                'status' => $data['status'],
                'correction_reason' => $data['correction_reason'] ?? null,
                'require_approval' => false,
            ],
            $request->user()?->id,
        );

        return back()->with('success', 'Attendance updated.');
    }

    public function reviewAttendanceCorrection(
        Request $request,
        string $tenantId,
        TrainingProgram $program,
        TrainingSession $session,
        TrainingRegistration $registration,
    ) {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($session->program_id !== $program->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $data = $request->validate([
            'decision' => 'required|in:approved,rejected',
        ]);

        $attendance = TrainingAttendance::where('session_id', $session->id)
            ->where('registration_id', $registration->id)
            ->firstOrFail();

        app(\App\Services\Training\TrainingAttendanceService::class)->reviewCorrection(
            $attendance,
            $data['decision'],
            $request->user()?->id,
        );

        return back()->with('success', 'Attendance correction '.$data['decision'].'.');
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

    /**
     * Preview what this teacher's certificate will look like, without requiring
     * one to already be issued (unlike printCertificate, which needs an
     * existing Certificate row). Renders live from the current template + the
     * registration's own attendance/school data, same as the sample-template
     * preview but using this teacher's real values instead of placeholders.
     */
    public function previewRegistrationCertificate(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $registration->load(['program', 'teacher', 'school']);

        $certificateService = app(TrainingCertificateService::class);
        $fieldValues = $certificateService->resolveFieldValues($registration, $this->sahodaya);
        $render = $certificateService->renderContext($registration, $this->sahodaya);

        $certificate = \App\Models\Certificate::where('entity_type', TrainingRegistration::class)
            ->where('entity_id', $registration->id)
            ->first();

        return view('training.certificate', array_merge($render, [
            'registration' => $registration,
            'certificate'  => $certificate,
            'sahodaya'     => $this->sahodaya,
            'fieldValues'  => $fieldValues,
            'previewOnly'  => ! $certificate,
        ]));
    }

    public function registrationInvoice(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);

        $service = app(\App\Services\Training\TrainingInvoiceService::class);
        $invoice = $service->ensureForRegistration($registration);
        abort_unless($invoice, 404, 'No invoice for this registration.');

        return $service->download($invoice, $this->sahodaya);
    }

    public function schoolFeeInvoice(string $tenantId, TrainingProgram $program, \App\Models\TrainingSchoolFee $schoolFee)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($schoolFee->program_id !== $program->id, 403);

        $service = app(\App\Services\Training\TrainingInvoiceService::class);
        $invoice = $service->ensureForSchoolFee($schoolFee);
        abort_unless($invoice, 404, 'No invoice for this school fee.');

        return $service->download($invoice, $this->sahodaya);
    }

    public function registrationIdCard(string $tenantId, TrainingProgram $program, TrainingRegistration $registration)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);
        abort_if($registration->program_id !== $program->id, 403);
        abort_if(in_array($registration->status, ['cancelled', 'rejected'], true), 422, 'ID card not available for this registration.');

        return app(\App\Services\Training\TrainingIdCardService::class)
            ->download($registration, $this->sahodaya);
    }

    public function previewCertificate(Request $request, string $tenantId, TrainingProgram $program)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        $templateId = $request->filled('template_id') ? (int) $request->input('template_id') : null;
        $render = app(TrainingCertificateService::class)
            ->sampleRenderContext($program, $this->sahodaya, $templateId);

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

    public function qrTeachers(string $tenantId, TrainingProgram $program, \App\Services\Training\TrainingQrReportService $reports)
    {
        abort_if($program->tenant_id !== $this->sahodaya->id, 403);

        return $this->inertia('Sahodaya/Training/QrTeachers', [
            'program' => $program->only('id', 'title', 'status'),
            'teachers' => $reports->createdTeachers($program),
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
