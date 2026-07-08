<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use App\Services\Membership\PaymentDueResolver;
use App\Services\Membership\SchoolPaymentStatusResolver;
use App\Support\TenancyDatabase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait BuildsMembershipExports
{
    /** @return array{search: string, date_from: ?string, date_to: ?string, sort: string, dir: string} */
    protected function schoolListFilters(Request $request): array
    {
        return [
            'search'    => trim($request->query('search', '')),
            'date_from' => $request->query('date_from'),
            'date_to'   => $request->query('date_to'),
            'sort'      => $request->query('sort', 'name'),
            'dir'       => $request->query('dir', 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }

    /** @return array{search: string, date_from: ?string, date_to: ?string, status: string} */
    protected function paymentListFilters(Request $request): array
    {
        $status = $request->query('status', 'submitted');

        return [
            'search'    => trim($request->query('search', '')),
            'date_from' => $request->query('date_from'),
            'date_to'   => $request->query('date_to'),
            'status'    => in_array($status, ['submitted', 'verified', 'rejected', 'all', 'payment-due'], true) ? $status : 'submitted',
        ];
    }

    protected function verifiedSchoolsQuery(string $sahodayaId, array $filters): Builder
    {
        $sortColumn = match ($filters['sort'] ?? 'name') {
            'school_prefix', 'created_at' => $filters['sort'],
            default                       => 'name',
        };

        $query = Tenant::where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->orderBy($sortColumn, $filters['dir'] ?? 'asc');

        $this->applySchoolSearchAndDates($query, $filters);

        return $query;
    }

    protected function allSchoolsQuery(string $sahodayaId, array $filters): Builder
    {
        $query = Tenant::where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->orderBy('name');

        $this->applySchoolSearchAndDates($query, $filters);

        return $query;
    }

    protected function paymentsQuery(string $sahodayaId, array $schoolIds, array $filters): Builder
    {
        if ($filters['search'] !== '') {
            $matchingIds = TenancyDatabase::schoolIdsMatchingSearch($sahodayaId, $filters['search']);
            $schoolIds = array_values(array_intersect($schoolIds, $matchingIds));

            if ($schoolIds === []) {
                return MembershipPayment::query()->whereRaw('0 = 1');
            }
        }

        $query = MembershipPayment::whereIn('school_id', $schoolIds)
            ->where('status', '!=', 'superseded')
            ->with('school:id,name,school_prefix,parent_id')
            ->orderByDesc('created_at');

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function paymentDueResolver(): PaymentDueResolver
    {
        return app(PaymentDueResolver::class);
    }

    protected function schoolPaymentStatusResolver(): SchoolPaymentStatusResolver
    {
        return app(SchoolPaymentStatusResolver::class);
    }

    /** @return array{payment_not_done: int, payment_not_done_amount: float, payments_pending_verification: int, payments_pending_verification_amount: float} */
    protected function paymentStatusSummary(string $sahodayaId, array $schoolIds, string $academicYear): array
    {
        $fees = $this->paymentFeeSummary($sahodayaId, $schoolIds, $academicYear);
        $pendingCount = MembershipPayment::whereIn('school_id', $schoolIds)->where('status', 'submitted')->count();

        return [
            'payment_not_done'                      => $this->unpaidRegistrationsCount($sahodayaId, $schoolIds, $academicYear),
            'payment_not_done_amount'               => $fees['payment_due_amount'],
            'payments_pending_verification'         => $pendingCount,
            'payments_pending_verification_amount'  => $fees['pending_amount'],
        ];
    }

    protected function attachSchoolPaymentStatuses($schools, string $sahodayaId, string $academicYear): void
    {
        $ids = $schools->pluck('id')->all();
        $statuses = $this->schoolPaymentStatusResolver()->forSchools($sahodayaId, $ids, $academicYear);

        $schools->transform(function ($school) use ($statuses) {
            $entry = $statuses[$school->id] ?? ['status' => 'none', 'label' => '—', 'amount' => null];
            $school->setAttribute('payment_status', $entry['status']);
            $school->setAttribute('payment_status_label', $entry['label']);
            $school->setAttribute('payment_amount', $entry['amount']);

            return $school;
        });
    }

    protected function unpaidRegistrationsCount(string $sahodayaId, array $schoolIds, string $academicYear): int
    {
        return $this->paymentDueResolver()->count($sahodayaId, $schoolIds, $academicYear);
    }

    protected function paginatedPaymentDue(string $sahodayaId, array $schoolIds, string $academicYear, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->paymentDueResolver()->paginate($sahodayaId, $schoolIds, $academicYear, $filters, $perPage);
    }

    /** @return array{pending_amount: float, approved_amount: float, rejected_amount: float, payment_due_amount: float} */
    protected function paymentFeeSummary(string $sahodayaId, array $schoolIds, string $academicYear): array
    {
        $base = MembershipPayment::whereIn('school_id', $schoolIds);
        $resolver = $this->paymentDueResolver();

        return [
            'pending_amount'     => (float) (clone $base)->where('status', 'submitted')->sum('amount'),
            'approved_amount'    => (float) (clone $base)->where('status', 'verified')->sum('amount'),
            'rejected_amount'    => (float) (clone $base)->where('status', 'rejected')->sum('amount'),
            'payment_due_amount' => $resolver->totalAmount($sahodayaId, $schoolIds, $academicYear),
        ];
    }

    protected function buildPaymentPageSummary(string $sahodayaId, array $schoolIds, string $academicYear): array
    {
        $base = MembershipPayment::whereIn('school_id', $schoolIds);
        $fees = $this->paymentFeeSummary($sahodayaId, $schoolIds, $academicYear);

        return array_merge($fees, [
            'payment_due' => $this->unpaidRegistrationsCount($sahodayaId, $schoolIds, $academicYear),
            'pending'     => (clone $base)->where('status', 'submitted')->count(),
            'verified'    => (clone $base)->where('status', 'verified')->count(),
            'rejected'    => (clone $base)->where('status', 'rejected')->count(),
            'total'       => (clone $base)->count(),
            'collected'   => $fees['approved_amount'],
        ], $this->paymentStatusSummary($sahodayaId, $schoolIds, $academicYear));
    }

    private function applySchoolSearchAndDates(Builder $query, array $filters): void
    {
        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('school_prefix', 'like', "%{$search}%");
            });
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
    }
}
