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
| 3 | Program and event configuration | ✅ **built 2026-09-23** — slots, settings/windows, venues & stages, event staff, item catalog. Grade/point rules deferred to Phase 7, where they are used |
| 4 | Qualifier and registration workflow | ✅ **complete 2026-09-23** — submissions, scrutiny, approvals, registrations, teams & squads, substitutions, quota and window enforcement |
| 5 | Pre-event operations | ✅ **mostly built 2026-09-23** — schedule, clashes, green room, chest numbers. ID/admit cards, printable bulk sheets and judge assignment outstanding |
| 6 | Event conduct — attendance, judge portal, mark entry, panel aggregation, bulk import, corrections, stage progress | thin version exists |
| 7 | Results and appeals — item calculation, provisional publishing, appeals, final publishing, Sahodaya points and ranking, School drill-down, public results | thin version exists |
| 8 | Report centre — `StateFestReportCatalog`, **menu-reachable reports only** (scope narrowed 2026-09-23), PDF/Excel/CSV, **catalog parity tests** | ✅ **built 2026-09-23** — 13 live, 9 awaiting later phases |
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

### The rest of Phase 3 — built 2026-09-23

**Venues and stages** did not exist on the State side at all: the pre-module workspace had judges and
marks but nowhere to say where an item is held, so Phase 5 had nothing to schedule against. A stage
belongs to a venue through a self-reference rather than its own table — a venue, a stage inside it, a
green room and a reporting desk are all "a place with a capacity and someone responsible", and the
schedule only ever asks which place. Only a stage or room can host an item. Deleting a venue that
still holds stages is refused: orphaning them into an event with no parent reads as data loss.

**Event staff are deliberately not user accounts.** Most are present for three days and never sign
in, so a name and phone is the minimum; `user_id` is set only for the few who also hold a login.

**Settings** live in `state_fest_events.settings` rather than thirty nullable columns.
`results_published` and `scoring_locked` stay as columns — they gate writes on a hot path and are
queried, not merely read. Unknown keys are dropped on save, so a typo in a form field cannot quietly
become a setting nothing reads.

**The windows change behaviour rather than documenting intent.** A window with no dates is **open** —
an event nobody has configured must not find its workflow silently shut — and a closed one explains
itself in terms the Sahodaya can act on: *"Qualifier submission closed on 10 Jan 2026, 17:00."*
Public visibility is kept separate from `results_published`, so results can be final internally
before they are public.

**The Items tab** shows the *effective* per-Sahodaya allowance so the catalog and the Slots tab
cannot disagree, and links to Slots for changes, where usage and history live.

**Fixed while building:** `state_fest_events.id` is an auto-increment integer, and the first version
of the venue/staff tables declared `state_event_id` as a uuid. Postgres refuses that comparison
outright rather than merely failing to join.

**Deferred on purpose:** Grade Master and rank-point rules move to Phase 7, where results actually
consume them — building the configuration before anything reads it invites two sources of truth.
Categories and eligibility are carried on the items themselves (`class_group`, `gender`,
`participant_type`, group sizes) and are surfaced on the Items tab; a separate eligibility screen
only earns its place once Phase 4 has rules to enforce.

**Tests:** `tests/Feature/State/StateEventConfigTest.php` — window open/closed/not-yet-open and the
wording of each, unknown keys dropped, venue nesting and the self-containment and cross-event
guards, refusing to delete a venue that still holds stages, staff without a login, capability gating
(a mark operator can open the workspace but not reconfigure it), and the Items tab agreeing with
Slots.

All seven workspace tabs now render: Overview, Settings, Items, Slots, Venues, Staff, Reports.


---

## Phase 8 — built 2026-09-23, scope narrowed

**Scope decision (operator, 2026-09-23): only the reports reachable from the sidebar and from a
workspace tab — not the ~100-report inventory in the spec.**

That matches what the Sahodaya module actually exposes: `sahodayaEventNav.js` puts **11 named report
pages plus a hub** in its Outputs section, out of **96** export permutations in `FestReportCatalog`.
A report nobody can reach from a menu is a maintenance cost with no user.

`app/Support/StateFestReportCatalog.php` holds **22 reports** across the spec's six groups:

| Group | Live now | Waiting |
|---|---|---|
| Before Event | 9 | 3 (scheduling) |
| During Event | 2 | — |
| After Event | — | 5 (results/points) |
| Certificates & Print | — | 2 |
| Finance & Audit | 1 | — |

