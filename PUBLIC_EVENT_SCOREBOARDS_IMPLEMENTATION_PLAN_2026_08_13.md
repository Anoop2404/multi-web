# Public Event Scoreboards — Implementation Plan

**Date:** 2026-08-13  
**Scope:** Public scoreboards and published results for standard, region-based, cluster-based, finale, phase-based, sports-house, and State events.  
**Status:** Core tenant public-page implementation completed on 2026-08-13; advanced publication modes and State parity remain planned.

### Implementation progress

Completed in the first delivery:

- separate hub, schedule, scoreboard, results, and live pages for every standard `FestEvent`;
- one parent public page with Overall and Region/Cluster/Finale scopes for partitioned events;
- canonical child-to-hub redirects and removal of partition children from `/fest` listings;
- composable partition + category filters through `PublicFestScoreboardService`;
- scope-level publication checks and prevention of unpublished standings leaking through live JSON;
- official `FestResult.published_at` stamping during publish/unpublish;
- event-day cache policies and focused public regression tests.

Still planned: configurable `off`/`official_only`/`live_unofficial` modes, independent child publication, versioned cache keys/ETags, immutable snapshots, and State presentation parity.

## 1. Outcome

Build one public scoreboard system that adapts to the event topology instead of creating separate implementations for “normal” and region-based events.

The public experience should be:

- **Standard event:** one official overall board, with optional category views.
- **Region/cluster event:** one hub page with Overall, each Region/Cluster, and Finale views.
- **Phase-based event:** one cumulative board unless phases are configured for independent publication.
- **Sports event:** school points, optional house standings, age-category boards, individual championship, and records.
- **State event:** the same presentation contract, backed by the State result projection.
- **Private until authorized:** no unpublished marks, school standings, or participant results may leak through alternate public routes.

The existing `/fest/{event}/results` and `/fest/{event}/scoreboard` surfaces should be consolidated around a single service and a single set of publication rules. Do not create a second scoreboard engine.

## 2. Current baseline

The repository already provides most of the foundation:

| Existing capability | Current implementation |
|---|---|
| Public event index and hub | `routes/tenant.php`, `FestPortalController::index/show` |
| Published school/category/item/individual results | `FestPortalController::results` |
| Published scoreboard with partition/category filters | `FestPortalController::scoreboard` |
| School ranking persistence | `fest_results` and `EventContext::recalculateSchoolPoints()` |
| Standard school scoreboard | `EventContext::scoreboardBySchoolForEvent()` |
| Region/cluster child scoreboard | `FestPartitionService::scoreboardByPartition()` |
| Combined partition scoreboard | `FestPartitionService::combinedScoreboard()` |
| Public individual championship | `results?tab=championship` |
| Hub publish/unpublish cascade | `FestResultsController::publish/unpublish` |
| Public participant privacy helper | `FestPublicVisibilityService` |
| Homepage scoreboard push | `FestCmsAutoPush` |
| Default public navigation entry | `NavConfigDefaults::forSahodaya()` → `Live Results` |

This means the work is primarily consolidation and correctness, not a greenfield build.

## 3. Gaps to close

### P0 — correctness and privacy

1. **The live route bypasses official-publication gating.** `/fest/{event}/live` and `/live/data` include `EventContext::scoreboardBySchool()` even when `results_published=false`. The published scoreboard route is gated, but the live payload can still expose standings.
2. **The public event index lists raw child events.** `FestPortalController::index()` does not restrict the query to public root/hub events, so Region and Finale children can appear as duplicate public events.
3. **Partition and category are not composable.** The scoreboard currently chooses either a cluster/partition board or a category board. Visitors cannot request “Region A + Category B,” and `scoreboardByCategory()` only queries the current event ID, which is wrong for a partitioned hub whose marks live on children.
4. **Publication timestamp is unreliable.** The public results page reads `FestResult.published_at`, but normal scoreboard recalculation does not populate that column. Event-level publication needs its own authoritative timestamp.
5. **Alternate result surfaces need one visibility gate.** Scoreboard, live payload, results tabs, item results, posters, participant search, records, display screens, API payloads, and homepage push currently make independent visibility decisions.
6. **Aggregation policy is only partly enforced.** `aggregation_config.method` is stored but `FestPartitionService::combinedScoreboard()` effectively supports only summed points. Unsupported methods must fail validation rather than silently behaving as `sum_points`.

