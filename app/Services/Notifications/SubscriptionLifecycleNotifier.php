<?php

namespace App\Services\Notifications;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;

/**
 * Mirrors SahodayaAdminNotifier's shape, but that one is hardcoded to
 * sahodaya_admin/sahodaya_staff roles — subscriptions apply to both Sahodaya and
 * school tenants, so this picks the admin role(s) from the tenant's own type.
 */
class SubscriptionLifecycleNotifier
{
    public function enteredGrace(TenantSubscription $subscription): void
    {
        $this->notify($subscription, 'subscription.entered_grace');
    }

    public function suspended(TenantSubscription $subscription): void
    {
        $this->notify($subscription, 'subscription.suspended');
    }

    private function notify(TenantSubscription $subscription, string $slug): void
    {
        $tenant = $subscription->tenant ?? Tenant::find($subscription->tenant_id);

        if (! $tenant) {
            return;
        }

        $roles = $tenant->type === 'school'
            ? ['school_admin']
            : ['sahodaya_admin', 'sahodaya_staff'];

        try {
            $users = User::role($roles)->where('tenant_id', $tenant->id)->get();
        } catch (\Throwable) {
            return;
        }

        $service = app(NotificationService::class);
        $replacements = [
            'plan_name' => $subscription->plan?->name ?? 'your plan',
            'period_end' => $subscription->period_end?->toFormattedDateString(),
        ];

        foreach ($users as $user) {
            $service->notifyFromTemplate($user, $slug, $replacements);
        }
    }
}
