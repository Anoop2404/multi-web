# UI/UX Audit Prompt — Sahodaya Connect

Paste this entire prompt into a new Claude session to run a deep UI/UX audit across every role and page.

---

## CONTEXT

You are auditing **Sahodaya Connect** — a multi-tenant SaaS platform for CBSE school clusters (Sahodaya Complexes) in Kerala. It is built with Laravel 11 + Inertia.js + Vue 3 (`<script setup>`) + Tailwind CSS.

**Architecture:**
- Platform (super admin) → Sahodaya Complex (sahodaya admin) → Schools (school admin/principal) → Portal users (students, teachers, judges, ops staff)
- Stancl multi-tenancy: each Sahodaya is a tenant; schools are sub-tenants
- 261 Vue pages across 13 role tiers

**Workspace root:** `/Users/neoo/laravel/multi-web`  
**Vue pages:** `resources/js/Pages/`  
**Controllers:** `app/Http/Controllers/`  
**Routes:** `routes/web.php`

---

## YOUR TASK

Perform a **deep UI/UX audit** of every role and every page in this project. For each area, read the actual Vue files and controllers, then document:

1. **What exists** — describe the current UI accurately
2. **What is broken or missing** — functional gaps, confusing flows, missing feedback, dead ends
3. **What should be improved** — concrete, specific changes (not generic advice)

Do NOT give vague suggestions like "improve the user experience." Be specific: name the component, the prop, the field, the missing button, the wrong label.

---

## ROLE TIERS TO AUDIT

Work through each tier in order. For each role, read its pages, then its controller(s), then write findings.

---

### TIER 1 — Super Admin (`/admin/*`)

**Pages:** `resources/js/Pages/Admin/Dashboard.vue`, `Admin/Tenants/`, `Admin/MasterData/`, `Admin/Billing/`, `Admin/Audit/`, `Admin/SkinPresets/`, `Admin/Builder/`

**Audit these questions:**
- Can super admin create a Sahodaya + immediately set up its admin user in one flow, or are these separate steps with no guidance?
- Is there a clear status indicator per tenant (active/inactive, database migrated, admin assigned)?
- `Admin/Billing/Index.vue` — what billing data is shown? Is pagination/filtering present?
- `Admin/Audit/Index.vue` — is the audit log filterable by tenant, action category, user, date range?
- `Admin/Builder/` (Sections/Nav/Theme/Widgets/Footer) — is it clear which tenant the builder is editing? Can you switch tenants mid-session?
- `Admin/SkinPresets/Index.vue` — do preset previews render? Can you apply a preset to a tenant?
- Are there empty-state messages when no tenants/schools exist?

---

### TIER 2 — State Admin (`/admin/state-*`)

**Pages:** `Admin/State/Dashboard.vue`, `Admin/State/Kalotsav/`, `Admin/State/Sports/`, `Admin/State/Users/`, `Admin/StatePrograms/`, `Admin/StateRemittances/`

**Audit these questions:**
- `Admin/State/Dashboard.vue` — what summary data is visible? Is there a cross-Sahodaya aggregate?
- `Admin/State/Kalotsav/Index.vue` → `ProgramDetail.vue` → `Results.vue` → `Winners.vue` — is the navigation between these four pages clear? Can you go back without losing context?
- `Admin/State/Kalotsav/Winners.vue` — are winners displayed with school, category, grade, position? Is there a public share link or export?
- `Admin/StateRemittances/Index.vue` — is the remittance proof viewable inline or does it open a new tab? What happens after verify/reject — does the list update without a full reload?
- `Admin/StatePrograms/Show.vue` — what actions are available here? Is the cascade-to-state flow obvious?

---

### TIER 3 — Sahodaya Admin (`/sahodaya-admin/{tenantId}/*`)

**Pages:** `Admin/Sahodaya/Dashboard.vue` and all sub-sections below.

