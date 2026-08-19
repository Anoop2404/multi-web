<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Membership\MembershipFeeCalculator;
use Illuminate\Console\Command;

class RecalculateMembershipFees extends Command
{
    protected $signature = 'membership:recalculate-fees {--sahodaya= : Optional Sahodaya Tenant ID or prefix}';

    protected $description = 'Recalculates and updates membership fee amounts for all member school registrations based on current class levels and fee settings.';

    public function handle(MembershipFeeCalculator $feeCalculator): int
    {
        $sahodayaOption = $this->option('sahodaya');

        $profiles = SahodayaProfile::query()
            ->when($sahodayaOption, function ($q) use ($sahodayaOption) {
                $q->where('tenant_id', $sahodayaOption)
                  ->orWhereHas('tenant', fn ($t) => $t->where('school_prefix', $sahodayaOption)->orWhere('name', 'like', "%{$sahodayaOption}%"));
            })
            ->get();

        if ($profiles->isEmpty()) {
            $this->warn('No Sahodaya profiles found matching your query.');

            return self::SUCCESS;
        }

        $totalUpdated = 0;

        foreach ($profiles as $profile) {
            $sahodaya = Tenant::find($profile->tenant_id);
            $this->info('Processing Sahodaya: '.($sahodaya->name ?? $profile->tenant_id));

            $registrations = Registration::whereHas('school', fn ($q) => $q->where('parent_id', $profile->tenant_id))
                ->with(['submission', 'school'])
                ->get();

            foreach ($registrations as $registration) {
                $oldAmount = $registration->membership_fee_amount;
                $feeCalculator->calculateAndApply($registration, $profile, $registration->submission);
                $newAmount = $registration->fresh()->membership_fee_amount;

                if ((float) $oldAmount !== (float) $newAmount) {
                    $schoolName = $registration->school->name ?? $registration->school_id;
                    $this->line("  ✓ {$schoolName}: ₹".number_format((float) $oldAmount).' → ₹'.number_format((float) $newAmount));
                    $totalUpdated++;
                }
            }
        }

        $this->info("Recalculation completed! Total registrations updated: {$totalUpdated}");

        return self::SUCCESS;
    }
}
