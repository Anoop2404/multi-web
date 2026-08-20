# Public Event-wise Scoreboard — Standalone Event Redesign Plan

**Status:** revised implementation plan; no application code changed  
**Scope:** public event listing, event page, scoreboard, schedule, live display, and results  
**Applies to:** Kalotsav, Sports Meet, Kids Fest, Teacher Fest, and other `FestEvent` programs

---

## 1. Confirmed product direction

There will be **no phase or region navigation inside the public scoreboard**.

Every conducted phase/region is listed as a **separate public event** with its own:

- public event card;
- event name;
- venue;
- conduct date/time;
- public URL;
- schedule;
- scoreboard;
- detailed results;
- publication state;
- share link and display-screen mode.

Examples:

```text
Kalotsav 2026 — Digi Fest
Venue: Central School Auditorium

Kalotsav 2026 — Off Stage — North Region
Venue: North Regional Centre

Kalotsav 2026 — Off Stage — South Region
Venue: South Regional Centre

Kalotsav 2026 — Grand Finale
Venue: Sahodaya Main Campus
```

Each card opens the same reusable scoreboard module, but that module shows data only for the selected event.

In short:

> **Many standalone event listings, one reusable scoreboard module, no phase/region switcher.**

---

## 2. Meaning of “separate event”

A phase or region is a separate public event when it has an operational `FestEvent` record containing its own identity and lifecycle.

The public does not need to understand the internal root/phase/region hierarchy. Internally, the records may remain linked to a root event for administration and aggregation.

### Public behavior

- `/fest` lists every publicly enabled operational event separately.
- `/fest/{event}` is the standalone event home.
- `/fest/{event}/scoreboard` shows only that event’s points.
- `/fest/{event}/schedule` shows only that event’s schedule.
- `/fest/{event}/results` shows only that event’s published results.
- `/fest/{event}/live` shows only that event’s live/public activity.
- No selector changes the visitor from one phase or region to another.
- Visitors return to `/fest` to choose another event.

### Internal behavior

- A root event can remain the administrative container.
- Phase/region child events keep `parent_event_id`, `root_event_id`, `source_phase_id`, and `region_id` for administration and reporting.
- Scoring is calculated directly for the selected operational event.
- Overall/finale aggregation is a separately listed event when required.

---

## 3. Event identity and naming

Every public event must have a clear identity instead of relying on generated technical labels.

### 3.1 Required event identity

| Field | Purpose |
|---|---|
| Public title | Complete standalone name visible on cards and pages |
| Short title | Compact mobile/display-screen label |
| Event type | Kalotsav, Sports, Kids Fest, Teacher Fest, etc. |
| Venue | Venue for this exact operational event |
| Start/end | Dates and times for this exact event |
| Public coordinator/contact | Event-local coordinator details controlled by an explicit public visibility setting |
| Status | Upcoming, ongoing, completed, postponed, cancelled |
| Public visibility | Whether it appears in `/fest` |
| Schedule published | Controls public schedule visibility |
| Results published | Controls scoreboard/results visibility |
| Parent/root metadata | Internal grouping only; not public navigation |

### 3.2 Naming convention

Recommended generated default:

```text
{Root Event} — {Phase} — {Region}
```

Examples:

- `Kalotsav 2026 — Digi Fest`
- `Kalotsav 2026 — Off Stage — Tirur Region`
- `Kalotsav 2026 — On Stage — Manjeri Region`
- `Kalotsav 2026 — Grand Finale`

Admins must be able to override the public title without breaking the internal event relationship.

### 3.3 Venue rules

- Non-regional phase event: use the phase venue, falling back to root venue.
- Regional event: use the phase-region venue.
- Finale event: use its own explicitly configured venue.
- Never show the root venue when a child event has a different venue.
- Venue changes affect only the selected operational event.

The existing `FestPhaseTopologyService::syncLeaf()` already copies phase/region venue and date information into child `FestEvent` records. The redesign should treat those records as the public source of truth.

### 3.4 Event-owned category configuration

**Categories are defined by the selected event. They are not a fixed platform-wide list and must not be inferred only from the event type.**

Each standalone event must have an effective category configuration containing:

```text
category_key
display_label
description
sort_order
eligible_classes_or_ages
optional_gender_or_division_rules
include_in_school_overall
include_in_student_topper
enabled
```

Examples are event-specific:

```text
Event A: Category I, Category II, Category III
Event B: Junior, Intermediate, Senior
Event C: Sub-Junior Boys, Sub-Junior Girls, Senior Boys, Senior Girls
Event D: Open Category only
```

Rules:

- the event admin selects or defines the category set during event setup;
- item category choices come only from that event’s enabled categories;
- public labels and ordering come from the event configuration;
- scoreboard filters, toppers, result search, cards, PDFs, and exports use the same effective categories;
- changing another event’s category setup must not change this event;
- generated phase/region events receive a snapshot/copy of the source category configuration, after which their public event identity remains stable;
- an admin can deliberately resync categories, but topology sync must not silently overwrite event-specific category overrides;
- disabled/deleted categories with historical published results remain resolvable for history and are not relabeled as a different category.

The repository already supports named category schemes through `FestClassCategoryScheme` and legacy event-local rows through `FestEventClassGroup`. Add a single event-category resolver rather than hard-coding `LP/UP/HS/HSS` or selecting a category column solely from `event_type`.

Recommended compatibility order:

1. explicit category configuration assigned/snapshotted for the selected event;
2. the event’s selected named category scheme;
3. legacy event-local category rows;
4. a legacy adapter for existing `class_group`/`age_group` item data;
5. no silent global fallback for newly created events.

### 3.5 Concrete reference — MCS District Kalotsav 2026

The supplied conduct format defines one administrative Kalotsava with four phases. Two phases are central/non-regional and two phases are conducted region-wise.

```text
MCS District Kalotsav 2026 (administrative container; not a public scoreboard)
├── Phase 1: Digi Fest (central)
│   └── 1 standalone public event
├── Phase 2: Off Stage (regional)
│   ├── Nilambur Region
│   └── Tirur Region
├── Phase 3: Regional Events / Sargadhara (regional)
│   ├── Tirur Region
│   └── Manjeri Region
└── Phase 4: District Kalotsav (central/final conduct)
    └── 1 standalone public event
```

Therefore, this four-phase Kalotsava produces **six standalone public events**, not four public cards and not one scoreboard with region navigation.

| # | Public event name | Conduct type | Venue | Date |
|---|---|---|---|---|
| 1 | MCS District Kalotsav 2026 — Digi Fest | Central | Al Hidayath EM School, Thurakkal, Kondotty | 05 Sep 2026 |
| 2 | MCS District Kalotsav 2026 — Off Stage — Nilambur Region | Regional | Guidance Public School, Munda, Edakkara | 10 Sep 2026 |
| 3 | MCS District Kalotsav 2026 — Off Stage — Tirur Region | Regional | Umeri English School, Veliyankode | 10 Sep 2026 |
| 4 | MCS District Kalotsav 2026 — Sargadhara — Tirur Region | Regional | Malabar Central School, Valiyaparamba, Pukayoor | 18 Sep 2026 |
| 5 | MCS District Kalotsav 2026 — Sargadhara — Manjeri Region | Regional | The Springs International School, Tana, Nilambur | 19 Sep 2026 |
| 6 | MCS District Kalotsav 2026 — District Kalotsav | Central/final conduct | St. Alphonsa Public School, Oorakam, Malappuram | 25–26 Sep 2026 |

