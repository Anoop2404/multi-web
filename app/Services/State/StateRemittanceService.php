<?php

namespace App\Services\State;

use App\Models\FestStateProgram;
use App\Models\FestStateProgramItem;
use App\Models\StateRemittance;
use App\Models\StateRemittanceLine;
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
        // Blank means ₹0, not a silent ₹500/nominee charge nobody configured — same policy
        // as calculateDemandFromApprovedQualifiers() below, and the sports composite fee
        // fix in FestSportsCompositeFeeService::resolveSportsFeeSource().
        $individualRate = (float) ($stateFees['individual_amount'] ?? 0);

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

    /**
     * Calculates the itemized State remittance demand for a Sahodaya: a flat
     * base/registration fee (level_fees['state']['sahodaya_registration_fee'])
     * plus each of that Sahodaya's approved entries billed at its own item's
     * fee_amount, recorded as StateRemittanceLine rows so reports can slice by
     * item/Sahodaya/status.
     */
    public function calculateDemandFromApprovedQualifiers(FestStateProgram $program, Tenant $sahodaya): StateRemittance
    {
        $remittance = StateRemittance::firstOrNew([
            'sahodaya_id'   => $sahodaya->id,
            'academic_year' => $program->academic_year ?? '2026-2027',
            'title'         => "{$program->title} — State Remittance Demand",
        ]);

        // Same idempotency guard as calculateDemand(): once a Sahodaya has
        // submitted or had a remittance verified, recomputing must not silently
        // rewrite the amount or the itemized lines it already paid against.
        if ($remittance->exists && in_array($remittance->status, ['submitted', 'verified'], true)) {
            return $remittance;
        }

        $baseFee = (float) ($program->level_fees['state']['sahodaya_registration_fee'] ?? 0);

        $itemCounts = StateQualifierEntry::where('status', 'approved')
            ->whereHas('intake', fn ($query) => $query
                ->where('state_program_id', $program->id)
                ->where('source_tenant_id', $sahodaya->id))
            ->whereNotNull('item_id')
            ->selectRaw('item_id, item_code, item_name, count(*) as cnt')
            ->groupBy('item_id', 'item_code', 'item_name')
            ->get();

        $itemFeeAmounts = FestStateProgramItem::whereIn('id', $itemCounts->pluck('item_id')->filter())
            ->pluck('fee_amount', 'id');

        $lines = [];
        if ($baseFee > 0) {
            $lines[] = [
                'line_type' => 'sahodaya_registration',
                'label' => 'Sahodaya registration fee',
                'quantity' => 1,
                'unit_amount' => $baseFee,
                'amount' => $baseFee,
            ];
        }

        $itemTotal = 0.0;
        foreach ($itemCounts as $row) {
            $rate = (float) ($itemFeeAmounts[$row->item_id] ?? 0);
            $amount = round($rate * $row->cnt, 2);
            $itemTotal += $amount;
            $lines[] = [
                'line_type' => 'item_fee',
                'state_program_item_id' => $row->item_id,
                'item_code' => $row->item_code,
                'label' => ($row->item_name ?: $row->item_code)." — {$row->cnt} × ₹".number_format($rate, 2),
                'quantity' => $row->cnt,
                'unit_amount' => $rate,
                'amount' => $amount,
            ];
        }

        $totalDemand = round($baseFee + $itemTotal, 2);

        $remittance->fill([
            'amount'           => $totalDemand,
            'status'           => 'pending',
            'source_breakdown' => [
                'state_program_id'  => $program->id,
                'base_fee'          => $baseFee,
                'accepted_nominees' => $itemCounts->sum('cnt'),
                'calculated_at'     => now()->toIso8601String(),
            ],
        ])->save();

        $this->syncLines($remittance, $lines);

        return $remittance->fresh('lines');
    }

    /** @param list<array<string, mixed>> $lines */
    private function syncLines(StateRemittance $remittance, array $lines): void
    {
        $remittance->lines()->delete();
        foreach ($lines as $line) {
            StateRemittanceLine::create(array_merge(['state_remittance_id' => $remittance->id], $line));
        }
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
