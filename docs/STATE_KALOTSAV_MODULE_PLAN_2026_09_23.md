# State Kalotsav — Separate Event-Management Module

**Decision recorded 2026-09-23.** The State Kalotsav becomes a complete, separate event-management
module with the same operational depth as the Sahodaya Kalotsav. The Sahodaya module is not touched.

This supersedes the open architecture question in
`STATE_ADMIN_TENANT_PROMOTION_AND_UAT_PLAN_2026_09_23.md` §2 (D1). That plan recommended reusing the
`fest_*` module with the State as a tenant; the decision is the other way — a State-specific stack.
The reasoning behind the recommendation still stands as a cost estimate (56 fest controllers, ~28k
LOC of event UI, ~150 tables to reach parity), so the work is sequenced rather than attempted at once.

## The separation

| Level | Competing organization | Participant origin |
|---|---|---|
| Sahodaya Kalotsav | School | School |
| **State Kalotsav** | **Sahodaya** | School inside that Sahodaya |

Sahodaya is always the primary organizational filter. School is retained as a secondary filter and a
displayed value — never dropped, never promoted to the competing unit.

**Canonical filter hierarchy for every State page and report:**
State Program → State Event → Sahodaya → School → Category → Item → Participant/Team

## Technical separation rules

```
app/Http/Controllers/StateAdmin/Fest/      resources/js/Pages/Admin/State/Fest/
app/Services/State/Fest/                   resources/js/Components/state/fest/
app/Services/State/Reports/                resources/js/support/stateFestNav.js
app/Support/StateFestReportCatalog.php     resources/js/support/stateFestReportCatalog.js
```

- State services query **only** the State operational database.
- Never query live Sahodaya tenant databases for State reports.
- **Certified qualifier snapshots are the State source of truth.**
- Generic UI components may be shared; Sahodaya controllers, routes and queries may not be reused.
- Existing Sahodaya screens must not be bent to pretend they are State screens.
- State permissions are separate (`state.fest.*`, 18 groups — see the spec section 13).
- Mark operators cannot publish; certificate operators cannot alter results; report users are read-only.

## External Sahodaya parity

External Sahodayas get the **full** workflow, not a reduced one: slots, submissions, scrutiny,
registrations, attendance, scheduling, marks, results, rankings, reports, certificates, fees and
public results. One shared directory resolves managed and external sources, and a promoted external
Sahodaya keeps **one canonical identity** — never counted twice.

## Implementation sequence

| Phase | Scope | Status |
|---|---|---|
| 1 | State identity foundation — canonical managed/external identity, Sahodaya + School name snapshots, migration, backfill, promoted-external de-duplication, shared directory resolver | ✅ **built 2026-09-23** |
| 2 | State application shell — sidebar, event workspace, tabs, permissions, event switcher, shared filter bar, activity log | not started |
| 3 | Program and event configuration — items, categories, eligibility, per-Sahodaya slots, windows, grade/point rules, venues, staff | partial (slots done) |
| 4 | Qualifier and registration workflow — submissions, scrutiny, approvals, registrations, teams, substitutions, quota enforcement, external parity | partial (intake queue, slots) |
| 5 | Pre-event operations — chest numbers, ID/admit cards, scheduling, clashes, performance order, green room, attendance sheets, judge assignment | not started |
| 6 | Event conduct — attendance, judge portal, mark entry, panel aggregation, bulk import, corrections, stage progress | thin version exists |
| 7 | Results and appeals — item calculation, provisional publishing, appeals, final publishing, Sahodaya points and ranking, School drill-down, public results | thin version exists |
| 8 | Report centre — `StateFestReportCatalog`, ~100 reports across before/during/after, PDF/Excel/CSV, background exports, **catalog parity tests** | not started |
| 9 | Certificates — templates, eligibility, merit/participation, batches, tally, Sahodaya/School packs, verification, stale regeneration | not started |
| 10 | Finance and services — State fees and remittance, ledger, receipts, appeal fees, catering, volunteers | partial (fixed fee done) |
| 11 | Migration and UAT — backfill, result comparison, managed + external testing, load tests, pilot, route switch, temporary redirects | not started |

