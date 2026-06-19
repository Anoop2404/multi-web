<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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

    protected function paymentsQuery(array $schoolIds, array $filters): Builder
    {
        $query = MembershipPayment::whereIn('school_id', $schoolIds)
            ->with('school:id,name,school_prefix,parent_id')
            ->orderByDesc('created_at');

        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('school', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('school_prefix', 'like', "%{$search}%"));
        }

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function unpaidRegistrationsQuery(array $schoolIds, array $filters, string $academicYear): Builder
    {
        $query = Registration::whereIn('school_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->whereIn('registration_status', ['payment_pending', 'payment_rejected'])
            ->with('school:id,name,school_prefix,membership_status,parent_id')
            ->orderByDesc('updated_at');

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->whereHas('school', fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('school_prefix', 'like', "%{$search}%"));
        }

        if ($filters['date_from']) {
            $query->whereDate('updated_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('updated_at', '<=', $filters['date_to']);
        }

        return $query;
    }

    protected function unpaidRegistrationsCount(array $schoolIds, string $academicYear): int
    {
        return $this->unpaidRegistrationsQuery($schoolIds, [
            'search'    => '',
            'date_from' => null,
            'date_to'   => null,
        ], $academicYear)->count();
    }

    /** @return array{pending_amount: float, approved_amount: float, rejected_amount: float, payment_due_amount: float} */
    protected function paymentFeeSummary(array $schoolIds, string $academicYear): array
    {
        $base = MembershipPayment::whereIn('school_id', $schoolIds);

        return [
            'pending_amount'     => (float) (clone $base)->where('status', 'submitted')->sum('amount'),
            'approved_amount'    => (float) (clone $base)->where('status', 'verified')->sum('amount'),
            'rejected_amount'    => (float) (clone $base)->where('status', 'rejected')->sum('amount'),
            'payment_due_amount' => (float) Registration::whereIn('school_id', $schoolIds)
                ->where('academic_year', $academicYear)
                ->whereIn('registration_status', ['payment_pending', 'payment_rejected'])
                ->sum('membership_fee_amount'),
        ];
    }

    protected function buildPaymentPageSummary(array $schoolIds, string $academicYear): array
    {
        $base = MembershipPayment::whereIn('school_id', $schoolIds);
        $fees = $this->paymentFeeSummary($schoolIds, $academicYear);

        return array_merge($fees, [
            'payment_due' => $this->unpaidRegistrationsCount($schoolIds, $academicYear),
            'pending'     => (clone $base)->where('status', 'submitted')->count(),
            'verified'    => (clone $base)->where('status', 'verified')->count(),
            'rejected'    => (clone $base)->where('status', 'rejected')->count(),
            'total'       => (clone $base)->count(),
            'collected'   => $fees['approved_amount'],
        ]);
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
