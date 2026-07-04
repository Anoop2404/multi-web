# UI/UX Audit Findings — Sahodaya Connect

**Audited:** July 2026  
**Resolved:** July 2026 (UX-001 through UX-036)  
**Pages reviewed:** 261 Vue pages, all 13 role tiers  
**Severity:** 🔴 Critical · 🟠 High · 🟡 Medium · 🟢 Low / Polish

Legend: Each finding is assigned a code (UX-001…) for easy reference in tickets.

> **Status:** All findings below are **✅ Fixed**. Run `php artisan tenants:migrate` to apply the MCQ `draft_answers` migration (`2026_07_09_000001_mcq_draft_answers`).

---

## Summary table

| Code | Status | Severity | Role tier | Area | Short description |
|------|--------|----------|-----------|------|-------------------|
| UX-001 | ✅ | 🔴 | Student Portal | Dashboard | Duplicate "registrations" sections — same data rendered twice |
| UX-002 | ✅ | 🔴 | Student Portal | MCQ Exam | No per-question auto-save — page crash = all answers lost |
| UX-003 | ✅ | 🟠 | Student Portal | Dashboard | Nav missing Fest Schedule, Results, Certificates |
| UX-004 | ✅ | 🟠 | Student Portal | Profile | Minimal — no name display/edit, no photo, no lock status |
| UX-005 | ✅ | 🟠 | Student Portal | MCQ Exam | No submit confirmation dialog; no "mark for review" |
| UX-006 | ✅ | 🟠 | Judge Portal | Mark Entry | Grade is free-text input — should be A+/A/B/C dropdown |
| UX-007 | ✅ | 🟠 | FestOps Portal | Gate Check | No camera QR scanner — must paste payload manually |
| UX-008 | ✅ | 🟠 | FestOps Portal | Gate Check | No participant photo shown on scan — identity gap |
| UX-009 | ✅ | 🟠 | Super Admin | Audit Log | No tenant/Sahodaya filter — can't isolate one cluster's events |
| UX-010 | ✅ | 🟠 | State Admin | Winners | Missing school name, category, grade columns |
| UX-011 | ✅ | 🟠 | Group Admin | Dashboard | "View Students" link button has `text-white` but no background class — invisible text |
| UX-012 | ✅ | 🟡 | Super Admin | Tenants | No search / filter on tenant list |
| UX-013 | ✅ | 🟡 | Super Admin | Dashboard | No quick link to audit log or billing from dashboard |
| UX-014 | ✅ | 🟡 | State Admin | Dashboard | No academic year indicator / filter |
| UX-015 | ✅ | 🟡 | State Admin | Remittances | No pagination on remittances table |
| UX-016 | ✅ | 🟡 | Super Admin | Billing | No search or pagination on subscriptions tab |
| UX-017 | ✅ | 🟡 | Sahodaya Admin | Mark Entry | No success toast / feedback after per-row Save |
| UX-018 | ✅ | 🟡 | Sahodaya Admin | Mark Entry | No "save all" / batch-save button |
| UX-019 | ✅ | 🟡 | Sahodaya Admin | Results | No confirmation before publishing results to public |
| UX-020 | ✅ | 🟡 | Sahodaya Admin | Registrations | No text search for participant name — only school filter |
| UX-021 | ✅ | 🟡 | School Admin | Students | No DOB column in student table (critical for sports) |
| UX-022 | ✅ | 🟡 | School Admin | Students | No per-student "generate / view login" shortcut |
| UX-023 | ✅ | 🟡 | School Admin | Dashboard | No recent activity or notifications widget |
| UX-024 | ✅ | 🟡 | School Admin | Registration | No "window closing soon" warning banner |
| UX-025 | ✅ | 🟡 | School Admin | Events | No real-time age-eligibility warning before sports registration |
| UX-026 | ✅ | 🟡 | Teacher Portal | Dashboard | No side-nav or section jump links — long scroll-only layout |
| UX-027 | ✅ | 🟡 | Exam Portal | Dashboard | No countdown timer when an exam is starting soon |
| UX-028 | ✅ | 🟡 | Student Portal | MCQ Exam | Mobile: question navigator sidebar hidden below `lg:` breakpoint |
| UX-029 | ✅ | 🟡 | Judge Portal | Mark Entry | No category / item context shown at the top of mark entry page |
| UX-030 | ✅ | 🟢 | Auth | All login pages | No "Remember me" checkbox on any login form |
| UX-031 | ✅ | 🟢 | Auth | Login | No "forgot password" link on Sahodaya admin and school admin login pages |
| UX-032 | ✅ | 🟢 | Sahodaya Admin | Schedule | No drag-to-reorder on schedule rows; reordering requires re-entering slots |
| UX-033 | ✅ | 🟢 | Sahodaya Admin | School activity | School participation badges are not clickable (no link to school detail) |
| UX-034 | ✅ | 🟢 | School Admin | Students | Change requests table shows no Approve/Reject for school (expected flow, but could use status-update tooltips) |
| UX-035 | ✅ | 🟢 | Student Portal | Dashboard | No chest number in "My registrations" section (only visible in Fest Schedule) |
| UX-036 | ✅ | 🟢 | State Admin | Kalotsav Winners | No "share" / public link for winners page from admin side |

