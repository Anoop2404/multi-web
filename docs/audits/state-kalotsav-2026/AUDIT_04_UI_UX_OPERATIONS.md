# AUDIT 04 — Frontend, Administration and Operational Readiness

## 1. Stack

Inertia.js with Vue 3 server-rendered pages under `resources/js/Pages/Admin/State/`, plus
server-rendered Blade for the public-facing State pages under `resources/views/state/public/` and
for print output under `resources/views/state/print/`.

| Surface | Count | Location |
|---|---|---|
| State admin Inertia pages | 37 | `resources/js/Pages/Admin/State/` |
| State public Blade views | 7 (+2 partials) | `resources/views/state/public/` |
| State print Blade views | 3 | `resources/views/state/print/` |
| State report Blade view | 1 | `resources/views/state/reports/table.blade.php` |
| State controller files | 33 | `app/Http/Controllers/` (StateAdmin + State-named) |
| State routes | 234 matching `state` | `routes/web.php` (2,453 lines, 2,887 total routes) |

37 pages is a real admin surface, not a stub. The breakdown by persona below uses these counts.

## 2. Persona coverage

The brief lists 12 personas. Verdict per persona, based on routes, controllers, middleware, and
tests found.

| # | Persona | Coverage | Evidence |
|---|---|---|---|
| 1 | Superadministrator | **COMPLETE** | Bypasses `EnsureStateAdmin`; `StateScope::shouldScope()` returns false for superadmin so all queries are unscoped. Tenant management via `TenantController` (also uses `StateScope`). |
| 2 | State administrator | **COMPLETE** | `EnsureStateAdmin` middleware, `state.admin` route group, 37 pages. Has all State capabilities. |
| 3 | State organizing committee | **PARTIAL** | The committee's functions are spread across `StateFestWorkspaceController`, `StateConductController` (venues, schedule, volunteers, catering, green room), and the reports controller. There is no distinct "committee" role or a consolidated committee dashboard — a committee member is given whichever operator roles they need. Works, but not modelled. |
| 4 | Sahodaya administrator | **COMPLETE** | The `SahodayaAdmin` controllers plus the winner-sheet / slot-collection flow. The winner sheet is currently **BROKEN** by P0-01. |
| 5 | School administrator | **COMPLETE** | Registration submission, slot response accept/opt-out with reason, nomination tracking. |
| 6 | Registration coordinator | **COMPLETE** | Scrutiny controller, `Registrations.vue`, `PendingApprovals.vue`. |
| 7 | Stage coordinator | **COMPLETE** | `Attendance.vue`, `GreenRoom.vue`, `Venues.vue`, `Schedule.vue`, `Slots.vue`, `ChestNumbers.vue`. |
| 8 | Judge | **COMPLETE** | `StateJudgePortalAndImportTest`, `StateJudgeScoreService`, `Portal/StateFestJudge` pages. |
| 9 | Appeal committee member | **PARTIAL** | `Appeals.vue` plus `StateAppealService`. No committee model; a single `decided_by_user_id`. Functional but not the committee shape the manual may describe. |
| 10 | Finance operator | **COMPLETE** | `StateRemittanceController`, `StateRemittanceService`, `StateRemittance` model, `StateRemittances` pages. |
| 11 | Certificate operator | **COMPLETE** | `StateCertificateController`, `StateCertificateService`, `Certificates.vue`, `state/print/certificate.blade.php`. |
| 12 | Public results viewer | **COMPLETE** | `state-results.blade.php`, `StatePublicResultsProjectionService`, and the public Blade set (home, results, ranking, schedule, sahodaya, verify). |

Net: 8 of 12 personas are COMPLETE, 3 are PARTIAL, and 1 (Sahodaya admin) is COMPLETE but
blocked by P0-01 on the winner-sheet sub-flow.

## 3. Page-by-page: State admin surface

Verified present in `resources/js/Pages/Admin/State/`:

**Core** — `Dashboard.vue`, `Kalotsav/Index.vue`, `Kalotsav/ProgramDetail.vue`,
`Kalotsav/Results.vue`, `Kalotsav/Winners.vue`, `Users/Index.vue`.