**Unavailable reports say which phase they wait on** and return **409 with that reason**, rather
than rendering an empty table — an empty table reads as "nobody registered", not "this does not
exist yet". Sports-only reports (House Ranking, Athletic Records) and School invoices are recorded
in `notApplicable()` **with a reason** rather than silently absent, since the State bills the
Sahodaya, not the School.

**Data rules honoured.** Every query reads State operational tables only; the certified qualifier
snapshot is the source of truth, which is what makes these reproducible after an event when a tenant
may have been renamed, deactivated or promoted. Grouping is on the canonical Sahodaya identity so a
promoted Sahodaya appears once, and School is carried through every row as a displayed value.

**Downloads** are CSV, Excel and PDF, declared per report, anything else refused with 422. CSV goes
through `CsvSafety` rather than `fputcsv` — a participant or school name starting with `=`, `+` or
`@` is a formula injection the moment the file opens in Excel.

**Found while building:** a registration can exist without an intake, and therefore without a
Sahodaya (the direct fixture noted in Phase 1). Those rows are labelled "Unattributed" rather than
rendered blank, which looked like corrupt data in the first CSV export.

**Access:** behind `state.fest:reports`, so a read-only report user reaches every report and a mark
operator reaches none — both asserted.

**Verified against the local data:** registration master 28 rows, Sahodaya participation 3,
Sahodaya × School matrix 4, item counts 140, slot usage 17, fee summary 3; CSV/XLS/PDF all stream.

**The parity contract** (`StateReportCatalogTest`, 11 tests / 128 assertions) is the spec's
"catalog parity test prevents silently missing State reports": every menu-reachable Sahodaya report
has a State counterpart or a recorded reason; every report declares a known group and valid formats;
every unavailable one says which phase it waits on; every available one renders and downloads in
each format it offers; and both the Sahodaya and the School appear in the registration master.


---

## Phase 4 — built 2026-09-23

### The gap that mattered

Scrutiny had **two** outcomes, approved and rejected. That loses the one a State office needs most —
*"this is wrong, fix it and send it back"* — and forces a scrutineer to reject a whole package over
one missing date of birth. A rejected intake is closed, so the Sahodaya then cannot correct it.

Now four outcomes: **approved**, **rejected**, **returned**, **documents_requested** (held pending
evidence, not judged). The two open outcomes *require* a note, because the Sahodaya reads it and
otherwise has nothing to act on.

### Decisions are a log, not a column

Every decision appends to `state_entry_reviews`. The entry keeps its current status for queries; the
log keeps the history — an appeal asks who decided what and when, and a status column overwritten
three times cannot answer that.

- **Finalising is refused** while entries are pending, returned or awaiting documents: closing the
  intake would strand them, since a finalised intake cannot be edited.
- **Reopening is itself a recorded decision.**
- **Accepting a reserve is two recorded decisions**, not a swap — the original withdrawn, the reserve
  approved — so the log reads as what happened. A reserve must come from the same submission and the
  same item.

### The Phase 3 windows now bite

Once the scrutiny window closes, decisions are refused **with the date it closed**. That is the
window doing work rather than documenting a deadline.

### Batch behaviour

Approval is still held to the Sahodaya's slot allowance, and a bulk decision **continues past** an
entry that breaches it, naming the ones refused rather than losing the batch. Scrutiny is done fifty
rows at a time; losing the work to one bad row is how a deadline gets missed.

### Screens

**Sahodaya Submissions** (package level — managed/outside badge, schools, per-status counts),
**Scrutiny** (entry by entry, bulk decisions, **the allowance shown beside each decision** so nobody
approves blind, plus the decision history), **Pending Approvals** across all submissions, and
**All Registrations** with the spec's full filter set — Sahodaya, School, item, individual/team,
managed/external, status, participant search.

**Sahodaya and School appear on every row throughout**, per §3.B.

Live on the local data: 4 submissions, 28 registrations, 26 pending entries.

**Tests:** `tests/Feature/State/StateScrutinyTest.php` — 15 covering return-for-correction, the
required note, the append-only log, slot enforcement at approval, reserve acceptance and its
same-item rule, finalise/reopen guards, closed-window refusal, batch continuation past a quota
breach, capability gating, and both names on every row.

### Teams, squads and substitutions — built 2026-09-23

