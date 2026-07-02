# Sahodaya Connect — Master System Prompt
> Paste this at the start of any new AI session to get full context immediately.

---

## What This System Is

**Sahodaya Connect** is a multi-tenant SaaS platform for CBSE school clusters (called "Sahodaya") in Kerala, India. A Sahodaya is an official cluster of 20–60 CBSE schools managed by a coordinator. The platform manages:

- Annual school membership and registration
- Competitive cultural/arts festivals (Kalolsavam)
- Sports meets
- MCQ-based competitive exams
- Teacher training programs
- Kids Fest and Teacher Fest
- Public CMS websites for each school and each Sahodaya

The product is sold as a service: each Sahodaya is a paying tenant. Schools within a Sahodaya are sub-tenants.

---

## Tech Stack

- **Backend:** Laravel 11, PHP 8.2, multi-tenancy via Stancl Tenancy
- **Frontend:** Inertia.js + Vue 3 (Composition API, `<script setup>`)
- **DB:** MySQL (some code was historically PostgreSQL — now MySQL-only)
- **Auth:** Laravel Sanctum + Spatie Permissions
- **File storage:** Laravel Storage (S3 or local)
- **Notifications:** In-app (`InAppNotification` model) + email via `NotificationService`
- **Audit:** `PlatformAuditLogger` writes to central `audit_logs` table
- **Activity feeds:** `FestEventActivityService` per-event feed

---

## Tenant Architecture

```
Central DB (superadmin)
│
├── Sahodaya tenant (e.g. "CBSE Sahodaya Ernakulam")
│   ├── sahodaya_admin, sahodaya_staff, various sub-roles
│   └── School sub-tenant (e.g. "St. Mary's CBSE School")
│       ├── school_admin, school_staff
│       └── Student/Teacher portal users
│
└── State Admin (cross-Sahodaya visibility)
```

Each Sahodaya has its own DB schema via Stancl. Schools share the Sahodaya's DB but are scoped by `tenant_id = school.id` or `school_id`.

---

## All Roles — Login & Portal Routing

| Role | Login URL | Panel / Portal | Access level |
|------|-----------|---------------|-------------|
| `superadmin` | `/login` | `/admin` | Full platform |
| `state_admin` | `/login` | `/admin` (state section) | Cross-Sahodaya read + remittances |
| `state_staff` | `/login` | `/admin` (read-only) | State read-only |
| `sahodaya_admin` | `/login` | `/sahodaya-admin/{id}` | Full cluster |
| `sahodaya_staff` | `/login` | `/sahodaya-admin/{id}` (permission-gated) | Assigned permissions |
| `registration_coordinator` | `/login` | `/sahodaya-admin/{id}` | fest.view + fest.registrations |
| `sahodaya_finance` | `/login` | `/sahodaya-admin/{id}` | fest.view + fest.finance + membership.view |
| `certificate_collector` | `/login` | `/sahodaya-admin/{id}` | fest.view + fest.certificates |
| `data_entry` | `/login` | `/sahodaya-admin/{id}` | fest.view + fest.manage + fest.marks |
| `event_coordinator` | `/login` | `/sahodaya-admin/{id}` | fest.view + fest.manage + fest.schedule + fest.settings |
| `mark_entry_admin` | `/login` | `/sahodaya-admin/{id}` | fest.view + fest.marks |
| `school_admin` | `/school-login` | `/school-admin/{id}` | Full school |
| `school_staff` | `/school-login` | `/school-admin/{id}` (permission-gated) | fest.view + website.view |
| `judge` | `/login` | `/portal/judge/{id}` | Mark entry for assigned items |
| `mark_entry_coordinator` | `/login` | `/portal/fest-coordinator/{id}` | Mark entry for all items |
| `fest_ops` | `/login` | `/portal/fest-ops/{id}` | Day-of: attendance, gate, stage, kitchen |
| `exam_controller` | `/login` | `/portal/exam/{id}` | MCQ: attendance + mark entry |
| `exam_staff` | `/login` | `/portal/exam/{id}` | MCQ: attendance only |
| `group_admin` | `/login` | `/portal/group/{id}` | Class group: students, schedule, admit cards |
| `house_admin` | `/login` | `/portal/house-admin/{id}` | House: students, ranking |
| `student` | `/login` | `/portal/student/{id}` | My registrations, MCQ exams, results |
| `teacher` | `/login` | `/portal/teacher/{id}` | Training, Teacher Fest, MCQ question banks |

