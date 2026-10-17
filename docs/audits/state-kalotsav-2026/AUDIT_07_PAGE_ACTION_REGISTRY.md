# AUDIT 07 — Page and Action Registry

Complete inventory of State Kalotsav routes, controllers, and pages. Every route below was read
from `routes/web.php`; controller names are `app/Http/Controllers/...` unless prefixed; pages are
`resources/js/Pages/Admin/State/` (Inertia) or `resources/views/state/` (Blade).

Important structural note for anyone reading this registry: **there are two State stacks.** The
older `/admin/state-workspace/*` routes sit behind `RedirectStateWorkspaceToModule` and are inert
until `state.module_switched` is on, at which point they redirect into the module. The current
surface is `/admin/state/fest/*`. Both are listed below because the old routes are live in
production bookmarks.

## 1. Central State program management (`/admin/state-programs`)

| Route name | Method | Controller | Action |
|---|---|---|---|
| `state-programs.index` | GET | `Admin\StateFestProgramController` | index |
| `state-programs.store` | POST | `Admin\StateFestProgramController` | store |
| `state-programs.show` | GET | `Admin\StateFestProgramController` | show |
| `state-programs.update` | PUT | `Admin\StateFestProgramController` | update |
| `state-programs.destroy` | DELETE | `Admin\StateFestProgramController` | destroy |
| `state-programs.publish` | POST | `Admin\StateFestProgramController` | publish |
| `state-programs.items.store` | POST | `Admin\StateFestProgramController` | storeItem |
| `state-programs.items.update` | PUT | `Admin\StateFestProgramController` | updateItem |
| `state-programs.items.destroy` | DELETE | `Admin\StateFestProgramController` | destroyItem |
| `state-programs.results` | GET | `Admin\StateFestProgramController` | results |
| `state-programs.winners` | GET | `Admin\StateFestProgramController` | winners |
| `state-programs.winners.export` | GET | `Admin\StateFestProgramController` | exportWinners |
| `state-programs.sahodaya.toggle` | POST | `Admin\StateFestProgramController` | toggleSahodaya |
| `state-programs.sahodaya.items` | GET | `Admin\StateFestProgramController` | sahodayaItems |
| `state-programs.sahodaya.items.toggle` | POST | `Admin\StateFestProgramController` | toggleSahodayaItem |
| `state-programs.sahodaya.items.bulk-toggle` | POST | `Admin\StateFestProgramController` | bulkToggleSahodayaItems |
| `state-programs.external-sahodayas.*` | GET/POST | `Admin\ExternalSahodayaController` | index/store/toggleStatus |
| `state-programs.external-schools.index` | GET | `Admin\ExternalSchoolController` | index |
| `state-programs.external-schools.reset-password` | POST | `Admin\ExternalSchoolController` | resetPassword |

Page: `resources/js/Pages/Admin/State/StatePrograms/Index.vue` (verified by
`StateCrossIsolationTest::assertInertia(fn ($page) => $page->component('StatePrograms/Index'))`).

## 2. State Kalotsav legacy dashboard (`/admin/kalotsav`)

| Route name | Method | Controller | Action | Page |
|---|---|---|---|---|
| `kalotsav.index` | GET | `Admin\KalotsavStateController` | index | `Admin/State/Kalotsav/Index.vue` |
| `kalotsav.show` | GET | `Admin\KalotsavStateController` | show | `Admin/State/Kalotsav/ProgramDetail.vue` |
| `kalotsav.results` | GET | `Admin\KalotsavStateController` | results | `Admin/State/Kalotsav/Results.vue` |
| `kalotsav.winners` | GET | `Admin\KalotsavStateController` | winners | `Admin/State/Kalotsav/Winners.vue` |
| `kalotsav.winners.export` | GET | `Admin\KalotsavStateController` | exportWinners | — |

## 3. State qualifier intake (`/admin/state-workspace/qualifiers`)

| Route name | Method | Controller | Action |
|---|---|---|---|
| `state.qualifiers.index` | GET | `StateAdmin\StateQualifierReviewController` | index |
| `state.qualifiers.store-intake` | POST | `StateAdmin\StateQualifierReviewController` | storeIntake |
| `state.qualifiers.show` | GET | `StateAdmin\StateQualifierReviewController` | show |
| `state.qualifiers.approve` | POST | `StateAdmin\StateQualifierReviewController` | approve |
| `state.qualifiers.entries.store` | POST | `StateAdmin\StateQualifierReviewController` | storeEntry |
| `state.qualifiers.entries.update` | PUT | `StateAdmin\StateQualifierReviewController` | updateEntry |
| `state.qualifiers.entries.destroy` | DELETE | `StateAdmin\StateQualifierReviewController` | destroyEntry |
| `state.qualifiers.entries.review` | POST | `StateAdmin\StateQualifierReviewController` | reviewEntry |

