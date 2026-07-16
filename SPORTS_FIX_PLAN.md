# Sports Section — Consolidated Fix Plan
_2026-07-16. Supersedes SPORTS_REBUILD_TODO.md (findings folded in). Target architecture as of commit `fb2de80`: sports is **non-singleton**; every sport is its own standalone FestEvent; season hub and Event Heads are legacy for sports._

## Why each reported symptom happens

| Symptom | Root cause |
|---|---|
| School still shows **old event name** (season hub), not per-sport events | Tenant DB rows `fest_competition_types.sports.is_singleton` are still `1` — migration `2026_08_20_000003` runs only via `tenants:migrate`, and `FestCompetitionTypeRegistry::ensureDefaults()` uses `firstOrCreate` (never updates existing rows). So `isSingletonType('sports')` is still true → school `index()` singleton short-circuit (guard removed in fb2de80) redirects to `primaryHub()` = the old season hub. Hub also isn't hidden/filtered anymore (`$hideSportsSeasonHub` removed). |
| **Items not shown / can't register** on school side | (a) Hub page has zero items (all moved to sport events). (b) Sport events stuck in `draft` are excluded by `listedForSchool()`. (c) c03ad13 made `FestItemRegistrationGate` require `event.status = registration_open` + windows for sports — events synced without windows/status flip block every item. (d) Custom (non-catalog) heads never got a sport event (`syncSeason()` loops catalog defs only). |
| **Head creation & head names still on pages** | `item-heads.*` routes + `ItemHeads.vue` still live for sports; `hydrateEventForSchoolRegistration()` still builds `head_navigation` from relinked `FestItemHead` rows; head-wise reports, chest numbers, Settings `itemHeads` payload, `/sports/heads/{head}` route. |
| **No option to delete events** | `FestEventController::destroy()` exists but sports UI doesn't expose it; worse, `syncSeason()` (still triggered via `syncEventHeads()` on report/results/catalog page loads) recreates one child per catalog sport unconditionally — deleted sports resurrect, and sports the Sahodaya doesn't conduct get created for every tenant. |
| **Old registrations not migrated** | Auto-sync `ensureItemsOnSportEvent()` moves items but never remaps `fest_registrations` / `fest_participants` / marks / fees, and it nulls `head_id` — which breaks `fest:migrate-sports-head-to-event` (`migrateStrayRowsToSportEvent()` matches items by `head_id`), so the only reg-migration code now matches nothing. |
| Hidden breakage everywhere | fb2de80 stubbed `FestEvent::isSportsSeasonEvent()` to `return false` and made `isSportsDisciplineEvent()` true for **every** sports event. Dead callers: `FestEventController:322` (post-update sync never runs), `:862` (hub→/sports redirect never fires), `FestSchoolEventFeeService:278` (season branch unreachable). |

---

## Phase 1 — Data & flag repair (run first; fixes school visibility)

1. **Model predicates** (`app/Models/FestEvent.php`): restore partition-aware logic —
   `isSportsSeasonEvent()`: `event_type=sports && parent_event_id=null && partition_role in (null,'sports_season') && has discipline children or heads`;
   `isSportsDisciplineEvent()`: sports && (`partition_role='sports_discipline'` || `parent_event_id!=null` || standalone new-flow event). Re-audit the 3 dead callers after restoring.
2. **Singleton flag**: run `php artisan tenants:migrate` everywhere; change `ensureDefaults()` to also **update** `is_singleton` (and label/slug) on `is_system` rows so config is authoritative; add safety guard `&& $eventType !== 'sports'` back into `FestRegistrationController::index()` singleton short-circuit (admin side at `FestEventController:75` already has it — keep them consistent).
3. **Registration backfill command** `fest:backfill-sports-registrations` (idempotent, `--dry-run`, all tenants, safe-tenancy pattern from 4c129bd):
   - sports regs where `fest_registrations.event_id != item.event_id` → set to item's event; same via `registration_id` for `fest_participants`; remap `fest_marks`, `fest_schedules`, `fest_event_staff`;
   - consolidate `fest_school_event_fees` hub/head rows onto sport events (reuse `consolidateFees()`);
   - fix `migrateStrayRowsToSportEvent()` to match by `item.event_id`, not the already-nulled `head_id`.
4. **Make sync safe going forward**: `ensureItemsOnSportEvent()` moves regs/participants/marks/fees in the same transaction as the item move.
5. **Hide the old season hub from schools**: when a sports event has discipline children (or after backfill), set `nav_hidden = true` on the hub → `visibleToSchool()`/`listedForSchool()` hides it everywhere (list, ProgramHub stats, API) with no per-query filters. Keep hub reachable admin-side only for the medal-tally/remittance rollup.
6. **Rename check**: schools should see per-sport titles ("Athletics 2026-27") — after hub hidden, verify no page falls back to the hub title.

## Phase 2 — School registration flow works end-to-end

