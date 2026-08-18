<?php

namespace App\Console\Commands;

use App\Models\TenantSubscription;
use App\Services\Audit\PlatformAuditLogger;
use App\Services\Notifications\SubscriptionLifecycleNotifier;
use Illuminate\Console\Command;

/**
 * FRD-13 §8 renewal/grace/suspension lifecycle. There's no payment gateway anywhere in
 * this codebase (manual receipt-upload only), so "automated renewal" can't mean
 * auto-charging — it means automatically progressing an overdue subscription through
 * active -> grace -> suspended if nobody manually renews it. 'readonly' stays a status
 * an admin can set by hand (via the billing form); this command doesn't set it
 * automatically, since FRD-13 gives no second threshold to derive it from.
 */
class SubscriptionsProcessLifecycleCommand extends Command
{
    protected $signature = 'subscriptions:process-lifecycle';

    protected $description = 'Move overdue subscriptions from active to grace, then grace to suspended';

    public function handle(SubscriptionLifecycleNotifier $notifier, PlatformAuditLogger $audit): int
    {
        $enteredGrace = 0;
        $suspended = 0;

        TenantSubscription::where('status', 'active')
            ->whereDate('period_end', '<', now()->toDateString())
            ->with('plan')
            ->chunkById(100, function ($subscriptions) use ($notifier, $audit, &$enteredGrace) {
                foreach ($subscriptions as $subscription) {
                    $subscription->update(['status' => 'grace']);
                    $notifier->enteredGrace($subscription);
                    $audit->log(
                        'subscription.entered_grace',
                        "Subscription entered grace period for tenant #{$subscription->tenant_id}",
                        $subscription,
                        ['tenant_id' => $subscription->tenant_id],
                        category: 'billing',
                    );
                    $enteredGrace++;
                }
            });

        TenantSubscription::where('status', 'grace')
            ->with('plan')
            ->get()
            ->each(function (TenantSubscription $subscription) use ($notifier, $audit, &$suspended) {
                $gracePeriodDays = $subscription->plan?->grace_period_days ?? 14;
                $suspendAfter = $subscription->period_end?->copy()->addDays($gracePeriodDays);

                if (! $suspendAfter || $suspendAfter->isAfter(now())) {
                    return;
                }

                $subscription->update([
                    'status' => 'suspended',
                    'suspended_at' => now(),
                    'suspended_reason' => 'Automatic: grace period expired without renewal.',
                ]);
                $notifier->suspended($subscription);
                $audit->log(
                    'subscription.auto_suspended',
                    "Subscription auto-suspended for tenant #{$subscription->tenant_id} — grace period expired",
                    $subscription,
                    ['tenant_id' => $subscription->tenant_id],
                    category: 'billing',
                );
                $suspended++;
            });

        $this->info("Entered grace: {$enteredGrace}. Suspended: {$suspended}.");

        return self::SUCCESS;
    }
}
