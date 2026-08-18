<?php

namespace App\Support;

use App\Models\Tenant;
use Illuminate\Http\Request;

/**
 * Central place to decide whether a request against a tenant should proceed
 * given that tenant's TenantSubscription.status — the field existed but was
 * only ever read inside SubscriptionController, so a suspended/readonly
 * tenant's own panel never actually enforced it.
 */
class TenantSubscriptionGate
{
    /**
     * @return string|null 'suspended' or 'readonly' when the request must be blocked, null when it may proceed.
     */
    public static function check(Tenant $tenant, Request $request): ?string
    {
        if ($request->user()?->isSuperAdmin()) {
            return null;
        }

        $subscription = $tenant->subscription;

        // Tenants created before billing existed (or never assigned a plan)
        // have no subscription row at all — don't retroactively block them.
        if (! $subscription) {
            return null;
        }

        if ($subscription->isSuspended()) {
            return 'suspended';
        }

        if ($subscription->isInGrace()) {
            // Re-flashed on every request while in grace, not just once after a
            // redirect, so the warning stays visible for the whole grace period.
            $request->session()->flash(
                'warning',
                "This organization's subscription is in its grace period and will be restricted soon. Please renew to avoid service interruption."
            );
        }

        if ($subscription->isReadOnly() && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return 'readonly';
        }

        return null;
    }
}
