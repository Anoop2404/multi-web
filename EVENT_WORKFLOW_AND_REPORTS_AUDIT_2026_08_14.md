# Event Workflows and Reports Audit

**Audit date:** 14 August 2026  
**Repository:** `multi-web`  
**Mode:** Read-only product/code audit; no application behavior was changed.

## 1. Executive verdict

The platform has a capable event engine, but the fest reporting surface is substantially overgrown and currently unsafe at the school boundary.

The most urgent issue is not report usefulness: it is authorization/data scope. School routes reuse the Sahodaya-wide 50-export catalog. The controller injects a `school_id`, but several exporters ignore it and return event-wide or tenant-wide data. This exposes other schools' registrations, fee lines, student names, operational documents, catering records, volunteer data, and potentially audit data to a school administrator who requests the generic export URLs.

The report catalog also treats file formats, print artifacts, configuration extracts, operational worksheets, and analytical reports as equal "reports." This creates 50 bulk export entries plus 20–21 interactive pages for a Sahodaya event, and 24–31 entries per school event. Users are being asked to browse implementation outputs rather than answer operational questions.

### Recommended direction

1. **Immediately close school export scope leakage.** Use an explicit school-safe whitelist and require every school exporter to accept/enforce a school scope.
2. **Reduce the fest report hub to 12–15 decision reports.** Move print packs and operational sheets to the workflow page where they are used.
3. **Make applicability declarative.** Each report needs event types, roles/audiences, phase, scope, required data, and available formats.
4. **Fix lifecycle and routing gaps.** Named competition phases have a failing update path and their item-specific gates are not wired into live registration/mark entry.
5. **Keep MCQ and Training report catalogs compact.** These modules are much closer to the right shape than fest reporting.

## 2. Scope and event families

### A. Fest/competition events

Seven fixed school-facing types share one underlying workflow:

- Kalotsav (`kalolsavam`)
- Sports Meet (`sports`)
- Kids Fest (`kids_fest`)
- Teacher Fest (`teacher_fest`)
- English Fest (`english_fest`)
- Science Fest (`science_fest`)
- Custom Events (`custom`)

The Sahodaya side also supports database-defined competition types through `FestCompetitionTypeRegistry`, but the school route/navigation layer remains hardcoded to the seven types.

### B. Talent Search / MCQ exams

Separate model and lifecycle: registration, fee approval, hall tickets, online/offline delivery, attendance, marks import/manual marks, result publication, ranking, certificates, and reports.

### C. Training programs

Separate lifecycle: eligibility, nomination/self-registration, school or individual fee handling, sessions, QR/manual attendance, feedback, certificates/CPD, and attendance/registration reports.

### D. Generic school calendar events

`App\Models\Event` is a separate school-only CRUD feature (title, dates, venue, image). It is not connected to registrations, fees, schedules, results, certificates, or the fest report system. It should be named **Calendar events** or **School announcements** to avoid confusion with competition events.

Board Results were reviewed only as an adjacent results/reporting workflow, not as an event type.

## 3. Confirmed high-priority defects

### P0 — School users can request cross-school fest exports

**Evidence**

- `FestSchoolReportController::export()` accepts every ID in the Sahodaya-wide `FestReportCatalog`, merges `school_id`, then calls the generic service (`app/Http/Controllers/SchoolAdmin/FestSchoolReportController.php:1409`).
- No school-safe export whitelist exists.
- Several generic exporters do not accept a request or a school ID:
  - all registrations (`app/Services/Events/FestExportService.php:18`)
  - all results (`:45`)
  - all school fees (`:124`)
  - all fee lines (`:175`)
  - all event-level student registrations (`:219`)
- Other generic exports also ignore school scope: green-room list, judge sheet, mark-entry sheet, promotions, certificate counts, catering, volunteer roster, audit-log extract, and the all-member-schools student directory.

**Reachability**

This is not limited to guessed URLs. The school report UI directly exposes at least these unsafe exports:

- `registrations-export`
- `fee-summary` → `export/fee-breakdown`
- sports `event-athletes-export`
- sports `discipline-participation` export
- `certificate-counts-export`

**Impact:** Cross-school personal/financial/operational data disclosure.

**Required fix**

- Add a `SCHOOL_SAFE_EXPORT_IDS` allowlist or a dedicated school report catalog.
- Pass a mandatory school scope object, not an optional request parameter.
- Make an exporter fail closed if the requested audience is `school` and the dataset does not support school isolation.
- Add feature tests that seed two schools and assert School A's response never contains School B's identifiers for every school-safe export.

### P0 — School report URLs do not bind the program to the event type

`eventHub()` and the generic export dispatcher verify only that the event belongs to the school's parent Sahodaya. They do not assert that the route's `{program}` maps to the event's `event_type` (`FestSchoolReportController.php:125` and `:1409`).

A Kalotsav event ID can therefore be opened through a Sports URL (or another fixed program), producing the wrong catalog, labels, navigation, and program-specific actions.

**Fix:** Centralize `assertProgramMatchesEvent($program, $event)` and call it from every program-prefixed school event/report action.

### P1 — Named competition phase update currently fails

The targeted lifecycle suite fails in `FestEventPhaseLifecycleTest::test_effective_lifecycle_reads_back_what_quick_status_and_update_wrote`.

`FestEventPhaseService::updatePhase()` falls back to `$phase->is_default` when the field was not included (`app/Services/Events/FestEventPhaseService.php:43`). A model created without that attribute can hold `null` in memory even though the database default is false; the subsequent update attempts to write `NULL` into a non-null column.

**Fix:** normalize fallback with `(bool) ($phase->is_default ?? false)` or refresh the model before building the payload. Keep the regression test.

### P1 — Item-specific phase lifecycle is present but not active

`EventLifecycleGate::allowRegistrationForItem()` and `allowMarkEntryForItem()` explicitly state that they are not wired to live call sites (`app/Services/Events/EventLifecycleGate.php:26` and `:120`).

When phase mode is enabled, the configured per-phase registration windows and scoring locks can therefore be bypassed by the normal event-level registration and mark-entry flows.

**Fix:** wire the item-aware gate into manual, bulk, import, judge, coordinator, and school registration paths, then add a route-level test for each writer.

### P1 — School export lifecycle differs from Sahodaya lifecycle

Sahodaya generic exports enforce the catalog phase before dispatching (`FestReportController.php:769` and `:829`). The school generic dispatcher does not; it only reaches `FestReportService`, whose staff audience check returns immediately and whose lifecycle check covers result exports only.

The school UI also lists all report phases without filtering against the event's current lifecycle.

**Fix:** share one lifecycle enforcement service across Sahodaya and school endpoints; provide `currentPhase`/`allowedPhases` to the school report hub.

### P1 — Sports Entry Form link in Sahodaya reports points to the wrong portal

The Sahodaya interactive report catalog constructs the sports Entry Form as:

`/school-admin/{tenantId}/sports/events/{eventId}/games-entry-form`

(`app/Support/FestReportCatalog.php:190`). Here `tenantId` is the Sahodaya ID, not a school ID. A Sahodaya admin is sent into a school route with the wrong tenant identity.

**Fix:** remove this tile from the Sahodaya catalog or create a proper Sahodaya-side consolidated entry form.

### P1 — Dynamic competition types stop at the Sahodaya tier

The Sahodaya navigation merges database-backed types and exposes `/programs/{program}` routes (`FestCompetitionTypeRegistry.php:184`; `routes/includes/sahodaya_event_programs.php:175`). School routes, `SchoolFestProgram`, program maps, and URL detectors remain fixed lists (`routes/includes/school_event_programs.php:345`).

**Impact:** An administrator can create/publish a new competition type such as Robotics, but member schools do not receive the equivalent registration, reports, and event workspace.