**Important:** All login goes through `/login`. Laravel middleware redirects to the correct portal after auth. There is NO dedicated portal-only login page — students must know the main URL. Password reset exists but is not linked from the portal login UI.

---

## Event Types & Real-World Flows

### 1. Kalolsavam (CBSE Arts & Cultural Festival)

**Real-world context:** This is the main annual cultural competition. Items include classical dance, music, drama, art, literature, etc. Categorized by class group (LP = Classes 3–5, UP = 6–7, HS = 8–9, HSS = 10–12). Students can compete in limited items per stage type (onstage/offstage/group). Results qualify winners to Sahodaya level, then State.

**Flow:**
```
State creates state program (FestStateProgram)
  → propagates to all Sahodaya clusters
    → Sahodaya creates Sahodaya-level Kalotsav event
    → Sahodaya opens school round (template event)
      → Each school creates their own school-round event
        → School registers students to items
        → School or Sahodaya enters results
        → Winners promoted to Sahodaya event
      → Sahodaya reviews all school registrations, approves
      → Sahodaya event day: attendance → mark entry → results
      → Sahodaya promotes winners to State event
    → State event conducted at Kerala state level
```

**Key constraints:**
- Classes 1–2 → Kids Fest only, NOT Kalotsav
- Each student has strict limits: max 1 onstage individual + 1 onstage group (CKSC rules)
- Item codes (CKSC codes) link the same item across school/Sahodaya/State levels
- Results can be grades (A/A+/B/C) or positions (1st/2nd/3rd)
- Chest numbers are assigned after registration closes, before event day
- School submits school registration before Sahodaya deadline
- `FestParticipationPolicy` enforces per-student item limits

**Edge cases:**
- A student can be a performer in one item and a standby in another
- Schools sometimes forget to link their school event to the Sahodaya parent (`parent_event_id`) — if they don't, promotion chain fails
- Sahodaya admin can register on behalf of a school that can't use the system
- "Override locked registration" flag allows Sahodaya to approve registrations even after deadline

---

### 2. Sports Meet

**Real-world context:** Annual inter-school sports competition. Age groups: U8, U10, U11, U12, U14, U17, U19, Open. Each event is a specific athletic item (100m, long jump, shot put, etc.) in a specific age group + gender. Schools run their own internal meets first, then submit top-N winners to Sahodaya.

**Flow:**
```
Sahodaya creates sports program → creates sports events (school round + Sahodaya round)
  → School runs internal sports meet (registers students, enters results by measurement)
  → School submits top winners to Sahodaya (via FestQualificationService::submitSchoolSportsWinners)
    → Sahodaya approves school winner submissions
    → Sahodaya event day: attendance → measurement entry (time/distance/height) → auto-rank
    → House championship calculated (if house-based)
    → Athletic record tracking (FestAthleticRecordService)
    → Results published
SPORTS ENDS AT SAHODAYA LEVEL — no state promotion
```

**Key constraints:**
- Student age calculated against `sports_age_cutoff_date` on the event (default: Dec 31 of competition year)
- U14 = student must be UNDER 14 years on the cutoff date
- Open category = any student with a recorded DOB
- Gender must be recorded on student profile for sports registration
- Same student CAN compete in multiple age groups if they qualify (e.g. a U11 student qualifies for U12 and U14 too)
- `eligible_sports_groups` annotation on student shows all groups they qualify for
- Sports items use `measurement_value` + `measurement_unit` (e.g., "12.5" + "seconds"), not just grade/position

**Edge cases:**
- Schools may not have done a formal school meet — they just know who their best athletes are and submit them directly
- Age group cutoff date can be overridden per event (some Sahodaya clusters use Jan 1 instead of Dec 31)
- House championship needs all items marked before it can be calculated
- Records can be "cluster record", "school record", or "personal best"
- Open category items (no age restriction) need DOB but no age check

---

### 3. MCQ Competitive Exam

**Real-world context:** Sahodaya conducts MCQ-based competitive exams (e.g., Science Olympiad, Math Quiz). Conducted at Sahodaya level only. Exam is PHYSICALLY OFFLINE (students come to a center, take a paper-based exam) OR online via the student portal. Registration and mark entry are always online.