**Program and items** — `Fest/Overview.vue`, `Fest/Items.vue`, `Fest/Settings.vue`,
`Fest/Eligibility.vue`.

**Intake and scrutiny** — `Fest/Submissions.vue`, `Fest/Scrutiny.vue`, `Fest/Registrations.vue`,
`Fest/PendingApprovals.vue`.

**Teams and slots** — `Fest/Teams.vue`, `Fest/Substitutions.vue`.

**Conduct** — `Fest/Venues.vue`, `Fest/Schedule.vue`, `Fest/Slots.vue`, `Fest/ChestNumbers.vue`,
`Fest/Attendance.vue`, `Fest/GreenRoom.vue`, `Fest/Catering.vue`, `Fest/Volunteers.vue`,
`Fest/Staff.vue`, `Fest/Clashes.vue`.

**Scoring and results** — `Fest/Marks.vue`, `Fest/Grades.vue`, `Fest/Points.vue`,
`Fest/Results.vue`, `Fest/Leaderboard.vue`.

**Awards** — `Fest/PrizeCategories.vue`, `Fest/Certificates.vue`, `Fest/Appeals.vue`.

**Output** — `Fest/Reports.vue`, `Fest/ReportPreview.vue`, `Fest/PublicPortal.vue`.

## 4. Gaps in the page surface

The brief asks for a set of specific pages. Cross-checking:

| Required page | Status |
|---|---|
| State dashboard | **Present** — `Fest/Dashboard.vue` |
| Sahodaya management | **Present** — via `ExternalSahodayaController` and the program's `sahodaya.items` routes |
| State event creation | **Present** — `StateFestWorkspaceController` |
| Master configuration | **Present** — `Fest/Settings.vue` |
| Academic year | **PARTIAL** — no dedicated academic-year page or model was found. Isolation is per `FestStateProgram`, which is per-year in practice, but there is no explicit academic-year entity, and no code that prevents two programs from overlapping in time. |
| Rules and eligibility | **Present** — `Fest/Eligibility.vue` |
| Sahodaya nomination intake | **Present** — `Fest/Submissions.vue` |
| State participant registry | **Present** — `Fest/Registrations.vue` |
| Team registry | **Present** — `Fest/Teams.vue` |
| Registration approval | **Present** — `Fest/PendingApprovals.vue` |
| Fee verification | **Present** — `StateRemittanceController` |
| State competition management | **Present** — the Conduct set |
| Venues/stages | **Present** — `Fest/Venues.vue` |
| Judge management | **PARTIAL** — judge portal and import exist; no judge-assignment screen was found that binds judges to specific items or venues. Given judging is chest-number-blind, an assignment screen may be unnecessary, but the brief asks, so it is recorded as PARTIAL. |
| Stage schedule | **Present** — `Fest/Schedule.vue` |
| Chest/roll assignment | **Present** — `Fest/ChestNumbers.vue` |
| Results and publication | **Present** — `Fest/Results.vue` + `StateResultPublicationService` |
| Appeals | **Present** — `Fest/Appeals.vue` |
| Certificates | **Present** — `Fest/Certificates.vue` |
| Overall points | **Present** — `Fest/Points.vue`, `Fest/Leaderboard.vue` |
| Reports | **Present** — `Fest/Reports.vue`, `Fest/ReportPreview.vue`, `StateParticipationReportController` |
| Audit logs | **PARTIAL** — `StateConductAudit` records mark and result changes and appeal decisions with who/when/why, and the capability `state.fest.audit` is defined in `StateFestPermissions`. An audit viewer exists at the Sahodaya level (`resources/js/Pages/Admin/Sahodaya/Audit/Index.vue`, with School and Admin equivalents). What was **not** found is a State-level audit viewer page or a route guarded by `state.fest.audit`. The capability is declared but appears unused, and the State audit rows are written without an operator-readable surface. |
| Settings | **Present** — `Fest/Settings.vue` |
| User roles and permissions | **Present** — `Users/Index.vue` + `StateFestPermissions` capability matrix |

Net: 22 of 25 present, 3 partial (academic year, judge assignment screen, audit-log viewer).

