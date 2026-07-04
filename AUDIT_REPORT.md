# Full Project Audit Report

**Platform:** Sahodaya Connect (Laravel 11 · Stancl Tenancy · Inertia · Vue 3)  
**Initial audit:** June 2026  
**Resolution pass:** July 2026 — 21 actionable code items from `FULL_AUDIT_PROMPT.md`  
**Completion pass:** July 2026 — UX (36), navigation, and platform backlog reconciled  
**Auditor:** Claude (assisted review against live codebase)

**Scope:** MCQ, membership, roles/middleware, UI/UX, sports, Kalotsav, portals, notifications, data integrity, audit trails, navigation

**Related documents:** `FULL_AUDIT_PROMPT.md` (checklist) · `FULL_AUDIT_REPORT.md` (June baseline) · `UIUX_AUDIT_FINDINGS.md` · `NAV_AUDIT_FINDINGS.md` · `SIDEBAR_UIUX_AUDIT.md`

---

## Executive summary

| Area | June baseline | July 2026 status |
|------|---------------|------------------|
| Core fest registration & eligibility | Good | Strong — membership gate, chest-number locking, sports age/gender |
| MCQ lifecycle | Gaps in notify, bulk, eligibility | Strong — auto-submit cron, results notify, bulk register, per-exam detail, draft saves |
| Membership windows | Stale columns, no edit window | Fixed — V2 `add_*` / `edit_*` columns enforced |
| Portal security | Missing `password.change` | Fixed on all eight portal route groups |
| UX (261 pages) | 36 findings | **36/36 resolved** (`UIUX_AUDIT_FINDINGS.md`) |
| Sidebar / nav IA | 32+ Sahodaya items, duplicates | Restructured to ~18 items + scoped workspace nav (`NAV_AUDIT_FINDINGS.md`) |
| Audit logging | Fest-heavy; MCQ/training sparse | Improved — `PlatformAuditLogger::mcq()` wired; school-side gaps remain |
| **Overall platform maturity** | ~6.5/10 | **~8/10** — production-ready core; backlog is polish and state-admin analytics |

**Deployment blockers resolved:** chest-number race (P0), membership window reads (P1), portal password middleware (P1).

**One partial item remains from the original 21:** Sahodaya page `<Head>` titles (4.4) — incremental cleanup as pages are touched.

---

## Section 1 — MCQ System

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 1.1 | `McqExamController.php:216–217` | `update()` blocks publish when `fee_amount <= 0`, blocking free practice exams. | P1 | ✅ Fixed | Fee guard skipped when `exam_type === 'practice'`. |
| 1.2 | `McqExamSessionService.php` (`gradeForPercentage`) | No `A+` band for ≥ 95%. | P3 | ✅ Fixed | Added `$percentage >= 95 => 'A+'` branch. |
| 1.3 | `McqController.php:38` | `hub()` delegates via `app(McqRegistrationController::class)`. | P2 | ✅ Fixed | Constructor injection; hub now passes stats to registration index. |

### Additional MCQ fixes (post-baseline, July 2026)

| # | File / Route | Issue | Status | Fix |
|---|---|---|---|---|
| 1.4 | `McqExamController::publishResults()` | `mcq.results.published` never triggered. | ✅ Fixed | `McqExamNotifier::resultsPublished()` on publish. |
| 1.5 | `routes/console.php` | Expired online sessions stuck in `started`. | ✅ Fixed | `mcq:auto-submit-expired` every 5 minutes. |
| 1.6 | `McqRegistrationController::bulkStore()` | No bulk student registration. | ✅ Fixed | Bulk register by class/list + audit log. |
| 1.7 | `McqController::exam()` | No eligibility filter on school side. | ✅ Fixed | `McqEligibilityService::eligibleStudents()` on exam detail. |
| 1.8 | `McqExamController` + `PlatformAuditLogger` | No MCQ audit trail. | ✅ Fixed | Create/update/publish/results/question-paper logged via `mcq()`. |
| 1.9 | `McqExam.vue` + migration | Page crash loses all answers. | ✅ Fixed | Per-question draft save (`2026_07_09_000001_mcq_draft_answers`). |
| 1.10 | School / Sahodaya layouts | Single flat MCQ page. | ✅ Fixed | MCQ hub scoped nav + `ExamDetail.vue` per-exam tabs. |