**Flow:**
```
Sahodaya creates MCQ exam (McqExam) with syllabus, eligibility, date
  → Teachers submit question banks via Teacher portal (TeacherMcqController)
  → Sahodaya reviews/approves question banks, sets final paper
  → School registers eligible students
    → Fee calculated per school (variable slab or fixed)
    → School uploads fee payment proof
    → Sahodaya approves fee → hall tickets generated
  → Exam day (OFFLINE): students come to center
    OR (ONLINE): student logs in, sees timer, answers questions
  → Exam controller (ExamOpsController) marks attendance per student
  → Marks entered: either via ExamOpsController (offline) OR auto-collected from StudentMcqController
  → Sahodaya runs McqRankingService::rankExam() → rank column on McqMark
  → Sahodaya publishes results → schools notified (currently MISSING trigger)
  → Students view results + rank via Student portal
```

**Key constraints:**
- Only ONE online attempt allowed per student per exam (session tracked in `McqExamSession`)
- Online exam has a timer (`expires_at` on session) — if browser closes, session stays as `started` forever (auto-submit cron is MISSING)
- Hall ticket required before attendance can be marked (but hall ticket check has a bypass for online-only exams)
- Fee approval is required before hall tickets can be generated
- Ranking is per-exam across all registered schools/students

**Edge cases:**
- School has 0 active students in DB but has students in the annual submission → use `SchoolYearStudentCount`
- Eligibility config on exam may restrict by class group — school can currently register any student (filter MISSING)
- Teacher may contribute questions that the Sahodaya rejects — teacher should see rejection reason
- If exam is cancelled, all registrations need to be refunded — no refund flow exists
- Multiple schools in same room → exam controller manages all schools at once

---

### 4. Training Program

**Real-world context:** Sahodaya organizes teacher training workshops (in-person). Schools register their teachers. Sometimes free, sometimes paid.

**Flow:**
```
Sahodaya creates training program → opens registration
  → School registers specific teachers
    → If paid: school uploads fee proof → Sahodaya approves
  → Program conducted (offline)
    → Sahodaya marks teacher attendance
  → Sahodaya generates participation certificates
  → Teacher views + downloads certificate via Teacher portal
```

**Key constraints:**
- A training session may have max capacity — registration closes when full
- Certificate only generated after Sahodaya marks attendance as "attended"
- Training fee is per-participant (per teacher), not per school

---

### 5. Kids Fest

Same flow as Kalotsav but only for students in Pre-KG through Class 2. Items are grouped into "bands" (Baby band = KG/LKG/UKG, Toddler band = Classes 1–2). No state level — ends at Sahodaya.

---

### 6. Teacher Fest

Same flow as Kalotsav but participants are teachers, not students. No student eligibility checks. Teacher picks from items open to teachers. Can go to state level.

---

## Membership & Annual Registration

**Real-world context:** Every school must register annually with the Sahodaya and pay a membership fee. This unlocks access to all events. Without valid membership, the school cannot participate in Kalotsav, Sports, etc.

**Flow:**
```
Sahodaya opens registration window (SahodayaRegistrationWindow with open/close dates)
  → School admin navigates to Annual Registration
  → Multi-step flow:
      Step 1: Submit school basic data
      Step 2: Student data (full records OR counts-only, depending on profile setting)
      Step 3: Teacher data
      Step 4: Fee calculation shown
      Step 5: Upload payment proof
  → Sahodaya reviews: approves data → approves payment → assigns membership number
  → School status becomes "member" → can participate in events
```

**Key constraints:**
- Window enforcement: schools should NOT be able to start registration outside the window (enforcement currently MISSING — needs to be added to the `begin()` action)
- Fee type: `fixed` (flat fee) or `variable_by_student_count` (slab-based on student count)
- Student data mode: `full_records` (individual student rows uploaded) OR `counts_only` (class-wise totals)
- Membership number is assigned on approval, used on all certificates and records
- Renewal: prior-year approved registration shown as context when starting current year

**Edge cases:**
- School submits counts that don't match their actual student roster → Sahodaya flags discrepancy
- Partial payment: school uploads one receipt, rejected, uploads another → multiple `MembershipPayment` rows, only latest is active
- School joins mid-year (new CBSE school) → Sahodaya manually approves and sets membership
- Receipt numbers must be unique → race condition if two approvals happen simultaneously

---

## Portal User Provisioning

**How students get portal access:**
1. School admin goes to Students list
2. Clicks "Provision portal access" for a student
3. Sets email + password
4. `StudentPortalProvisioner::provision()` creates User record with `student` role
5. Student can now log in at `/login` → redirected to `/portal/student/{id}`
6. **NO welcome email is currently sent** (trigger MISSING)
7. **NO self-service password reset link** visible on portal login

**How teachers get portal access:** Same flow via `TeacherPortalProvisioner`.

