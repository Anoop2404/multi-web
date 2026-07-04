# Full Setup & Workflow Audit Prompt
**Purpose:** Paste this into a fresh session to audit every required setting, config, UI page, sidebar, report, and workflow for Sahodaya admins and School admins. The goal is to find anything that blocks proper operation, is confusing to a first-time user, or is missing/broken.

---

## Context (read first)

Laravel 11 multi-tenant SaaS. Hierarchy: **Platform → Sahodaya → Schools → Portals**.

- Route prefix: `SahodayaAdmin` → `SchoolAdmin` → `Portal/*`
- Vue 3 + Inertia.js. Page resolution in `admin.js`: controller returns `'School/Dashboard'` → resolves to `Pages/Admin/School/Dashboard.vue`
- Roles: Super Admin, State Admin, Sahodaya Admin, School Admin/Principal/VP/Event Coordinator, Student Portal, Teacher Portal, Judge Portal, FestOps, Mark Coordinator, Group Admin, House Admin, Exam Supervisor, Public Portal
- Key models: `Tenant` (type: sahodaya|school), `SahodayaProfile`, `SahodayaRegistrationWindow`, `MembershipFeeSlab`, `MasterClass`, `FestEvent`, `FestEventItem`, `FestParticipant`, `McqExam`, `McqExamSeries`

---

## SECTION 1 — Sahodaya First-Time Setup Requirements

**Read these files:**
```
app/Http/Controllers/SahodayaAdmin/MembershipSettingsController.php
app/Http/Controllers/SahodayaAdmin/DashboardController.php
app/Http/Controllers/SahodayaAdmin/AcademicYearController.php
app/Models/SahodayaProfile.php
app/Models/SahodayaRegistrationWindow.php
app/Services/Membership/MembershipRegistrationWindowService.php
resources/js/Pages/Admin/Sahodaya/Dashboard.vue
resources/js/Pages/Admin/Sahodaya/Membership/Settings.vue
resources/js/Pages/Admin/Sahodaya/AcademicYears/Index.vue
```

**Check every required setting — does a missing value break anything downstream?**

| Setting | Where stored | Breaks if missing | UI highlights it? | Hard validation? |
|---|---|---|---|---|
| Sahodaya prefix (`SahodayaProfile.prefix`) | `sahodaya_profiles` | All membership/student numbers | ? | ? |
| Active academic year | `academic_years` table or `SahodayaProfile.active_academic_year` | Fees, windows, reports | ? | ? |
| Membership fee type + amount / slabs | `SahodayaProfile` + `membership_fee_slabs` | Schools see ₹0 | ? | ? |
| Registration window (`add_open/add_close`) | `sahodaya_registration_windows` | Schools blocked or unblocked forever | ? | ? |
| Payment details (bank/UPI) | `SahodayaProfile` | Schools don't know where to pay | ? | ? |
| Class master (at least one `MasterClass`) | `master_classes` | Schools can't add students | ? | ? |
| Teaching types | `teaching_types` | Teacher registration breaks | ? | ? |
| ZeptoMail API token | `SahodayaProfile.mail_password` | Emails fall back to platform default | ? | ? |
| School registration form config | `SahodayaProfile.application_form_config` | Registration form may show wrong fields | ? | ? |
| Receipt template | `SahodayaProfile.receipt_template_json` | Receipt PDFs blank or malformatted | ? | ? |
| Fest class group scheme (`cbse`/`sahodaya`) | `SahodayaProfile.fest_class_group_scheme` | Age group fee categories wrong | ? | ? |
| Logo | TenantBranding | Sidebar/portal shows placeholder | ? | ? |

**Questions to answer for each:**
1. What happens if it's null/missing? Does the feature silently break, show a PHP error, or show a graceful empty state?
2. Is there any UI indicator (badge, warning, checklist) telling the admin they need to set it?
3. Is there server-side validation that catches a bad value before it propagates?
4. Is the field marked required (`*`) in the form?
5. Is the setup checklist in `Settings.vue` (`incompleteSetup` computed) catching it? Read the computed and verify each condition.

