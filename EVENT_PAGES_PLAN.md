# Dedicated Event Pages, Menus & Dashboards Plan

> Each event type needs its own dedicated hub, sub-pages, and navigation — not the current
> shared generic `FestRegistrationController` / `programs/{program}` pattern.
>
> Three admin levels: **School Admin** · **Sahodaya Admin** · **State Admin**
> Six event types: **Kalotsav** · **Sports Meet** · **MCQ Exam** · **Training** · **Kids Fest** · **Teacher Fest**

---

## Current State vs Target

| Now | Target |
|-----|--------|
| `programs/{program}` → same controller for all event types | Dedicated controller per event type per role |
| Generic `FestRegistrationController::index()` serves kalotsav + sports + kids + teacher | Each event type has its own page tree |
| Sports and Kalotsav share same registration UI | Sports has measurement/age-group specific UI |
| MCQ is a separate controller but lacks hall tickets, attendance, exam-day pages | MCQ has its own full page set |
| Sahodaya dashboard has no event-type specific quick actions | Each event type has its own program hub |
| State admin has basic state-programs list only | State has per-event-type program management |

---

## Navigation Structure (Sidebar Menus)

### School Admin Sidebar

```
Dashboard
├── My School
│   ├── Students
│   ├── Teachers
│   └── Annual Registration (Membership)
│
├── Kalotsav                          ← dedicated menu section
│   ├── My School Kalotsav            ← school-run internal event
│   └── Sahodaya Kalotsav             ← register at cluster level
│
├── Sports Meet                       ← dedicated menu section
│   ├── My School Sports              ← school sports meet management
│   └── Sahodaya Sports               ← submit winners to Sahodaya
│
├── Kids Fest                         ← dedicated menu section
│   └── Sahodaya Kids Fest
│
├── Teacher Fest                      ← dedicated menu section
│   └── Sahodaya Teacher Fest
│
├── MCQ Exams                         ← dedicated menu section
│   ├── Available Exams
│   └── My Registrations
│
├── Training Programs                 ← dedicated menu section
│   ├── Available Programs
│   └── My Registrations
│
└── Payments & Receipts
```

---

### Sahodaya Admin Sidebar

```
Dashboard
│
├── Membership
│   ├── Schools
│   ├── Data Review
│   ├── Payment Verification
│   ├── Reports
│   └── Settings
│
├── Kalotsav                          ← dedicated section
│   ├── Program Dashboard
│   ├── Events (all rounds)
│   ├── School Rounds
│   ├── Catalog / Items Master
│   └── Reports & Exports
│
├── Sports Meet                       ← dedicated section
│   ├── Program Dashboard
│   ├── Events
│   ├── Age Group Settings
│   ├── Athletic Records
│   ├── House Championship
│   └── Reports & Exports
│
├── Kids Fest                         ← dedicated section
│   ├── Program Dashboard
│   ├── Events
│   ├── Catalog
│   └── Reports
│
├── Teacher Fest                      ← dedicated section
│   ├── Program Dashboard
│   ├── Events
│   └── Reports
│
├── MCQ Exams                         ← dedicated section
│   ├── Exam Dashboard
│   ├── Question Banks
│   ├── Exams List
│   └── Reports
│
├── Training Programs                 ← dedicated section
│   ├── Programs List
│   └── Registrations
│
├── Finance / Ledger
└── Settings
```

---

### State Admin Sidebar

```
Dashboard
│
├── Sahodaya Clusters
│
├── Kalotsav (State)                  ← dedicated section
│   ├── State Programs
│   ├── Cluster Reports
│   ├── State Event Management
│   └── Results & Winners
│
├── Sports (State Visibility)         ← read-only, sports ends at Sahodaya
│   └── Cluster Results
│
├── MCQ (State)                       ← if state conducts MCQ in future
│   └── (placeholder)
│
├── Membership Overview
├── State Remittances
└── Settings
```

---

---

## 1. Kalotsav

### 1A — School Admin: Kalotsav Pages

#### Page: My School Kalotsav Hub
**Route:** `GET /school-admin/{tenantId}/kalotsav`
**Controller:** `SchoolAdmin/KalotsavController::hub()`
**Shows:**
- Active school-level kalotsav events created by this school
- Active Sahodaya-level kalotsav events open for registration
- Quick stats: total items registered, approved, pending, results available
- Button: "Create School Kalotsav Event" (if Sahodaya has opened school-level option)
- Button: "Go to Sahodaya Kalotsav Registration"

---

