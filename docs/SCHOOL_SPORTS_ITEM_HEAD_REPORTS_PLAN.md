# School Admin — Sports Item/Head Reports: Fix Plan

Scope: `school-admin/{sahodaya}/sports/reports/{event}` and everything it links to.
Status: **implemented (24 Jul 2026).** See §9 for what was actually built, including several bugs found only once the fix was underway (broader than this original plan).

## 1. The problem in one paragraph

For a sports meet, the "event" a school admin opens is a **season hub** — a container `FestEvent`. Each Event Head (e.g. Athletics, Kabaddi) is **auto-promoted to its own hidden child `FestEvent`** (`FestItemHeadService::syncEventHeads()`). Real `FestEventItem`, `FestRegistration`, `FestParticipant`, `FestMark`, and per-head `FestSchoolEventFee` rows all attach to the **child event's id**, never the season hub's id. Two services were updated for this architecture (`FestEventReportAnalyticsService::headWiseSummary()`'s sports branch, and `FestHeadItemNavigationService`). Every other report-row builder in the same analytics service was not, so it still filters directly by `event_id = $seasonHub->id` — which is always empty for a season hub. Result: the summary tiles on a report page are correct, but the drill-down/detail table on the very same page is empty or zero. This is the "many reports are item/head based ... broken" the user is pointing at.

There are two smaller, unrelated gaps riding along in the same controller/service pair: `feeSummary()` picks an arbitrary fee row instead of the correct rollup, and it's blind to `FestFeeCredit` the way several other reports were before this session's earlier credit-visibility work.

## 2. Affected reports

| # | Report | Controller method | Vue page | Bug | Root cause |
|---|---|---|---|---|---|
| 1 | Head-wise participants (drill-down) | `FestSchoolReportController::headWise()` | `ReportHeadWise.vue` | Participant list always empty when a head is selected | `headWiseParticipantRows()` queries `FestItemHead`, which sports events never populate |
| 2 | Head-wise participants PDF export | `FestSchoolReportExportService::headWisePdf()` | — | Same empty export | Calls the same `headWiseParticipantRows()` |
| 3 | Item registration counts | `FestSchoolReportController::itemCounts()` | `ReportItemCounts.vue` | Item table shows zero rows while the "by Sport Event" summary above it shows real numbers — internally inconsistent page | `itemRegistrationRows()` filters `FestEventItem` by `event_id = season hub id` |
| 4 | Item-wise participants | `FestSchoolReportController::itemWise()` | `ReportItemWise.vue` | Selecting any item from the dropdown returns zero participants | Query filters `FestRegistration.event_id = season hub id`, but real registrations sit on the child event id |
| 5 | Discipline participation | `FestSchoolReportController::disciplineParticipation()` | `ReportDisciplineParticipation.vue` | Empty, despite being shown specifically when the event *is* a multi-discipline season hub | `disciplineRegistrationRows()` same direct `event_id` filter |
| 6 | Fee summary | `FestSchoolReportController::feeSummary()` (via `FestSchoolReportAnalyticsService`) | `ReportFeeSummary.vue` | Can under-report due/paid for per-head-billed events; never shows outstanding credit | Unordered `FestSchoolEventFee::where(...)->first()` with no `head_id`/rollup handling, no `outstandingCredit()` |
| 7 | Head-wise due/collected totals (non-sports branch, shared method) | `FestEventReportAnalyticsService::headWiseSummary()` | Sahodaya-admin `HeadWiseParticipants.vue` (not currently rendered on School Admin, but same shared method) | `due_total`/`collected_total` always 0 for non-per-head-billing fee models | Filters `FestSchoolEventFee` by `head_id = $head->id` unconditionally, ignoring `usesPerHeadBilling()` |

Assignment completeness, numbering register, pending approvals, and student/teacher-wise reports share the same "direct `event_id` filter" pattern (`assignmentCompletenessRows()`, `numberingRegisterRows()`, `pendingApprovalRows()`, controller's `studentWise()`/`itemWise()`) — flagged for the same fix, lower priority since they weren't the ones named, but they will break identically the first time someone opens them for a sports season hub.

Also checked and confirmed **clean**, no changes needed:
- Item pricing (`itemRegistrationRows()`) already goes through `FestItemFeeResolver::amountForItem()`, not a raw `item.fee_amount` field.
- Registration/participant counts already use the correct active-status scoping (`FestRegistration::scopeActive()`, `['submitted','approved']`) — nothing stale.

## 3. Root cause fix — one change, reused everywhere

Every broken report-row builder needs the same resolution step that `headWiseSummary()`/`sportsWiseSummary()` and `FestHeadItemNavigationService` already do: **when `$this->event` is a sports season hub, resolve the actual child event(s) before querying `FestEventItem`/`FestRegistration`/`FestMark`/`FestSchedule`.**

Plan:
1. Add one small shared helper (e.g. `resolveReportableEventIds(FestEvent $event): array` or similar) in `FestEventReportAnalyticsService`, following the existing `sportsWiseSummary()` branch as the reference implementation: if `$event->event_type === 'sports'` and it's a season hub, return the child `FestEvent` ids (`FestEvent::where('parent_event_id', $event->id)->pluck('id')`); otherwise return `[$event->id]`.
2. Replace every direct `where('event_id', $this->event->id)` in the affected builders with `whereIn('event_id', $this->resolveReportableEventIds())`:
   - `headWiseParticipantRows()`
   - `itemRegistrationRows()`
   - `disciplineRegistrationRows()`
   - `assignmentCompletenessRows()`
   - `numberingRegisterRows()`
   - `pendingApprovalRows()`
   - Controller-level `studentWise()` / `itemWise()` in `FestSchoolReportController`
3. For `headWiseParticipantRows()` specifically: since sports events use child-`FestEvent`-as-head rather than `FestItemHead` rows, this method additionally needs a sports branch that resolves "the head" to a child event id (matching how `navigationForEvent()`/`sportsNavigation()` already interpret `head_id` as a child-event id for sports) rather than looking up `FestItemHead::find($headId)`.
4. Re-run the same fix for `FestSchoolReportExportService::headWisePdf()` since it depends on `headWiseParticipantRows()`.

This is a single, well-understood pattern applied consistently — the risk is mechanical repetition error, not conceptual uncertainty, since the reference implementation (`sportsWiseSummary()`) already exists in the same file.

## 4. Fee summary fix

`FestSchoolReportAnalyticsService::feeSummary()`:
1. Replace the unordered `->first()` with the same rollup-safe pattern already used elsewhere in this codebase: prefer the `head_id IS NULL` aggregate row when `usesPerHeadBilling()` is true (mirroring `FestSchoolEventFeeService::currentFeeRecordFor()` and the model's own `scopeForAmountAggregation()`/`withoutDuplicateRollups()` scopes — reuse one of these rather than re-deriving the logic a third time).
2. Add `'available_credit' => $fee->outstandingCredit()` to the returned array, matching the exact field name/pattern already used in `FestEventReportAnalyticsService::feeCollectionRows()` for consistency across the codebase.
3. `ReportFeeSummary.vue`: add a conditional credit line, same visual pattern as `EventBillingPanel.vue`/`Fees.vue` from the earlier credit-visibility work (small emerald text, only rendered when `> 0`).

## 5. Head-wise due/collected totals fix (shared method, lower urgency)

`headWiseSummary()`'s non-sports branch: guard the per-head fee query with `usesPerHeadBilling($event)`, exactly like the sibling `feeCollectionByHeadRows()` already does — when false, fall back to the single `head_id IS NULL` rollup row divided/attributed however `feeCollectionByHeadRows()` already handles it (read that method's exact fallback before writing this, don't re-derive). This one is currently dormant on School Admin (not rendered) but live on the Sahodaya-admin equivalent page — fixing it is worthwhile since it's one shared method, but not urgent for the specific page the user hit.

## 6. What this plan deliberately does NOT include

- **N+1 query cleanup** (roughly 5-7 queries per loop iteration across `itemRegistrationRows()`, `headWiseSummary()`, `sportsWiseSummary()`, `assignmentCompletenessRows()`). This is a real performance issue but not a correctness bug, and batching it into aggregate queries is a separate, higher-risk refactor better done on its own once the correctness fixes are verified in production. Flagging it here so it isn't lost, not scoping it into this pass.
- Changing anything about how sports events are structured (season hub / child event promotion) — that's working as designed elsewhere (`sportsWiseSummary()`, `sportsNavigation()`); this plan only makes the remaining report builders consistent with it.
- Ledger/credit posting changes — out of scope, already covered by the standing decision in `docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md` §13.3.

## 7. Suggested build order

1. Add the shared `resolveReportableEventIds()` helper + apply to `itemRegistrationRows()` and `disciplineRegistrationRows()` first — these are the two the user is most likely looking at right now (item counts, item-wise, discipline participation all depend on one of these two).
2. Fix `headWiseParticipantRows()` + its sports-head resolution + the PDF export that depends on it.
3. Fix `feeSummary()` (rollup row + credit field) + `ReportFeeSummary.vue`.
4. Apply the same `resolveReportableEventIds()` swap to `assignmentCompletenessRows()`, `numberingRegisterRows()`, `pendingApprovalRows()`, and the controller-level `studentWise()`/`itemWise()` filters — same pattern, batched together since they're mechanical repeats of step 1.
5. Fix `headWiseSummary()`'s non-sports per-head due/collected totals (lowest urgency, dormant on this surface).
6. Manual verification pass per report (see §8) before calling it done — this environment has no PHP runtime/DB connection, so nothing here can be executed or tested automatically; verification will be manual code review plus the user smoke-testing against a real sports season-hub event (event 21) after deploy.

## 8. Verification checklist (manual — no runtime available here)

For each fixed report, confirm by reading the resulting query that:
- A season-hub sports event resolves to its child event ids before any `FestEventItem`/`FestRegistration`/`FestMark`/`FestSchedule` query.
- A non-sports (or leaf, non-season-hub) event still resolves to just `[$event->id]` — i.e. the fix is additive/branching, not a behavior change for every other program type that already worked.
- Summary tiles and drill-down tables on the same page now report consistent, non-zero numbers against each other for the same season-hub event.
- `feeSummary()` returns the same `total_due`/`paid` figures as the (already correct) Sahodaya-admin fee views for the same school+event.
- Brace-balance / syntax sanity check (`grep -o "{" | wc -l` vs `}`) on every touched file, same discipline as the rest of this session's edits.

Once this plan is approved, each numbered step above becomes its own implementation task, same as the payment/registration fixes earlier in this doc's sibling document.

## 9. What was actually built (24 Jul 2026)

The core fix landed as planned, plus a wider sweep once implementation started turning up the same bug in files this plan hadn't enumerated yet.

**New shared helper.** `FestEvent::reportableEventIds(): array` (model method, not a service method as originally sketched — centralizing it on the model made it reusable from controllers and every service without DI). Returns `[$this->id]` normally; for a sports season hub, `[$this->id, ...child sport event ids]`. `studentWiseBrowserRows()` (already correct, pre-existing) was refactored to call this instead of duplicating the same inline logic — now there's exactly one implementation of the branch.

**`FestEventReportAnalyticsService` — fixed to use `reportableEventIds()`:**
`disciplineRegistrationRows()`, `itemRegistrationRows()`, `assignmentCompletenessRows()`, `numberingRegisterRows()`, `pendingApprovalRows()`, `itemWiseBrowserRows()`, `teamSquadRows()` (found during the sweep — team/group item squad sheets, same bug). `headWiseParticipantRows()` got the planned sports branch, split into a new private `sportsWiseParticipantRows()` mirroring `sportsWiseSummary()`'s shape (one bucket per child sport event, `head_id` = child event id — matches the convention `sportsNavigation()` and `sportsWiseSummary()` already use, verified against `ReportHeadWise.vue`'s `?head_id=` link).

`headWiseSummary()`'s non-sports per-head `due_total`/`collected_total`/`pending_fee_total` are now only computed when `FestSchoolEventFeeService::usesPerHeadBilling($event)` is true — otherwise the keys are simply omitted from the row (the Sahodaya-admin `HeadWiseParticipants.vue` already defensively does `row.due_total ?? 0`, so this is a safe, non-breaking change there).

**`FestSchoolReportAnalyticsService` — fixed:**
`feeSummary()` now calls `FestSchoolEventFeeService::currentFeeRecordFor()` (always the `head_id IS NULL` rollup — the same record every `recalculate()` dispatch branch produces) instead of an unordered `->first()`, and returns a new `available_credit` field. `attendanceRows()`, `publishedResultsRows()`, `resultsPublishStatus()`, `resultsSummary()`, `itemParticipantDetails()` — found during the sweep, all had the identical direct-`event_id` bug (attendance sheets, published results, and results status were all silently empty for a season hub) — all now use `reportableEventIds()`.

**`FestSchoolReportController` — fixed:**
`studentWiseLookups()` (private, feeds both the screen and CSV export), `itemWise()`, `exportItemWise()`, `exportItemWisePdf()`. The latter two also had a second bug: they resolved the item via `$event->items()->findOrFail($itemId)` / `->first()`, which is always empty for a season hub (items live on `FestEventItem.event_id = child id`) — replaced with a direct `FestEventItem::whereIn('event_id', $event->reportableEventIds())` lookup.

**`FestItemResultsService`** (found during the sweep — feeds both School Admin's `resultsPublishStatus()` and the Sahodaya-admin `FestResultsController`): `itemSummaries()` and `resultRowsForItem()` had the same direct-`event_id` bug; fixed the same way.

**`FestSchoolReportExportService::itemParticipantsExcel()`** (found during the sweep): used `$event->items()->find($itemId)` only to look up a title for the export filename — cosmetic, not a data bug (the actual rows were already fixed upstream), but corrected for consistency.

**`FestRegistrationRegisterService`** (found during the sweep, not in the original plan table — Registration Register report, `registrationRegister()`/`exportRegistrationRegister()`/`exportRegistrationRegisterPdf()`): had the same direct-`event_id` bug on both registrations and fee lookups. This one needed more care than a plain `whereIn` swap: under a season hub, a school's fee is spread across multiple per-sport `FestSchoolEventFee` rows (one per child event, matching `sportsWiseSummary()`'s billing model — there is no season-hub-level fee record). The original code did `->keyBy('school_id')`, which would have silently kept only one sport's fee row per school once the `event_id` filter started matching more than one row. Fixed by grouping and summing per school (`total_due` summed, `status` = approved only if every row is approved, else `partial` if any is, else the first row's status) before building rows/summaries. `rowFromParticipant()` and `schoolSummaries()` were updated to consume this aggregate array instead of a single `FestSchoolEventFee` model.

**`ReportFeeSummary.vue`:** added a conditional "credit owed to you" line, same visual pattern as `EventBillingPanel.vue`/`Fees.vue`.

### 9.1 Deliberately not touched — flagged, not fixed

`FestSchoolEventFeeService::recalculate()` is dispatched with whatever `FestEvent` it's given, and `recalculateForSportsEvent()` (its sports branch) bills directly at `$event->id` — it has no season-hub awareness of its own; every other place that reads sports fees (`sportsWiseSummary()`, this fix's own aggregation) works with child sport events specifically, never the hub. `FestRegistrationRegisterService::schoolSummaries()` still calls `recalculate($event, $sid)` as a fallback when a school has registrations but no fee row yet — if `$event` is a season hub, this preserves whatever `recalculate()` already does today (which may itself be a latent bug: creating a `FestSchoolEventFee` row keyed to the hub, inconsistent with how every other sports fee row is keyed to a child event). This was **not** changed, because fixing it means changing `recalculate()`'s own dispatch/mutation behavior — a financial write path — not a report read. Rushing that fix without being able to run migrations or hit a real database in this environment was judged too risky. Flagging it here as a follow-up: audit whether `recalculate()` is ever actually invoked with a season-hub `$event` in production, and if so, give it the same child-event resolution treatment on the write side.

### 9.2 Verification performed

Brace-balance check (`grep -o "{" | wc -l` vs `}`) on every touched PHP/Vue file — all matched. No PHP runtime or database available in this environment, so nothing here was executed; verification beyond static review requires the user to smoke-test against a real sports season-hub event (e.g. event 21) after deploy — specifically: open Head-wise, Item counts, Item-wise, Discipline participation, Fee summary, Attendance, Published results, and Registration Register for that event and confirm the drill-down tables now show data consistent with the summary tiles above them.