---

## Detailed findings

---

### 🔴 UX-001 — Student Dashboard: duplicate registrations sections

**File:** `resources/js/Pages/Admin/Portal/Student/Dashboard.vue`

Two sections — "My registrations" and "Event Registrations" — both iterate the same `registrations` prop. Every student sees each entry twice.

**Fix:** Remove the duplicate section. Decide which heading to keep ("My registrations" is simpler) and delete the other `v-for` block.

---

### 🔴 UX-002 — MCQ Exam: no per-question auto-save

**File:** `resources/js/Pages/Admin/Portal/Student/McqExam.vue`

All answers are held in reactive state (`answers` object). A browser crash, accidental tab close, or network error before the student submits loses everything. There is no periodic save-to-server.

**Fix:** After each answer change (`@change` / `@click` on option), fire a debounced `PUT /portal/student/{school}/mcq-exams/{session}/answer` to persist individual answers server-side. On page load, restore from the session record.

---

### 🟠 UX-003 — Student Portal: nav missing key pages

**File:** `resources/js/Pages/Admin/Portal/Student/Dashboard.vue` (`navItems` computed)

Current nav: Dashboard, Profile. Missing: Fest Schedule, Results, Certificates — all of which have content already rendered as sections on the dashboard.

**Fix:** Add nav items for `/portal/student/{school}/fest/schedule`, `/portal/student/{school}/results`, `/portal/student/{school}/certificates` so students can deep-link directly without scrolling past unrelated sections.

---

### 🟠 UX-004 — Student Profile: extremely minimal

**File:** `resources/js/Pages/Admin/Portal/Student/Profile.vue`

Current profile page shows only: email, parent phone, change password form.

Missing:
- Student's own name (read-only when locked, editable when unlocked)
- Profile photo upload / display
- Lock status message ("Records locked — contact school admin to request changes")
- "Request change" CTA when records are locked