#### Page: School Kalotsav Event (school-run internal event)
**Route:** `GET /school-admin/{tenantId}/kalotsav/my-events/{event}`
**Controller:** `SchoolAdmin/KalotsavController::myEvent()`
**Tabs:**
- **Overview** — event details, status, link to Sahodaya parent event (if linked)
- **Items** — items for this event (inherited from Sahodaya catalog + school custom)
- **Registration** — register students for items, class-group eligibility enforced
- **Marks** — enter results for each item/student (grade/position)
- **Results** — publish school-level results; view qualifiers
- **Link to Sahodaya** — link this event to the Sahodaya parent event for promotion

---

#### Page: Sahodaya Kalotsav Registration
**Route:** `GET /school-admin/{tenantId}/kalotsav/sahodaya/{event}`
**Controller:** `SchoolAdmin/KalotsavController::sahodayaEvent()`
**Tabs:**
- **Register** — register students per item, grouped by On Stage / Off Stage / Group
- **My Participants** — list of registered students with chest numbers, schedule slot
- **Fees** — event fee status, upload payment proof
- **Results** — view published results (when available)
- **Certificates** — download participation and winner certificates

---

### 1B — Sahodaya Admin: Kalotsav Pages

#### Page: Kalotsav Program Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}/kalotsav`
**Controller:** `SahodayaAdmin/KalotsavProgramController::dashboard()`
**Shows:**
- Stats cards: Active Events / Total Registrations / Schools Participating / Results Published
- Events list grouped by: School Rounds | Sahodaya Round | State Round
- Quick actions: Create Event / View Catalog / Open Registration / Publish Results
- Recent activity feed (registrations submitted, fees paid, appeals filed)
- School participation matrix: which schools have registered, fee paid, results viewed

---

#### Page: Kalotsav Event Detail (sub-pages via tabs)
**Route:** `GET /sahodaya-admin/{tenantId}/kalotsav/events/{event}/{tab?}`
**Tabs (each a dedicated sub-page):**

| Tab | Route Segment | Content |
|-----|--------------|---------|
| Overview | `/overview` | Event settings, lifecycle checklist, status controls |
| Items | `/items` | Item list by category (on-stage/off-stage/group), enable/disable per item |
| School Rounds | `/school-rounds` | Linked school events, results published per school, promote-all button |
| Registrations | `/registrations` | All school registrations, approve/reject, bulk approve, substitutions |
| Chest Numbers | `/chest-numbers` | Generate, reveal, print, green room view |
| Schedule | `/schedule` | Stage schedule, auto-generate, publish |
| Judges | `/judges` | Judge assignments per item |
| Marks | `/marks` | Mark entry per item/participant, grade + position |
| Attendance | `/attendance` | Mark attendance on event day |
| Results | `/results` | Scoreboard by school, publish, promote winners to state |
| Qualifications | `/qualifications` | Who promoted, revoke promotion, state event link |
| Certificates | `/certificates` | Generate, bulk download, collect |
| Appeals | `/appeals` | Open appeals, resolve, disqualify/reinstate |
| Finance | `/finance` | School event fees, payment proofs, approve/reject, invoices |
| Reports | `/reports` | Participation, item-wise, school-wise, admit cards, ID cards, exports |
| Settings | `/settings` | Dates, venue, fee config, participation policy, grade config, point rules |

---

#### Page: Kalotsav Catalog (master item bank)
**Route:** `GET /sahodaya-admin/{tenantId}/kalotsav/catalog`
**Sub-pages:**
- `catalog/master` — full item list across all categories
- `catalog/on-stage` — on-stage items
- `catalog/off-stage` — off-stage items
- `catalog/group` — group/team items
- `catalog/assign` — assign catalog items to a specific event

---

#### Page: School Rounds Management
**Route:** `GET /sahodaya-admin/{tenantId}/kalotsav/school-rounds`
**Controller:** `SahodayaAdmin/KalotsavProgramController::schoolRounds()`
**Shows:**
- All school-created kalotsav events in the cluster
- Per school: event name, items count, results published (Y/N), linked to parent (Y/N)
- Bulk link action: link unlinked school events to the Sahodaya parent event
- Promote all button: pull winners from all published school rounds

---

### 1C — State Admin: Kalotsav Pages

#### Page: State Kalotsav Programs
**Route:** `GET /admin/kalotsav`
**Sub-pages:**
- `programs` — list state Kalotsav programs, create new
- `programs/{program}` — program detail: propagation status per Sahodaya, conduct levels
- `programs/{program}/propagate` — push to Sahodaya clusters
- `programs/{program}/results` — aggregate state-level results from all Sahodayas
- `programs/{program}/winners` — state winners list, export

