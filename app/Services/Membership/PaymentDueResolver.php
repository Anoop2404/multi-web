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
            ->with('school:id,name,school_prefix,membership_status,parent_id,created_at,application_payload')
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

        $unpaidSchools = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->whereIn('membership_status', ['pending', 'approved'])
            ->whereNotIn('id', $coveredSchoolIds)
            ->orderBy('name')
            ->get();

        foreach ($unpaidSchools as $school) {
            if ($this->schoolHasSubmittedOrVerifiedPayment($school->id, $academicYear)) {
                continue;
            }

            $items->push($this->mapPendingSchool($school, $academicYear));
        }

        return $this->filterItems($items, $filters)->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function noProofItems(string $sahodayaId, array $schoolIds, string $academicYear, array $filters = []): Collection
    {
        $approvedSchools = Tenant::query()
            ->where('parent_id', $sahodayaId)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->whereIn('id', $schoolIds)
            ->orderBy('name')
            ->get();

        $items = collect();

        foreach ($approvedSchools as $school) {
            $hasProof = MembershipPayment::query()
                ->where('school_id', $school->id)
                ->where('academic_year', $academicYear)
                ->whereNotNull('payment_proof_path')
                ->where('payment_proof_path', '!=', '')
                ->exists();

            if (! $hasProof) {
                $items->push([
                    'id'                    => null,
                    'school_id'             => $school->id,
                    'academic_year'         => $academicYear,
                    'reg_no'                => null,
                    'registration_status'   => 'approved_no_proof',
                    'membership_fee_amount' => $this->feeCalculator->estimateFeeForSchool($school, $academicYear),
                    'source'                => 'approved_without_proof',
                    'updated_at'            => $school->created_at?->toIso8601String(),
                    'school'                => [
                        'id'                => $school->id,
                        'name'              => $school->name,
                        'school_prefix'     => $school->school_prefix,
                        'membership_status' => $school->membership_status,
                        'admin_note'        => $school->application_payload['admin_note'] ?? null,
                    ],
                ]);
            }
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

    private function schoolHasSubmittedOrVerifiedPayment(string $schoolId, string $academicYear): bool
    {
        return MembershipPayment::query()
            ->where('school_id', $schoolId)
            ->where('academic_year', $academicYear)
            ->whereIn('status', ['submitted', 'verified'])
            ->exists();
    }

    /** @return Collection<int, array<string, mixed>> */
    public function partialItems(string $sahodayaId, array $schoolIds, string $academicYear, array $filters = []): Collection
    {
        $items = collect();

        $registrations = Registration::query()
            ->whereIn('school_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->where('amount_paid', '>', 0)
            ->whereIn('registration_status', ['payment_pending', 'payment_rejected', 'completed', 'approved'])
            ->with([
                'school:id,name,school_prefix,membership_status,parent_id,created_at,application_payload',
                'payments' => fn ($q) => $q->where('status', '!=', 'superseded')->orderByDesc('created_at'),
            ])
            ->orderByDesc('updated_at')
            ->get();

        foreach ($registrations as $registration) {
            $school = $registration->school;
            if (! $school) {
                continue;
            }

            $items->push($this->mapRegistration($registration, $school, $academicYear));
        }

        return $this->filterItems($items, $filters)->values();
    }

    private function mapRegistration(Registration $registration, Tenant $school, string $academicYear): array
    {
        $calculatedFee = $this->feeCalculator->estimateFeeForSchool($school, $academicYear);

        if ($registration->fee_override && isset($registration->fee_override['override_amount'])) {
            $totalFee = (float) $registration->fee_override['override_amount'];
        } elseif ($calculatedFee > 0) {
            $totalFee = $calculatedFee;
            if ((float) ($registration->membership_fee_amount ?? 0) !== $totalFee && empty($registration->fee_override)) {
                $registration->update(['membership_fee_amount' => $totalFee]);
            }
        } else {
            $totalFee = $registration->membership_fee_amount !== null
                ? (float) $registration->membership_fee_amount
                : $calculatedFee;
        }

        $amountPaid = (float) ($registration->amount_paid ?? 0);
        $dueAmount = max(0.0, round($totalFee - $amountPaid, 2));

        $paymentsQuery = $registration->relationLoaded('payments')
            ? $registration->payments
            : MembershipPayment::where('school_id', $school->id)
                ->where('academic_year', $academicYear)
                ->where('status', '!=', 'superseded')
                ->orderByDesc('created_at')
                ->get();

        $schoolPayments = $paymentsQuery->map(fn (MembershipPayment $p) => [
            'id'              => $p->id,
            'amount'          => (float) $p->amount,
            'status'          => $p->status,
            'payment_method'  => $p->payment_method,
            'transaction_ref' => $p->transaction_ref,
            'created_at'      => $p->created_at?->toIso8601String(),
            'proof_url'       => $p->proof_url,
            'rejection_reason'=> $p->rejection_reason,
        ])->values()->all();

        return [
            'id'                    => $registration->id,
            'school_id'             => $registration->school_id,
            'academic_year'         => $registration->academic_year,
            'reg_no'                => $registration->reg_no,
            'registration_status'   => $registration->registration_status,
            'membership_fee_amount' => $dueAmount,
            'total_fee_amount'      => $totalFee,
            'amount_paid'           => $amountPaid,
            'school_payments'       => $schoolPayments,
            'source'                => 'registration',
            'updated_at'            => $registration->updated_at?->toIso8601String(),
            'school'                => [
                'id'                => $school->id,
                'name'              => $school->name,
                'school_prefix'     => $school->school_prefix,
                'membership_status' => $school->membership_status,
                'admin_note'        => $school->application_payload['admin_note'] ?? null,
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
                'admin_note'        => $school->application_payload['admin_note'] ?? null,
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
