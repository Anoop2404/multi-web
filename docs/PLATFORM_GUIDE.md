# Sahodaya Connect — Platform Guide

Complete reference for roles, workflows, features, events, MCQ, schools, and portals.

**URL patterns:** `{tenantId}` = UUID of Sahodaya cluster or school tenant.

---

## 1. Platform architecture

```
┌─────────────────────────────────────────────────────────────────┐
│  Central admin (superadmin)     /admin                          │
│  State admin                    /state-admin                    │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
  Sahodaya cluster        Member schools       Public websites
  /sahodaya-admin/{id}  /school-admin/{id}   {domain}/portal
        │                     │
        └────────── fest / mcq / membership ──────┘
                              │
                    Operational portals
        /portal/student  /portal/teacher  /portal/fest-ops
        /portal/judge    /portal/exam     /portal/fest-coordinator
        /portal/house-admin  /portal/group
```

| Tenant type | Purpose |
|-------------|---------|
| **Sahodaya** | Cluster body — membership, all schools, fest programs, finance |
| **School** | Member school — students, fest registration, MCQ, optional website |
| **State** | State-level program propagation (optional) |

Each Sahodaya has its **own database** (multi-tenant). Schools belong to a Sahodaya via `parent_id`.

---

## 2. Login & home URLs

| Role | Login | After login |
|------|-------|-------------|
| Super admin | `/admin/login` | `/admin` |
| State admin / staff | `/admin/login` | `/state-admin` |
| Sahodaya admin | `/portal/login` or Sahodaya domain | `/sahodaya-admin/{sahodayaId}` |
| Sahodaya staff (permission roles) | Same | `/sahodaya-admin/{sahodayaId}` |
| School principal / admin / staff | `/portal/login` or `/portal/s/{schoolCode}/login` | `/school-admin/{schoolId}` |
| Student | School portal login | `/portal/student/{schoolId}` |
| Teacher | School portal login | `/portal/teacher/{schoolId}` |
| Judge | Portal login | `/portal/judge/{sahodayaId}` |
| Fest ops | Portal login | `/portal/fest-ops/{sahodayaId}` |
| Mark coordinator | Portal login | `/portal/fest-coordinator/{sahodayaId}` |
| Exam controller / staff | Portal login | `/portal/exam/{sahodayaId}` |
| House admin | Portal login | `/portal/house-admin/{schoolId}` |
| Group admin | Portal login | `/portal/group/{schoolId}` |

---

## 3. Roles & permissions

### 3.1 Sahodaya roles

| Role | Panel | Default access |
|------|-------|----------------|
| **sahodaya_admin** | Sahodaya admin | Full cluster control |
| **sahodaya_staff** | Sahodaya admin | View + custom permissions |
| **registration_coordinator** | Sahodaya admin | Fest registrations |
| **sahodaya_finance** | Sahodaya admin | Fest finance, ledger, membership payments |
| **certificate_collector** | Sahodaya admin | Certificates |
| **data_entry** | Sahodaya admin | Fest manage + marks |
| **event_coordinator** | Sahodaya admin | Fest manage, schedule, settings |
| **mark_entry_admin** | Sahodaya admin | Fest marks (all events) |
| **judge** | Judge portal | Mark entry at assigned items |
| **mark_entry_coordinator** | Fest coordinator portal | Mark entry (assigned events) |
| **fest_ops** | Fest ops portal | Per-event duties (see §7.4) |
| **exam_controller** | Exam portal | MCQ attendance + marks |
| **exam_staff** | Exam portal | MCQ hall attendance |

**Portal-only roles** (`judge`, `fest_ops`, `exam_*`, `mark_entry_coordinator`) do not use the Sahodaya admin sidebar — they use dedicated portals.

**Granular permissions** (assignable on staff users):  
`fest.view`, `fest.manage`, `fest.marks`, `fest.registrations`, `fest.results`, `fest.finance`, `fest.settings`, `fest.catering`, `fest.schedule`, `fest.certificates`, `training.view`, `training.manage`, `finance.view`, `mcq.view`, `mcq.manage`, `mcq.attendance`, `mcq.marks`, `membership.view`, `membership.manage`, `website.view`, `website.news`, `website.manage`, `users.manage`

### 3.2 School roles