---

---

## 2. Sports Meet

### 2A — School Admin: Sports Pages

#### Page: Sports Hub
**Route:** `GET /school-admin/{tenantId}/sports`
**Controller:** `SchoolAdmin/SportsMeetController::hub()`
**Shows:**
- My School Sports Meet (school-created event) — status, items, results
- Sahodaya Sports Meet (open for winner registration) — registration status, deadline
- Quick stats: items registered at school level, qualified for Sahodaya, results
- Age groups summary (which U-groups the school has registered winners in)

---

#### Page: School Sports Meet (school-run event)
**Route:** `GET /school-admin/{tenantId}/sports/my-event/{event}`
**Tabs:**
- **Overview** — event details, age cutoff date, linked Sahodaya event
- **Items** — items per age group (U14 Boys / U14 Girls / U17 / Open etc.)
- **Register Students** — register students per item, DOB-based age group eligibility enforced
- **Mark Entry** — enter measurement values (time, distance, height) per item
- **Auto-Rank** — auto-assign positions by measurement (asc/desc per event type)
- **Results** — view ranked results per age group and item
- **Winners** — list of 1st/2nd/3rd per item → submit to Sahodaya
- **Link to Sahodaya** — link this event as school round for a Sahodaya sports event

---

#### Page: Sahodaya Sports Registration
**Route:** `GET /school-admin/{tenantId}/sports/sahodaya/{event}`
**Tabs:**
- **Register Winners** — register school-level winners per item+age group
  - Shows only students who won at school level (if qualification gate enabled)
  - Shows age group eligibility per student
- **My Entries** — all submitted registrations with item, age group, student name
- **Fees** — sports event fee, upload proof
- **Schedule** — published schedule (when available)
- **Results** — published results when available

---

### 2B — Sahodaya Admin: Sports Pages

#### Page: Sports Program Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}/sports`
**Controller:** `SahodayaAdmin/SportsProgramController::dashboard()`
**Shows:**
- Stats: Events / Schools Registered / Items / Athletic Records Broken
- Active sports events with registration deadline countdown
- Age group participation summary (how many in U14/U17/U19/Open)
- School participation matrix (registered Y/N per school, fee paid Y/N)
- Athletic records at risk (top performers near existing records)
- Quick actions: Create Sports Event / Manage Age Groups / View Records

---

#### Page: Sports Event Detail
**Route:** `GET /sahodaya-admin/{tenantId}/sports/events/{event}/{tab?}`
**Tabs — sports-specific additions beyond the common tabs:**

| Tab | Content |
|-----|---------|
| Overview | Event settings, age cutoff date, house points config |
| Age Groups | Active age groups for this event, per-group item list |
| Items | Items grouped by Age Group + Gender; enable/disable |
| School Rounds | Linked school sports events, promote-all winners |
| Registrations | School registrations per item per age group; approve/reject |
| Marks & Measurements | Enter `measurement_value` + `measurement_unit` per participant per item |
| Auto-Rank | Rank by measurement for each item (button per item: "Rank by Time" / "Rank by Distance") |
| Athletic Records | Current records per item; records broken in this event highlighted |
| House Points | Points table per house, championship standings |
| Results | Scoreboard by school, overall championship, publish |
| Certificates | Generate for winners + record breakers |
| Finance | School fees, payment proofs |
| Reports | Age-group-wise, item-wise, school-wise, measurement reports, exports |
| Settings | Dates, age cutoff, house config, fee config, point rules |

---

#### Page: Age Group Settings
**Route:** `GET /sahodaya-admin/{tenantId}/sports/age-groups`
**Controller:** Already exists as `SportsAgeGroupController` — move into sports section nav
**Shows:**
- Configured age groups (U8/U10/U11/U12/U14/U17/U19/Open)
- Per group: label, under-age value, default fee, active/inactive
- Add/edit/remove/reset-defaults

---

#### Page: Athletic Records Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}/sports/records`
**Controller:** `SahodayaAdmin/AthleticRecordsDashboardController::index()`
**Shows:**
- All current records per item per age group
- Records broken this year (linked to FestRecordBreak)
- Record holder history per item
- Export: full records register PDF/Excel

---

#### Page: House Championship
**Route:** `GET /sahodaya-admin/{tenantId}/sports/championship`
**Shows:**
- Overall house points standings across all sports events this year
- Points breakdown per event per house
- Individual championship points (top students by total points)
- Live update as marks are entered and published

---

### 2C — State Admin: Sports Pages