**How judges/exam controllers get access:** Sahodaya admin creates them directly in user management panel.

---

## Known Bugs & Issues

### Still Open (not yet fixed)

| # | Bug | Location | Impact |
|---|-----|----------|--------|
| B1 | Receipt number race condition — no DB lock on `receipt_next_number` | `SahodayaProfile` model or `FeeReceiptService` | Duplicate receipt numbers under concurrent load |
| B2 | Registration window never enforced in `begin()` write action | `AnnualRegistrationController::begin()` | Schools bypass Sahodaya's window dates |
| B3 | MCQ results published notification never fires | `McqExamController::publishResults()` | Schools never know results are ready |
| B4 | Expired online MCQ sessions stay `started` forever | No scheduled command | Students who close browser are stuck |
| B5 | MCQ eligibility config not enforced on school registration page | `McqRegistrationController` | Wrong-class students can be registered |
| B6 | `student.portal.provisioned` notification never sent | `StudentPortalProvisioner` | Students don't get welcome email |
| B7 | `teacher.portal.provisioned` notification never sent | `TeacherPortalProvisioner` | Teachers don't get welcome email |
| B8 | Fest schedule published notification not triggered | `FestScheduleController` | Schools not notified when schedule is ready |
| B9 | Chest numbers revealed notification not triggered | `FestChestNumberController` | Schools not notified |
| B10 | Deadline reminder cron exists (`FestEventNotifier::registrationDeadlineReminder`) but no scheduled command calls it | `Console/Kernel.php` | No deadline reminders ever sent |
| B11 | Student dashboard shows school-level `festFees` | `StudentDashboardController` | Wrong data for student — school-level fees not relevant to a student |
| B12 | `McqController::hub()` delegates to `McqRegistrationController::index()` — not a real hub | `McqController` | No MCQ hub page for school admin |
| B13 | School dashboard has no MCQ summary, training summary, or action queue | `SchoolAdmin/DashboardController` | School admin can't see pending actions at a glance |
| B14 | `AuditLogCatalog` has no `mcq` category — all MCQ audit actions fall into `system` | `AuditLogCatalog.php` | MCQ audit trail indistinguishable in log viewer |
| B15 | No audit logging in MCQ exam create/update/publish | `McqExamController` | No audit trail for MCQ lifecycle |
| B16 | No audit logging in school-side fest registration submit/withdraw | `SchoolAdmin/FestRegistrationController` | No trail for school submissions |
| B17 | No audit logging in `ExamOpsController` (MCQ attendance/marks) | `ExamOpsController` | No trail for exam day actions |
| B18 | No audit logging in `JudgeDashboardController` (mark entry) | `JudgeDashboardController` | No trail for judge marks |
| B19 | No audit logging in `StudentMcqController` start/submit | `StudentMcqController` | No trail for student exam sessions |

### Already Fixed (in codebase)

- Sports open-category registration — correctly allows all students with a DOB (no bug)
- `parent_event_id` — `FestProgramController::store()` auto-links to active Sahodaya event
- Sports never promoted to state — `FestQualificationService::resolveNextLevelEvent()` returns null for sports
- `MembershipFeeCalculator` — correctly reads active student count
- Ledger pagination — uses `paginate(50)` (not old `limit(100)`)
- Ledger MySQL compat — uses `DB::getDriverName()` conditional for `DATE_FORMAT` vs `to_char`
- Ledger financial year — uses `FinancialYear::currentId()` (April-March)
- `FestEventFeeResolver::normalizeAgeGroupFees()` — now accepts `?string $tenantId` to use DB defaults
- `FestEventSettingsController` — passes `$this->sahodaya->id` to `normalizeEventFeeSettings()`
- `FestProgramController::storeMark()` — method body restored, `festack_event_items` typo fixed to `fest_event_items`
- `Sahodaya/Events/Registrations.vue` — `sportsGroupedRegistrations` and `genderLabel()` added to script

---

## Incomplete Features (Flows With No UI Yet)

