# Wildcard registration uniqueness and State collection overview

The `fest_reg_active_unique` index allowed only one active registration per event, school and item. Sahodaya wildcard entries share the synthetic `appeal-school-{sahodayaId}` school but need separate registrations so each entry retains its own student and origin school. A second student in the same item therefore failed with PostgreSQL 23505.

The tenant migration updates the existing index to exclude synthetic Appeal School IDs. The constraint remains unchanged for normal schools. Wildcard duplicate-student checks and student locks in FestAppealWildcardService remain in force. Existing registrations are not modified or deleted. Rolling back refuses to restore the old index if it would conflict with legitimate active entries.

After deploying, apply the specific migration to the affected Vadakara database:

```bash
XDEBUG_MODE=off php -d opcache.enable_cli=0 -d opcache.jit=0 artisan tenants:migrate \
  --tenants=b99e8dda-fe60-4eb6-a2d7-59f7518a89c6 \
  --path=database/migrations/tenant/2026_11_27_000001_allow_separate_appeal_pool_registrations.php \
  --force
```

Apply the same migration to other isolated Sahodaya databases through the normal tenant migration deployment workflow. It updates an existing index only; databases that never had that index are unchanged.

The State slot collection overview also referenced an undefined controller property `$publicUrl`. It now uses the publicUrl already supplied by SahodayaAdminController::inertia().

Validation: wildcard service, Sahodaya wildcard creation/resolution and State collection suites; includes the legacy index upgraded with an existing wildcard, a second student in the same item, duplicate-student rejection, normal-school uniqueness, rollback refusal, and overview rendering. Index regression coverage uses SQLite's compatible partial-index SQL; production PostgreSQL migration has not been run from this workspace.