#### 3a. Dashboard
- Read `Admin/Sahodaya/Dashboard.vue` and `app/Http/Controllers/SahodayaAdmin/SahodayaDashboardController.php`
- What KPIs are shown? Are they current-year only? Is there a quick-action bar?
- Are pending items (unreviewed registrations, pending change requests, pending substitutions) surfaced prominently?

#### 3b. Events Hub
- **Pages:** `Admin/Sahodaya/Events/Index.vue`, `Events/ProgramIndex.vue`, `Events/Overview.vue`, `Events/Activity.vue`
- Is the difference between `Index.vue` (event list) and `ProgramIndex.vue` clear to users?
- `Events/Overview.vue` — does it show event progress (% registered, % marks entered, % results published)?
- `Events/Activity.vue` — is the activity feed real-time or stale?

#### 3c. Event Items
- **Pages:** `Events/Items/List.vue`, `Events/Items/Master.vue`, `Events/ItemHeads.vue`
- Can items be reordered by drag-and-drop or only by `display_order` number input?
- Is item eligibility config (class_group, gender, age_group) editable inline or only via a modal?
- `Events/Items/Master.vue` — is this a catalog browser? Can you bulk-add items from catalog to an event?

#### 3d. Registrations
- **Pages:** `Events/Registrations.vue`, `Events/Registrations/Import.vue`
- Is there a per-school registration status summary (who has registered vs not)?
- Can Sahodaya admin approve/reject individual registrations? What happens to fee status after rejection?
- `Registrations/Import.vue` — what file formats are accepted? Is there error reporting per row?

#### 3e. Schedule & Clashes
- **Pages:** `Events/ItemSchedule.vue`, `Events/ClashReview.vue`
- `ItemSchedule.vue` — can items be scheduled via drag-and-drop or only form inputs?
- `ClashReview.vue` — are clashes shown with participant names, schools, and conflicting time slots? Is there a one-click "resolve" action?

#### 3f. Mark Entry
- **Pages:** `Events/MarkEntry.vue`, `Events/MarksImport.vue`, `Events/Judges.vue`
- `MarkEntry.vue` — does grade auto-derive from score for kalotsav? Does measurement auto-rank for sports?
- Is there a "mark entry completion" progress bar per item (X/Y participants entered)?
- `Judges.vue` — can judges be bulk-assigned to multiple items at once?

#### 3g. Results & Leaderboard
- **Pages:** `Events/Appeals.vue`, `Events/Championship.vue`, `Events/LeaderboardHub.vue`, `Events/AthleticRecords.vue`
- `LeaderboardHub.vue` — is the public live scoreboard link prominently shown?
- `Appeals.vue` — what states does an appeal move through? Is the resolution workflow clear?
- `Events/AthleticRecords.vue` — can records be manually set vs auto-detected from marks?

#### 3h. Chest Numbers & ID Cards
- **Pages:** `Events/ChestNumbers.vue`, `Events/IdCards/Index.vue`
- `ChestNumbers.vue` — can numbers be bulk-revealed per school? Is reveal status visible per participant?
- `Events/IdCards/Index.vue` — is bulk download (ZIP) available? Can you filter by school or item?

#### 3i. Finance
- **Pages:** `Events/FeeLedger.vue`, `Events/Finance/Index.vue`, `Events/Fees.vue`
- Is there a per-school fee breakdown showing what's paid vs outstanding?
- Can Sahodaya admin issue manual receipts or mark fees as paid offline?

#### 3j. Certificates & Attendance
- **Pages:** `Events/Certificates.vue`, `Events/CertificateSearch.vue`, `Events/Attendance.vue`
- Can certificates be generated per school in bulk? Is the download format clear (ZIP/PDF)?
- `Events/Attendance.vue` — is attendance taken per item or per participant? Can it be imported?

#### 3k. MCQ Exams
- **Pages:** `Admin/Sahodaya/Mcq/` (read all files)
- Is the exam creation flow (create → add questions → set eligibility → publish → results) guided with clear steps?
- Can question banks be shared across exams?
- Is the live exam supervision view available during online exams?

