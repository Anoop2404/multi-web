# Full Platform Audit Report
> Sahodaya Connect — Complete system audit: roles, logins, flows, bugs, UI/UX, logs, features
> Audited: 2026-06-30

---

## 1. ALL ROLES & LOGIN FLOWS

### Role Inventory

| Role | Panel / Portal | What they do |
|------|---------------|-------------|
| `superadmin` | `/admin` (central) | Platform-level: tenants, billing, master data, audit logs |
| `state_admin` | `/admin` (state) | State programs, remittances, cross-Sahodaya visibility |
| `state_staff` | `/admin` (state, limited) | Read-only state views |
| `sahodaya_admin` | `/sahodaya-admin/{id}` (full) | Full cluster management |
| `sahodaya_staff` | `/sahodaya-admin/{id}` (permission-gated) | Assigned permissions only |
| `registration_coordinator` | `/sahodaya-admin/{id}` | fest.view + fest.registrations |
| `sahodaya_finance` | `/sahodaya-admin/{id}` | fest.view + fest.finance + membership.view |
| `certificate_collector` | `/sahodaya-admin/{id}` | fest.view + fest.certificates |
| `data_entry` | `/sahodaya-admin/{id}` | fest.view + fest.manage + fest.marks |
| `event_coordinator` | `/sahodaya-admin/{id}` | fest.view + fest.manage + fest.schedule + fest.settings |
| `mark_entry_admin` | `/sahodaya-admin/{id}` | fest.view + fest.marks |
| `school_admin` | `/school-admin/{id}` (full) | Full school management |
| `school_staff` | `/school-admin/{id}` (permission-gated) | fest.view + website.view |
| `judge` | `/portal/judge/{id}` | Assigned items: mark entry only |
| `mark_entry_coordinator` | `/portal/fest-coordinator/{id}` | All items: mark entry |
| `fest_ops` | `/portal/fest-ops/{id}` | Day-of operations: attendance, registrations, stage, catering, gate |
| `exam_controller` | `/portal/exam/{id}` | MCQ: attendance + mark entry + supervision |
| `exam_staff` | `/portal/exam/{id}` | MCQ: attendance only |
| `group_admin` | `/portal/group/{id}` | Class/group view: students, registrations, schedule, admit cards |
| `house_admin` | `/portal/house-admin/{id}` | House: students, registrations, ranking |
| `student` | `/portal/student/{id}` | Fest registrations, MCQ exams, results, admit cards, appeals |
| `teacher` | `/portal/teacher/{id}` | Training, Teacher Fest, MCQ question banks, results, appeals |

### Login Routes

| URL | Who uses it | Notes |
|-----|-------------|-------|
| `/login` | All admin roles + portal users | Redirects to correct panel after auth |
| `/school-login` | School admin + school staff | School-specific login |
| No dedicated portal login URL | Students, teachers, judges, etc. | **GAP: no `/portal/login` page** |

### Login Flow Gaps 🔴

1. **No portal-specific login URL** — Students and teachers must use `/login` and be redirected. There is no branded `/portal/login` page or school-specific URL like `/school/{schoolId}/login`. Schools must share the main URL and students must know their credentials.

2. **Student provisioning gives school admin the password** — `StudentPortalProvisioner::provision()` takes `$email` + `$password` as parameters set by the school admin, then creates the user. There is no self-service registration or invite-via-email flow. The school must communicate the password to the student out-of-band. No welcome email is sent.

3. **Teacher provisioning same issue** — `TeacherPortalProvisioner` follows the same pattern.

4. **No forgot-password for portal users** — There is a standard Laravel `PasswordResetLink` email but no UI link on the portal login page. Portal users (students, teachers, judges) have no visible self-service password reset.

5. **Role collision** — A user with both `sahodaya_staff` AND `judge` roles will land in the sahodaya admin panel, not the judge portal. The portal middleware (`judge.portal`) checks role but the redirect-after-login logic points to the panel. No clear documented UX for multi-role users.

---