| Role | Panel | Notes |
|------|-------|-------|
| **school_principal** | School admin | Full school + create admins/VP/coordinators |
| **school_vice_principal** | School admin | Manage staff + coordinators |
| **school_admin** | School admin | Day-to-day school management |
| **school_staff** | School admin | View fest + website |
| **school_event_coordinator** | School admin | **Scoped** to assigned programs/events/MCQ exams |
| **group_admin** | Group portal | Class/group students + fest oversight |
| **house_admin** | House portal | House students, registrations, ranking |
| **student** | Student portal | Profile, fest, MCQ |
| **teacher** | Teacher portal | Question banks, fest schedule |

### 3.3 Fest ops duties (per event)

Assign at **Portal users** or **Event → Event staff**:

| Duty | Portal screen |
|------|---------------|
| `coordinator` | Event overview & stats |
| `registration` | Approve/reject registrations |
| `stage` | Live stage queue (optional: one stage) |
| `attendance` | Item attendance |
| `food` | Kitchen / catering board |
| `appeals` | Participant appeals |
| `certificates` | Print certificates |
| `marks` | Mark entry |
| `discipline` | Item-head–scoped admin |
| `admit_cards` | Admit card desk |

---

## 4. Sahodaya admin — menu map

**Base:** `/sahodaya-admin/{sahodayaId}`

### 4.1 Membership & schools

| Feature | Path | Workflow |
|---------|------|----------|
| Dashboard | `/` | Setup checklist, quick stats |
| Member schools | `/schools` | Approve/reject applications, toggle fest registration |
| Applications | `/schools/applications` | New school intake |
| School students | `/schools/{schoolId}/students` | Browse cluster student records |
| Lock overrides | `/schools/{schoolId}/lock-overrides` | Per-school add/edit unlock |
| Membership settings | `/membership/settings` | Fees, windows, categories, mail |
| Payment verification | `/membership/payments` | Approve annual membership fees |
| Student submissions | `/membership/submissions` | Annual data submission review |
| Student verification | `/students/verification` | Bulk/individual student verify |
| Change requests | `/student-change-requests` | Approve profile edits from schools |
| Academic years | `/academic-years` | Activate/close years, financial years |
| Membership reports | `/membership/reports` | Exports |
| Setup wizard | `/setup` | First-time cluster setup |

### 4.2 Fest programs (6 hubs)

| Program | Hub URL | Event type |
|---------|---------|------------|
| Kalotsav | `/kalotsav` | `kalolsavam` |
| Sports Meet | `/sports` | `sports` |
| Kids Fest | `/kids-fest` | `kids_fest` |
| Teacher Fest | `/teacher-fest` | `teacher_fest` |
| English Fest | `/english-fest` | `english_fest` |
| Science Fest | `/science-fest` | `science_fest` |
| All events | `/events` | Cross-program directory |

**Each program hub includes:**
- Program dashboard & stats
- **Item catalog** (master setup, assign to events, heads for sports)
- Create new event
- Sports-only: age groups, athletic records, house championship

**Catalog workflow (once per program):**
```
Resync CKSC master → Enable items & set fees → Assign items into event
→ (Sports) Sync Event Heads on event
```

### 4.3 Cross-fest tools

| Feature | Path |
|---------|------|
| Fest payment queue | `/fest/payments` |
| Display screens | `/display-screens` |
| Certificate templates | `/certificate-templates` |
| Certificate search | `/events/certificates/search` |
| Category masters | `/taxonomy-masters` |
| Competition types | `/competition-types` |
| Custom type hub | `/programs/{nav-slug}` (e.g. `/programs/robotics`) |
| Event areas & eligibility | `/events/{id}/areas`, `/events/{id}/eligibility-rules` (non-sports) |
| Area-wise participants report | `/events/{id}/reports/area-wise-participants` |

Custom competition types can use **competition areas** (windows/fees), **eligibility rules** (gender/school/verified overlays), and per-item **tie-break modes** (`none`, `include_all_ties`, `exclude_ties`, `lot_draw`, `manual`) on promote. Sports continues to use Event Heads instead of areas.

**Fest notifications (FRD-08):** templates `fest.registration.open`, `fest.payment.pending`, `fest.competition.reminder`, `fest.certificate.available` ship with the platform. Optional per-type overrides use `fest.{event_type}.{suffix}` (e.g. `fest.robotics.registration.open`). Scheduled: `fest:competition-reminders`, `fest:payment-reminders`. Seed types: `php artisan fest:ensure-competition-types`.

