<?php

namespace App\Services\State;

use App\Models\FestStateProgram;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Models\State\StateQualifierEntry;

class StateRemittanceService
{
    /**
     * Calculate consolidated State remittance demand for a Sahodaya based on accepted primary nominees.
     */
    public function calculateDemand(FestStateProgram $program, Tenant $sahodaya, int $acceptedNomineeCount): StateRemittance
    {
        $stateFees = $program->level_fees['state'] ?? [];
        $individualRate = (float) ($stateFees['individual_amount'] ?? 500);

        $totalDemand = $acceptedNomineeCount * $individualRate;

        $remittance = StateRemittance::firstOrNew([
            'sahodaya_id'   => $sahodaya->id,
            'academic_year' => $program->academic_year ?? '2026-2027',
            'title'         => "{$program->title} — State Remittance Demand",
        ]);

        // Never reset a payment that is already under review or verified merely because
        // scrutiny was reopened/replayed. Corrections after submission require an explicit
        // supplemental demand instead of silently rewriting the paid amount.
        if ($remittance->exists && in_array($remittance->status, ['submitted', 'verified'], true)) {
            return $remittance;
        }

        $remittance->fill([
            'amount'           => $totalDemand,
            'status'           => 'pending',
            'source_breakdown' => [
                'state_program_id'       => $program->id,
                'accepted_nominees'      => $acceptedNomineeCount,
                'individual_rate'        => $individualRate,
                'calculated_at'          => now()->toIso8601String(),
            ],
        ])->save();

        return $remittance->fresh();
    }

    public function calculateDemandFromApprovedQualifiers(FestStateProgram $program, Tenant $sahodaya): StateRemittance
    {
        $acceptedNomineeCount = StateQualifierEntry::where('status', 'approved')
            ->whereHas('intake', fn ($query) => $query
                ->where('state_program_id', $program->id)
                ->where('source_tenant_id', $sahodaya->id))
            ->count();

        return $this->calculateDemand($program, $sahodaya, $acceptedNomineeCount);
    }

    /**
     * Verify uploaded payment proof from Sahodaya for State remittance.
     */
    public function verifyProof(StateRemittance $remittance, int $reviewerId, ?string $notes = null): StateRemittance
    {
        $remittance->update([
            'status'      => 'verified',
            'reviewed_by' => $reviewerId,
            'reviewed_at' => now(),
            'description' => $notes ?: 'State remittance payment verified.',
        ]);

        return $remittance->fresh();
    }
}