### P1 — usability and operational clarity

7. “Cluster” naming is hard-coded on a topology-neutral public feature. The public UI needs Region, Cluster, Zone, Phase, or Finale labels based on `partition_role` and configured labels.
8. Child URLs do not explain their parent event or provide canonical navigation back to the hub.
9. The event hub always links to the Category Scoreboard, even before publication, resulting in a public 404 under official-only behavior.
10. The global public cache middleware applies a one-hour cache to fest pages, which can leave a newly published, unpublished, or corrected board stale during an event.
11. There is little focused public-scoreboard regression coverage, especially for tenant isolation, combined totals, category-by-region filters, and unpublished-data leakage.

## 4. Product rules

### 4.1 Canonical public entry points

Keep the existing URLs, but give each one a clear role:

| URL | Role |
|---|---|
| `GET /fest` | Public list of root/standalone events only |
| `GET /fest/{hub}` | Event landing page and topology navigation |
| `GET /fest/{hub}/scoreboard` | Canonical public points/standings board |
| `GET /fest/{hub}/results` | Canonical published item and participant results |
| `GET /fest/{hub}/live` | Optional live operations page; standings follow the event's public mode |
| `GET /fest/{hub}/live/data` | Short-lived JSON polling endpoint using the same visibility service |

For a child Region/Cluster/Finale event URL, either:

- redirect to the root hub with `?scope=partition:{key}`, or
- render the same canonical page with a parent breadcrumb and canonical URL pointing to the root hub.

The first option is preferred because it avoids duplicate public pages and SEO ambiguity.

### 4.2 Public scoreboard modes

Add an explicit event setting instead of inferring all behavior from status:

| Mode | Before official publish | After official publish |
|---|---|---|
| `off` | No public board | No public board |
| `official_only` | “Standings not published yet” | Official scoreboard and published timestamp |
| `live_unofficial` | Live board labeled “Unofficial”; no participant details beyond privacy policy | Official scoreboard |

Recommended default: `official_only`.

Existing events should migrate to `official_only`. A live mode must be deliberately enabled by an authorized event admin. The word **Official** or **Unofficial** must always be visible next to the board timestamp.

### 4.3 Topology behavior matrix

| Topology | Public tabs/scopes | Data source | Publication rule |
|---|---|---|---|
| Standard | Overall + Categories | Current event | Event publication/mode |
| Region-based hub | Overall + each Region + optional Finale | Region/finale children | Per policy in §4.4 |
| Cluster/zone hub | Combined + each Cluster/Zone | Cluster children | Per policy in §4.4 |
| Standard event with phases | Overall + optional phase filter | Same event, phase-tagged items | Event publication |
| Independent phase children | Overall + each Phase | Phase children | Per policy in §4.4 |
| Sports houses | Schools + Houses + age categories | `FestResult`, houses, marks | Event publication |
| State | Overall + categories/items supported by State projection | State database projection | State event publication |

Venue is not a scoreboard scope. A sub-venue or stage should create a separate public board only when it is modeled as a real partition with independent scoring.

### 4.4 Regional publication policy

Support two configurable policies for partitioned events:

1. **Atomic publication — default and backward-compatible**
   - Hub publish reveals Overall, Region/Cluster, and Finale scopes together.
   - Hub unpublish hides them together.
   - This matches the current cascade behavior.

2. **Independent child publication — opt-in**
   - A Region/Cluster may publish its official board before other regions finish.
   - Only published child scopes appear publicly.
   - Overall remains hidden until the hub is finalized.
   - Finale remains independent and is not treated as Overall.
   - Hub unpublish hides Overall but does not silently rewrite separately certified child history; an explicit “unpublish all scopes” action is required.

Store this policy under a validated `aggregation_config.results.publication_policy` or an equivalent typed setting. Do not infer it from event type or tenant name.

### 4.5 Scope and filter contract

Use topology-neutral query parameters:

```text
/fest/42/scoreboard?scope=overall
/fest/42/scoreboard?scope=partition:tirur
/fest/42/scoreboard?scope=partition:tirur&category=hs
/fest/42/scoreboard?scope=partition:district-finale&category=hss
```

Compatibility:

- Continue accepting the existing `cluster=` parameter for old/shared links.
- Convert it internally to `scope=partition:{key}`.
- Generate only the new canonical form in the UI.