#### Page: Sports Results Viewer (read-only, sports ends at Sahodaya)
**Route:** `GET /admin/sports`
**Shows:**
- All Sahodaya cluster sports results (published only)
- Filter by: cluster, age group, item, gender
- No promotion mechanism (sports is Sahodaya-terminal)

---

---

## 3. MCQ Exam

### 3A — School Admin: MCQ Pages

#### Page: MCQ Hub
**Route:** `GET /school-admin/{tenantId}/mcq`
**Controller:** `SchoolAdmin/McqController::hub()`
**Shows:**
- Upcoming exams (available for registration)
- Registered exams (students already registered with count)
- Completed exams with results (if published)
- Total fee due / paid summary

---

#### Page: MCQ Exam Detail
**Route:** `GET /school-admin/{tenantId}/mcq/{exam}`
**Tabs:**
- **Register** — register individual students or bulk-register by class
  - Eligibility filter by class group (if exam has `eligibility_config`)
  - Shows already-registered vs available students
- **Students** — all registered students, hall ticket numbers, seat numbers
- **Hall Tickets** — download individual or bulk hall tickets PDF
- **Fee** — MCQ school fee (aggregate), upload payment proof
- **Results** — marks/grades per student when published, school-level ranking, rank among all cluster schools
- **Toppers** — school's top performers

---

### 3B — Sahodaya Admin: MCQ Pages

#### Page: MCQ Exam Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}/mcq`
**Controller:** `SahodayaAdmin/McqDashboardController::index()`
**Shows:**
- All exams this year with status
- Stats: Total Exams / Total Registrations / Results Published / Fees Pending
- Pending actions: fee approvals, results to publish

---

#### Page: MCQ Exam Detail
**Route:** `GET /sahodaya-admin/{tenantId}/mcq/{exam}/{tab?}`
**Tabs:**

| Tab | Content |
|-----|---------|
| Overview | Exam settings: date, duration, questions, pass mark, fee |
| Question Banks | Linked question banks, add/remove |
| Questions | Questions list from linked banks (read view) |
| Registrations | All school registrations; school-wise count; fee status per school |
| Hall Tickets | Generate hall ticket numbers; assign halls and seats; bulk print |
| Attendance | Mark attendance on exam day per student (present/absent) |
| Exam Session | Monitor live exam: who has started, submitted, remaining time |
| Mark Entry | Enter correct/wrong/unanswered per student OR upload answer sheet CSV |
| Ranking | Auto-ranked list by score; ties handled; view per school or overall |
| Results | Publish results; leaderboard top-N; school-wise comparison |
| Fee Approvals | School-level MCQ fee proofs: approve/reject per school |
| Staff | Assign exam staff (invigilators, supervisors) |
| Reports | Participation report, result report, topper list, school comparison, exports |
| Settings | Edit exam details, eligibility config, fee config, status |

---

#### Page: Question Banks
**Route:** `GET /sahodaya-admin/{tenantId}/mcq/question-banks`
**Sub-pages:**
- `question-banks` — list all banks
- `question-banks/{bank}` — view questions in bank
- `question-banks/{bank}/questions` — add/edit/delete questions
- `question-banks/{bank}/import` — bulk import questions from CSV/Excel

---

### 3C — State Admin: MCQ
*(Placeholder — MCQ is Sahodaya-level only currently. State visibility read-only if needed.)*

---

---

## 4. Training Programs

### 4A — School Admin: Training Pages

#### Page: Training Hub
**Route:** `GET /school-admin/{tenantId}/training`
**Controller:** `SchoolAdmin/TrainingController::hub()`
**Shows:**
- Available training programs (open for registration)
- My registrations (teachers registered + fee status)
- Completed programs with attendance records

---

#### Page: Training Program Detail
**Route:** `GET /school-admin/{tenantId}/training/{program}`
**Tabs:**
- **Details** — program description, dates, venue, fee, eligibility
- **Register Teachers** — select teachers to enroll
- **Fee** — school-level training fee, upload proof
- **Attendance** — teacher attendance records (post-event)
- **Certificate** — download training certificates (if issued)

---

### 4B — Sahodaya Admin: Training Pages

#### Page: Training Programs Hub
**Route:** `GET /sahodaya-admin/{tenantId}/training`
**Controller:** `SahodayaAdmin/TrainingProgramController::hub()`
**Shows:**
- All training programs with status
- Stats: Programs / Registered Teachers / Schools Participating / Revenue
- Pending fee approvals alert

---

#### Page: Training Program Detail
**Route:** `GET /sahodaya-admin/{tenantId}/training/{program}/{tab?}`
**Tabs:**

