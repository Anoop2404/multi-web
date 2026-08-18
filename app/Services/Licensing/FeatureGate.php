<?php

namespace App\Services\Licensing;

use App\Models\PlanFeature;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;

/**
 * FRD-13 §9 Feature Management enforcement point. Precedence: an explicit per-tenant
 * override always wins; otherwise fall back to the tenant's current plan; if neither is
 * configured, modules default to allowed (this is a brand-new system being layered onto
 * existing tenants that have never had it — defaulting to deny-all would instantly lock
 * out every tenant whose plan hasn't been configured yet) and limits default to
 * unenforced (null = no cap).
 */
class FeatureGate
{
    public function allows(Tenant $tenant, string $key): bool
    {
        $override = $this->override($tenant, $key);
        if ($override && $override->enabled !== null) {
            return $override->enabled;
        }

        $planFeature = $this->planFeature($tenant, $key);
        if ($planFeature) {
            return $planFeature->enabled;
        }

        return true;
    }

    public function limit(Tenant $tenant, string $key): ?int
    {
        $override = $this->override($tenant, $key);
        if ($override && $override->limit_value !== null) {
            return $override->limit_value;
        }

        return $this->planFeature($tenant, $key)?->limit_value;
    }

    private function override(Tenant $tenant, string $key): ?TenantFeatureOverride
    {
        return TenantFeatureOverride::where('tenant_id', $tenant->id)
            ->where('feature_key', $key)
            ->first();
    }

    private function planFeature(Tenant $tenant, string $key): ?PlanFeature
    {
        $planId = $tenant->subscription?->plan_id;

        if (! $planId) {
            return null;
        }

        return PlanFeature::where('plan_id', $planId)
            ->where('feature_key', $key)
            ->first();
    }
}
