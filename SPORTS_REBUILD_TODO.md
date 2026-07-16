# Sports Head-First Rebuild — Remaining Work TODO
_Analysis date: 2026-07-16. Phases 0–3 are committed; three gaps remain: (A) legacy Event Head data still rendered on many pages, (B) items for new-flow sport events not visible school side, (C) existing registrations never migrated._

---

## Why already-registered data was NOT migrated (root cause)

1. **The auto path never touches registrations.** `FestSportsEventSyncService::ensureItemsOnSportEvent()` (runs on every season page load) moves `FestEventItem` rows from the season hub to the child sport event and nulls `head_id` — but it never updates `fest_registrations.event_id`, `fest_participants.event_id`, marks, schedules, or `fest_school_event_fees`. Old registrations still point at the season hub while their items moved to the sport event.
2. **The manual migration command is now a no-op.** `fest:migrate-sports-head-to-event` → `migrateStrayRowsToSportEvent()` selects items via `whereIn('head_id', $headIds)` — but the auto-sync already set `head_id = NULL` on those items, so the command finds zero items and migrates zero registrations. The only code that remaps registrations is unreachable.
3. **Result:** school registration lists load regs `whereIn('event_id', [visible sport events])`; old regs have `event_id = season hub` → invisible. Fee recalc, quotas, chest numbers, and counts on sport events all miss them too.

---

## P0 — Data repair (do first, blocks everything else)

- [ ] **New idempotent backfill command** `fest:backfill-sports-registrations` (all tenants):
  - For every sports registration where `fest_registrations.event_id != item.event_id` → set reg `event_id = item.event_id`.
  - Same remap for `fest_participants` (via `registration_id`), `fest_marks`, `fest_schedules`, `fest_event_staff`.
  - Consolidate `fest_school_event_fees` season-hub / head-scoped rows onto the sport events (reuse `consolidateFees()` logic from `MigrateSportsHeadToEvent`).
  - `--dry-run` + per-tenant counts; follow the safe-tenancy pattern from commit 4c129bd.
- [ ] **Fix the sync service going forward:** `ensureItemsOnSportEvent()` must move registrations/participants/marks/fees in the same transaction as the item move (mirror Phase-0 lesson from commit 36b291d — participant `event_id` gap).
- [ ] **Fix or retire `migrateStrayRowsToSportEvent()`** — match by item ids actually moved (or by `item.event_id`), not by `head_id` (already nulled).
- [ ] Verify after run: no sports reg whose `event_id` differs from its item's `event_id`; chest/level-reg numbering sequences see all participants.

## P1 — School side: items for new event flow not showing

Root causes found (commit `fb2de80` regressions + promotion gap):

- [ ] **Singleton flag per tenant.** fb2de80 removed the `$eventType !== 'sports'` guard from the singleton short-circuit in `FestRegistrationController::index()`, relying on tenant migration `2026_08_20_000003_sports_non_singleton`. Any tenant DB where `fest_competition_types.sports.is_singleton` is still `1` redirects schools to the **season hub's** registration page — which now has **zero items** (all moved to children). Actions:
  - Confirm `tenants:migrate` ran everywhere; make `FestCompetitionTypeRegistry::ensureDefaults()` correct `is_singleton` on existing rows, not just insert missing ones.
  - Re-add the cheap `&& $eventType !== 'sports'` guard as defense-in-depth.
- [ ] **Season hub back in the school list.** fb2de80 also removed the `$hideSportsSeasonHub` filter. Schools now see the empty hub alongside sport events. Preferred fix: set `nav_hidden = true` on the season hub once children exist (hides it via `visibleToSchool()` everywhere — list, hub page, API) instead of per-query filters; keep hub visible only as fallback when no children exist. Apply same filter to `ProgramHubDataService::schoolFestHub()` (hub currently inflates `open_events`/event cards).
- [ ] **Custom (non-catalog) Event Heads never promoted.** `syncSeason()` iterates only `catalogHeadDefinitions()` (`cksc_sports_heads.php`); a head an admin creates in the new flow with a custom name gets no child FestEvent → its items stay invisible to schools forever. `promoteIfSeason()` therefore silently skips them. Action: after the catalog loop, promote every remaining `FestItemHead` with `event_id = season` and no `discipline_event_id` into its own sport event (reuse the legacy-head branch).
- [ ] **Status propagation.** New sport events inherit season status only when season is `registration_open/ongoing/published` and child is `draft/published`. A sport created while season is draft stays draft → filtered out by `listedForSchool()`. Decide: propagate status on season open, or surface per-sport "open registration" clearly in Setup hub. Also fix stale comment on `schoolListStatusesForType()` (says sports = reg-open-only, code returns published+ for all).
- [ ] E2E check: school → Sports → sees each conducting sport → opens one → items grouped by age group render → can register.