**Decision needed:** either fully support dynamic types school-side or relabel the feature as Sahodaya-internal/custom configuration and prevent publishing it to schools.

### P1 — Custom event pages lose the event-scoped school sidebar

`schoolProgramNav.js` includes `custom`, but every regex in `schoolEventNav.js` omits it (`resources/js/support/schoolEventNav.js:13–58`). Custom event pages therefore fail detection and fall back to a less useful/global navigation state.

**Fix:** include `custom`, then add a route-detection test. Prefer deriving the regex from the program registry rather than copying lists.

### P1 — Sports navigation hides available operational features

Sports routes use the same event controllers, but the dedicated sports sidebar omits several routes shown for other fest types:

- Attendance
- Judges/staff assignment
- Appeals
- Event staff
- Leaderboard
- Athletic records
- Food coupons
- School invoices/event finance

See `resources/js/support/sportsEventNav.js:65` compared with `resources/js/support/sahodayaEventNav.js:85`.

This produces feature invisibility rather than missing backend capability. Add the applicable links or explicitly remove/redirect unsupported routes.

### P1 — Regional workflow regressions are present in reports and training eligibility

Two focused tests expose regional logic failures:

- `MissingReportsTest::test_region_performance_comparison_aggregates_school_points_per_region` cannot find the expected `Region A Leg` row from report `RPT-FST-009`, even though the partition hub, child region, and school result exist. The implementation builds rows from child events in `FestCrossEventReportService::regionPerformanceComparison()` (`app/Services/Reports/FestCrossEventReportService.php:327`).
- `TeacherTrainingEligibilityServiceTest::test_region_ids_via_school_assignment` still considers a teacher ineligible after a matching school/region assignment is created. The lookup applies the current Sahodaya academic year (`app/Services/Training/TeacherTrainingEligibilityService.php:138`), while the failing fixture uses `2025-26`; the production rule and write paths need one canonical academic-year source and a clear behavior when no/current-year context differs.

**Fix:** trace both queries with an explicit current academic year, make the year an injected/request-scoped value where appropriate, and add current-year plus historical-assignment cases.

### P1 — Lifecycle notification tests are not isolated/idempotent

Three event lifecycle notification tests error while inserting duplicate `notification_templates.slug` values (`fest.results.unpublished` and `fest.registration.needs_reapproval`). This makes the notification cascade suite order-dependent and prevents a reliable signal for result-unpublish and reapproval workflows.

**Fix:** make notification-template seeding/upserts idempotent and ensure each test owns or resets the template records it creates.

### P2 — Report catalog has event-type-invalid entries

The export catalog returns the same 50 exports for every fest event. It does not carry event applicability.

Examples:

- House-wise results are offered to all event types even though the interactive page is hidden for non-sports.
- `Sports Fee Breakdown` is offered to non-sports events.
- Age-group matrix and discipline registration appear where those concepts do not apply.
- Area-wise participants appear across all non-sports types, not only types using competition areas.
- Catering, houses, volunteer roster, and sports-specific documents can produce empty/misleading downloads for unrelated events.

**Fix:** add `event_types` or capability predicates to every report definition and enforce them server-side as well as in the UI.

### P2 — Report files are vulnerable to catalog drift

There are three independently maintained catalogs/mappings:

- PHP export catalog (`FestReportCatalog`)
- JS school catalog (`SCHOOL_EVENT_REPORTS`)
- JS export-to-preview/category maps

The current tests check PHP metadata completeness, but not JS/PHP parity, actual route existence, school safety, or event-type applicability.

**Fix:** make PHP return the catalog to both UIs, or generate the JS artifact from one typed source. Add a route-contract test.

## 4. Fest report rationalization

### Current size

- Sahodaya: **50 bulk exports** and **20 non-sports / 21 sports interactive entries**.
- School: **32 definitions**, resulting in **24 Kalotsav**, **25 Teacher Fest**, and **31 Sports** entries before head-only filtering.