Each of these six event records requires its own event page, schedule, items, venue, public contact, scoreboard, toppers, recent results, item-result search, detailed results, publication flags, and public URL.

#### Phase-specific region membership

The regional pair is not the same for both regional phases:

- Off Stage: Nilambur and Tirur;
- Sargadhara: Tirur and Manjeri.

Therefore:

- allowed regions must be attached to each phase;
- do not assume one global region list applies to every phase;
- public event generation must use `phase + allowed region`, not a Cartesian product of all Kalotsava regions;
- this configuration must generate exactly four regional children in this example;
- a school’s operational region may need to be resolved per phase rather than from one permanent event-wide assignment;
- use phase-specific school-region selection where applicable so a school can be routed correctly when regional structures differ between Off Stage and Sargadhara.

#### Public listing order

The six events should appear chronologically:

1. Digi Fest — 05 Sep;
2. both Off Stage regional events — 10 Sep;
3. Sargadhara Tirur — 18 Sep;
4. Sargadhara Manjeri — 19 Sep;
5. District Kalotsav — 25–26 Sep.

Events on the same day should be secondarily ordered by configured display order, region name, or start time—not database ID.

#### Venue coordinator/contact

The supplied sheet includes a coordinator and phone number for each standalone event. Model this as an event-local public contact or an assigned event staff member with an explicit `show_on_public_event` permission.

Public contact rules:

- show the coordinator only on the corresponding event;
- allow admins to hide the phone number while keeping the coordinator name;
- format phone links accessibly as `tel:` actions on mobile;
- do not inherit a sibling event’s coordinator;
- topology sync must preserve deliberate contact overrides.

#### Billing remains grouped separately

Based on the earlier MCS fee format, billing groups events by level:

```text
Level 1 billing batch: Digi Fest + Off Stage regional events
Level 2 billing batch: Sargadhara regional events + District Kalotsav
```

This produces six public/operational events but only the configured billing batches. A school or unique student must not be charged once per generated public event when the rule is once per level/batch.

#### Decisions still required from the detailed Kalotsava rules

The following must be confirmed before scoring/qualification implementation:

- whether Off Stage and Sargadhara regional winners qualify into District Kalotsav;
- whether District Kalotsav re-competes qualifiers or contains a different item set;
- whether an MCS-wide Overall School Champion combines points from all six events;
- whether regional event points remain local or contribute to an aggregate championship;
- whether student category toppers are event-local only or an additional Kalotsava-wide topper is required;
- the category set and item assignment for each of the four phases;
- fee amounts and inclusion rules for Level 2;
- how ties, appeals, corrections, and withdrawn qualifiers affect later events.

### 3.6 Cumulative points continuity across phases

The MCS Kalotsava requires one continuous championship score from Phase 1 through the final phase, even though each phase/region is published as a standalone event.

The public and admin scoreboards must distinguish three values:

```text
Opening Points + Current Phase Points = Cumulative Closing Points
```

For every school and every event-defined category:

```text
opening(p, school, category)
    = sum of finalized contributions from phases before p

phase_points(p, school, category)
    = sum of new eligible points earned in phase p

closing(p, school, category)
    = opening + phase_points
```

Phase 1 always starts at zero. Phase 2 starts from Phase 1’s locked closing total. Phase 3 starts from Phase 2’s locked cumulative closing total. District Kalotsav starts from Phase 3’s locked cumulative closing total. The final Kalotsava championship is the closing total after Phase 4.

#### MCS continuity sequence

```text
Phase 1 — Digi Fest
Opening: 0
Closing: Digi Fest points
        ↓ lock Phase 1 closing snapshot

Phase 2 — Off Stage (Nilambur + Tirur events)
Opening: Phase 1 closing
New points: Off Stage points from both regional events
Closing: Phase 1 + Phase 2
        ↓ lock Phase 2 closing snapshot

Phase 3 — Sargadhara (Tirur + Manjeri events)
Opening: Phase 2 closing
New points: Sargadhara points from both regional events
Closing: Phase 1 + Phase 2 + Phase 3
        ↓ lock Phase 3 closing snapshot

Phase 4 — District Kalotsav
Opening: Phase 3 closing
New points: District Kalotsav points
Final: Phase 1 + Phase 2 + Phase 3 + Phase 4
```

#### Example category calculation

| Phase | School | Category | Opening | New phase points | Closing cumulative |
|---|---|---|---:|---:|---:|
| Digi Fest | School A | Category I | 0 | 20 | 20 |
| Off Stage | School A | Category I | 20 | 10 | 30 |
| Sargadhara | School A | Category I | 30 | 15 | 45 |
| District Kalotsav | School A | Category I | 45 | 12 | 57 |

The same calculation runs for every school and each category configured for the root Kalotsava. School Overall is the sum of included category closing totals according to the event’s category rules.

#### Category continuity key

Categories remain configured per event, but cumulative addition requires a stable championship category identity.

Each event category participating in continuity must map to a root Kalotsava category:

```text
source_event_category_id → championship_category_id
```

Rules:

- add points by `championship_category_id`, never by display label;
- an event may use a subset of the root championship categories;
- an event may use a different public label while retaining the same championship mapping;
- an event-only category can be explicitly excluded from cumulative championship totals;
- an unmapped category that is marked for championship inclusion blocks phase consolidation;
- changing a label must not move historical points to another category;
- merging or splitting championship categories after a phase is locked requires a versioned migration/recalculation.

This preserves the earlier requirement that categories are event-based while still making cross-phase category continuity mathematically safe.

#### Do not copy carried points into child event results

Carry-forward points must not be inserted into each child event’s normal `FestResult.total_points` as if the school earned them again in that event.

Keep two concepts separate:

- **event contribution:** points newly earned in the selected standalone event;
- **championship cumulative balance:** earlier finalized contributions plus this event/phase contribution.

Copying opening points into every regional child would double-count them when the regional events are consolidated. The scoreboard can display an opening balance, but the aggregation engine sums only new phase contributions.

#### Regional-phase consolidation rule

For a regional phase:

- every regional child receives the same previous-phase opening snapshot for display;
- a child event shows only the schools routed to that region;
- the child event’s `Current Phase Points` contains only points earned in that regional event;
- when closing the phase, combine only the new contributions from all required regional children;
- add the previous closing snapshot once, after regional contributions are consolidated;
- never add the opening snapshot once per region;
- a school is keyed by stable `school_id`, so its points continue correctly even if its phase-specific region changes later.

Formula:

```text
phase_2_closing
    = phase_1_closing
    + off_stage_nilambur_new_points
    + off_stage_tirur_new_points
```

Not:

```text
(phase_1_closing + nilambur_points)
+ (phase_1_closing + tirur_points)
```

#### Standalone event scoreboard presentation

Every phase/region event remains a separate public event. Its default championship table should show:

```text
Rank | School | Opening Points | This Event Points | Cumulative Total
```

Category-wise view:

```text
Rank | School | Category | Opening | This Event | Cumulative
```

Also provide a clearly labelled `This Event Only` result view for users who need to see the standalone event contribution without carried points.

Labels must be explicit:

- `This Event Result` — winners and points earned in the selected event only;
- `Championship Standing After This Event` — cumulative result including previous phases;
- `Opening balance locked from {previous phase}`;
- `Provisional` when the current phase is not fully consolidated;
- `Official` only after the required phase close/publish action.

#### Toppers under continuity

For Phase 2 onward:

- School Overall Toppers default to the cumulative closing balance;
- School Category-wise Toppers default to cumulative category closing balances;
- provide `This Event Only` toppers as a secondary view;
- ranks are recalculated from cumulative points; do not carry a previous rank number;
- joint ranks and tie-breaks use the Kalotsava’s configured championship rule.