| Tab | Content |
|-----|---------|
| Overview | Program settings: title, dates, venue, fee, capacity |
| Registrations | All teacher registrations by school; approve/reject |
| Attendance | Mark attendance per teacher on program day |
| Fee Approvals | School-level training fee proofs; approve/reject |
| Certificates | Generate and issue training certificates |
| Reports | School-wise participation, attendance summary, exports |
| Settings | Edit program details, fee, eligibility |

---

---

## 5. Kids Fest

### 5A — School Admin: Kids Fest Pages

#### Page: Kids Fest Hub
**Route:** `GET /school-admin/{tenantId}/kids-fest`
**Controller:** `SchoolAdmin/KidsFestController::hub()`
**Shows:**
- Open Sahodaya Kids Fest events
- Registration status per event per band
- Stats: registered students per band

---

#### Page: Kids Fest Event Registration
**Route:** `GET /school-admin/{tenantId}/kids-fest/{event}`
**Tabs:**
- **Register** — register students grouped by band (Pre-KG / KG / Class 1 / Class 2)
  - Students auto-sorted to correct band by class
- **My Participants** — registered students, items, schedule
- **Fees** — event fee, upload proof
- **Results** — published results per band

---

### 5B — Sahodaya Admin: Kids Fest Pages

#### Page: Kids Fest Program Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}/kids-fest`
**Shows:**
- Same structure as Kalotsav dashboard but band-oriented
- Stats per band: Pre-KG / KG / Class 1 / Class 2

---

#### Page: Kids Fest Event Detail
**Route:** `GET /sahodaya-admin/{tenantId}/kids-fest/events/{event}/{tab?}`
**Tabs — same as Kalotsav but:**
- Items grouped by **Band** instead of stage type
- No chest number concept (simpler)
- Registrations filtered by band eligibility
- Schedule grouped by band

---

---

## 6. Teacher Fest

### 6A — School Admin: Teacher Fest Pages

#### Page: Teacher Fest Hub
**Route:** `GET /school-admin/{tenantId}/teacher-fest`
**Controller:** `SchoolAdmin/TeacherFestController::hub()`
**Shows:**
- Open Teacher Fest events
- Teacher registration status
- Quick register: select teacher + item

---

#### Page: Teacher Fest Event Registration
**Route:** `GET /school-admin/{tenantId}/teacher-fest/{event}`
**Tabs:**
- **Register** — select teacher, select item, submit registration
- **My Registrations** — all teacher-item registrations, status
- **Fees** — event fee
- **Results** — published results

---

### 6B — Sahodaya Admin: Teacher Fest Pages

#### Page: Teacher Fest Program Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}/teacher-fest`

---

#### Page: Teacher Fest Event Detail
**Route:** `GET /sahodaya-admin/{tenantId}/teacher-fest/events/{event}/{tab?}`
**Tabs — same as Kalotsav but:**
- Participants are teachers, not students
- No class group filtering
- No chest number typically (optional)
- Marks: grade-based (A/B/C)

---

---

## 7. Shared Dashboards

### School Admin Main Dashboard
**Route:** `GET /school-admin/{tenantId}`
**Widgets:**
- Membership status card (registration status, fee due, reg number)
- Active events across all programs (any program with open registration)
- Pending payments summary (total due across membership + events + MCQ + training)
- Recent results (any program results published in last 30 days)
- Upcoming exam dates (MCQ, training, fest events)
- Student count / teacher count quick stats

---

### Sahodaya Admin Main Dashboard
**Route:** `GET /sahodaya-admin/{tenantId}`
**Widgets:**
- **Action Queue** (prominent, top of page):
  - N membership data submissions awaiting review
  - N membership payments awaiting verification
  - N fest fee proofs awaiting approval
  - N MCQ fee proofs awaiting approval
  - N fest registrations awaiting approval
  - N open appeals
- **Program Status Cards** (one per active event type):
  - Kalotsav: events open / registrations / results pending
  - Sports: events open / registrations / records broken
  - MCQ: exams upcoming / registrations / results pending
  - Training: programs open / teachers registered
- **Finance Summary**: total collected this year by category (membership / kalotsav / sports / MCQ / training)
- **School Activity**: which schools are active, which haven't registered yet

---

### State Admin Main Dashboard
**Route:** `GET /admin` (or `/admin/dashboard`)
**Widgets:**
- Total Sahodaya clusters
- State programs active (Kalotsav state programs propagated)
- Cluster results received vs pending
- State remittances: pending / submitted / verified amounts
- Upcoming state events

