<?php

namespace App\Services\Membership;

use App\Models\MembershipPayment;
use App\Models\Tenant;
use App\Services\Audit\DataChangeLogger;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SchoolMembershipCancellationService
{
    /**
     * Approved schools that never uploaded a membership payment (submitted/verified).
     *
     * @param  list<string>|\Illuminate\Support\Collection<int, string>  $schoolIds
     * @return list<string>
     */
    public function approvedWithoutPaymentIds(iterable $schoolIds): array
    {
        $ids = collect($schoolIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $approved = Tenant::query()
            ->whereIn('id', $ids)
            ->where('type', 'school')
            ->where('membership_status', 'approved')
            ->pluck('id');

        if ($approved->isEmpty()) {
            return [];
        }

        $paid = MembershipPayment::query()
            ->whereIn('school_id', $approved)
            ->whereIn('status', ['submitted', 'verified'])
            ->distinct()
            ->pluck('school_id');

        return $approved->diff($paid)->values()->all();
    }

    public function canCancel(Tenant $school): bool
    {
        if ($school->type !== 'school' || $school->membership_status !== 'approved') {
            return false;
        }

        return ! MembershipPayment::query()
            ->where('school_id', $school->id)
            ->whereIn('status', ['submitted', 'verified'])
            ->exists();
    }

    public function cancel(
        Tenant $school,
        string $reason,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
        ?int $reviewerId = null,
    ): void {
        if (! $this->canCancel($school)) {
            throw ValidationException::withMessages([
                'school' => 'Membership can only be cancelled for approved schools with no submitted or verified payment.',
            ]);
        }

        $before = $school->membership_status;

        $school->update([
            'membership_status'   => 'rejected',
            'is_active'           => false,
            'application_payload' => array_merge($school->application_payload ?? [], [
                'cancellation_reason' => $reason,
                'cancelled_at'        => now()->toIso8601String(),
                'cancelled_by'        => $reviewerId,
                'rejection_reason'    => $reason,
            ]),
        ]);

        app(DataChangeLogger::class)->updated(
            $school,
            "School membership cancelled: {$school->name}",
            ['membership_status' => ['old' => $before, 'new' => 'rejected']],
            $school->id,
            'membership',
        );

        $notifier->schoolRejected($school, $reason);

        $audit->log(
            'membership.school.cancelled',
            "Membership cancelled (no payment): {$school->name}",
            $school,
            ['reviewer_id' => $reviewerId, 'reason' => $reason],
        );
    }

    /**
     * @param  Collection<int, Tenant>  $schools
     */
    public function cancelMany(
        Collection $schools,
        string $reason,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
        ?int $reviewerId = null,
    ): int {
        $count = 0;

        foreach ($schools as $school) {
            if (! $this->canCancel($school)) {
                continue;
            }

            $this->cancel($school, $reason, $notifier, $audit, $reviewerId);
            $count++;
        }

        return $count;
    }
}