#### 3l. Students & Schools Management
- **Pages:** `Admin/Sahodaya/Students/`, `Admin/Sahodaya/Schools/`
- Can Sahodaya admin view all students across all schools? Is there filtering by class, gender, school?
- `Students/ChangeRequests.vue` — is the two-level approval flow (school approved → Sahodaya review) visible and clear?

#### 3m. Membership
- **Pages:** `Admin/Sahodaya/Membership/` (read all files)
- Is the registration window (open/close dates for add + edit separately) easy to configure?
- Can Sahodaya admin grant per-school overrides with expiry dates?
- Is the fee payment status per school clearly shown?

#### 3n. Other Sahodaya Sections
- `Admin/Sahodaya/Circulars/Index.vue` — can circulars be sent to specific schools or all schools?
- `Admin/Sahodaya/OfficeBearers/` — is designation + photo upload in one form?
- `Admin/Sahodaya/PublicContent/` — how does public website content link to the builder?
- `Admin/Sahodaya/DisplayScreens/Index.vue` — what is shown on the display screen? Is it used for live events?
- `Admin/Sahodaya/AcademicYears/Index.vue` — is it clear which year is "current"? Can you switch years?

---

### TIER 4 — School Admin / Principal / Event Coordinator (`/school-admin/{tenantId}/*`)

**Pages:** `Admin/School/` (all sub-directories)

#### 4a. Registration Hub
- **Pages:** `Admin/School/Registration/Index.vue`, `Registration/Students.vue`, `Registration/Teachers.vue`, `Registration/Counts.vue`, `Registration/Payment.vue`, `Registration/Profile.vue`
- Is the annual registration flow guided (step 1: profile → step 2: students → step 3: teachers → step 4: payment)?
- `Registration/Counts.vue` — does it show current class-wise student count vs last year?
- `Registration/Payment.vue` — is the fee breakdown shown before payment? What payment methods are supported?
- Is the registration window status (open/closed/locked) clearly shown on first load?

#### 4b. Students
- **Pages:** `Admin/School/Students/Index.vue`, `Students/Create.vue`, `Students/BulkCreate.vue`, `Students/ChangeRequests.vue`, `Students/PendingChangeRequests.vue`
- `Students/Index.vue` — can students be filtered by class, gender, status? Is photo upload inline?
- `Students/Create.vue` — is the portal login provisioning option visible here? What happens when you tick "create login"?
- `Students/BulkCreate.vue` — is there a CSV template download? What column headers are required?
- `Students/ChangeRequests.vue` — can a school admin see all change requests they've submitted and their current approval status (pending school / pending Sahodaya / approved / rejected)?
- `Students/PendingChangeRequests.vue` — this is for Principal/VP review of requests submitted by school staff. Is the reviewer role clearly indicated?

#### 4c. Teachers
- **Pages:** `Admin/School/Teachers/Index.vue`
- Is portal login provisioning for teachers available from this page?
- Can teachers be filtered by subject, designation, class teacher assignment?

#### 4d. Staff (Event Coordinators etc.)
- **Pages:** `Admin/School/Staff/Index.vue`, `Staff/Create.vue`, `Staff/Edit.vue`
- Is it clear that "staff" here are event-based roles (event coordinator) vs permanent school staff?
- Can a staff member be assigned to specific events from the create/edit page?

#### 4e. Events (Fest, Sports, Kids Fest)
- **Pages:** `Admin/School/Events/Index.vue`, `Events/FestHub.vue`, `Events/ProgramHub.vue`, `Events/Registration.vue`, `Events/Programs.vue`, `Events/ProgramShow.vue`, `Events/FestDay.vue`, `Events/Results.vue`, `Events/Qualifiers.vue`
- `Events/FestHub.vue` — is this the main entry point? Does it show all active events the school is registered in?
- `Events/Registration.vue` — how does a school register a student for an item? Is eligibility checked before submission (age group, gender, class)?
- `Events/ProgramShow.vue` — does this show the school's registered participants, their chest numbers, and schedule slots?
- `Events/FestDay.vue` — what is shown on event day? Schedule, participant list, results as they come in?
- `Events/Results.vue` — does it show the school's category-wise result summary and individual item results?
- `Events/Qualifiers.vue` — which students qualified to next level? Is next-level event info shown?