---

---

## Implementation Plan

### Phase 1 — Navigation restructure (no new features, just proper routing)
Separate program routes per event type instead of generic `programs/{program}`:

```
Before: /school-admin/{id}/programs/kalotsav
        /school-admin/{id}/programs/sports-meet
        /school-admin/{id}/programs/kids-fest
        /school-admin/{id}/programs/teacher-fest

After:  /school-admin/{id}/kalotsav/...
        /school-admin/{id}/sports/...
        /school-admin/{id}/kids-fest/...
        /school-admin/{id}/teacher-fest/...
        /school-admin/{id}/mcq/...
        /school-admin/{id}/training/...
```

Same restructure for sahodaya-admin routes.
- Old routes redirect 301 to new ones (no broken links)
- Sidebar nav updated to show dedicated sections

**Files:**
- `routes/web.php` — new route groups per event type
- New controllers: `SchoolAdmin/KalotsavController`, `SchoolAdmin/SportsMeetController`, etc.
- Existing controllers refactored into event-specific ones
- Sidebar nav component updated

---

### Phase 2 — Dedicated dashboards per event + role

**School Admin dashboards:**
- `SchoolAdmin/KalotsavController::hub()` — Kalotsav hub with school+Sahodaya events
- `SchoolAdmin/SportsMeetController::hub()` — Sports hub
- `SchoolAdmin/KidsFestController::hub()` — Kids Fest hub
- `SchoolAdmin/TeacherFestController::hub()` — Teacher Fest hub
- `SchoolAdmin/McqController::hub()` — MCQ hub (refactor existing)
- `SchoolAdmin/TrainingController::hub()` — Training hub (refactor existing)

**Sahodaya Admin dashboards:**
- `SahodayaAdmin/KalotsavProgramController::dashboard()` — Kalotsav program dashboard
- `SahodayaAdmin/SportsProgramController::dashboard()` — Sports program dashboard
- `SahodayaAdmin/KidsFestProgramController::dashboard()` — Kids Fest program dashboard
- `SahodayaAdmin/TeacherFestProgramController::dashboard()` — Teacher Fest dashboard
- `SahodayaAdmin/McqDashboardController::index()` — MCQ dashboard (refactor existing)
- `SahodayaAdmin/TrainingProgramController::hub()` — Training dashboard

---

### Phase 3 — Sports-specific pages

New pages that don't exist yet:
1. **Auto-rank by measurement** — `POST /sahodaya-admin/{id}/sports/events/{event}/items/{item}/auto-rank`
2. **Athletic Records Dashboard** — `GET /sahodaya-admin/{id}/sports/records`
3. **House Championship across events** — `GET /sahodaya-admin/{id}/sports/championship`
4. **School sports: winner submission UI** — school sees their event results and submits to Sahodaya
5. **Measurement marks entry UI** — dedicated mark entry for sports showing value + unit input

---

### Phase 4 — MCQ-specific pages

New pages that don't exist yet:
1. **Hall Ticket Generation** — bulk generate, assign halls/seats, print PDF
2. **Attendance marking** — `POST .../mcq/{exam}/attendance` (individual + bulk import)
3. **Exam session monitor** — live view of who started/submitted
4. **Bulk mark import** — upload answer sheet CSV, auto-calculate correct/wrong
5. **Ranking page** — auto-rank after publish, show leaderboard with school filter
6. **MCQ school-level fee** — aggregate fee per school per exam (replaces per-student proofs)

---

### Phase 5 — Sahodaya main dashboard action queue

Upgrade `DashboardController::index()` to return:
- Consolidated pending action counts across all modules
- Program status cards per active event type
- Finance summary widget
- School activity summary

---

### Phase 6 — State admin event pages

New state admin pages:
1. `GET /admin/kalotsav` — State Kalotsav programs hub
2. `GET /admin/kalotsav/{program}` — Program detail with propagation status
3. `GET /admin/kalotsav/{program}/results` — Aggregate results from all clusters
4. `GET /admin/kalotsav/{program}/winners` — State winners list + export
5. `GET /admin/sports` — Read-only view of all cluster sports results

---

## New Controllers to Create

### School Admin
| Controller | Replaces / New |
|-----------|---------------|
| `SchoolAdmin/KalotsavController` | Refactor from `FestRegistrationController` (kalotsav slice) |
| `SchoolAdmin/SportsMeetController` | Refactor from `FestRegistrationController` (sports slice) + new school sports mgmt |
| `SchoolAdmin/KidsFestController` | Refactor from `FestRegistrationController` (kids_fest slice) |
| `SchoolAdmin/TeacherFestController` | Refactor from `FestRegistrationController` (teacher_fest slice) |
| `SchoolAdmin/McqController` | Refactor from `McqRegistrationController` + add new pages |
| `SchoolAdmin/TrainingController` | Refactor from `TrainingRegistrationController` |

