# Implementation Plan — Sahodaya Connect Platform
> Based on FULL_AUDIT_REPORT.md · Status as of 2026-06-30

---

## What Was Fixed in the Last Session (already in codebase)

| # | What | File |
|---|------|------|
| ✅ | Sahodaya Registrations.vue sports view — sportsGroupedRegistrations + genderLabel added | `Sahodaya/Events/Registrations.vue` |
| ✅ | FestProgramController.storeMark — corrupted method restored, typo `festack_event_items` fixed | `SchoolAdmin/FestProgramController.php` |
| ✅ | FestEventFeeResolver.normalizeAgeGroupFees — now accepts tenantId so DB fees override config | `FestEventFeeResolver.php` |
| ✅ | FestEventSettingsController — passes sahodaya->id to normalizeEventFeeSettings | `FestEventSettingsController.php` |
| ✅ | Sports open-category registration — was never blocked (audit false-positive, code correct) | — |
| ✅ | parent_event_id auto-linking — already auto-detects Sahodaya parent in store() | — |
| ✅ | Sports never promoted to state — already returns null in resolveNextLevelEvent | — |
| ✅ | MembershipFeeCalculator — already calls estimateStudentCount() correctly | — |
| ✅ | Ledger pagination — already uses paginate(50), not limit(100) | — |
| ✅ | Ledger MySQL compat — already uses driver-conditional DATE_FORMAT / to_char | — |
| ✅ | Ledger fiscal year — already has FinancialYear support class (April–March) | — |

---

## Phase 1 — Critical Bug Fixes (Do First)

### 1.1 Receipt Number Race Condition
**File:** `app/Models/SahodayaProfile.php`
**Problem:** `receipt_next_number` is read and incremented in PHP without a DB lock. Concurrent receipts → duplicate numbers.
**Fix:** Wrap in `DB::transaction()` with pessimistic lock:
```php
// In SahodayaProfile or ReceiptNumberService:
DB::transaction(function () use ($sahodayaId) {
    $profile = SahodayaProfile::where('tenant_id', $sahodayaId)->lockForUpdate()->first();
    $next = $profile->receipt_next_number;
    $profile->increment('receipt_next_number');
    return $next;
});
```
**Impact:** Prevents duplicate receipt numbers on simultaneous fee approvals.

---

### 1.2 Sahodaya Registration Window Enforcement
**File:** `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php`
**Problem:** `begin()` method never checks whether a `SahodayaRegistrationWindow` is open. Schools can start registration any time.
**Fix:** At the top of `begin()` (or `store()`) add:
```php
$window = SahodayaRegistrationWindow::where('sahodaya_id', $sahodaya->id)
    ->where('academic_year', $academicYear)
    ->first();
$windowService = app(MembershipRegistrationWindowService::class);
if ($blockReason = $windowService->blockReason($window)) {
    return back()->withErrors(['window' => $blockReason]);
}
```
The `index()` already shows `$windowBlockReason` in the view — just needs enforcement in the write action.
**Impact:** Prevents schools from bypassing Sahodaya-controlled registration dates.

---

### 1.3 MCQ Results Published Notification Not Firing
**File:** `app/Http/Controllers/SahodayaAdmin/McqExamController.php`
**Problem:** Template `mcq.results.published` exists in seeder but `publishResults()` never fires it.
**Fix:** After setting `results_published = true`, call:
```php
app(NotificationService::class)->notifyFromTemplate(
    'mcq.results.published',
    $exam->registeredSchools()->pluck('id')->toArray(),
    ['exam_title' => $exam->title, 'results_url' => '...']
);
```
**Impact:** Schools never know MCQ results are ready — this is a critical UX gap.

---

### 1.4 MCQ Auto-Submit Expired Online Exams
**File:** Create `app/Console/Commands/AutoSubmitExpiredMcqExams.php`
**Problem:** Students who start an online MCQ exam and close the browser are stuck in `started` status forever.
**Fix:** New Artisan command:
```php
class AutoSubmitExpiredMcqExams extends Command
{
    protected $signature = 'mcq:auto-submit-expired';

    public function handle(McqExamSessionService $sessionService)
    {
        $expired = McqExamSession::where('status', 'started')
            ->where('expires_at', '<', now())
            ->get();

        foreach ($expired as $session) {
            $sessionService->autoSubmit($session);
            $this->info("Auto-submitted session #{$session->id}");
        }
    }
}
```
Register in `Console/Kernel.php`:
```php
$schedule->command('mcq:auto-submit-expired')->everyFiveMinutes();
```
Also add `autoSubmit()` method to `McqExamSessionService` if it doesn't exist.
**Impact:** Prevents dangling exam sessions; ensures attendance and marks are properly recorded.