### 4.4 MCQ exams

| Feature | Path |
|---------|------|
| MCQ hub | `/mcq` |
| Exam series & levels | `/mcq-series` |
| Exam management | `/mcq-exams/{examId}` |
| MCQ payments | `/mcq/payments` |

### 4.5 Training

| Feature | Path |
|---------|------|
| Training programs | `/training` |

### 4.6 Finance

| Feature | Path |
|---------|------|
| Finance hub | `/finance` |
| Receivables | `/finance/receivables` |
| Payables | `/finance/payables` |
| Ledger | `/ledger` |
| Opening balances | `/ledger/opening-balances` |
| State remittances | `/state-remittances` |

### 4.7 Website (if enabled)

| Feature | Path |
|---------|------|
| Site builder (draft / publish / preview) | `/site-builder` |
| Custom domains (TXT verify) | `/website/domains` |
| Microsites | `/website/sites` → public `/m/{slug}` |
| Forms builder + submissions | `/website/forms` → public `/forms/{slug}` |
| Content / office bearers / circulars | `/public-content`, `/office-bearers`, `/circulars` |

HTML in section configs is sanitized on save and render. Public homepage serves **published** section snapshots; `/preview-site` (auth) shows drafts. Permissions: `website.edit`, `website.publish` (plus existing `website.manage`).

### 4.8 Settings

| Feature | Path |
|---------|------|
| Portal users | `/users` |
| Membership settings | `/membership/settings` |

---

## 5. Fest event — full lifecycle

**Event URL:** `/sahodaya-admin/{sahodayaId}/events/{eventId}`

### 5.1 Lifecycle targets (checklist)

Tracked on **Settings → Lifecycle**:

1. ☐ Event items configured  
2. ☐ Registrations reviewed (no pending)  
3. ☐ School fest fees verified  
4. ☐ State remittance verified *(if state program)*  
5. ☐ Performance schedule built  
6. ☐ Schedule published  
7. ☐ Status → **Ongoing**  
8. ☐ Mark entry complete  
9. ☐ Results published  

### 5.2 Workflow stepper (UI)

```
① Items & settings  →  ② Registrations  →  ③ Marks & chest  →  ④ Results
```

### 5.3 Event statuses

| Status | Meaning |
|--------|---------|
| `draft` | Setup only — not listed for schools |
| `published` | Optional announce; **Sports:** still Sahodaya-only (schools do not see it yet). Other fests may preview. |
| `registration_open` | Schools can see the event and register (Sports first visibility) |
| `ongoing` | Competition / marks in progress |
| `completed` | Finished |

**Sports school visibility:** schools list Sports events only when status is `registration_open`, `ongoing`, or `completed`. Draft and `published` stay Sahodaya-only.

**Publish results** is separate: set **Results published** on Overview (or Results page). That releases medals/rankings; it does not change the status field.

Also set: **fest start/end dates**, **registration open/close** on Overview. Per-head `reg_start`/`reg_end` on Sports Event Heads further control when each discipline accepts entries.

### 5.4 Event setup phases

#### Phase A — Create & load items

1. Create event from program hub or `/events`
2. **Catalog → Assign to event** — import enabled master items
3. **Event → Event Heads** (sports) — sync from catalog
4. **Event → Catalog & rules** — enable/disable items, fees per item
5. **Settings** tabs:

| Tab | Purpose |
|-----|---------|
| Lifecycle | Checklist, verification day, school doc verification |
| Locks | Registration lock, scoring lock, appeals, chest reveal |
| Venues & stages | Venues, stages for schedule |
| Participation | Max items per student, fee-before-approval |
| Eligibility | Sports age cutoff date |
| **Fees** | Billing model (see §6) |
| Registration | Event reg windows, per-item reg/competition dates |
| Numbering | Chest numbers, registration IDs |
| Grades / Points | Marking scheme |
| Combo rules | Max arts/sports per student |
| Volunteers | Volunteer roster |
| Clone | Duplicate event for next year |

#### Phase B — Registration

- Schools register via **School admin → program → registration**
- Students may self-register if enabled
- **Sahodaya:** Event → Registrations — approve/reject
- Clash & substitution review queues
- **Event staff** (fest_ops duty `registration`) can approve from portal

**Gates:**
- School membership approved
- Student verified (if required)
- School fest fee paid (if required)
- Event registration fee (sports composite student reg)
- Per-item registration windows

