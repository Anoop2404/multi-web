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
| 2 | State application shell — sidebar, event workspace, tabs, permissions, event switcher, shared filter bar, activity log | ✅ **built 2026-09-23** (activity log tab pending) |
| 3 | Program and event configuration — items, categories, eligibility, per-Sahodaya slots, windows, grade/point rules, venues, staff | partial — **Sahodaya Slots built 2026-09-23**; items/eligibility/windows/grades/venues/staff outstanding |
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


---

## Phase 2 — built 2026-09-23

### Permissions (spec §13)

The State had two roles, enforced by refusing `state_staff` every non-GET request. That cannot
express the separations the module requires — a mark operator who must never publish, a certificate
operator who must never alter a result.

`app/Support/StateFestPermissions.php` defines the 18 `state.fest.*` capabilities and the role
matrix over them, seeded for both guards:

| Role | Holds |
|---|---|
| `state_admin` | all 18 |
| `state_staff` | view, reports |
| `state_mark_operator` | view, attendance, marks — **no results, no publish** |
| `state_certificate_operator` | view, certificates, reports — **no results, no marks** |
| `state_scrutiny_officer` | view, qualifiers, scrutiny, registrations, reports |
| `state_report_user` | view, reports (read-only) |

`EnsureStateFestPermission` gates routes as `state.fest:marks` and **names the missing capability**
when it refuses, because these roles exist so an operator can be told what they are not trusted with.

**Found while building:** `EnsureStateAdmin` recognised only `state_admin`/`state_staff`, so every
new operator role authenticated successfully and was then refused at the door of its own workspace.
It now accepts every State role; `state_staff` keeps its blunt read-only rule, which predates the
matrix.

### Navigation

`resources/js/support/stateFestNav.js` — deliberately **not** derived from `sahodayaEventNav.js`.
The two modules compete different organizations, and sharing a nav file is the first step toward
sharing the queries behind it. The main sidebar plus the 6-section / 38-item event workspace.

Every item carries the capability gating its screen, and `visibleStateNav()` **drops** what a role
cannot open rather than disabling it — an operator is not shown a map of what they are not trusted
with. Verified: a mark operator's sidebar is `Event Home (Overview) | Competition (Attendance, Mark
Entry)` and nothing else.

### Workspace shell

`StateAdmin\Fest\StateFestWorkspaceController` renders event header, **event switcher**,
capability-filtered sidebar, and the **shared filter bar** (`StateFilterBar.vue`) enforcing the
spec's hierarchy — Sahodaya primary, School a dependent second level whose select stays disabled
until a Sahodaya is chosen, because a School filter without one silently matches nothing.

Tabs whose screens arrive with later phases render as plainly pending rather than linking to a 404.

**Overview is real, not a placeholder.** Counts come only from State operational tables:
participation grouped on the canonical Sahodaya identity (so a promoted Sahodaya appears once),
schools counted from the certified qualifier snapshot rather than by reading tenant databases, plus
intake/scrutiny and attendance/mark progress. Scheduling and certificate figures are **left out**
rather than shown as a zero that would read as "nothing to do".

Live figures on the local data: 2 Sahodayas (1 managed, 1 from outside), 4 schools, 28 registrations,
4 submissions with 2 awaiting scrutiny, 53 entries (26 pending / 27 approved).

### A data gap this surfaced

Programs, events, intakes and outside-Sahodaya rows created before multi-state carry a **null
`state_id`**, which makes them invisible to every state user — they fail closed and 403 on their own
event. `state:backfill-sahodaya-directory --state=KL` now stamps the whole chain in one pass
(3 programs, 1 event, 5 intakes, 21 outside-Sahodaya rows locally), and a superadmin — unscoped
everywhere else — is no longer scoped to an event's state for the Sahodaya filter list.

### Not yet built in this phase

The Activity Log tab (spec §2.A.8). It needs a State-scoped read over the audit log, which belongs
with the audit work in Phase 8 rather than being stubbed here.

**Tests:** `tests/Feature/State/StateFestWorkspaceTest.php` — the matrix separations, that the roles
seed with exactly those permissions, workspace access per role, cross-state refusal, canonical
Sahodaya counting, and that the capability middleware refuses a mark operator asked to publish.


---

## Phase 3 (in progress) — Sahodaya Slots, built 2026-09-23

The spec asks for this as a tab of its own rather than a field inside each item form, and that is
right: slots decide who may compete, so they need to be seen across every item at once, with usage
beside them and every change recorded.

**Three levels, most specific first** — and all three are per Sahodaya:

1. a Sahodaya's override for that item — `state_sahodaya_item_slots` **(new)**
2. the item's own figure — `FestStateProgramItem.max_per_school`
3. the item default — `FestStateProgramItem.qualify_count`

Level 1 was the missing one: there was no way to grant a single Sahodaya an extra place on a single
item, or hold it to fewer after a ruling, without moving the figure for everybody.

**Overrides key on the canonical directory id** from Phase 1, so they survive promotion. Enforcement
now counts approved entries on that identity too, matching either the canonical id or the raw
submission key — a Sahodaya that submitted before *and* after its promotion used two different raw
keys, and counting those separately handed it a second full allowance for every item. Covered by
`test_a_promoted_sahodaya_gets_one_allowance_not_two`.

**Change history is part of the screen.** Every change appends to `state_slot_audit_entries` with
who, from what, to what and why; the table is never updated or deleted from. A quota that moved and
left no trace is exactly what an appeal will ask about.

**Practical shaping.** The matrix returns only cells carrying an override or some usage — 140 items
across 22 Sahodayas is 3,080 cells, almost all empty. Team items are marked as consuming one slot per
entry, not one per member. The tab sits behind `state.fest:catalog`, so a report user or mark
operator can open the workspace without being able to move a quota.

**Live on the local data:** 140 items, 16 with entries or overrides, per-Sahodaya usage resolving
correctly (Recitation-Malayalam: Malappuram 1/2, Thrissur 2/2).

**Tests** — `tests/Feature/State/StateSlotOverrideTest.php`: three-level fallback, an override
changing what approval allows without affecting the next Sahodaya, clearing returning to the item
figure, the audit trail's contents, the spec's mandatory "team registration consumes one slot", the
matrix's used/available/exceeded, and one allowance for a promoted Sahodaya.

### Still outstanding in Phase 3

Items & Catalog, Categories & Eligibility, event dates and windows (qualifier submission, scrutiny,
publication), Grade Master and rank points, Venues & Stages, Event Staff.