## 2. MCQ FLOW — COMPLETE STATUS

### What is Online vs Offline

| Step | Mode | Status |
|------|------|--------|
| Sahodaya creates exam | Online (Sahodaya admin) | ✅ Complete |
| School registers students | Online (School admin) | ✅ Complete |
| MCQ school fee calculation | Online (auto-calculated) | ✅ Complete |
| School uploads MCQ fee proof | Online (School admin) | ✅ Complete |
| Sahodaya approves school fee | Online (Sahodaya admin) | ✅ Complete |
| Hall ticket generation | Online (Sahodaya admin) | ✅ Complete |
| Student downloads hall ticket | Online (Student portal) | ✅ Complete |
| **Exam conduct** | **OFFLINE** (physical exam hall) | N/A |
| Exam controller marks attendance | Online (Exam portal) | ✅ Complete |
| Exam controller enters marks | Online (Exam portal) | ✅ Complete |
| **Alternative: Student takes online** | **ONLINE** (Student portal) | ✅ Complete |
| Sahodaya computes ranking | Online (Sahodaya admin → trigger) | ✅ Complete — `McqRankingService` |
| Sahodaya publishes results | Online (Sahodaya admin) | ✅ Complete |
| School views results per student | Online (School admin MCQ page) | ⚠️ Partial — single page, no per-exam detail |
| Student views result + grade | Online (Student portal) | ✅ Complete |
| Teacher contributes questions | Online (Teacher portal) | ✅ Complete |

### MCQ Flow Gaps / Bugs

**Bug:** `McqRankingService` updates `rank` field on `McqMark`. A migration (`2026_06_30_000002`) adds the `rank` column — verify this has been run on all tenant DBs. If not run, `$mark->update(['rank' => $rank])` silently fails (no column error in MySQL, just ignored).

**Missing: School-level MCQ hub page** — `McqController::hub()` simply delegates to `McqRegistrationController::index()`. The school sees one combined list of exams with inline registration. There is no dedicated per-exam detail page showing: hall ticket status per student, attendance result, marks per student, school rank vs cluster. The `School/Mcq/Index.vue` handles everything in one flat page.

**Missing: MCQ school fee upload UI** — `McqSchoolFee` model exists and fee is calculated, but the school admin MCQ page doesn't clearly show "You owe ₹X for this exam — upload proof here". This is buried in the exam row.

**Missing: MCQ notification triggers** — `mcq.results.published` template exists but where is it triggered? Checked `McqExamController` — only one `notifyFromTemplate` call (at line 126) which notifies registered school admins when an exam is published, not when results are published. Results publish notification is NEVER fired.

**Missing: Bulk student registration for MCQ** — School must register students one by one. No bulk-by-class or bulk-by-list feature. For a large exam with 200 students, this is extremely tedious.

**Missing: MCQ eligibility filter on school side** — `McqExam.eligibility_config` field exists but the school MCQ page shows ALL active students without filtering by class group or eligibility criteria defined on the exam. Students from wrong class can be registered.

**Missing: Hall ticket download for school admin** — School admin can't download all hall tickets for their school as a PDF bulk download. Only the student portal has per-student hall ticket view.

**Missing: MCQ attendance import** — `ExamOpsController::storeAttendance()` does one student at a time. For 200+ students, no bulk CSV attendance import exists (unlike the hint in EVENT_PAGES_PLAN.md which lists it as needed).

**Missing: Auto-expire and auto-submit** — When a student starts an online MCQ exam, `McqExamSessionService::isExpired()` checks on the next page load. But if the student closes the browser, the exam is never auto-submitted. There is no scheduled command to auto-submit expired in-progress exams. Students who abandon mid-exam stay as `started` status indefinitely.

---

## 3. ACTIVITY LOGS / AUDIT TRAIL — STATUS

### What is Logged ✅