### Sahodaya Admin
| Controller | Replaces / New |
|-----------|---------------|
| `SahodayaAdmin/KalotsavProgramController` | New — program-level dashboard + school rounds |
| `SahodayaAdmin/SportsProgramController` | New — program-level dashboard + records + championship |
| `SahodayaAdmin/KidsFestProgramController` | New |
| `SahodayaAdmin/TeacherFestProgramController` | New |
| `SahodayaAdmin/McqDashboardController` | New — dashboard + question banks |
| `SahodayaAdmin/AthleticRecordsDashboardController` | New — club all records in one view |

### State Admin
| Controller | New |
|-----------|-----|
| `Admin/KalotsavStateController` | New — state program management for Kalotsav |
| `Admin/SportsResultsController` | New — read-only cluster results view |

---

## New Frontend Pages to Create

### School Admin (`resources/js/Pages/School/`)
```
Kalotsav/
  Hub.jsx                  ← kalotsav entry point
  MyEvent.jsx              ← school-run kalotsav
  SahodayaEvent.jsx        ← register at Sahodaya kalotsav

Sports/
  Hub.jsx                  ← sports entry point
  MyEvent.jsx              ← school sports meet management
  MyEventMarks.jsx         ← measurement entry
  MyEventResults.jsx       ← results + winner list
  SahodayaEvent.jsx        ← submit winners to Sahodaya sports

KidsFest/
  Hub.jsx
  Event.jsx

TeacherFest/
  Hub.jsx
  Event.jsx

Mcq/
  Hub.jsx                  ← refactor existing
  ExamDetail.jsx           ← per-exam: register, hall tickets, results
  HallTickets.jsx          ← print view

Training/
  Hub.jsx
  ProgramDetail.jsx
```

### Sahodaya Admin (`resources/js/Pages/Sahodaya/`)
```
Kalotsav/
  Dashboard.jsx            ← program dashboard
  SchoolRounds.jsx         ← all school events
  EventDetail/             ← per-event tabs (refactor existing Events/*)

Sports/
  Dashboard.jsx
  AgeGroups.jsx            ← moved from standalone page
  Records.jsx              ← athletic records dashboard
  Championship.jsx         ← house championship cross-event
  EventDetail/             ← per-event tabs with sports-specific additions

KidsFest/
  Dashboard.jsx
  EventDetail/

TeacherFest/
  Dashboard.jsx
  EventDetail/

Mcq/
  Dashboard.jsx
  ExamDetail/
    Overview.jsx
    QuestionBanks.jsx
    Registrations.jsx
    HallTickets.jsx
    Attendance.jsx
    MarkEntry.jsx
    Ranking.jsx
    Results.jsx
    FeeApprovals.jsx

Training/
  Dashboard.jsx
  ProgramDetail/
```

### State Admin (`resources/js/Pages/State/` or `resources/js/Pages/Admin/`)
```
Kalotsav/
  Index.jsx                ← state programs list
  ProgramDetail.jsx
  Results.jsx
  Winners.jsx

Sports/
  Results.jsx              ← read-only
```

---

## Route Summary (new structure)

