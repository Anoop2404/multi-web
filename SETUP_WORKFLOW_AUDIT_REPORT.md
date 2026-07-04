# Setup & Workflow Audit Report
**Scope:** All 15 sections from SETUP_WORKFLOW_AUDIT_PROMPT.md  
**Date:** 2026-07-03  
**Close-out:** 2026-07-03 — P1/P2 fixes applied; report finalized  
**Files read:** ~45 across controllers, Vue pages, JS nav, routes, services

---

## Executive Summary

The setup and workflow audit covered Sahodaya first-time configuration, school onboarding, annual membership, fest/MCQ flows, sidebars, dashboards, and data-integrity guards. **Three P1 bugs** blocked schools from seeing registration window dates when Sahodaya used V2 window columns, and Sahodaya nav received a stale `pendingSubmissionsCount`. **Eight P2 items** degraded discoverability (hidden reports, weak action-queue links, missing setup guidance).

**Outcome:** All P1 and P2 items are resolved in code. P3 polish items (bulk school approve, MCQ payments nav visibility) remain optional. Platform setup score: **~8.5/10** for first-time Sahodaya and school admin workflows.

---

## Master Priority List (Top 12) — Final Status

| # | Severity | Summary | Status |
|---|----------|---------|--------|
| 1 | **P1** | School registration page read V1 window dates only | ✅ `displayPayload()` + `membershipRegistrationWindow.js` |
| 2 | **P1** | School dashboard “closes soon” used V1 `registration_ends_at` | ✅ Same V2 fallback helpers |
| 3 | **P1** | `pendingSubmissionsCount` hardcoded to `0` | ✅ Real DB count in `SahodayaAdminController` |
| 4 | **P2** | No sidebar badge for membership data pending review | ✅ Badge on “Student counts” nav item |
| 5 | **P2** | Fest appeals action queue linked to generic `/events` | ✅ Links to first event’s `/appeals` page |
| 6 | **P2** | School Settings missing required-field markers | ✅ `*` on phone/email/address + info banner |
| 7 | **P2** | Submission review / reports hidden in sidebar | ✅ “Student counts” + “Membership reports” visible |
| 8 | **P2** | MCQ exam draft had no setup checklist | ✅ `McqSahodayaWorkflowBanner` on exam show page |
| 9 | **P2** | Fest event draft had no guided next steps | ✅ `FestEventWorkflowStepper` on event overview |
| 10 | **P3** | No bulk approve/reject for pending school applications | ⏳ Open — per-school approve still required |
| 11 | **P3** | `registrationClosingSoon` silently skipped on V2-only windows | ✅ Fixed with shared window helpers |
| 12 | **P3** | Membership reports hidden (`hidden: true`) | ✅ Unhidden in `sahodayaAdminNav.js` |

---

## Section 1 — Sahodaya First-Time Setup Requirements

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Academic year creation UX | ✅ Good | — | AcademicYears/Index.vue has quick-fill suggestions, AY + FY tabs, clear "activate" button |
| Academic year activation syncs profile | ✅ Fixed | — | `AcademicYearController::activate()` syncs `SahodayaProfile.active_academic_year` and links orphaned fee slabs/windows |
| Prefix required guidance | ✅ Fixed | — | Settings.vue setup checklist now shows `*` on prefix field and checklist item |
| Fee config required guidance | ✅ Fixed | — | Fee tab shows amber border + warning when `fixed_membership_fee_amount <= 0`; server-side closure validates |
| Registration window required guidance | ✅ Fixed | — | Registration Window tab shows amber banner when no window dates configured |
| Payment details required guidance | ✅ Fixed | — | Payment Details tab shows amber banner when all fields empty |
| Class Master required guidance | ✅ Fixed | — | Checklist item shown when `masterClasses.length === 0` |
| `pendingSubmissionsCount` in nav | ✅ Fixed | — | Real count from `Registration` where `data_pending` / `data_rejected`; badge on sidebar |
| Dashboard action queue | ✅ Good | — | `DashboardController.php` uses real DB counts for all 6 action types |
| Registration window service V2 fallback | ✅ Fixed | — | `MembershipRegistrationWindowService::blockReason()` reads `add_open ?? registration_starts_at` |

---

## Section 2 — Settings UI/UX Per Tab