1. **Status/window propagation**: opening registration must be a per-sport action that actually sets `status='registration_open'` + windows (`registration_open/close`, `event_reg_*`). Add a bulk "open all sports" action on the sports list. Audit gate chain after c03ad13: `isRegistrationOpen()` (status hard-check) → `FestItemRegistrationGate` → `FestItemWindowResolver` (event `reg_start/reg_end` now feed sports fallback) — ensure synced/migrated events got windows copied from heads (`copyHeadFeesIfEmpty` does this only when fees empty — copy windows unconditionally when target null).
2. **`require_event_registration`**: c03ad13 enforces student event-registration for sports. Decide default per sport event; backfill the flag; make sure the school UI clearly shows the "register students to event first" step (currently a silent 422 otherwise).
3. **Custom sports**: replace head-creation on sports with "Add sport event" (see Phase 3.2); for existing custom heads without a sport event, promote them in the backfill (create event from head, move items/regs).
4. **E2E acceptance**: school → Sports → sees only open per-sport events → opens one → items grouped by age → registers a student & a team → fees compute at event level → old registrations visible under the right sport.

## Phase 3 — Remove Event Head UI/data from sports (admin + school)

1. **School side**: stop building `head_navigation` + `school_head_fees` for sports in `hydrateEventForSchoolRegistration()`; strip head filter/tabs from `Registration.vue` (`sportsHeadOptions`, `selectSportsHead`); retire `EventItemRegistration.vue` / `SportsEventItemRegistrationPanel.vue` (entry already redirects); school reports group by sport event, not head.
2. **Head creation**: gate all `item-heads.*` routes (store/sync/windows/notifications/destroy) with `abort_if($event->event_type === 'sports')` → redirect to Setup hub; `ItemHeads.vue` nav entries removed for sports; commit the WIP diff (ItemHeadOps redirect + Competition tab removal) as part of this.
3. **Head names on pages**: reports `ByHead.vue`, `HeadWiseParticipants.vue`, `BuildsItemHeadReportContext`, `FestReportController` heads dropdown; ChestNumbers (`chest_head_id`) → scope by sport event; `FestEventController` Settings `itemHeads` payload + `Settings.vue`/`RegistrationTab.vue` head sections; decide fate of `/sports/heads/{head}` permalink (keep as redirect-to-event only).
4. **Notifications**: notification settings for sports live on the sport FestEvent (`notification_settings` column exists); make `FestEventNotifier` read only from the event; remove head-row editing path (`updateNotifications`).
5. **Head-row cleanup command**: after Phases 1–2 verified, delete `FestItemHead` rows whose `event_id` points at a sports event (keep `event_id=null` catalog templates for Kalotsav-style programs).

## Phase 4 — Sport event lifecycle (create / disable / delete)

1. **Stop unconditional resurrection**: `syncSeason()` runs via `syncEventHeads()` from report/results/catalog page loads. Either (a) stop auto-creating events for every catalog sport — create only from an explicit admin action ("Add sport"), or (b) respect a per-catalog-sport `is_enabled` toggle per tenant. Deleted sports must stay deleted (tombstone: skip catalog keys that had an event soft/hard-deleted, or check `is_enabled`).
2. **Delete/disable UI**: expose on the sports list + event settings — **Disable** (nav_hidden, keeps data), **Delete** (only when zero registrations; otherwise block with count and offer disable). Guard `destroy()` for sports accordingly (currently deletes with no reg/child checks → orphans regs and children).
3. **Season hub**: with children/regs migrated, keep exactly one hidden hub per year for medal tally + season remittance (`FestSportsChecklist::seasonRemittanceBanner`), or drop the hub entirely and move the rollup to the sports list page — pick one and delete the dead branch (`FestEventController:322/:862`).

## Phase 5 — Consistency, tests, ops

- Comment/behavior mismatch: `schoolListStatusesForType()` says sports lists only after reg opens but returns `published+` for all — align code or comment.
- `ProgramHubDataService::schoolFestHub()` — exclude hidden hub from `open_events`/cards (fixed automatically by Phase 1.5 if nav_hidden used).
- Remove deprecated `fest:promote-sports-heads`; review `MergeDuplicateSportsHeads`/`CheckSportsDuplicateHeads` still relevant post-cleanup.
- Public pages: verify `FestPublicVisibilityService` + public sports pages list sport events, never the hidden hub or head names.
- Tests: singleton short-circuit never fires for sports; hub hidden when children exist; item gate opens when status+window set; backfill idempotency; custom-sport creation; delete blocked with regs; head routes blocked for sports.
- Ops runbook per tenant: `tenants:migrate` → `fest:backfill-sports-registrations --dry-run` → apply → spot-check one legacy + one fresh tenant (school list, items, old regs, fees).

## Order & size

Phase 1 (small, unblocks schools) → Phase 2 (small-medium) → Phase 3 (wide but mechanical) → Phase 4 (needs the one product decision: hub kept as rollup vs dropped) → Phase 5.

**Decisions needed from you:**
1. Season hub: keep as hidden rollup (medal tally/remittance) or remove entirely?
2. Auto-create sport events from catalog at all, or only explicit "Add sport"?
3. `require_event_registration` default for sports: on or off?