```php
// School Admin — Kalotsav
Route::prefix('kalotsav')->group(function () {
    Route::get('/', [KalotsavController::class, 'hub'])->name('kalotsav.hub');
    Route::get('/my-events', [KalotsavController::class, 'myEvents'])->name('kalotsav.my-events');
    Route::post('/my-events', [KalotsavController::class, 'createMyEvent'])->name('kalotsav.my-events.store');
    Route::get('/my-events/{event}/{tab?}', [KalotsavController::class, 'myEvent'])->name('kalotsav.my-event');
    Route::get('/sahodaya/{event}/{tab?}', [KalotsavController::class, 'sahodayaEvent'])->name('kalotsav.sahodaya-event');
    Route::post('/sahodaya/{event}/register', [KalotsavController::class, 'register'])->name('kalotsav.register');
    Route::post('/sahodaya/{event}/payment', [KalotsavController::class, 'uploadPayment'])->name('kalotsav.payment');
    Route::post('/sahodaya/registrations/{registration}/withdraw', [KalotsavController::class, 'withdraw']);
});

// School Admin — Sports
Route::prefix('sports')->group(function () {
    Route::get('/', [SportsMeetController::class, 'hub'])->name('sports.hub');
    Route::get('/my-events/{event}/{tab?}', [SportsMeetController::class, 'myEvent'])->name('sports.my-event');
    Route::post('/my-events/{event}/marks', [SportsMeetController::class, 'saveMark']);
    Route::post('/my-events/{event}/items/{item}/auto-rank', [SportsMeetController::class, 'autoRank']);
    Route::get('/sahodaya/{event}/{tab?}', [SportsMeetController::class, 'sahodayaEvent'])->name('sports.sahodaya-event');
    Route::post('/sahodaya/{event}/register', [SportsMeetController::class, 'register']);
});

// School Admin — MCQ
Route::prefix('mcq')->group(function () {
    Route::get('/', [McqController::class, 'hub'])->name('mcq.hub');
    Route::get('/{exam}/{tab?}', [McqController::class, 'exam'])->name('mcq.exam-detail');
    Route::post('/{exam}/register', [McqController::class, 'register']);
    Route::post('/{exam}/register-bulk', [McqController::class, 'bulkRegister']);
    Route::get('/{exam}/hall-tickets', [McqController::class, 'hallTickets']);
    Route::get('/{exam}/hall-tickets/pdf', [McqController::class, 'hallTicketsPdf']);
    Route::post('/{exam}/fee', [McqController::class, 'uploadFee']);
});

// Sahodaya Admin — Kalotsav
Route::prefix('kalotsav')->group(function () {
    Route::get('/', [KalotsavProgramController::class, 'dashboard'])->name('kalotsav.dashboard');
    Route::get('/school-rounds', [KalotsavProgramController::class, 'schoolRounds']);
    Route::post('/school-rounds/link', [KalotsavProgramController::class, 'linkSchoolRound']);
    Route::get('/events/{event}/{tab?}', [KalotsavEventController::class, 'show'])->name('kalotsav.event');
    // all event sub-actions remain same as existing /events/{event}/... routes
});

// Sahodaya Admin — Sports
Route::prefix('sports')->group(function () {
    Route::get('/', [SportsProgramController::class, 'dashboard'])->name('sports.dashboard');
    Route::get('/age-groups', [SportsAgeGroupController::class, 'index']);  // moved here
    Route::get('/records', [AthleticRecordsDashboardController::class, 'index']);
    Route::get('/championship', [SportsProgramController::class, 'championship']);
    Route::get('/events/{event}/{tab?}', [SportsEventController::class, 'show'])->name('sports.event');
    Route::post('/events/{event}/items/{item}/auto-rank', [SportsEventController::class, 'autoRank']);
});

// Sahodaya Admin — MCQ
Route::prefix('mcq')->group(function () {
    Route::get('/', [McqDashboardController::class, 'index'])->name('mcq.dashboard');
    Route::get('/question-banks', [McqDashboardController::class, 'questionBanks']);
    Route::get('/question-banks/{bank}', [McqDashboardController::class, 'questionBank']);
    Route::get('/{exam}/{tab?}', [McqExamDetailController::class, 'show'])->name('mcq.exam');
    Route::post('/{exam}/hall-tickets/generate', [McqExamDetailController::class, 'generateHallTickets']);
    Route::get('/{exam}/hall-tickets/print', [McqExamDetailController::class, 'printHallTickets']);
    Route::post('/{exam}/attendance', [McqExamDetailController::class, 'markAttendance']);
    Route::post('/{exam}/marks/bulk-import', [McqExamDetailController::class, 'bulkImportMarks']);
    Route::post('/{exam}/ranking/compute', [McqExamDetailController::class, 'computeRanking']);
    Route::get('/{exam}/school-fees', [McqExamDetailController::class, 'schoolFees']);
    Route::post('/{exam}/school-fees/{fee}/approve', [McqExamDetailController::class, 'approveSchoolFee']);
});

// State Admin — Kalotsav
Route::prefix('kalotsav')->group(function () {
    Route::get('/', [KalotsavStateController::class, 'index'])->name('state.kalotsav.index');
    Route::post('/', [KalotsavStateController::class, 'store']);
    Route::get('/{program}', [KalotsavStateController::class, 'show']);
    Route::post('/{program}/propagate', [KalotsavStateController::class, 'propagate']);
    Route::get('/{program}/results', [KalotsavStateController::class, 'results']);
    Route::get('/{program}/winners', [KalotsavStateController::class, 'winners']);
    Route::get('/{program}/winners/export', [KalotsavStateController::class, 'exportWinners']);
});
```