Student Category-wise Toppers require an explicit rule choice:

- event-local only; or
- cumulative across phases by stable student identity.

Until that rule is approved, do not silently add a student’s points across phases. School cumulative continuity is mandatory and independent of this open student-topper decision.

#### Phase close and opening-balance workflow

Each phase needs two different operations:

1. **Publish operational event results** — exposes item results and new event contribution.
2. **Consolidate and close phase points** — validates every required child, calculates phase contribution, locks the closing snapshot, and creates the next phase’s opening balance.

Recommended phase states:

```text
Draft
→ Results in progress
→ Regional/event results published
→ Ready to consolidate
→ Closing balance locked
→ Superseded by corrected version, when formally reopened
```

The next phase may accept registrations/schedules earlier, but its official cumulative scoreboard cannot be published until the previous phase opening snapshot is locked.

#### Corrections after a later phase starts

Do not overwrite a locked snapshot silently.

Correction workflow:

1. authorized admin opens a correction request;
2. system shows every downstream phase/scoreboard affected;
3. unpublish the affected cumulative boards;
4. correct and republish the source event result;
5. create a new version of that phase’s closing snapshot;
6. recalculate every later opening/closing balance in phase order;
7. republish with `Updated result` and audit metadata.

If a later phase is already officially closed, require elevated approval before cascading the correction.

#### Recommended score ledger and snapshots

The existing live `FestPhaseScoreboardService` can calculate phase totals, but reliable continuity needs auditable, versioned phase closure.

Recommended records:

```text
fest_score_contributions
├── root_event_id
├── phase_id
├── source_event_id
├── school_id
├── source_event_category_id/key
├── championship_category_id
├── points
├── source_publication_version
└── finalized_at

fest_phase_score_snapshots
├── root_event_id
├── phase_id
├── school_id
├── championship_category_id
├── opening_points
├── phase_points
├── closing_points
├── closing_rank
├── version
├── locked_at/by
└── published_at/by
```

The contribution ledger is the auditable source. The snapshot is the fast, official phase closing balance. Neither replaces item marks or standalone event results.

Required uniqueness/idempotency must prevent the same source event/category/school contribution from being added twice during republish or retry.

---

## 4. Public event listing redesign

### 4.1 Listing rule

Change `/fest` from “root events only” to “public operational events.”

List an event when:

- it belongs to the current tenant;
- its status is public-listable;
- `nav_hidden` or the new explicit public visibility setting does not hide it;
- it represents an event visitors can attend or follow;
- it has a meaningful public title and event identity.

### 4.2 Root/container handling

The root event must not automatically appear merely because it exists.

Use these rules:

| Root usage | Public listing behavior |
|---|---|
| Root is only an administrative container | Hide root; list operational children |
| Root is also a real conducted event | List root as a standalone event |
| Root represents Overall Championship only | List only when Overall is intentionally public |
| Separate finale child exists | List finale child; do not duplicate it as root |
| Standard event with no children | List the root event normally |

### 4.3 Event cards

Each public card should show:

- event title;
- program/event type;
- date and start time;
- venue;
- status badge;
- schedule availability;
- results/scoreboard availability;
- primary action based on lifecycle.

Primary action behavior:

| Event state | Primary action |
|---|---|
| Upcoming + schedule published | View Schedule |
| Ongoing | Open Event |
| Completed + results published | View Scoreboard |
| Results pending | Event Details |
| Cancelled/postponed | View Notice |

### 4.4 Listing filters

Recommended filters:

- All;
- Today;
- Upcoming;
- Ongoing;
- Completed;
- Event type/program;
- search by event name or venue.

Phase and region are not navigation concepts on the scoreboard. If useful, they may appear only as optional labels/filter metadata on the event listing.

---

## 5. Standalone event page

Route:

```text
GET /fest/{event}
```

The event page is based directly on the requested operational event. It must not redirect phase/region events back to the root event.

### Features

- event title and tenant branding;
- venue and date/time;
- event description;
- event status;
- event items/categories;
- schedule link;
- scoreboard link;
- detailed results link;
- live display link;
- participant search, if enabled;
- records/manual links, if applicable;
- “Back to all events” action.

### Isolation rule

All items, schedules, results, participants, marks, winners, and standings must be queried using the selected event’s ID only.

The page may show a small informational label such as “Part of Kalotsav 2026,” but it must not provide phase/region navigation.

---

## 6. Reusable scoreboard module

Route:

```text
GET /fest/{event}/scoreboard
```

The same scoreboard UI and service contract is reused for every public event.

### 6.1 Scoreboard header

Show:

- tenant logo and name;
- exact event title;
- event type;
- venue;
- date/time;
- official publication status;
- last published/updated time;
- share, print, and display-mode actions;
- “Back to event” and “All events” links.

Do not show:

- phase selector;
- region selector;
- combined-scope selector;
- root/child technical terminology.

### 6.2 School standings

For a standard event without championship continuity:

```text
Rank | School | Gold | Silver | Bronze | Total Points
```

For an event linked to a continuing Kalotsava phase, the default table is:

```text
Rank | School | Opening Points | This Event Points | Cumulative Total
```

The category view uses the same Opening/This Event/Cumulative breakdown for every configured category.

Mobile presentation:

```text
#1  School Name                         124 pts
    Gold 8 · Silver 5 · Bronze 3
```

Features:

- tie-aware ranking;
- top-three highlighting;
- school search;
- filters generated from the selected event’s category configuration;
- clear zero-data state;
- official results timestamp;
- accessible medal labels;
- responsive wide-table behavior.

Medal counts must be labelled as `This Event` or `Cumulative`. Points continuity is mandatory; medal continuity affects ranking only when the configured tie-break or award rules use it.

### 6.3 Latest winners

Optional panel scoped to the selected event only:

- participant/team name;
- school;
- item;
- rank/medal;
- grade/result where public;
- winner poster link where supported.

Participant rendering must follow the card rules in section 7.6. An individual winner shows the student card; a pair/group/team winner shows the complete participant roster.

### 6.4 Category standings

Allow category filtering inside the event because categories are result dimensions, not separate events.

The available filters must come from the selected event’s effective category configuration. Example labels may be:

- Category I, Category II, Category III;
- Junior, Intermediate, Senior;
- Sub-Junior Boys, Sub-Junior Girls;
- Open.

Changing a category must never change the event ID.

Do not render a fixed platform category list. If an event has one category, show that category without an unnecessary selector. If an event has no valid category configuration, show a configuration-safe empty state and block category-topper publication until the admin resolves it.

### 6.5 Official publication status

Use accurate labels:

- `Official results published`;
- `Results updated`;
- `Awaiting official results`;
- `Results withdrawn for correction`.

Do not call publication-gated data “unofficial live standings.” Live refresh should mean refreshed official data only.

### 6.6 School Overall Toppers

Every standalone event must have a **School Overall Toppers** section.

Purpose:

- identify the highest-scoring schools at the close of the selected event/phase;
- provide the current cumulative Kalotsava champion and runners-up while still allowing a This Event Only view;
- give the public a concise podium view before the full school standings table.

Display:

```text
Rank | School | Gold | Silver | Bronze | Total Points
```

Minimum presentation:

- Champion — Rank 1;
- First Runner-up — Rank 2;
- Second Runner-up — Rank 3;
- “View complete school standings” action.

Rules:

