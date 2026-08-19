<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\SahodayaProfile;
use App\Models\Tenant;
use App\Services\Membership\MembershipFeeCalculator;
use Illuminate\Console\Command;

class RecalculateMembershipFees extends Command
{
    protected $signature = 'membership:recalculate-fees 
                            {--sahodaya= : Optional Sahodaya Tenant ID, UUID or name} 
                            {--dry-run : Preview fee calculations without updating database}';

    protected $description = 'Recalculates membership fee amounts for member school registrations based on class levels and fee settings.';

    public function handle(MembershipFeeCalculator $feeCalculator): int
    {
        $sahodayaOption = $this->option('sahodaya');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('--- DRY RUN MODE (No database changes will be saved) ---');
        }

        $profiles = SahodayaProfile::query()
            ->when($sahodayaOption, function ($q) use ($sahodayaOption) {
                $q->where('tenant_id', $sahodayaOption)
                  ->orWhereHas('tenant', fn ($t) => $t->where('school_prefix', $sahodayaOption)->orWhere('name', 'like', "%{$sahodayaOption}%"));
            })
            ->get();

        if ($profiles->isEmpty()) {
            // Fallback if profiles table is empty or tenant database scoped
            $tenants = Tenant::where('type', 'sahodaya')
                ->when($sahodayaOption, fn ($q) => $q->where('id', $sahodayaOption))
                ->get();

            if ($tenants->isEmpty()) {
                $tenants = Tenant::all();
            }

            foreach ($tenants as $t) {
                $p = SahodayaProfile::where('tenant_id', $t->id)->first();
                if ($p) {
                    $profiles->push($p);
                }
            }
        }

        $totalUpdated = 0;

        foreach ($profiles as $profile) {
            $sahodaya = Tenant::find($profile->tenant_id);
            $this->info('Processing Sahodaya: '.($sahodaya->name ?? $profile->tenant_id));

            $registrations = Registration::whereHas('school', fn ($q) => $q->where('parent_id', $profile->tenant_id))
                ->with(['submission', 'school'])
                ->get();

            if ($registrations->isEmpty()) {
                $schools = Tenant::where('type', 'school')->where('parent_id', $profile->tenant_id)->get();
                foreach ($schools as $school) {
                    $tier = \App\Support\SchoolClassCategoryResolver::feeTierFor($school);
                    $fee = $feeCalculator->estimateFeeForSchool($school, '2026-27');
                    $highest = $school->application_payload['highest_class'] ?? 'N/A';
                    $this->line("  ✓ {$school->name} | Class: {$highest} | Tier: {$tier} | Estimated Fee: ₹".number_format($fee));
                }

                continue;
            }

            foreach ($registrations as $registration) {
                $school = $registration->school;
                if (! $school) {
                    continue;
                }

                $oldAmount = (float) ($registration->membership_fee_amount ?? 0);
                $calculatedFee = $feeCalculator->estimateFeeForSchool($school, $registration->academic_year);
                $tier = \App\Support\SchoolClassCategoryResolver::feeTierFor($school);
                $paid = (float) ($registration->amount_paid ?? 0);
                $due = max(0, $calculatedFee - $paid);

                if (! $isDryRun && $calculatedFee > 0 && empty($registration->fee_override)) {
                    $feeCalculator->calculateAndApply($registration, $profile, $registration->submission);
                }

                $highest = $school->application_payload['highest_class'] ?? 'N/A';
                $this->line("  ✓ {$school->name} | Class: {$highest} | Tier: {$tier} | Fee: ₹".number_format($calculatedFee)." | Paid: ₹".number_format($paid)." | Due: ₹".number_format($due));
                $totalUpdated++;
            }
        }

        $this->info("Completed! Total registrations processed: {$totalUpdated}");

        return self::SUCCESS;
    }
}