#### Phase C — Fees & finance

- **Settings → Fees** — configure billing model
- Schools upload payment proof → **Event → School fees** or `/fest/payments`
- Sahodaya verifies → registrations can proceed

#### Phase D — Schedule & fest day

- **Stage schedule** — build performance order
- **Item scheduling** — assign items to dates/times
- Publish schedule to public portal
- Set status **Ongoing**
- **Attendance**, **Stage queue**, **Gate check** (fest ops portal)
- **Mark entry** — admin, judge portal, or mark coordinator
- **Chest numbers** (sports)

#### Phase E — Results & certificates

- Enter marks → **Results → Publish**
- Per-item result publish (optional)
- **Leaderboard / Championship** (sports)
- **Certificates** — generate & print
- **ID cards** — by head or staff assignment
- **Reports** — before / during / after phase packs

### 5.5 Event sidebar (all pages)

| Group | Pages |
|-------|-------|
| **Event setup** | Settings, Activity log |
| **Participants** | Registrations, Attendance, Stage schedule, Item scheduling |
| **Competition** | Mark entry, Results, Leaderboard, Championship*, Chest numbers* |
| **Reports & finance** | School fees, Fee ledger, Reports, Certificates, ID cards |
| **Administration** | Judges, Appeals, Event staff, Invoices, Athletic records*, Houses*, Catering* |

\*Sports or program-specific.

### 5.6 Sports Meet — program-level extras

Sports Meet is a **season hub** listing **discipline events** (Athletics, Chess, …). Each **Event Head** can be promoted to its own `FestEvent` (`partition_role=sports_discipline`) with independent status, venue, dates, and always-on **sports composite** fees (no fee-model dropdown on Sports).

| Feature | Path |
|---------|------|
| Season / discipline list | `/sports` |
| Age groups (U8–U19) | `/sports/age-groups` |
| Master catalog | `/sports/catalog` |
| Athletic records | `/sports/records` |
| House championship | `/sports/championship` |
| Results / rankings | `/sports/results`, `/sports/rankings` |

**Promote Event Heads → discipline events:** on `/sports`, use **Promote Event Head(s) → discipline events** when heads are ready (idempotent — already linked heads are skipped). CLI still works: `php artisan fest:promote-sports-heads --sahodaya=… [--dry-run]`.  
Backfill head fee columns: `php artisan fest:backfill-head-fees --sahodaya=… [--dry-run]`.

### 5.7 Kalotsav extras

- School rounds management: `/kalotsav/school-rounds`
- Level promotion: Event → Rounds & promotion

---

## 6. Fest fee models (Sports composite)

**Sports:** billing is always `sports_composite` — there is no fee-model dropdown. Configure school / student / team fees on each Event Head (Competition hub). Optional event-wide fallbacks live under Settings → Fees.

**Other fest types:** choose a billing model in Settings → Fees as needed.

### Fee stack (applied in order)

```
① School registration     — once per school (₹)
② Student registration    — per student in event (₹)
③ Included items          — how many items covered by ② per student
④ Item fees               — per item (when quota = 0, ALL items)
⑤ Extra item fees         — items beyond ③ (uses head "extra" rate)
```

### Item fee resolution (each billed item)

**Sports (per Event Head):** items inherit head rates — per-item `fee_amount` is ignored. Use head student/team rates (and optional default/extra item fees). Over-cap registrations go to a waiting list and promote when a seat frees.

**Other fest types:**

1. Per-item override on event item  
2. Item **head** default or extra fee  
3. Age group fee (U8, U14, …)  
4. Default item fee fallback  

### Examples

| Config | Result |
|--------|--------|
| School ₹2000, Student ₹300, Included **2** | First 2 items/student free; 3rd+ charged at extra/head rates |
| Included **0** | Every item billed separately; student reg still applies |
| Head: Chess default ₹150, extra ₹200 | Used based on item position vs quota |

**Configure:** Event → Settings → Fees → Save → verify on Event → School fees.

Other models: `item_catalog` (Kalotsav class categories), `cksc_tiered`, `flat_school`, `per_item`, `per_student`, `none`.

---

## 7. Assigning event staff

### Create fest ops user

**Sahodaya admin → Portal users → Create:**
1. Name, email, password  
2. Role: **Event operations (fest_ops)**  
3. Event ops assignment: pick **event** + **duties**  
4. User logs in → `/portal/fest-ops/{sahodayaId}` → sees only assigned events  