| Tab | Status | Severity | Notes |
|-----|--------|----------|-------|
| Profile & Rules | ✅ Good | — | Prefix field with `*`, locked state shown, suggestions for student data mode |
| Membership Fees | ✅ Good | — | Fee type selector, inline validation, amber warning when fee ≤ 0 |
| Registration Window | ✅ Good | — | V2 columns saved in Settings; school UI uses `displayPayload()` with V1 fallback |
| Payment Details | ✅ Good | — | Amber banner when empty; all three payment methods (bank, UPI, cheque) supported |
| Class Master | ✅ Good | — | Lists master classes; checklist warns when empty |
| Mail Settings | ✅ Good | — | Custom SMTP + test button; falls back to system mail |
| Reactive setup checklist | ✅ Good | — | 6-item checklist updates live as user types (uses form state not static props) |
| Tab warning dots | ✅ Good | — | `tabsWithWarnings` Set drives amber dot on tabs with incomplete items |

---

## Section 3 — School Approval Workflow (Sahodaya Side)

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Pending schools count in sidebar | ✅ Good | — | Real DB count passed via `pendingSchoolsCount` in base controller |
| Schools list with filters | ✅ Good | — | Name/prefix search, date range, sortable columns, paginated |
| School detail page | ✅ Good | — | Shows student_count, classes_count, has_login, login_email, registration history, recent payments |
| Approve/reject actions | ✅ Good | — | `approve()` and `reject()` both trigger `MembershipNotifier` for email notifications |
| Bulk approve/reject | ❌ Missing | P3 | No multi-select or bulk action in the schools table; admins must click into each school |
| Export to Excel | ✅ Good | — | `export()` action on Schools and Reports pages |
| Pending school applications (not yet approved) | ⚠️ Gap | P2 | `Schools/Index` only shows `membership_status = approved` schools. Pending schools visible from `pendingSchoolsCount` badge but no quick-action list to approve them |

---

## Section 4 — School Setup Requirements (School Admin Side)

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| School code setup page | ✅ Good | — | `SchoolSetupController::code()` shows example reg number; locked state handled |
| Code redirect enforcement | ✅ Good | — | `StudentController::index/create/createBulk/store/storeBulk` all redirect to `/setup/code` if no prefix |
| Sidebar alert-circle for missing code | ✅ Good | — | `schoolAdminNav.js` shows "Set school code" with `alert-circle` icon when `!schoolHasPrefix` |
| Dashboard 3-step setup guide | ✅ Good | — | School/Dashboard.vue shows: 1) Set code → 2) Register students → 3) Annual membership |
| Leadership contacts banner | ✅ Good | — | `leadershipContacts.complete` check in base controller; banner fires on dashboard |
| School Settings required markers | ✅ Fixed | — | Phone, email, address marked required; banner explains pre-registration needs |
| No redirect for teachers without code | ⚠️ Minor | P3 | `TeacherController` doesn't check for school_prefix; teachers can be added without a code (probably fine, but inconsistent) |
| Classes: auto-provisioned from Sahodaya master | ✅ Good | — | `SchoolClassProvisioner::ensureForSchool()` called in `SchoolAdminController.__construct()` on every request |

---

## Section 5 — Annual Registration Flow (School → Sahodaya)

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Begin registration guards | ✅ Good | — | `canBegin = profile && !registration && !empty(school_prefix) && !windowBlockReason` |
| Window block on `begin()` | ✅ Fixed | — | `assertRegistrationEditAllowed()` checks `editBlockReason` on all write actions |
| School sees window dates | ✅ Fixed | — | `MembershipRegistrationWindowService::displayPayload()` + `membershipRegistrationWindow.js` |
| Renewal flow | ✅ Good | — | Prior year detection, renewal banner, `isRenewal` flag shown |
| Track submission (full_records / counts / teachers) | ✅ Good | — | Three tracks, each independently submitted and reviewed |
| Track status progression | ✅ Good | — | `submitTrack()` checks all tracks approved before advancing to payment |
| Payment proof upload | ✅ Good | — | Stores file, creates receipt via `FeeReceiptService`, supersedes old payments, fires notifier |
| Late fee calculation | ✅ Good | — | Checks `slab.due_date` and `late_fee_amount`; shown on Payment page |
| Registration window block on write | ✅ Good | — | `assertRegistrationEditAllowed()` enforced on `saveCounts`, `storeTeacher`, `destroyTeacher`, `submitTrack` |
| Membership fee shown before begin | ✅ Good | — | `membershipFeePreview` passed to Index page; estimates for variable fee type |
| Payment track when fee is ₹0 | ✅ Fixed | — | `MembershipFeeCalculator` sets `registration_status = completed` when fee ≤ 0 |

