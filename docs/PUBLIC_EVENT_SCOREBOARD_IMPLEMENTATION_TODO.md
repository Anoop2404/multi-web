# Public Event Scoreboard — Implementation TODO

**Source specification:** `docs/PUBLIC_EVENT_SCOREBOARD_MODULAR_REDESIGN_PLAN.md`  
**Reference conduct:** MCS District Kalotsav 2026 — 4 phases, 2 regional phases, 6 operational public events  
**Status legend:** `[ ]` pending, `[~]` in progress, `[x]` completed

## Non-negotiable invariants

- [x] Each operational phase/region is a standalone public `FestEvent`.
- [x] Administrative root containers are not duplicate public events.
- [x] Public item, winner, participant, schedule, and recent-result data is scoped to the requested event.
- [x] No public phase/region switcher is used; visitors choose another event from `/fest`.
- [ ] Each event owns its public name, venue, dates, contact, categories, lifecycle, and result publication.
- [ ] School/category championship points continue across phases using `Opening + Current = Closing`.
- [ ] Regional children contribute new points only; the opening balance is added once per phase.
- [ ] Locked phase balances are versioned and auditable.
- [ ] Event-local categories map to stable root championship categories before cumulative addition.
- [x] Existing user changes in dirty files are preserved.

---

## Slice 1 — Standalone public operational events

### Backend

- [x] Add a public operational-event resolver/policy.
- [x] Change `/fest` to list visible operational children separately.
- [x] Keep standard root events visible when they have no operational children.
- [x] Hide phased/partitioned administrative roots that only contain public children.
- [x] Respect `nav_hidden`, public statuses, tenant ownership, dates, and event order.
- [x] Stop redirecting public phase/region children to the root event.
- [x] Build a direct event scope with `event_ids = [selected_event_id]`.
- [ ] Keep explicit aggregate/finale handling compatible.
- [x] Apply the same selected-event scope to event home, schedule, scoreboard, results, live HTML, and live JSON.
- [x] Keep legacy scope links safe during migration without exposing sibling data.

### UI

- [x] Redesign `/fest` cards to show event name, venue, date/range, type, status, and available action.
- [x] Remove phase/region scope navigation from standalone event pages.
- [x] Add `Back to all events` links.
- [x] Ensure every URL uses the selected operational event ID.
- [ ] Show an optional non-navigational `Part of {root}` label.
- [x] Preserve existing participant/winner visibility behavior.

### Tests

- [x] Standard root event remains public.
- [x] Administrative root is hidden when operational children exist.
- [x] Phase and region children appear as separate cards.
- [x] Child event page does not redirect to root.
- [x] Child schedule/results/scoreboard/live use only child data.
- [x] Published child works while root is incomplete.
- [x] Unpublished child does not leak while root is published.
- [x] Existing user fixes for group-result deduplication remain green.

### Slice 1 exit gate

- [x] Targeted public feature tests pass.
- [x] PHP formatting passes on touched files.
- [x] `git diff --check` passes.
- [x] No unrelated dirty file is changed.

---

## Slice 2 — MCS four-phase topology and public identity

### Phase/event generation

- [ ] Configure Phase 1 Digi Fest as one central operational event.
- [ ] Configure Phase 2 Off Stage with Nilambur and Tirur only.
- [ ] Configure Phase 3 Sargadhara with Tirur and Manjeri only.
- [ ] Configure Phase 4 District Kalotsav as one central/final operational event.
- [ ] Generate exactly six operational events.
- [ ] Prevent Cartesian generation of unsupported phase/region combinations.
- [ ] Make topology synchronization idempotent.
- [ ] Preserve approved public-title, venue, date, and contact overrides on resync.

### Event identity

- [ ] Add/confirm public short title.
- [ ] Add/confirm event-local venue and date range.
- [ ] Model public coordinator/contact visibility.
- [ ] Add public display order and same-day secondary ordering.
- [ ] Validate missing public identity before event publication.

### School routing

- [ ] Resolve school region per phase, not globally.
- [ ] Route registrations using phase-specific school-region selection.
- [ ] Prevent a school from being routed to a region not enabled for that phase.

### Tests

- [ ] Exact MCS six-event fixture.
- [ ] No Off Stage Manjeri event.
- [ ] No Sargadhara Nilambur event.
- [ ] Correct venues/dates/contacts for all six events.
- [ ] Correct chronological listing.
- [ ] Phase-specific school routing.