| Action | Where logged |
|--------|-------------|
| User login / logout / failed login | `PlatformAuditLogger` in auth controllers |
| User created / updated / deleted | `PlatformAuditLogger` in TenantUserController (both Sahodaya + School) |
| Membership payment verified / rejected | `PlatformAuditLogger` |
| Fest registration approved / rejected / cancelled | `PlatformAuditLogger` in `FestRegistrationReviewController` and `FestEventOpsController` |
| Fest appeal resolved | `PlatformAuditLogger` |
| Fest promotion completed | `PlatformAuditLogger` |
| Fest catalog actions | `PlatformAuditLogger::festCatalog()` in `FestCatalogController` |
| Fest per-event page activity | `PlatformAuditLogger::festEvent()` in most Sahodaya event controllers |
| Athletic record broken | Logged via `FestAthleticRecordService` |

### What is NOT Logged 🔴

| Action | Gap |
|--------|-----|
| School submits fest registration | No audit log in `FestRegistrationController::store()` |
| School withdraws registration | No audit log in `FestRegistrationController::withdraw()` |
| School uploads event fee proof | No audit log |
| MCQ exam created / updated / status changed | No audit log in `McqExamController` |
| MCQ exam results published | No audit log |
| MCQ ranking computed | No audit log |
| School registers student for MCQ | No audit log in `McqRegistrationController::store()` |
| School uploads MCQ fee proof | No audit log |
| Exam controller marks attendance (MCQ) | No audit log in `ExamOpsController::storeAttendance()` |
| Exam controller enters marks (MCQ) | No audit log in `ExamOpsController::storeMark()` |
| Judge enters marks | No audit log in `JudgeDashboardController::storeMark()` |
| Student starts MCQ exam | No audit log in `StudentMcqController::startExam()` |
| Student submits MCQ exam | No audit log in `StudentMcqController::submitExam()` |
| Student / teacher files appeal | No audit log in `PortalFestAppealController` |
| Chest numbers generated / revealed | Partially logged — check `FestChestNumberController` |
| Schedule published | Partially logged — check `FestScheduleController` |
| Training program created / updated | No audit log |
| School submits training registration | No audit log |
| School uploads training fee proof | No audit log |
| State program created / propagated | No audit log in `StateFestProgramController` |
| State remittance created | No audit log |
| Membership data submitted / reviewed | No audit log in `AnnualRegistrationController` |
| Circular published | No audit log in `CircularController` |

### MCQ-Specific Activity Page
There is an `Activity.vue` page for fest events (`/sahodaya-admin/{id}/events/{event}/activity`) but NO equivalent activity/log page for MCQ exams. Sahodaya admin cannot see a per-exam history of who did what.

### Audit Log Categories Missing
`AuditLogCatalog::categoryForAction()` only handles: `auth.*`, `user.*`, `payment.*`, `fest.*`, `ledger.*`, `remittance.*`. Actions starting with `mcq.*`, `training.*`, `membership.*`, `sports.*` fall through to `system` category and are indistinguishable in the admin audit log viewer.

---

## 4. NOTIFICATION SYSTEM — STATUS

### Templates That Exist & Are Triggered ✅

| Template slug | Trigger location |
|---------------|-----------------|
| `fest.registration.approved` | `FestEventNotifier::registrationApproved()` |
| `fest.registration.rejected` | `FestEventNotifier::registrationRejected()` |
| `fest.registration.withdrawn` | `FestEventNotifier::registrationWithdrawn()` |
| `fest.results.published` | `FestEventNotifier::resultsPublished()` |
| `fest.promotion.completed` | `FestEventNotifier::promotionCompleted()` |
| `fest.registration.deadline` | `FestEventNotifier::registrationDeadlineReminder()` |
| `fest.record.broken` | `FestAthleticRecordService` |
| `training.registration.confirmed` | `TrainingProgramController` |
| `circular.published` | `CircularController` |
| `state.remittance.created / verified / rejected` | `StateRemittanceController` |
| `mcq.results.published` | **MCQ exam controller when results_published — NOT TRIGGERED** 🔴 |

### Missing Notification Templates 🔴