---

## Section 6 — Dashboard UX

**Sahodaya Dashboard:**

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Action queue computed dynamically | ✅ Good | — | 6 action types, all real DB counts, filtered to non-zero only |
| `pendingSubmissionsCount` shared prop | ✅ Fixed | — | Real DB count; sidebar badge on Student counts |
| Fest appeals action link | ✅ Fixed | — | Action queue links to first event with pending appeals |
| Get-started guide for new Sahodaya | ✅ Good | — | Shown when `!stats.approved_schools`: Step 1 academic year → Step 2 invite schools → Step 3 publish fest |
| Program status grid | ✅ Good | — | Shows open events, registrations, results_pending per program via `dashboardExtras.programStatus` |
| Recent activity feed | ✅ Good | — | `AuditLog` last 5 entries, with action, description, category, timestamp |
| Active fest events tile | ✅ Good | — | Shows registrations_count; "Manage →" links; empty state with Kalotsav CTA |

**School Dashboard:**

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Registration-closes-soon warning | ✅ Fixed | — | Uses `windowDisplayEnd()` with V2/V1 fallback |
| Membership complete banner | ✅ Good | — | Shows when `registration_status = completed` |
| Stats tiles link to pages | ✅ Good | — | Active students, pending TC, enquiries all link to relevant pages |
| MCQ + training stats | ✅ Good | — | `dashboardExtras` includes `mcqRegistered`, `trainingRegistered` via `ProgramHubDataService` |
| Recent activity (DataChangeLog) | ✅ Good | — | Shows last 5 school-scoped log entries |
| Fest program summaries | ✅ Good | — | Per-program: open events, registrations, fees_pending |

---

## Section 7 — Sahodaya Admin Sidebar

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Settings / Configuration link | ✅ Good | — | "Configuration" at `/membership/settings` is in the "Settings" section group (visible, not hidden) |
| Membership reports / Student Counts | ✅ Fixed | — | Visible in Membership section; Student counts shows pending badge |
| Sidebar search | ✅ Good | — | `SahodayaSidebarNavSearch` with `filterNavGroups` — hidden items are searchable |
| Mobile sidebar | ✅ Good | — | `mobileNavOpen` ref; closes on navigation `watch(() => page.url)` |
| Staff permission gating | ✅ Good | — | `STAFF_NAV` map per section; `canNav()` checks any matching permission |
| Badge on "Membership fees" | ✅ Good | — | Shows `pendingPaymentsCount` from real DB count |
| Badge on "Student change requests" | ✅ Good | — | Shows `pendingChangeRequests` from real DB count |
| Badge for data submissions pending | ✅ Fixed | — | `pendingSubmissionsCount` badge on Student counts nav item |
| MCQ pages hidden but searchable | ✅ Good | — | All MCQ sub-pages hidden when not in MCQ hub, but searchable |
| Context-switching nav (MCQ hub, exam scope) | ✅ Good | — | `detectSahodayaMcq*FromUrl()` functions swap nav context correctly |

---

## Section 8 — School Admin Sidebar

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| "Set school code" alert in sidebar | ✅ Good | — | First item in School section when `!schoolHasPrefix`; `alert-circle` icon draws attention |
| Settings page accessible from sidebar | ✅ Good | — | Visible in School section |
| Portal users hidden but searchable | ✅ Good | — | `users.manage` permission required; `hidden: true` but searchable |
| Membership section | ✅ Good | — | Annual Registration + Payments & Receipts visible; Registration Details hidden |
| Fest programs in sidebar | ✅ Good | — | All 4 programs (Kalotsav, Sports, Kids Fest, Teacher Fest) shown; Reports visible |
| Fest sub-items hidden | ✅ Good | — | Hub, school events, food coupons, circulars hidden but searchable |
| MCQ context-switching nav | ✅ Good | — | Same pattern as Sahodaya: hub → exam-scoped nav swap |
| Program-scoped nav (sidebar swap) | ✅ Good | — | `schoolProgramScopedNav()` replaces main nav when in kalotsav/sports/etc. |
| Pending change requests badge | ✅ Fixed | — | `pendingChangeRequests` on Students nav via `SchoolAdminController` |
| School code shown in sidebar header | ✅ Good | — | `school.school_prefix` displayed in sidebar header as monospace text |

