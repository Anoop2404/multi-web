<?php

namespace App\Console\Commands;

use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Console\Command;

class BackfillTenantWebsiteTier extends Command
{
    /**
     * php artisan tenants:backfill-website-tier
     *
     * Gives every tenant with no TenantSubscription row an explicit subscription on the
     * given plan (default: free). Needed so FeatureGate's "no plan configured = allowed"
     * fallback never accidentally reads as Premium-allowed for a tenant nobody has
     * subscribed yet. Run once, manually, after SubscriptionPlanSeeder. Does not touch
     * tenants that already have a subscription (won't clobber an admin-assigned plan).
     */
    protected $signature = 'tenants:backfill-website-tier {--plan=free}';

    protected $description = 'Ensure every tenant has an explicit subscription (defaults to the free plan)';

    public function handle(): int
    {
        $planSlug = $this->option('plan');
        $plan = SubscriptionPlan::where('slug', $planSlug)->first();

        if (! $plan) {
            $this->error("Subscription plan '{$planSlug}' is not seeded — run `php artisan db:seed --class=SubscriptionPlanSeeder` first.");

            return self::FAILURE;
        }

        $created = 0;
        $skipped = 0;

        foreach (Tenant::all() as $tenant) {
            if (TenantSubscription::where('tenant_id', $tenant->id)->exists()) {
                $skipped++;
                continue;
            }

            TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'period_start' => now(),
                'period_end' => now()->addYears(50),
                'status' => 'active',
                'auto_renew' => true,
            ]);
            $created++;
        }

        $this->info("Done. Created {$created} subscription(s) on '{$plan->name}', skipped {$skipped} tenant(s) that already had one.");

        return self::SUCCESS;
    }
}