| Missing slug | When it should fire |
|-------------|---------------------|
| `student.portal.provisioned` | School creates student portal login — send welcome email with login details |
| `teacher.portal.provisioned` | School creates teacher portal login |
| `mcq.results.published` | When Sahodaya publishes MCQ results (template exists, trigger MISSING) |
| `mcq.registration.confirmed` | School registers student for MCQ exam |
| `mcq.fee.approved` | Sahodaya approves school MCQ fee proof |
| `mcq.fee.rejected` | Sahodaya rejects school MCQ fee proof |
| `fest.schedule.published` | Sahodaya publishes event schedule (schools need to know) |
| `fest.chest_numbers.revealed` | Sahodaya reveals chest numbers |
| `membership.payment.approved` | School membership payment approved |
| `membership.payment.rejected` | School membership payment rejected |
| `membership.data.rejected` | Sahodaya rejects school's data submission |
| `membership.registration.opened` | Sahodaya opens registration window |
| `sports.winners.received` | Sahodaya receives school sports winner submissions |
| `training.fee.approved` | School training fee approved |
| `fest.appeal.received` | Sahodaya receives a student/teacher appeal (notify Sahodaya admin) |

### Notification Architecture Issue
`FestEventNotifier::notifySchool()` sends to all users with `role('school_admin')` at the school. If a school has staff users but no `school_admin` role user logged in, notifications never arrive. Also: students and teachers are never directly notified by any event (results published, schedule available, etc.) — only school admins are.

---

## 5. REAL-WORLD EVENT FLOWS — COMPLETENESS CHECK

### Kalotsav Flow
```
Sahodaya creates state program → propagates to Sahodaya clusters (via FestStateProgramController)
→ Sahodaya creates Kalotsav event (sahodaya level)
→ Sahodaya opens school round (school creates their own event via FestProgramController)
→ School registers students for school-round items
→ School enters marks (school-level)
→ Sahodaya promotes winners to Sahodaya event ← BUG: parent_event_id never set
→ School registers Sahodaya-level items
→ Sahodaya approves registrations, assigns chest numbers
→ Event day: attendance, marks, results
→ Sahodaya promotes winners to State event
→ State admin manages state round
```

**Gaps:**
- ❌ School event `parent_event_id` never linked to Sahodaya event (`FestProgramController::store()`)
- ❌ School has no "link my school event to Sahodaya parent" UI
- ❌ `promoteAllSchoolRounds()` silently fails due to above
- ✅ Sahodaya-level flow complete
- ⚠️ State-level: `StateFestProgramController` exists but state admin can't view results per Sahodaya or compile state winners list

### Sports Meet Flow
```
School holds internal sports meet → enters results by age group
→ School submits WINNERS to Sahodaya sports event (not all participants)
→ Sahodaya approves entries
→ Sahodaya marks attendance, enters measurement values, auto-ranks
→ Results published. ENDS HERE — no state promotion for sports.
```

**Gaps:**
- ❌ Sports `open` age group registration blocked (EligibilityService bug — `if ($itemAge === null || $itemAge === 'open') return error`)
- ❌ No dedicated "submit my school winners" UI — school registers to Sahodaya sports via generic registration page
- ❌ No school-level sports mark entry UI (school must use the generic fest events mark entry, not sports-specific measurement entry)
- ❌ No auto-rank trigger in school-level sports (only Sahodaya-level has it)
- ✅ Sahodaya-level sports: complete (measurement marks, athletic records, house championship, age groups)
- ✅ Sports correctly blocked from state promotion in UI

### MCQ Exam Flow
See Section 2 above. Summary: mostly complete, missing bulk operations and notifications.

### Training Program Flow
```
Sahodaya creates training program → opens registration
→ School registers teachers
→ School pays training fee → Sahodaya approves
→ Program conducted offline
→ Sahodaya marks attendance
→ Teacher downloads certificate (portal)
```