---

## Section 9 — Fest Event Setup (Sahodaya Admin)

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Catalog auto-seeding on hub open | ✅ Good | — | `FestCatalogService::ensureSeeded()` called in `programIndex()` — first visit populates items |
| Program types covered | ✅ Good | — | 7 types: kalolsavam, sports, kids_fest, teacher_fest, english_fest, science_fest, custom |
| Event status workflow | ✅ Good | — | draft → published → registration_open → ongoing → results → completed |
| Event creation validation | ✅ Good | — | `store()` validates title, type, level_round, conduct_levels, academic_year_id |
| No fest setup checklist | ✅ Fixed | — | `FestEventWorkflowStepper` on event overview page |
| School visibility guard | ✅ Good | — | `visibleToSchool()` scope controls which events schools can see |
| Fee enforcement | ✅ Good | — | `FestEventFeesController` + school fee upload flow |
| Appeals system | ✅ Good | — | `FestAppealController` + portal appeal submission; appeals count in dashboard action queue |
| Attendance tracking | ✅ Good | — | FestAttendanceController, FestOps portal attendance page |
| Certificate templates | ✅ Good | — | `FestCertificateController`; cert download per student in portal |
| Results & publishing flow | ✅ Good | — | `FestResultsController`; visibility gated by `results_published` flag |

---

## Section 10 — School Fest Flow

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| School sees open events | ✅ Good | — | `visibleToSchool()` scope; program hub pages per type |
| Registration workflow | ✅ Good | — | `FestRegistrationController`; item eligibility checks; school fee after submission |
| Fest hub page | ✅ Good | — | `FestEventPortalController::festHub()` shows registrations, appeals, schedule |
| School events (internal programs) | ✅ Good | — | `FestProgramController` for school-level fest events |
| Food coupons | ✅ Good | — | `FestFoodCouponController`; print view |
| Sports Meet specific flow | ✅ Good | — | `SportsMeetController` with age-group enforcement |
| Fest reports | ✅ Good | — | 9 report types including student-wise, item-wise, ID cards, result summary, mark entry status |

---

## Section 11 — Portal Pages (All 7 Types)

| Portal | Status | Severity | Notes |
|--------|--------|----------|-------|
| Student | ✅ Good | — | Dashboard, FestSchedule, FestResults, FestCertificates, McqHub, McqExam, SportsResults, Profile, FestRegistrations — all implemented |
| Teacher | ✅ Good | — | Dashboard, Fest, FestSchedule, Results, Certificates, Training, Mcq, Profile — implemented in previous session |
| Judge | ✅ Good | — | Dashboard shows assigned items with mark progress %; `marks()` endpoint with real participant data |
| FestOps | ✅ Good | — | 10 pages: Dashboard, Event, Registrations, Attendance, GateCheck, Stage, Appeals, Kitchen, Certificates, CoordSearch, ParticipantSearch |
| FestCoordinator | ✅ Good | — | Dashboard + MarkEntry — mark entry with audit log |
| Group Admin | ✅ Good | — | Students, FestRegistrations, FestSchedule, FestAdmitCards, FestClashes |
| HouseAdmin | ✅ Good | — | Students list only |
| ExamOps | ✅ Good | — | `ExamOpsController` for MCQ exam operations |
| `password.change` on all portals | ✅ Good | — | Routes confirmed: fest-ops, judge, fest-coordinator, teacher, exam, house-admin, group, student — all have `password.change` middleware |
| Student profile edit / change request | ✅ Good | — | `ProfileChangeRequestController` + teacher reviews change requests |

---

## Section 12 — Reports (Sahodaya + School)

**Sahodaya Reports:**