Note: this is the *older* intake screen. The module's equivalent is `state.fest.submissions` plus
`state.fest.scrutiny` below.

## 4. State Kalotsav module (`/admin/state/fest/{event}/...`)

All guarded by `EnsureStateAdmin` plus a `state.fest:*` capability. The route comments explain the
capability choices and they are worth repeating, because they show deliberate separation of
concerns.

| Route name | Capability | Method | Controller | Action | Page |
|---|---|---|---|---|---|
| `state.fest.overview` | `state.fest:view` | GET | `StateAdmin\Fest\StateFestWorkspaceController` | overview | `Fest/Overview.vue` |
| `state.fest.submissions` | `state.fest:qualifiers` | GET | `StateAdmin\Fest\StateScrutinyController` | submissions | `Fest/Submissions.vue` |
| `state.fest.scrutiny` | `state.fest:scrutiny` | GET | `StateAdmin\Fest\StateScrutinyController` | scrutiny | `Fest/Scrutiny.vue` |
| `state.fest.scrutiny.decide` | `state.fest:scrutiny` | POST | `StateAdmin\Fest\StateScrutinyController` | decide | — |
| `state.fest.scrutiny.reserve` | `state.fest:scrutiny` | POST | `StateAdmin\Fest\StateScrutinyController` | acceptReserve | — |
| `state.fest.scrutiny.finalise` | `state.fest:scrutiny` | POST | `StateAdmin\Fest\StateScrutinyController` | finalise | — |
| `state.fest.scrutiny.reopen` | `state.fest:scrutiny` | POST | `StateAdmin\Fest\StateScrutinyController` | reopen | — |
| `state.fest.pending` | `state.fest:scrutiny` | GET | `StateAdmin\Fest\StateScrutinyController` | pending | `Fest/PendingApprovals.vue` |
| `state.fest.registrations` | `state.fest:registrations` | GET | `StateAdmin\Fest\StateScrutinyController` | registrations | `Fest/Registrations.vue` |
| `state.fest.teams` | `state.fest:registrations` | GET | `StateAdmin\Fest\StateTeamController` | teams | `Fest/Teams.vue` |
| `state.fest.teams.leader` | `state.fest:registrations` | POST | `StateAdmin\Fest\StateTeamController` | setLeader | — |
| `state.fest.teams.standby` | `state.fest:registrations` | POST | `StateAdmin\Fest\StateTeamController` | setStandby | — |
| `state.fest.substitutions` | `state.fest:registrations` | GET | `StateAdmin\Fest\StateTeamController` | substitutions | `Fest/Substitutions.vue` |
| `state.fest.substitutions.request` | `state.fest:registrations` | POST | `StateAdmin\Fest\StateTeamController` | requestSubstitution | — |
| `state.fest.substitutions.decide` | `state.fest:registrations` | POST | `StateAdmin\Fest\StateTeamController` | decideSubstitution | — |
| `state.fest.attendance` | `state.fest:attendance` | GET | `StateAdmin\Fest\StateConductController` | attendance | `Fest/Attendance.vue` |
| `state.fest.attendance.mark` | `state.fest:attendance` | POST | `StateAdmin\Fest\StateConductController` | markAttendance | — |
| `state.fest.marks` | `state.fest:marks` | GET | `StateAdmin\Fest\StateConductController` | marks | `Fest/Marks.vue` |
| `state.fest.marks.aggregate` | `state.fest:marks` | POST | `StateAdmin\Fest\StateConductController` | aggregate | — |
| `state.fest.marks.import-template` | `state.fest:marks` | GET | `StateAdmin\Fest\StateConductController` | importTemplate | — |
| `state.fest.marks.import` | `state.fest:marks` | POST | `StateAdmin\Fest\StateConductController` | importMarks | — |
| `state.fest.results` | `state.fest:results` | GET | `StateAdmin\Fest\StateConductController` | results | `Fest/Results.vue` |
| `state.fest.leaderboard` | `state.fest:results` | GET | `StateAdmin\Fest\StateConductController` | leaderboard | `Fest/Leaderboard.vue` |
| `state.fest.results.compute` | `state.fest:results` | POST | `StateAdmin\Fest\StateConductController` | resultAction(action=compute) | — |
| `state.fest.results.publish` | `state.fest:publish` | POST | `StateAdmin\Fest\StateConductController` | resultAction(action=publish) | — |
| `state.fest.results.unpublish` | `state.fest:publish` | POST | `StateAdmin\Fest\StateConductController` | resultAction(action=unpublish) | — |
| `state.fest.results.lock` | `state.fest:publish` | POST | `StateAdmin\Fest\StateConductController` | resultAction(action=lock) | — |
| `state.fest.public-portal` | `state.fest:publish` | GET | `StateAdmin\Fest\StateEventConfigController` | publicPortal | `Fest/PublicPortal.vue` |
| `state.fest.public-portal.save` | `state.fest:publish` | POST | `StateAdmin\Fest\StateEventConfigController` | savePublicVisibility | — |
| `state.fest.appeals` | `state.fest:appeals` | GET | `StateAdmin\Fest\StateCertificateController` | appeals | `Fest/Appeals.vue` |
| `state.fest.appeals.submit` | `state.fest:appeals` | POST | `StateAdmin\Fest\StateCertificateController` | submitAppeal | — |
| `state.fest.appeals.decide` | `state.fest:appeals` | POST | `StateAdmin\Fest\StateCertificateController` | decideAppeal | — |
| `state.fest.certificates` | `state.fest:certificates` | GET | `StateAdmin\Fest\StateCertificateController` | certificates | `Fest/Certificates.vue` |
| `state.fest.certificates.generate` | `state.fest:certificates` | POST | `StateAdmin\Fest\StateCertificateController` | generate | — |
| `state.fest.certificates.detect-stale` | `state.fest:certificates` | POST | `StateAdmin\Fest\StateCertificateController` | detectStale | — |
| `state.fest.certificates.pack` | `state.fest:certificates` | GET | `StateAdmin\Fest\StateCertificateController` | pack | — |
| `state.fest.certificates.print` | `state.fest:certificates` | GET | `StateAdmin\Fest\StateCertificateController` | print | — |
| `state.fest.settings` | `state.fest:settings` | GET/POST | `StateAdmin\Fest\StateEventConfigController` | settings/saveSettings | `Fest/Settings.vue` |
| `state.fest.staff` | `state.fest:settings` | GET/POST/DELETE | `StateAdmin\Fest\StateEventConfigController` | staff/storeStaff/destroyStaff | `Fest/Staff.vue` |
| `state.fest.items` | `state.fest:catalog` | GET | `StateAdmin\Fest\StateEventConfigController` | items | `Fest/Items.vue` |
| `state.fest.grades` | `state.fest:settings` | GET/POST | `StateAdmin\Fest\StateGradingController` | grades/saveGrades | `Fest/Grades.vue` |
| `state.fest.grades.apply-standard` | `state.fest:settings` | POST | `StateAdmin\Fest\StateGradingController` | applyStandard | — |
| `state.fest.points` | `state.fest:settings` | GET/POST | `StateAdmin\Fest\StateGradingController` | points/savePoints | `Fest/Points.vue` |
| `state.fest.points.apply-standard` | `state.fest:settings` | POST | `StateAdmin\Fest\StateGradingController` | applyStandard | — |
| `state.fest.prizes` | `state.fest:settings` | GET/POST | `StateAdmin\Fest\StatePrizeCategoryController` | index/save | `Fest/PrizeCategories.vue` |
| `state.fest.prizes.full-standings` | `state.fest:settings` | GET | `StateAdmin\Fest\StatePrizeCategoryController` | fullStandings | — |
| `state.fest.prizes.assign` | `state.fest:settings` | POST | `StateAdmin\Fest\StatePrizeCategoryController` | assign | — |
| `state.fest.prizes.destroy` | `state.fest:settings` | DELETE | `StateAdmin\Fest\StatePrizeCategoryController` | destroy | — |
| `state.fest.eligibility` | `state.fest:settings` | GET | `StateAdmin\Fest\StateGradingController` | eligibility | `Fest/Eligibility.vue` |
| `state.fest.eligibility.seed` | `state.fest:settings` | POST | `StateAdmin\Fest\StateGradingController` | seedCategories | — |
| `state.fest.eligibility.categories.save` | `state.fest:settings` | POST | `StateAdmin\Fest\StateGradingController` | saveCategory | — |
| `state.fest.eligibility.categories.destroy` | `state.fest:settings` | DELETE | `StateAdmin\Fest\StateGradingController` | destroyCategory | — |
| `state.fest.hospitality` | `state.fest:settings` | GET/POST | `StateAdmin\Fest\StateHospitalityController` | index/save | `Fest/Catering.vue` |

