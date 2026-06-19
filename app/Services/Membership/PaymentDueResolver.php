<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use App\Models\Registration;
use App\Models\Tenant;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PaymentDueResolver
{
    public function __construct(private MembershipFeeCalculator $feeCalculator) {}

    /** @return Collection<int, array<string, mixed>> */
    public function items(string $sahodayaId, array $schoolIds, string $academicYear, array $filters = []): Collection
    {
        $items = collect();

        $registrations = Registration::query()
            ->whereIn('school_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->whereIn('registration_status', ['payment_pending', 'payment_rejected'])
            ->whereDoesntHave('payments', fn ($q) => $q->where('status', 'submitted'))
            ->with('school:id,name,school_prefix,membership_status,parent_id,created_at')
            ->orderByDesc('updated_at')
            ->get();

        foreach ($registrations as $registration) {
            $school = $registration->school;
            if (! $school) {
                continue;
            }

            $items->push($this->mapRegistration($registration, $school, $academicYear));
        }

        $coveredSchoolIds = $items->pluck('school_id')->all();

        $pendingSchools = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->where('membership_status', 'pending')
            ->whereNotIn('id', $coveredSchoolIds)
            ->orderBy('name')
            ->get();

        foreach ($pendingSchools as $school) {
            if ($this->schoolHasSubmittedPayment($school->id, $academicYear)) {
                continue;
            }

            $items->push($this->mapPendingSchool($school, $academicYear));
        }

        return $this->filterItems($items, $filters)->values();
    }

    public function count(string $sahodayaId, array $schoolIds, string $academicYear): int
    {
        return $this->items($sahodayaId, $schoolIds, $academicYear)->count();
    }

    public function totalAmount(string $sahodayaId, array $schoolIds, string $academicYear): float
    {
        return (float) $this->items($sahodayaId, $schoolIds, $academicYear)
            ->sum(fn (array $item) => (float) ($item['membership_fee_amount'] ?? 0));
    }

    public function paginate(string $sahodayaId, array $schoolIds, string $academicYear, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $items = $this->items($sahodayaId, $schoolIds, $academicYear, $filters);
        $page = max(1, (int) request()->query('page', 1));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()],
        );
    }

    private function schoolHasSubmittedPayment(string $schoolId, string $academicYear): bool
    {
        return MembershipPayment::query()
            ->where('school_id', $schoolId)
            ->where('academic_year', $academicYear)
            ->where('status', 'submitted')
            ->exists();
    }

    private function mapRegistration(Registration $registration, Tenant $school, string $academicYear): array
    {
        $amount = $registration->membership_fee_amount !== null
            ? (float) $registration->membership_fee_amount
            : $this->feeCalculator->estimateFeeForSchool($school, $academicYear);

        return [
            'id'                    => $registration->id,
            'school_id'             => $registration->school_id,
            'academic_year'         => $registration->academic_year,
            'reg_no'                => $registration->reg_no,
            'registration_status'   => $registration->registration_status,
            'membership_fee_amount' => $amount,
            'source'                => 'registration',
            'updated_at'            => $registration->updated_at?->toIso8601String(),
            'school'                => [
                'id'                => $school->id,
                'name'              => $school->name,
                'school_prefix'     => $school->school_prefix,
                'membership_status' => $school->membership_status,
            ],
        ];
    }

    private function mapPendingSchool(Tenant $school, string $academicYear): array
    {
        return [
            'id'                    => null,
            'school_id'             => $school->id,
            'academic_year'         => $academicYear,
            'reg_no'                => null,
            'registration_status'   => 'payment_pending',
            'membership_fee_amount' => $this->feeCalculator->estimateFeeForSchool($school, $academicYear),
            'source'                => 'pending_membership',
            'updated_at'            => $school->created_at?->toIso8601String(),
            'school'                => [
                'id'                => $school->id,
                'name'              => $school->name,
                'school_prefix'     => $school->school_prefix,
                'membership_status' => $school->membership_status,
            ],
        ];
    }

    /** @param Collection<int, array<string, mixed>> $items */
    private function filterItems(Collection $items, array $filters): Collection
    {
        if (($filters['search'] ?? '') !== '') {
            $search = strtolower($filters['search']);
            $items = $items->filter(function (array $item) use ($search) {
                $school = $item['school'] ?? [];

                return str_contains(strtolower($school['name'] ?? ''), $search)
                    || str_contains(strtolower($school['school_prefix'] ?? ''), $search);
            });
        }

        if (! empty($filters['date_from'])) {
            $items = $items->filter(fn (array $item) => $item['updated_at']
                && substr($item['updated_at'], 0, 10) >= $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $items = $items->filter(fn (array $item) => $item['updated_at']
                && substr($item['updated_at'], 0, 10) <= $filters['date_to']);
        }

        return $items;
    }
}
