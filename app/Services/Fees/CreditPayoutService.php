<?php

namespace App\Services\Fees;

use App\Models\CreditPayout;
use App\Models\FestFeeCredit;
use App\Models\ProgramFeeCredit;
use App\Models\User;
use App\Services\Audit\PlatformAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditPayoutService
{
    /**
     * Record an out-of-platform bank payout against a FestFeeCredit or ProgramFeeCredit.
     */
    public function recordPayout(
        FestFeeCredit|ProgramFeeCredit $credit,
        User $actor,
        ?string $bankRef = null,
        ?string $notes = null,
    ): CreditPayout {
        if ($credit->applied_at !== null) {
            throw ValidationException::withMessages([
                'credit' => 'This credit has already been applied or closed out.',
            ]);
        }

        return DB::transaction(function () use ($credit, $actor, $bankRef, $notes) {
            /** @var FestFeeCredit|ProgramFeeCredit $locked */
            $locked = get_class($credit)::query()->whereKey($credit->id)->lockForUpdate()->firstOrFail();

            if ($locked->applied_at !== null) {
                throw ValidationException::withMessages([
                    'credit' => 'This credit has already been applied or closed out.',
                ]);
            }

            // ProgramFeeCredit::creditable is McqSchoolFee/TrainingSchoolFee (has its own
            // school_id column) for program fee credits, but Tenant::class directly for a
            // membership cancel-with-settlement credit (SchoolMembershipCancellationService::
            // cancelWithSettlement()) — the school itself IS the creditable there, so
            // ->school_id doesn't exist on it. Without this branch every membership-credit
            // payout silently wrote school_id = '' , making it invisible to every school-
            // scoped query (CreditsReport's own filter included). See
            // docs/FLOW_GAP_FIX_PLAN.md Phase 1.4/4.3.
            $schoolId = match (true) {
                $locked instanceof FestFeeCredit => (string) $locked->schoolEventFee?->school_id,
                $locked instanceof ProgramFeeCredit && $locked->creditable instanceof \App\Models\Tenant => (string) $locked->creditable->id,
                $locked instanceof ProgramFeeCredit => (string) ($locked->creditable?->school_id ?? ''),
                default => '',
            };

            $payout = CreditPayout::create([
                'school_id'           => $schoolId,
                'creditable_type'     => get_class($locked),
                'creditable_id'       => $locked->id,
                'amount'              => $locked->amount,
                'bank_ref'            => $bankRef,
                'notes'               => $notes,
                'recorded_by_user_id' => $actor->id,
            ]);

            $locked->update(['applied_at' => now()]);

            app(PlatformAuditLogger::class)->log(
                action: 'credit.payout_recorded',
                description: "Recorded out-of-platform credit payout of ₹".number_format((float) $locked->amount, 2)." for school #{$schoolId}",
                subject: $payout,
                properties: [
                    'credit_type' => get_class($locked),
                    'credit_id'   => $locked->id,
                    'amount'      => $locked->amount,
                    'bank_ref'    => $bankRef,
                ],
                category: 'finance',
            );

            return $payout;
        });
    }
}
