<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use Illuminate\Support\Collection;

class SchoolPaymentStatusResolver
{
    public function __construct(private PaymentDueResolver $paymentDueResolver) {}

    /**
     * @param  list<string>  $schoolIds
     * @return array<string, array{status: string, label: string, amount: ?float}>
     */
    public function forSchools(string $sahodayaId, array $schoolIds, string $academicYear): array
    {
        if ($schoolIds === []) {
            return [];
        }

        $submitted = MembershipPayment::query()
            ->whereIn('school_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->where('status', 'submitted')
            ->get()
            ->keyBy('school_id');

        $notDone = $this->paymentDueResolver
            ->items($sahodayaId, $schoolIds, $academicYear)
            ->keyBy('school_id');

        $verified = MembershipPayment::query()
            ->whereIn('school_id', $schoolIds)
            ->where('academic_year', $academicYear)
            ->where('status', 'verified')
            ->pluck('school_id')
            ->flip();

        $map = [];

        foreach ($schoolIds as $schoolId) {
            if ($submitted->has($schoolId)) {
                $payment = $submitted->get($schoolId);
                $map[$schoolId] = $this->entry('payment_pending', 'Payment pending', (float) $payment->amount);

                continue;
            }

            if ($notDue = $notDone->get($schoolId)) {
                $map[$schoolId] = $this->entry(
                    'payment_not_done',
                    'Payment not done',
                    isset($notDue['membership_fee_amount']) ? (float) $notDue['membership_fee_amount'] : null,
                );

                continue;
            }

            if ($verified->has($schoolId)) {
                $map[$schoolId] = $this->entry('payment_verified', 'Payment verified', null);

                continue;
            }

            $map[$schoolId] = $this->entry('none', '—', null);
        }

        return $map;
    }

    /** @param  list<string>  $schoolIds */
    public function schoolIdsMatching(string $sahodayaId, array $schoolIds, string $academicYear, string $paymentStatus): array
    {
        $map = $this->forSchools($sahodayaId, $schoolIds, $academicYear);

        return collect($map)
            ->filter(fn (array $entry) => $entry['status'] === $paymentStatus)
            ->keys()
            ->values()
            ->all();
    }

    /** @return array{status: string, label: string, amount: ?float} */
    private function entry(string $status, string $label, ?float $amount): array
    {
        return [
            'status' => $status,
            'label'  => $label,
            'amount' => $amount,
        ];
    }
}
