<?php

namespace App\Services\Reports\Concerns;

use App\Models\Alumni;
use App\Models\AuditLog;
use App\Models\FeeReceipt;
use App\Models\LedgerOpeningBalance;
use App\Models\LedgerTransaction;
use App\Models\McqExam;
use App\Models\McqMark;
use App\Models\McqQuestionBank;
use App\Models\McqRegistration;
use App\Models\McqSchoolFee;
use App\Models\MembershipFeeSlab;
use App\Models\MembershipPayment;
use App\Models\NotificationLog;
use App\Models\OfficeBearers;
use App\Models\Registration;
use App\Models\SahodayaPayable;
use App\Models\SahodayaRegistrationWindow;
use App\Models\SchoolClass;
use App\Models\SchoolDocument;
use App\Models\SchoolDocumentType;
use App\Models\SchoolYearSubmission;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\TcRequest;
use App\Models\Tenant;
use App\Models\TrainingProgram;
use App\Models\TrainingRegistration;
use App\Models\TrainingSession;
use App\Models\User;
use App\Models\FestEvent;
use App\Models\FestEventItem;
use App\Models\FestRegistration;
use App\Models\FestSchoolEventFee;
use App\Services\Ledger\FinancialStatementsService;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\AcademicYear;
use Illuminate\Support\Collection;

trait QueriesExtendedReports
{
    /** @param  array<string, mixed>  $filters */
    protected function extendedReportRows(string $sahodayaId, string $reportId, array $filters): Collection
    {
        return match ($reportId) {
            'RPT-SCH-001' => $this->rptSchoolList($sahodayaId),
            'RPT-SCH-002' => $this->rptMembershipStatusBySchool($sahodayaId),
            'RPT-SCH-003' => $this->rptStudentCountBySchool($sahodayaId),
            'RPT-SCH-004' => $this->rptTeacherCountBySchool($sahodayaId),
            'RPT-SCH-005' => $this->rptSchoolLoginHistory($sahodayaId, $filters),
            'RPT-SCH-006' => $this->rptSchoolActivity($sahodayaId, $filters),
            'RPT-SCH-008' => $this->rptPendingSchoolApplications($sahodayaId),
            'RPT-SCH-009' => $this->rptClusterSummary($sahodayaId),
            'RPT-SCH-010' => $this->rptOfficeBearers($sahodayaId),
            'RPT-SCH-011' => $this->rptCoordinatorAssignments($sahodayaId),
            'RPT-SCH-012', 'RPT-SCH-014' => $this->rptSchoolContactDirectory($sahodayaId),
            'RPT-SCH-013' => $this->rptInactiveSchools($sahodayaId),
            'RPT-SCH-015' => $this->rptAnnualSubmissionStatus($sahodayaId),

            'RPT-STU-001' => $this->rptStudentList($sahodayaId, $filters),
            'RPT-STU-002' => $this->rptStudentGenderSummary($sahodayaId),
            'RPT-STU-003' => $this->rptStudentClassSummary($sahodayaId),
            'RPT-STU-004' => $this->rptStudentCategorySummary($sahodayaId),
            'RPT-STU-006' => $this->rptStudentsByVerification($sahodayaId, false, $filters),
            'RPT-STU-007' => $this->rptStudentsByVerification($sahodayaId, true, $filters),
            'RPT-STU-008' => $this->rptInactiveStudents($sahodayaId),
            'RPT-STU-014' => $this->rptAlumniList($sahodayaId),
            'RPT-STU-015' => $this->rptTcLog($sahodayaId),

            'RPT-TCH-001' => $this->rptTeacherList($sahodayaId, $filters),
            'RPT-TCH-002' => $this->rptTeachersByTeachingType($sahodayaId),
            'RPT-TCH-003' => $this->rptTeachersBySubject($sahodayaId),
            'RPT-TCH-004' => $this->rptTeacherCountBySchool($sahodayaId),
            'RPT-TCH-005', 'RPT-TRN-007' => $this->rptTeacherTrainingHistory($sahodayaId),
            'RPT-TCH-006' => $this->rptTeachersByVerification($sahodayaId, false, $filters),
            'RPT-TCH-007' => $this->rptTeachersByVerification($sahodayaId, true, $filters),
            'RPT-TCH-008' => $this->rptTeachersByQualification($sahodayaId),
            'RPT-TCH-009' => $this->rptTeachersByExperience($sahodayaId),

            'RPT-PAY-001' => $this->rptMembershipCollectionSummary($sahodayaId),
            'RPT-PAY-002' => $this->rptMembershipPayments($sahodayaId, 'submitted'),
            'RPT-PAY-003' => $this->rptExpiredMembership($sahodayaId),
            'RPT-PAY-004' => $this->rptRenewedMembership($sahodayaId),
            'RPT-PAY-005' => $this->rptMembershipPaymentHistory($sahodayaId, $filters),
            'RPT-PAY-009' => $this->rptReceiptEmailStatus($sahodayaId),
            'RPT-PAY-010' => $this->rptUnifiedPaymentHub($sahodayaId, $filters),
            'RPT-PAY-011' => $this->rptProofPending($sahodayaId),
            'RPT-PAY-012' => $this->rptSchoolCollectionComparison($sahodayaId),
            'RPT-PAY-015' => $this->rptInvoiceOutstanding($sahodayaId),
            'RPT-PAY-016' => $this->rptReceiptReprintLog($sahodayaId),
            'RPT-PAY-017' => $this->rptMembershipCertificates($sahodayaId),
            'RPT-PAY-018' => $this->rptFeeSlabExport($sahodayaId),

            'RPT-FIN-001' => $this->rptDayBook($sahodayaId, $filters),
            'RPT-FIN-002' => $this->rptCashBook($sahodayaId, $filters),
            'RPT-FIN-003' => $this->rptBankBook($sahodayaId, $filters),
            'RPT-FIN-004' => $this->rptGeneralLedger($sahodayaId, $filters),
            'RPT-FIN-005' => $this->rptTrialBalance($sahodayaId),
            'RPT-FIN-006' => $this->rptIncomeExpenditure($sahodayaId, $filters),
            'RPT-FIN-007' => $this->rptBalanceSheet($sahodayaId),
            'RPT-FIN-008' => $this->rptReceiptRegister($sahodayaId),
            'RPT-FIN-009' => $this->rptPaymentRegister($sahodayaId),
            'RPT-FIN-010' => $this->rptOutstandingReceivables($sahodayaId),
            'RPT-FIN-012' => $this->rptCollectionSummary($sahodayaId),
            'RPT-FIN-013' => $this->rptEventWiseIncome($sahodayaId),
            'RPT-FIN-014' => $this->rptSchoolWiseIncome($sahodayaId),
            'RPT-FIN-015' => $this->rptMonthlyIncomeTrend($sahodayaId, $filters),
            'RPT-FIN-016' => $this->rptExpenseAnalysis($sahodayaId, $filters),
            'RPT-FIN-017' => $this->rptCostCenter($sahodayaId),
            'RPT-FIN-018' => $this->rptBankReconciliation($sahodayaId),
            'RPT-FIN-019' => $this->rptOpeningBalances($sahodayaId),
            'RPT-FIN-020' => $this->rptVoucherListing($sahodayaId, $filters),

            'RPT-MCQ-001' => $this->rptMcqRegistrationBySchool($sahodayaId, $filters),
            'RPT-MCQ-002' => $this->rptMcqRegistrationByTier($sahodayaId, $filters),
            'RPT-MCQ-003' => $this->rptMcqFeeCollection($sahodayaId, $filters),
            'RPT-MCQ-004' => $this->rptMcqHallTickets($sahodayaId, $filters),
            'RPT-MCQ-005' => $this->rptMcqAttendance($sahodayaId, $filters),
            'RPT-MCQ-007' => $this->rptMcqResultsByTier($sahodayaId, $filters),
            'RPT-MCQ-008' => $this->rptMcqRankList($sahodayaId, $filters),
            'RPT-MCQ-009' => $this->rptMcqQuestionAnalysis($sahodayaId, $filters),
            'RPT-MCQ-010' => $this->rptMcqAbsentIncomplete($sahodayaId, $filters),
            'RPT-MCQ-012' => $this->rptMcqTierCutoffs($sahodayaId, $filters),
            'RPT-MCQ-014' => $this->rptMcqQuestionBankExport($sahodayaId),
            'RPT-MCQ-015' => $this->rptMcqRegistrationWindow($sahodayaId),

            'RPT-TRN-001' => $this->rptTrainingProgramList($sahodayaId),
            'RPT-TRN-002' => $this->rptTrainingNominationsBySchool($sahodayaId),
            'RPT-TRN-004' => $this->rptTrainingFees($sahodayaId),
            'RPT-TRN-005' => $this->rptTrainingAttendance($sahodayaId),
            'RPT-TRN-006' => $this->rptTrainingCertificates($sahodayaId),
            'RPT-TRN-008' => $this->rptTrainingFeedbackSummary($sahodayaId),
            'RPT-TRN-011' => $this->rptTrainingNominationQueue($sahodayaId),
            'RPT-TRN-012' => $this->rptTrainingResourcePersons($sahodayaId),

            'RPT-EML-001' => $this->rptEmailDeliveryLog($filters),
            'RPT-EML-002' => $this->rptFailedEmails($filters),
            'RPT-EML-003' => $this->rptReceiptEmailStatus($sahodayaId),

            'RPT-AUD-001' => $this->rptAuditTrail($filters),
            'RPT-AUD-002' => $this->rptAuthEventsSummary($filters),
            'RPT-AUD-003' => $this->rptFinanceAudit($filters),
            'RPT-AUD-004' => $this->rptExportActivityLog($filters),
            'RPT-AUD-005' => $this->failedLoginAttempts($filters),

            'RPT-DOC-001' => $this->rptDocumentReviewQueue($sahodayaId),
            'RPT-DOC-002' => $this->rptDocumentTypeConfig($sahodayaId),

            'RPT-CAL-001' => $this->rptAggregatedCalendar($sahodayaId, $filters),
            'RPT-CAL-002' => $this->rptMembershipWindows($sahodayaId),
            'RPT-CAL-003' => $this->rptFestEventDates($sahodayaId),
            'RPT-CAL-004' => $this->rptMcqExamSchedule($sahodayaId),

            'RPT-DSH-002' => $this->rptSchoolDashboardExport($sahodayaId),
            'RPT-DSH-004' => $this->rptEventOpsBrief($sahodayaId),

            default => collect(),
        };
    }