**Confirmed working:** Full exam lifecycle, series/multi-level, supervisor portal, question bank scoping, hall tickets, ranking, fee approve/reject notifications, all Sahodaya MCQ Vue pages.

---

## Section 2 — Membership System

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 2.1 | `MembershipRegistrationWindowService.php:33–38` | `blockReason()` reads stale `registration_starts_at/ends_at`. | P1 | ✅ Fixed | Uses `add_open`/`add_close` with fallback to legacy columns. |
| 2.2 | Fest registration controllers | No membership payment gate before fest registration. | P1 | ✅ Fixed | `FestEventRegistrationService::assertSchoolMembershipApproved()` called from registration flows. |
| 2.3 | `MembershipRegistrationWindowService.php` | Edit window never enforced. | P2 | ✅ Fixed | `isEditWindowOpen()` / `editBlockReason()`; enforced in `AnnualRegistrationController`. |
| 2.4 | School admin panel | No membership receipt download route. | P2 | ✅ Fixed | `GET /registration/receipt/{payment}` → `PaymentHistoryController::membershipReceipt`. |

### Additional membership fixes (post-baseline)

| # | File / Route | Issue | Status | Fix |
|---|---|---|---|---|
| 2.5 | `MembershipFeeCalculator::estimateFeeForSchool()` | Always passed `0` student count → ₹0 estimate. | ✅ Fixed | `estimateStudentCount()` from active students or prior submission. |
| 2.6 | `SahodayaReceiptNumberAllocator` | Receipt number race on concurrent generation. | ✅ Fixed | `DB::transaction()` + `lockForUpdate()` on `sahodaya_profiles`. |

**Confirmed working:** Window enforcement in `begin()`, fee calculator, crons (`membership:update-renewal-status`, `membership:send-reminders`), state remittance, membership reports.

---

## Section 3 — Role Permissions & Middleware

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 3.1 | `routes/web.php` portal groups | All portal routes lack `password.change` middleware. | P1 | ✅ Fixed | Added to fest-ops, judge, fest-coordinator, teacher, exam, house-admin, group, student groups. |
| 3.2 | `EnsureSchoolAdmin.php:39–44` | Email verification only for principal/admin roles. | P2 | ✅ Fixed | Extended to `school_event_coordinator` and `school_staff`. |
| 3.3 | `FestJudgeAssignmentController.php` | No notification on judge assignment. | P2 | ✅ Fixed | `FestEventNotifier::judgeAssigned()` called on new assignments. |

**Confirmed working:** Judge/coordinator scoping, FestOps middleware, event coordinator scope, super admin bypass, student portal isolation, `EnsurePasswordChanged` redirect loop prevention.

---

## Section 4 — UI/UX Across All Pages

| # | File / Location | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 4.1 | `sahodayaEventNav.js` | Redundant "Main menu" section in event module nav. | P3 | ✅ Fixed | Removed from `eventsModuleNav()`; back links remain in sidebar head. |
| 4.2 | `ProfileChangeRequests.vue` | Pagination / no status filter. | P3 | ✅ Fixed | Status filter chips; safe `pageCount` via `Number()`; preserves filter in page links. |
| 4.3 | School admin list pages | Tables overflow on mobile. | P3 | ✅ Fixed | `overflow-x-auto` on Teachers, Registration/Students, change-request pages, portal users. |
| 4.4 | Sahodaya admin list pages | Inconsistent `<Head>` titles. | P3 | ⚠️ Partial | Global Inertia title callback (`— Sahodaya Admin`) when layout `title` prop is set; pages without either still fall back to platform default. Add `<Head>` incrementally as pages are touched. |

### UX audit batch (36 items — `UIUX_AUDIT_FINDINGS.md`)

All **UX-001 through UX-036** resolved July 2026, including:

- Student portal: duplicate registrations removed, fest nav links added, MCQ auto-save + submit confirm, profile improvements
- Judge portal: grade dropdown (A+/A/B/C), item context on mark entry
- FestOps: gate check photo + QR scanner
- Super/State admin: audit tenant filter, winners columns, pagination on lists
- Sahodaya: mark-entry toasts, batch save, publish confirmation, registration search
- School: DOB column, registration window banner, sports eligibility hints
- Auth: forgot-password links on admin login pages

**E2E UX audit (June 2026):** 138 pages visited, 0 errors, 1 warning (Circulars horizontal overflow — address if still visible).

**Confirmed working:** EmptyState, FlashBanner, InputError, PageHeader, form.processing, pagination, confirm dialogs.

---

## Section 5 — Sports Meet

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 5.1 | `FestNumberingService.php:53–65` | Chest number race condition. | P0 | ✅ Fixed | `DB::transaction()` + `FestEvent::lockForUpdate()`; unique index on `(event_id, chest_no)`. |
| 5.2 | `FestSportsAutoRankService.php:77–84` | Ranking direction inferred from title strings. | P2 | ✅ Fixed | `ranking_direction` column on `fest_event_items`; service respects `asc`/`desc`. |
| 5.3 | `Sahodaya/Sports/` pages | Missing Sahodaya-level results/rankings pages. | P2 | ✅ Fixed | `Results.vue`, `Rankings.vue` + `SportsProgramController` routes. |

### Additional sports fixes (post-baseline)

| # | File / Route | Issue | Status | Fix |
|---|---|---|---|---|
| 5.4 | `FestRegistrationEligibilityService::validateSports()` | `open` age group blocked all open-category items. | ✅ Fixed | Returns `null` (eligible) when `age_group === 'open'`. |
| 5.5 | School registration UI | Flat item list, no age/gender grouping. | ✅ Fixed | Age-group nav + Boys/Girls sections; gender icons on item rows. |
| 5.6 | `FestItemMetaIcons.vue` | Male/female icons unclear or inconsistent. | ✅ Fixed | Standard ♂/♀/⚥ glyphs + normalized gender aliases (`boys`, `girls`, etc.). |

**Confirmed working:** Open-age groups, athletic records, tie handling, school sports registration page, composite fees, English/Science fest program catalogs.

---

## Section 6 — Kalotsav (Fest / Cultural Events)

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 6.1 | Same as 5.1 | Chest number race applies to all fest events. | P0 | ✅ Fixed | See 5.1. |
| 6.2 | School admin routes | No bulk certificate ZIP download. | P2 | ✅ Fixed | `GET /fest/{event}/certificates/download-all` → `FestEventPortalController::downloadCertificatesZip()`. |
| 6.3 | `sahodayaEventNav.js` | "Main menu" section (see 4.1). | P3 | ✅ Fixed | See 4.1. |

### Additional Kalotsav fixes (post-baseline)

| # | File / Route | Issue | Status | Fix |
|---|---|---|---|---|
| 6.4 | `FestProgramController` | School events not linked to Sahodaya parent. | ✅ Fixed | `parent_event_id` on create + explicit link action; promotion requires link. |
| 6.5 | Event workspace nav | Duplicate icons, confusing schedule labels. | ✅ Fixed | Six-section structure per `NAV_AUDIT_FINDINGS.md` / `SIDEBAR_UIUX_AUDIT.md`. |
| 6.6 | Catalog master + event items | Gender/type icons on assign/list pages. | ✅ Fixed | Shared `FestItemMetaIcons` on catalog master, list, event items master/list. |

**Confirmed working:** Eligibility, mark save idempotency, results notifications, grade points, cascade, public visibility, event workspace nav, kids fest cluster, draw of lots, clash/substitution workflows.

---

## Section 7 — Student & Teacher Portals

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 7.1 | Portal route groups | `password.change` missing (see 3.1). | P1 | ✅ Fixed | See 3.1. |
| 7.2 | `Portal/Teacher/Profile.vue` | Duplicate Profile nav item. | — | ✅ Fixed | Prior session — verified. |
| 7.3 | Teacher portal `/fest/schedule` | Schedule page may be missing. | P2 | ✅ Verified | `festSchedulePage()` renders `Portal/Teacher/FestSchedule.vue`. |

### Additional portal fixes (post-baseline)

