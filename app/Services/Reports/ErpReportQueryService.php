<?php

namespace App\Services\Reports;

use App\Models\AgeCategory;
use App\Models\AuditLog;
use App\Models\DataChangeLog;
use App\Models\FeeReceipt;
use App\Models\FestEvent;
use App\Models\FestSchoolEventFee;
use App\Models\McqExam;
use App\Models\McqRegistration;
use App\Models\MembershipFeeSlab;
use App\Models\MembershipPayment;
use App\Models\NotificationLog;
use App\Models\SchoolDocument;
use App\Models\SchoolDocumentType;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\FestRegistration;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Services\Portal\StudentPortalProvisioner;
use App\Services\Reports\Concerns\QueriesExtendedReports;
use App\Support\AcademicYear;
use App\Support\ErpReportMeta;
use App\Support\TenancyDatabase;
use Illuminate\Support\Collection;

class ErpReportQueryService
{
    use QueriesExtendedReports;

    public function __construct(
        private FestCrossEventReportService $festReports,
    ) {}

    /** @return list<string> */
    public static function runnableIds(): array
    {
        return ErpReportMeta::runnableIds();
    }

    public function isRunnable(string $reportId): bool
    {
        return ErpReportMeta::isRunnable($reportId);
    }

    /** @return array{columns: list<array{key: string, label: string}>, filters: list<array{key: string, label: string, type: string}>} */
    public function meta(string $reportId): array
    {
        return ErpReportMeta::meta($reportId);
    }

    /** @param  array<string, mixed>  $filters */
    public function rows(string $sahodayaId, string $reportId, array $filters = []): Collection
    {
        $reportId = ErpReportMeta::resolveId($reportId);

        if ($this->festReports->supports($reportId)) {
            return $this->festReports->rows($sahodayaId, $reportId, $filters);
        }

        return match ($reportId) {
            'RPT-PAY-006' => $this->paymentDueAll($sahodayaId, $filters),
            'RPT-PAY-013' => $this->lateFeeCollected($sahodayaId, $filters),
            'RPT-PAY-014' => $this->waiverRegister($sahodayaId),
            'RPT-PAY-019' => $this->dailyCollection($sahodayaId, $filters),
            'RPT-SCH-007' => $this->documentCompliance($sahodayaId),
            'RPT-STU-005' => $this->studentsByAgeCategory($sahodayaId),
            'RPT-STU-010' => $this->studentImportErrors($sahodayaId),
            'RPT-STU-011' => $this->studentsPhotoMissing($sahodayaId, $filters),
            'RPT-STU-012' => $this->duplicateAdmissionNumbers($sahodayaId),
            'RPT-STU-013' => $this->monthlyAdmissions($sahodayaId, $filters),
            'RPT-TCH-012' => $this->teachersMissingEmail($sahodayaId, $filters),
            'RPT-EML-004' => $this->templateUsageCounts(),
            'RPT-EML-005' => $this->emailsByModuleMonthly($filters),
            'RPT-MCQ-006' => $this->mcqSessionLog($sahodayaId, $filters),
            'RPT-MCQ-013' => $this->mcqIpAudit($sahodayaId, $filters),
            'RPT-AUTH-001' => $this->studentLoginReport($sahodayaId, $filters),
            'RPT-AUTH-002' => $this->teacherLoginReport($sahodayaId, $filters),
            'RPT-AUTH-003' => $this->neverLoggedInStudents($sahodayaId, $filters),
            'RPT-AUTH-004' => $this->neverLoggedInTeachers($sahodayaId, $filters),
            'RPT-AUTH-005' => $this->failedLoginAttempts($filters),
            'RPT-DOC-003' => $this->expiringDocuments($sahodayaId),
            'RPT-DOC-004' => $this->rejectedDocuments($sahodayaId),
            'RPT-DSH-001' => $this->sahodayaKpiSnapshot($sahodayaId),
            'RPT-DSH-003' => $this->financeDashboardSnapshot($sahodayaId),
            'RPT-DSH-005' => $this->registrationFunnel($sahodayaId),
            'RPT-TRN-003' => $this->trainingEligibilityRejections($sahodayaId),
            'RPT-TRN-009' => $this->trainingCapacityUtilization($sahodayaId),
            'RPT-TRN-010' => $this->trainingFinancialPending($sahodayaId),
            'RPT-PAY-008' => $this->paymentRejectedLog($sahodayaId),
            'RPT-PAY-020' => $this->modulePaymentMix($sahodayaId),
            'RPT-FIN-011' => $this->paymentDueAll($sahodayaId, $filters),
            'RPT-MCQ-011' => $this->mcqSchoolPerformance($sahodayaId, $filters),
            default => $this->extendedReportRows($sahodayaId, $reportId, $filters),
        };
    }