| Feature | Missing piece |
|---------|-------------|
| Sports winner submission from school | No dedicated "Submit my winners to Sahodaya" UI. Uses generic registration page. |
| MCQ bulk student registration by class | One-at-a-time only. No "register entire class" action. |
| MCQ bulk hall ticket PDF | Only per-student in student portal. No school-level bulk download. |
| Exam controller bulk attendance import | One student at a time. No CSV import. |
| State admin per-Sahodaya results view | No page showing all cluster results side-by-side for a state program. |
| State winners export | No PDF/CSV export of state-level winners. |
| MCQ activity log / audit page | No per-exam activity history in Sahodaya admin. |
| School-branded portal login URL | No `/s/{code}/login` that shows school logo + context. |
| Student direct notifications | Results/schedule notifications only go to school_admin, not directly to student portal users. |
| Teacher portal circulars view | Teacher portal has no circulars/notice board page. |
| Judge portal schedule view | Judge portal has no schedule or appeals view — only dashboard + mark entry. |

---

## Key Service Classes (What They Do)

| Service | Purpose |
|---------|---------|
| `FestRegistrationEligibilityService` | Validates student eligibility for a fest item (age, gender, class group, DOB) |
| `FestMarkSaveService` | Saves a mark, auto-resolves grade from score, checks athletic records |
| `FestQualificationService` | Promotes winners from one level to the next; builds school sports winner candidates |
| `FestEventFeeResolver` | Normalizes fee settings JSON; resolves per-item and per-level fees |
| `FestItemSyncService` | Copies catalog items from Sahodaya/State to a school event |
| `McqExamSessionService` | Manages online exam sessions: start, answer, submit, expire check |
| `McqRankingService` | Computes and assigns rank across all students in an exam |
| `McqSchoolFeeService` | Calculates school fee based on registered student count |
| `MembershipFeeCalculator` | Calculates and applies annual membership fee (fixed or slab-variable) |
| `MembershipRegistrationWindowService` | Returns reason why registration is blocked (window closed, already registered, etc.) |
| `PlatformAuditLogger` | Writes to `audit_logs` table (central DB); methods: `.log()`, `.festEvent()`, `.festCatalog()` |
| `FestEventActivityService` | Per-event activity feed for Sahodaya admin UI |
| `NotificationService` | Sends in-app + email notifications via template slugs |
| `FestEventNotifier` | Higher-level notification triggers for fest events (registration approved/rejected, results, etc.) |
| `StudentPortalProvisioner` | Creates a portal-access User account for a student |
| `TeacherPortalProvisioner` | Same for teachers |
| `FestSportsAgeGroup` | Static helpers for sports age group logic (eligible groups, cutoff dates, labels, fees) |
| `FestSportsAgeGroupRegistry` | DB-backed per-tenant registry of age groups; seeded from config on first access |
| `FestParticipationPolicyService` | Applies participation limits (max onstage/offstage/group per student) |
| `LedgerPostingService` | Posts double-entry journal entries to the ledger |
| `FinancialYear` (Support) | Returns current financial year (April–March), falls back to academic year |

---

## Key Models (What They Represent)

| Model | What it stores |
|-------|---------------|
| `FestEvent` | An event instance (Sahodaya Kalotsav 2025, School Sports Meet, etc.) with status, level_round, event_type |
| `FestStateProgram` | The state-level program definition that propagates to clusters |
| `FestEventItem` | An individual competition item within a FestEvent (e.g., "Classical Dance — Girls — UP") |
| `FestRegistration` | A school's registration of student(s) for an item in an event |
| `FestParticipant` | A performer or standby within a registration |
| `FestMark` | Result for a participant in an item (grade, position, score, measurement) |
| `FestQualification` | Records that a participant qualified from one event to the next |
| `FestSchedule` | Event schedule — item → venue → date/time slot |
| `FestChestNumber` | Chest number assigned to a participant |
| `McqExam` | An MCQ competitive exam with syllabus, eligibility, date, fee |
| `McqRegistration` | A student's registration for an MCQ exam |
| `McqExamSession` | Online exam session (started_at, expires_at, status, answers JSON) |
| `McqMark` | Student's score + rank in an MCQ exam |
| `McqSchoolFee` | Per-school fee record for an MCQ exam |
| `Registration` | Annual membership registration (one per school per academic year) |
| `MembershipPayment` | Fee payment proof upload for annual registration |
| `SahodayaRegistrationWindow` | Open/close dates for annual registration per Sahodaya per year |
| `SahodayaProfile` | Sahodaya settings (fee type, receipt numbering, data mode, etc.) |
| `AccountHead` | Ledger account head (income, expense, asset, liability) |
| `LedgerTransaction` | Double-entry ledger transaction |
| `Student` | A student in a school with class, DOB, gender, reg_no |
| `Tenant` | Both Sahodaya clusters AND schools (school.parent_id = sahodaya.id) |
| `InAppNotification` | In-app notification (bell icon) for a user |
| `TrainingProgram` | A teacher training workshop with date, capacity, fee |
| `PlatformAuditLog` | Central audit trail entry |

