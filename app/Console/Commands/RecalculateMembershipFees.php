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

        $sahodayas = Tenant::query()
            ->where('type', 'sahodaya')
            ->when($sahodayaOption, function ($q) use ($sahodayaOption) {
                $q->where(function ($sub) use ($sahodayaOption) {
                    $sub->where('id', $sahodayaOption)
                        ->orWhere('school_prefix', $sahodayaOption)
                        ->orWhere('name', 'like', "%{$sahodayaOption}%");
                });
            })
            ->get();

        if ($sahodayas->isEmpty()) {
            $sahodayas = Tenant::where('type', 'sahodaya')->get();
        }

        if ($sahodayas->isEmpty()) {
            $sahodayas = Tenant::all();
        }

        $totalUpdated = 0;

        foreach ($sahodayas as $sahodaya) {
            $this->info('Processing Sahodaya: '.($sahodaya->name ?? $sahodaya->id));

            // Fetch member schools for this Sahodaya from Central connection
            $schools = Tenant::where('type', 'school')
                ->where('parent_id', $sahodaya->id)
                ->get()
                ->keyBy('id');

            if ($schools->isEmpty()) {
                $schools = Tenant::where('type', 'school')->get()->keyBy('id');
            }

            $initialized = false;
            if (function_exists('tenancy')) {
                try {
                    tenancy()->initialize($sahodaya);
                    $initialized = true;
                } catch (\Throwable) {
                }
            }

            try {
                $profile = SahodayaProfile::where('tenant_id', $sahodaya->id)->first()
                    ?? SahodayaProfile::first();

                if (! $profile) {
                    $this->warn("  - No fee profile configured for {$sahodaya->name}.");
                    continue;
                }

                $registrations = Registration::whereIn('school_id', $schools->keys()->all())
                    ->with('submission')
                    ->get();

                if ($registrations->isEmpty()) {
                    $registrations = Registration::with('submission')->get();
                }

                if ($registrations->isEmpty()) {
                    foreach ($schools as $school) {
                        $tier = \App\Support\SchoolClassCategoryResolver::feeTierFor($school);
                        $fee = $feeCalculator->estimateFeeForSchool($school, '2026-27');
                        $highest = $school->application_payload['highest_class'] ?? $school->application_payload['highest_class_offered'] ?? 'N/A';
                        $this->line("  ✓ {$school->name} | Class: {$highest} | Tier: {$tier} | Estimated Fee: ₹".number_format($fee));
                    }
                    continue;
                }

                foreach ($registrations as $registration) {
                    $school = $schools->get($registration->school_id) ?? $registration->school;
                    if (! $school) {
                        continue;
                    }

                    $calculatedFee = $feeCalculator->estimateFeeForSchool($school, $registration->academic_year);
                    $tier = \App\Support\SchoolClassCategoryResolver::feeTierFor($school);
                    $paid = (float) ($registration->amount_paid ?? 0);
                    $due = max(0, $calculatedFee - $paid);

                    if (! $isDryRun && $calculatedFee > 0 && empty($registration->fee_override)) {
                        $feeCalculator->calculateAndApply($registration, $profile, $registration->submission);
                    }

                    $highest = $school->application_payload['highest_class'] ?? $school->application_payload['highest_class_offered'] ?? 'N/A';
                    $this->line("  ✓ {$school->name} | Class: {$highest} | Tier: {$tier} | Fee: ₹".number_format($calculatedFee).' | Paid: ₹'.number_format($paid).' | Due: ₹'.number_format($due));
                    $totalUpdated++;
                }
            } finally {
                if ($initialized && function_exists('tenancy')) {
                    try {
                        tenancy()->end();
                    } catch (\Throwable) {
                    }
                }
            }
        }

        $this->info("Completed! Total registrations processed: {$totalUpdated}");

        return self::SUCCESS;
    }
}