    /** @param  list<string>  $cols */
    /** @param  list<string>  $filterKeys */
    private function metaCols(array $cols, array $filterKeys): array
    {
        $columns = array_map(fn (string $key) => [
            'key'   => $key,
            'label' => ucwords(str_replace('_', ' ', $key)),
        ], $cols);

        $filters = array_map(fn (string $key) => [
            'key'   => $key,
            'label' => ucwords(str_replace('_', ' ', $key)),
            'type'  => in_array($key, ['from', 'to'], true) ? 'date' : 'text',
        ], $filterKeys);

        return compact('columns', 'filters');
    }

    private function schoolIds(string $sahodayaId): Collection
    {
        return collect(TenancyDatabase::schoolIdsFor($sahodayaId));
    }

    /** @param  array<string, mixed>  $filters */
    private function paymentDueAll(string $sahodayaId, array $filters): Collection
    {
        $eventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');
        $schoolIds = $this->schoolIds($sahodayaId);

        $rows = FestSchoolEventFee::whereIn('event_id', $eventIds)
            ->with(['event:id,title', 'school:id,name'])
            ->whereNotIn('status', ['approved', 'waived'])
            ->get()
            ->map(fn (FestSchoolEventFee $f) => [
                'school'     => $f->school?->name,
                'source'     => 'fest',
                'program'    => $f->event?->title,
                'amount'     => (float) $f->total_due,
                'status'     => $f->status,
                'updated_at' => $f->updated_at?->format('j M Y'),
            ]);

        $membership = MembershipPayment::whereIn('school_id', $schoolIds)
            ->with('school:id,name')
            ->whereNotIn('status', ['verified', 'waived', 'superseded'])
            ->get()
            ->map(fn (MembershipPayment $p) => [
                'school'     => $p->school?->name,
                'source'     => 'membership',
                'program'    => 'Annual membership',
                'amount'     => (float) $p->amount,
                'status'     => $p->status,
                'updated_at' => $p->updated_at?->format('j M Y'),
            ]);

        return $rows->concat($membership)
            ->when(! empty($filters['status']), fn ($c) => $c->where('status', $filters['status']))
            ->sortByDesc('amount')
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function lateFeeCollected(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $year = $filters['academic_year'] ?? AcademicYear::forSahodaya($sahodayaId);

        $slabs = MembershipFeeSlab::where('sahodaya_id', $sahodayaId)
            ->where('academic_year', $year)
            ->orderBy('min_students')
            ->get();

        return MembershipPayment::whereIn('school_id', $schoolIds)
            ->where('academic_year', $year)
            ->where('status', 'verified')
            ->with('school:id,name')
            ->get()
            ->map(function (MembershipPayment $p) use ($slabs) {
                $studentCount = Student::where('tenant_id', $p->school_id)->where('status', 'active')->count();
                $slab = $slabs->first(fn (MembershipFeeSlab $s) => $studentCount >= $s->min_students
                    && ($s->max_students === null || $studentCount <= $s->max_students));
                $base = $slab ? (float) $slab->amount : (float) $p->amount;
                $late = max(0, (float) $p->amount - $base);

                return [
                    'school'        => $p->school?->name,
                    'academic_year' => $p->academic_year,
                    'amount'        => (float) $p->amount,
                    'base_amount'   => $base,
                    'late_fee'      => $late,
                    'verified_at'   => $p->verified_at?->format('j M Y'),
                ];
            })
            ->filter(fn (array $r) => $r['late_fee'] > 0)
            ->values();
    }

    private function waiverRegister(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return FeeReceipt::query()
            ->where('status', 'approved')
            ->where('waiver_amount', '>', 0)
            ->orderByDesc('updated_at')
            ->get()
            ->filter(function (FeeReceipt $r) use ($schoolIds) {
                $schoolId = app(\App\Services\Fees\ProgramFeeReceiptService::class)->schoolIdForReceipt($r);

                return $schoolId && $schoolIds->contains($schoolId);
            })
            ->map(fn (FeeReceipt $r) => [
                'receipt_number' => $r->receipt_number,
                'school'         => Tenant::find(app(\App\Services\Fees\ProgramFeeReceiptService::class)->schoolIdForReceipt($r))?->name,
                'amount'         => (float) $r->amount,
                'waiver_amount'  => (float) $r->waiver_amount,
                'waiver_reason'  => $r->waiver_reason,
                'waived_at'      => $r->updated_at?->format('j M Y'),
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function dailyCollection(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $receipts = FeeReceipt::query()
            ->where('status', 'approved')
            ->whereBetween('payment_date', [$from, $to])
            ->get();

        $byDate = [];

        foreach ($receipts as $receipt) {
            $schoolId = app(\App\Services\Fees\ProgramFeeReceiptService::class)->schoolIdForReceipt($receipt);
            if (! $schoolId || ! $schoolIds->contains($schoolId)) {
                continue;
            }

            $date = $receipt->payment_date?->toDateString() ?? 'unknown';
            $type = match ($receipt->feeable_type) {
                (new MembershipPayment)->getMorphClass() => 'membership',
                (new FestSchoolEventFee)->getMorphClass() => 'fest',
                (new \App\Models\McqSchoolFee)->getMorphClass() => 'mcq',
                (new \App\Models\TrainingRegistration)->getMorphClass() => 'training',
                default => 'other',
            };

            $byDate[$date] ??= ['date' => $date, 'membership' => 0, 'fest' => 0, 'mcq' => 0, 'training' => 0, 'total' => 0];
            if (isset($byDate[$date][$type])) {
                $byDate[$date][$type] += (float) $receipt->amount;
            }
            $byDate[$date]['total'] += (float) $receipt->amount;
        }

        return collect($byDate)->sortKeys()->values();
    }

    private function documentCompliance(string $sahodayaId): Collection
    {
        $requiredTypes = SchoolDocumentType::where('sahodaya_id', $sahodayaId)
            ->where('is_required', true)
            ->where('is_active', true)
            ->count();

        $schools = Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')->get();

        return $schools->map(function (Tenant $school) use ($requiredTypes, $sahodayaId) {
            $docs = SchoolDocument::where('school_id', $school->id)
                ->whereHas('documentType', fn ($q) => $q->where('sahodaya_id', $sahodayaId))
                ->get();

            $approvedRequired = $docs->filter(fn (SchoolDocument $d) => $d->status === 'approved'
                && $d->documentType?->is_required)->count();

            return [
                'school'          => $school->name,
                'required_types'  => $requiredTypes,
                'approved'        => $docs->where('status', 'approved')->count(),
                'pending'         => $docs->where('status', 'pending')->count(),
                'expired'         => $docs->where('status', 'expired')->count(),
                'compliance'      => $requiredTypes === 0 ? 'N/A' : ($approvedRequired >= $requiredTypes ? 'Compliant' : 'Incomplete'),
            ];
        })->sortBy('compliance')->values();
    }

    private function studentsByAgeCategory(string $sahodayaId): Collection
    {
        $categories = AgeCategory::forSahodaya($sahodayaId)->active()->orderBy('sort_order')->get();
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNotNull('dob')
            ->with(['schoolClass:id,name', 'tenant:id,name'])
            ->get()
            ->map(function (Student $s) use ($categories) {
                $cat = $categories->first(fn (AgeCategory $c) => $c->isEligible($s->dob));

                return [
                    'school'       => $s->tenant?->name,
                    'student'      => $s->name,
                    'reg_no'       => $s->reg_no,
                    'class'        => $s->schoolClass?->name,
                    'dob'          => $s->dob?->toDateString(),
                    'age'          => $s->dob ? now()->diffInYears($s->dob) : null,
                    'age_category' => $cat?->label ?? 'Uncategorised',
                ];
            })
            ->values();
    }

    private function studentImportErrors(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return DataChangeLog::query()
            ->whereIn('school_id', $schoolIds)
            ->where('log_name', 'students')
            ->where('action', 'imported')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get()
            ->map(fn (DataChangeLog $log) => [
                'school'      => Tenant::find($log->school_id)?->name,
                'date'        => $log->created_at?->format('j M Y H:i'),
                'imported'    => $log->properties['imported'] ?? 0,
                'skipped'     => $log->properties['skipped'] ?? 0,
                'errors'      => $log->properties['errors'] ?? 0,
                'description' => $log->description,
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function studentsPhotoMissing(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('photo')->orWhere('photo', ''))
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['schoolClass:id,name', 'tenant:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Student $s) => [
                'school' => $s->tenant?->name,
                'name'   => $s->name,
                'reg_no' => $s->reg_no,
                'class'  => $s->schoolClass?->name,
                'status' => $s->status,
            ])
            ->values();
    }

    private function duplicateAdmissionNumbers(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)
            ->whereNotNull('admission_number')
            ->where('admission_number', '!=', '')
            ->with('tenant:id,name')
            ->get()
            ->groupBy(fn (Student $s) => $s->tenant_id.'|'.$s->admission_number)
            ->filter(fn ($group) => $group->count() > 1)
            ->map(fn ($group, $key) => [
                'school'           => $group->first()->tenant?->name,
                'admission_number' => explode('|', $key)[1] ?? '',
                'count'            => $group->count(),
                'students'         => $group->pluck('name')->join(', '),
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function monthlyAdmissions(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $from = $filters['from'] ?? now()->subMonths(12)->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return Student::whereIn('tenant_id', $schoolIds)
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->with('tenant:id,name')
            ->get()
            ->groupBy(fn (Student $s) => $s->created_at?->format('Y-m').'|'.$s->tenant_id)
            ->map(fn ($group, $key) => [
                'month'  => substr($key, 0, 7),
                'school' => $group->first()->tenant?->name,
                'count'  => $group->count(),
            ])
            ->sortByDesc('month')
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function teachersMissingEmail(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('email')->orWhere('email', ''))
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with('tenant:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Teacher $t) => [
                'school' => $t->tenant?->name,
                'name'   => $t->name,
                'reg_no' => $t->reg_no,
                'mobile' => $t->mobile,
                'status' => $t->status,
            ])
            ->values();
    }

    private function templateUsageCounts(): Collection
    {
        return NotificationLog::query()
            ->selectRaw('template_key, sum(case when status = "sent" then 1 else 0 end) as sent, sum(case when status = "failed" then 1 else 0 end) as failed, sum(case when status = "skipped" then 1 else 0 end) as skipped, count(*) as total')
            ->groupBy('template_key')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'template_key' => $row->template_key ?? 'direct',
                'sent'         => (int) $row->sent,
                'failed'       => (int) $row->failed,
                'skipped'      => (int) $row->skipped,
                'total'        => (int) $row->total,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    private function emailsByModuleMonthly(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subMonths(6)->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return NotificationLog::query()
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->get()
            ->groupBy(fn (NotificationLog $l) => $l->created_at?->format('Y-m').'|'.($l->template_key ?? 'direct'))
            ->map(fn ($group, $key) => [
                'month'        => substr($key, 0, 7),
                'template_key' => explode('|', $key)[1] ?? 'direct',
                'sent'         => $group->where('status', 'sent')->count(),
                'failed'       => $group->where('status', 'failed')->count(),
                'total'        => $group->count(),
            ])
            ->sortByDesc('month')
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function mcqSessionLog(string $sahodayaId, array $filters): Collection
    {
        $examIds = McqExam::where('tenant_id', $sahodayaId)->pluck('id');

        return McqRegistration::query()
            ->whereIn('exam_id', $examIds)
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))
            ->with(['exam:id,title', 'student:id,name,reg_no', 'school:id,name'])
            ->whereNotNull('started_at')
            ->orderByDesc('started_at')
            ->get()
            ->map(function (McqRegistration $r) {
                $duration = ($r->started_at && $r->submitted_at)
                    ? $r->started_at->diffInMinutes($r->submitted_at)
                    : null;

                return [
                    'exam'             => $r->exam?->title,
                    'student'          => $r->student?->name,
                    'school'           => $r->school?->name,
                    'started_at'       => $r->started_at?->toDateTimeString(),
                    'submitted_at'     => $r->submitted_at?->toDateTimeString(),
                    'duration_minutes' => $duration,
                    'status'           => $r->submitted_at ? 'completed' : 'in_progress',
                ];
            })
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function mcqIpAudit(string $sahodayaId, array $filters): Collection
    {
        $examIds = McqExam::where('tenant_id', $sahodayaId)->pluck('id');

        return AuditLog::query()
            ->where('category', 'mcq')
            ->whereBetween('created_at', [now()->subMonths(3), now()])
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (AuditLog $log) => [
                'exam'       => McqExam::find($filters['exam_id'] ?? null)?->title ?? '—',
                'student'    => $log->properties['student'] ?? $log->description,
                'school'     => $log->properties['school'] ?? '—',
                'action'     => $log->action,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDateTimeString(),
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function studentLoginReport(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['tenant:id,name', 'user:id,username,email,last_login_at'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Student $s) => [
                'school'         => $s->tenant?->name,
                'student'        => $s->name,
                'reg_no'         => $s->reg_no,
                'username'       => $s->user?->username,
                'last_login_at'  => $s->user?->last_login_at?->toDateTimeString() ?? 'Never',
                'email'          => StudentPortalProvisioner::isPlaceholderPortalEmail($s->user?->email)
                    ? '—'
                    : ($s->user?->email ?? '—'),
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function teacherLoginReport(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->whereNotNull('user_id')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['tenant:id,name', 'user:id,username,email,last_login_at'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (Teacher $t) => [
                'school'        => $t->tenant?->name,
                'teacher'       => $t->name,
                'reg_no'        => $t->reg_no,
                'username'      => $t->user?->username,
                'last_login_at' => $t->user?->last_login_at?->toDateTimeString() ?? 'Never',
                'email'         => $t->email ?? $t->user?->email,
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function neverLoggedInStudents(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['tenant:id,name', 'schoolClass:id,name', 'user:id,last_login_at'])
            ->get()
            ->filter(fn (Student $s) => ! $s->user_id || ! $s->user?->last_login_at)
            ->map(fn (Student $s) => [
                'school'      => $s->tenant?->name,
                'student'     => $s->name,
                'reg_no'      => $s->reg_no,
                'class'       => $s->schoolClass?->name,
                'has_portal'  => $s->user_id ? 'Yes (never logged in)' : 'No portal',
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function neverLoggedInTeachers(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['tenant:id,name', 'user:id,last_login_at'])
            ->get()
            ->filter(fn (Teacher $t) => ! $t->user_id || ! $t->user?->last_login_at)
            ->map(fn (Teacher $t) => [
                'school'     => $t->tenant?->name,
                'teacher'    => $t->name,
                'reg_no'     => $t->reg_no,
                'has_portal' => $t->user_id ? 'Yes (never logged in)' : 'No portal',
                'email'      => $t->email,
            ])
            ->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function failedLoginAttempts(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return AuditLog::query()
            ->where('action', 'login.failed')
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')
            ->limit(2000)
            ->get()
            ->map(fn (AuditLog $log) => [
                'username'    => $log->properties['username'] ?? $log->properties['email'] ?? '—',
                'ip_address'  => $log->ip_address,
                'description' => $log->description,
                'created_at'  => $log->created_at?->toDateTimeString(),
            ])
            ->values();
    }

    private function expiringDocuments(string $sahodayaId): Collection
    {
        return SchoolDocument::query()
            ->where('status', 'approved')
            ->whereNotNull('valid_to')
            ->whereBetween('valid_to', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->whereHas('documentType', fn ($q) => $q->where('sahodaya_id', $sahodayaId))
            ->with(['school:id,name', 'documentType:id,name'])
            ->orderBy('valid_to')
            ->get()
            ->map(fn (SchoolDocument $d) => [
                'school'         => $d->school?->name,
                'document_type'  => $d->documentType?->name,
                'valid_to'       => $d->valid_to?->toDateString(),
                'days_remaining' => $d->valid_to ? now()->diffInDays($d->valid_to, false) : null,
                'status'         => $d->status,
            ])
            ->values();
    }

    private function rejectedDocuments(string $sahodayaId): Collection
    {
        return SchoolDocument::query()
            ->where('status', 'rejected')
            ->whereHas('documentType', fn ($q) => $q->where('sahodaya_id', $sahodayaId))
            ->with(['school:id,name', 'documentType:id,name'])
            ->orderByDesc('reviewed_at')
            ->get()
            ->map(fn (SchoolDocument $d) => [
                'school'            => $d->school?->name,
                'document_type'     => $d->documentType?->name,
                'rejection_reason'  => $d->rejection_reason,
                'reviewed_at'       => $d->reviewed_at?->format('j M Y'),
            ])
            ->values();
    }

    private function sahodayaKpiSnapshot(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $eventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');

        $metrics = [
            'Approved schools'        => Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')->count(),
            'Registered schools'      => $schoolIds->count(),
            'Active students'         => Student::whereIn('tenant_id', $schoolIds)->where('status', 'active')->count(),
            'Active teachers'         => Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')->count(),
            'Fest events'             => $eventIds->count(),
            'Pending membership fees' => MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'submitted')->count(),
            'Failed receipt emails'   => FeeReceipt::where('receipt_email_status', 'failed')->count(),
        ];

        return collect($metrics)->map(fn ($value, $metric) => compact('metric', 'value'))->values();
    }

    private function financeDashboardSnapshot(string $sahodayaId): Collection
    {
        $eventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');
        $schoolIds = $this->schoolIds($sahodayaId);

        $metrics = [
            'Fest outstanding'        => FestSchoolEventFee::whereIn('event_id', $eventIds)->whereNotIn('status', ['approved', 'waived'])->sum('total_due'),
            'Membership outstanding'  => MembershipPayment::whereIn('school_id', $schoolIds)->whereNotIn('status', ['verified', 'waived', 'superseded'])->sum('amount'),
            'Approved receipts (YTD)' => FeeReceipt::where('status', 'approved')->whereYear('payment_date', now()->year)->sum('amount'),
            'Waivers applied (YTD)'   => FeeReceipt::where('waiver_amount', '>', 0)->whereYear('updated_at', now()->year)->sum('waiver_amount'),
            'Failed email deliveries' => NotificationLog::where('status', 'failed')->count(),
        ];

        return collect($metrics)->map(fn ($value, $metric) => [
            'metric' => $metric,
            'value'  => is_numeric($value) ? round((float) $value, 2) : $value,
        ])->values();
    }

    private function registrationFunnel(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);
        $schools = Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')->get();

        return $schools->map(function (Tenant $school) use ($year) {
            $reg = \App\Models\Registration::where('school_id', $school->id)->where('academic_year', $year)->first();
            $payment = MembershipPayment::where('school_id', $school->id)->where('academic_year', $year)->latest()->first();

            return [
                'school'              => $school->name,
                'registration_status' => $reg?->registration_status ?? 'not_started',
                'students'            => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'teachers'            => Teacher::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'payment_status'      => $payment?->status ?? 'none',
            ];
        })->values();
    }

    private function trainingEligibilityRejections(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return TrainingRegistration::whereIn('school_id', $schoolIds)
            ->where('status', 'rejected')
            ->with(['program:id,title', 'teacher:id,name', 'school:id,name'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (TrainingRegistration $r) => [
                'program'    => $r->program?->title,
                'teacher'    => $r->teacher?->name,
                'school'     => $r->school?->name,
                'reason'     => 'Rejected nomination',
                'status'     => $r->status,
                'created_at' => $r->created_at?->format('j M Y'),
            ])
            ->values();
    }

    private function trainingCapacityUtilization(string $sahodayaId): Collection
    {
        return TrainingProgram::where('tenant_id', $sahodayaId)
            ->withCount('registrations')
            ->orderBy('title')
            ->get()
            ->map(function (TrainingProgram $p) {
                $cap = (int) ($p->max_participants ?? 0);
                $enrolled = (int) $p->registrations_count;
                $pct = $cap > 0 ? round($enrolled / $cap * 100, 1) : null;

                return [
                    'program'          => $p->title,
                    'capacity'         => $cap ?: 'Unlimited',
                    'enrolled'         => $enrolled,
                    'utilization_pct'  => $pct !== null ? $pct.'%' : '—',
                ];
            })
            ->values();
    }

    private function trainingFinancialPending(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return TrainingRegistration::whereIn('school_id', $schoolIds)
            ->whereHas('feeReceipt', fn ($q) => $q->whereIn('status', ['uploaded', 'pending']))
            ->with(['program:id,title', 'teacher:id,name', 'school:id,name', 'feeReceipt:id,amount,status'])
            ->get()
            ->map(fn (TrainingRegistration $r) => [
                'school'   => $r->school?->name,
                'program'  => $r->program?->title,
                'teacher'  => $r->teacher?->name,
                'amount'   => (float) ($r->feeReceipt?->amount ?? 0),
                'status'   => $r->feeReceipt?->status ?? 'none',
            ])
            ->values();
    }

    private function paymentRejectedLog(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        $membership = MembershipPayment::whereIn('school_id', $schoolIds)
            ->where('status', 'rejected')
            ->with('school:id,name')
            ->get()
            ->map(fn (MembershipPayment $p) => [
                'school'       => $p->school?->name,
                'module'       => 'membership',
                'amount'       => (float) $p->amount,
                'reason'       => $p->rejection_reason,
                'rejected_at'  => $p->updated_at?->format('j M Y'),
            ]);

        $receipts = FeeReceipt::where('status', 'rejected')
            ->orderByDesc('updated_at')
            ->get()
            ->filter(function (FeeReceipt $r) use ($schoolIds) {
                $schoolId = app(\App\Services\Fees\ProgramFeeReceiptService::class)->schoolIdForReceipt($r);

                return $schoolId && $schoolIds->contains($schoolId);
            })
            ->map(fn (FeeReceipt $r) => [
                'school'      => Tenant::find(app(\App\Services\Fees\ProgramFeeReceiptService::class)->schoolIdForReceipt($r))?->name,
                'module'      => 'program_fee',
                'amount'      => (float) $r->amount,
                'reason'      => $r->rejection_reason,
                'rejected_at' => $r->updated_at?->format('j M Y'),
            ]);

        return $membership->concat($receipts)->values();
    }

    private function modulePaymentMix(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $eventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');

        $modules = [
            'membership' => (float) MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'verified')->sum('amount'),
            'fest'       => (float) FestSchoolEventFee::whereIn('event_id', $eventIds)->where('status', 'approved')->sum('total_due'),
            'mcq'        => (float) FeeReceipt::where('status', 'approved')->where('feeable_type', (new \App\Models\McqSchoolFee)->getMorphClass())->sum('amount'),
            'training'   => (float) FeeReceipt::where('status', 'approved')->where('feeable_type', (new TrainingRegistration)->getMorphClass())->sum('amount'),
        ];

        $total = array_sum($modules) ?: 1;

        return collect($modules)->map(fn ($amount, $module) => [
            'module' => $module,
            'count'  => '—',
            'amount' => round($amount, 2),
            'pct'    => round($amount / $total * 100, 1).'%',
        ])->values();
    }

    /** @param  array<string, mixed>  $filters */
    private function mcqSchoolPerformance(string $sahodayaId, array $filters = []): Collection
    {
        $examIds = McqExam::where('tenant_id', $sahodayaId)->pluck('id');

        return McqRegistration::query()
            ->whereIn('exam_id', $examIds)
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))
            ->with(['exam:id,title', 'school:id,name'])
            ->get()
            ->groupBy(fn (McqRegistration $r) => $r->exam_id.'|'.$r->school_id)
            ->map(function ($group) {
                $first = $group->first();

                return [
                    'school'     => $first->school?->name,
                    'exam'       => $first->exam?->title,
                    'registered' => $group->count(),
                    'present'    => $group->where('attendance_status', 'present')->count(),
                    'avg_score'  => '—',
                ];
            })
            ->values();
    }

    private function festRegistrationWindowStatus(string $sahodayaId, ?string $eventType = null): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)
            ->when($eventType, fn ($q) => $q->where('event_type', $eventType))
            ->withCount(['registrations'])
            ->orderByDesc('registration_open')
            ->get()
            ->map(fn (FestEvent $e) => [
                'event'               => $e->title,
                'type'                => $e->event_type,
                'status'              => $e->status,
                'registration_opens'  => $e->registration_open?->toDateString(),
                'registration_closes' => $e->registration_close?->toDateString(),
                'registrations'       => $e->registrations_count,
            ])
            ->values();
    }
}
