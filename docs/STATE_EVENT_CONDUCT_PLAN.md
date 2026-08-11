# State Event Conduct — Scoping Plan

2026-08-11. Scopes checklist item §0.6 ("State event conduct") from
`STATE_KALOTSAV_PENDING_SIGNOFF_AND_LAUNCH_CHECKLIST.md` — by far the largest remaining gap.
Nothing here has been executed against a live database (no PHP runtime in this sandbox); each
phase should be verified locally before the next is trusted.

## Design decision: State Finals is a fresh competition

Participants who qualify from their Sahodaya perform *again* at the State event and get new
marks/positions there — State conduct is not just "certify the Sahodaya result." This matches
why `state_fest_marks` exists as its own table (migration
`database/migrations/state/2026_07_20_000001_state_fest_tables.php`) separate from the
`meta['position']/['grade']` carried over from the qualifier by
`StateQualifierMaterializationService` (that carried-over data represents the *qualifying*
result, kept for reference/seeding — e.g. pre-filling a scoreboard before State's own judging
starts — not the final State-level outcome).

## What to mirror from the tenant-level system (verified by research this session)

The existing Sahodaya-level conduct system is mature and battle-tested. State conduct should
copy its shape onto the `state` connection rather than invent a new one:

- **Item-scoped operations.** Attendance, mark entry, and chest numbers always operate one
  item at a time, never "whole event."
- **`EventLifecycleGate` as the single choke point** for phase permission checks
  (registration → mark-entry → publish). State needs its own `StateEventLifecycleGate`
  against `StateFestEvent`.
- **Dual mark-entry path**: judges each submit independently per participant
  (`FestJudgeScore`, one row per judge), auto-averaged into the canonical mark once every
  assigned judge has submitted (`FestJudgeScoreService::syncAggregatedMark()`) — *this is the
  "double verification"* your gap list is asking for, not a separate reconciliation step.
  Alternatively a coordinator can type marks in directly for non-panel items
  (`FestMarkEntryController`/`FestMarkSaveService`).
- **Two-level publish**: item-level (`FestItemResultsService::publishItem`) then
  event-level (`FestResultsController::publish` → recompute school points → generate
  certificates → cascade to child events).
- **Certificates are polymorphic already** (`Certificate` model, `entity_type`/`entity_id`) —
  a `StateCertificateService` can reuse the same table, no new schema needed there.
- **Team/group items "expand to team"** — one attendance/mark action writes identical values
  to every squad member.

## Phased build order

**Phase 1 — StateEventLifecycleGate + schema foundation.** Add `results_published`,
`scoring_locked`, `appeals_open` to `state_fest_events` (currently has none of these — it only
has `status`, a free string). New `StateEventLifecycleGate` service mirroring
`EventLifecycleGate`'s phase checks. No UI yet — this is the permission substrate everything
else gates on.

**Phase 2 — Attendance.** New `state_attendances` table (mirrors `FestAttendance`:
`state_event_id, item_id(state_program_item_id), registration_id, participant_id, status,
marked_by, marked_at`). `StateAttendanceController` (index/store/bulkStore), item-scoped,
team-expand for group registrations. Self-contained, no dependency on marks/judging.

**Phase 3 — Judge assignment + judge portal.** New `state_judge_assignments` table
(`state_event_id, item_code, user_id`). `EnsureStateJudgePortal` middleware (new — the
existing `EnsureJudgePortal` is Sahodaya-tenant-scoped and can't be reused directly since
State isn't a tenant). `StateJudgeDashboardController` mirroring `JudgeDashboardController`.

**Phase 4 — Marks entry (dual path) + double verification.** New `state_judge_scores` table
(mirrors `FestJudgeScore`: one row per item/participant/judge). `StateJudgeScoreService::save()`
+ `syncAggregatedMark()` writing into the already-existing `state_fest_marks` table once every
assigned judge has scored. Coordinator direct-entry path (`StateMarkEntryController`) for
non-panel items, gated by a new `EnsureStateMarkCoordinator` middleware. This is the phase
that actually starts populating `state_fest_marks`, which nothing writes to today.

**Phase 5 — Results publish.** `StateItemResultsService` (item-level publish) +
`StateResultsController` (event-level publish). Reuse `FestGradePointService` directly for
grade/points resolution — it's stateless scoring logic keyed off `$event->scoring_preset`
and a score, not tenant-specific; `StateFestEvent` would just need a `scoring_preset` column
added in Phase 1's migration. On event publish: recompute school-level aggregate points
(new `StateSchoolPointsService`, mirrors `EventContext::recalculateSchoolPoints` but against
`StateFestRegistration`/`StateFestParticipant`), call `StateConductService::assignChestNumbers()`
(already exists), generate certificates (Phase 7).

**Phase 6 — Appeals.** New `state_appeals` table (mirrors `FestAppeal`: `state_event_id,
participant_id, reason, fee_amount, status, resolution_note, resolved_by, resolved_at`).
Submission needs *some* portal for a Sahodaya/school to file one — likely reuses whatever
State-facing portal ends up authenticated (ties back to the P-02/named-account gap, since
today's Sahodaya coordinators have no State-side login at all, only the Sahodaya tenant
admin). `StateAppealController` for State-admin resolution, mirroring
`FestAppealController::resolve`.

**Phase 7 — Certificates.** `StateCertificateService`, reusing the existing polymorphic
`Certificate` model (`entity_type = StateFestParticipant::class`). Winner certs for
`state_fest_marks.position <= 3`, participation certs for everyone else. Template resolution
can start simple (one State-wide default template) rather than the tenant system's
item→event→tenant cascade, since there's only one State per year.

**Deliberately out of scope for v1** (flagged, not silently dropped): venues/stages/formal
scheduling (`FestVenue`/`FestStage`/`FestSchedule` equivalents) — a single State final is
plausibly small enough to run without formal venue/stage modeling for a first pilot; a
`scheduled_at` timestamp directly on the registration is enough to start. Build the full
three-level venue/stage/schedule system only if the pilot actually needs it.

## What's being built this turn

Phase 1 (lifecycle gate + schema foundation) and Phase 2 (attendance) — the smallest complete,
self-contained, low-risk vertical slice. Phases 3–7 (judging, marks, results, appeals,
certificates) are each a substantial build in their own right and should be tackled one at a
time with a checkpoint in between, not blind in one pass.
