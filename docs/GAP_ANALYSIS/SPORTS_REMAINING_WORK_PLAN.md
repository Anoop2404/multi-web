# Sports Completion — Remaining Work Plan

Status as of 2026-07-11. Covers everything still open on the Sports (FRD-04 + Event Head v2) Critical-item checklist after the per-Event-Head composite billing rebuild. Done so far: schema, fee calculation, approval/download/invoice gating, report fixes, Fees UI, item quota flag, cancellation lock — all committed on `main`.

Remaining, in build order: **#50 → #52 → #51 → #58 → #53 → #61 → #54**. Rationale for the order is in each section below.

---

## #50 — Backfill command for live events

**Why first:** live events are already running on flat item-level fees (100/individual, 200/team). Nothing else in this list should touch fee amounts on a live event until this data is safely mapped onto the new head columns.

**What it does:** a new Artisan command that, per Sahodaya tenant, walks each sports event's `FestItemHead` rows and, for any head that has no composite fee columns set yet, backfills:
- `school_registration_fee = 0`
- `student_registration_fee` = the observed flat individual-item rate for that head (from existing `FestEventItem.fee_amount`, most common non-null value across the head's individual items — falls back to 0 if none set)
- `team_registration_fee` = same derivation from team/group items
- `included_items_per_student = 0`, `included_teams = 0` (no free quota by default — matches "no quota" live behavior)
- `verification_policy = 'all_students'`, `approval_policy = 'auto'` (matches current implicit behavior)

**Explicitly does NOT touch:** `FestSchoolEventFee`, `FestRegistration`, `FestEventItem.fee_amount` (left as item-level overrides, which `calculateForHead()` already respects ahead of the head default).

**Files:**
- New: `app/Console/Commands/BackfillSportsHeadFees.php` — signature `fest:backfill-head-fees {--sahodaya=} {--event=} {--dry-run}`, following the `SyncFestCatalog`/`BackfillStudentRegNumbers` pattern (per-tenant loop, `$tenant->run()` to enter tenant DB, `$this->info()` progress lines, `--dry-run` prints a table of proposed values without saving).
- Read-only reference: `app/Models/FestItemHead.php`, `app/Models/FestEventItem.php`.

**Risk:** low — additive, idempotent (skip heads that already have any composite fee column set), `--dry-run` lets the user review before committing. Run manually per-Sahodaya once this ships; not part of any request cycle.

**Verification:** `--dry-run` output reviewed by user against known live event numbers before the real run. No automated test possible (no PHP runtime in sandbox) — this is a manual-run, manually-verified command by design.

---

## #52 — Max participants/teams per head + capacity enforcement

**Why before #51:** simpler, self-contained, and unlocks the same enforcement pattern (`FestParticipationLimitService`) that team/coach validation in #51 will also touch — doing it first avoids editing the same method twice.

**What it does:** enforce `FestItemHead.max_participants` / `max_teams` (already in schema, currently unused) at registration time, mirroring the existing `max_per_school` pattern.

**Files:**
- `app/Services/Events/FestParticipationLimitService.php::validateRegistration()` (existing method, lines ~44-109) — add a new check block after the existing `max_per_school` block (~53-63):
  - For individual items: count `FestRegistration` rows for `event_id` + `head_id` (via `whereHas('item', fn($q) => $q->where('head_id', $headId))`) with `countableStatuses($policy)`, non-team participants only; compare against `$head->max_participants`.
  - For team/group items: count distinct `FestGroup`/team registrations under the head; compare against `$head->max_teams`.
  - Push a clear error message (`"{$head->name} has reached its participant/team cap ({$max})."`) into the same `$errors` array the method already returns.
- No schema change needed — columns already exist from the #43 migration.

**Risk:** medium — this is a new hard gate on live registration flow. Must confirm `null`/`0` means "no cap" (matches existing `max_per_school` convention of `> 0` check) so events that haven't configured a cap aren't suddenly blocked.

**Verification:** add unit tests in `FestSchoolEventFeeServiceTest`-adjacent test file (or a new `FestParticipationLimitServiceTest` if one doesn't exist — needs a quick check) exercising: cap not set (unlimited), cap reached (blocks), cap not yet reached (allows), team cap vs individual cap counted separately. Bundle with #61 since both are test-writing tasks against the same event-head fixtures — tests can't be executed in this sandbox, so they're delivered for the user to run locally.

---

## #51 — Team entity coach/manager fields + roster governance

**What it does:** `FestGroup` (the team registration entity — file confirmed at `app/Models/FestGroup.php`, currently just `registration_id`, `team_name`, `status`) gets contact/roster metadata:
- New columns: `coach_name`, `coach_phone`, `manager_name`, `manager_phone` (all nullable strings).
- Min/max team size is **not** new — it already exists on `FestEventItem.min_group_size`/`max_group_size` and is enforced via `FestTeamSquadRules::validateCount()`. This task only needs to confirm that path still fires correctly for `sports_composite` team items (it's item-level, not head-level, so should be unaffected by the billing changes — quick regression check, not new code).

**Files:**
- New migration: `database/migrations/tenant/2026_0X_XX_fest_group_coach_manager_fields.php` — `Schema::table('fest_groups', ...)` adding the four nullable string columns, guarded with `hasColumn` checks, full `down()`.
- `app/Models/FestGroup.php` — add the four fields to `$fillable`.
- UI: wherever a school currently creates/edits a `FestGroup` for a team item (need to locate the team-registration Vue component — likely `resources/js/Components/school/SportsEventItemRegistrationPanel.vue`, already seen in this session for #58 research) — add four optional input fields.
- Backend: the controller/service that creates `FestGroup` on team registration (likely inside `FestRegistrationCreateService` or a sibling — needs a locate-pass at implementation time) — accept and persist the four new fields.

**Risk:** low — additive columns, optional fields, no billing logic touched.

**Verification:** manual — create a team registration with coach/manager filled in, confirm persistence and display on the Sahodaya-admin team roster view.

---

## #58 — Redesign school-side Registration.vue for per-head invoices

**Why this size/placement:** the largest remaining UI piece, and depends on #50 being done first (so live events have sane per-head numbers to display) and benefits from #52/#51 being settled (so the payment panel can also surface caps/team info without a second pass).

**What it does:** today `event.school_fee` is a single object (one invoice, one upload form, one status). For `sports_composite` events with per-head billing, a school can owe money to N different heads independently (confirmed rule: Athletics paid ≠ Chess paid). The page needs to become a list.

**Files (from earlier research this session):**
- `resources/js/Pages/Admin/School/Events/Registration.vue` — replace the single fee/payment block with a loop over per-head fee records (one card per head: amount due, amount paid, status badge, upload-proof form, receipt link).
- `resources/js/Components/school/SportsEventItemRegistrationPanel.vue` — likely needs to show which head an item belongs to and that head's payment status inline, so a school understands why "Register" might be blocked for one head but not another.
- Backend routes/controllers that currently assume one fee record per school per event need a head-identifier parameter added:
  - Upload payment proof route (`attachPaymentForHead()` already exists in `FestSchoolEventFeeService` from #46 — just needs a controller/route wired to it for the school-facing surface; check `app/Http/Controllers/SchoolAdmin/FestEventFeeController.php` or equivalent for the existing single-fee upload endpoint to extend).
  - Fee-receipt / invoice display endpoints — need a `head_id` route parameter or query param.
- Inertia props: the controller feeding `Registration.vue` needs to pass a list of per-head fee summaries instead of (or alongside) the single aggregate `FestEventInvoice` rollup already built in #59.

**Risk:** highest of the remaining items — school-facing, payment-critical UI touching real money flows on a live product. Needs its own careful sub-scoping pass (read the current `Registration.vue` fee section in full, the controller that renders it, and `attachPaymentForHead()`'s exact signature) before writing any code — same exhaustive-research-before-code approach used for the original per-head fee-fragmentation build.

**Suggested sub-steps when this is picked up:**
1. Read-only pass: current `Registration.vue` fee section + its controller + `attachPaymentForHead()` signature.
2. Confirm backward compatibility: non-head events (the other fee models) must keep working through the existing single-invoice path untouched.
3. Build the per-head list UI, gated on `usesPerHeadBilling()` (same flag used throughout this build), with the old single-invoice UI as the `else` branch — mirrors the pattern already used in `FeesTab.vue`.
4. Wire upload/receipt routes to accept `head_id`.
5. Manual verification against a real per-head test event (dry-run in the app, not automated — no PHP runtime here).

---

## #53 — Medal tally from FestRankPoint

**Independent of the billing work** — can be done any time, included here for completeness since it's still on the original Sports checklist.

**What it does:** replace the hardcoded `gold*5 + silver*3 + bronze*1` formula in `SportsProgramController::rankings()` (confirmed at `app/Http/Controllers/SahodayaAdmin/SportsProgramController.php`, line 110) with a lookup against `FestRankPoint` (event-configurable rank→points mapping, already exists and is already editable via the event Settings "Points" tab per this project's earlier FRD-04 work).

**Files:**
- `app/Http/Controllers/SahodayaAdmin/SportsProgramController.php::rankings()` (lines ~76-126) — instead of counting gold/silver/bronze and multiplying by fixed constants, join `FestMark.position` against `FestRankPoint` scoped by `event_id` (and `is_group` for team events) to get the actual points value per rank, then sum per school. Falls back to the old 5/3/1 formula only if the event has no `FestRankPoint` rows configured (keeps existing events working without a migration).

**Risk:** low-medium — purely a scoring-display change, no money/registration impact, but affects publicly-visible standings so should be spot-checked against a known event's current tally before/after to confirm the numbers don't silently shift for events that never bothered configuring custom rank points (the fallback handles this, but verify).

**Verification:** manual — pick one live/completed sports event, compare tally before and after the change; should be identical unless that event has custom `FestRankPoint` rows, in which case the new tally is the "more correct" one.

---

## #61 — FestSchoolEventFeeServiceTest: head-aware fixtures/tests

**What it does:** the existing 22-test file at `tests/Unit/Services/Events/FestSchoolEventFeeServiceTest.php` already creates `FestItemHead` fixtures in several tests but never sets any of the new composite-fee columns (`school_registration_fee`, `student_registration_fee`, `team_registration_fee`, `included_items_per_student`, `included_teams`, `max_participants`, `max_teams`, `verification_policy`, `approval_policy`) — so none of the new `calculateForHead()` / `recalculateForHead()` / `hasApprovedPaymentForRegistration()` logic built this session is covered.

**New test cases to add:**
1. `calculateForHead()` basic case — school fee + student fee + per-item fee, no quota (matches the user's original worked example: school 100, student 100, item 150 → total 350 with no quota).
2. Quota waiver — student registers 2 items, 1 free quota slot → student pays base + 1 item fee, second item waived (matches the "student pays base reg 100 + event fee 100 = 200" clarification).
3. Multiple eligible items + quota exhaustion — 3 items, 1 quota slot, FIFO order — first item free, next two billed.
4. Team item billing — team fee charged once per team registration, separate from individual quota, own `included_teams` quota.
5. Per-head independence — two heads on one event/school, one fully paid one not; `isPaidForRegistration()` returns true for the paid head's registrations and false for the other's.
6. Cancellation + quota release — register 2 quota-eligible items (quota=1), cancel the first (unpaid) registration, recalculate, confirm the second registration now receives the freed slot.
7. Cancellation lock — approve payment on a head's fee record, attempt `FestRegistrationService::cancel()` on a registration under that head, assert 422 abort.
8. Capacity cap (pairs with #52) — registrations at/under/over `max_participants`/`max_teams`.

**Files:** extend `tests/Unit/Services/Events/FestSchoolEventFeeServiceTest.php` (or split new cases into a `tests/Unit/Services/Events/FestSportsCompositeFeeServiceTest.php` if the existing file is getting unwieldy at 640+ lines — judgment call at implementation time).

**Constraint:** this sandbox has no PHP runtime (`php` binary absent, `vendor/bin/phpunit` present but unexecutable) — tests will be written and syntax-sanity-checked only (brace/paren balance, PHP-aware review), then handed to the user to run locally with `php artisan test --filter=FestSchoolEventFeeServiceTest`.

---

## #54 — Verify + commit each item (final pass)

Not a separate implementation task — this is the closing step once #50/#51/#52/#53/#58/#61 are all done: a final read-through of every diff from this entire Sports build (from #43 through #61), confirming:
- Every change is gated behind `usesPerHeadBilling()` / `head_id !== null` / `fee_model === 'sports_composite'` where appropriate, so non-sports and non-composite events are provably untouched.
- No leftover TODOs or debug code.
- Each logical unit has its own commit with a clear message (already the practice throughout this session).
- A short final summary written back to the user listing every commit hash in this Sports build, for their own changelog/release notes.

---

## What's already done (for reference, no action needed)

Schema (#43-44), fee calculation engine (#45), head-aware recalculate/attachPayment/isPaid (#46), per-head approval gate (#55), per-head download gate (#56), auto-approve cascade scoping (#57), invoice rollup (#59), report query fixes (#60), Fees tab UI (#47), item quota_eligible flag (#48), cancellation lock (#49, quota release confirmed automatic — no code needed beyond the lock itself).

## Suggested next session command

Say **"do #50"** (or whichever number) to pick up a specific item, or **"continue sports"** to work through the list in the order above.