## 5. UX audit

| Check | Finding |
|---|---|
| State vs Sahodaya context visibility | **Good.** `StateScope` and the `stateId` request attribute make scoping explicit server-side; the shell component receives the program and state. The risk is a user not knowing which State they are in when they are superadmin and unscoped — worth a visible indicator. |
| School identity | Present. `school_id` and `school_name` on State qualifier entries and registrations. |
| Participant identity | Present. `student_name`, `roll_number`, `class_name`. |
| Academic year | **Weak.** Per above. |
| Competition identification | Present. `item_id`, `item_code`, `item_name` throughout. |
| Search behavior | **UNVERIFIED.** Inertia pages were not exercised interactively in this audit. |
| Filters and pagination | **UNVERIFIED** interactively. Pagination matters here: the brief calls out 5,000–20,000 participants and "large tabular datasets". `StateQualifierReviewController` and `Fest/Registrations.vue` were read as code, not clicked. |
| Bulk actions | Present in the Sahodaya program routes (`bulkToggleSahodayaItems`). |
| Form error handling | Good. Validation messages are specific and human-readable throughout (`Say why this winner is not going. The Sahodaya will see this reason.`). |
| Duplicate submissions | Handled at the service layer: `lockForUpdate()` on slot response, idempotent judge score submit, outbox idempotency. |
| Loading and failure states | **UNVERIFIED** interactively. |
| Mobile responsiveness | **UNVERIFIED.** No responsive breakpoint review was performed. |
| Accessibility | **UNVERIFIED.** No a11y audit performed. |
| Print layouts | Present and non-trivial: `state/print/sheet.blade.php`, `certificate.blade.php`, `cards.blade.php`, `state/reports/table.blade.php`. |
| Large tabular datasets | **NOT TESTED.** See `AUDIT_05`. |

The interactive items are genuinely unverified and are flagged as such rather than guessed.

## 6. KEEP / REUSE / MODIFY / MERGE / CREATE / REMOVE

**KEEP** — the State public portal (`state/public/*`) and the State print templates. They are
working, and redesigning them would produce no functional gain. The Sahodaya-facing scoring and
results pages. The portal credential flow from the recent commits.

**REUSE** — the Sahodaya-grade `FestGradePointService` is already reused by the State side through
`StateGradePointService`'s tier chain. `StateConductService` reuses `FestTeamSquadRules`. The
public scoreboard projection is shared. This reuse is the reason State results work at all, and it
should be preserved.

**MODIFY** — `FestStateNominationService` / `FestStateWinnerSheetService` / `FestStateSlotCollectionController`:
the minimum change to fix P0-01 and nothing more. Also `Fest/Appeals.vue` to surface a
"pending re-rank" state after an upheld appeal (F-03-01).

**MERGE** — nothing. Merging the two workspace stacks is explicitly not recommended; the
`module_switched` flag exists precisely so both can coexist during cutover.

**CREATE** — a State audit-log viewer page (the `state.fest.audit` capability already exists but no route consumes it), an academic-year entity or a program-overlap guard, and the multi-Sahodaya test fixture.

**REMOVE** — the old `/admin/state-workspace/*` screens, but only after `STATE_MODULE_SWITCHED`
is on and verified in production. The code comment in `config/state.php` states the flag is
deliberately one setting so it can be turned back off during an event rather than needing a deploy.
Do not remove the fallback before a full State program has run on the new stack.

## 7. Operational readiness by workflow

| Workflow | Ready |
|---|---|
| State program creation | Yes |
| Sahodaya onboarding (tenant and external) | Yes |
| Qualifier intake and approval | Yes (pending P0-01 on the Sahodaya side) |
| State registration and scrutiny | Yes |
| Venues, schedule, slots, chest numbers | Yes |
| Mark entry | Yes |
| Result publication | Yes, with correct incomplete-data guards |
| Points and leaderboard | Yes |
| Appeals | Yes for the decision path; manual re-rank required |
| Certificates | Yes for generation |
| Reports | Yes |
| Public portal | Yes |
| Remittances / fees | Yes |