#### 4f. School Event Reports
- **Pages:** `Admin/School/Events/Reports.vue`, `ReportsHub.vue`, `ReportDisciplineParticipation.vue`, `ReportFeeSummary.vue`, `ReportIdCards.vue`, `ReportItemWise.vue`, `ReportMarkEntryStatus.vue`, `ReportParticipation.vue`, `ReportRegistrationRegister.vue`, `ReportResultsSummary.vue`, `ReportScheduleClashes.vue`, `ReportStudentWise.vue`, `ReportTeacherWise.vue`
- Are all these reports accessible from a single hub page or spread across menus?
- Do all reports have a "Download as CSV/Excel" option?
- `ReportScheduleClashes.vue` — does it link to the substitution request or clash request form?

#### 4g. Substitution & Clash Requests
- **Pages:** `Admin/School/Events/SubstitutionRequests.vue`, `Events/ClashRequests.vue`
- `SubstitutionRequests.vue` — is the form easy to use? Can you search for participants by name or reg no? Is the approval status shown?
- `ClashRequests.vue` — does it auto-populate conflicting schedule slots from the school's registered participants?

#### 4h. Sports
- **Pages:** `Admin/School/Sports/MyEvent.vue`, `Sports/SubmitWinners.vue`
- `MyEvent.vue` — does this show the school's sports registrations grouped by age group and discipline?
- `SubmitWinners.vue` — when is this used? Is it for school-level round before Sahodaya?

#### 4i. MCQ
- **Pages:** `Admin/School/Mcq/Index.vue`, `Mcq/ExamDetail.vue`
- `Index.vue` (MCQ Hub) — does it show exam availability, registration status per student, upcoming exams, and results in one view?
- `ExamDetail.vue` — can school admin register multiple students at once? Is eligibility check (class, gender) shown before registration?

#### 4j. Training
- **Pages:** `Admin/School/Training/Index.vue`
- What is shown here? Can school admin register teachers for training programs?

#### 4k. Other School Pages
- `Admin/School/Users/Index.vue` — does this list all users linked to this school (admin, principal, VP, event coordinators)? Can passwords be reset from here?
- `Admin/School/Settings/Index.vue` — what settings are available? School prefix? Contact details? Academic year?
- `Admin/School/Payments/Index.vue` — payment history with receipt download?
- `Admin/School/Notifications/Index.vue` — notification center with read/unread status?
- `Admin/School/Houses/Index.vue` — colour house assignment of students?
- `Admin/School/Events/House.vue` — house points view during a fest?
- `Admin/School/Events/Appeals.vue` — appeal submission flow: is fee payment step before submission? Is the reason field guided?
- `Admin/School/Events/Catering.vue` — food coupon ordering: is the deadline enforced?

---

### TIER 5 — Student Portal (`/portal/student/*`)

**Pages:** `Admin/Portal/Student/Dashboard.vue`, `Student/Profile.vue`, `Student/FestRegistrations.vue`, `Student/McqExam.vue`, `Student/McqResult.vue`, `Student/SportsResults.vue`

**Audit these questions:**
- `Dashboard.vue` — what does a student see first? Is there a list of upcoming events they're registered in? MCQ exams? Pending actions?
- `Profile.vue` — can students edit their name/photo during the edit window? Is the lock status explained with a clear message and a "Request change" CTA when locked?
- `FestRegistrations.vue` — does it show event name, item name, chest number, schedule slot, and result grade all in one view?
- `McqExam.vue` — what is the MCQ exam-taking flow? Is there a countdown timer? Can answers be saved draft? Is the submit confirmation clear?
- `McqResult.vue` — does it show score, rank among participants, and grade? Is this visible before admin publishes results?
- `SportsResults.vue` — does it show position, measurement value, and whether they qualified to next level?
- Are all portal pages mobile-responsive (students primarily use phones)?
- Is there a logout button easily accessible?