---

## Slice 3 — Event-owned categories

### Category contract

- [ ] Implement `EventCategoryResolver`.
- [ ] Resolve explicit event category configuration first.
- [ ] Resolve selected named `FestClassCategoryScheme` second.
- [ ] Support legacy `FestEventClassGroup` rows.
- [ ] Map legacy `class_group`/`age_group` values without making them the new source of truth.
- [ ] Return event category key/ID, label, order, eligibility, award flags, and enabled state.
- [ ] Block category topper publication when included items have invalid/unmapped categories.

### Championship mapping

- [ ] Add stable root championship categories.
- [ ] Map each included event category to `championship_category_id`.
- [ ] Support event-only categories excluded from cumulative totals.
- [ ] Preserve historical mapping/labels after publication.
- [ ] Version category merge/split changes after a phase closes.

### Consumers

- [ ] Public filters.
- [ ] School category standings/toppers.
- [ ] Student category toppers.
- [ ] Item-result search.
- [ ] Recent Results cards.
- [ ] PDFs/exports/display mode.

### Tests

- [ ] Two events with different category sets do not leak labels/options.
- [ ] Label changes do not move historical points.
- [ ] Included but unmapped categories block consolidation.
- [ ] Excluded categories remain event-local.

---

## Slice 4 — Cumulative championship ledger

### Schema

- [x] Add `fest_score_contributions` migration/model.
- [x] Add `fest_phase_score_snapshots` migration/model.
- [x] Store root, phase, source event, school, source category, championship category, points, and publication version.
- [x] Store opening, phase contribution, closing, rank, version, and lock/publish audit metadata.
- [x] Add uniqueness constraints preventing retry/republish duplication.
- [x] Add indexes for root/phase/category/school public queries.

### Service

- [x] Implement `FestCumulativeChampionshipService`.
- [x] `openingBalance(root, phase)`.
- [x] `eventContribution(event)`.
- [x] `phaseContribution(root, phase)`.
- [ ] `previewClosing(root, phase)`.
- [x] `consolidateAndLock(root, phase, actor)`.
- [x] `closingSnapshot(root, phase, version)`.
- [x] `recalculateDownstream(root, fromPhase, actor)`.
- [x] Reuse existing grade-point calculation and competition-rank rules.

### Continuity rules

- [x] Phase 1 opening is zero.
- [x] Phase N opening equals Phase N-1 locked closing.
- [x] Regional events share the same opening for display.
- [x] Consolidation sums only new regional contributions.
- [x] Previous opening is added once per phase.
- [x] Stable `school_id` retains continuity across phase-region changes.
- [x] Required missing/unpublished regional events block official close.
- [x] Ranks are recalculated from closing totals.

### Correction workflow

- [ ] Add correction request and authorization.
- [ ] Preview all downstream affected phases.
- [x] Unpublish affected cumulative boards.
- [x] Create a new source/phase snapshot version.
- [x] Recalculate downstream phases in order.
- [x] Audit actor, reason, version, invalidation time, and invalidating actor.
- [ ] Invalidate versioned caches.

### Tests

- [ ] Phase 1 → 2 → 3 → 4 school/category continuity.
- [x] Regional opening is not duplicated.
- [x] Retry and republish are idempotent.
- [x] Correction cascades downstream once.
- [ ] Unauthorized correction cannot alter a locked snapshot.
- [ ] Category and school-overall reconciliation.

---

## Slice 5 — Scoreboard and topper UX

### Shared view model

- [ ] Implement `PublicEventScoreboardViewService`.
- [ ] Return event identity/publication/category/filter metadata.
- [ ] Return `opening`, `this_event`, `this_phase`, `closing`, and snapshot status.
- [ ] Return event-local and cumulative topper modes.
- [ ] Batch medal/winner/school data.

### Public scoreboard

- [ ] Standard events: Rank, School, Medals, Total.
- [x] Phase events: Rank, School, Opening, This Event, This Phase, Cumulative.
- [x] Category breakdown uses locked cumulative totals.
- [x] `Championship Standing After This Event` as default.
- [ ] `This Event Only` as a secondary result view.
- [ ] Provisional/official/corrected status labels.
- [x] Snapshot phase name and version.
- [ ] Mobile cards and accessible table semantics.

### Toppers

- [ ] School Overall Top 3 cumulative by default.
- [ ] School Category Top 3 cumulative by default.
- [ ] This Event Only school topper option.
- [ ] Student Category Top 3 event-local until cumulative student rules are approved.
- [ ] Joint-rank/tie presentation.