- calculate `This Event Points` from the selected standalone event only;
- for a phase-linked Kalotsava, calculate the default topper from the phase’s official cumulative closing snapshot;
- include only officially published, eligible results;
- use the event’s configured grade/position point rules;
- exclude disqualified participants and voided results;
- apply the common ranking/tie policy;
- if two schools tie, show the same rank and do not invent a winner through display ordering;
- an aggregate/finale event uses only its explicitly configured scoring sources;
- never add the previous opening balance once per regional child.

### 6.7 School Category-wise Toppers

Every standalone event must have a **School Category-wise Toppers** section.

Purpose:

- identify the leading school inside each category configured for the selected event;
- allow a school to be recognized for category performance even when it is not the overall event champion.

Example only—the real labels come from the selected event:

```text
Category I        — School A — 46 points
Category II       — School B — 52 points
Senior            — School C — 61 points
```

Display per category:

```text
Rank | School | Gold | Silver | Bronze | Category Points
```

Features:

- category cards showing the Rank 1 school;
- expandable Top 3 or full category table;
- category filter;
- gender division only where the event configuration requires separate school awards;
- share/print the selected category result.

Rules:

- resolve the category through the selected event’s effective category configuration;
- use the item’s event-category key/ID; treat legacy `class_group` and `age_group` only as compatibility inputs;
- do not merge unrelated category codes;
- uncategorized/open items follow an explicit event rule: include in Overall, place under “Open,” or exclude from category awards;
- publish a category topper only when the relevant category results satisfy the event publication rule;
- use the same tie policy as School Overall Toppers;
- for a phase-linked Kalotsava, default to the cumulative category closing balance and provide `This Event Only` as a secondary view;
- load category opening points from the previous phase’s locked snapshot, keyed by school and event category.

### 6.8 Student Category-wise Toppers

Every standalone event must have a **Student Category-wise Toppers** section.

Purpose:

- identify the highest-scoring individual student in every event category;
- provide category champions independently of the school ranking;
- support Best Performer/Individual Championship recognition for the selected event.

Display per category:

```text
Rank | Student | School | Category | Gender/Division | Gold | Silver | Bronze | Points
```

Minimum presentation:

- Category Topper — Rank 1;
- First Runner-up — Rank 2;
- Second Runner-up — Rank 3;
- participant photo only when public visibility rules allow it;
- item/result drill-down for auditing the points total.

Rules:

- calculate from the selected standalone event only;
- group by student and the selected event’s configured category;
- use only officially published eligible marks/results;
- exclude disqualified participants and withdrawn/voided results;
- separate Boys/Girls or other divisions only when configured for that event;
- group/team item points count toward a student topper only when the scoring preset explicitly awards those points to individual team members;
- never multiply a team result across students unless the configured competition rules require it;
- use the official event tie-break policy; unresolved ties remain joint toppers;
- do not display a student’s private registration number or other protected data on the public topper card;
- if no eligible individual scores exist in a category, show “No student topper declared.”

### 6.9 Topper presentation order

Recommended order on the event scoreboard:

1. School Overall Toppers;
2. complete School Overall Standings;
3. School Category-wise Toppers;
4. Student Category-wise Toppers;
5. Latest Item Winners.

On mobile, show the three topper sections as compact cards with a “View full table” action. On display screens, rotate between the three modules without changing the selected event.

---

## 7. Detailed results module

Route:

```text
GET /fest/{event}/results
```

The selected event remains fixed across all result tabs.

Tabs/features:

- school overall toppers and complete points/medal table;
- school category-wise toppers and complete category standings;
- student category-wise toppers;
- item-wise winners;
- individual participant results;
- Individual Championship / Best Athlete where applicable.

Rules:

- Scoreboard and School-wise Results must use the same row assembler.
- item winners and `This Event Only` results must never merge parent/sibling event data;
- the cumulative championship view may include prior finalized phase contributions only through the versioned championship ledger/snapshot—not by broad querying of sibling marks;
- An Overall or Finale event performs aggregation through its own configured scoring service and appears as its own public event.
- Tab changes preserve category/search filters where relevant.

### 7.1 Recent Results page

Every standalone event must have a **Recent Results** page.

Recommended event route:

```text
GET /fest/{event}/results/recent
```

Purpose:

- show the latest officially published item results for the selected event;
- help visitors follow announcements without opening every item;
- provide a chronological result feed suitable for mobile and event-day display.

Each recent-result card should show:

- result publication time;
- item name and item code;
- event-defined category;
- participant type: individual, pair, group, team, or house;
- first, second, and third positions;
- participant/team names according to public visibility policy;
- school;
- grade, score, time, distance, or measurement when publicly allowed;
- links to the full item result, PDF, and winner poster where available.

For an individual item, show the winner’s student photo, name, and school directly in the result card. For a pair/group/team item, show all approved performers in that placed entry; do not show only the participant attached to the mark.

Sorting and visibility rules:

- order by the official result publication timestamp, newest first;
- do not use raw mark `updated_at` as the public announcement time;
- include only results officially published for the selected event;
- do not include draft, corrected-but-unpublished, withdrawn, or sibling-event results;
- after an unpublish action, immediately remove the item from Recent Results;
- republished corrections return to the feed with the corrected publication timestamp and an optional “Updated result” badge;
- paginate or use “Load more”; do not load the event’s entire result history initially.

Filters:

- All results;
- Today;
- event-defined category;
- item type;
- individual/team;
- search by item name or item code.

Empty state:

```text
No official item results have been published for this event yet.
```

### 7.2 Site-level Recent Results page

Recommended route:

```text
GET /fest/results/recent
```

Register this static route before `/fest/{event}` or constrain `{event}` to a numeric ID, so Laravel does not interpret `results` as an event identifier.

This optional but recommended page combines the latest published result announcements from all publicly listed operational events under the current tenant.

Each result must clearly show its standalone event name and venue. Selecting a result opens that event’s item-result page.

Rules:

- include only publicly listed events and officially published item results;
- never merge scoreboards or totals across events;
- filter by event, event type, date, and item/category;
- cap each query with pagination;
- use tenant-scoped cache keys and publication timestamps;
- hiding an event removes its announcements from the site-level feed.

This is a discovery page, not phase/region navigation. Every result still belongs to and opens one standalone event.

### 7.3 Event Item Results search

Every standalone event must provide a dedicated **Search Item Results** experience.

Recommended route:

```text
GET /fest/{event}/results/items?q={item name or code}
```

Search inputs:

- item name;
- item code;
- event-defined category;
- stage/item type;
- participant type;
- published date.

Search-result row/card:

```text
Item Code | Item Name | Category | Result Status | Published At | Top Winner | Action
```

Actions:

- View Full Result;
- Download Result PDF;
- View/Share Winner Poster where supported.

Search behavior:

- search only items belonging to the selected event;
- support partial, case-insensitive item-name matching;
- support exact and partial item-code matching;
- normalize spaces and common punctuation without changing the stored item identity;
- return only items whose results are publicly visible;
- use the item-level publication flag/timestamp when item-wise publishing is enabled, in addition to the event visibility gate;
- optionally show an unpublished matching item as “Result not published yet” only if revealing the item itself is already allowed on the public event page;
- never show participant names, marks, winners, or result snippets for unpublished items;
- preserve the query and filters when the visitor returns from an item result;
- paginate results and protect the endpoint with the normal public rate limit.

### 7.4 Item Result detail page

The existing item-result page remains the canonical detail page:

```text
GET /fest/{event}/items/{item}/results
```

Required improvements:

- event name, item code/name, category, venue, and publication timestamp;
- complete official result ordered by position and approved tie rules;
- correct team/group roster;
- school, grade, score/measurement, and record information where allowed;
- Previous/Next published item actions within the selected event’s search order;
- Back to Recent Results;
- Back to Item Search with filters preserved;
- PDF and winner-poster actions;
- strict check that the item belongs to the selected event.

### 7.5 Individual result cards

Every individual result must use a student/participant card rather than a plain name-only row.

Required card content:

- student photo;
- student name;
- school name;
- position/rank;
- medal treatment for first, second, and third;
- grade and score/measurement when public;
- event-defined category;
- public participant reference only when permitted.

Recommended layout:

```text
┌─────────────────────────────────────┐
│ [Student Photo]  🥇 First Place     │
│                  Student Name       │
│                  School Name        │
│                  Grade A · 85 pts   │
└─────────────────────────────────────┘
```

Image rules:

- use the approved student profile/event photo;
- use a neutral initials/avatar fallback when no public photo is available;
- never display a broken image;
- lazy-load off-screen photos;
- provide meaningful alt text such as `{Student Name}, {School Name}`;
- respect tenant/student photo visibility and consent rules;
- do not expose the underlying private storage path.

### 7.6 Pair, group, trio, and team result cards

Every placed pair/group/trio/team entry must show **all approved participants**.

Required group result content:

- group/team position and medal;
- school name;
- item and category;
- complete participant roster;
- photo and name for every participant when publicly allowed;
- participant role when roles are meaningful;
- grade, score, time, distance, or measurement when public;
- result PDF and winner poster actions.

Recommended layout:

```text
┌────────────────────────────────────────────┐
│ 🥇 First Place · School Name               │
│                                            │
│ [Photo] Name  [Photo] Name  [Photo] Name  │
│ [Photo] Name  [Photo] Name  [Photo] Name  │
│                                            │
│ Grade A · 90 points                        │
└────────────────────────────────────────────┘
```

Roster rules:

- resolve the roster from the result’s `registration_id`;
- include every approved performer for that placed registration;
- exclude withdrawn, rejected, disqualified, duplicate, and non-performer rows according to event rules;
- preserve the configured participant/roster order where available;
- never treat the single `FestMark.participant_id` as the complete group roster;
- do not truncate to four participants or replace remaining members with `+N` on the full result page;
- compact Recent Results cards may collapse the roster visually only when an explicit “Show all participants” control reveals every name on the same card;
- PDF, print, accessibility text, and winner posters must also contain the complete approved roster;
- if participants belong to multiple schools under a supported event type, show the school beside each participant; otherwise show the shared school once.

### 7.7 Reusable participant-card data contract

Use one public participant presenter/resource for individual and group results:

```text
participant
├── public_reference
├── display_name
├── photo_url
├── photo_alt
├── school_id
├── school_name
├── participant_role
└── visibility
```

Group result data must return:

```text
result_entry
├── registration_id
├── participant_type
├── school
├── participants[]
├── position
├── grade
├── score_or_measurement
└── publication
```

Build this contract once and reuse it in Recent Results, item-result detail, Detailed Results, latest winners, display mode, PDF, and winner posters. This prevents different pages from showing different members of the same group.

### 7.8 Result discovery links

Every standalone event home and scoreboard should include:

- Recent Results;
- Search Item Results;
- All Detailed Results.

The main `/fest` page should include a tenant-level “Latest Results” action when the site-level feed is enabled.

---

## 8. Schedule, live display, and supporting pages

### 8.1 Schedule

`/fest/{event}/schedule` shows only schedules assigned to that event.

- venue/time must match the standalone event;
- event items only;
- downloadable/printable schedule;
- unpublished schedules return the approved empty/404 state;
- no schedule rows from sibling regions or phases.

### 8.2 Live display

`/fest/{event}/live` is a full-screen event display.

Features:

- current/next item;
- newly published winners;
- official standings when published;
- event venue and title;
- last refreshed time;
- scoped JSON refresh without full-page reload.

### 8.3 Search and participants

Participant search must be scoped to the selected standalone event. The same participant can appear in different operational events, but each event page shows only its own registration/result.

### 8.4 Records and championship

Show only when supported by the event type and populated for the selected event. Do not pull records or championship points from the root unless the selected event is explicitly an aggregate/finale event.

---

## 9. Overall and finale events

An Overall or Finale is not a navigation tab. It is another standalone public event.

### 9.1 Combined-points event

Example:

```text
Kalotsav 2026 — Overall Championship
```

Its scoreboard may aggregate configured source events, but it has its own:

- public event listing;
- URL;
- title;
- publication status;
- scoreboard;
- results timestamp.

### 9.2 Re-competed finale

Example:

```text
Kalotsav 2026 — Grand Finale
```

Regional winners register/qualify into the finale event and compete again. Its results come from finale marks, not a sum of region points.

### 9.3 Region result is final

When regions do not combine:

- list each region event separately;
- do not create or display an Overall event;
- each regional event publishes independently;
- each event has its own winner and ranking.

### 9.4 Publication independence

A published regional/phase event must be publicly accessible even if its root container or sibling events are not published.

The public visibility rule must evaluate the requested operational event, not require the root event to be complete.

---

## 10. Admin event management module

Administrators can keep a grouped root-event workspace internally, but the public output is a collection of standalone events.

### 10.1 Event setup

For every operational phase/region event, admins must be able to configure:

- public title;
- short title;
- venue;
- event coordinator and public contact visibility;
- event date/time;
- description;
- public-list visibility;
- schedule publication;
- result publication;
- event status;
- public ordering/featured state;
- link to public preview.

### 10.2 Generated event review

When phase/region topology generates child `FestEvent` records, show a review table:

```text
Event name | Venue | Date | Items | Schools | Status | Public | Actions
```

Admins must review missing names, venues, dates, or item assignments before publishing the event listing.

### 10.3 Publish readiness

Per standalone event show:

- item count;
- participant count;
- schedule completeness;
- marks completed/pending;
- scoring lock;
- previous phase closing snapshot status/version;
- opening balance readiness;
- required regional event contribution count;
- received/finalized regional contribution count;
- phase consolidation readiness;
- result calculation status;
- school overall topper calculation status;
- school category topper calculation status;
- student category topper calculation status;
- result publication state;
- recent-results visibility and announcement timestamp;
- published at/by;
- validation blockers.

### 10.4 Admin actions

- preview event;
- open public page;
- publish/unpublish schedule;
- publish/unpublish results;
- recalculate standings;
- preview Opening + This Phase + Closing category balances;
- consolidate regional event contributions;
- lock phase closing balance and create the next phase opening snapshot;
- request correction/reopen with downstream impact preview;
- recalculate downstream cumulative snapshots after an approved correction;
- print/export;
- preview the Recent Results card;
- open the public Item Results search;
- hide/show event in listing;
- edit title, venue, dates, and description;
- regenerate missing operational events;
- sync phase/region configuration without overwriting deliberate public-title/venue overrides.

### 10.5 Audit and safety

- confirm result publish/unpublish;
- record actor and timestamp;
- keep an audit trail for identity, venue, schedule, scoring, and visibility changes;
- invalidate only the selected event’s public cache;
- never expose preview-only data to anonymous users;
- warn before hiding an already shared event.

---

## 11. Backend architecture

### 11.1 Selected event is the public boundary

Introduce or adapt a public-event resolver that returns the requested operational event directly.

It should not automatically convert these events to their root:

- phase child;
- region child;
- phase-region child;
- cluster event;
- finale event.

Root resolution remains available for internal administration and aggregate-event calculation only.

### 11.2 Shared standalone scoreboard service

Create a service such as:

```text
PublicEventScoreboardViewService
```

