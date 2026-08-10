<?php

namespace App\Services\State;

use App\Models\FestStateProgram;
use App\Models\StateRemittance;
use App\Models\Tenant;

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

        return StateRemittance::updateOrCreate(
            [
                'sahodaya_id'   => $sahodaya->id,
                'academic_year' => $program->academic_year ?? '2026-2027',
                'title'         => "{$program->title} — State Remittance Demand",
            ],
            [
                'amount'           => $totalDemand,
                'status'           => 'pending',
                'source_breakdown' => [
                    'state_program_id'       => $program->id,
                    'accepted_nominees'      => $acceptedNomineeCount,
                    'individual_rate'        => $individualRate,
                    'calculated_at'          => now()->toIso8601String(),
                ],
            ]
        );
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