Validate every requested partition key against children of the requested tenant event. Invalid or cross-event keys return 404, never an empty board that looks legitimate.

### 4.6 Ranking and aggregation rules

- Use competition ranking consistently: `1, 1, 3`, not `1, 2, 3`, for equal totals.
- The scoring preset remains the source of mark-to-points conversion.
- The combined board must include only roles listed in validated `aggregation_config.include_roles`.
- MVP supports `method=sum_points` only. Reject unsupported aggregation methods during event setup/update.
- Prevent double counting when an item moves Region → Finale. The item's configured scoring/advancement rule must state whether Region points, Finale points, or both contribute to Overall.
- Category totals must use the same `FestGradePointService` calculation as Overall and must query all event IDs in the selected public scope.
- Disqualified participants never contribute points.
- School, category, house, and individual championship boards should use a shared tie-ranking helper.

## 5. Architecture

### 5.1 One public projection service

Introduce a topology-aware service, for example:

```php
PublicFestScoreboardService::forEvent($event)->build(
    scope: 'overall',
    category: null,
    audience: 'public',
);
```

It should return a stable DTO/array contract:

```php
[
    'event' => [...],
    'root_event_id' => 42,
    'scope' => ['key' => 'overall', 'label' => 'Overall Championship', 'role' => 'overall'],
    'status' => 'official', // hidden | unofficial | official
    'published_at' => '2026-09-25T18:30:00+05:30',
    'version' => 12,
    'filters' => ['partitions' => [...], 'categories' => [...]],
    'rows' => [
        ['rank' => 1, 'school_id' => '...', 'school_name' => '...', 'total_points' => 120],
    ],
];
```

Responsibilities:

- resolve root versus child event;
- resolve allowed event IDs for Overall, Region, Cluster, Finale, or Phase;
- enforce public publication mode and scope publication;
- calculate category-aware totals across the selected scope;
- apply aggregation and tie rules;
- exclude private/unpublished child data;
- expose safe public labels only;
- provide one payload to Blade, JSON, CMS push, display screens, and broadcasts.

`EventContext` can continue to own operational scoring/recalculation. Public controllers should stop assembling independent scoreboard logic.

### 5.2 Central public visibility gate

Extend `FestPublicVisibilityService` or introduce `FestPublicResultGate` with explicit methods:

```php
boardStatus(FestEvent $event, ?FestEvent $scope): hidden|unofficial|official
canShowSchoolStandings(...): bool
canShowItemResults(...): bool
canShowParticipantIdentity(...): bool
canShowRawScore(...): bool
```

Every public surface listed in gap P0.5 must call this gate. A direct route must not bypass it.

### 5.3 Data changes

Recommended additions to `fest_events`:

| Column | Purpose |
|---|---|
| `public_scoreboard_mode` | `off`, `official_only`, or `live_unofficial` |
| `results_published_at` | Authoritative event-level publication timestamp |
| `scoreboard_version` | Monotonic version for cache keys/ETags |

For independent child publication, each child uses the same fields. The hub timestamp represents Overall publication, not the earliest child publication.

Before adding constraints to `fest_results`, audit and remove duplicate summary rows. Then enforce one current school-summary row per event. If item-level rows are still required in this table, use an explicit result scope/type rather than relying on nullable `item_id` uniqueness semantics.

Do not create a new snapshot table in the first release. `FestResult` remains the persisted school-total projection; marks remain the source for category breakdowns. Add immutable publication snapshots later only if formal historical versioning becomes a product requirement.

### 5.4 Cache policy

Override the current generic one-hour public cache for event-day routes:

| Surface | Suggested policy |
|---|---|
| Official scoreboard/results HTML | `public, max-age=30, s-maxage=60, stale-while-revalidate=120` |
| Live HTML | `no-cache` |
| Live JSON | `no-store` or at most 5 seconds |
| Unpublished/hidden response | `no-store` so publish becomes visible immediately |
| Old completed archives | Longer cache after the event is closed and appeals are locked |

Use `scoreboard_version` in cache keys and ETags. Increment it after mark recalculation, publish, unpublish, appeal correction, disqualification, or topology/aggregation changes. Invalidate homepage section data in the same transaction or queued workflow.

## 6. Public UI

### Standard event