---

### 1.5 MCQ Eligibility Filter on School Registration Page
**File:** `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php`
**Problem:** School can register any student for an MCQ exam regardless of the exam's `eligibility_config` (class group, etc.).
**Fix:** In `McqRegistrationController::index()` or the student-listing endpoint, filter by the exam's eligibility config before sending to the Vue page:
```php
// Read exam->eligibility_config and filter $students accordingly
$eligible = $students->filter(fn ($s) => $this->mcqEligibilityService->isEligible($s, $exam));
```
Create `McqEligibilityService` or add a method to existing MCQ service.
**Impact:** Prevents wrong-class students from being accidentally registered.

---

## Phase 2 — Missing Audit Logging

Add `PlatformAuditLogger` calls to all unlogged mutations. Create a `'mcq'` category in `AuditLogCatalog` so MCQ actions are visible as their own category.

### 2.1 School-Level Fest Registration
**File:** `app/Http/Controllers/SchoolAdmin/FestRegistrationController.php`

```php
// In store():
$audit->festEvent($event, 'registration', 'fest.registration.submitted', "School submitted registration for {$item->title}", [
    'item_id' => $item->id, 'school_id' => $this->school->id,
]);

// In withdraw():
$audit->festEvent($event, 'registration', 'fest.registration.withdrawn', "School withdrew registration #{$registration->id}", [
    'registration_id' => $registration->id,
]);
```

### 2.2 MCQ Exam Controller
**File:** `app/Http/Controllers/SahodayaAdmin/McqExamController.php`

```php
// After create:
$audit->log('mcq', 'mcq.exam.created', "MCQ exam '{$exam->title}' created", ['exam_id' => $exam->id]);

// After update:
$audit->log('mcq', 'mcq.exam.updated', "MCQ exam '{$exam->title}' updated", ['exam_id' => $exam->id]);

// After publishResults:
$audit->log('mcq', 'mcq.results.published', "Results published for '{$exam->title}'", ['exam_id' => $exam->id]);
```

### 2.3 Exam Portal (ExamOpsController)
**File:** `app/Http/Controllers/Portal/ExamOpsController.php`

```php
// In storeAttendance():
$audit->log('mcq', 'mcq.attendance.marked', "Attendance marked for student #{$studentId}", ['exam_id' => $examId]);

// In storeMark():
$audit->log('mcq', 'mcq.mark.entered', "Mark entered for student #{$studentId}", ['exam_id' => $examId, 'score' => $score]);
```

### 2.4 Judge Portal
**File:** `app/Http/Controllers/Portal/JudgeDashboardController.php`

```php
// In storeMark():
$audit->log('fest', 'fest.judge.mark.saved', "Judge entered mark for item #{$itemId}", ['item_id' => $itemId, 'participant_id' => $participantId]);
```

### 2.5 Student MCQ Portal
**File:** `app/Http/Controllers/Portal/StudentMcqController.php`

```php
// In startExam():
$audit->log('mcq', 'mcq.student.started', "Student #{$student->id} started exam '{$exam->title}'", ['exam_id' => $exam->id]);

// In submitExam():
$audit->log('mcq', 'mcq.student.submitted', "Student #{$student->id} submitted exam '{$exam->title}'", ['exam_id' => $exam->id]);
```

### 2.6 AuditLogCatalog — Add MCQ category
**File:** `app/Services/Audit/AuditLogCatalog.php`

```php
// In categoryForAction():
'mcq' => 'MCQ Exams',
// and map mcq.* actions to 'mcq' category
```

---

## Phase 3 — Missing Notification Triggers

Add these to `NotificationTemplatesSeeder` if template slugs don't exist, and wire up the trigger:

| # | Template slug | Where to trigger | What to notify |
|---|--------------|-----------------|----------------|
| N1 | `student.portal.provisioned` | `StudentPortalProvisioner::provision()` after user created | Send welcome email with login URL + password hint |
| N2 | `teacher.portal.provisioned` | `TeacherPortalProvisioner::provision()` after user created | Send welcome email |
| N3 | `mcq.results.published` | `McqExamController::publishResults()` | Notify registered school admins |
| N4 | `mcq.registration.confirmed` | `McqRegistrationController::store()` | Confirm student registered for exam |
| N5 | `mcq.fee.approved` | MCQ fee approval handler | School fee accepted |
| N6 | `mcq.fee.rejected` | MCQ fee rejection handler | School fee rejected |
| N7 | `fest.schedule.published` | `FestScheduleController::publish()` | Notify schools when schedule is out |
| N8 | `fest.chest_numbers.revealed` | `FestChestNumberController::reveal()` | Notify schools |
| N9 | `membership.payment.approved` | Membership payment approval | School membership accepted |
| N10 | `membership.payment.rejected` | Membership payment rejection | School membership rejected |
| N11 | `sports.winners.received` | When Sahodaya accepts school sports winners | Confirm submission |
| N12 | Deadline reminder cron | New `SendRegistrationDeadlineReminders` command | `FestEventNotifier::registrationDeadlineReminder()` |

**Deadline reminder cron:**
```php
// app/Console/Commands/SendRegistrationDeadlineReminders.php
class SendRegistrationDeadlineReminders extends Command
{
    protected $signature = 'fest:send-deadline-reminders';

    public function handle(FestEventNotifier $notifier)
    {
        // Find events whose registration_close is in 24–48 hours
        $events = FestEvent::whereIn('status', ['registration_open'])
            ->whereBetween('registration_close', [now()->addHours(24), now()->addHours(48)])
            ->get();

        foreach ($events as $event) {
            $notifier->registrationDeadlineReminder($event);
        }
    }
}
```
Register: `$schedule->command('fest:send-deadline-reminders')->dailyAt('08:00');`

---

## Phase 4 — Portal UX Fixes

### 4.1 Student Dashboard — Remove festFees
**File:** `app/Http/Controllers/Portal/StudentDashboardController.php`
**Problem:** Student dashboard receives school-level `festFees` — not actionable by a student.
**Fix:** Remove `festFees` from the data passed to the student portal. Replace with student-specific data:
```php
'myRegistrations' => FestRegistration::whereHas('participants', fn ($q) => $q->where('student_id', $student->id))
    ->with('item', 'event')
    ->latest()
    ->take(5)
    ->get(),
'upcomingEvents' => FestEvent::where('tenant_id', $sahodayaId)
    ->whereIn('status', ['registration_open', 'ongoing'])
    ->orderBy('event_start')
    ->take(3)
    ->get(['id', 'title', 'event_type', 'event_start', 'status']),
```
**File:** `resources/js/Pages/Portal/Student/Dashboard.vue`
Remove `festFees` section, add "My registrations" and "Upcoming events" cards.

---

### 4.2 MCQ Hub Page (School Admin)
**File:** `app/Http/Controllers/SchoolAdmin/McqController.php` → `hub()` method
**Problem:** `McqController::hub()` delegates to `McqRegistrationController::index()`. There is no real MCQ hub.
**Fix:** Build a proper hub response:
```php
public function hub()
{
    $academicYear = AcademicYear::forSchool($this->school);

    $exams = McqExam::where('tenant_id', $this->school->parent_id)
        ->where('status', '!=', 'draft')
        ->orderByDesc('exam_date')
        ->get();

    $registrationsByExam = McqRegistration::where('school_id', $this->school->id)
        ->whereIn('exam_id', $exams->pluck('id'))
        ->with('student')
        ->get()
        ->groupBy('exam_id');

    $feesByExam = McqSchoolFee::where('school_id', $this->school->id)
        ->whereIn('exam_id', $exams->pluck('id'))
        ->get()
        ->keyBy('exam_id');

    $marksByExam = McqMark::whereHas('registration', fn ($q) => $q->where('school_id', $this->school->id))
        ->get()
        ->groupBy('exam_id');

    return $this->inertia('School/Mcq/Hub', [
        'exams'             => $exams,
        'registrationsByExam' => $registrationsByExam,
        'feesByExam'        => $feesByExam,
        'marksByExam'       => $marksByExam,
        'school'            => $this->school->only('id', 'name'),
    ]);
}
```
**Create:** `resources/js/Pages/School/Mcq/Hub.vue` with exam cards showing:
- Exam title + date + status
- Registered students count + eligible count
- Fee status (pending / approved / rejected)
- Hall ticket download button (if approved)
- Marks/results link (if published)

---

### 4.3 School Dashboard — Add MCQ + Training + Action Queue
**File:** `app/Http/Controllers/SchoolAdmin/DashboardController.php`
**Problem:** School dashboard has no MCQ summary, no training summary, no pending actions.
**Fix:** Add to the dashboard response:

```php
$pendingActions = [];

// Pending fee uploads
$pendingFees = FestSchoolEventFee::where('school_id', $this->school->id)
    ->where('status', 'pending')
    ->count();
if ($pendingFees) $pendingActions[] = ['type' => 'fee_upload', 'count' => $pendingFees, 'label' => 'Event fees awaiting upload'];

// MCQ upcoming exams
$upcomingMcq = McqExam::where('tenant_id', $this->school->parent_id)
    ->whereIn('status', ['published', 'registration_open'])
    ->orderBy('exam_date')
    ->take(3)
    ->get(['id', 'title', 'exam_date', 'status']);

// Training upcoming
$upcomingTraining = TrainingProgram::where('tenant_id', $this->school->parent_id)
    ->whereIn('status', ['published', 'registration_open'])
    ->orderBy('start_date')
    ->take(3)
    ->get(['id', 'title', 'start_date', 'status']);

// Membership status
$membershipStatus = Registration::where('school_id', $this->school->id)
    ->where('academic_year', $academicYear)
    ->value('registration_status');
if (in_array($membershipStatus, ['payment_pending', null])) {
    $pendingActions[] = ['type' => 'membership', 'count' => 1, 'label' => 'Annual registration incomplete'];
}
```

**File:** `resources/js/Pages/Admin/School/Dashboard.vue` — Add:
- MCQ summary card (3 upcoming exams, registration count)
- Training summary card
- "Action required" card list (pending fees, incomplete membership)

---

## Phase 5 — Portal Feature Gaps

### 5.1 Student Portal — Sports Results Page
**Create:** `resources/js/Pages/Portal/Student/SportsResults.vue`
Show sports events the student is registered for with: event name, item, position, grade, athletic records.

### 5.2 Teacher Portal — Missing MCQ View
**File:** `resources/js/Pages/Portal/Teacher/Dashboard.vue`
Add a card showing upcoming MCQ exams the teacher has contributed question banks to, and a link to question bank management.

### 5.3 Teacher Portal — Training Schedule Detail
**File:** `resources/js/Pages/Portal/Teacher/Training.vue`
Add: venue, map link, date/time, attendance status per session, certificate download when completed.

### 5.4 Judge Portal — Item Progress Widget
**File:** `resources/js/Pages/Portal/Judge/Dashboard.vue`
Add a widget showing "X of Y items marked" per category assigned to this judge.

### 5.5 Exam Controller Portal — Bulk Attendance Import
**File:** `app/Http/Controllers/Portal/ExamOpsController.php` → new `importAttendance()` action
Accept CSV with `student_id,hall_ticket_no,attendance_status` columns. Register route and add upload UI in the exam portal.

---

## Phase 6 — CMS & Login UX

### 6.1 School-Branded Portal Login URL
**Route:** Add `/s/{schoolCode}/login` or `/{schoolId}/portal` that sets a cookie/session with the school context and redirects to `/login`.
**File:** `routes/web.php` + new `PortalLoginController::school()` method
Show school name, logo in the login form when accessed via this URL.

### 6.2 Student/Teacher Self-Service Password Reset
**Problem:** Portal users have no "Forgot password" link visible on the login page.
**Fix:** The standard Laravel password reset already exists. Add the link to `Portal/Auth/Login.vue` or the main login page when the user's role is detected as `student`/`teacher`.

### 6.3 Direct Student/Teacher Notifications
**Problem:** `FestEventNotifier::notifySchool()` only notifies `school_admin` role. Students and teachers with portal accounts never get results notifications.
**Fix:** When results publish, also notify portal users:
```php
// FestEventNotifier::resultsPublished()
$studentIds = FestRegistration::where('event_id', $event->id)
    ->whereIn('status', ['approved'])
    ->with('participants.student.portalUser')
    ->get()
    ->pluck('participants.*.student.portalUser')
    ->flatten()
    ->filter()
    ->pluck('id');

NotificationService::sendToUsers($studentIds, 'fest.results.published', [...]);
```

---

## Phase 7 — State Admin Completeness

### 7.1 Per-Sahodaya Results View
**File:** New `StateResultsController.php` in state admin
**Route:** `/admin/state/programs/{program}/results`
**View:** Table of all Sahodaya clusters with their top-3 per item, filterable by event type and item.

### 7.2 State Winners Export
**Add:** Export action that generates a PDF or CSV of all state-level winners across clusters for a given program.

### 7.3 Sports State Summary (Read-Only)
**Add:** State admin can view sports results from all Sahodaya clusters (read-only) for annual reporting.

