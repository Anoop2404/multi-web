<?php

namespace App\Services\Ledger;

class LateFeeCalculator
{
    public function apply(float $baseAmount, ?string $deadline, ?float $lateFee = null, ?float $penalty = null): float
    {
        if ($baseAmount <= 0 || ! $deadline) {
            return $baseAmount;
        }

        if (now()->toDateString() <= $deadline) {
            return $baseAmount;
        }

        return $baseAmount + (float) ($lateFee ?? 0) + (float) ($penalty ?? 0);
    }
}