---

### TIER 6 — Teacher Portal (`/portal/teacher/*`)

**Pages:** `Admin/Portal/Teacher/Dashboard.vue`, `Teacher/QuestionBanks.vue`, `Teacher/QuestionBankShow.vue`

**Audit these questions:**
- `Dashboard.vue` — what does a teacher see? Assigned training programs? Fest participation history?
- `Teacher/QuestionBanks.vue` — can teachers create question banks for MCQ exams? What question types are supported?
- `Teacher/QuestionBankShow.vue` — can questions be added/edited inline? Is there bulk import from CSV?
- Are teachers able to see their school's event schedule and results?

---

### TIER 7 — Judge Portal (`/portal/judge/*`)

**Pages:** `Admin/Portal/Judge/Dashboard.vue`, `Judge/MarkEntry.vue`

**Audit these questions:**
- `Dashboard.vue` — what items is this judge assigned to? Are venue, time slot, and date clearly shown?
- `Judge/MarkEntry.vue` — is it clear which item and category the judge is entering marks for? Can judges enter A+/A/B/C grade + position? Is there a submit confirmation? Can marks be edited after submit?
- Is there any offline-capable mode in case of poor connectivity at the venue?

---

### TIER 8 — Fest Event Ops (`/portal/fest-ops/*`)

**Pages:** `Admin/Portal/FestOps/Dashboard.vue`, `FestOps/Event.vue`, `FestOps/Registrations.vue`, `FestOps/GateCheck.vue`, `FestOps/Attendance.vue`, `FestOps/Stage.vue`, `FestOps/Coordinator.vue`, `FestOps/Certificates.vue`, `FestOps/Appeals.vue`, `FestOps/Kitchen.vue`, `FestOps/ParticipantSearch.vue`

**Audit these questions:**
- `GateCheck.vue` — is this a QR code scanner? What happens when an unknown QR is scanned? Is there a manual lookup fallback?
- `Stage.vue` — what does the stage coordinator see? Current performing participant, next in queue, scores?
- `ParticipantSearch.vue` — can you search by name, chest number, or reg no? Is the result shown with photo for identity verification?
- `Kitchen.vue` — is food coupon consumption tracked here? Is there a count of remaining coupons?
- `Attendance.vue` — can attendance be marked per participant or per school registration?
- `Appeals.vue` — can ops staff accept/reject appeals on-site?

---

### TIER 9 — Fest Mark Coordinator (`/portal/mark-coordinator/*`)

**Pages:** `Admin/Portal/FestCoordinator/Dashboard.vue`, `FestCoordinator/MarkEntry.vue`

**Audit these questions:**
- What is the difference between Fest Coordinator and Judge portal? (Judge = individual judge entering marks for their item; Mark Coordinator = staff who enters marks on behalf of judges for multiple items)
- `Dashboard.vue` — which items is the coordinator responsible for?
- `FestCoordinator/MarkEntry.vue` — can the coordinator see previously submitted judge scores? Can they override?

---

### TIER 10 — Group Admin (`/portal/group/*`)

**Pages:** `Admin/Portal/Group/Dashboard.vue`, `Group/FestAdmitCards.vue`, `Group/FestClashes.vue`, `Group/FestRegistrations.vue`, `Group/FestSchedule.vue`, `Group/Students.vue`

**Audit these questions:**
- What is a "Group Admin" in this context? (A group coordinator managing a specific sub-group of students in a fest)
- `FestRegistrations.vue` — can the group admin register new participants or only view existing?
- `FestClashes.vue` — how are clashes presented? Is there a resolution action available?
- `FestAdmitCards.vue` — can admit cards be printed per student or only downloaded as ZIP?

---

### TIER 11 — House Admin (`/portal/house/*`)