Sports-only reports (House Ranking, Athletic Records) must be explicitly marked **"Not applicable to
State Kalotsav"** rather than silently appearing.

Bulk certificate PDFs use the configured third-party browser-rendering service, not DomPDF.

---

## Phase 1 — built 2026-09-23

**The problem.** Every State row keyed on `state_qualifier_intakes.source_tenant_id`, a polymorphic
string: a central tenant uuid for a managed Sahodaya, `external:{uuid}` for one outside the platform.
Two consequences the module cannot carry:

1. **Promotion changed a Sahodaya's identity.** Its history stayed under `external:{uuid}` while new
   submissions arrived under the tenant uuid — the same body counted twice in standings, slot usage,
   fees and every report. Exactly what the spec forbids.
2. **Names were read live from central tables**, which the State module is not allowed to depend on,
   and which lose the name outright if a tenant is renamed or deleted after the event. Already
   visible: `StateParticipationLimitService::sahodayaComplianceRows()` resolved names through
   `Tenant::whereIn(...)`, so external Sahodayas rendered as a raw `external:uuid` string.

**Shipped.**

- `database/migrations/state/2026_09_23_200001_create_state_sahodayas_directory.php` —
  `state_sahodayas` on the State connection: one row per Sahodaya per state, carrying the **name
  snapshot** and pointing at whichever central records it maps to (`tenant_id`,
  `external_sahodaya_id`, both set at once for a promoted Sahodaya). Plus `sahodaya_id` /
  `sahodaya_name` on intakes and `sahodaya_name` on registrations.
- `app/Models/State/StateSahodaya.php` — `sourceKeys()` returns every raw key that resolves to the
  identity; `origin` (how it arrived) is kept deliberately distinct from `isOnPlatform()` (what it is
  now), so reports can still answer "how many took part from outside the platform".
- `app/Services/State/StateSahodayaDirectory.php` — the only place that decides identity.
  `resolve()` maps either key form onto a row; `linkPromotedTenant()` attaches a newly promoted
  tenant to the row already holding the history, absorbing a stray duplicate onto the
  external-origin row (the one with the earlier history) if one exists.
- Intake stamps the canonical id and name at the door; materialization carries both onto
  registrations. Promotion links the identity, and `retry()` repairs it.
- `state:backfill-sahodaya-directory` (`--dry-run`, `--force`).

**Verified on the local data.** 3 identities built from 5 intakes; 5 of 5 intakes and 27 of 28
registrations linked. The 28th is a direct fixture registration with no `qualifier_entry_id` and
correctly has no Sahodaya to resolve — worth noting as a real case: **registrations can exist
without an intake**, so later phases must let the canonical Sahodaya be set directly on such rows.

The de-duplication is visible in the directory itself — `Test External Sahodaya` carries **two
source keys and one identity**:

```
SAHODAYA                ORIGIN    ON PLATFORM  SOURCE KEYS
Test External Sahodaya  external  yes          67f5940a-… | external:01a00fa3-…
```

**Tests** — `tests/Feature/State/StateSahodayaDirectoryTest.php`, covering the spec's mandatory
"promoted external Sahodaya deduplication", plus: one identity per source however often resolved,
submissions from before and after promotion landing on one identity, stray-duplicate absorption,
the name snapshot surviving a tenant rename, the School staying attached to every entry, an
unresolvable key not breaking intake, and per-state scoping failing closed.

**Carried forward.** Promotion now touches the State connection; if that database is unavailable a
promotion fails loudly and is resumable with `--retry-failed`, which is deliberate — a silent
failure here would reintroduce the double-count this phase exists to prevent.