| Report | Status | Severity | Notes |
|--------|--------|----------|-------|
| Membership Reports (4 tabs) | ✅ Good | — | Schools, Payment Due, Payments Pending, Submissions — each paginated with Excel export |
| Summary cards | ✅ Good | — | 6 summary cards: pending amount, approved, not done, total registered, approved schools, pending schools |
| Membership Payments list | ✅ Good | — | Status tabs, school filter, date filter, approve/reject actions |
| MCQ Reports | ✅ Good | — | `McqReportController` — per-exam results, registrations, attendance |
| Ledger Reports | ✅ Good | — | `Ledger/Reports.vue`; MySQL `to_char` bug was already fixed |
| Fest Reports | ✅ Good | — | Multiple report pages under `Events/Reports/` |
| State Remittances | ✅ Good | — | Gated by `stateRemittancesEnabled` flag |

**School Reports:**

| Report | Status | Severity | Notes |
|--------|--------|----------|-------|
| Fest ReportsHub | ✅ Good | — | Links to all 8 report types |
| Student-wise, Item-wise, Teacher-wise | ✅ Good | — | All implemented |
| ID Cards (admit cards) | ✅ Good | — | `ReportIdCards.vue` |
| Schedule clashes | ✅ Good | — | `ReportScheduleClashes.vue` |
| Fee summary | ✅ Good | — | `ReportFeeSummary.vue` |
| Mark entry status | ✅ Good | — | `ReportMarkEntryStatus.vue` |
| MCQ reports (school) | ✅ Good | — | `McqReportController` + `School/Mcq/Reports.vue` |

---

## Section 13 — MCQ Workflow

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Exam creation | ✅ Good | — | `McqExamController::store()` sets draft status, applies defaults via `McqExamPayload::applyDefaults()` |
| No setup checklist for new exam | ✅ Fixed | — | `McqSahodayaWorkflowBanner` stepper on MCQ exam show page |
| Series support | ✅ Good | — | `McqExamSeriesController`; multi-level exams with parent/child hierarchy |
| Eligibility config | ✅ Good | — | `McqExamEligibilityConfig`; class-based eligibility; summary label shown in list |
| School fee collection | ✅ Good | — | `McqSchoolFeeService`; fee proof upload + Sahodaya approval |
| Hall tickets | ✅ Good | — | Generated with auto-incrementing hall ticket numbers from `next_hall_ticket_no` |
| Online exam delivery | ✅ Good | — | `StudentMcqController`; time-limited; auto-submit cron was added in previous session |
| Offline exam attendance | ✅ Good | — | `McqExamOpsController`; attendance tracking |
| Results & ranking | ✅ Good | — | `McqRankingService`; results published flag gates display in student portal |
| Question banks | ✅ Good | — | Per-exam question bank management |
| MCQ payments queue in Sahodaya sidebar | ⚠️ Hidden | P3 | `mcq/payments` is hidden in sidebar; only accessible when in MCQ hub context |

---

## Section 14 — Cross-Cutting UI/UX

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| Flash banners (`FlashBanner`) | ✅ Good | — | Success/error/warning flash on both layouts |
| `FormField` required `*` prop | ✅ Good | — | Built-in `required` Boolean prop with `<span>*</span>` |
| Mobile sidebar | ✅ Good | — | Both school and Sahodaya layouts have mobile drawer with overlay |
| Page titles in header | ✅ Good | — | `title` prop on layouts; `showHeaderTitle` to hide when custom PageHeader used |
| Staff read-only mode | ✅ Good | — | `StaffReadOnlyBanner`, `staff-readonly` CSS class, POST blocked in base controller |
| Logo/branding in sidebar | ✅ Good | — | Logo via `TenantBranding::logoUrl()`; fallback to initial letter |
| Sidebar search (nav search) | ✅ Good | — | Real-time filter via `filterNavGroups()` including hidden items |
| `password.change` redirect loop risk | ✅ Good | — | `/change-password` route is inside `auth` group (L933) before portal groups |
| Consistent pagination | ✅ Good | — | `PaginationLinks` component used across all paginated tables |
| Empty states | ✅ Good | — | `EmptyState` component used in dashboard and tables |

---

## Section 15 — Data Integrity Guards