**Gaps:**
- ⚠️ Training fee upload UI exists on Sahodaya side for approval, but school-side training page (`School/Training/Index.vue`) — need to verify fee upload UI exists there
- ❌ No training audit logging
- ❌ No notification to teacher when their training registration is confirmed (template `training.registration.confirmed` exists, is it triggered for teachers? Only sends to school admin)
- ❌ Teacher portal shows training list but no direct certificate download for non-completed trainings

### Kids Fest & Teacher Fest Flow
Same as Kalotsav but band-based (Kids Fest) or teacher-based (Teacher Fest). Both use the same generic `FestRegistrationController`. Flows are functional but lack dedicated UX (see EVENT_PAGES_PLAN.md).

---

## 6. BUGS — COMPLETE LIST

### Critical 🔴

| # | Location | Bug |
|---|----------|-----|
| B1 | `FestRegistrationEligibilityService::validateSports()` | `open` age group blocked: `if ($itemAge === null \|\| $itemAge === 'open') return error`. Blocks ALL open-category sports items from registration. |
| B2 | `FestProgramController::store()` | `parent_event_id` NEVER set on school-created events. Breaks entire school-to-Sahodaya promotion chain for Kalotsav. |
| B3 | `FestQualificationService::resolveNextLevelEvent()` | For sports at Sahodaya level, returns state event if `state_program_id` exists — should return null (sports never goes to state). |
| B4 | `McqExamSessionService` | No scheduled command to auto-submit expired online MCQ exams. Students who abandon are stuck in `started` status forever. |
| B5 | `McqRankingService` | Calls `$mark->update(['rank' => $rank])`. The `rank` column was added in migration `2026_06_30_000002` — verify this ran on ALL tenant DBs, not just new ones. |

### High 🟠

| # | Location | Bug |
|---|----------|-----|
| B6 | `MembershipFeeCalculator::estimateFeeForSchool()` | Passes `0` as student count to `fromSlabs()` — school always sees ₹0 estimated variable fee. |
| B7 | `AnnualRegistrationController::begin()` | Never checks `SahodayaRegistrationWindow` open/close dates — schools can open registration any time regardless of Sahodaya window. |
| B8 | `LedgerPostingService::postJournal()` | Uses `AcademicYear::activeId()` for `financial_year_id`. Indian fiscal year is April–March, academic year is June–May — wrong year assigned to ledger entries between June and March. |
| B9 | `LedgerController::reports()` | Uses PostgreSQL-only `to_char(transaction_date, 'YYYY-MM')` — fails on MySQL. |
| B10 | `LedgerPostingService::postJournal()` | Skips posting if journal already exists for `(reference_type, reference_id)`. After a reversal + re-approval, ledger is never updated. |
| B11 | `SahodayaProfile::receipt_next_number` | Incremented in PHP without DB-level lock — race condition if two receipts are generated simultaneously (duplicate receipt numbers possible). |
| B12 | `FestSportsAgeGroup::defaultFees()` | Reads from config file, not `FestSportsAgeGroupConfig` DB table that UI manages. Age group fee changes in UI are ignored. |
| B13 | `McqRegistrationController` | No eligibility filter by `exam.eligibility_config` on school side — wrong-class students can be registered. |
| B14 | `FestEventNotifier` | `mcq.results.published` notification template exists but `McqExamController::publishResults()` never calls the notifier. Schools never get notified of MCQ result publication. |

### Medium 🟡

| # | Location | Bug |
|---|----------|-----|
| B15 | `LedgerController::index()` | `->limit(100)` with no pagination — transactions beyond 100 are invisible. |
| B16 | `MembershipFeeSlab` model | `due_date` field exists on model and migration but is never read anywhere in the codebase. |
| B17 | `Tenant` model | `membership_status` set to `'approved'` on first payment only — never updated on renewal, lapse, or rejection. |
| B18 | `StudentDashboardController` | Includes `festFees` (school-level event fees) in student portal data — these are not actionable by a student and shouldn't be visible. |
| B19 | `StudentMcqController::showExam()` | If `hall_ticket_no` is null, the attendance check is bypassed (`if attendance_status === 'pending' && filled(hall_ticket_no)`). Student can start exam without hall ticket / without attendance being marked. This may be intentional for online-only exams but undocumented and inconsistent. |
| B20 | `FestSchoolEventFeeController` | Multiple fee proofs create multiple `FestSchoolEventFee` rows (or `MembershipPayment` rows) with no supersede logic — stale rejected proofs remain visible and confuse status. |