The route comments document three deliberate capability decisions that are worth preserving:

1. Reading submissions is a **qualifier** capability; deciding on them is **scrutiny**. A scrutiny
   officer holds both; a report user holds neither.
2. Computing a result is a **results** capability; publishing is its own (`state.fest:publish`),
   so a scrutineer can correct a ranking without releasing it.
3. Grade scales, point rules, and class categories sit behind **settings**, not `marks`, because
   they decide what a mark is worth — a deliberately higher trust than entering marks.

Also of note: a route was renamed from `state.fest.results.publish` to avoid a name collision with
the older `admin.state.fest.results.publish`, because the duplicate made `route:cache` refuse to
build. The comment in the route file records this. Anyone adding State routes must check for
duplicate route names before running `route:cache`.

## 5. Legacy State workspace (`/admin/state-workspace/*`)

| Route name | Method | Controller | Action |
|---|---|---|---|
| `state.fest.index` | GET | `StateAdmin\StateFestWorkspaceController` | index |
| `state.fest.store` | POST | `StateAdmin\StateFestWorkspaceController` | store |
| `state.fest.show` | GET | `StateAdmin\StateFestWorkspaceController` | show |
| `state.fest.assign-chest-numbers` | POST | `StateAdmin\StateFestWorkspaceController` | assignChestNumbers |
| `state.fest.attendance.index` | GET | `StateAdmin\StateAttendanceController` | index |
| `state.fest.attendance.store` | POST | `StateAdmin\StateAttendanceController` | store |
| `state.fest.judges.assign` | POST | `StateAdmin\StateFestWorkspaceController` | assignJudge |
| `state.fest.judges.unassign` | DELETE | `StateAdmin\StateFestWorkspaceController` | unassignJudge |
| `state.fest.marks.enter` | POST | `StateAdmin\StateFestWorkspaceController` | enterMark |
| `state.fest.publish-results` | POST | `StateAdmin\StateFestWorkspaceController` | publishResults |
| `state.reports.participation` | GET | `StateAdmin\StateParticipationReportController` | index |
| `state.reports.participation.export` | GET | `StateAdmin\StateParticipationReportController` | export |