### Tests

- [ ] Default cumulative vs This Event Only parity.
- [ ] Category topper reconciliation.
- [ ] Provisional data cannot appear as official.
- [ ] Mobile/accessibility browser coverage.

---

## Slice 6 — Result discovery and participant cards

### Routes/pages

- [ ] Per-event Recent Results page.
- [ ] Optional tenant-wide Recent Results page, registered before `/fest/{event}`.
- [ ] Per-event item-result search by name/code/category/type/date.
- [ ] Canonical item-result detail.
- [ ] PDF and winner-poster links.

### Participant cards

- [ ] Implement `PublicResultEntryPresenter`.
- [ ] Individual card: photo, name, school, category, rank, grade/result.
- [ ] Safe initials/avatar fallback.
- [ ] Pair/trio/group/team card with every approved performer.
- [ ] Never treat the marked participant as the complete roster.
- [ ] Never truncate the full result/PDF/accessibility roster.
- [ ] Batch-load participants, students, photos, and schools.

### Publication/security

- [ ] Use official item publication timestamp.
- [ ] Item-level visibility gate when item-wise publishing is enabled.
- [ ] Remove unpublished/corrected results from feeds/search/cache.
- [ ] Rate-limit public search.

### Tests

- [ ] Recent result ordering and pagination.
- [ ] Item search/filter combinations.
- [ ] Cross-event item rejection.
- [ ] Complete group roster and photo privacy.
- [ ] No N+1 queries for result cards.

---

## Slice 7 — Admin operational-event and phase-close workspace

- [ ] Generated event review table.
- [ ] Public identity, venue, dates, contact, category, and visibility controls.
- [ ] Per-event registration/schedule/marks/result readiness.
- [ ] Previous snapshot readiness.
- [ ] Required vs finalized regional contribution count.
- [ ] Phase consolidation preview.
- [ ] Lock closing/create next opening action.
- [ ] Correction impact/recalculation UI.
- [ ] Public preview and direct public links.
- [ ] Audit timeline.
- [ ] Preserve manual overrides during topology sync.

---

## Slice 8 — Billing separation

- [ ] Keep public events independent from billing batches.
- [ ] MCS Level 1 batch covers Digi Fest + both Off Stage region events.
- [ ] MCS Level 2 batch covers both Sargadhara events + District Kalotsav.
- [ ] Count a unique student once per configured batch, not per public event.
- [ ] Support included item and extra-item rules across a batch.
- [ ] Support school, unique-student, per-item, phase, group base, and per-member rules.
- [ ] Add fee explanation and sample calculation.
- [ ] Snapshot invoice rules after generation.
- [ ] Test that six public events do not create six unintended fees.

---

## Slice 9 — Performance, export, accessibility, rollout

- [ ] Scoped JSON refresh without full-page reload.
- [ ] Versioned cache keys and targeted invalidation.
- [ ] Pause polling on hidden tabs.
- [ ] Print/PDF/export filenames include event identity.
- [ ] Display/projector mode.
- [ ] Keyboard, screen-reader, alt-text, focus, and reduced-motion QA.
- [ ] Event-day load test and query-budget assertions.
- [ ] Pilot standard, regional, and final events.
- [ ] Reconcile every public total with admin reports.
- [ ] Feature-flagged rollout and documented rollback.

---

## Required open decisions

- [ ] Does District Kalotsav re-compete regional qualifiers or contain different items?
- [ ] Which regional event points contribute to the MCS championship?
- [ ] Are student category toppers cumulative or event-local only?
- [ ] What are the exact root championship categories and mappings?
- [ ] What tie-break rules apply to school/category championships?
- [ ] What are the confirmed Level 2 fee amounts/rules?
- [ ] What appeal/correction authority can reopen a locked earlier phase?

## Final release gate

- [ ] Six MCS operational events generated exactly.
- [ ] No public phase/region navigation.
- [ ] Event-local result isolation proven.
- [ ] Phase 1–4 school/category continuity proven.
- [ ] Opening balance added once through regional consolidation.
- [ ] All topper and item result views reconcile.
- [ ] Individual/group participant-card requirements pass.
- [ ] Publication and correction paths are secure and audited.
- [ ] Billing batches do not double-charge generated events.
- [ ] Focused, full feature, and browser test suites pass.
