<?php

namespace App\Services\Ledger;

use App\Models\LedgerTransaction;
use App\Models\StateRemittance;
use App\Models\Tenant;
use App\Support\TenancyDatabase;

class StateRemittanceLedgerService
{
    public function postVerified(StateRemittance $remittance, ?int $postedBy = null): ?LedgerTransaction
    {
        if ($remittance->status !== 'verified') {
            return null;
        }

        $sahodaya = Tenant::query()->find($remittance->sahodaya_id);
        if (! $sahodaya) {
            return null;
        }

        return TenancyDatabase::runWhenDatabaseReady($sahodaya, function () use ($remittance, $postedBy, $sahodaya) {
            $rows = app(LedgerPostingService::class)->postExpense(
                $sahodaya->id,
                'STATE-REMITTANCE',
                $remittance->amount,
                "State remittance — {$remittance->title}",
                StateRemittance::class,
                $remittance->id,
                $remittance->payment_date?->format('Y-m-d') ?? now()->toDateString(),
                $postedBy
            );

            return $rows[0] ?? null;
        });
    }
}
