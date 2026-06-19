<?php

namespace App\Http\Controllers\SahodayaAdmin\Concerns;

use App\Models\MembershipPayment;
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
            'status'    => in_array($status, ['submitted', 'verified', 'rejected', 'all'], true) ? $status : 'submitted',
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