---

## 7. INCOMPLETE FEATURES & FLOWS

### School Admin

| Feature | Status | Gap |
|---------|--------|-----|
| Kalotsav school-round creation | ⚠️ | Event created but not linked to Sahodaya parent |
| Sports school meet management | ⚠️ | Generic fest event UI, no sports-specific measurement entry |
| Sports winner submission to Sahodaya | ⚠️ | No dedicated "submit winners" flow; uses generic registration |
| MCQ bulk student registration | ❌ | One at a time only |
| MCQ hall ticket bulk download | ❌ | Only per-student in student portal |
| MCQ results per-exam detail page | ❌ | Single combined page, no per-exam drill-down |
| Training fee upload | ⚠️ | Need to verify UI exists on school training page |
| Fest registration deadline | ❌ | `registrationDeadlineReminder()` exists but no scheduled cron calls it |
| School-level event results (own events) | ✅ | Qualifiers page exists |
| Annual registration window check | ❌ | No window enforcement |
| Student portal provisioning email | ❌ | Password communicated out-of-band |

### Sahodaya Admin

| Feature | Status | Gap |
|---------|--------|-----|
| MCQ audit log / activity page | ❌ | No per-exam activity history |
| MCQ bulk mark import (CSV) | ❌ | One student at a time via Exam portal |
| MCQ attendance bulk import | ❌ | One student at a time |
| MCQ results publish notification | ❌ | Trigger missing |
| Sports auto-rank for school rounds | ❌ | Only Sahodaya-level events have auto-rank |
| School-round linking to parent event | ❌ | No UI to link school event to Sahodaya parent |
| Kalotsav school-round promote all | ❌ | Depends on parent_event_id being set (B2) |
| Training audit logging | ❌ | No audit trail |
| Fest schedule reminder cron | ❌ | Command exists but not scheduled |
| Financial year config | ❌ | No way to define fiscal year separately from academic year |
| Expense account heads | ❌ | Only STATE-REMITTANCE + AWARDS-FUND — no venue/catering/printing/prizes |
| Ledger pagination | ❌ | Hard-coded limit 100 |
| Certificate bulk generate | ✅ | Exists |
| Display screen / live scoreboard | ✅ | Complete |

### State Admin

| Feature | Status | Gap |
|---------|--------|-----|
| State program propagation | ✅ | Complete |
| State remittances | ✅ | Complete |
| Per-Sahodaya result aggregation | ❌ | No page to see all cluster results side-by-side |
| State winners list | ❌ | No compiled state-level winner export |
| Cross-Sahodaya student count / stats | ❌ | No analytics dashboard |
| Sports results view (read-only) | ❌ | No state-level sports summary |
| MCQ state visibility | ❌ | Not applicable yet, but no placeholder |

### Portal Users

| Role | What's Missing |
|------|---------------|
| Student | Sports-specific results page; MCQ scheduled exam countdown; no "upcoming events" list; no circular/notice board |
| Teacher | No MCQ exam view; no circulars view; no training schedule/venue/map details; no school announcement board |
| Judge | No assigned items list on dashboard; no schedule view; no appeals view; no results preview |
| Exam Controller | No bulk attendance import; no hall-assignment view; no auto-submit expired online exams |
| Group Admin | Complete ✅ (students, registrations, schedule, clashes, admit cards) |
| House Admin | Complete ✅ (students, registrations, ranking) |
| FestOps | Complete ✅ (attendance, registrations, marks, stage, gate, kitchen) |

---

## 8. UI / UX CHANGES NEEDED

### Critical UX Gaps