---

## Real-World Edge Cases to Always Handle

1. **School with no students in DB:** Membership fee estimate must fall back to prior year's count from `SchoolYearStudentCount`.
2. **Sahodaya with no active state program:** Kalotsav can still be run as a cluster-only event without state propagation.
3. **Student with no DOB:** Cannot register for sports. Must show clear error.
4. **Same student registered for item + standby in same event:** Allowed. Picker shows them in both lists.
5. **Registration after deadline:** Should be blocked UNLESS Sahodaya admin enables "override locked registration".
6. **Winner promotion when target item doesn't exist:** `FestQualificationService::matchingItem()` tries item_code → title → category+type. If none match, promotion records are created but no auto-registration is made.
7. **Duplicate MCQ sessions:** If student refreshes the exam page mid-exam, must resume the same session (not create a new one).
8. **Fee proof re-upload:** School re-uploads after rejection. Previous entry marked `superseded`, not deleted.
9. **Sahodaya admin registers on behalf of school:** `FestRegistrationController::storeOnBehalf()` with `auto_approve` flag.
10. **Kids Fest band logic:** A student in Class 1 is in "Toddler" band. A student in LKG is in "Baby" band. The band determines which items they can compete in — not class number directly.
11. **Sports open category + gender specific:** An item can be "Open age group, Girls only" — student just needs a DOB and gender=female.
12. **Teacher Fest participants are teachers:** All eligibility checks for student class/age do NOT apply. Any active teacher can register.
13. **MCQ exam: offline vs online:** Both modes use the same `McqExam` model. `McqExamSession` only exists for online. Offline: `ExamOpsController` enters marks directly (no session). The `mode` flag on the exam or event determines which flow.
14. **Membership fee slab matching:** A school with 450 students matches the slab `min_students=401, max_students=500`. If no slab matches (e.g., 5000 students and max slab is 1000), fee returns ₹0 — Sahodaya must add a slab or override manually.
15. **Multi-school judge:** A judge user may be assigned items from multiple schools in the same event. Their portal shows ALL assigned items regardless of which school submitted them.
16. **Chest number visibility:** Chest numbers are hidden until Sahodaya "reveals" them (`FestChestNumberController::reveal()`). Before reveal, only Sahodaya admin can see them.
17. **Schedule clash detection:** A student registered for two items in the same time slot. `FestSchedule` has clash detection but it's advisory — Sahodaya can override.
18. **House championship scoring:** Each student belongs to a house. All approved registrations for approved students contribute points to the house. House admin sees live ranking.
19. **State program propagation failure:** If a new Sahodaya is added after state program creation, they must manually receive a propagation via `StateFestProgramController::propagate()`.
20. **Annual registration for new school joining mid-year:** No prior year registration exists. `is_renewal` flag = false. Fee may be prorated manually (no automatic proration).

---

## Notification Templates That Exist

Stored in `NotificationTemplatesSeeder`. Reference these slugs in code:

```
fest.registration.approved        fest.registration.rejected
fest.registration.withdrawn       fest.results.published
fest.promotion.completed          fest.record.broken
fest.registration.deadline        fest.schedule.published (TRIGGER MISSING)
fest.chest_numbers.revealed       (TRIGGER MISSING)
mcq.results.published             (TRIGGER MISSING)
mcq.registration.confirmed        (TEMPLATE MAY BE MISSING)
mcq.fee.approved                  (TEMPLATE MAY BE MISSING)
mcq.fee.rejected                  (TEMPLATE MAY BE MISSING)
training.registration.confirmed
circular.published
state.remittance.created          state.remittance.verified
state.remittance.rejected
student.portal.provisioned        (TEMPLATE + TRIGGER MISSING)
teacher.portal.provisioned        (TEMPLATE + TRIGGER MISSING)
membership.payment.approved       (TEMPLATE MAY BE MISSING)
membership.payment.rejected       (TEMPLATE MAY BE MISSING)
sports.winners.received           (TEMPLATE MAY BE MISSING)
```

---

## Implementation Plan Reference

See `IMPLEMENTATION_PLAN.md` for the full 9-phase fix plan.
See `FULL_AUDIT_REPORT.md` for the complete bug + gap inventory with scores.
See `EVENT_PAGES_PLAN.md` for the dedicated event pages/menus architecture plan.
See `SAHODAYA_FIX_PLAN.md` for the original 7-part fix plan.