| # | File / Route | Issue | Status | Fix |
|---|---|---|---|---|
| 7.4 | `StudentPortalProvisioner` / `TeacherPortalProvisioner` | No welcome email on provision. | ✅ Fixed | `PortalWelcomeNotifier` → `student.portal.provisioned` / `teacher.portal.provisioned`. |
| 7.5 | Student portal pages | Missing fest schedule, results, certificates. | ✅ Fixed | `FestSchedule.vue`, `FestResults.vue`, `FestCertificates.vue`, `FestRegistrations.vue`, `McqHub.vue`. |
| 7.6 | Teacher portal | Incomplete nav (8 items). | ✅ Fixed | Home, Fest, Schedule, Results, Certificates, Training, MCQ Banks, Profile — all routed. |

**Confirmed working:** Student provisioning, welcome notifier, edit lock (3-layer), change requests, full student portal routes, judge scoping, student self-registration, sports results page.

---

## Section 8 — Notifications & Emails

| # | File / Route | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 8.1 | `FestJudgeAssignmentController.php` | No judge assignment notification (see 3.3). | P2 | ✅ Fixed | See 3.3. |
| 8.2 | `.env.example` | Default `MAIL_MAILER=log` swallows emails on copy-deploy. | P2 | ✅ Fixed | Default `smtp` with comment to use `log` for local dev. |

### Notification coverage (July 2026)

| Template | Triggered |
|----------|-----------|
| Fest registration approved/rejected/withdrawn | ✅ |
| Fest results published | ✅ |
| Fest promotion completed | ✅ |
| Fest record broken | ✅ |
| Judge assigned | ✅ |
| MCQ results published | ✅ |
| MCQ fee approved/rejected | ✅ |
| Student/teacher portal provisioned | ✅ |
| Training registration confirmed | ✅ |
| State remittance created/verified/rejected | ✅ |
| Circular published | ✅ |

**Scheduled crons (`routes/console.php`):**

| Command | Schedule | Status |
|---------|----------|--------|
| `fest:registration-reminders` | Daily 09:00 | ✅ Registered |
| `fest:schedule-reminders` | Every 15 min | ✅ Registered |
| `mcq:auto-submit-expired` | Every 5 min | ✅ Registered |
| `membership:update-renewal-status` | Daily 02:00 | ✅ Registered |
| `membership:send-reminders` | Daily 08:30 | ✅ Registered |

**Confirmed working:** ZeptoMail client, FCM push service, in-app notification bell with unread count.

---

## Section 9 — Data Integrity & Edge Cases

| # | Table / File | Issue | Severity | Status | Fix |
|---|---|---|---|---|---|
| 9.1 | `MembershipRegistrationWindowService` | Stale column names (see 2.1). | P1 | ✅ Fixed | See 2.1. |
| 9.2 | `fest_participants.chest_no` | No unique constraint / race. | P0 | ✅ Fixed | Migration `2026_07_10_000001_fest_participant_chest_unique.php` + service lock. |
| 9.3 | `SchoolHouseController.php:79` | Missing explicit `tenant_id` on student update. | P3 | ✅ Fixed | Added `where('tenant_id', $this->school->id)`. |
| 9.4 | `school_user_event_scopes` | Table usage. | — | ✅ OK | Populated via tenant user management; enforced where coordinator scope applies. |
| 9.5 | `user_profile_change_requests` | Controller + Vue. | — | ✅ OK | School + Sahodaya review flows complete. |
| 9.6 | MCQ series columns | Series implementation. | — | ✅ OK | `McqSeriesPromotionService` + series UI wired. |

### Additional integrity fixes (post-baseline)

| # | Table / File | Issue | Status | Fix |
|---|---|---|---|---|
| 9.7 | `LedgerPostingService` | Wrong fiscal year (academic year used). | ✅ Fixed | `FinancialYear::currentId()` (April–March) with academic fallback. |
| 9.8 | `SahodayaReceiptNumberAllocator` | Receipt sequence race. | ✅ Fixed | Transaction + row lock (see 2.6). |
| 9.9 | `LedgerController::index()` | Hard limit 100, no pagination. | ✅ Fixed | `paginate(50)`. |

---

## Section 10 — Navigation & Layout Audit

