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

    public function cancelWithSettlement(
        Tenant $school,
        string $reason,
        string $settlement,
        MembershipNotifier $notifier,
        PlatformAuditLogger $audit,
        ?int $reviewerId = null,
    ): void {
        if ($school->type !== 'school' || $school->membership_status !== 'approved') {
            throw ValidationException::withMessages([
                'school' => 'School is not currently an approved member.',
            ]);
        }

        if (!in_array($settlement, ['credit_next_year', 'forfeit'], true)) {
            throw ValidationException::withMessages([
                'settlement' => 'Invalid settlement option.',
            ]);
        }

        $payment = MembershipPayment::query()
            ->where('school_id', $school->id)
            ->whereIn('status', ['submitted', 'verified'])
            ->latest('id')
            ->first();

        if (!$payment) {
            throw ValidationException::withMessages([
                'school' => 'No active payment found to settle. Use the standard cancellation action instead.',
            ]);
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($school, $reason, $settlement, $payment, $notifier, $audit, $reviewerId) {
            if ($settlement === 'credit_next_year') {
                $credit = \App\Models\ProgramFeeCredit::create([
                    'creditable_type' => \App\Models\Tenant::class,
                    'creditable_id'   => $school->id,
                    'source_type'     => MembershipPayment::class,
                    'source_id'       => $payment->id,
                    'amount'          => $payment->amount,
                    'reason'          => 'Membership cancelled: credit toward next year',
                    'created_by_user_id' => $reviewerId ?? auth()->id(),
                ]);

                try {
                    app(\App\Services\Fees\CreditNoteService::class)->issue($credit);
                } catch (\Throwable) {
                    // credit is already recorded; the note can be regenerated later
                }
            }
            // else ($settlement === 'forfeit'): deliberately no ProgramFeeCredit row —
            // per docs/FLOW_GAP_FIX_PLAN.md Phase 0/D4, forfeit means the school explicitly
            // gives up the paid amount. The required $reason is the only record of that
            // decision (captured in the audit log below); "no credit created" is what
            // distinguishes forfeit from credit_next_year at the data layer.

            // $payment->status intentionally stays 'verified' — it genuinely was received
            // and approved, and every other credit-on-cancellation flow in this codebase
            // (fest rejectMany/cancelWithRefund, MCQ/Training syncForSchool) follows the
            // same rule: never rewrite the original approved record, track the backward
            // money movement as a separate, additive credit row instead. Reversing/
            // superseding this payment would also incorrectly suggest the money was never
            // received, which isn't true — it's owed *forward*, not taken back. Per D6,
            // no cash moves in-platform either way.

            $before = $school->membership_status;

            $school->update([
                'membership_status'   => 'rejected',
                'is_active'           => false,
                'application_payload' => array_merge($school->application_payload ?? [], [
                    'cancellation_reason' => $reason,
                    'cancellation_settlement' => $settlement,
                    'cancelled_at'        => now()->toIso8601String(),
                    'cancelled_by'        => $reviewerId,
                    'rejection_reason'    => $reason,
                ]),
            ]);

            app(DataChangeLogger::class)->updated(
                $school,
                "School membership cancelled with settlement ({$settlement}): {$school->name}",
                ['membership_status' => ['old' => $before, 'new' => 'rejected']],
                $school->id,
                'membership',
            );

            // Reuses schoolRejected() rather than a new dedicated template — the school's
            // membership_status is genuinely moving to 'rejected' above, and that email's
            // copy ("application not approved... {{reason}}") reads correctly for this case
            // too. A settlement-specific ("your payment has been credited toward next
            // year"/"forfeited") template would be clearer but is new plumbing (template
            // slug + blade view) not otherwise needed here — noted in
            // docs/FLOW_GAP_FIX_PLAN.md as a possible follow-up, not built speculatively.
            $notifier->schoolRejected($school, $reason);

            $audit->log(
                'membership.school.cancelled_with_settlement',
                "Membership cancelled with {$settlement}: {$school->name}",
                $school,
                ['reviewer_id' => $reviewerId, 'reason' => $reason, 'settlement' => $settlement],
            );
        });
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
