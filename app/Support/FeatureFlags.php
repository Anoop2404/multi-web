<?php

namespace App\Support;

class FeatureFlags
{
    public static function websiteEnabled(): bool
    {
        return (bool) config('features.website_enabled', false);
    }

    /**
     * Gates the FestReportScopeResolver/FestReportScope-based retrofit (plan §12).
     * Not yet actually consumed by any report controller as a gate — the Phase 1
     * containment fix (EnsureSahodayaAdmin, ResolveRegionScopedReportEvent) and the
     * Registration Register scoping are unconditional (security fixes, not opt-in
     * features). This flag exists for the broader Phase 3 retrofit to check as each
     * report family is migrated, per the plan's rollout order — wire it in as that
     * work lands rather than all at once.
     */
    public static function festScopedReportsV2(): bool
    {
        return (bool) config('features.fest_scoped_reports_v2', false);
    }

    public static function festNamedPhaseLifecycle(): bool
    {
        return (bool) config('features.fest_named_phase_lifecycle', false);
    }

    public static function festSportsRegionTree(): bool
    {
        return (bool) config('features.fest_sports_region_tree', false);
    }
}