**Fix:** Pass `student.name`, `student.photo_url`, and `lock_status` from the controller. Render name as read-only field when locked. Show a notice banner explaining the lock. Add a "Request edit" button that opens a change-request modal (same flow as the school admin's change-request workflow).

---

### 🟠 UX-005 — MCQ Exam: no submit confirmation; no mark-for-review

**File:** `resources/js/Pages/Admin/Portal/Student/McqExam.vue`

Clicking "Submit" fires the final submission immediately without a confirmation dialog. A student can submit accidentally.

Also, the question navigator only tracks answered / unanswered. There is no "mark for review" state that students commonly expect from exam UIs.

**Fix:** Wrap submit in a confirmation modal ("You have answered X of Y questions. Submit now?"). Add a `markedForReview` reactive Set; toggle flag per question; colour the navigator badge accordingly.

---

### 🟠 UX-006 — Judge Mark Entry: grade is free text

**File:** `resources/js/Pages/Admin/Portal/Judge/MarkEntry.vue`

The grade field is `<input type="text">`. Judges can enter any string. Valid values are A+, A, B, C.

The Sahodaya admin's own mark entry page (`Admin/Sahodaya/Events/MarkEntry.vue`) already uses a `<select>` with the correct options — the judge portal was not updated to match.

**Fix:** Replace the text input with `<select><option>A+</option><option>A</option><option>B</option><option>C</option></select>` (same as the Sahodaya admin page).

---

### 🟠 UX-007 — Gate Check: no camera scanner

**File:** `resources/js/Pages/Admin/Portal/FestOps/GateCheck.vue`

The gate check page asks the operator to paste the QR payload into a textarea. On mobile devices at a real-world gate, this is impractical — the operator must copy raw JSON text from a QR app, switch to the browser, paste, then submit.

**Fix:** Integrate a browser-native camera QR scanner using the `jsQR` or `@zxing/browser` library. Wrap in a progressive-enhancement pattern: show the camera scanner if `navigator.mediaDevices.getUserMedia` is available, fall back to the textarea. The `<video>` element + canvas scan loop is well-established and works in Safari/Chrome mobile.

---

### 🟠 UX-008 — Gate Check: no participant photo

**File:** `resources/js/Pages/Admin/Portal/FestOps/GateCheck.vue`

After a successful QR scan, the result panel shows name and chest number but no photo. Without a photo, identity verification is not possible at the gate.

**Fix:** Return `student.photo_url` (or a placeholder avatar) in the `FestGateCheckController` response and render it in the result card.

---

### 🟠 UX-009 — Audit Log: no tenant filter

**File:** `resources/js/Pages/Admin/Audit/Index.vue`

The platform audit log shows all events across all Sahodaya tenants (up to 200). There is no way to filter by tenant/Sahodaya cluster. A super admin investigating one cluster must scroll or guess.

**Fix:** Add a "Sahodaya" select dropdown populated from `Tenant::all(['id','name'])`. Pass `tenant_id` to the controller query.

---

### 🟠 UX-010 — State Kalotsav Winners: missing columns

**File:** `resources/js/Pages/Admin/State/Kalotsav/Winners.vue`

Current columns: participant, reg_no, item, from_event, next_level, promoted_at.

Missing: school name, category (LP/UP/HS/HSS), grade (A+/A/B/C).

Without category and grade, this page is not useful for generating certificates or verifying results at state level.

**Fix:** Include `winner.school.name`, `winner.category`, `winner.grade` in the controller query (`with('school', 'category')`) and add columns to the table.

---

### 🟠 UX-011 — Group Admin: invisible button text

**File:** `resources/js/Pages/Admin/Portal/Group/Dashboard.vue`

The "View Students →" link button has classes `text-white rounded-xl` but no background color class. The text is white on a white card background — invisible.

**Fix:** Add `bg-violet-600` (matches the portal's violet accent) or use the existing `btn-primary` class:
```html
<Link :href="..." class="btn-primary block w-full text-center py-3 rounded-xl font-semibold">
```

---

### 🟡 UX-012 — Tenant list: no search or filter

**File:** `resources/js/Pages/Admin/Tenants/Index.vue`

The tenant list has no search input, no status filter (active/inactive/trial), and no sort controls. As the platform grows beyond ~20 tenants this becomes unusable.

**Fix:** Add a search input (name/domain) and a status filter dropdown. Wire to existing `router.get()` pattern used elsewhere.

---

### 🟡 UX-013 — Super Admin dashboard: no quick-access to audit or billing

**File:** `resources/js/Pages/Admin/Dashboard.vue`

The dashboard shows 4 stat tiles and 5 track cards but has no link to the audit log or billing section. These are the two most-accessed operational pages for a super admin.

**Fix:** Add two QuickAction / HubCard links to the dashboard for `/admin/audit` and `/admin/billing`.

---

### 🟡 UX-014 — State dashboard: no academic year context

**File:** `resources/js/Pages/Admin/State/Dashboard.vue`

All KPI stats are displayed without indicating which academic year they belong to. If the active year changes, stats change silently.

**Fix:** Add an academic year badge/chip in the header, and optionally a year-switcher dropdown if the controller supports it.

---

### 🟡 UX-015 — State Remittances: no pagination

**File:** `resources/js/Pages/Admin/StateRemittances/Index.vue`

The remittances table has no pagination controls. For clusters with many schools, this renders all rows as a single long list.

**Fix:** Paginate the backend query and add `SahodayaDataTable` pagination controls.

---

### 🟡 UX-016 — Billing: no search or pagination on subscriptions

**File:** `resources/js/Pages/Admin/Billing/Index.vue`

The subscriptions tab shows all records without search or pagination.

**Fix:** Add a search input and paginate the subscriptions query in the controller.

---

### 🟡 UX-017 — Sahodaya Mark Entry: no save feedback

**File:** `resources/js/Pages/Admin/Sahodaya/Events/MarkEntry.vue`

Each row has an individual "Save" button but there is no visible success toast or inline confirmation after saving. The admin cannot tell if the save worked.

**Fix:** After `router.patch(…)` resolves, show a brief success toast (e.g., using `$page.props.flash` or a reactive local state flag per participant row).

---

### 🟡 UX-018 — Sahodaya Mark Entry: no batch save

**File:** `resources/js/Pages/Admin/Sahodaya/Events/MarkEntry.vue`

Each participant requires a separate Save click. For items with 20+ participants (relay teams, group events) this is slow.

**Fix:** Add a "Save all" button at the top of each item section that fires a bulk `PATCH /marks/bulk` endpoint, sending all `markForms` for the item at once.

---

### 🟡 UX-019 — Results page: no publish confirmation

**File:** `resources/js/Pages/Admin/Sahodaya/Events/Results.vue`

"Publish Results" immediately sets `results_published = true` and makes results live on the public portal with no confirmation step.

**Fix:** Wrap the publish action in a confirm modal: "This will make results visible to all schools and the public. Continue?"

---

### 🟡 UX-020 — Registrations: no participant name search

**File:** `resources/js/Pages/Admin/Sahodaya/Events/Registrations.vue`

Filtering is by school only. Admins cannot search by student name or reg number to find a specific participant's registration.

**Fix:** Add a text search input to the filter bar; filter `filteredRegistrations` by `performers[].student.name` / `student.reg_no` on the frontend, or add a `search` query param to the backend endpoint.

---

### 🟡 UX-021 — Student table: no DOB column

**File:** `resources/js/Pages/Admin/School/Students/Index.vue`

The student list table shows: name, reg no, class, status. Date of birth is not shown. For sports age-group eligibility checks, school admins frequently need to see DOB without opening each student.

**Fix:** Add a `dob` column (formatted as DD/MM/YYYY). It can be optional / toggled since it adds width.

---

### 🟡 UX-022 — Student table: no login credentials shortcut

**File:** `resources/js/Pages/Admin/School/Students/Index.vue`

There is no way to quickly view or reset a student's login credentials from the list. Admins must navigate to each student's detail page.

**Fix:** Add a "Login" chip or action in the row that opens a modal with the student's username and a "Reset password" button. This is already supported in `Tenants/Show.vue` for admin logins; apply the same pattern.

---

### 🟡 UX-023 — School dashboard: no activity / notification widget

**File:** `resources/js/Pages/Admin/School/Dashboard.vue`

The school dashboard shows only a setup checklist and static info. There is no widget for recent activity (new student added, payment verified, registration approved/rejected), and no notification bell/count.

**Fix:** Add a "Recent activity" list fed from the school's audit log (last 5–10 actions). The `ActionBanner` component and `dashboardExtras` pattern already used in the Sahodaya admin dashboard can be reused.

---

### 🟡 UX-024 — Annual Registration: no closing-soon warning

**File:** `resources/js/Pages/Admin/School/Registration/Index.vue`

The registration window dates are shown but there is no urgent banner when the window is closing within (e.g.) 3 days. Schools may miss deadlines.

**Fix:** Compute `daysUntilClose = diff(registrationWindow.registration_ends_at, now)`. If ≤ 3 days, show a `notice-banner--warning` ("Registration closes in X days").

---

### 🟡 UX-025 — Sports registration: no real-time age warning

**File:** `resources/js/Pages/Admin/School/Events/Registration.vue`

When a school picks a student for a sports event, there is no client-side check that the student's age matches the event's age group. The mismatch is only caught on form submit (server-side), which is frustrating.

**Fix:** When a student is selected, compute `age = cutoffDate - student.dob` in the Vue component and compare against `item.age_group`. Show an inline warning badge if ineligible.

---

### 🟡 UX-026 — Teacher Portal: no section navigation

**File:** `resources/js/Pages/Admin/Portal/Teacher/Dashboard.vue`

All sections (MCQ banks, training, fest registrations, schedule, admit cards, results, certs, fees, appeals) are stacked in a single scroll. The nav has only "Dashboard". With many active programs, scrolling is the only way to reach a section.

**Fix:** Either split into separate pages (each with its own nav item) or add an in-page sticky jump nav (`<a href="#training">Training</a>`, etc.) with `id` anchors on each section.

---

### 🟡 UX-027 — Exam Portal: no exam countdown

**File:** `resources/js/Pages/Admin/Portal/Exam/Dashboard.vue`

The exam supervisor dashboard shows scheduled exam date but no countdown timer. If an exam is starting in 30 minutes, there is no alert.

**Fix:** For exams with `status = 'scheduled'` and `scheduled_at` within 2 hours, show a countdown badge: "Starts in 1h 23m" using a `setInterval`.

---

### 🟡 UX-028 — MCQ Exam: question sidebar hidden on mobile

**File:** `resources/js/Pages/Admin/Portal/Student/McqExam.vue`

The question navigator sidebar uses `lg:flex` and is hidden on screens below 1024px. Mobile students have no way to see their progress or jump to unanswered questions.

**Fix:** Add a collapsible/drawer version of the sidebar for mobile. A "Questions" button fixed at the bottom of the screen opens a bottom sheet with the grid of question numbers.

---

### 🟡 UX-029 — Judge Mark Entry: no item context at top

**File:** `resources/js/Pages/Admin/Portal/Judge/MarkEntry.vue`

The mark entry page drops judges into a list of registrations without prominently showing which event / item they are marking. Category and school are visible per row but the overall item title is not shown as a header.

**Fix:** Add a page-level `<h2>` showing `event.title` and `item.title` above the participant table.

---

### 🟢 UX-030 — Login pages: no "Remember me"

**Files:** `Admin/Auth/Login.vue`, `Admin/Auth/SchoolLogin.vue`, `Admin/Auth/PortalLogin.vue`

None of the three login forms have a "Remember me" checkbox. Sessions expire on browser close, requiring re-login frequently on shared school computers.

**Fix:** Add `<input type="checkbox" v-model="form.remember">` and pass it to `Auth::attempt(['email' => …], $remember)` in the controller.

---

### 🟢 UX-031 — Admin and school login: no forgot-password link

**Files:** `Admin/Auth/Login.vue`, `Admin/Auth/SchoolLogin.vue`

The portal login has a "Forgot password?" link. The Sahodaya admin login and school login do not. Admins who forget their password have no self-service recovery path.

**Fix:** Add `<a href="/forgot-password">Forgot password?</a>` (or the appropriate tenant-scoped route) to both login pages.

---

### 🟢 UX-032 — Schedule: no drag-to-reorder

**File:** `resources/js/Pages/Admin/Sahodaya/Events/Schedule.vue`

Reordering schedule slots requires deleting and re-adding entries. There is no drag-and-drop on the table rows, despite `sort_order` being a stored field.

**Fix:** Add `@vuedraggable/next` to the schedule table `<tbody>`. On drag-end, fire a `PUT /schedule/reorder` endpoint with the new `sort_order` array.

---

### 🟢 UX-033 — Sahodaya dashboard: school badges not linked

**File:** `resources/js/Pages/Admin/Sahodaya/Dashboard.vue`

The "school participation" section renders school name badges. Clicking a badge does nothing — there is no link to the school detail page.

**Fix:** Wrap each badge in `<Link :href="\`/sahodaya-admin/${sahodaya.id}/schools/${s.id}\`">`.

---

### 🟢 UX-034 — Student Dashboard: no chest number in "My registrations"

**File:** `resources/js/Pages/Admin/Portal/Student/Dashboard.vue`

The "My registrations" section does not show the student's assigned chest number. Chest numbers are only visible in the Fest Schedule section. Students expect to see their chest number on the registration entry itself.

**Fix:** Include `participants[0].chest_no` in the registrations serialisation and display it inline.

---

### 🟢 UX-035 — State Winners: no public share link

**File:** `resources/js/Pages/Admin/State/Kalotsav/Winners.vue`

The admin winners list has no "share" button that would copy a public URL to clipboard, unlike the item-level winner poster URLs that exist elsewhere.

**Fix:** Add a copy-to-clipboard icon per row with the URL `/fest/{event}/items/{item}/winners/{mark}/poster.svg`.

---

## Priority fix order

All items resolved. Original priority buckets:

- **Critical:** UX-001, UX-002, UX-006, UX-011
- **Before next event:** UX-003–UX-010
- **Sprint:** UX-012–UX-029
- **Polish:** UX-030–UX-036

---

## Files to change (quick reference)

| Finding | File |
|---------|------|
| UX-001 | `resources/js/Pages/Admin/Portal/Student/Dashboard.vue` |
| UX-002 | `resources/js/Pages/Admin/Portal/Student/McqExam.vue` + new API endpoint |
| UX-003 | `resources/js/Pages/Admin/Portal/Student/Dashboard.vue` (navItems) |
| UX-004 | `resources/js/Pages/Admin/Portal/Student/Profile.vue` + `StudentPortalController` |
| UX-005 | `resources/js/Pages/Admin/Portal/Student/McqExam.vue` |
| UX-006 | `resources/js/Pages/Admin/Portal/Judge/MarkEntry.vue` |
| UX-007/008 | `resources/js/Pages/Admin/Portal/FestOps/GateCheck.vue` + `FestGateCheckController` |
| UX-009 | `resources/js/Pages/Admin/Audit/Index.vue` + `PlatformAuditController` |
| UX-010 | `resources/js/Pages/Admin/State/Kalotsav/Winners.vue` + `StateKalotsavController` |
| UX-011 | `resources/js/Pages/Admin/Portal/Group/Dashboard.vue` |
| UX-012 | `resources/js/Pages/Admin/Tenants/Index.vue` + `TenantController::index()` |
| UX-013 | `resources/js/Pages/Admin/Dashboard.vue` |
| UX-014 | `resources/js/Pages/Admin/State/Dashboard.vue` + `StateAdminController` |
| UX-015 | `resources/js/Pages/Admin/StateRemittances/Index.vue` + controller |
| UX-016 | `resources/js/Pages/Admin/Billing/Index.vue` + `BillingController` |
| UX-017/018 | `resources/js/Pages/Admin/Sahodaya/Events/MarkEntry.vue` + `FestMarkController` |
| UX-019 | `resources/js/Pages/Admin/Sahodaya/Events/Results.vue` |
| UX-020 | `resources/js/Pages/Admin/Sahodaya/Events/Registrations.vue` |
| UX-021/022 | `resources/js/Pages/Admin/School/Students/Index.vue` + `StudentController` |
| UX-023 | `resources/js/Pages/Admin/School/Dashboard.vue` + `SchoolDashboardController` |
| UX-024 | `resources/js/Pages/Admin/School/Registration/Index.vue` |
| UX-025 | `resources/js/Pages/Admin/School/Events/Registration.vue` |
| UX-026 | `resources/js/Pages/Admin/Portal/Teacher/Dashboard.vue` |
| UX-027 | `resources/js/Pages/Admin/Portal/Exam/Dashboard.vue` |
| UX-028 | `resources/js/Pages/Admin/Portal/Student/McqExam.vue` |
| UX-029 | `resources/js/Pages/Admin/Portal/Judge/MarkEntry.vue` |
| UX-030/031 | `resources/js/Pages/Admin/Auth/*.vue` + auth controllers |
| UX-032 | `resources/js/Pages/Admin/Sahodaya/Events/Schedule.vue` + `FestScheduleController` |
| UX-033 | `resources/js/Pages/Admin/Sahodaya/Dashboard.vue` |
| UX-034 | `resources/js/Pages/Admin/Portal/Student/Dashboard.vue` + `StudentDashboardController` |
| UX-035 | `resources/js/Pages/Admin/State/Kalotsav/Winners.vue` |