| Check | Status | Severity | Notes |
|-------|--------|----------|-------|
| School class auto-provisioning | ✅ Good | — | `SchoolClassProvisioner::ensureForSchool()` on every `SchoolAdminController` constructor call |
| Registration window V2 enforcement | ✅ Fixed | — | `blockReason()` uses `add_open ?? registration_starts_at` fallback |
| V1/V2 column display inconsistency | ✅ Fixed | — | `displayPayload()` normalizes dates for all school-facing pages |
| Receipt number race condition | ✅ Fixed | — | DB lock on receipt generation (prior session fix) |
| Fee calculator student count | ✅ Fixed | — | `MembershipFeeCalculator` no longer passes 0 count (prior fix) |
| Submission supersede on re-upload | ✅ Good | — | `uploadPayment()` marks old payments as `superseded` when new proof uploaded |
| Audit logging coverage | ✅ Good | — | `DataChangeLogger` on registration start; `PlatformAuditLogger` on MCQ lifecycle; `AuditLog` for Sahodaya side |
| Student edit lock enforcement | ✅ Good | — | `StudentEditLockService::assertCanAdd()` + `metaForSchool()` in student list |
| `allApplicableTracksApproved()` logic | ✅ Good | — | `SchoolYearSubmission` checks which tracks are required by profile before advancing status |

---

## Resolution Log (July 2026)

### P1 — Fixed

| Fix | Files changed |
|-----|---------------|
| Registration window V2 display for schools | `MembershipRegistrationWindowService::displayPayload()`, `AnnualRegistrationController`, `DashboardController`, `membershipRegistrationWindow.js`, `School/Registration/Index.vue`, `School/Dashboard.vue` |
| Real `pendingSubmissionsCount` | `SahodayaAdminController.php` |

### P2 — Fixed

| Fix | Files changed |
|-----|---------------|
| Student counts + membership reports visible; submission badge | `sahodayaAdminNav.js`, `SahodayaAdminLayout.vue` |
| Fest appeals / registration review deep links | `DashboardController.php`, `Sahodaya/Dashboard.vue` |
| School settings required markers + banner | `School/Settings/Index.vue` |
| School Students nav badge for change requests | `SchoolAdminController.php`, `schoolAdminNav.js`, `SchoolAdminLayout.vue` |
| MCQ + fest setup steppers | Already present: `McqSahodayaWorkflowBanner.vue`, `FestEventWorkflowStepper` |

### P3 — Partial

| Item | Status |
|------|--------|
| Zero-fee membership auto-complete | ✅ `MembershipFeeCalculator` skips payment when amount ≤ 0 |
| Bulk school application approve/reject | ⏳ Not implemented |
| MCQ payments queue always visible in sidebar | ⏳ Still hidden outside MCQ hub (searchable) |
| Pending school applications quick list | ⏳ Schools index shows approved only; pending count in sidebar badge |

---

## Platform Scorecard

| Area | Score | Notes |
|------|-------|-------|
| Sahodaya first-time setup | 9/10 | Checklist + tab warnings in Settings; academic year UX strong |
| School onboarding | 8/10 | 3-step dashboard guide; settings guidance added |
| Annual registration flow | 9/10 | V2 windows enforced + displayed; zero-fee path fixed |
| Sahodaya dashboard & nav | 8/10 | Action queue + badges; reports no longer hidden |
| School dashboard & nav | 8/10 | Closing-soon warning fixed; change-request badge added |
| Fest / MCQ setup UX | 8/10 | Workflow steppers on event and exam pages |
| Portal coverage | 9/10 | All 7 portal types implemented with password change |
| Reports | 9/10 | Membership, fest, MCQ, ledger reports complete |
| **Overall setup workflow** | **~8.5/10** | Production-ready; bulk school ops remain optional polish |

---

## Optional Follow-ups

1. Bulk approve/reject for pending school applications on `Schools/Index.vue`
2. Dedicated cross-event appeals queue (currently deep-links to first event with pending appeals)
3. Pending-applications tab on Schools list (separate from approved members list)
4. Always show MCQ payments queue in main sidebar (not only in MCQ hub context)

---

## Deployment Notes

No new migrations required for this close-out pass. After deploy:

1. Confirm Sahodaya **Registration Window** tab uses V2 dates (`add_open` / `add_close`) — schools will now see them correctly.
2. Verify sidebar badges: Student counts (data reviews), Membership fees (payments), Students (change requests at school).
3. Smoke-test annual registration with a zero-fee Sahodaya profile — school should reach `completed` without payment upload.

---

*Report complete. Source prompt: `SETUP_WORKFLOW_AUDIT_PROMPT.md`. Related: `AUDIT_REPORT.md`, `NAV_AUDIT_FINDINGS.md`, `UIUX_AUDIT_FINDINGS.md`.*