Input:

```text
FestEvent $event
optional category/filter
```

Output:

```text
event
publication
event_categories
filters
school_board
score_continuity
  opening_balance
  current_event_contribution
  current_phase_contribution
  cumulative_closing_balance
  snapshot_version/status
medal_tally
school_overall_toppers
school_category_toppers
student_category_toppers
latest_winners
recent_results
item_result_search
result_entries_with_complete_rosters
result_links
meta
```

The service must use the selected event’s scoring context. Aggregate/overall events can delegate to `FestPartitionService` or `FestPhaseScoreboardService` only when that selected event is explicitly configured as an aggregate.

### 11.3 Controller responsibilities

Public controllers should only:

1. resolve tenant;
2. resolve selected event;
3. verify public listing/access policy;
4. validate filter/category;
5. request the event view model;
6. render HTML or JSON.

Move medal tally, latest-winner mapping, publication metadata, and shared standings composition out of `FestPortalController`.

Topper calculations should be exposed through one reusable service layer, for example:

```text
EventTopperService
├── schoolOverall(event)
├── schoolsByCategory(event, category)
└── studentsByCategory(event, category, optionalDivision)
```

The service must delegate point calculation and rank/tie handling to the existing scoring services. It must not introduce a second points formula.

Use a dedicated result-discovery service, for example:

```text
PublicEventResultDiscoveryService
├── recentForEvent(event, filters)
├── recentForTenant(tenant, filters)
├── searchPublishedItems(event, query, filters)
└── itemResult(event, item)
```

This service must use official publication metadata and the centralized public visibility policy. It must not infer publication from the existence or update time of marks.

Add a shared presenter such as `PublicResultEntryPresenter` to resolve individual cards and complete pair/group/team rosters. It should batch-load roster participants, students, photos, and schools to avoid one query per result card.

Add an `EventCategoryResolver` as the only category source used by public scoreboard/result services. It should return the selected event’s category keys, public labels, order, eligibility metadata, root championship-category mapping, and compatibility mapping for legacy items. `PublicFestScoreboardService::categories()` and `categoryLabel()` should delegate to it instead of choosing `class_group` or `age_group` purely from `event_type`.

Add a cumulative scoring service, for example:

```text
FestCumulativeChampionshipService
├── openingBalance(root, phase)
├── eventContribution(event)
├── phaseContribution(root, phase)
├── previewClosing(root, phase)
├── consolidateAndLock(root, phase, actor)
├── closingSnapshot(root, phase, version)
└── recalculateDownstream(root, fromPhase, actor)
```

All methods operate by stable `school_id + event_category` keys. The service must add prior-phase opening points once, aggregate only new child-event contributions for the current phase, and delegate raw point calculation/ranking to the existing scoring engine.

### 11.4 Public event policy

Centralize these decisions:

```text
isPubliclyListed(event)
canViewEvent(event)
canViewSchedule(event)
canViewScoreboard(event)
canViewResults(event)
canViewCumulativeSnapshot(event, phase/version)
```

Both HTML and JSON endpoints must use the same policy.

### 11.5 Existing service reuse

Keep:

- `EventContext` for event-local scoring;
- `FestGradePointService` for grade points;
- `FestPartitionService` for explicit aggregate events;
- `FestPhaseScoreboardService` for explicit combined phase/overall calculations;
- `FestCumulativeChampionshipService` for versioned opening, phase contribution, and closing balances;
- `FestPhaseTopologyService` for creating/syncing operational event records;
- `FestPhasePublicationService` for lifecycle operations, after adjusting it for standalone public accessibility.

Do not create separate scoring code for every phase or region.

### 11.6 Caching and refresh

- cache by tenant + selected event + category + publication version;
- include root championship snapshot version in cumulative scoreboard cache keys;
- never share cache keys between parent and child events;
- invalidate the affected event plus downstream cumulative phase keys on an approved correction;
- keep SSR for initial load;
- provide a scoped JSON endpoint for refresh;
- pause polling when the browser tab is hidden;
- never cache private/unpublished results in public buckets.

---

## 12. Required changes in the current code

### 12.1 Public index

Current behavior hides region, cluster, finale, and phase child events from `/fest`.

Required change:

- list public operational children separately;
- hide only administrative containers and explicitly hidden records;
- order by date/time, configured public order, and title;
- show each child’s own venue/name/status.

Primary touchpoint:

- `FestPortalController::index()`.

### 12.2 Root redirects

Current event, scoreboard, results, and live flows redirect partition children to a root event plus a scope query.

Required change:

- stop redirecting publicly listed operational children;
- render the requested event directly;
- retain redirects only for legacy/non-public technical records when necessary.

Primary touchpoints:

- `FestPortalController::show()`;
- `FestPortalController::results()`;
- `FestPortalController::scoreboard()`;
- `FestPortalController::live()`;
- `PublicFestScoreboardService::rootEvent()` usage.

### 12.3 Scope navigation

Required change:

- remove `scope-nav` from public event/scoreboard/results/schedule pages;
- remove phase/region scope query generation;
- provide “Back to all events” instead;
- preserve category and result-tab filters only.

### 12.4 Event-local queries

Required change:

- use the selected event ID for items, marks, results, schedules, participants, winners, medals, and championship;
- use aggregate services only for an explicitly configured aggregate/finale event;
- add assertions preventing sibling data from appearing.

### 12.5 Publication gate

Required change:

- allow a published operational child event even when its root is incomplete;
- block an unpublished child even when the root is published;
- use identical rules for scoreboard, results, live JSON, PDFs, posters, and exports.
- require item-level publication for Recent Results, item search snippets, and item-result pages when the event supports item-wise publishing; event-level publication must not accidentally reveal a still-unpublished item.

---

## 13. Features by priority

### Must have

- separate public listing for every operational event;
- unique event name, venue, date, and status;
- standalone event home;
- standalone schedule;
- standalone scoreboard;
- standalone detailed results;
- selected-event-only item/result isolation, with prior-phase points entering only through the championship ledger;
- event-configured category filters;
- medals and total points;
- School Overall Toppers for every event;
- School Category-wise Toppers for every event;
- Student Category-wise Toppers for every event;
- consistent tie and team-item attribution rules;
- Recent Results page for every event;
- item-wise result search by item name and code;
- canonical item-result detail with PDF/share actions;
- photo/name/school cards for every individual result;
- complete participant cards for every pair/group/team result;
- official publication status/timestamp;
- correct child-event publication independence;
- event-owned category configuration with no cross-event category leakage;
- versioned school/category point continuity from Phase 1 through the final phase;
- Opening + This Event/Phase + Cumulative Closing displays;
- regional phase consolidation without duplicated opening balances;
- controlled downstream recalculation after approved corrections;
- mobile and accessible UI;
- public preview and admin readiness;
- automated isolation and publication tests.

### Should have next

- event-list search and lifecycle filters;
- partial JSON refresh;
- full-screen display mode;
- print/PDF output;
- latest-winners feed;
- tenant-level Recent Results feed across public standalone events;
- share/copy link;
- featured/current-event card;
- public ordering controls.

### Later enhancements

- rank movement history;
- event comparison;
- school drill-down;
- winner sharing cards;
- QR codes;
- multilingual event titles;
- event-day traffic analytics.

---

## 14. Delivery phases

### Delivery Phase 0 — Event listing rules and data review

Work:

- classify root records as conducted events or administrative containers;
- define which existing child events are public operational events;
- verify child titles, venues, dates, and lifecycle values;
- inventory and validate the effective category setup for every event;
- approve generated naming rules and admin overrides;
- define aggregate/finale listing rules.
- approve category inclusion, cumulative school scoring, phase close, correction, and tie-break rules.