All behind `RedirectStateWorkspaceToModule`.

## 6. Sahodaya-side State submission

| Route name | Method | Controller | Action |
|---|---|---|---|
| `fest-state-outbox.index` | GET | `Admin\FestStateOutboxController` | index |
| `fest-state-outbox.submit` | POST | `Admin\FestStateOutboxController` | submit |
| `fest-state-outbox.retry` | POST | `Admin\FestStateOutboxController` | retry |
| `submit-state-qualifiers` | POST | `SahodayaAdmin\StateQualifierSubmissionController` | store |

The route comment records that `submit-state-qualifiers` still works with zero certified
nominations — i.e. an empty list is a legitimate submission, not an error.

## 7. Judge portal

| Route | Method | Controller |
|---|---|---|
| `/portal/state-judge/...` | GET/POST | `Portal\StateFestJudgeController` |

The public/judge portal routes exist; exact paths are in the portal route group. Judge scoring is
via `StateJudgeScoreService::save()`, which requires a `StateJudgeAssignment` row for the
(user, item) pair and refuses a withdrawn/rejected registration.

## 8. Public State portal (Blade)

| Route | Page |
|---|---|
| `/state/{slug}` | `state/public/home.blade.php` |
| `/state/{slug}/results` | `state/public/results.blade.php` |
| `/state/{slug}/rankings` | `state/public/rankings.blade.php` |
| `/state/{slug}/schedule` | `state/public/schedule.blade.php` |
| `/state/{slug}/sahodayas` | `state/public/sahodayas.blade.php` |
| `/state/{slug}/verify` | `state/public/verify.blade.php` |

Plus `state/print/` (certificate, sheet, cards) and `state/reports/table.blade.php`.

## 9. Background jobs and schedule

| Command | Schedule | Notes |
|---|---|---|
| `fest:process-state-outbox` | Every 5 minutes, `withoutOverlapping()` | Drains the idempotent outbox to the State API |
| `state:migrate` | Manual / CI | Separate migration path for the `state_kalotsav` connection |

## 10. Registry summary

| Group | Route count (approx.) | Controllers |
|---|---|---|
| State program management | 20 | 4 |
| Legacy kalotsav dashboard | 5 | 1 |
| Qualifier intake | 8 | 1 |
| State fest module | 48 | 9 |
| Legacy state workspace | 11 | 4 |
| Sahodaya outbox | 4 | 2 |
| Public portal | 6 | 1 |
| **Total State-related** | **~100** | **~22** |