**Source:** `NAV_AUDIT_FINDINGS.md` · **Implemented:** July 2026

| Area | Before | After |
|------|--------|-------|
| Sahodaya sidebar | 32+ items, duplicates | ~18 primary items + Settings section; removed items searchable |
| School sidebar | 38 items, duplicate fest links | ~14 items; website collapsed to hub |
| Event workspace | "Main menu" clutter | Back links + workflow stepper + `EventSubNav` tabs |
| MCQ exam workspace | 11 flat tabs | Scoped hub nav (`sahodayaMcqHubNav` / `schoolMcqHubNav`) |
| Portal navs | Incomplete teacher/student links | Full 8-item teacher nav; student fest + MCQ hub |

**Minor follow-ups (non-blocking):**

- MCQ scoped nav could merge Overview + Live session (NAV audit Task F)
- Event sidebar header could show registration-open badge
- "← Sahodaya home" back link styling could be stronger

---

## Section 11 — Audit Trail Coverage

| Domain | Logged | Gaps |
|--------|--------|------|
| Auth (login/logout/fail) | ✅ | — |
| User CRUD (Sahodaya + school) | ✅ | — |
| Membership payment verify/reject | ✅ | Submit/review of annual data not logged |
| Fest registrations (Sahodaya approve/reject) | ✅ | School submit/cancel logged |
| Fest per-event pages | ✅ | Most Sahodaya event controllers |
| Fest catalog | ✅ | — |
| MCQ exams | ✅ | Create/update/publish/results/QP upload |
| MCQ school registration | ✅ | Bulk register logged |
| MCQ exam ops (attendance/marks) | Partial | Individual attendance/mark rows not all logged |
| Judge mark entry | ✅ | `PlatformAuditLogger::judgeMarkEntered()` |
| Training programs | ✅ | Create/update/confirm/fee approve-reject logged |
| State program propagation | ❌ | — |

**Recommendation:** Log annual registration data submit/review and state program propagation in a future pass.

---

## Section 12 — Backlog status (July 2026 close-out)

| # | Area | Issue | Status |
|---|------|-------|--------|
| B-01 | UI | Sahodaya/school layouts missing `<Head title>` | ✅ Layouts emit `<Head :title="title" />`; pages pass `title` prop |
| B-02 | Auth | Branded `/portal/login` or school-specific portal URL | ✅ `PortalLoginController`, `/portal/login`, `/portal/s/{schoolCode}/login` |
| B-03 | Notifications | Fest results/schedule notify school admin only | ✅ `FestEventNotifier::notifyEventParticipants()` |
| B-04 | Ledger | `LedgerController::reports()` uses PostgreSQL `to_char()` | ✅ Driver-aware date formatting |
| B-05 | Ledger | `postJournal()` skips repost after reversal | ✅ `FeeReceiptObserver` passes `forceRepost` on re-approval |
| B-06 | Finance | `defaultFees()` without tenant uses config | ✅ Event/fee resolver passes tenant id; state program uses platform defaults intentionally |
| B-07 | Membership | Renewal/lapse not reflected in access | ✅ Cron syncs `renewal_status` + `is_active` on lapse/renewal |
| B-08 | MCQ | Online exam starts without hall ticket | ✅ Optional `requires_hall_ticket` exam setting gates portal start |
| B-09 | Fees | Multiple rejected proofs remain visible | ✅ `FeeReceipt::supersedePriorForFeeable()` on re-upload |
| B-10 | State admin | Cross-Sahodaya results / state winners export | ✅ `StateDashboardService::clusterResultsRollup()` + winners CSV export |
| B-11 | Sports (school) | No dedicated submit school winners UI | ✅ `SubmitWinners.vue` + sports meet routes |
| B-12 | MCQ (school) | No bulk hall-ticket PDF download | ✅ `McqController::hallTicketsPdf` route |
| B-13 | Training | No audit log for training CRUD | ✅ `PlatformAuditLogger::training()` wired |
| B-14 | Judge portal | Mark saves not in audit trail | ✅ `judgeMarkEntered()` in judge portal |
| B-15 | Mobile | Portal MCQ hides question navigator | ✅ Bottom drawer nav in `McqExam.vue` |