Formats are currently counted as separate reports, inflating the apparent catalog. A report should be a business question; PDF/Excel/CSV are output choices.

### A. Must-have report concepts

These should remain visible in the main report hub.

| Report concept | Why it is required | Primary audience | Phase |
|---|---|---|---|
| Registration register | Canonical approved/pending/rejected roster | Sahodaya + school-scoped | Before |
| Participant by item | Competition-day roster and verification | Sahodaya + school-scoped | Before |
| Student/teacher participation | Detect overload and confirm a person's assignments | Sahodaya + school-scoped | Before |
| Item registration counts | Capacity/readiness by item | Sahodaya + school-scoped | Before |
| Pending approvals | Action queue, not just analytics | Sahodaya + school-scoped | Before |
| Fee status/reconciliation | Due, paid, rejected, credit owed | Finance + school-scoped | Before |
| Schedule by date/venue | Operational timetable | Staff/public-safe version | Before/During |
| Schedule clashes | Corrective action queue | Sahodaya + school-scoped | Before |
| Attendance | Event-day control and audit | Staff + school-scoped | During |
| Mark-entry completeness | Blocks premature result publication | Event operations | During |
| Item result sheet | Official item outcome | Staff/public after publish | After |
| School performance/ranking | Championship and school result summary | Staff/public after publish | After |
| Qualifiers/promotions | Next-level handoff register | Staff + affected school | After |
| Certificate issuance status | Missing/generated/collected counts | Certificate team + school-scoped | After |
| Event audit trail | Compliance and dispute resolution | Privileged staff only | All |

### B. Keep, but only when the event capability applies

- House results and championship/medal tally — only house-enabled sports/events.
- Athletic records — sports only.
- Age-group matrix — sports/Kids Fest or events explicitly configured for age groups.
- Competition-area report — only area-enabled custom/dynamic types.
- Teacher-wise report — Teacher Fest only.
- Team/squad roster — group/team items only.
- Catering and food billing — only when enabled and used.
- Region combined/region-specific reports — only partitioned events.
- Level 2/next-level qualifier reports — only linked multi-level events.

### C. Move out of the Reports hub

These are useful artifacts, but not analytical reports:

| Artifact | Better location |
|---|---|
| ID cards / admit cards | ID Card / Hall Ticket workspace |
| Attendance blank sheet | Attendance page |
| Judge evaluation sheet | Judges / mark-entry page |
| Mark-entry blank sheet | Mark-entry page |
| Performance order | Schedule/run-of-show page |
| Green-room list | Event-day operations page |
| Volunteer roster | Event staff page |
| Team/squad sheet | Registration/item page |
| Day bulletin | Schedule page |
| Certificate counts | Certificate workspace |
| Audit log extract | Activity/Audit page |

The report hub may link to these workspaces under **Operational documents**, but they should not compete with analytical reports.

### D. Remove or merge

1. **Remove `cumulative`** — it directly returns `overallRankingPdf()` (`FestReportService.php:682`). It is the same report under a second name and a different phase.
2. **Merge `sahodaya-ranking` into `overall-ranking`** — it uses the same view and the same `schoolRankingRows()` dataset (`FestReportService.php:1319`). Keep one report with an optional title.
3. **Remove one of `mark-entered-summary` / `mark-entry-status`** — both dispatch to the exact same method (`FestReportService.php:320`).
4. **Merge file-format pairs into one report:**
   - item schedule CSV/PDF
   - clashes CSV/PDF
   - promotions CSV/PDF
   - registration spreadsheet/PDF
   - attendance item/school pivots under one configurable attendance concept
5. **Remove `students` from event reports.** It exports every active student across member schools, not event participants (`FestReportService.php:1272`). This belongs in the membership/student directory and is especially dangerous on school routes.
6. **Rename/generalize `fee-breakdown`.** It is labeled sports-specific but is exposed for every type.
7. **Merge registration variants** around a single configurable roster (group by school/item/person; format PDF/Excel) instead of separate master, category, item, student, event-registration, and student-participation exports.

