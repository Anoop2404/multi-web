# Deprecated: orphaned external-identity stub services

Moved here 2026-08-10, at the user's direction, out of `app/Services/External/` and
`tests/Feature/External/`.

`ExternalAuthService`, `ExternalStudentRegistryService`, `ExternalRegistrationService`, and
`ExternalConductService` were added in commit `05054b2` alongside a passing test
(`ExternalPlatformTest.php`), but every method only builds and returns an in-memory array —
none of them ever write to a database. No `external_students`/`external_registrations` tables
exist. Nothing outside the test file ever called them.

They duplicated the `ExternalSahodaya`/`ExternalSchool`/`ExternalIntakeService` system (see
`docs/STATE_LEVEL_KALOTSAV_ROLLOUT_PLAN.md` §2.1 and
`app/Services/State/ExternalIntakeService.php`), which is DB-persisted, quota-enforced, and
already handles outside-Sahodaya/school intake for this rollout.

Files kept here (`.txt` extension, out of the Composer autoload path and PHPUnit's test
discovery, so they're inert) for reference only, in case a future real external-identity build
(named accounts, MFA, `external_students` registry — see P-02/P-04 in the master plan's policy
register) wants to reuse the method shapes as a starting sketch. If nobody's referenced this
folder in a while, it's safe to actually delete outright — the sandbox filesystem that
generated these couldn't perform a true delete (rename-only), so a plain `rm -rf` from a normal
terminal on the real repo will finish the job.