**1. No Unified Portal Login Page**
Students/teachers use `/login` and are redirected. Need a school-branded portal URL:
`/{schoolId}/portal` or `/login?school={id}` that pre-fills the school context and shows the school logo.

**2. Student / Teacher Not Notified Directly**
All notifications go to `school_admin` role users. Students and teachers who have portal accounts never receive direct notifications (results published, schedule out, deadline reminders). Need to route notifications to `student` and `teacher` users who have registered.

**3. School Dashboard Missing Critical Widgets**
Current school dashboard shows: active students, news, enquiries, TC requests, program summaries.
Missing:
- MCQ exam summary card (upcoming exams, registrations pending)
- Training summary card (upcoming programs)
- Overdue membership payment alert
- Pending event fee upload alerts
- "Action required" queue (similar to Sahodaya dashboard)

**4. Sahodaya Dashboard Action Queue Needs Labels**
Dashboard has `actionQueue` with counts but no direct links to resolve each item. User must navigate manually. Each item should be a clickable card linking to the relevant page.

**5. Sports Registration Page — Now Fixed (this session)**
Items were flat list; now grouped by age group → gender. ✅

**6. Sports Program Index Shows Catalog Language**
"CKSC seed", "Custom catalog items" — language is Kalotsav-specific. Now fixed (this session) for sports with conditional rendering. ✅

**7. Sahodaya Registrations Page (Sports) — Now Fixed (this session)**
Flat table with no age-group grouping. Now fixed. ✅

**8. MCQ Page (School Admin)**
One `School/Mcq/Index.vue` page handles everything. Should be split into:
- Hub with exam cards (available / registered / completed)
- Per-exam detail: students list, hall tickets, fee status, results
- Bulk registration by class

**9. Student MCQ Exam Page — Timer UX**
`Portal/Student/McqExam.vue` exists. Verify it shows: visible countdown timer, question navigator sidebar, answer saved confirmation, auto-submit warning. No evidence of these in the controller (sends `expiresAt` to frontend).

**10. Judge Portal Dashboard**
Shows events + assignments but no quick mark-entry count (how many marked vs. total assigned). Should show progress per item.

**11. No "What's Next" Guidance**
First-time school admins see a blank dashboard. There is a `setupStatus()` array but the UI for it (`School/Dashboard.vue`) needs to show a visible step-by-step checklist: Set school code → Add classes → Add students → Complete annual registration → Register for events.

**12. Membership Registration Flow**
Multi-step flow (begin → students → counts → teachers → submitTrack → payment → complete) has no breadcrumb or progress indicator in the URL. User loses context on refresh.

**13. Notification Bell**
`InAppNotification` model exists, `unreadCount()` exists in `NotificationService`. Verify the notification bell in the navbar actually shows real-time unread count via Inertia shared data, not a static 0.

**14. Mobile / Responsive**
All admin panels are desktop-oriented. Portal pages (student, teacher, judge) are used on mobile but are likely not optimized. Especially the MCQ exam page, which is a full-screen form.

---

## 9. CMS / PUBLIC SITE — STATUS

| Feature | Controller | Status |
|---------|-----------|--------|
| Public school site | `PublicSiteController` | ✅ |
| Sahodaya CMS pages | `SahodayaCmsPageController` | ✅ |
| News articles | `NewsArticleController` | ✅ |
| Gallery | `GalleryAlbumController` | ✅ |
| Events public page | `EventController` | ✅ |
| Fest public results/portal | `FestPortalController` | ✅ |
| Admission enquiry | `AdmissionEnquiryController` | ✅ |
| School application (new school joins) | `SchoolApplicationController` | ✅ |
| TC requests (online) | `TcRequestController` | ✅ |
| Certificate verification | `PublicCertificateController` | ✅ |
| SEO / sitemap | `SeoController` | ✅ |
| Site builder (drag & drop) | `SiteBuilderController` | ✅ (Sahodaya + School) |
| Live display screen | `DisplayScreenController` | ✅ |