**Pages:** `Admin/Portal/HouseAdmin/Dashboard.vue`, `HouseAdmin/Ranking.vue`, `HouseAdmin/Registrations.vue`, `HouseAdmin/Students.vue`

**Audit these questions:**
- `Dashboard.vue` — which house is shown? Is the house points total prominent?
- `Ranking.vue` — is this a live ranking of all houses or a summary after results?

---

### TIER 12 — Exam Supervisor Portal (`/portal/exam/*`)

**Pages:** `Admin/Portal/Exam/Dashboard.vue`, `Exam/Attendance.vue`, `Exam/MarkEntry.vue`, `Exam/Supervision.vue`

**Audit these questions:**
- `Supervision.vue` — can the supervisor see which students have started, submitted, or had their session expire?
- `Exam/Attendance.vue` — is this hall attendance before the exam starts?
- `Exam/MarkEntry.vue` — is this for offline MCQ exams where a supervisor enters answers after? How does this differ from online auto-submission?

---

### TIER 13 — Public Portal (`/fest/*`)

**Pages:** `resources/js/Pages/` — look for public-facing pages (non-authenticated)

**Audit these questions:**
- Is there a public homepage showing upcoming events for this Sahodaya?
- Is the public scoreboard filterable by category (LP/UP/HS/HSS)?
- Are item-wise results readable on mobile without horizontal scrolling?
- Is the winner poster / shareable graphic linked from results?
- Is the live scoreboard auto-refreshing or requires manual reload?
- Is the public schedule searchable by participant name or school?
- Is there a public certificate verification page (`/certificates/verify/{uuid}`)?

---

### AUTH FLOWS (all roles)

- **Pages:** `Admin/Auth/Login.vue`, `Auth/SchoolLogin.vue`, `Auth/PortalLogin.vue`, `Auth/SuperadminLogin.vue`, `Auth/ChangePassword.vue`, `Auth/ForgotPassword.vue`, `Auth/ResetPassword.vue`
- Is it clear which login page to use for which role? (Super admin vs Sahodaya admin vs School admin vs Portal user)
- `PortalLogin.vue` — is this for students and teachers? Does it have "forgot password" for students who don't know their username?
- `Auth/ChangePassword.vue` — is this the forced change on first login? Is the password strength requirement shown?
- `SchoolLogin.vue` — is the school selector (which Sahodaya?) easy to use if a user has accounts in multiple Sahodayas?

---

## AUDIT OUTPUT FORMAT

For each section, write your findings in this structure:

```
### [Section Name]

**Current state:** [What the UI currently shows/does]

**Issues found:**
- [Issue 1]: Specific problem with specific component/page/field
- [Issue 2]: ...

**Recommended changes:**
- [Change 1]: Specific action (e.g., "Add a `registration_window_status` banner at the top of `Registration/Index.vue` showing open/locked/closed with dates")
- [Change 2]: ...

**Priority:** HIGH / MEDIUM / LOW
```

---

## CONSTRAINTS

- Read actual Vue files and controllers — do not guess
- Where a page is empty or unimplemented, note it explicitly  
- Flag any page that shows raw IDs instead of names in the UI
- Flag any form that has no validation feedback
- Flag any list page without search, filter, or pagination
- Flag any action (delete, approve, reject, publish) without a confirmation dialog
- Flag any empty state that shows a blank screen instead of a helpful message
- Note any inconsistency in button labels, terminology, or navigation patterns across roles
- Note any mobile UX issues — several roles (student portal, ops, gate check) are used on phones

---

## PRIORITY FOCUS AREAS

If you need to prioritise, focus on these first (highest user impact):

1. **Student portal** — used by hundreds of students per school
2. **School admin registration flow** — critical annual process
3. **Fest registration + results** — most-used Sahodaya admin feature
4. **Student edit change requests** — complex two-level approval needs clear UI
5. **MCQ exam-taking experience** — time-pressured, must be near-zero-friction
6. **Public scoreboard** — most public-facing feature, high visibility
7. **Judge mark entry** — used under time pressure at live events
