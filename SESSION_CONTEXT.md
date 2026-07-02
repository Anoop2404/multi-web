# Sahodaya Connect — Session Context
> Compact brief for new AI sessions. Full detail in SYSTEM_PROMPT.md.

---

## System in One Paragraph

Multi-tenant SaaS for CBSE school clusters (Sahodaya) in Kerala. Each Sahodaya (~20–60 schools) is a paying tenant. Schools are sub-tenants. Platform runs: annual membership, Kalotsav (arts festival), Sports Meet, MCQ exams, Training programs, Kids Fest, Teacher Fest, and CMS websites. Stack: Laravel 11 + Inertia.js + Vue 3 + MySQL + Stancl Tenancy. Roles use Spatie Permissions. Frontend is SPA via Inertia — no full-page reloads. All forms use `useForm()` from `@inertiajs/vue3`.

---

## Directory Layout (key paths)

```
app/Http/Controllers/
  SchoolAdmin/          ← school_admin panel controllers
  SahodayaAdmin/        ← sahodaya_admin panel controllers
  Admin/                ← superadmin + state admin
  Portal/               ← judge, student, teacher, exam, fest-ops portals

app/Services/
  Events/               ← FestRegistrationEligibilityService, FestMarkSaveService,
                           FestQualificationService, FestEventFeeResolver,
                           McqExamSessionService, McqRankingService, etc.
  Membership/           ← MembershipFeeCalculator, FeeReceiptService
  Audit/                ← PlatformAuditLogger, AuditLogCatalog
  Notifications/        ← NotificationService, FestEventNotifier

app/Models/             ← FestEvent, FestEventItem, FestRegistration, FestParticipant,
                           FestMark, FestQualification, McqExam, McqExamSession,
                           McqMark, McqRegistration, Student, Tenant, Registration

resources/js/Pages/
  Admin/School/         ← school admin Vue pages
  Admin/Sahodaya/       ← sahodaya admin Vue pages
  Portal/Student/       ← student portal pages
  Portal/Teacher/       ← teacher portal pages
  Portal/Judge/         ← judge portal pages
```

---

## Role → Panel/Portal Map

| Role | Logs in at | Goes to |
|------|-----------|---------|
| sahodaya_admin, sahodaya_staff, mark_entry_admin, registration_coordinator, sahodaya_finance, event_coordinator, data_entry, certificate_collector | `/login` | `/sahodaya-admin/{id}` |
| school_admin, school_staff | `/school-login` | `/school-admin/{id}` |
| judge | `/login` | `/portal/judge/{id}` |
| mark_entry_coordinator | `/login` | `/portal/fest-coordinator/{id}` |
| fest_ops | `/login` | `/portal/fest-ops/{id}` |
| exam_controller, exam_staff | `/login` | `/portal/exam/{id}` |
| group_admin | `/login` | `/portal/group/{id}` |
| house_admin | `/login` | `/portal/house-admin/{id}` |
| student | `/login` | `/portal/student/{id}` |
| teacher | `/login` | `/portal/teacher/{id}` |
| superadmin, state_admin, state_staff | `/login` | `/admin` |

---

## Event Types & Critical Rules

### Kalotsav (Arts Festival)
- Classes 1–2 → Kids Fest only. Classes 3–12 → Kalotsav.
- Class groups: LP (3–5), UP (6–7), HS (8–9), HSS (10–12).
- Student limits enforced by `FestParticipationPolicy`: max 1 onstage-individual + 1 onstage-group.
- Item codes (CKSC) link same item across school → Sahodaya → State.
- Flow: School round → Sahodaya round → State round.
- School event must have `parent_event_id` pointing to the Sahodaya event for promotion to work. `FestProgramController::store()` auto-links, but school can also manually link via `linkParent()`.

### Sports Meet
- Age groups: U8, U10, U11, U12, U14, U17, U19, Open.
- Age checked against `sports_age_cutoff_date` on the event (default Dec 31).
- U14 = age UNDER 14 on cutoff. Open = just needs a recorded DOB.
- Student can qualify for multiple age groups simultaneously.
- Gender must be recorded on student for sports registration.
- Items use `measurement_value` + `measurement_unit`, not just grade.
- **Sports ends at Sahodaya — NO state promotion.** `FestQualificationService::resolveNextLevelEvent()` returns null for sports.
- School submits winners via `FestQualificationService::submitSchoolSportsWinners()`.

### MCQ Exam
- Sahodaya level only.
- Exam is **OFFLINE** (physical) OR **ONLINE** (student portal timer). Registration + mark entry always online.
- Online: `McqExamSession` tracks attempt. Only ONE attempt allowed. `expires_at` — if student closes browser, session stays `started` (auto-submit cron is **MISSING**).
- Offline: `ExamOpsController` enters marks directly. No session.
- Hall ticket required before attendance. Fee approval required before hall tickets.
- Ranking: `McqRankingService::rankExam()` writes `rank` to `McqMark`.

### Kids Fest
- Pre-KG through Class 2 only. Bands: Baby (KG/LKG/UKG), Toddler (Classes 1–2).
- Ends at Sahodaya. No state round.

### Teacher Fest
- Participants are teachers, not students. No age/class eligibility checks.
- Can go to state level.

### Training Program
- Teacher workshops. Fee per-participant. Max capacity may apply.
- Certificate generated after attendance marked as "attended".

---

## Membership / Annual Registration
- School must be a paid member to participate in events.
- Flow: Sahodaya opens window → School submits data + fee → Sahodaya approves → membership number assigned.
- Fee types: `fixed` or `variable_by_student_count` (slab-based). `MembershipFeeCalculator` handles both.
- Window enforcement (`SahodayaRegistrationWindow`) is checked in `index()` but **NOT enforced in `begin()` write action (Bug B2)**.
- Receipt numbers from `SahodayaProfile::receipt_next_number` have a **race condition — no DB lock (Bug B1)**.