---

## Phase 8 — MCQ Bulk Operations

### 8.1 Bulk Student Registration by Class
**File:** `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php` → new `storeByClass()` action
Accept `class_id` and `exam_id`, register all eligible students from that class.
**Route:** `POST /school-admin/{school}/mcq/{exam}/register-by-class`

### 8.2 Bulk Hall Ticket Download (School)
**File:** `app/Http/Controllers/SchoolAdmin/McqHallTicketController.php` → new `downloadAll()` action
Generate a single PDF with all hall tickets for a school for a given exam.
Use existing `pdf` skill pipeline (pdfgen service or wkhtmltopdf).

### 8.3 MCQ Ranking Auto-Trigger After Marks Complete
**File:** `app/Http/Controllers/SahodayaAdmin/McqExamController.php`
After `storeMark()` completes, check if all attendance-marked students have marks. If yes, auto-trigger `McqRankingService::rankExam()` or show a "All marks entered — compute rankings" button.

---

## Phase 9 — MCQ Activity Log Page

### 9.1 Per-Exam Activity Log (Sahodaya Admin)
**File:** New `resources/js/Pages/Admin/Sahodaya/Mcq/Activity.vue`
**Controller:** Add `activity()` action to `McqExamController`
Query `audit_logs` where `context_type = 'mcq'` and `context_id = $exam->id`, paginated.
Similar to existing `EventPageActivityLog` component pattern.

---

## Execution Order Summary

| Phase | Focus | Effort | Risk |
|-------|-------|--------|------|
| **Phase 1** | Critical bugs (receipt lock, window enforcement, MCQ notify, auto-submit, eligibility) | 1–2 days | High impact if skipped |
| **Phase 2** | Audit logging (MCQ, school registration, portals) | 1 day | Medium |
| **Phase 3** | Notification triggers + deadline cron | 1 day | Medium |
| **Phase 4** | Portal UX (student dashboard, MCQ hub, school dashboard) | 2 days | High UX impact |
| **Phase 5** | Portal feature gaps (sports results, teacher MCQ, judge progress) | 1–2 days | Medium |
| **Phase 6** | CMS / Login UX (school login URL, password reset, direct notifications) | 1 day | Medium |
| **Phase 7** | State admin completeness | 1–2 days | Low urgency |
| **Phase 8** | MCQ bulk ops (class registration, hall ticket PDF, auto-rank) | 2 days | High UX impact |
| **Phase 9** | MCQ activity log page | 0.5 days | Low |
| **Total** | | ~11–14 days | |

---

## Files to Create (New)

| File | Purpose |
|------|---------|
| `app/Console/Commands/AutoSubmitExpiredMcqExams.php` | Auto-submit expired MCQ sessions |
| `app/Console/Commands/SendRegistrationDeadlineReminders.php` | Daily deadline reminder cron |
| `app/Services/Events/McqEligibilityService.php` | MCQ student eligibility filter |
| `app/Http/Controllers/StateAdmin/StateResultsController.php` | State-level results aggregation |
| `resources/js/Pages/School/Mcq/Hub.vue` | Real MCQ hub page for school admin |
| `resources/js/Pages/Portal/Student/SportsResults.vue` | Student sports results page |

## Files to Modify (Key)

| File | What changes |
|------|-------------|
| `AnnualRegistrationController.php` | Add window enforcement in write actions |
| `SahodayaProfile.php` or `FeeReceiptService.php` | Wrap receipt number in DB::transaction + lockForUpdate |
| `McqExamController.php` | Add publishResults notification + audit log |
| `McqRegistrationController.php` | Add eligibility filter, add bulk-by-class |
| `StudentPortalProvisioner.php` | Send provisioned notification after create |
| `TeacherPortalProvisioner.php` | Send provisioned notification after create |
| `FestScheduleController.php` | Add schedule.published notification |
| `FestChestNumberController.php` | Add chest_numbers.revealed notification |
| `StudentDashboardController.php` | Remove festFees, add myRegistrations + upcomingEvents |
| `DashboardController.php` (school) | Add MCQ, training, and action-queue data |
| `McqController.php` (school) | Build real hub() response |
| `ExamOpsController.php` | Add audit logging, bulk attendance import |
| `JudgeDashboardController.php` | Add audit logging |
| `StudentMcqController.php` | Add audit logging for start/submit |
| `AuditLogCatalog.php` | Add 'mcq' category |
| `Console/Kernel.php` | Register new scheduled commands |