---

## SECTION 2 — Sahodaya Membership Settings UI/UX Audit

**Read:**
```
resources/js/Pages/Admin/Sahodaya/Membership/Settings.vue   (9 tabs)
resources/js/Components/ui/FormField.vue
resources/js/Components/ui/FormSection.vue
resources/js/Components/ui/FormGrid.vue
```

**Audit each tab:**

### Tab: Profile & Rules
- Is the prefix field clearly marked as required? Does the `*` show?
- Is there a lock-state message when `prefixes_locked = true`?
- Is the academic year override confusing vs the formal academic year record? Do users know the difference?
- Does the `fest_class_group_scheme` selector have enough explanation?
- Is there an empty-state / first-run hint if nothing is set yet?

### Tab: Payment Details
- Is there a warning when all payment fields are empty?
- Does the "School preview" section show what schools actually see on the payment page?

### Tab: ZeptoMail API
- Is it clear this is optional (falls back to platform mail)?
- Is there a "Test email" button with clear success/error feedback?

### Tab: Registration Window
- Are `add_open`/`add_close` and `registration_starts_at`/`registration_ends_at` clearly differentiated? (They serve different purposes.)
- Is there a warning when no window is set?
- Does the old V1 window (`registration_starts_at/ends_at`) still appear confusingly alongside V2 (`add_open/add_close`)?

### Tab: Membership Fees
- Does fixed fee = ₹0 show a warning?
- For variable type: is there a warning if no slabs exist?
- Is the academic year clearly shown next to slabs?

### Tab: Class Master
- Is it clear that all schools share this list and cannot override it?
- Is there a warning when the list is empty?
- Are category assignments required before adding a class? What happens if category is missing?

### Tab: Receipt Template
- Is it clear this is only emailed when payment is verified?
- Does the "preview" link work?

### Tab: Registration Form
- Are locked fields (school name, password) clearly explained as non-toggleable?
- Is it clear which fields appear on the public `/school-register` page?

### Tab: Teaching Types
- Is it clear these are for teacher registration submissions?
- Are the global types vs custom types distinction explained?

**Overall Settings page:**
- Does the setup checklist banner appear for a fresh Sahodaya with nothing configured?
- Do tab warning dots (amber) appear on tabs with missing required settings?
- Does "All required settings are configured" show when complete?
- Are the 9 tabs clearly labeled and in a logical order? (First-time user should naturally go: Profile → Academic Year → Fees → Window → Payment → Classes)
- Is there a clear "Save" affordance on every tab?

---

## SECTION 3 — Sahodaya School Approval Workflow

**Read:**
```
app/Http/Controllers/SahodayaAdmin/SchoolController.php (if exists, else grep routes)
resources/js/Pages/Admin/Sahodaya/Schools/Index.vue
resources/js/Pages/Admin/Sahodaya/Schools/Show.vue
resources/js/Pages/Admin/Sahodaya/Membership/Submissions.vue
resources/js/Pages/Admin/Sahodaya/Membership/SubmissionShow.vue
resources/js/Pages/Admin/Sahodaya/Membership/Payments.vue
resources/js/Pages/Admin/Sahodaya/Membership/Reports.vue
```

**Workflow steps to trace end-to-end:**
1. School submits public registration form → Sahodaya sees it in Schools list (status=pending)
2. Sahodaya reviews and approves → school gets login credentials
3. School submits annual membership data (students/teachers) → Sahodaya sees submission
4. Sahodaya reviews submission → approves or rejects
5. School uploads payment proof → Sahodaya verifies → receipt emailed
6. School membership status → `approved`

