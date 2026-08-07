# Code Audit — 2026-08-06

Scope note: this is a 929-file Laravel app (204 models, 278 controllers). This pass targeted the highest-risk areas — multi-tenancy, authorization, concurrency/race conditions, mass assignment, and job reliability — rather than reading every file. Several areas already had prior audits on record (N+1 sweeps, UI/UX, region-admin security); this pass verified those and found new issues on top.

## Confirmed critical

**1. Student reg-no generator is O(N²) — still unfixed.**
`app/Services/Students/StudentRegistrationNumberGenerator::generate()` pulls **every** `reg_no` for **every school in the whole Sahodaya** (`Student::whereIn('tenant_id', $schoolIds)->pluck('reg_no')`), then maps/parses all of them in PHP, on every single call. Bulk import or backfill calls this once per student, so total work is O(N²) in Sahodaya-wide student count. A Sahodaya with a few thousand students across its schools will make bulk import/backfill visibly slow or time out. Fix: track the running max per (school-group, year) instead of rescanning, or cache the max within the transaction/request and increment in memory.

**2. Item-registration-number race condition between live registration and admin bulk-assign.**
`FestNumberingService::nextItemRegistrationNumber()` (and the chest-number path it shares) computes "next number" by reading `MAX(item_registration_number)` with **no lock**. It's only race-safe because `FestRegistrationCreateService::createForSchool()` wraps it inside a transaction that does `FestEvent::lockForUpdate()` first. But `FestChestNumberController::assignItemRegIds()` → `FestNumberingService::assignMissingItemRegNumbers()` (the admin "assign missing IDs" button) calls the same numbering logic **without ever acquiring that lock or wrapping in a transaction**. If an admin clicks "assign missing IDs" while a school is submitting a registration for the same event, both can read the same MAX and produce duplicate `item_registration_number`s. Fix: wrap `assignMissingChestNumbers`/`assignMissingItemRegNumbers` in the same `FestEvent::lockForUpdate()` pattern used by registration creation.

## Confirmed high

**3. Region-admin privilege-escalation fix (shipped today) is unverified.**
Per project history, `region_admin` staff duty used to grant the unscoped `fest_ops` Spatie role (full access to every event in the Sahodaya) — the opposite of what "region admin" implies. The fix is in code now (`FestEventStaffController::store()` no longer grants `fest_ops` for `region_admin`; scoping is enforced in `EnsureSahodayaAdmin` via `FestEventStaff.region_id`), and the companion migration (`2026_09_14_000001_add_region_id_to_fest_events.php`) exists. But per the implementation notes, **no PHP runtime was available while this was built — nothing was migrated or tested.** Until `php artisan migrate` runs per tenant and someone exercises it, treat this as unverified, not fixed. Also worth noting: the new `region_admin` permissions are granted directly to the user via `givePermissionTo()`, so any endpoint that checks `$user->can(...)` without also going through `EnsureSahodayaAdmin`'s event/region matching would bypass the scoping — worth a quick grep for `->can(` calls in fest controllers that aren't behind that middleware before trusting this fully.

**4. Job retry + temp-file cleanup interaction will break retried imports.**
`ImportStudentsJob::handle()` downloads the uploaded CSV to a local temp path, and on any exception it re-throws (correct, so the queue can retry/mark failed) — but the `finally` block **unconditionally deletes the temp file** before re-throwing. If the queue worker is configured with `--tries > 1` (or any retry_after/backoff), the retry attempt will run against a file that no longer exists and fail immediately, silently defeating the retry. None of the 6 job classes in `app/Jobs/` define `$tries`, `$backoff`, or a `failed()` handler, so there's also no visibility when a job exhausts its attempts — the user just never gets the "import complete/rejected" notification.

## Confirmed medium

**5. Per-student existence checks inside registration loops.**
`FestRegistrationCreateService::createForSchool()` runs one `Student::where(...)->doesntExist()` query and one `FestParticipant::create()` **per participant** in a loop, rather than a single `whereIn(...)->count()` check up front plus a bulk insert. For large squads (dance troupes, sports relay teams) this is N+1-ish — not currently causing timeouts (the whole thing sits inside one transaction holding a lock on the event row), but it does extend how long that event-wide lock is held, which is a scalability concern at registration-deadline traffic spikes.

**6. Event-wide lock on every registration submission.**
`createForSchool()` takes `FestEvent::lockForUpdate()` on the *entire event* for every single school's registration, not per-item or per-school. This is correct (prevents quota races) but means all schools registering for the same event serialize against each other — including unrelated items. Under deadline-day load this is a plausible source of request pile-ups/timeouts. Worth load-testing before a big Kalolsavam registration window opens; a narrower lock (per item, or a dedicated counter row) would scale better.

## Things checked and found clean

- No `$guarded = []` (unrestricted mass assignment) in any of the 204 models.
- No `Model::create($request->all())` / `->update($request->all())` patterns found — inputs go through validated arrays.
- No string-interpolated `whereRaw`/`DB::raw`/`selectRaw` (no obvious SQL-injection surface).
- `LedgerPostingService` (financial double-entry posting) is properly idempotent and lock-protected — checks for an existing journal by `reference_type`/`reference_id` under `lockForUpdate()` before reposting, and reversal entries are generated from the original rows rather than recomputed.
- `.env` has `APP_DEBUG=true` — fine for `APP_ENV=local`, but flagging so it's checked that production `.env` doesn't share this (couldn't verify from this workspace).

## Not covered in this pass (flag if you want a follow-up)

Given the codebase size, this pass did not systematically check: the 278 controllers for missing authorization checks outside the fest/region area, migrations for missing indexes/FKs, the sahodaya_mobile app, front-end (Vue/Inertia) code, or the export/report services. The existing `n1_audit_sweep2` findings (reg-no generator — confirmed above) and `region_scoped_admin_plan` (confirmed above) were the only prior open items re-verified here.