### Or assign from event

**Event → Event staff** (sidebar under Administration): pick user + duty (+ stage for stage managers).

### Mark entry only

Role: **mark_entry_coordinator** + duty `marks` → `/portal/fest-coordinator/{sahodayaId}`

---

## 8. School admin — full map

**Base:** `/school-admin/{schoolId}`

### 8.1 Core

| Area | Path | Purpose |
|------|------|---------|
| Dashboard | `/` | Overview |
| Students | `/students` | CRUD, import, verify, portal accounts |
| Teachers | `/teachers` | Staff records |
| Houses | `/houses` | House setup |
| Users | `/users` | Create coordinators, staff |
| Annual registration | `/registration/*` | Sahodaya membership data submission |
| Payments | `/payments` | Membership fee upload |
| Circulars | `/circulars` | Sahodaya notices |

### 8.2 Fest (per program)

**Paths:** `/school-admin/{schoolId}/{program}/`  
Programs: `kalotsav`, `sports`, `kids-fest`, `teacher-fest`, `english-fest`, `science-fest`

| Tab | Purpose |
|-----|---------|
| Hub | Program overview |
| My events | Events school is in |
| Registration | Register students for items |
| Results | View published results |
| Reports | School participation reports |
| Fest day | Day-of tools |
| Payment | Upload event fee proof |

**Sports:** `/sports/my-event/{eventId}/{tab}` — deeper event workspace for school.

### 8.3 MCQ (school)

| Path | Purpose |
|------|---------|
| `/mcq` | Exam list |
| `/mcq/{examId}/register` | Register students |
| `/mcq/{examId}/fee` | Upload fee proof |
| `/mcq/{examId}/hall-tickets` | Download hall tickets |

### 8.4 Website (optional)

Site builder, news, gallery, staff page, achievements, downloads, board results, alumni, settings.

### 8.5 School coordinator scoping

`school_event_coordinator` users only see programs/events/MCQ exams they are assigned to (`EventCoordinatorScope` middleware).

---

## 9. MCQ exam — full workflow

### 9.1 Sahodaya setup

```
Create series (optional levels) → Create exam → Attach question banks
→ Set eligibility (class, gender, verification) → Publish exam
→ Set fee & schedule → Assign exam staff
```

**Sahodaya paths:** `/mcq-exams/{id}` — tabs for questions, registrations, marks, fees, attendance, hall tickets, staff, session monitor, results.

### 9.2 School registration

```
School opens /mcq/{examId} → Register students (single/bulk/by class)
→ Status: pending_payment → Upload fee proof
→ Sahodaya approves fee → Sahodaya approves registration
→ Hall ticket issued
```

### 9.3 Exam delivery

| Mode | Flow |
|------|------|
| **Online** | Student → `/portal/student/{id}/mcq` → start exam → auto-grade → submit |
| **Offline** | Exam portal attendance → manual marks entry |

### 9.4 Exam portal roles

| Role | Path |
|------|------|
| Exam controller | `/portal/exam/{sahodayaId}/exams/{examId}/attendance`, `/marks`, `/supervision` |
| Exam staff | Attendance only |

### 9.5 Multi-level series

`/mcq-series` — level 2+ exams can require promotion from parent exam (cutoff marks, top rank, or manual).

### 9.6 Teacher question banks

`/portal/teacher/{schoolId}/question-banks` — author questions linked to Sahodaya exams.

---

## 10. Student portal

**Base:** `/portal/student/{schoolId}`

| Feature | Path |
|---------|------|
| Dashboard | `/` |
| Profile | `/profile` |
| MCQ hub & online exam | `/mcq`, `/mcq/{registration}/exam` |
| Hall ticket | `/mcq/{registration}/hall-ticket` |
| Fest registrations | `/fest-registrations` |
| Register for item | POST `/fest/{eventId}/items/{itemId}/register` |
| Schedule | `/fest/schedule` |
| Results | `/results`, `/sports-results` |
| Certificates | `/certificates` |
| Admit card | `/fest/{eventId}/admit-card` |
| Appeals | POST `/fest/{eventId}/appeals` |

---

## 11. Teacher portal

**Base:** `/portal/teacher/{schoolId}`

Fest schedule, results, certificates, **MCQ question banks**, training programs, profile.