---

## Known Open Bugs (Fix These)

| ID | File | Problem |
|----|------|---------|
| B1 | `SahodayaProfile` / `FeeReceiptService` | Receipt number race condition — no `lockForUpdate()` |
| B2 | `AnnualRegistrationController::begin()` | Registration window not enforced in write action |
| B3 | `McqExamController::publishResults()` | `mcq.results.published` notification never fired |
| B4 | `Console/Kernel.php` | No cron to auto-submit expired MCQ online sessions |
| B5 | `McqRegistrationController` | Exam eligibility config not applied — any student can register |
| B6 | `StudentPortalProvisioner` | No welcome email after student portal provisioning |
| B7 | `TeacherPortalProvisioner` | No welcome email after teacher portal provisioning |
| B8 | `FestScheduleController` | `fest.schedule.published` notification never fired |
| B9 | `FestChestNumberController` | `fest.chest_numbers.revealed` notification never fired |
| B10 | `Console/Kernel.php` | `FestEventNotifier::registrationDeadlineReminder()` has no scheduled caller |
| B11 | `StudentDashboardController` | Student portal receives school-level `festFees` — wrong data |
| B12 | `McqController::hub()` | Delegates to registration index — no real hub page |
| B13 | `SchoolAdmin/DashboardController` | No MCQ summary, training summary, or action queue on school dashboard |
| B14 | `AuditLogCatalog.php` | No `mcq` category — MCQ actions fall through to `system` |
| B15–B19 | Various controllers | Missing audit logs: MCQ lifecycle, school registration, exam portal mark entry, judge marks, student start/submit |

---

## Already Fixed (Don't Re-Fix)

- Sports open-category registration — works correctly (no bug)
- `parent_event_id` — `FestProgramController::store()` auto-links to active Sahodaya event
- Sports → state promotion — `resolveNextLevelEvent()` returns null for sports
- `MembershipFeeCalculator` — reads active student count correctly
- Ledger: pagination uses `paginate(50)`, MySQL compat uses driver check, fiscal year uses `FinancialYear::currentId()`
- `FestEventFeeResolver::normalizeAgeGroupFees()` — now accepts `?string $tenantId`
- `FestEventSettingsController` — passes sahodaya ID to normalizeEventFeeSettings
- `FestProgramController::storeMark()` — method restored, typo fixed
- `Sahodaya/Events/Registrations.vue` — sports grouping with `sportsGroupedRegistrations` + `genderLabel()` added

---

## Incomplete Features (No UI Yet)

- School "Submit winners to Sahodaya" sports flow — uses generic registration, no dedicated UI
- MCQ: bulk student registration by class, bulk hall ticket PDF download
- Exam controller: bulk attendance CSV import
- State admin: per-Sahodaya results view, state winners export
- MCQ activity log page in Sahodaya admin
- School-branded portal login URL
- Student/teacher direct notifications (results go to school_admin only, not portal users)
- Teacher portal: no circulars view
- Judge portal: no schedule or appeals view

---

## Notification Template Slugs (reference in code)

**Wired up and working:**
`fest.registration.approved` · `fest.registration.rejected` · `fest.registration.withdrawn`
`fest.results.published` · `fest.promotion.completed` · `fest.record.broken`
`training.registration.confirmed` · `circular.published`
`state.remittance.created` · `state.remittance.verified` · `state.remittance.rejected`

**Template exists, trigger MISSING:**
`fest.registration.deadline` · `fest.schedule.published` · `fest.chest_numbers.revealed`
`mcq.results.published`

**Template may not exist + trigger missing:**
`student.portal.provisioned` · `teacher.portal.provisioned`
`mcq.registration.confirmed` · `mcq.fee.approved` · `mcq.fee.rejected`
`membership.payment.approved` · `membership.payment.rejected`
`sports.winners.received`

---

## Key Patterns to Follow

**Audit logging:**
```php
app(PlatformAuditLogger::class)->festEvent($event, 'registration', 'fest.X.action', 'Message', ['key' => 'val']);
// OR for MCQ:
app(PlatformAuditLogger::class)->log('mcq', 'mcq.X.action', 'Message', ['exam_id' => $exam->id]);
```

**Notifications:**
```php
app(NotificationService::class)->notifyFromTemplate('slug', [$schoolId], ['var' => 'value']);
// OR via high-level notifier:
app(FestEventNotifier::class)->someAction($event, ...);
```

**Inertia response (Sahodaya admin):**
```php
return $this->inertia('Sahodaya/Path/Page', ['prop' => $data]);
// In SahodayaAdminController base: $this->sahodaya gives the tenant
```

**Inertia response (School admin):**
```php
return $this->inertia('School/Path/Page', ['prop' => $data]);
// In SchoolAdminController base: $this->school gives the school tenant
```

**Vue form pattern:**
```js
const form = useForm({ field: '' });
form.post('/url', { preserveScroll: true, onSuccess: () => { ... } });
```

---

## Reference Documents (in project root)

| File | Contents |
|------|---------|
| `SYSTEM_PROMPT.md` | Full detailed context (long version of this file) |
| `FULL_AUDIT_REPORT.md` | Complete bug inventory + scores per module |
| `IMPLEMENTATION_PLAN.md` | 9-phase fix plan with code snippets |
| `EVENT_PAGES_PLAN.md` | Dedicated menus/pages plan per event type |
| `SAHODAYA_FIX_PLAN.md` | Original 7-part fix plan |