- Header: event title, event type, venue/date, Official/Unofficial badge, last updated/published time.
- Primary table: Rank, School, Points.
- Filters: Overall and categories when categories exist.
- Sports-only tabs when applicable: Houses, Individual Championship, Records.
- Empty state distinguishes “not published,” “no results,” and “invalid filter.”

### Region/cluster event

- Event hub shows one competition, not a flat list of child events.
- Scope pills: Overall, Region A, Region B, Finale; use configured labels.
- Category filter remains available after choosing a scope.
- Each Region view states that its ranking is local to that Region.
- Overall view shows which scopes contribute, such as “Includes Tirur, Manjeri, and Finale.”
- Optional breakdown drawer/table shows each school's points by contributing partition, then total. This is recommended for auditability but may follow the first release.

### Publication states

- `hidden`: friendly “Standings are not published yet” panel; never a bare 404 from a link rendered by the event hub.
- `unofficial`: persistent warning banner and timestamp; no “Published Results” wording.
- `official`: official badge and authoritative publication time.
- `unpublished after correction`: hide cached totals immediately and show the not-published state.

### Accessibility and mobile

- Use a semantic table on desktop and a readable ranked list/card layout on narrow screens.
- Preserve headings and table headers for screen readers.
- Do not encode rank or Region using color alone.
- Filters must be keyboard reachable and expose selected state.
- Long school names must wrap without hiding points.

## 7. Admin workflow

Add a “Public scoreboard” block to event settings/results publication:

- public mode: Off / Official only / Live unofficial;
- partition publication policy: Atomic / Independent children;
- Overall label;
- contributing partition roles;
- preview public board as unpublished admin data;
- validation summary before publish;
- publish/unpublish confirmation showing affected scopes;
- copy public link and QR code after publication.

Pre-publish validation must fail when:

- required marks/judge scores are incomplete;
- an included child scope has stale/unrecalculated totals;
- aggregation config names an unsupported method or missing role;
- a Region child lacks a valid Region/partition identity;
- the topology can double count a Region → Finale item;
- the event has duplicate school-summary rows.

All publication, unpublication, and aggregation-setting changes remain audit logged.

## 8. Implementation phases

### Phase 0 — decisions and fixtures

- Confirm the default mode is `official_only`.
- Confirm atomic publication is the default for existing partitioned events.
- Define two reusable fixtures: one standard event and one two-Region-plus-Finale event.
- Document one worked scoring example, including a tie and a Region → Finale item.

**Exit:** product rules and expected totals are signed off before code changes.

### Phase 1 — canonical service and regression safety

- Add the public scoreboard projection service and shared tie-ranking helper.
- Move current standard and combined calculations behind it without changing outputs.
- Add unit tests for standard, Region, combined, excluded-role, category, disqualification, and ties.
- Validate `aggregation_config.method=sum_points`.

**Exit:** current standard and Kids Fest boards are unchanged under tests; the Region fixture returns exact expected totals.

### Phase 2 — publication and privacy

- Add public mode, publication timestamp, and version fields.
- Centralize the visibility gate.
- Remove the pre-publication leak from `/live`, `/live/data`, display/API payloads, and homepage push.
- Make publish/unpublish timestamps and cache invalidation symmetric.
- Render a friendly hidden state from hub links.

**Exit:** no public route exposes standings or winner details in `official_only` mode before publish.

### Phase 3 — topology-aware public UX

- Restrict `/fest` to root/standalone public events.
- Add topology scope pills and child-to-hub canonical redirects.
- Support Region/Cluster/Finale plus category as two independent filter dimensions.
- Add contribution labels and parent breadcrumbs.
- Preserve old `cluster=` shared links.

**Exit:** a user can move Overall → Region A → Region A/HS → Finale without leaving the event hub or seeing duplicate events.

### Phase 4 — independent regional publication

- Add the optional independent-child publication policy.
- Show only certified child scopes; keep Overall hidden until hub finalization.
- Define explicit “unpublish scope” and “unpublish all” actions.
- Update notification and audit behavior for child publication.

**Exit:** Region A may publish without exposing Region B or an incomplete Overall board.

### Phase 5 — cache, performance, and event-day hardening

- Add route-specific cache headers and ETags.
- Cache calculated public projections by tenant/event/scope/category/version.
- Eager-load school names and eliminate per-row tenant lookups in championship/leaderboard code.
- Add query-count and response-time assertions for a realistic large event.
- Verify rapid publish → unpublish → republish behavior behind the production cache/CDN.