## P2 — Remove Event Head data/UI from pages (Head = Event now)

School side:
- [ ] `FestRegistrationController::hydrateEventForSchoolRegistration()` — stop building `head_navigation` for sport events (each sport now shows one redundant head tab from relinked legacy `FestItemHead` rows). Drop `school_head_fees` / `uses_per_head_billing` payload for sport events (always event-level now).
- [ ] `Registration.vue` — remove sports head filter/tab UI (`sportsHeadOptions`, `selectSportsHead`, `head_navigation` reads); keep age-group grouping only.
- [ ] `EventItemRegistration.vue` + `SportsEventItemRegistrationPanel.vue` — legacy "register by head" page; entry already redirects (`itemRegistrationEntry`), remove/retire the page itself.
- [ ] School reports (`FestSchoolReportController`, `ReportHeadWise.vue`) — group by sport event instead of head for sports.

Sahodaya side:
- [ ] Commit the uncommitted WIP: `FestItemHeadOpsController::index()` sports redirect + `SportsSetupSubNav.vue` Competition tab removal. Also gate the remaining `item-heads.*` routes (store/sync/windows/notifications/destroy) for sports events.
- [ ] `FestItemHeadController::store()` on a sports season — currently creates a head row that (if non-catalog) never promotes. Replace with "create sport event" flow, or make store immediately create the child event and return its URL.
- [ ] `FestItemHeadController::destroy()` — deleting a head leaves its promoted discipline event live for schools. Define behavior: archive/hide the sport event (and block if registrations exist).
- [ ] `ItemHeads.vue` page for sports → redirect to Setup hub (same as ItemHeadOps).
- [ ] Reports: `ByHead.vue`, `HeadWiseParticipants.vue`, `BuildsItemHeadReportContext`, `FestReportController` heads dropdown (line ~122) — switch sports to per-sport-event grouping.
- [ ] Chest numbers (`chest_head_id`, `ChestNumbers.vue`) — sports should scope by sport event, not head.
- [ ] Settings (`FestEventController` ~762 `itemHeads` payload, `Settings.vue`, `RegistrationTab.vue`) — hide head-based sections for sports.
- [ ] Notifications: per-head notification settings (Phase 3) now live on `FestItemHead` rows relinked to sport events — move/read them from the sport FestEvent (`notification_settings` already copied by `copyHeadFeesIfEmpty`); make head rows non-authoritative.

Data cleanup (after P0 + P2 UI ships):
- [ ] Delete or detach leftover `FestItemHead` rows whose `event_id` points at a sport event (keep only `event_id = NULL` catalog templates). One-time cleanup command; extend `fest:merge-duplicate-sports-heads` learnings.
- [ ] Remove deprecated `fest:promote-sports-heads` alias once nothing references it.

## P3 — Verification & guardrails

- [ ] Feature tests: singleton redirect never fires for sports; season hub hidden when children exist; custom head → sport event promotion; registration backfill idempotency; school items visible for a newly created sport.
- [ ] Run `fest:check-sports-duplicate-heads` across tenants after cleanup.
- [ ] Manual pass per tenant: one legacy tenant (pre-rebuild data) + one fresh tenant.

---

### Suggested order
P0 backfill → P1 visibility fixes (schools unblocked) → P2 UI removal → P3 cleanup/tests. P0 and P1 are small, high-impact; P2 is wide but mechanical.