CMS is largely complete. The main gap: **public fest results page** may not show MCQ results (only `FestPortalController` / `FestEvent` results). MCQ leaderboard is Sahodaya-admin-only, not publicly visible.

---

## 10. PRIORITY FIXES — RANKED

### Phase 1 — Fix First (Blockers)

| Priority | Item | File |
|----------|------|------|
| P1 | Fix sports `open` age group bug | `FestRegistrationEligibilityService::validateSports()` |
| P2 | Set `parent_event_id` in `FestProgramController::store()` | `FestProgramController` |
| P3 | Trigger `mcq.results.published` notification | `McqExamController` after `results_published = true` |
| P4 | Add scheduled command for deadline reminders | New Artisan command + `schedule()` |
| P5 | Fix `MembershipFeeCalculator` always passing `0` student count | `MembershipFeeCalculator::estimateFeeForSchool()` |
| P6 | Verify `rank` column migration ran on all tenant DBs | `2026_06_30_000002` |

### Phase 2 — High Impact

| Priority | Item |
|----------|------|
| P7 | Auto-submit expired online MCQ exams (scheduled command) |
| P8 | MCQ eligibility filter on school registration page |
| P9 | MCQ bulk student registration by class |
| P10 | Add audit logs to MCQ, training, school-level registration actions |
| P11 | Fix ledger financial year (fiscal vs academic) |
| P12 | Add notification: `student.portal.provisioned` + `teacher.portal.provisioned` (send email on provision) |
| P13 | Fix Sahodaya registration window enforcement in `AnnualRegistrationController::begin()` |
| P14 | Fix receipt number race condition (DB-level lock or sequence) |

### Phase 3 — UX & Completeness

| Priority | Item |
|----------|------|
| P15 | School dashboard: add MCQ/training/action-queue widgets |
| P16 | Student portal: remove `festFees`, add direct notifications, sports results |
| P17 | MCQ school page: split into hub + per-exam detail |
| P18 | Sports school-level: add measurement mark entry + winner submission UI |
| P19 | Missing notification templates (14 listed in Section 4) |
| P20 | Ledger: remove 100 limit, add pagination |
| P21 | Fix MySQL `to_char()` in Ledger reports |
| P22 | Add expense account heads to ledger catalog |
| P23 | State admin: per-Sahodaya result view + state winners export |
| P24 | Portal login: branded school-specific URL |
| P25 | Teacher / student direct notifications for results, schedule |

---

## 11. SUMMARY SCORECARD

| Area | Status | Score |
|------|--------|-------|
| Role definitions & permissions | Complete and well-structured | 9/10 |
| Login / authentication routing | Works but no portal-specific URL | 7/10 |
| Kalotsav full flow | Broken at school→Sahodaya link | 5/10 |
| Sports full flow | Open-category bug; no dedicated school UI | 6/10 |
| MCQ full flow | Mostly working; missing bulk ops, notifications | 7/10 |
| Training full flow | Functional; missing notifications, audit | 7/10 |
| Kids Fest / Teacher Fest | Functional via generic flow | 7/10 |
| Activity logs / audit trails | Fest events well-logged; MCQ/training/school side missing | 5/10 |
| Notifications | Templates defined; multiple trigger gaps | 5/10 |
| Student portal | Core features present; UX and sports/MCQ gaps | 6/10 |
| Teacher portal | Core features present; MCQ/circular gaps | 7/10 |
| Judge / Exam / FestOps portals | Functional; minor UX gaps | 8/10 |
| School admin dashboard | Weak; missing MCQ/training/action queue | 5/10 |
| Sahodaya admin dashboard | Good action queue; needs program cards | 7/10 |
| State admin | Minimal; missing results aggregation | 4/10 |
| CMS / public site | Comprehensive | 9/10 |
| Ledger / finance | Core works; bugs in year assignment, pagination | 6/10 |
| Membership flow | Works; window enforcement missing | 7/10 |
| **Overall** | | **6.5/10** |