**Exit:** corrected totals are visible within the agreed freshness window and large boards do not create N+1 queries.

### Phase 6 — State and presentation parity

- Adapt `StatePublicResultsProjectionService` to the same public payload contract.
- Reuse the public scoreboard presentation for State events where fields match.
- Keep State data access isolated from tenant databases.
- Update display screens and CMS homepage widgets to consume the canonical projection.

**Exit:** tenant and State boards look consistent while retaining separate data boundaries.

## 9. File-level work map

| Area | Expected files |
|---|---|
| Routes/controllers | `routes/tenant.php`, `FestPortalController.php`, `StatePublicResultsController.php` |
| Public projection | new service under `app/Services/Events/` plus `EventContext.php` and `FestPartitionService.php` |
| Visibility | `FestPublicVisibilityService.php`, possibly a dedicated result gate |
| Publication | `FestResultsController.php`, `FestCmsAutoPush.php`, `FestScoreboardUpdated.php` |
| Data | new tenant migration, `FestEvent.php`, optional cleanup/audit command for duplicate results |
| Cache | `SetPublicCacheHeaders.php` or a fest-specific middleware |
| Public UI | `resources/views/public/fest/index.blade.php`, `show.blade.php`, `scoreboard.blade.php`, `results.blade.php`, `live.blade.php` |
| Admin UI | event settings/results Vue pages and `LeaderboardHub.vue` |
| Displays/API | `DisplayScreenController.php`, `EventsApiController.php` |
| Tests | new `tests/Feature/Public/FestPublicScoreboardTest.php`, service unit tests, Playwright public journey |

## 10. Required test matrix

### Feature/integration

- tenant host cannot access another tenant's event or partition;
- draft/unpublished event does not appear in `/fest`;
- child events do not appear as duplicate root events;
- standard official-only board is hidden before publish and visible after publish;
- live-unofficial mode is visibly labeled and does not expose forbidden participant data;
- Region A board contains only Region A schools/results;
- Region B identifiers never appear in Region A response;
- Overall equals the configured sum of included scopes;
- excluded roles and Region → Finale policy prevent double counting;
- Region + category filter calculates from the correct child event IDs;
- tied totals receive competition ranks;
- disqualification and appeal correction update all affected scopes;
- hub publish/unpublish behavior matches atomic policy;
- independent Region publish does not expose an incomplete Overall board;
- invalid/cross-hub partition key returns 404;
- old `cluster=` URL resolves to the expected scope;
- publish timestamp and Official/Unofficial state are correct;
- cache headers differ appropriately for hidden, live, official, and archived pages.

### End-to-end

- public visitor discovers event from `Live Results` navigation;
- standard event journey on desktop and mobile;
- Region event journey: Overall → Region → Category → Results;
- unpublished board shows a useful state, not a broken link;
- publish/unpublish becomes visible without a one-hour stale page;
- keyboard navigation and responsive layout pass the public-page accessibility checks.

### Performance

- fixed upper query budget per board request, independent of school row count;
- representative fixture with at least 150 schools, 500 items, and multiple Regions;
- concurrent polling does not recalculate the full board on every request;
- cache invalidation is tenant- and event-specific.

## 11. Definition of done

The work is complete when:

1. One canonical service produces every public tenant scoreboard payload.
2. Standard-event behavior remains backward-compatible.
3. Region/Cluster/Finale boards and Overall aggregation are correct and clearly labeled.
4. Region and category filters work together.
5. No official-only standings or result identities leak before publication through any public route.
6. Child events do not clutter the public event index.
7. Publish, unpublish, appeal corrections, and mark changes invalidate the correct cached projection.
8. Public timestamps and Official/Unofficial status are authoritative.
9. State and tenant presentations share a contract without sharing database access.
10. The full test matrix passes for standard and partitioned fixtures.

## 12. Deferred enhancements

Keep these outside the first release unless separately prioritized:

- medal tally by school;
- rank movement/history charts;
- public WebSocket updates (short polling is sufficient initially);
- downloadable combined Region-breakdown PDF/CSV;
- immutable published scoreboard snapshots and version archive;
- multilingual labels beyond the event/partition labels already stored;
- social share cards for Overall/Region leaders.

These should build on the canonical projection rather than introduce new calculations.