A team entry is a registration with several participants, and what was missing is **which of them is
which**. A group item needs a leader to report to the stage and sign for the team, and **standbys**
who travel but do not compete unless substituted in. Without the distinction a standby is
indistinguishable from a competitor, which breaks both the team-size check and the chest-number
allocation Phase 5 will do.

Team size is checked against the item's `min_group_size`/`max_group_size` **with standbys excluded**,
and a wrong-sized team is flagged rather than silently accepted.

**A substitution is not an edit.** Replacing a participant after certification is a decision with a
reason, evidence, a deadline and an approver — so the request is stored, decided, and only then
applied:

- Approving **withdraws** the original rather than deleting it; the record must show who replaced whom.
- The substitute **inherits the vacated role** (leader) and the chest number.
- Refusing requires a note, because the Sahodaya reads it.
- **Substitutions close with the scrutiny window** — after the State has finished deciding who
  competes, a change of participant is an appeal, not a correction.

Eligibility is revalidated at request time: a substitute already in the entry is a duplicate rather
than a replacement, and a participant already substituted cannot be substituted again.

**Found by the tests:** `(state_event_id, chest_number)` is unique, so the substitute could not
inherit a number the withdrawn original still held. The number is now released on withdrawal and
reassigned — it is already printed on attendance sheets and ID cards, so issuing a different one
would invalidate them.

**Tests:** `tests/Feature/State/StateTeamSubstitutionTest.php` — 14 covering team listing with
Sahodaya and School, standbys excluded from the size check, under-size flagging, leader transfer and
the standby/leader guards, request-not-applied, approval mechanics including the inherited chest
number, refusal requiring a note and changing nothing, double-decision and double-substitution
guards, the duplicate-substitute check, the closed window, and capability gating.


---

## Phase 5 — built 2026-09-23

Nothing in the State stack carried a **time** before this. The pre-module workspace could record
marks but not say when an item was held — so clashes could not be detected, performance order had
nothing to order against, and the sheets printed in that order could not be produced.

Times are stored as a **date plus three times**, not timestamps: that is how a schedule is published
and argued about — *"Folk Dance, 21st, report 9:30, stage at 10:00"*. The finish is derived from the
duration when not set by hand, which is what makes overlap detection possible at all.

### Clash detection

Four kinds, and deliberately not the Sahodaya module's, because the competing unit differs:

| Kind | Why it matters here |
|---|---|
| **Participant** | The same person in two overlapping items — the State's worst case, since a Kalotsavam participant routinely enters several |
| **Team** | Distinguished from individual: moving one child is easy, moving six is a different conversation |
| **Venue** | Two items on one stage at once |
| **Sahodaya load** | **Advisory, not an error** — a large contingent can genuinely staff four stages, and only the State office knows which can |

Judgement calls worth recording:

- Participants are matched **on name within a School**. Two children of the same name at one school
  is a false positive — the right way round, since a scrutineer would rather check an extra row than
  miss a real clash.
- **Touching slots do not overlap.** Finishing at 11:00 and starting at 11:00 is a schedule.
- **Standbys are excluded** — they are not competing for that time.
- **Unscheduled items cannot clash**, so a half-built schedule produces no noise.

### Chest numbers

Allocated **in a block per Sahodaya**, because that is how a contingent arrives, is seated and is
called — one sheet covers numbers 150–189.

The property that matters more than the numbering scheme: **a number, once issued, never moves.**
Assigning fills gaps only, so running it again after entries are added cannot renumber anyone already
on a printed sheet. A manual number that duplicates another is refused **by name** ("55 already
belongs to One") rather than silently taking it. Standbys are not numbered until substituted in, when
they inherit the number being vacated — which is also why Phase 4 releases it on withdrawal.

The register puts unnumbered participants **last**, since it is used to find gaps.

### Reports unblocked

**Item Venue & Time Schedule** and **Schedule Clash Report** declared themselves blocked on Phase 5;
both now read this data and are available. That loop closing is the catalog working as intended.

**Tests:** `tests/Feature/State/StateScheduleTest.php` — 16 covering each clash kind, the
touching-slots and different-day non-clashes, standby exclusion, team-vs-individual, stage
double-booking, unscheduled items, per-Sahodaya blocks, never reissuing a number, skipping taken
numbers, duplicate refusal, register ordering, green-room performance order, screen rendering and
capability gating, and the two newly available reports.

### Outstanding in Phase 5

ID and admit cards, printable bulk sheets (attendance, timesheet, judge sheets — the third report
still blocked), and judge assignment, which belongs with Phase 6's conduct work.