---

## 12. Operational portals summary

| Portal | Role(s) | Scope |
|--------|---------|-------|
| Fest ops | fest_ops | Assigned events + duties only |
| Fest coordinator | mark_entry_coordinator | Assigned events, marks |
| Judge | judge | Assigned items/events, marks |
| Exam | exam_controller, exam_staff | Assigned MCQ exams |
| House admin | house_admin | One house in school |
| Group admin | group_admin | Class/group in school |

---

## 13. Student data & verification

### Registration windows (Sahodaya controls)

- **Add window** — schools can add new students  
- **Edit window** — schools can edit existing students  
- **Emergency lock** — hard freeze on all schools  
- **Per-school override** — temporary unlock with expiry  

### Verification

- Sahodaya verifies students → `verified_at` set  
- Events can require **verified students only** (Settings → Fees)  
- Verification queue: `/students/verification`  

### Change requests

During lock: school submits → school principal approves → Sahodaya approves → applied.

---

## 14. Finance & ledger

| Layer | What |
|-------|------|
| **Membership fees** | Annual school membership to Sahodaya |
| **Fest event fees** | Per-event school invoices (composite, catalog, etc.) |
| **MCQ fees** | Per-exam school/student fees |
| **Ledger** | Double-entry accounts — income from verified fees posts automatically |
| **Payables** | Sahodaya outgoing obligations |
| **State remittances** | State-round fee remittance tracking |

---

## 15. Reports

### Event reports (Sahodaya)

**Event → Reports** — phased packs:

- **Before:** registration lists, admit cards, clashes, fees pending, school participation  
- **During:** attendance, schedule, mark entry status, catering  
- **After:** results, certificates, rankings, head-wise participants, promotions  

### School reports

**School admin → program → Reports** — school-scoped participation, head-wise, student-wise.

---

## 16. Quick-start playbooks

### A. New Sports Meet season

1. `/sports/age-groups` — configure U8–U19  
2. `/sports/catalog` — resync master, enable items  
3. `/sports` — open season hub (auto-created for Sports)  
4. `/sports/catalog/assign` — import items into the season  
5. Season → Event Heads (Competition) — sync / add heads with composite fees  
6. `/sports` → **Promote** Event Heads into discipline events  
7. Each discipline → Overview → dates + `registration_open`  
8. Portal users → create fest_ops / mark coordinators → assign per Event Head  
9. Schools register → approve → verify fees  
10. Schedule → publish → ongoing → marks → results  

### B. New Kalotsav cluster event

1. `/kalotsav/catalog` — master setup  
2. Create sahodaya-round event  
3. Settings → Fees → **Item catalog** or **CKSC tiered**  
4. School rounds (optional) → promote winners  
5. Standard registration → marks → results flow  

### C. New MCQ exam

1. `/mcq-exams` — create & publish  
2. Attach question banks  
3. Schools register at `/school-admin/.../mcq`  
4. Approve fees & registrations  
5. Exam day: attendance + online or offline marks  
6. Publish results  

### D. New member school

1. School applies or Sahodaya creates  
2. Approve membership  
3. School completes annual registration submission  
4. Verify membership payment  
5. School admin login → add students → verify students  
6. Enable fest registration on school record  

---

## 17. Public-facing

| URL | Content |
|-----|---------|
| `{sahodaya-domain}/` | Public website (if enabled) |
| `{sahodaya-domain}/portal` | Portal login landing |
| `{school-domain}/portal` | School portal login |
| Public fest schedule/results | After publish flags set |

---

## 18. Glossary

| Term | Meaning |
|------|---------|
| **Master catalog** | Year-independent item list for a program |
| **Event items** | Items imported into one fest event |
| **Event Head** | Sports grouping (Chess, Athletics); fee/policy unit; may be promoted to a discipline event |
| **Item head** | Same concept for non-sports fests (section grouping) |
| **Chest number** | Sports participant ID on fest day |
| **Level round** | school / sahodaya / state |
| **Conduct levels** | Which future rounds this event feeds |
| **CKSC** | Kerala CBSE Sahodaya standard item templates |
| **Composite fee** | School + student + included items + extras |
| **Fest ops** | Day-of operational staff role |

---

*Generated from codebase routes, `TenantUserCatalog`, fest/MCQ services, and admin navigation. For planned (not yet built) features see `FEATURE_PLAN_V2.md`.*