### Proposed main fest report hub (14 concepts)

1. Registration & approval register
2. Participant roster (item/person toggle)
3. Registration counts/capacity
4. Fees & reconciliation
5. Schedule
6. Clash queue
7. Attendance
8. Assignment/numbering readiness
9. Mark-entry completeness
10. Item results
11. School ranking/performance
12. Qualifiers/promotions
13. Certificate status
14. Audit trail

Capability-specific cards should be injected only when applicable.

## 5. Event-type feature matrix

Legend: **Core** = shared workflow; **Extra** = meaningful specialization; **Gap** = missing/broken access or enforcement.

| Event type | Core workflow | Specialized features | Main gaps |
|---|---|---|---|
| Kalotsav | Full fest lifecycle | School rounds, combo rules, grades, championship | Overlarge generic reports; type-invalid exports |
| Sports | Full fest lifecycle | Age groups, sport-as-event topology, athletic records, houses, rankings/championship, games entry | Dedicated nav hides operational routes; Sahodaya Entry Form link broken; legacy head concepts still visible in reporting code |
| Kids Fest | Full fest lifecycle | Age-group fees, championship | Needs applicability-driven age reports; otherwise mostly complete |
| Teacher Fest | Full fest lifecycle with teacher participants | Teacher-wise reporting | Generic student wording/data assumptions remain in some outputs |
| English Fest | Full fest lifecycle | Championship | No strong type-specific gap; inherits report/security issues |
| Science Fest | Full fest lifecycle | Championship | No strong type-specific gap; inherits report/security issues |
| Custom | Full backend fest lifecycle, areas/combos | Flexible competition areas | School event sidebar detection broken; fixed maps constrain extensibility |
| Dynamic DB type | Sahodaya program/catalog/event entry | Custom label/icon/type | School registration/report workflow is not implemented |
| MCQ | Full exam lifecycle, online/offline, fees, attendance, results, certificates | Series/levels, grade bands, session monitoring, malpractice | Report page mixes configuration export (grade bands) with reports; lifecycle test coverage should expand |
| Training | Full program lifecycle, QR/manual attendance, fees, feedback, certificates/CPD | Sessions, waitlist, resource persons | No unified report hub; outputs are distributed, but catalog size is reasonable |
| Generic school event | CRUD only | Calendar/image | Name collides conceptually with competition events; no workflow/reporting by design |

## 6. Workflow audit

### Fest lifecycle

Intended sequence:

1. Program/catalog setup
2. Event creation and items
3. Settings, eligibility, regions/levels, fees, registration windows
4. Publish/open registration
5. School event-level roster → item registration → billing/payment
6. Sahodaya approval/rejection; substitutions and clash requests
7. Numbering, schedule, judges/staff, attendance
8. Marks, results publish/unpublish, appeals
9. Qualifiers, certificates, championship, audit/archive

**What works well**

- Guarded status transition matrices exist for fest, MCQ, and training.
- Event publish blocks a completely empty event and requires venue or start date.
- Fest result publish supports unpublish/correction.
- The school fest workflow is explicitly ordered into event registration, item registration, and payment.
- Region-aware report remediation is present across many report builders.

**Workflow gaps**

- Item-level phase windows/locks are not enforced in live writers.
- The phase update test is failing.
- School report/export lifecycle is inconsistent with the Sahodaya path.
- No catalog-level `required_data` contract explains why a report is empty (for example, no schedule, no houses, no grades, no fee records).
- The UI often presents diagnostics as reports rather than action queues linked to a correcting screen.
- Dynamic program creation is not a complete end-to-end feature because schools cannot consume it.

### MCQ workflow

The current MCQ workflow is materially complete: offline marks bulk import and manual entry exist, result correction/unpublish exists, certificates and verification exist, and reporting is appropriately smaller.

Recommended visible report concepts:

- Registration/class counts
- Fee reconciliation (with pending/rejected filters)
- Attendance/absent/malpractice
- Marks pending
- Result analysis
- School performance
- Toppers/qualifiers
- Online session status only for online exams

Move **Grade bands** to exam configuration. Keep filters as views/exports of one concept rather than separate report cards where possible.

### Training workflow

The training outputs are well scoped:

- Registration roster (Excel/PDF)
- Blank attendance sheet
- Filled attendance report (Excel/PDF)
- QR registration report
- Certificate ZIP

Do not copy the fest catalog model here. A small **Outputs** section in the program workspace is enough.

## 7. Test and build evidence

### Passed

- Production frontend build: `npm run build` — passed (1,128 modules transformed).
- Full PHPUnit run with an explicit 512 MB parent process: 597 tests; 574 passed, 17 failed, 6 errored, 2 risky; 3,148 assertions.
- Event/report-focused run: 92 tests; 86 passed, 1 failed, 5 errored, 2 risky; 731 assertions.

### Event/report-focused failures

- Named phase update: `NOT NULL constraint failed: fest_event_phases.is_default`.
- Region performance comparison: expected `Region A Leg` is absent from the keyed report rows.
- Training regional eligibility: a matching school-region assignment is not accepted under the test's academic-year context.
- Three lifecycle notification-cascade tests error on duplicate notification-template slugs.

### Test infrastructure gap

The normal `php artisan test --compact` command exhausts the configured 128 MB PHP memory limit while loading the AWS SDK during `TrainingCertificateServiceTest`. Even `php -d memory_limit=512M artisan test` delegates to a child PHP process constrained to 128 MB. Running `php -d memory_limit=512M vendor/bin/phpunit --no-progress` bypasses that runner issue and completed, producing the full-suite counts above.

**Fix:** set the spawned test-process memory in `phpunit.xml`/test bootstrap or avoid initializing the S3 driver in unit tests by forcing a fake/local disk. Separately triage the 23 full-suite failures/errors; they include event and non-event regressions.

## 8. Prioritized delivery plan

### Immediate — security and correctness

1. Lock the school generic export endpoint to a school-safe whitelist.
2. Fix every visible unsafe school export and add two-school isolation tests.
3. Enforce program/event-type matching on all school program routes.
4. Fix the phase `is_default` null write.
5. Remove/fix the Sahodaya sports Entry Form tile.

### Phase 1 — workflow integrity

1. Wire item-aware phase gates into every registration/mark writer.
2. Share report lifecycle gates between school and Sahodaya.
3. Restore missing Sports operational navigation.
4. Fix Custom event route detection.
5. Decide and implement/end dynamic school program support.

### Phase 2 — report redesign

1. Introduce a single report registry with:
   - report concept ID
   - event capability/type applicability
   - audience and authorization
   - dataset scope (`school`, `event`, `region`, `combined`)
   - lifecycle phase
   - required data/readiness state
   - formats
   - interactive/operational destination
2. Replace the main hub with the 14 core concepts.
3. Move operational documents into their owning workflow pages.
4. Merge duplicate formats and delete the three exact/near-exact duplicate report concepts.
5. Add catalog contract tests for route existence, scope isolation, applicability, lifecycle, and JS/PHP parity.

### Phase 3 — usability and measurement

1. Add report usage telemetry: opened, exported, filters, row count, actor role.
2. After one event cycle, archive reports with zero/near-zero use unless legally required.
3. Add saved report presets only for commonly repeated operational views.

## 9. Acceptance criteria

- A school user cannot retrieve another school's identifier from any fest report/export.
- Every report shown applies to the current event type and has the required source data.
- A business report appears once; output formats are choices, not separate cards.
- Result reports cannot be accessed before publication, and named phase rules are enforced at every writer/export.
- Custom and dynamic program behavior is consistent across Sahodaya and school portals.
- Sports users can navigate to every supported operational feature without knowing a direct URL.
- The targeted lifecycle suite and the full test suite pass under documented test settings.
