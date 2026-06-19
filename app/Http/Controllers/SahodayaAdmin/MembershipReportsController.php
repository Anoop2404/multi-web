<?php

namespace App\Http\Controllers\SahodayaAdmin;

use App\Http\Controllers\SahodayaAdmin\Concerns\BuildsMembershipExports;
use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\SchoolClass;
use App\Models\SchoolYearSubmission;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\Membership\EffectiveMasterDataResolver;
use App\Support\AcademicYear;
use App\Support\ExcelExport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MembershipReportsController extends SahodayaAdminController
{
    use BuildsMembershipExports;

    public function index(Request $request, EffectiveMasterDataResolver $resolver)
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $tab = $request->query('tab', 'schools');
        $search = trim($request->query('search', ''));
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');

        $summary = $this->buildSummary($schoolIds, $year);

        $schools = null;
        $paymentsPending = null;
        $paymentsDone = null;
        $paymentDue = null;

        if ($tab === 'schools') {
            $schools = $this->paginatedSchoolsReport($schoolIds, $year, $search, $dateFrom, $dateTo);
        } elseif ($tab === 'payment-due') {
            $paymentDue = $this->paginatedUnpaidRegistrations($schoolIds, $year, $search, $dateFrom, $dateTo);
        } elseif ($tab === 'payments-pending') {
            $paymentsPending = $this->paginatedPayments($schoolIds, 'submitted', $search, $dateFrom, $dateTo);
        } elseif ($tab === 'payments-done') {
            $paymentsDone = $this->paginatedPayments($schoolIds, 'verified', $search, $dateFrom, $dateTo);
        }

        return $this->inertia('Sahodaya/Membership/Reports', [
            'academicYear'    => $year,
            'tab'             => $tab,
            'search'          => $search,
            'dateFrom'        => $dateFrom,
            'dateTo'          => $dateTo,
            'summary'         => $summary,
            'schools'         => $schools,
            'paymentDue'      => $paymentDue,
            'paymentsPending' => $paymentsPending,
            'paymentsDone'    => $paymentsDone,
            'classMaster'     => $resolver->classCategories($this->sahodaya->id)->values(),
            'categoryBreakdown' => $this->categoryBreakdown($schoolIds, $resolver),
        ]);
    }

    public function exportSchools(Request $request): StreamedResponse
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $filters = $this->schoolListFilters($request);
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');

        $schools = $this->allSchoolsQuery($this->sahodaya->id, $filters)->get();

        $submissionsBySchool = SchoolYearSubmission::whereIn('school_id', $schoolIds)
            ->where('academic_year', $year)
            ->with(['counts', 'students'])
            ->get()
            ->keyBy('school_id');

        $classCounts = $this->classCountsFor($schoolIds);
        $activeStudentCounts = $this->activeStudentCountsFor($schoolIds);
        $paymentStatuses = $this->schoolPaymentStatusResolver()->forSchools(
            $this->sahodaya->id,
            $schoolIds->all(),
            $year,
        );

        $rows = $schools->map(function (Tenant $school) use ($submissionsBySchool, $classCounts, $activeStudentCounts, $paymentStatuses) {
            $row = $this->mapSchoolReportRow(
                $school,
                $submissionsBySchool->get($school->id),
                (int) ($classCounts[$school->id] ?? 0),
                (int) ($activeStudentCounts[$school->id] ?? 0),
                $paymentStatuses[$school->id] ?? null,
            );

            return [
                $row['name'],
                $row['membership_status'],
                $row['payment_status_label'] ?? '—',
                $row['payment_amount'] ?? '',
                $row['school_prefix'] ?? '',
                $row['student_count'],
                $row['classes_count'],
                $school->created_at?->format('Y-m-d') ?? '',
            ];
        });

        return ExcelExport::download('membership-schools', [
            'School', 'Membership Status', 'Payment Status', 'Amount', 'Prefix', 'Students', 'Classes', 'Joined',
        ], $rows);
    }

    public function exportPaymentsPending(Request $request): StreamedResponse
    {
        return $this->exportPayments($request, 'submitted', 'membership-payments-pending');
    }

    public function exportPaymentsDone(Request $request): StreamedResponse
    {
        return $this->exportPayments($request, 'verified', 'membership-payments-done');
    }

    public function exportSubmissions(): StreamedResponse
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->pluck('id');

        $rows = SchoolYearSubmission::whereIn('school_id', $schoolIds)
            ->where('academic_year', $year)
            ->with('school:id,name')
            ->orderBy('school_id')
            ->get()
            ->map(fn ($s) => [
                $s->school?->name ?? '',
                $s->academic_year,
                $s->full_records_status,
                $s->counts_status,
                $s->teacher_status,
            ]);

        return ExcelExport::download('membership-submissions-'.$year, [
            'School', 'Year', 'Records', 'Counts', 'Teachers',
        ], $rows);
    }

    public function exportPaymentDue(Request $request): StreamedResponse
    {
        $year = AcademicYear::forSahodaya($this->sahodaya->id);
        $filters = $this->paymentListFilters($request);
        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->where('type', 'school')->pluck('id');
        $items = $this->paymentDueResolver()->items($this->sahodaya->id, $schoolIds->all(), $year, $filters);

        $rows = $items->map(fn (array $item) => [
            $item['school']['name'] ?? '',
            $item['school']['school_prefix'] ?? '',
            $item['academic_year'],
            $item['reg_no'] ?? '',
            $item['registration_status'],
            $item['membership_fee_amount'],
            isset($item['updated_at']) ? date('Y-m-d H:i', strtotime($item['updated_at'])) : '',
        ]);

        return ExcelExport::download('membership-payment-due-'.$year, [
            'School', 'Code', 'Year', 'Reg No', 'Status', 'Fee Due', 'Updated',
        ], $rows);
    }

    public function exportPayments(Request $request, ?string $forcedStatus = null, ?string $filename = null): StreamedResponse
    {
        $filters = $this->paymentListFilters($request);
        if ($forcedStatus) {
            $filters['status'] = $forcedStatus;
        }

        $schoolIds = Tenant::where('parent_id', $this->sahodaya->id)->pluck('id');
        $payments = $this->paymentsQuery($schoolIds, $filters)->get();

        $rows = $payments->map(fn (MembershipPayment $p) => [
            $p->school?->name ?? '',
            $p->school?->school_prefix ?? '',
            $p->academic_year,
            $p->amount,
            $p->status,
            $p->payment_method ?? '',
            $p->transaction_ref ?? '',
            $p->created_at?->format('Y-m-d H:i') ?? '',
            $p->verified_at?->format('Y-m-d H:i') ?? '',
            $p->payment_proof_path ?? '',
        ]);

        $name = $filename ?? 'membership-payments-'.$filters['status'];

        return ExcelExport::download($name, [
            'School', 'Code', 'Year', 'Amount', 'Status', 'Method', 'Reference', 'Submitted', 'Verified', 'Proof Path',
        ], $rows);
    }

    private function buildSummary($schoolIds, string $year): array
    {
        $allSchools = Tenant::whereIn('id', $schoolIds)->get(['id', 'membership_status']);

        $fees = $this->paymentFeeSummary($this->sahodaya->id, $schoolIds->all(), $year);
        $paymentSummary = $this->paymentStatusSummary($this->sahodaya->id, $schoolIds->all(), $year);

        return array_merge([
            'total_registered'        => $allSchools->count(),
            'total_schools'           => $allSchools->where('membership_status', 'approved')->count(),
            'pending_schools'         => $allSchools->where('membership_status', 'pending')->count(),
            'rejected_schools'        => $allSchools->where('membership_status', 'rejected')->count(),
            'payments_verified'       => MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'verified')->count(),
            'payments_pending'        => $paymentSummary['payments_pending_verification'],
            'payment_due'             => $paymentSummary['payment_not_done'],
            'pending_amount'          => $fees['pending_amount'],
            'approved_amount'         => $fees['approved_amount'],
            'payment_due_amount'      => $fees['payment_due_amount'],
            'total_collected'         => $fees['approved_amount'],
            'registrations_completed' => Registration::whereIn('school_id', $schoolIds)
                ->where('academic_year', $year)
                ->where('registration_status', 'completed')
                ->count(),
        ], $paymentSummary);
    }

    private function paginatedSchoolsReport($schoolIds, string $year, string $search, ?string $dateFrom, ?string $dateTo)
    {
        $filters = ['search' => $search, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'sort' => 'name', 'dir' => 'asc'];
        $schools = $this->allSchoolsQuery($this->sahodaya->id, $filters)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $pageIds = $schools->pluck('id');

        $submissionsBySchool = SchoolYearSubmission::whereIn('school_id', $pageIds)
            ->where('academic_year', $year)
            ->with(['counts', 'students'])
            ->get()
            ->keyBy('school_id');

        $classCounts = $this->classCountsFor($pageIds);
        $activeStudentCounts = $this->activeStudentCountsFor($pageIds);
        $paymentStatuses = $this->schoolPaymentStatusResolver()->forSchools(
            $this->sahodaya->id,
            $pageIds->all(),
            $year,
        );

        $schools->getCollection()->transform(fn (Tenant $school) => $this->mapSchoolReportRow(
            $school,
            $submissionsBySchool->get($school->id),
            (int) ($classCounts[$school->id] ?? 0),
            (int) ($activeStudentCounts[$school->id] ?? 0),
            $paymentStatuses[$school->id] ?? null,
        ));

        return $schools;
    }

    private function paginatedUnpaidRegistrations($schoolIds, string $year, string $search, ?string $dateFrom, ?string $dateTo)
    {
        $filters = [
            'search'    => $search,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ];

        return $this->paginatedPaymentDue($this->sahodaya->id, $schoolIds->all(), $year, $filters, 20)
            ->withQueryString();
    }

    private function paginatedPayments($schoolIds, string $status, string $search, ?string $dateFrom, ?string $dateTo)
    {
        $filters = [
            'search'    => $search,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'status'    => $status,
        ];

        return $this->paymentsQuery($schoolIds, $filters)->paginate(20)->withQueryString();
    }

    private function categoryBreakdown($schoolIds, EffectiveMasterDataResolver $resolver)
    {
        return $resolver->classCategories($this->sahodaya->id)
            ->map(function ($cat) use ($schoolIds) {
                $total = Student::query()
                    ->whereIn('tenant_id', $schoolIds)
                    ->where('status', 'active')
                    ->whereHas('schoolClass', fn ($q) => $q->where('class_category_id', $cat->id))
                    ->count();

                return [
                    'id'            => $cat->id,
                    'code'          => $cat->code,
                    'label'         => $cat->label,
                    'min_class'     => $cat->min_class,
                    'max_class'     => $cat->max_class,
                    'sahodaya_id'   => $cat->sahodaya_id,
                    'student_count' => $total,
                ];
            })
            ->values();
    }

    private function classCountsFor($schoolIds)
    {
        return SchoolClass::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('is_active', true)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
    }

    private function activeStudentCountsFor($schoolIds)
    {
        return Student::query()
            ->whereIn('tenant_id', $schoolIds)
            ->where('status', 'active')
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');
    }

    private function mapSchoolReportRow(
        Tenant $school,
        ?SchoolYearSubmission $submission,
        int $classesCount,
        int $activeStudentCount,
        ?array $paymentStatus = null,
    ): array {
        return [
            'id'                => $school->id,
            'name'              => $school->name,
            'school_prefix'     => $school->school_prefix,
            'membership_status' => $school->membership_status,
            'payment_status'    => $paymentStatus['status'] ?? 'none',
            'payment_status_label' => $paymentStatus['label'] ?? '—',
            'payment_amount'    => $paymentStatus['amount'] ?? null,
            'created_at'        => $school->created_at,
            'student_count'     => $this->resolveStudentCount($submission, $activeStudentCount),
            'classes_count'     => $classesCount,
        ];
    }

    private function resolveStudentCount(?SchoolYearSubmission $submission, int $activeStudentCount): int
    {
        if ($submission) {
            $fromCounts = (int) $submission->counts->sum('total_count');
            if ($fromCounts > 0) {
                return $fromCounts;
            }

            $fromRecords = $submission->students->count();
            if ($fromRecords > 0) {
                return $fromRecords;
            }
        }

        return $activeStudentCount;
    }
}