Exit criteria:

- every existing event record has an unambiguous public-list decision;
- missing operational identity data is known before rollout.

### Delivery Phase 1 — Standalone public event resolution

Work:

- stop automatic root redirection for public operational events;
- add centralized public event policy;
- update routes/controllers to use the selected event as the boundary;
- fix child publication independence;
- retain safe compatibility redirects for legacy URLs.

Exit criteria:

- every public child URL renders its own event;
- no child requires root publication to be viewable;
- unpublished events leak no result data.

### Delivery Phase 2 — Public event listing

Work:

- list operational events separately;
- hide administrative root containers;
- show correct event title, venue, date, and status;
- add search/status/type filters;
- add lifecycle-aware primary actions.

Exit criteria:

- phase and region events appear as separate cards;
- the same conducted event is not duplicated through its root/container;
- users can find events without a phase/region scoreboard navigator.

### Delivery Phase 3 — Reusable standalone scoreboard

Work:

- redesign the scoreboard header and standings table;
- remove scope navigation;
- extract shared scoreboard view-model service;
- add event-local medals, categories, and latest winners;
- replace hard-coded/type-derived public category selection with `EventCategoryResolver`;
- add School Overall Top 3 and full standings;
- add School Category-wise Top 3 and full standings;
- add Student Category-wise Top 3 and full standings;
- add shared topper ranking/tie logic;
- add official status and timestamps;
- add mobile layout.
- add Opening Points, This Event Points, and Cumulative Total columns for phase-linked events.

Exit criteria:

- the module works unchanged for standard, phase, region, and finale events;
- each instance shows only the selected event’s data.
- all three topper sections reconcile with the same event’s official result rows.

### Delivery Phase 3A — Cumulative championship continuity

Work:

- add contribution ledger and versioned phase snapshots;
- calculate school/category opening balances from the previous locked phase;
- aggregate current phase contributions across its required operational events;
- ensure regional children do not duplicate the shared opening balance;
- add phase consolidate/lock workflow;
- create the next phase opening snapshot;
- default school overall/category toppers to cumulative closing totals;
- add This Event Only secondary tables;
- add correction impact preview and ordered downstream recalculation.

Exit criteria:

- Phase 1 opens at zero;
- every later phase opens from the immediately previous locked closing snapshot;
- the final total equals the sum of each phase’s new contributions exactly once;
- category and school overall totals reconcile at every phase boundary;
- retries, republishing, and regional consolidation cannot duplicate points.

### Delivery Phase 4 — Event pages and detailed results

Work:

- make event home, schedule, result tabs, item results, championship, records, and search event-local;
- add per-event Recent Results page;
- add item-wise result search and filtering;
- improve item-result detail and return links;
- add reusable individual student cards and complete group-roster cards;
- add the optional tenant-level Recent Results feed;
- share the school/medal row assembler;
- keep filters within the selected event;
- add “Back to all events.”

Exit criteria:

- scoreboard and detailed results reconcile;
- no sibling event data appears anywhere in the public journey.
- newly published item results appear in the correct event feed and search.

### Delivery Phase 5 — Admin operational-event manager

Work:

- add generated-event review table;
- allow public name/venue/date/visibility editing;
- add readiness and publication state;
- add preview/open-public-page actions;
- preserve manual overrides during topology sync;
- add audit records.

Exit criteria:

- admins can prepare and publish every standalone event without editing the root container as a proxy.

### Delivery Phase 6 — Live refresh, exports, and display mode

Work:

- add selected-event JSON endpoint;
- replace full-page refresh;
- add cache/invalidation;
- add print/PDF;
- add display mode;
- load-test event-day traffic.

Exit criteria:

- live refresh preserves page position and filters;
- caches cannot mix events;
- display mode is readable at projector resolutions.

### Delivery Phase 7 — QA and rollout

Work:

- run event isolation and publication matrix;
- accessibility/responsive QA;
- validate old shared URLs;
- pilot one standard event, one regional event, and one finale event;
- reconcile totals with admin reports;
- roll out behind a feature flag if required.

Exit criteria:

- no cross-event data leak;
- every public event’s identity and venue are correct;
- totals and medals match official reports.

---

## 15. Test matrix

### Event listing tests

- standard root event is listed;
- administrative root container is hidden;
- phase child is listed separately;
- region child is listed separately;
- phase-region child is listed separately;
- finale event is listed once;
- explicitly hidden event is absent;
- child card shows child venue/date, not root values;
- status, search, and event-type filters work.

### MCS four-phase blueprint tests

- the four configured phases generate exactly six operational public events;
- Digi Fest generates one non-regional event;
- Off Stage generates only Nilambur and Tirur events;
- Sargadhara generates only Tirur and Manjeri events;
- District Kalotsav generates one two-day central/final event;
- no unwanted Off Stage Manjeri or Sargadhara Nilambur event is generated;
- every generated event preserves its own venue, dates, coordinator, and public contact setting;
- the public listing follows conduct date and configured secondary order;
- phase-specific school-region selection routes registrations to the correct operational event;
- Level 1 and Level 2 billing batches do not charge once per generated public event;
- topology resync is idempotent and preserves approved public identity/contact overrides.

### Event isolation tests

- event home shows only selected event items;
- schedule shows only selected event rows;
- scoreboard shows only selected event schools/points;
- medal tally uses only selected event marks;
- latest winners use only selected event marks;
- item/individual results exclude siblings;
- participant search excludes siblings;
- championship/records exclude siblings;
- This Event Only school toppers exclude parent and sibling results;
- cumulative school toppers include only versioned contributions from finalized prior/current phases;
- school category toppers use only the selected category and the approved continuity ledger;
- student category toppers include only students/results in the selected category and event;
- Recent Results includes only the selected event unless the tenant-level feed is requested;
- item search never returns an item from a parent or sibling event;
- cache and JSON responses cannot cross event IDs.

### Cumulative continuity tests

- Phase 1 opening points are zero for every school/category;
- Phase 2 opening equals the exact Phase 1 locked closing snapshot;
- Phase 3 opening equals Phase 1 + Phase 2 finalized contributions;
- District Kalotsav opening equals Phase 1 + Phase 2 + Phase 3 finalized contributions;
- the final closing equals the sum of new contributions from all four phases;
- category closing equals opening category points plus current phase category points;
- event-local category labels map to stable root championship category IDs before addition;
- two differently labelled event categories mapped to the same championship category continue correctly;
- an event-only category marked as excluded does not enter cumulative totals;
- an included but unmapped event category blocks phase consolidation;
- school overall closing equals the sum of included category closing totals;
- Nilambur and Tirur Off Stage contributions consolidate while Phase 1 opening is added once;
- Tirur and Manjeri Sargadhara contributions consolidate while Phase 2 closing is added once;
- a school changing phase-specific region retains continuity through stable `school_id`;
- missing/unpublished required regional children block official phase close;
- publishing a child twice or retrying consolidation does not duplicate its contribution;
- a corrected earlier phase creates a new snapshot version and recalculates every downstream balance in order;
- an unauthorized correction cannot change locked snapshots;
- This Event Only totals remain unchanged by opening points;
- cumulative topper ranks are recalculated from closing points rather than copied from the previous phase;
- cache keys distinguish snapshot versions and never serve an obsolete cumulative total.

### Topper calculation tests

