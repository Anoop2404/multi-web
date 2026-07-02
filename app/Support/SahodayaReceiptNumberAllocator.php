<?php

namespace App\Support;

use App\Models\SahodayaProfile;
use Illuminate\Support\Facades\DB;

class SahodayaReceiptNumberAllocator
{
    /** Allocate the next receipt sequence number atomically for a Sahodaya tenant. */
    public function next(string $sahodayaTenantId): int
    {
        return DB::transaction(function () use ($sahodayaTenantId) {
            $profile = SahodayaProfile::where('tenant_id', $sahodayaTenantId)->lockForUpdate()->first();

            if (! $profile) {
                return 1;
            }

            $number = (int) ($profile->receipt_next_number ?? 1);
            $profile->update(['receipt_next_number' => $number + 1]);

            return $number;
        });
    }
}
