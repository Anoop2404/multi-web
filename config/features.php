<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Public website & CMS
    |--------------------------------------------------------------------------
    |
    | When false, public tenant sites, site builder, and website admin menus
    | are disabled. Registration and admin panels remain available.
    |
    | Defaults to enabled — this was a gradual-rollout kill switch during initial
    | development; the website/CMS feature is now standard, so no .env entry
    | should be required. Set WEBSITE_ENABLED=false explicitly if an install
    | genuinely needs to turn it off.
    |
    */
    'website_enabled' => env('WEBSITE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Region/phase event reporting remediation (rollout controls)
    |--------------------------------------------------------------------------
    |
    | docs/REGION_PHASE_EVENT_REPORTING_REMEDIATION_PLAN.md §12. These are global
    | booleans, not per-tenant flags — this codebase has no dynamic/per-tenant flag
    | infrastructure (no Laravel Pennant, no DB-backed flags table) as of this change.
    | A tenant-scoped rollout as described in the plan's rollout order would need that
    | infrastructure built first; until then, treat these as env-gated kill switches
    | for the whole install, not a way to pilot with one tenant while others are
    | unaffected.
    |
    */
    'fest_scoped_reports_v2' => env('FEST_SCOPED_REPORTS_V2', false),
    'fest_named_phase_lifecycle' => env('FEST_NAMED_PHASE_LIFECYCLE', false),
    'fest_sports_region_tree' => env('FEST_SPORTS_REGION_TREE', false),

];