- School Overall Top 3 matches the complete school standings;
- every category identifies the correct leading school;
- every category identifies the correct leading student;
- category points use the selected event’s configured category definitions;
- two events with different category sets render only their own labels and filters;
- changing one event’s category configuration does not affect another event;
- generated phase/region events retain their snapshotted category configuration;
- legacy `class_group` and `age_group` values map through the compatibility resolver;
- an unknown/stale category does not get merged into another valid category;
- open/uncategorized item policy is applied consistently;
- disqualified and voided results contribute zero;
- tied schools receive the configured joint rank;
- tied students receive the configured joint rank or approved tie-break outcome;
- gender/division splitting occurs only when enabled;
- team/group points are not duplicated across members unless explicitly configured;
- unpublished item results do not influence any topper;
- publish, unpublish, correction, and republish recalculate all affected topper sections.

### Publication tests

- published child is public while root is incomplete;
- unpublished child remains hidden while root is published;
- sibling publication states are independent;
- unpublish immediately hides HTML, JSON, PDF, and poster result data;
- republish updates timestamps and invalidates event cache;
- item publication adds the item to Recent Results and Item Search;
- item unpublish removes the item from feeds, search results, JSON, and caches;
- unpublished matching items reveal no winner/mark data;
- preview does not open anonymous access.

### Result discovery tests

- Recent Results is ordered by official `published_at`, newest first;
- pagination remains stable when new results are published;
- item-name search is case-insensitive and supports partial matching;
- item-code search supports exact and partial matching;
- event category, participant-type, and date filters work together;
- query and filters survive item-detail back navigation;
- item-detail rejects an item belonging to another event;
- group/team item results show the complete approved roster;
- an individual result card shows the correct student photo, name, and school;
- a missing/private student photo uses the approved fallback without exposing storage paths;
- pair, trio, group, and team results include every approved performer;
- the participant attached to `FestMark.participant_id` is not treated as the whole group;
- withdrawn, rejected, disqualified, duplicate, and non-performer rows are excluded as configured;
- a roster with more than four participants is not truncated on the full result page, PDF, or accessibility output;
- Recent Results can reveal every member through the same card when a compact roster is collapsed;
- result pages batch-load roster/student/school/photo data without N+1 queries;
- corrected and republished results show the new official timestamp;
- site-level Recent Results includes the event name and links to the correct standalone event;
- hidden/unlisted events do not appear in the tenant-level feed.

### Aggregate/finale tests

- combined Overall event includes only configured source events;
- no-combine configuration creates no public Overall event;
- re-competed finale uses finale marks rather than summed region points;
- aggregate event does not cause source events to merge their own scoreboards;
- aggregate publication is independent and auditable.

### Browser/accessibility tests

- mobile event cards and scoreboard rows;
- keyboard navigation;
- accessible status and medal labels;
- empty/loading/unpublished/error states;
- reduced motion;
- print/PDF layout;
- display mode at common screen sizes.

---

## 16. Expected implementation touchpoints

Existing files likely to change:

- `routes/tenant.php`
- `app/Http/Controllers/Public/FestPortalController.php`
- `app/Services/Events/PublicFestScoreboardService.php`
- `app/Services/Events/FestPhaseTopologyService.php`
- `app/Services/Events/FestPhasePublicationService.php`
- `resources/views/public/fest/index.blade.php`
- `resources/views/public/fest/show.blade.php`
- `resources/views/public/fest/scoreboard.blade.php`
- `resources/views/public/fest/results.blade.php`
- a new `resources/views/public/fest/recent-results.blade.php`
- a new `resources/views/public/fest/item-results-search.blade.php`
- `resources/views/public/fest/schedule.blade.php`
- `resources/views/public/fest/live.blade.php`
- `tests/Feature/Public/FestPublicScoreboardTest.php`

The existing `resources/views/public/fest/partials/scope-nav.blade.php` should be removed from these public flows after compatibility migration.

Recommended new responsibilities:

- `PublicOperationalEventService` — determines public-listable operational events;
- `PublicEventVisibilityService` — centralizes event/schedule/scoreboard/result gates;
- `EventCategoryResolver` — supplies the selected event’s effective categories and legacy mappings;
- `PublicEventScoreboardViewService` — builds one event-local scoreboard contract;
- `FestCumulativeChampionshipService` — manages contributions, opening balances, phase consolidation, versioned closing snapshots, and downstream correction;
- `EventTopperService` — returns school overall, school-by-category, and student-by-category rankings;
- `PublicEventResultDiscoveryService` — supplies per-event/site-wide recent results and event-local item search;
- `PublicResultEntryPresenter` — builds individual student cards and complete group/team rosters;
- reusable public event card;
- reusable standings table;
- reusable topper podium/category cards;
- reusable participant and group-roster cards;
- dedicated standalone-child isolation tests.

---

## 17. Definition of done

The redesign is complete when:

1. Every operational phase/region is shown as a separate public event.
2. Every event card shows its own name, venue, dates, and status.
3. Administrative root containers are not shown as duplicate public events.
4. Each event has its own home, schedule, scoreboard, results, and live view.
5. There is no phase or region navigation inside the scoreboard.
6. The same reusable scoreboard module works for every event.
7. Item/winner data is isolated to the selected event ID; cumulative points can include prior phases only through finalized versioned ledger entries.
8. Published child events do not depend on root/sibling publication.
9. Overall/finale competitions, when required, are separately listed events.
10. Scoreboard totals match Detailed Results and official admin reports.
11. Every event shows School Overall Toppers.
12. Every event shows School Category-wise Toppers.
13. Every event shows Student Category-wise Toppers.
14. Every category label, filter, topper, and result is derived from that event’s configured categories.
15. Every event provides a publication-ordered Recent Results page.
16. Visitors can search official results by item name, code, and category within the selected event.
17. Every individual result shows the student image, name, and school using the approved visibility rules.
18. Every pair/group/team result shows all approved participants without using a single representative or truncated full roster.
19. Topper ranks use the same scoring and tie policy as official standings.
20. HTML, JSON, PDF, poster, search, recent-result, and display outputs share the same visibility policy.
21. Automated tests prove there is no cross-event or cross-tenant leakage.
22. The MCS four-phase reference generates exactly six correctly named, dated, located, and isolated public events.
23. Phase 1 through Phase 4 maintain continuous school/category points using Opening + Current Contribution = Closing Cumulative.
24. Regional phase consolidation adds the previous opening balance exactly once.
25. Every locked phase closing becomes the next phase’s official opening snapshot.
26. Corrections are versioned, audited, and recalculated through every affected later phase.

---

## 18. Recommended first implementation slice

1. Define and test the public operational-event rule.
2. Change `/fest` to list public phase/region events separately.
3. Stop redirecting a public child event to its root.
4. Add the event-category resolver and snapshot/legacy compatibility rules.
5. Render `/fest/{event}` and `/fest/{event}/scoreboard` directly from the selected event.
6. Add the contribution ledger, phase snapshots, and cumulative championship service.
7. Add the three required topper outputs with cumulative and This Event Only modes.
8. Add per-event Recent Results and item-result search using official publication metadata.
9. Add the shared individual-card and complete group-roster presenter.
10. Remove the public scope selector.
11. Fix publication gating so each event publishes independently.
12. Implement the MCS four-phase blueprint as the primary acceptance fixture: four configured phases, two phase-specific regional pairs, and exactly six standalone public events.
13. Add isolation, cumulative-continuity, topper, recent-result, item-search, photo-card, full-roster, billing-batch, and event-category tests using the generated MCS events with different names, venues, schools, marks, category schemes, and publication states.

This vertical slice proves the corrected product model before detailed results, admin tooling, exports, and display mode are expanded.