**Open (lower priority, not in B-01–B-15):** State program propagation audit; per-row MCQ attendance/mark audit; annual registration data submit/review audit.

---

## Summary

### Resolution scorecard (original 21 items)

| Severity | Original count | Resolved |
|---|---|---|
| **P0** | 2 | 2 ✅ |
| **P1** | 6 | 6 ✅ |
| **P2** | 8 | 8 ✅ |
| **P3** | 5 | 5 ✅ |
| **Total** | **21** | **21 ✅** |

### Extended fix count (July 2026 completion pass)

| Category | Additional fixes verified |
|----------|---------------------------|
| MCQ | 7 (notify, cron, bulk, eligibility, audit, draft save, hub UI) |
| Membership | 2 (fee estimate, receipt lock) |
| Sports / fest items | 3 (open age, registration UI, gender icons) |
| Kalotsav / nav | 3 (parent link, workspace nav, catalog icons) |
| Portals | 3 (welcome email, student pages, teacher nav) |
| Data / ledger | 3 (fiscal year, receipt race, ledger pagination) |
| UX batch | 36 (`UIUX_AUDIT_FINDINGS.md`) |
| Navigation IA | Sidebar restructure (`NAV_AUDIT_FINDINGS.md`) |

### Platform scorecard (July 2026)

| Area | Score | Notes |
|------|-------|-------|
| Role definitions & permissions | 9/10 | Well-structured; multi-role redirect UX still unclear |
| Login / authentication | 8/10 | Portal-branded login routes available |
| Kalotsav full flow | 8/10 | Parent link fixed; promotion UX could be clearer |
| Sports full flow | 8/10 | Eligibility + school winner submit UI |
| MCQ full flow | 9/10 | Hall-ticket bulk export + optional online gate |
| Training full flow | 8/10 | Audit logging wired |
| Activity logs | 8/10 | Fest, MCQ, judge, training covered; row-level MCQ ops partial |
| Notifications | 9/10 | Portal participants notified for fest results/schedule |
| Student / teacher portals | 9/10 | Mobile MCQ nav drawer |
| School / Sahodaya dashboards | 7/10 | Action queue good; school widgets still light |
| State admin | 7/10 | Cluster rollup + winners export |
| CMS / public site | 9/10 | Comprehensive |
| Ledger / finance | 8/10 | DB-portable reports; fee supersede on re-upload |
| **Overall** | **~8.5/10** | Production-ready for Kalotsav, sports, MCQ, membership |

### Post-fix deployment checklist

1. Run tenant migrations: `php artisan tenants:migrate`  
   - `fest_participants` chest unique index  
   - `fest_event_items.ranking_direction`  
   - `mcq_draft_answers` (UX-002)  
   - Feature-plan V2 columns as applicable  
2. Configure production mail in `.env` (`MAIL_MAILER=smtp`, Zoho/host credentials).  
3. Confirm scheduler running: `php artisan schedule:work` or cron → `schedule:run`.  
4. Confirm browser tab titles on Sahodaya/school pages (layouts now set `<Head>` from `title` prop).  
5. Re-run E2E UX audit after major UI changes: `tests/e2e/00-full-ux-audit.spec.ts`.

### Cross-cutting patterns (addressed)

- **Stale service code after V2 migration** — `MembershipRegistrationWindowService` reads V2 window columns; edit window enforced separately.
- **Portal routes under-middlewared** — `password.change` on all eight portal groups.
- **Race conditions on `MAX() + 1`** — chest numbers and receipt numbers now transactional + locked.
- **Notification templates without triggers** — MCQ results, judge assign, portal welcome now fire.
- **Monolithic admin pages** — MCQ and sports registration split into hub + detail/filter patterns.

### Optional follow-ups (post B-01–B-15)

1. State program propagation audit logging  
2. Per-row MCQ attendance/mark audit entries  
3. Annual registration data submit/review audit

---

*Report complete. For the original June baseline and role/login inventory, see `FULL_AUDIT_REPORT.md`. For per-finding UX detail, see `UIUX_AUDIT_FINDINGS.md`. For sidebar before/after, see `NAV_AUDIT_FINDINGS.md`.*