**Check:**
- Is each step visible with a clear status in the UI?
- Are there action buttons at each step (Approve / Reject / Request changes)?
- Are status labels consistent (`pending`, `approved`, `rejected`, `under_review`)?
- Is the `pendingSchoolsCount` badge on the sidebar correct? Where is it computed?
- Is `pendingSubmissionsCount` wired up? (In `SahodayaAdminController::inertia()` it's hardcoded to 0 — check if this is fixed)
- Is `pendingPaymentsCount` accurate?
- What does the school see if Sahodaya hasn't approved them yet? Is there a clear message on their dashboard?

---

## SECTION 4 — School First-Time Setup Requirements

**Read:**
```
app/Http/Controllers/SchoolAdmin/SchoolAdminController.php
app/Http/Controllers/SchoolAdmin/DashboardController.php
app/Http/Controllers/SchoolAdmin/SchoolSetupController.php
app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php
app/Http/Controllers/SchoolAdmin/StudentController.php   (lines 20-160 — prefix checks)
resources/js/Pages/Admin/School/Dashboard.vue
resources/js/Pages/Admin/School/Setup/Code.vue
resources/js/Pages/Admin/School/Registration/Index.vue
```

**Required settings for a school:**

| Setting | Where | Breaks if missing | UI highlights it? |
|---|---|---|---|
| School code (`school_prefix`) | `tenants.school_prefix` | Student add/edit/bulk/export blocked. `canBegin` = false | ? |
| Annual registration begun | `membership_submissions` | Cannot add students for the year | ? |
| Students submitted | `membership_submissions.status` | Can't proceed to payment step | ? |
| Payment proof uploaded | `membership_payments` | Annual cycle incomplete | ? |
| Sahodaya membership approved | `tenants.membership_status` | Portal access blocked for students/teachers | ? |

**Questions:**
1. Does the school dashboard show a clear "setup incomplete" banner when `school_prefix` is null?
2. Is there a step-by-step guide on the dashboard for a brand-new school? (`School/Dashboard.vue` — check the action queue / setup steps)
3. When `canBegin = false`, does the registration page explain WHY (prefix missing? window closed? already registered?)
4. Does `SchoolSetupController::code()` show a clear explanation of what the code is and how it's used?
5. Is there a "next step" CTA after each completed step?

---

## SECTION 5 — School Annual Registration Flow (Detailed)

**Read:**
```
resources/js/Pages/Admin/School/Registration/Index.vue
resources/js/Pages/Admin/School/Registration/Profile.vue
resources/js/Pages/Admin/School/Registration/Counts.vue
resources/js/Pages/Admin/School/Registration/Students.vue
resources/js/Pages/Admin/School/Registration/Teachers.vue
resources/js/Pages/Admin/School/Registration/Payment.vue
app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php
app/Services/Membership/MembershipRegistrationWindowService.php
```

**Each step — check:**
- Is the current step highlighted in a progress indicator?
- Is the "why can't I proceed" message shown when a step is blocked?
- On the Students step: is the student count visible, and is it clear what categories they belong to?
- On the Payment step: are the Sahodaya bank/UPI details visible? (pulled from `SahodayaProfile`)
- After payment submission: is there a clear "submitted, waiting for verification" message?
- What happens if the school tries to re-start an already-submitted registration? Is there a lock?
- Does the registration window enforcement show the correct open/close dates from `add_open/add_close` columns?

---

## SECTION 6 — School Dashboard UI/UX

**Read:**
```
resources/js/Pages/Admin/School/Dashboard.vue
app/Http/Controllers/SchoolAdmin/DashboardController.php
```

**Check:**
- Is there an action queue (pending items the admin needs to act on)?
- Is the school code setup step shown prominently for new schools?
- Is the annual registration status shown (not started / in progress / submitted / approved)?
- Are Kalotsav / MCQ / Training widgets visible with correct counts?
- Are any widget counts hardcoded to 0 or missing entirely?
- Is the sidebar nav structure logical? (Settings → Annual Registration → Students → Teachers → Staff → Events → MCQ → Training → Portal Users → ...)
- Does the dashboard have a "get started" guide for first-time school admins?

---

## SECTION 7 — School Admin Sidebar & Navigation Audit

**Read:**
```
resources/js/Layouts/SchoolAdminLayout.vue
resources/js/support/schoolAdminNav.js   (or wherever school nav items are defined)
```

**For every nav item, check:**
- Does it link to an existing page? (No dead links)
- Is it shown only to the right roles? (Principal vs VP vs Event Coordinator vs Staff)
- Is it grouped logically? (Membership / Fest Events / MCQ / Training / Website / Admin)
- Are badge counts shown on items that have pending items?
- Is the active state highlighted correctly?
- Are any nav items missing that should be there?
- Are there nav items that point to pages that don't exist yet?

**Specific items to verify:**
- "Annual Registration" — shown only when membership features enabled?
- "Setup > School Code" — shown only when `school_prefix` is null?
- "Portal Users" — shows student + teacher portal user management?
- "Profile Change Requests" — newly added, does it appear?
- "Training" — links to Training/Index.vue?
- "MCQ" — links to Mcq/Index.vue (school hub)?

---

## SECTION 8 — Sahodaya Admin Sidebar & Navigation Audit

**Read:**
```
resources/js/Layouts/SahodayaAdminLayout.vue
resources/js/support/sahodayaAdminNav.js   (or wherever it's defined)
```

**Check all nav groups:**
- Membership group: Settings, Schools, Submissions, Payments, Reports — all linked?
- Kalotsav group: hub → event → all sub-pages (Items, Schedule, Registrations, Results, Certificates, etc.)
- Sports Meet group: same as Kalotsav
- MCQ group: all pages linked (Series, Exams, Attendance, Reports, etc.)
- Training group: linked?
- Website group: linked?
- Reports/Ledger group: linked?
- Academic Years: linked?

**Check:**
- Is "Main menu" section header removed from Kalotsav nav? (Previously flagged as P3 bug)
- Are badge counts on pending items accurate?
- Does staff-only view hide the correct sections based on permissions?
- Are any sections visible when the underlying feature is disabled?

---

## SECTION 9 — Sahodaya Fest Event Setup & Workflow

**Read:**
```
app/Http/Controllers/SahodayaAdmin/FestEventSettingsController.php   (lines 1-100)
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/LifecycleTab.vue
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/FeesTab.vue
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/RegistrationTab.vue
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/NumberingTab.vue
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/VenuesTab.vue
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/GradesTab.vue
resources/js/Pages/Admin/Sahodaya/Events/Settings/Tabs/PointsTab.vue
app/Services/Events/FestLifecycleService.php
```

**Required configs before an event can go live:**

| Config | Tab | Missing = ? |
|---|---|---|
| At least one item (program/discipline) added | Items page | Schools can't register |
| Fee model set | Fees tab | Schools charged ₹0 or error |
| Chest number scheme set | Numbering tab | Numbers not assigned |
| Venues added (if venue-based) | Venues tab | Schedule has no rooms |
| Grade config (A/B/C thresholds) | Grades tab | Results can't be published |
| Point rules | Points tab | Championship table empty |
| Event status lifecycle | Lifecycle tab | Schools can't see event |

**Check:**
- Does `FestLifecycleService::checklist()` surface all these gaps? Read the service and list what it checks.
- Does the Lifecycle tab show the checklist with clear pass/fail per item?
- Is the suggested status (`suggestedStatus`) sensible?
- What is the correct order to configure: Lifecycle → Items → Fees → Registration → Venues → Grades → Points → Numbering?
- Is there a "quick start" guide on the event overview page?

---

## SECTION 10 — School Fest Registration Flow

**Read:**
```
resources/js/Pages/Admin/School/Events/FestHub.vue
resources/js/Pages/Admin/School/Events/Registration.vue
resources/js/Pages/Admin/School/Events/Programs.vue
resources/js/Pages/Admin/School/Events/ProgramHub.vue
app/Http/Controllers/SchoolAdmin/FestRegistrationController.php
app/Http/Controllers/SchoolAdmin/KalotsavController.php
```

**Trace the school-side flow:**
1. School sees events list — is the registration window open/closed status clear?
2. School selects students for an item — is eligibility checked with a clear message?
3. School pays fest fee — is the fee amount and due date visible?
4. School views admit cards / chest numbers — are they available before or after event goes live?
5. School views results — are they visible only after Sahodaya publishes them?

**Check:**
- Is there a "you haven't registered yet" empty state on FestHub when no registrations exist?
- Are clash warnings shown before submission?
- Are substitution request pages functional?

---

## SECTION 11 — Portal Pages Audit

### Student Portal
**Read:** `resources/js/Pages/Admin/Portal/Student/` (all files)
- Dashboard: shows upcoming fest events, MCQ exams, results — are counts real or hardcoded?
- FestRegistrations: does the student see their own items only?
- FestResults: visible only after published?
- FestSchedule: correct dates from `FestStage`?
- McqHub: shows available exams, fee status, can start exam?
- McqExam: timer works? Auto-submit on expiry?
- McqResult: grade shown correctly (A/B/C)?
- Profile: can update? Triggers change request or direct update?
- FestCertificates: downloadable after event completes?
- SportsResults: populated?

### Teacher Portal
**Read:** `resources/js/Pages/Admin/Portal/Teacher/` (all files)
- Dashboard: shows assigned items, upcoming schedule?
- Fest: correct events visible to this teacher?
- FestSchedule: correct?
- Results: teacher can view results of their school's participants?
- Certificates: downloadable?
- QuestionBanks + QuestionBankShow: teacher can create/edit questions?
- Training: enrolled trainings shown?
- Profile: change request flow working? (`ProfileChangeRequests.vue` on school side)

### Judge Portal
**Read:** `resources/js/Pages/Admin/Portal/Judge/` (all files)
- Dashboard: shows assigned items only?
- MarkEntry: can enter marks? Shows existing marks? Saves correctly?
- Is there a "no items assigned" empty state?

### FestOps Portal
**Read:** `resources/js/Pages/Admin/Portal/FestOps/` (all files)
- Dashboard, Event, Stage, Attendance, Certificates, Appeals, GateCheck, Kitchen, ParticipantSearch, Registrations, Coordinator
- Are all these scoped to the assigned event only?
- Kitchen / GateCheck — are these production-ready?

### Group Admin Portal
**Read:** `resources/js/Pages/Admin/Portal/Group/` (all files)
- Dashboard, FestRegistrations, FestSchedule, FestClashes, FestAdmitCards, Students
- Are these scoped to the right group?

### House Admin Portal
**Read:** `resources/js/Pages/Admin/Portal/HouseAdmin/` (all files)
- Dashboard, Ranking, Registrations, Students

### Exam Supervisor Portal
**Read:** `resources/js/Pages/Admin/Portal/Exam/` (all files)
- Dashboard, Supervision, Attendance, MarkEntry

**For all portals check:**
- Is `password.change` middleware applied? (Known P1 gap — check if fixed)
- Does each portal have a sensible sidebar/nav?
- Are empty states shown when no data exists?
- Is there a "portal access denied" state if the user isn't properly provisioned?

---

## SECTION 12 — Reports Audit (Sahodaya + School)

### Sahodaya-side reports
**Read:**
```
resources/js/Pages/Admin/Sahodaya/Membership/Reports.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/Hub.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/Downloads.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/ParticipationCounts.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/SchoolDetailed.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/ItemCounts.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/HouseDetailed.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/MarkEntryStatus.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/OverallRanking.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/FeeCollection.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/ScheduleClashes.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/RegistrationRegister.vue
resources/js/Pages/Admin/Sahodaya/Events/Reports/ItemSchedule.vue
resources/js/Pages/Admin/Sahodaya/Mcq/Reports.vue
resources/js/Pages/Admin/Sahodaya/Ledger/Index.vue
resources/js/Pages/Admin/Sahodaya/Ledger/Reports.vue
```

**For each report:**
- Does it load without errors when the underlying data is empty?
- Are filters (academic year, event, school) functional?
- Are there export buttons (CSV/PDF)? Do they work?
- Is pagination present for large datasets?
- Are column headers descriptive?
- Does the "no data" empty state look good?

### School-side reports
**Read:**
```
resources/js/Pages/Admin/School/Events/ReportsHub.vue
resources/js/Pages/Admin/School/Events/ReportParticipation.vue
resources/js/Pages/Admin/School/Events/ReportItemWise.vue
resources/js/Pages/Admin/School/Events/ReportStudentWise.vue
resources/js/Pages/Admin/School/Events/ReportTeacherWise.vue
resources/js/Pages/Admin/School/Events/ReportFeeSummary.vue
resources/js/Pages/Admin/School/Events/ReportIdCards.vue
resources/js/Pages/Admin/School/Events/ReportMarkEntryStatus.vue
resources/js/Pages/Admin/School/Events/ReportResultsSummary.vue
resources/js/Pages/Admin/School/Events/ReportScheduleClashes.vue
resources/js/Pages/Admin/School/Events/ReportRegistrationRegister.vue
resources/js/Pages/Admin/School/Events/ReportDisciplineParticipation.vue
resources/js/Pages/Admin/School/Payments/Index.vue
```

**Same checks as above. Additionally:**
- Are hall tickets / admit cards printable from the school side?
- Is ID card generation available (or is it Sahodaya-only)?

---

## SECTION 13 — MCQ System Workflow

**Read:**
```
app/Http/Controllers/SahodayaAdmin/McqExamController.php   (key methods: update, publishResults)
app/Services/Mcq/McqExamSessionService.php
resources/js/Pages/Admin/Sahodaya/Mcq/Show.vue
resources/js/Pages/Admin/Sahodaya/Mcq/Series/Show.vue
resources/js/Pages/Admin/Sahodaya/Mcq/Dashboard.vue
resources/js/Pages/Admin/School/Mcq/Index.vue
resources/js/Pages/Admin/School/Mcq/ExamDetail.vue
resources/js/Pages/Admin/Portal/Student/McqHub.vue
resources/js/Pages/Admin/Portal/Student/McqExam.vue
```

**Trace the full MCQ workflow:**
1. Sahodaya creates series → sets fee → publishes
2. School registers students for exam → pays fee (if paid exam)
3. Sahodaya approves school payment
4. Student sees exam in their portal → starts exam
5. Student completes or time expires → auto-submitted
6. Sahodaya publishes results → student sees result + grade

**Check per step:**
- Is the "publish" button blocked for free exams with fee ≤ 0? (Known P1 bug — was it fixed?)
- Is there a clear status badge at each stage?
- Does the school see which students are registered vs not registered?
- Does the student see time remaining during the exam?
- Does auto-submit work? (Cron command `mcq:auto-submit-expired`)
- Is the grade display correct? (Known P3: no A+ grade band above 90%)
- Are MCQ reports (attempts, scores, school-wise) visible and accurate?

---

## SECTION 14 — Cross-Cutting UI/UX Checks

**Read:**
```
resources/js/Layouts/SahodayaAdminLayout.vue
resources/js/Layouts/SchoolAdminLayout.vue
resources/js/Layouts/PortalLayout.vue
resources/js/Components/ui/PageHeader.vue
resources/js/Components/ui/EmptyState.vue
resources/js/Components/ui/ActionBanner.vue
resources/js/Components/ui/FormField.vue
```

**Check across all pages:**

1. **Empty states** — Every list/table page should have an `<EmptyState>` with an icon, title, description, and a CTA action button. Read 5–10 list pages and check.

2. **Mobile / overflow** — Tables should have `overflow-x-auto` wrapper. Check all table pages.

3. **Page titles** — Every page should set a `<Head title="...">` (or equivalent). Check 10 pages.

4. **Breadcrumbs / eyebrows** — `<PageHeader>` should have `eyebrow` and `title` on every page. Check for consistency.

5. **Loading / processing states** — Form submit buttons should show "Saving…" or be disabled while `form.processing`. Check 5 forms.

6. **Success / error flash** — After saves, flash messages should appear. Check that `back()->with('success', ...)` is paired with a flash display component in the layout.

7. **Validation error display** — Inertia form errors should be shown near the field. Check `profileForm.errors.xxx` display patterns.

8. **Confirmation dialogs** — Destructive actions (delete, reject, cancel) should use `confirm()` or a modal. Check delete/reject buttons.

9. **Date formats** — All dates should be formatted in `en-IN` locale consistently (`dd Mon yyyy`). Check tables vs forms.

10. **Currency format** — All money amounts should be `₹X,XX,XXX` Indian format. Check fee displays.

---

## SECTION 15 — Data Integrity & Workflow Guards

**Read:**
```
app/Http/Middleware/EnsureSchoolAdmin.php
app/Http/Middleware/EnsurePasswordChanged.php
app/Http/Middleware/EnsureEventCoordinatorScope.php
routes/web.php   (school admin group lines 200–600, portal groups lines 900–1100)
app/Services/Membership/MembershipRegistrationWindowService.php
```

**Check:**
1. **Password change enforcement** — Is `password.change` middleware on ALL portal route groups (Teacher, Student, Judge, FestOps, Exam Supervisor)? Known P1 gap — verify if fixed.
2. **Membership gate** — Are fest registration routes blocked for schools with `membership_status != 'approved'`? Where is this gate?
3. **Registration window** — Does `MembershipRegistrationWindowService::blockReason()` now read `add_open`/`add_close` (not old `registration_starts_at`)? This was a P1 bug — verify fix.
4. **School prefix gate** — Does `StudentController` block all student operations when `school_prefix` is null? Read lines 20–160.
5. **Event Coordinator scope** — Is `EnsureEventCoordinatorScope` correctly limiting access? Only events assigned via `school_user_event_scopes`.
6. **Chest number race condition** — Was the P0 fix applied? Is there a unique index on `(event_id, chest_no)` in migrations? Does `FestNumberingService` now use a DB lock?

---

## OUTPUT FORMAT

For each section, produce a table:

| # | Finding | Severity | File(s) | Fix needed |
|---|---|---|---|---|
| 1 | ... | P0/P1/P2/P3 | `path/to/file.php:line` | ... |

**Severity guide:**
- **P0** — Data loss, race condition, security hole, or feature completely broken
- **P1** — Feature blocked or silent wrong data; blocks normal workflow
- **P2** — Confusing UX, missing empty state, incomplete flow; workarounds exist
- **P3** — Polish, cosmetic, minor inconsistency

After each section's table, add a **Summary** line: "X issues found (P0: N, P1: N, P2: N, P3: N)"

At the end, produce a **Master Priority List** of the top 10 items to fix first across all sections.

---

## HOW TO USE THIS PROMPT

1. Paste this entire file into a new session
2. Work through sections 1–15 in order, reading the listed files for each
3. For each file, focus on: what data flows in, what's checked, what's returned to the Vue page, and what the Vue does with missing/empty data
4. Do NOT guess — only report findings grounded in code you have read
5. After completing all 15 sections, write the master priority list

**Tip:** Read multiple files in parallel using separate bash/Read calls in the same message to speed up the audit.