    protected function rptSchoolList(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);
        $schoolIds = $this->schoolIds($sahodayaId);

        return Tenant::whereIn('id', $schoolIds)->orderBy('name')->get()->map(function (Tenant $school) use ($year) {
            $payment = MembershipPayment::where('school_id', $school->id)->where('academic_year', $year)->where('status', 'verified')->latest()->first();

            return [
                'school'            => $school->name,
                'membership_status' => $school->membership_status,
                'payment_status'    => $payment?->status ?? 'none',
                'students'          => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                'classes'           => SchoolClass::where('tenant_id', $school->id)->where('is_active', true)->count(),
                'joined'            => $school->created_at?->format('Y-m-d'),
            ];
        });
    }

    protected function rptMembershipStatusBySchool(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);

        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->orderBy('name')->get()->map(function (Tenant $school) use ($year) {
            $reg = Registration::where('school_id', $school->id)->where('academic_year', $year)->first();
            $payment = MembershipPayment::where('school_id', $school->id)->where('academic_year', $year)->latest()->first();

            return [
                'school'              => $school->name,
                'membership_status'   => $school->membership_status,
                'registration_status' => $reg?->registration_status ?? 'not_started',
                'payment_status'      => $payment?->status ?? 'none',
            ];
        });
    }

    protected function rptStudentCountBySchool(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')
            ->orderBy('name')->get()->map(fn (Tenant $s) => [
                'school'        => $s->name,
                'student_count' => Student::where('tenant_id', $s->id)->count(),
                'active_count'  => Student::where('tenant_id', $s->id)->where('status', 'active')->count(),
            ]);
    }

    protected function rptTeacherCountBySchool(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')
            ->orderBy('name')->get()->map(fn (Tenant $s) => [
                'school'        => $s->name,
                'teacher_count' => Teacher::where('tenant_id', $s->id)->where('status', 'active')->count(),
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptSchoolLoginHistory(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return User::whereIn('tenant_id', $schoolIds)
            ->whereNotNull('last_login_at')
            ->whereBetween('last_login_at', [$from, $to.' 23:59:59'])
            ->with('tenant:id,name')
            ->orderByDesc('last_login_at')
            ->get()
            ->map(fn (User $u) => [
                'school'         => $u->tenant?->name,
                'user'           => $u->name ?? $u->username,
                'role'           => $u->getRoleNames()->first() ?? '—',
                'last_login_at'  => $u->last_login_at?->toDateTimeString(),
                'email'          => $u->email,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptSchoolActivity(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId)->all();
        $from = $filters['from'] ?? now()->subDays(14)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return AuditLog::query()
            ->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->where(function ($q) use ($schoolIds, $sahodayaId) {
                $q->whereIn('tenant_id', $schoolIds)->orWhere('tenant_id', $sahodayaId);
            })
            ->orderByDesc('created_at')
            ->limit(1000)
            ->get()
            ->map(fn (AuditLog $log) => [
                'school'      => Tenant::find($log->tenant_id)?->name ?? '—',
                'action'      => $log->action,
                'description' => $log->description,
                'user'        => $log->properties['user'] ?? '—',
                'created_at'  => $log->created_at?->toDateTimeString(),
            ]);
    }

    protected function rptPendingSchoolApplications(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')
            ->where('membership_status', 'pending')->orderByDesc('created_at')->get()
            ->map(function (Tenant $s) {
                $payload = $s->application_payload ?? [];

                return [
                    'school'       => $s->name,
                    'status'       => $s->membership_status,
                    'submitted_at' => $s->created_at?->format('j M Y'),
                    'contact_email'=> $payload['school_email'] ?? $payload['contact_email'] ?? '—',
                    'phone'        => $payload['phone'] ?? $payload['contact_phone'] ?? '—',
                ];
            });
    }

    protected function rptClusterSummary(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')
            ->get()
            ->groupBy(fn (Tenant $s) => ($s->application_payload['cluster'] ?? $s->application_payload['district'] ?? 'Unassigned'))
            ->map(function ($schools, $cluster) {
                $ids = $schools->pluck('id');

                return [
                    'cluster'   => $cluster,
                    'schools'   => $schools->count(),
                    'students'  => Student::whereIn('tenant_id', $ids)->where('status', 'active')->count(),
                    'teachers'  => Teacher::whereIn('tenant_id', $ids)->where('status', 'active')->count(),
                ];
            })
            ->sortBy('cluster')
            ->values();
    }

    protected function rptOfficeBearers(string $sahodayaId): Collection
    {
        return OfficeBearers::where('tenant_id', $sahodayaId)->active()->get()->map(fn (OfficeBearers $o) => [
            'name'        => $o->name,
            'designation' => $o->role,
            'school'      => $o->school_name,
            'email'       => $o->email,
            'phone'       => $o->phone,
        ]);
    }

    protected function rptCoordinatorAssignments(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')
            ->orderBy('name')->get()->map(function (Tenant $school) {
                $payload = $school->application_payload ?? [];
                $coordUser = User::role('school_event_coordinator')->where('tenant_id', $school->id)->first();

                return [
                    'school'             => $school->name,
                    'coordinator_name'   => $payload['event_coordinator_name'] ?? $coordUser?->name ?? '—',
                    'coordinator_email'  => $payload['event_coordinator_email'] ?? $coordUser?->email ?? '—',
                    'coordinator_phone'  => $payload['event_coordinator_phone'] ?? '—',
                    'portal_user'        => $coordUser?->username ?? '—',
                ];
            });
    }

    protected function rptSchoolContactDirectory(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')
            ->orderBy('name')->get()->map(function (Tenant $school) {
                $p = $school->application_payload ?? [];

                return [
                    'school'      => $school->name,
                    'code'        => $school->school_prefix,
                    'email'       => $p['school_email'] ?? $p['contact_email'] ?? '—',
                    'phone'       => $p['phone'] ?? $p['contact_phone'] ?? '—',
                    'address'     => $p['address'] ?? '—',
                    'principal'   => $p['principal_name'] ?? '—',
                    'coordinator' => $p['event_coordinator_name'] ?? '—',
                ];
            });
    }

    protected function rptInactiveSchools(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')
            ->where(fn ($q) => $q->where('is_active', false)->orWhere('membership_status', '!=', 'approved'))
            ->orderBy('name')->get()->map(fn (Tenant $s) => [
                'school'            => $s->name,
                'membership_status' => $s->membership_status,
                'is_active'         => $s->is_active ? 'Yes' : 'No',
                'last_activity'     => $s->updated_at?->format('j M Y'),
            ]);
    }

    protected function rptAnnualSubmissionStatus(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);
        $schoolIds = $this->schoolIds($sahodayaId);

        return SchoolYearSubmission::whereIn('school_id', $schoolIds)->where('academic_year', $year)
            ->with('school:id,name')->orderBy('school_id')->get()
            ->map(fn (SchoolYearSubmission $s) => [
                'school'          => $s->school?->name,
                'academic_year'   => $s->academic_year,
                'records_status'  => $s->full_records_status,
                'counts_status'   => $s->counts_status,
                'teacher_status'  => $s->teacher_status,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptStudentList(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['tenant:id,name', 'schoolClass:id,name'])
            ->orderBy('name')->get()
            ->map(fn (Student $s) => [
                'school' => $s->tenant?->name, 'name' => $s->name, 'reg_no' => $s->reg_no,
                'class' => $s->schoolClass?->name, 'gender' => $s->gender, 'status' => $s->status,
            ]);
    }

    protected function rptStudentGenderSummary(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->with('tenant:id,name')->get()
            ->groupBy(fn (Student $s) => $s->tenant_id.'|'.($s->gender ?? 'unknown'))
            ->map(fn ($group) => [
                'school' => $group->first()->tenant?->name,
                'gender' => $group->first()->gender ?? 'unknown',
                'count'  => $group->count(),
            ])->values();
    }

    protected function rptStudentClassSummary(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->with(['tenant:id,name', 'schoolClass:id,name'])->get()
            ->groupBy(fn (Student $s) => $s->tenant_id.'|'.($s->schoolClass?->name ?? '—'))
            ->map(fn ($group) => [
                'school' => $group->first()->tenant?->name,
                'class'  => $group->first()->schoolClass?->name ?? '—',
                'count'  => $group->count(),
            ])->values();
    }

    protected function rptStudentCategorySummary(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $categories = app(EffectiveMasterDataResolver::class)->classCategories($sahodayaId);

        return $categories->map(function ($cat) use ($schoolIds) {
            $count = Student::whereIn('tenant_id', $schoolIds)->where('status', 'active')
                ->whereHas('schoolClass', fn ($q) => $q->where('class_category_id', $cat->id))->count();

            return ['school' => 'All schools', 'category' => $cat->label, 'count' => $count];
        })->filter(fn ($r) => $r['count'] > 0)->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptStudentsByVerification(string $sahodayaId, bool $verified, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $query = Student::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with(['tenant:id,name', 'schoolClass:id,name']);
        $students = $verified ? $query->whereNotNull('verified_at')->get() : $query->whereNull('verified_at')->get();

        return $students->map(fn (Student $s) => [
            'school' => $s->tenant?->name, 'name' => $s->name, 'reg_no' => $s->reg_no,
            'class' => $s->schoolClass?->name, 'status' => $verified ? 'verified' : 'unverified',
            'verified_at' => $s->verified_at?->format('j M Y'),
        ]);
    }

    protected function rptInactiveStudents(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Student::whereIn('tenant_id', $schoolIds)->where('status', '!=', 'active')
            ->with(['tenant:id,name'])->orderBy('name')->get()
            ->map(fn (Student $s) => [
                'school' => $s->tenant?->name, 'name' => $s->name, 'reg_no' => $s->reg_no, 'status' => $s->status,
            ]);
    }

    protected function rptAlumniList(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Alumni::whereIn('tenant_id', $schoolIds)->orderByDesc('batch_year')->get()
            ->map(fn (Alumni $a) => [
                'school' => Tenant::find($a->tenant_id)?->name, 'name' => $a->name,
                'batch_year' => $a->batch_year, 'email' => $a->email,
                'organisation' => $a->current_organisation, 'approved' => $a->is_approved ? 'Yes' : 'No',
            ]);
    }

    protected function rptTcLog(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return TcRequest::whereIn('tenant_id', $schoolIds)->orderByDesc('created_at')->get()
            ->map(fn (TcRequest $t) => [
                'school' => Tenant::find($t->tenant_id)?->name, 'student' => $t->student_name,
                'class' => $t->class, 'status' => $t->status,
                'requested_at' => $t->created_at?->format('j M Y'), 'issued_date' => $t->issued_date?->format('Y-m-d'),
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptTeacherList(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with('tenant:id,name')->orderBy('name')->get()
            ->map(fn (Teacher $t) => [
                'school' => $t->tenant?->name, 'name' => $t->name, 'reg_no' => $t->reg_no,
                'designation' => $t->designation, 'email' => $t->email, 'status' => $t->status,
            ]);
    }

    protected function rptTeachersByTeachingType(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->with('teachingType:id,name')->get()
            ->groupBy(fn (Teacher $t) => $t->teachingType?->name ?? 'Unassigned')
            ->map(fn ($group, $type) => ['teaching_type' => $type, 'count' => $group->count()])->values();
    }

    protected function rptTeachersBySubject(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        $subjectLabelMap = \App\Models\Subject::forSahodaya($sahodayaId)->pluck('label', 'id');

        return Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')->get()
            ->flatMap(function (Teacher $t) use ($subjectLabelMap) {
                $names = collect($t->subject_ids ?? [])
                    ->map(fn ($id) => $subjectLabelMap->get($id))
                    ->filter()
                    ->values();
                if ($names->isEmpty()) {
                    return collect([$t->subject ?: 'Unassigned']);
                }

                return $names;
            })
            ->countBy()->map(fn ($count, $subject) => ['subject' => $subject, 'count' => $count])
            ->sortByDesc('count')->values();
    }

    protected function rptTeachersByQualification(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')->get()
            ->groupBy(fn (Teacher $t) => $t->qualification ?: 'Not specified')
            ->map(fn ($group, $qual) => ['qualification' => $qual, 'count' => $group->count()])
            ->sortByDesc('count')->values();
    }

    protected function rptTeachersByExperience(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')->get()
            ->groupBy(function (Teacher $t) {
                $y = (int) ($t->experience_years ?? 0);

                return match (true) {
                    $y < 5  => '0-4 years',
                    $y < 10 => '5-9 years',
                    $y < 20 => '10-19 years',
                    default => '20+ years',
                };
            })
            ->map(fn ($group, $band) => ['experience_band' => $band, 'count' => $group->count()])->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptTeachersByVerification(string $sahodayaId, bool $verified, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $query = Teacher::whereIn('tenant_id', $schoolIds)->where('status', 'active')
            ->when(! empty($filters['school_id']), fn ($q) => $q->where('tenant_id', $filters['school_id']))
            ->with('tenant:id,name');
        $teachers = $verified ? $query->whereNotNull('verified_at')->get() : $query->whereNull('verified_at')->get();

        return $teachers->map(fn (Teacher $t) => [
            'school' => $t->tenant?->name, 'name' => $t->name, 'reg_no' => $t->reg_no,
            'status' => $verified ? 'verified' : 'unverified', 'verified_at' => $t->verified_at?->format('j M Y'),
        ]);
    }

    protected function rptTeacherTrainingHistory(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return TrainingRegistration::whereIn('school_id', $schoolIds)
            ->with(['teacher:id,name', 'school:id,name', 'program:id,title'])
            ->orderByDesc('updated_at')->get()
            ->map(fn (TrainingRegistration $r) => [
                'teacher' => $r->teacher?->name, 'school' => $r->school?->name, 'program' => $r->program?->title,
                'status' => $r->status, 'completed_at' => $r->updated_at?->format('j M Y'), 'year' => $r->program?->title,
            ]);
    }

    protected function rptMembershipCollectionSummary(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $year = AcademicYear::forSahodaya($sahodayaId);
        $verified = MembershipPayment::whereIn('school_id', $schoolIds)->where('academic_year', $year)->where('status', 'verified')->sum('amount');
        $pending = MembershipPayment::whereIn('school_id', $schoolIds)->where('academic_year', $year)->where('status', 'submitted')->sum('amount');

        return collect([
            ['metric' => 'Verified collection', 'value' => round((float) $verified, 2)],
            ['metric' => 'Pending verification', 'value' => round((float) $pending, 2)],
            ['metric' => 'Verified count', 'value' => MembershipPayment::whereIn('school_id', $schoolIds)->where('academic_year', $year)->where('status', 'verified')->count()],
        ]);
    }

    protected function rptMembershipPayments(string $sahodayaId, string $status): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return MembershipPayment::whereIn('school_id', $schoolIds)->where('status', $status)
            ->with('school:id,name')->orderByDesc('created_at')->get()
            ->map(fn (MembershipPayment $p) => [
                'school' => $p->school?->name, 'amount' => (float) $p->amount,
                'status' => $p->status, 'submitted_at' => $p->created_at?->format('j M Y'),
            ]);
    }

    protected function rptExpiredMembership(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')
            ->where(fn ($q) => $q->where('renewal_status', 'expired')->orWhere('membership_status', 'expired'))
            ->orderBy('name')->get()
            ->map(fn (Tenant $s) => [
                'school' => $s->name, 'renewal_status' => $s->renewal_status ?? '—', 'membership_status' => $s->membership_status,
            ]);
    }

    protected function rptRenewedMembership(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);
        $schoolIds = $this->schoolIds($sahodayaId);

        return MembershipPayment::whereIn('school_id', $schoolIds)->where('academic_year', $year)->where('status', 'verified')
            ->with('school:id,name')->orderByDesc('verified_at')->get()
            ->map(fn (MembershipPayment $p) => [
                'school' => $p->school?->name, 'academic_year' => $p->academic_year,
                'amount' => (float) $p->amount, 'verified_at' => $p->verified_at?->format('j M Y'),
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMembershipPaymentHistory(string $sahodayaId, array $filters): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $status = $filters['status'] ?? 'all';

        return MembershipPayment::whereIn('school_id', $schoolIds)->where('status', '!=', 'superseded')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('school:id,name')->orderByDesc('created_at')->get()
            ->map(fn (MembershipPayment $p) => [
                'school' => $p->school?->name, 'academic_year' => $p->academic_year, 'amount' => (float) $p->amount,
                'status' => $p->status, 'method' => $p->payment_method ?? '—', 'verified_at' => $p->verified_at?->format('j M Y'),
            ]);
    }

    protected function rptReceiptEmailStatus(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $receiptService = app(\App\Services\Fees\ProgramFeeReceiptService::class);

        return FeeReceipt::query()->orderByDesc('updated_at')->limit(500)->get()
            ->filter(fn (FeeReceipt $r) => ($sid = $receiptService->schoolIdForReceipt($r)) && $schoolIds->contains($sid))
            ->map(fn (FeeReceipt $r) => [
                'receipt_number' => $r->receipt_number,
                'school'         => Tenant::find($receiptService->schoolIdForReceipt($r))?->name,
                'email_status'   => $r->receipt_email_status ?? '—',
                'emailed_at'     => $r->receipt_emailed_at?->format('j M Y H:i'),
                'error'          => $r->receipt_email_error,
                'resend_count'   => $r->receipt_email_resend_count ?? 0,
                'status'         => $r->receipt_email_status ?? '—',
            ])->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptUnifiedPaymentHub(string $sahodayaId, array $filters): Collection
    {
        $membership = $this->rptMembershipPaymentHistory($sahodayaId, $filters)->map(fn ($r) => array_merge($r, ['module' => 'membership', 'program' => 'Membership']));
        $training = TrainingRegistration::whereIn('school_id', $this->schoolIds($sahodayaId))
            ->with(['school:id,name', 'program:id,title', 'feeReceipt:id,amount,status'])
            ->get()->map(fn (TrainingRegistration $r) => [
                'school' => $r->school?->name, 'module' => 'training', 'program' => $r->program?->title,
                'amount' => (float) ($r->feeReceipt?->amount ?? 0), 'status' => $r->feeReceipt?->status ?? $r->status,
                'submitted_at' => $r->created_at?->format('j M Y'),
            ]);

        return $membership->concat($training)->values();
    }

    protected function rptProofPending(string $sahodayaId): Collection
    {
        return $this->rptMembershipPayments($sahodayaId, 'submitted')->map(fn ($r) => array_merge($r, [
            'module' => 'membership', 'submitted_at' => $r['submitted_at'],
        ]));
    }

    protected function rptSchoolCollectionComparison(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $eventIds = FestEvent::where('tenant_id', $sahodayaId)->pluck('id');
        $receiptService = app(\App\Services\Fees\ProgramFeeReceiptService::class);

        return Tenant::whereIn('id', $schoolIds)->orderBy('name')->get()->map(function (Tenant $school) use ($eventIds, $receiptService) {
            $membership = (float) MembershipPayment::where('school_id', $school->id)->where('status', 'verified')->sum('amount');
            $fest = (float) FestSchoolEventFee::whereIn('event_id', $eventIds)->where('school_id', $school->id)->where('status', 'approved')->sum('total_due');
            $mcq = (float) FeeReceipt::where('status', 'approved')->where('feeable_type', (new McqSchoolFee)->getMorphClass())
                ->get()->filter(fn (FeeReceipt $r) => $receiptService->schoolIdForReceipt($r) === $school->id)->sum('amount');
            $training = (float) FeeReceipt::where('status', 'approved')->where('feeable_type', (new TrainingRegistration)->getMorphClass())
                ->get()->filter(fn (FeeReceipt $r) => $receiptService->schoolIdForReceipt($r) === $school->id)->sum('amount');

            return [
                'school' => $school->name, 'membership' => $membership, 'fest' => $fest,
                'mcq' => $mcq, 'training' => $training, 'total' => $membership + $fest + $mcq + $training,
            ];
        });
    }

    protected function rptInvoiceOutstanding(string $sahodayaId): Collection
    {
        return SahodayaPayable::where('tenant_id', $sahodayaId)->whereIn('status', ['open', 'partial'])
            ->orderBy('due_date')->get()
            ->map(fn (SahodayaPayable $p) => [
                'school' => $p->vendor_name, 'source' => 'payable', 'amount' => $p->balanceDue(),
                'due_date' => $p->due_date?->format('Y-m-d'), 'status' => $p->status,
            ]);
    }

    protected function rptReceiptReprintLog(string $sahodayaId): Collection
    {
        return $this->rptReceiptEmailStatus($sahodayaId)->filter(fn ($r) => ($r['resend_count'] ?? 0) > 0)->values();
    }

    protected function rptMembershipCertificates(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);
        $schoolIds = $this->schoolIds($sahodayaId);

        return Registration::whereIn('school_id', $schoolIds)->where('academic_year', $year)
            ->with('school:id,name')->get()
            ->map(function (Registration $reg) use ($year) {
                $payment = MembershipPayment::where('school_id', $reg->school_id)->where('academic_year', $year)->where('status', 'verified')->latest()->first();

                return [
                    'school' => $reg->school?->name, 'academic_year' => $year,
                    'payment_status' => $payment?->status ?? 'none',
                    'registration_status' => $reg->registration_status,
                    'verified_at' => $payment?->verified_at?->format('j M Y'),
                ];
            });
    }

    protected function rptFeeSlabExport(string $sahodayaId): Collection
    {
        $year = AcademicYear::forSahodaya($sahodayaId);

        return MembershipFeeSlab::where('sahodaya_id', $sahodayaId)->where('academic_year', $year)
            ->orderBy('min_students')->get()
            ->map(fn (MembershipFeeSlab $s) => [
                'academic_year' => $s->academic_year, 'min_students' => $s->min_students,
                'max_students' => $s->max_students, 'amount' => (float) $s->amount,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptDayBook(string $sahodayaId, array $filters): Collection
    {
        return $this->ledgerTransactions($sahodayaId, $filters)->map(fn (LedgerTransaction $t) => [
            'date' => $t->transaction_date?->format('Y-m-d'), 'voucher' => $t->journal_id,
            'account' => $t->accountHead?->name, 'debit' => $t->entry_type === 'debit' ? $t->amount : '',
            'credit' => $t->entry_type === 'credit' ? $t->amount : '', 'narration' => $t->description,
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptCashBook(string $sahodayaId, array $filters): Collection
    {
        $rows = app(FinancialStatementsService::class)->cashBook($sahodayaId, null, $filters['from'] ?? null, $filters['to'] ?? null);

        return $rows->map(fn ($t) => [
            'date' => $t->transaction_date?->format('Y-m-d'), 'voucher' => $t->journal_id,
            'debit' => $t->entry_type === 'debit' ? $t->amount : '',
            'credit' => $t->entry_type === 'credit' ? $t->amount : '',
            'balance' => $t->running_balance ?? '—',
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptBankBook(string $sahodayaId, array $filters): Collection
    {
        return $this->rptCashBook($sahodayaId, $filters);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptGeneralLedger(string $sahodayaId, array $filters): Collection
    {
        return $this->rptDayBook($sahodayaId, $filters);
    }

    protected function rptTrialBalance(string $sahodayaId): Collection
    {
        return app(FinancialStatementsService::class)->trialBalance($sahodayaId)->map(fn ($row) => [
            'code' => $row->code, 'account' => $row->name, 'type' => $row->type,
            'opening' => $row->opening, 'debit' => $row->debit, 'credit' => $row->credit, 'balance' => $row->balance,
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptIncomeExpenditure(string $sahodayaId, array $filters): Collection
    {
        $data = app(FinancialStatementsService::class)->incomeAndExpenditure(
            $sahodayaId, null, $filters['from'] ?? null, $filters['to'] ?? null
        );

        return collect([
            ['category' => 'Income', 'amount' => $data['income']],
            ['category' => 'Expense', 'amount' => $data['expense']],
            ['category' => 'Surplus', 'amount' => $data['surplus']],
        ]);
    }

    protected function rptBalanceSheet(string $sahodayaId): Collection
    {
        $data = app(FinancialStatementsService::class)->balanceSheet($sahodayaId);

        return collect([
            ['section' => 'Assets', 'amount' => $data['assets']],
            ['section' => 'Liabilities', 'amount' => $data['liabilities']],
            ['section' => 'Equity', 'amount' => $data['equity']],
        ]);
    }

    protected function rptReceiptRegister(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);
        $receiptService = app(\App\Services\Fees\ProgramFeeReceiptService::class);

        return FeeReceipt::where('status', 'approved')->orderByDesc('payment_date')->limit(500)->get()
            ->filter(fn (FeeReceipt $r) => ($sid = $receiptService->schoolIdForReceipt($r)) && $schoolIds->contains($sid))
            ->map(fn (FeeReceipt $r) => [
                'receipt_number' => $r->receipt_number, 'date' => $r->payment_date?->format('Y-m-d'),
                'school' => Tenant::find($receiptService->schoolIdForReceipt($r))?->name,
                'amount' => (float) $r->amount, 'status' => $r->status,
            ])->values();
    }

    protected function rptPaymentRegister(string $sahodayaId): Collection
    {
        return SahodayaPayable::where('tenant_id', $sahodayaId)->orderByDesc('incurred_date')->get()
            ->map(fn (SahodayaPayable $p) => [
                'voucher' => $p->id, 'date' => $p->incurred_date?->format('Y-m-d'),
                'payee' => $p->vendor_name, 'amount' => (float) $p->amount, 'status' => $p->status,
            ]);
    }

    protected function rptOutstandingReceivables(string $sahodayaId): Collection
    {
        return $this->paymentDueAll($sahodayaId, []);
    }

    protected function rptCollectionSummary(string $sahodayaId): Collection
    {
        return $this->modulePaymentMix($sahodayaId)->map(fn ($r) => [
            'module' => $r['module'], 'collected' => $r['amount'], 'pending' => '—',
        ]);
    }

    protected function rptEventWiseIncome(string $sahodayaId): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)->withCount('registrations')->get()->map(function (FestEvent $e) {
            $collected = FestSchoolEventFee::where('event_id', $e->id)->where('status', 'approved')->sum('total_due');
            $pending = FestSchoolEventFee::where('event_id', $e->id)->whereNotIn('status', ['approved', 'waived'])->sum('total_due');

            // "Schools paid" = schools with EVERY fee row for this event approved/waived, not
            // just at least one. Under sports_composite per-head billing a school can have
            // several rows (one per Event Head); counting distinct school_id on a single
            // approved row would overcount schools that still owe money on another head.
            $schoolsPaid = FestSchoolEventFee::where('event_id', $e->id)
                ->select('school_id')
                ->groupBy('school_id')
                ->havingRaw("SUM(CASE WHEN status NOT IN ('approved', 'waived') THEN 1 ELSE 0 END) = 0")
                ->get()
                ->count();

            return [
                'event' => $e->title, 'collected' => (float) $collected, 'pending' => (float) $pending,
                'schools_paid' => $schoolsPaid,
            ];
        });
    }

    protected function rptSchoolWiseIncome(string $sahodayaId): Collection
    {
        return $this->rptSchoolCollectionComparison($sahodayaId)->map(fn ($r) => [
            'school' => $r['school'], 'collected' => $r['total'], 'pending' => '—',
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMonthlyIncomeTrend(string $sahodayaId, array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subMonths(12)->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return FeeReceipt::where('status', 'approved')->whereBetween('payment_date', [$from, $to])
            ->get()->groupBy(fn (FeeReceipt $r) => $r->payment_date?->format('Y-m'))
            ->map(fn ($group, $month) => [
                'month' => $month, 'income' => round($group->sum('amount'), 2),
                'expense' => 0, 'net' => round($group->sum('amount'), 2),
            ])->sortKeys()->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptExpenseAnalysis(string $sahodayaId, array $filters): Collection
    {
        $from = $filters['from'] ?? now()->startOfYear()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return LedgerTransaction::where('tenant_id', $sahodayaId)->where('entry_type', 'debit')
            ->whereBetween('transaction_date', [$from, $to])
            ->with('accountHead:id,name,category')->get()
            ->groupBy(fn (LedgerTransaction $t) => $t->accountHead?->id ?? 0)
            ->map(fn ($group) => [
                'account' => $group->first()->accountHead?->name ?? '—',
                'category' => $group->first()->accountHead?->category ?? 'expense',
                'amount' => round($group->sum('amount'), 2),
            ])->sortByDesc('amount')->values();
    }

    protected function rptCostCenter(string $sahodayaId): Collection
    {
        return LedgerTransaction::where('tenant_id', $sahodayaId)->with('accountHead:id,name')
            ->get()->groupBy(fn (LedgerTransaction $t) => $t->accountHead?->name ?? 'General')
            ->map(fn ($group, $center) => [
                'cost_center' => $center,
                'debit' => round($group->where('entry_type', 'debit')->sum('amount'), 2),
                'credit' => round($group->where('entry_type', 'credit')->sum('amount'), 2),
                'net' => round($group->where('entry_type', 'debit')->sum('amount') - $group->where('entry_type', 'credit')->sum('amount'), 2),
            ])->values();
    }

    protected function rptBankReconciliation(string $sahodayaId): Collection
    {
        return LedgerTransaction::where('tenant_id', $sahodayaId)->whereNotNull('bank_account_id')
            ->orderByDesc('transaction_date')->limit(200)->get()
            ->map(fn (LedgerTransaction $t) => [
                'bank_account' => (string) $t->bank_account_id,
                'statement_date' => $t->transaction_date?->format('Y-m-d'),
                'status' => $t->reconciled_at ? 'reconciled' : 'pending',
                'matched' => $t->reconciled_at ? 1 : 0,
                'unmatched' => $t->reconciled_at ? 0 : 1,
            ]);
    }

    protected function rptOpeningBalances(string $sahodayaId): Collection
    {
        return LedgerOpeningBalance::where('tenant_id', $sahodayaId)->with('accountHead:id,name')->get()
            ->map(fn (LedgerOpeningBalance $b) => [
                'account' => $b->accountHead?->name ?? '—',
                'opening_balance' => (float) $b->amount,
                'financial_year' => (string) $b->financial_year_id,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptVoucherListing(string $sahodayaId, array $filters): Collection
    {
        return $this->ledgerTransactions($sahodayaId, $filters)->map(fn (LedgerTransaction $t) => [
            'voucher_no' => $t->journal_id, 'date' => $t->transaction_date?->format('Y-m-d'),
            'type' => $t->entry_type, 'amount' => (float) $t->amount, 'narration' => $t->description,
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function ledgerTransactions(string $sahodayaId, array $filters): Collection
    {
        return LedgerTransaction::where('tenant_id', $sahodayaId)
            ->when(! empty($filters['from']), fn ($q) => $q->where('transaction_date', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->where('transaction_date', '<=', $filters['to']))
            ->with('accountHead:id,name')->orderBy('transaction_date')->get();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqRegistrationBySchool(string $sahodayaId, array $filters): Collection
    {
        $examIds = McqExam::where('tenant_id', $sahodayaId)->pluck('id');

        return McqRegistration::whereIn('exam_id', $examIds)
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))
            ->with(['exam:id,title', 'school:id,name'])->get()
            ->groupBy(fn (McqRegistration $r) => $r->exam_id.'|'.$r->school_id)
            ->map(fn ($group) => [
                'exam' => $group->first()->exam?->title, 'school' => $group->first()->school?->name,
                'registered' => $group->count(), 'approved' => $group->where('approval_status', 'approved')->count(),
            ])->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqRegistrationByTier(string $sahodayaId, array $filters): Collection
    {
        $examIds = McqExam::where('tenant_id', $sahodayaId)
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('id', $filters['exam_id']))
            ->pluck('id');

        return McqExam::whereIn('id', $examIds)->withCount('registrations')->get()
            ->map(fn (McqExam $e) => [
                'exam' => $e->title, 'tier' => $e->exam_level ?? '—', 'registered' => $e->registrations_count,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqFeeCollection(string $sahodayaId, array $filters): Collection
    {
        return McqSchoolFee::whereHas('exam', fn ($q) => $q->where('tenant_id', $sahodayaId))
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))
            ->with(['exam:id,title', 'school:id,name'])->get()
            ->map(fn (McqSchoolFee $f) => [
                'exam' => $f->exam?->title, 'school' => $f->school?->name,
                'amount' => (float) ($f->feeReceipt?->amount ?? 0), 'status' => $f->feeReceipt?->status ?? '—',
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqHallTickets(string $sahodayaId, array $filters): Collection
    {
        return $this->mcqRegistrationsQuery($sahodayaId, $filters)->where('approval_status', 'approved')
            ->map(fn (McqRegistration $r) => [
                'exam' => $r->exam?->title, 'student' => $r->student?->name,
                'school' => $r->school?->name, 'hall_ticket_no' => $r->hall_ticket_no,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqAttendance(string $sahodayaId, array $filters): Collection
    {
        return $this->mcqRegistrationsQuery($sahodayaId, $filters)->map(fn (McqRegistration $r) => [
            'exam' => $r->exam?->title, 'student' => $r->student?->name,
            'school' => $r->school?->name, 'attendance_status' => $r->attendance_status ?? '—',
        ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqResultsByTier(string $sahodayaId, array $filters): Collection
    {
        return McqRegistration::whereIn('exam_id', McqExam::where('tenant_id', $sahodayaId)->pluck('id'))
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))
            ->with(['exam:id,title,exam_level', 'student:id,name', 'school:id,name', 'mark'])
            ->get()->map(fn (McqRegistration $r) => [
                'exam' => $r->exam?->title, 'tier' => $r->exam?->exam_level,
                'student' => $r->student?->name, 'school' => $r->school?->name,
                'score' => $r->mark?->score, 'rank' => $r->mark?->rank,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqRankList(string $sahodayaId, array $filters): Collection
    {
        return McqMark::whereHas('registration.exam', fn ($q) => $q->where('tenant_id', $sahodayaId))
            ->when(! empty($filters['exam_id']), fn ($q) => $q->whereHas('registration', fn ($q2) => $q2->where('exam_id', $filters['exam_id'])))
            ->with(['registration.exam:id,title', 'registration.student:id,name', 'registration.school:id,name'])
            ->orderBy('rank')->limit(500)->get()
            ->map(fn (McqMark $m) => [
                'exam' => $m->registration?->exam?->title, 'rank' => $m->rank,
                'student' => $m->registration?->student?->name, 'school' => $m->registration?->school?->name,
                'score' => $m->score,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqQuestionAnalysis(string $sahodayaId, array $filters): Collection
    {
        $examId = $filters['exam_id'] ?? McqExam::where('tenant_id', $sahodayaId)->value('id');
        if (! $examId) {
            return collect();
        }

        return McqRegistration::where('exam_id', $examId)->whereNotNull('submitted_at')
            ->with('exam:id,title')->limit(200)->get()
            ->flatMap(function (McqRegistration $r) {
                $answers = $r->draft_answers ?? [];

                return collect($answers)->map(fn ($ans, $qId) => [
                    'exam' => $r->exam?->title, 'question' => (string) $qId,
                    'attempts' => 1, 'correct_pct' => '—',
                ]);
            })->groupBy('question')->map(fn ($group, $q) => [
                'exam' => $group->first()['exam'], 'question' => $q,
                'attempts' => $group->count(), 'correct_pct' => '—',
            ])->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqAbsentIncomplete(string $sahodayaId, array $filters): Collection
    {
        return $this->mcqRegistrationsQuery($sahodayaId, $filters)
            ->filter(fn (McqRegistration $r) => $r->attendance_status !== 'present' || ! $r->submitted_at)
            ->map(fn (McqRegistration $r) => [
                'exam' => $r->exam?->title, 'student' => $r->student?->name,
                'school' => $r->school?->name,
                'status' => $r->submitted_at ? 'incomplete' : 'absent',
            ])->values();
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptMcqTierCutoffs(string $sahodayaId, array $filters): Collection
    {
        return McqExam::where('tenant_id', $sahodayaId)
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('id', $filters['exam_id']))
            ->withCount('registrations')->get()
            ->map(fn (McqExam $e) => [
                'exam' => $e->title, 'tier' => $e->exam_level ?? '—',
                'cutoff_score' => $e->cutoff_score, 'promoted_count' => count($e->promoted_student_ids ?? []),
            ]);
    }

    protected function rptMcqQuestionBankExport(string $sahodayaId): Collection
    {
        return McqQuestionBank::where('tenant_id', $sahodayaId)->withCount('questions')->get()
            ->map(fn (McqQuestionBank $b) => [
                'bank' => $b->title, 'questions' => $b->questions_count,
                'created_at' => $b->created_at?->format('j M Y'),
            ]);
    }

    protected function rptMcqRegistrationWindow(string $sahodayaId): Collection
    {
        return McqExam::where('tenant_id', $sahodayaId)->withCount('registrations')->orderByDesc('scheduled_at')->get()
            ->map(fn (McqExam $e) => [
                'exam' => $e->title, 'status' => $e->status,
                'registration_opens' => $e->settings_json['registration_open'] ?? '—',
                'registration_closes' => $e->settings_json['registration_close'] ?? '—',
                'registered' => $e->registrations_count,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function mcqRegistrationsQuery(string $sahodayaId, array $filters): Collection
    {
        $examIds = McqExam::where('tenant_id', $sahodayaId)->pluck('id');

        return McqRegistration::whereIn('exam_id', $examIds)
            ->when(! empty($filters['exam_id']), fn ($q) => $q->where('exam_id', $filters['exam_id']))
            ->with(['exam:id,title', 'student:id,name', 'school:id,name'])->get();
    }

    protected function rptTrainingProgramList(string $sahodayaId): Collection
    {
        return TrainingProgram::where('tenant_id', $sahodayaId)->withCount('registrations')->orderBy('title')->get()
            ->map(fn (TrainingProgram $p) => [
                'program' => $p->title, 'status' => $p->status, 'capacity' => $p->max_participants ?? 'Unlimited',
                'enrolled' => $p->registrations_count, 'fee' => (float) ($p->fee_amount ?? 0),
            ]);
    }

    protected function rptTrainingNominationsBySchool(string $sahodayaId): Collection
    {
        $schoolIds = $this->schoolIds($sahodayaId);

        return TrainingRegistration::whereIn('school_id', $schoolIds)
            ->with(['program:id,title', 'school:id,name'])->get()
            ->groupBy(fn (TrainingRegistration $r) => $r->program_id.'|'.$r->school_id)
            ->map(fn ($group) => [
                'program' => $group->first()->program?->title, 'school' => $group->first()->school?->name,
                'nominations' => $group->count(), 'approved' => $group->where('status', 'approved')->count(),
            ])->values();
    }

    protected function rptTrainingFees(string $sahodayaId): Collection
    {
        return TrainingRegistration::whereIn('school_id', $this->schoolIds($sahodayaId))
            ->with(['program:id,title', 'teacher:id,name', 'school:id,name', 'feeReceipt:id,amount,status'])
            ->get()->map(fn (TrainingRegistration $r) => [
                'program' => $r->program?->title, 'school' => $r->school?->name,
                'teacher' => $r->teacher?->name, 'amount' => (float) ($r->feeReceipt?->amount ?? 0),
                'status' => $r->feeReceipt?->status ?? $r->status,
            ]);
    }

    protected function rptTrainingAttendance(string $sahodayaId): Collection
    {
        return TrainingRegistration::whereIn('school_id', $this->schoolIds($sahodayaId))
            ->where('status', 'approved')->with(['program:id,title', 'teacher:id,name', 'school:id,name'])
            ->get()->map(fn (TrainingRegistration $r) => [
                'program' => $r->program?->title, 'teacher' => $r->teacher?->name,
                'school' => $r->school?->name, 'attendance_status' => $r->status,
            ]);
    }

    protected function rptTrainingCertificates(string $sahodayaId): Collection
    {
        return TrainingRegistration::whereIn('school_id', $this->schoolIds($sahodayaId))
            ->whereHas('certificate')->with(['program:id,title', 'teacher:id,name', 'school:id,name', 'certificate'])
            ->get()->map(fn (TrainingRegistration $r) => [
                'program' => $r->program?->title, 'teacher' => $r->teacher?->name,
                'school' => $r->school?->name,
                'certificate_issued_at' => $r->certificate?->generated_at?->format('j M Y'),
            ]);
    }

    protected function rptTrainingFeedbackSummary(string $sahodayaId): Collection
    {
        return TrainingProgram::where('tenant_id', $sahodayaId)->withCount('registrations')->get()
            ->map(fn (TrainingProgram $p) => [
                'program' => $p->title, 'registrations' => $p->registrations_count,
                'completed' => TrainingRegistration::where('program_id', $p->id)->where('status', 'completed')->count(),
                'completion_pct' => $p->registrations_count > 0
                    ? round(TrainingRegistration::where('program_id', $p->id)->where('status', 'completed')->count() / $p->registrations_count * 100, 1).'%'
                    : '—',
            ]);
    }

    protected function rptTrainingNominationQueue(string $sahodayaId): Collection
    {
        return TrainingRegistration::whereIn('school_id', $this->schoolIds($sahodayaId))
            ->whereIn('status', ['submitted', 'pending'])->with(['program:id,title', 'teacher:id,name', 'school:id,name'])
            ->orderByDesc('created_at')->get()
            ->map(fn (TrainingRegistration $r) => [
                'program' => $r->program?->title, 'teacher' => $r->teacher?->name,
                'school' => $r->school?->name, 'status' => $r->status,
                'submitted_at' => $r->created_at?->format('j M Y'),
            ]);
    }

    protected function rptTrainingResourcePersons(string $sahodayaId): Collection
    {
        return TrainingProgram::where('tenant_id', $sahodayaId)->with('sessions')->get()
            ->flatMap(fn (TrainingProgram $p) => $p->sessions->map(fn (TrainingSession $s) => [
                'program' => $p->title, 'resource_person' => $s->title,
                'sessions' => 1, 'status' => $p->status,
            ]));
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptEmailDeliveryLog(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return NotificationLog::whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (NotificationLog $l) => [
                'recipient' => $l->to ?? '—', 'template_key' => $l->template_key ?? 'direct',
                'status' => $l->status, 'sent_at' => $l->sent_at?->toDateTimeString() ?? $l->created_at?->toDateTimeString(),
                'error' => $l->error,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptFailedEmails(array $filters): Collection
    {
        return NotificationLog::where('status', 'failed')->orderByDesc('created_at')->limit(200)->get()
            ->map(fn (NotificationLog $l) => [
                'recipient' => $l->to ?? '—', 'template_key' => $l->template_key ?? 'direct',
                'failed_at' => $l->created_at?->toDateTimeString(), 'error' => $l->error,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptAuditTrail(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return AuditLog::whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (AuditLog $l) => [
                'action' => $l->action, 'description' => $l->description,
                'user' => $l->properties['user'] ?? '—', 'created_at' => $l->created_at?->toDateTimeString(),
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptAuthEventsSummary(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return AuditLog::where('category', 'auth')->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (AuditLog $l) => [
                'action' => $l->action,
                'username' => $l->properties['username'] ?? $l->properties['email'] ?? '—',
                'ip_address' => $l->ip_address, 'created_at' => $l->created_at?->toDateTimeString(),
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptFinanceAudit(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return AuditLog::where('category', 'finance')->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (AuditLog $l) => [
                'action' => $l->action, 'description' => $l->description,
                'amount' => $l->properties['amount'] ?? '—', 'created_at' => $l->created_at?->toDateTimeString(),
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptExportActivityLog(array $filters): Collection
    {
        $from = $filters['from'] ?? now()->subDays(30)->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        return AuditLog::where('action', 'report.downloaded')->whereBetween('created_at', [$from, $to.' 23:59:59'])
            ->orderByDesc('created_at')->limit(500)->get()
            ->map(fn (AuditLog $l) => [
                'report' => $l->properties['report'] ?? '—',
                'user' => $l->properties['user'] ?? '—',
                'filters' => json_encode($l->properties['filters'] ?? []),
                'downloaded_at' => $l->created_at?->toDateTimeString(),
            ]);
    }

    protected function rptDocumentReviewQueue(string $sahodayaId): Collection
    {
        return SchoolDocument::where('status', 'pending')
            ->whereHas('documentType', fn ($q) => $q->where('sahodaya_id', $sahodayaId))
            ->with(['school:id,name', 'documentType:id,name'])->orderByDesc('created_at')->get()
            ->map(fn (SchoolDocument $d) => [
                'school' => $d->school?->name, 'document_type' => $d->documentType?->name,
                'status' => $d->status, 'submitted_at' => $d->created_at?->format('j M Y'),
            ]);
    }

    protected function rptDocumentTypeConfig(string $sahodayaId): Collection
    {
        return SchoolDocumentType::where('sahodaya_id', $sahodayaId)->orderBy('name')->get()
            ->map(fn (SchoolDocumentType $t) => [
                'name' => $t->name, 'required' => $t->is_required ? 'Yes' : 'No',
                'active' => $t->is_active ? 'Yes' : 'No', 'validity_days' => $t->validity_days,
            ]);
    }

    /** @param  array<string, mixed>  $filters */
    protected function rptAggregatedCalendar(string $sahodayaId, array $filters): Collection
    {
        $from = $filters['from'] ?? now()->toDateString();
        $to = $filters['to'] ?? now()->addMonths(3)->toDateString();
        $rows = collect();

        FestEvent::where('tenant_id', $sahodayaId)->whereBetween('event_start', [$from, $to])->get()
            ->each(fn (FestEvent $e) => $rows->push([
                'program' => 'Fest', 'title' => $e->title,
                'starts' => $e->event_start?->toDateString(), 'ends' => $e->event_end?->toDateString(), 'status' => $e->status,
            ]));

        McqExam::where('tenant_id', $sahodayaId)->whereBetween('scheduled_at', [$from, $to.' 23:59:59'])->get()
            ->each(fn (McqExam $e) => $rows->push([
                'program' => 'Talent Search', 'title' => $e->title,
                'starts' => $e->scheduled_at?->toDateString(), 'ends' => $e->scheduled_at?->toDateString(), 'status' => $e->status,
            ]));

        TrainingProgram::where('tenant_id', $sahodayaId)->get()->each(fn (TrainingProgram $p) => $rows->push([
            'program' => 'Training', 'title' => $p->title,
            'starts' => $p->registration_open?->toDateString(), 'ends' => $p->registration_close?->toDateString(), 'status' => $p->status,
        ]));

        return $rows->sortBy('starts')->values();
    }

    protected function rptMembershipWindows(string $sahodayaId): Collection
    {
        return SahodayaRegistrationWindow::where('sahodaya_id', $sahodayaId)->orderByDesc('registration_starts_at')->get()
            ->map(fn (SahodayaRegistrationWindow $w) => [
                'program' => 'Membership', 'opens' => $w->registration_starts_at?->toDateString(),
                'closes' => $w->registration_ends_at?->toDateString(),
                'status' => ($w->registration_ends_at && $w->registration_ends_at->isFuture()) ? 'active' : 'closed',
            ]);
    }

    protected function rptFestEventDates(string $sahodayaId): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)->orderBy('event_start')->get()
            ->map(fn (FestEvent $e) => [
                'event' => $e->title, 'type' => $e->event_type,
                'starts' => $e->event_start?->toDateString(), 'registration_closes' => $e->registration_close?->toDateString(),
            ]);
    }

    protected function rptMcqExamSchedule(string $sahodayaId): Collection
    {
        return McqExam::where('tenant_id', $sahodayaId)->orderBy('scheduled_at')->get()
            ->map(fn (McqExam $e) => [
                'exam' => $e->title, 'scheduled_at' => $e->scheduled_at?->toDateTimeString(),
                'venue' => $e->venue, 'status' => $e->status,
            ]);
    }

    protected function rptSchoolDashboardExport(string $sahodayaId): Collection
    {
        return Tenant::where('parent_id', $sahodayaId)->where('type', 'school')->where('membership_status', 'approved')
            ->orderBy('name')->get()->map(function (Tenant $school) use ($sahodayaId) {
                $pendingDocs = SchoolDocument::where('school_id', $school->id)->where('status', 'pending')
                    ->whereHas('documentType', fn ($q) => $q->where('sahodaya_id', $sahodayaId))->count();
                $year = AcademicYear::forSahodaya($sahodayaId);
                $payment = MembershipPayment::where('school_id', $school->id)->where('academic_year', $year)->latest()->first();

                return [
                    'school' => $school->name,
                    'students' => Student::where('tenant_id', $school->id)->where('status', 'active')->count(),
                    'teachers' => Teacher::where('tenant_id', $school->id)->where('status', 'active')->count(),
                    'documents_pending' => $pendingDocs,
                    'payment_status' => $payment?->status ?? 'none',
                ];
            });
    }

    protected function rptEventOpsBrief(string $sahodayaId): Collection
    {
        return FestEvent::where('tenant_id', $sahodayaId)->whereIn('status', ['active', 'published', 'ongoing'])
            ->withCount('registrations')->get()
            ->map(fn (FestEvent $e) => [
                'event' => $e->title, 'status' => $e->status,
                'registrations_today' => FestRegistration::where('event_id', $e->id)->whereDate('created_at', today())->count(),
                'pending_marks' => FestEventItem::where('event_id', $e->id)->whereNull('results_published_at')->count(),
                'pending_payments' => FestSchoolEventFee::where('event_id', $e->id)->whereNotIn('status', ['approved', 'waived'])->count(),
            ]);
    }
}
