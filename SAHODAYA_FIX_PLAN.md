# Sahodaya Platform — Complete Fix & Feature Plan

> Derived from full codebase analysis + real-world Sahodaya workflow review  
> Covers: Sports, MCQ, Membership, Fees, Ledger/Accounts, User Flows  
> Status: Planning — not yet started

---

## How to read this document

Each item has:
- **What's broken / missing** — the exact gap in current code
- **Fix** — what needs to be built/changed
- **Files touched** — key files affected
- Priority: 🔴 Critical (breaks real usage) · 🟠 High · 🟡 Medium

---

## Part 1 — Sports Workflow

### S1 🔴 `open` age group blocks all sports registration
**What's broken:** `FestRegistrationEligibilityService::validateSports()` line 194 returns an error
if `$itemAge === null || $itemAge === 'open'`. Any sports item without a strict age group
(open relay, tug of war, mixed events) fails registration entirely.

**Fix:**
- Change logic: if `$itemAge === 'open'` or null, allow registration (require only that `student->dob` exists)
- Only error if age group is set to a specific Under-N category and student doesn't qualify

**Files:**
- `app/Services/Events/FestRegistrationEligibilityService.php` — `validateSports()` method

---

### S2 🟠 School sports events not linked to Sahodaya parent
**What's broken:** When a school creates a school-level sports event via `FestProgramController::store()`,
`parent_event_id` is never set. `FestQualificationService::promoteAllSchoolRounds()` queries
`where('parent_event_id', $parent->id)` to pull winners — finds zero school rounds.

**Fix:**
- Add optional `parent_event_id` field to `FestProgramController::store()` form
- When a Sahodaya sports event exists, show a dropdown on school event creation: "Link to Sahodaya event"
- Sahodaya admin should also be able to retroactively link school events to the parent event
- Add a route: `POST /sahodaya-admin/{tenant}/events/{event}/link-school-round` accepting `school_event_id`

**Files:**
- `app/Http/Controllers/SchoolAdmin/FestProgramController.php` — `store()`
- `app/Http/Controllers/SahodayaAdmin/FestEventController.php` — new `linkSchoolRound()` action
- `routes/web.php`

---

### S3 🟠 No winner-gate: any student can register at Sahodaya sports directly
**What's broken:** Schools can register any student for Sahodaya-level sports events without
having won anything at school level. The system doesn't enforce that Sahodaya sports registrations
come only from school-level winners.

**Fix:**
- Add a `FestParticipationPolicy` setting: `require_school_qualification = true/false`
- When enabled on a Sahodaya sports event, `FestRegistrationEligibilityService` checks:
  `FestQualification::where('participant->student_id', $student->id)->where('next_level_event_id', $event->id)->exists()`
- If no qualification record found, reject the registration with a clear message

**Files:**
- `app/Models/FestParticipationPolicy.php` — add `require_school_qualification` field
- `app/Services/Events/FestRegistrationEligibilityService.php` — add qualification check
- `database/migrations/tenant/` — migration to add column

---

### S4 🟡 No auto-ranking for sports measurements
**What's broken:** For athletics (100m sprint, long jump etc.), staff manually enter `position`
in `FestMark`. There's no auto-sort by `measurement_value` (lowest time for sprints,
highest distance for throws). Wrong positions = wrong promotion.

**Fix:**
- Add `competition_format` to `FestEventItem` already exists — use `record_direction` concept
- New action in `FestMarkEntryController`: `autoRankByMeasurement(FestEvent $event, FestEventItem $item)`
  - Fetches all marks for the item, sorts by `measurement_value` (asc for time/weight, desc for distance/height)
  - Assigns `position` 1,2,3... and saves
- Show "Auto-rank by measurement" button on Sahodaya marks entry page for sports events

**Files:**
- `app/Http/Controllers/SahodayaAdmin/FestMarkEntryController.php` — add `autoRank()` action
- `app/Services/Events/FestMarkSaveService.php` — add ranking logic
- `routes/web.php`

---

### S5 🟡 Athletic record not auto-flagged on mark entry
**What's broken:** `FestAthleticRecord` and `FestRecordBreak` models exist but saving a mark
via `FestMarkEntryController` doesn't check if the new measurement beats the existing record.
Admin must manually go to a separate screen to update records.

**Fix:**
- After saving a `FestMark` with `measurement_value`, call a service:
  `FestAthleticRecordService::checkAndUpdateRecord($mark, $item, $event)`
- If new value beats the existing record (respecting `record_direction`), create a `FestRecordBreak`
  and update `FestAthleticRecord` — flag it as a new record in the UI response

**Files:**
- `app/Services/Events/FestMarkSaveService.php` — call record check after save
- `app/Http/Controllers/SahodayaAdmin/FestMarkEntryController.php`
- New: `app/Services/Events/FestAthleticRecordService.php`

---

### S6 🟡 Sports state-level progression not restricted
**What's broken:** Nothing prevents a Sahodaya admin from linking a sports event to a state program
and promoting winners to state level. User confirmed: sports ends at Sahodaya.

**Fix:**
- In `FestQualificationService::resolveNextLevelEvent()`, for `event_type === 'sports'`, always return `null`
  when `level_round === 'sahodaya'` (no state promotion)
- In `FestStateProgram` creation (admin panel), add validation: sports type cannot include `state` in `conduct_levels`
- Show informational note in Sahodaya sports event UI: "Sports ends at Sahodaya level"

**Files:**
- `app/Services/Events/FestQualificationService.php` — `resolveNextLevelEvent()`
- `app/Http/Controllers/Admin/StateFestProgramController.php` — validation
- `app/Http/Controllers/SahodayaAdmin/FestEventController.php` — UI hint

---

### S7 🟡 House point system missing sports-specific scoring
**What's broken:** `FestPointRule`, `FestHouse`, `FestHouseSchool` exist but point rules
don't distinguish between track events (individual points) and field events or relays (team points).
No athletics points table (standard IAAF school points for 1st/2nd/3rd etc.).

**Fix:**
- Add `points_table` preset option to `FestPointRule`: `custom`, `athletics_standard`, `sahodaya_default`
- For `athletics_standard`: 1st=8pts, 2nd=7pts, 3rd=6pts, 4th=5pts, 5th=4pts, 6th=3pts (standard school athletics)
- Auto-apply house points when marks are saved and positions assigned

**Files:**
- `app/Models/FestPointRule.php`
- `app/Services/Events/FestChampionshipService.php` (or equivalent)
- `database/migrations/tenant/`

---

## Part 2 — MCQ Exam Workflow (Sahodaya-level only)

### M1 🟠 No rank/position field on McqMark
**What's broken:** `McqMark` has `score`, `percentage`, `grade` — but no `position` or `rank`.
After results are published, there's no way to identify toppers or produce a ranked list.

**Fix:**
- Add `rank` integer column to `mcq_marks` table
- After results are published (`McqExamController::publishResults()`), auto-compute ranks:
  - Sort all marks for the exam by `score` DESC, then `correct_count` DESC as tiebreaker
  - Assign `rank` 1, 2, 3... (handle ties: same rank for equal scores)
- Expose ranked results in the Sahodaya exam view

**Files:**
- `database/migrations/tenant/` — add `rank` to `mcq_marks`
- `app/Models/McqMark.php` — add to `$fillable`
- `app/Http/Controllers/SahodayaAdmin/McqExamController.php` — `publishResults()` triggers ranking
- New: `app/Services/Mcq/McqRankingService.php`

---

### M2 🟠 No MCQ class/category eligibility filter
**What's broken:** Any active student from any class can be registered to any MCQ exam.
No class group, standard, or category check on `McqRegistrationController::store()`.

**Fix:**
- Add `eligibility_config` JSON column to `mcq_exams`: `{ class_groups: ["hs","hss"], gender: "open" }`
- In `McqRegistrationController::store()` and `bulkStore()`, validate student against eligibility config
- In Sahodaya exam creation form, allow admin to set eligible class groups

**Files:**
- `database/migrations/tenant/` — add `eligibility_config` to `mcq_exams`
- `app/Models/McqExam.php` — cast + fillable
- `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php` — add eligibility check
- `app/Http/Controllers/SahodayaAdmin/McqExamController.php` — update form fields

---

### M3 🟡 No MCQ leaderboard / topper export
**What's broken:** No aggregate ranked view of all students across schools for a given exam.
Results are per-registration only. Sahodaya cannot see "Top 10 students across all schools."

**Fix:**
- New route + controller action: `McqExamController::leaderboard(McqExam $exam)`
- Returns top-N scorers with student name, school, score, rank, grade
- Export to CSV/Excel: school-wise results breakdown + overall topper list
- Only available when `results_published = true`

**Files:**
- `app/Http/Controllers/SahodayaAdmin/McqExamController.php` — `leaderboard()` action
- `routes/web.php`

---

### M4 🟡 MCQ fee: per-student proof upload is impractical for bulk registration
**What's broken:** Each `McqRegistration` has its own `fee_receipt_id`. A school registering
30 students needs 30 separate payment proofs and 30 individual approvals.

**Fix:**
- Add school-level MCQ fee aggregation similar to `FestSchoolEventFee`
- New model: `McqSchoolFee` with `exam_id`, `school_id`, `student_count`, `total_due`, `fee_receipt_id`, `status`
- When school bulk-registers, compute the total and create one `McqSchoolFee` record
- School uploads one payment proof for the batch
- Sahodaya approves one record — all linked registrations are marked paid

**Files:**
- New model: `app/Models/McqSchoolFee.php`
- `database/migrations/tenant/` — `mcq_school_fees` table
- `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php` — rework payment flow
- `app/Http/Controllers/SahodayaAdmin/McqExamController.php` — school fee review

---

## Part 3 — Membership & Annual Registration

### R1 🔴 Variable fee preview always shows ₹0
**What's broken:** `MembershipFeeCalculator::estimateFeeForSchool()` passes `0` as student count
to `fromSlabs()`. Schools see ₹0 as estimated fee regardless of their actual student count.

**Fix:**
- `estimateFeeForSchool()` must count actual students from DB:
  `Student::where('tenant_id', $school->id)->where('status', 'active')->count()`
- If no students yet, fetch from latest `SchoolYearStudentCount` for prior year
- Show "estimated based on N current students" note in the UI

**Files:**
- `app/Services/Membership/MembershipFeeCalculator.php` — `estimateFeeForSchool()`
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php` — pass estimate to view

---

### R2 🟠 Registration window not enforced
**What's broken:** `SahodayaRegistrationWindow` dates are stored but `AnnualRegistrationController::begin()`
never checks whether the window is open.

**Fix:**
- In `begin()`, check:
  ```php
  if ($window && now()->lt($window->registration_starts_at)) {
      return back()->with('error', 'Registration has not opened yet.');
  }
  if ($window && now()->gt($window->registration_ends_at)) {
      return back()->with('error', 'Registration window has closed.');
  }
  ```
- Add a "force open" override for Sahodaya admin (late registrations)
- Show window open/close dates prominently on school registration page

**Files:**
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php` — `begin()`
- `app/Http/Controllers/SahodayaAdmin/MembershipSettingsController.php` — add `force_open` flag

---

### R3 🟠 Multiple payment proofs pile up — no supersede logic
**What's broken:** Each rejected-then-resubmitted payment creates a new `MembershipPayment` row.
Sahodaya admin sees multiple rows per school with no clear indication of which is current.

**Fix:**
- On `uploadPayment()`, mark any existing `submitted` or `rejected` payment for the same `school_id`
  and `academic_year` as `superseded` before creating the new one
- In Sahodaya payment list, filter out `superseded` rows by default (show only latest)
- Add `superseded_by_payment_id` nullable FK to `membership_payments`

**Files:**
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php` — `uploadPayment()`
- `database/migrations/tenant/` — add `superseded_by_payment_id` and `status = 'superseded'`
- `app/Http/Controllers/SahodayaAdmin/PaymentVerificationController.php` — filter superseded

---

### R4 🟠 `membership_status` on school never reflects renewal or lapse
**What's broken:** `Tenant.membership_status` is set to `'approved'` on first payment and never
changes. A school that last paid in 2023-24 looks identical to one that just renewed.

**Fix:**
- Add a scheduled command: `UpdateMembershipStatusCommand` (daily)
  - For each school, check if current academic year `Registration.registration_status === 'completed'`
  - If yes → `membership_status = 'active'`
  - If prior year completed but current year not started → `membership_status = 'renewal_due'`
  - If no registration for 2+ years → `membership_status = 'lapsed'`
- Show membership status badge on school dashboard and Sahodaya member list

**Files:**
- New: `app/Console/Commands/UpdateMembershipStatusCommand.php`
- `app/Console/Kernel.php` — schedule daily
- `app/Models/Tenant.php` — document status values

---

### R5 🟠 No prior-year outstanding check before new registration
**What's broken:** Schools with unresolved prior-year registrations can freely begin a new year.

**Fix:**
- In `RegistrationStatusService::beginAnnualRegistration()`, check prior year:
  ```php
  $priorReg = Registration::where('school_id', $school->id)
      ->where('academic_year', $priorYear)
      ->first();
  if ($priorReg && !in_array($priorReg->registration_status, ['completed', 'approved'])) {
      throw new \Exception('Prior year registration is unresolved. Contact Sahodaya office.');
  }
  ```
- Allow Sahodaya admin to override this block via a "force new year" flag

**Files:**
- `app/Services/Membership/RegistrationStatusService.php` — `beginAnnualRegistration()`
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php`

---

### R6 🟠 Track rejection status ambiguous for schools
**What's broken:** When individual tracks (full_records, counts, teachers) are partially
approved/rejected, `registration_status` shows a generic `data_pending`. Schools don't
know which specific track was rejected and why.

**Fix:**
- In `AnnualRegistrationController::index()`, compute and send per-track status:
  ```php
  'trackStatus' => [
      'full_records' => $submission->full_records_status,
      'counts'       => $submission->counts_status,
      'teachers'     => $submission->teacher_status,
  ],
  'trackRejectionReasons' => [...], // from submission
  ```
- Frontend shows per-track coloured status badges (Approved ✓ / Pending ⏳ / Rejected ✗)
- Rejection reason displayed per track

**Files:**
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php` — `index()`
- Frontend: `resources/js/Pages/School/Registration/Index.*`

---

### R7 🟡 `due_date` on fee slabs never enforced
**What's broken:** `MembershipFeeSlab.due_date` is stored but never read. No late warnings.

**Fix:**
- In `AnnualRegistrationController::payment()`, check if due date is past:
  ```php
  $slab = MembershipFeeSlab::forSchool($school)->first();
  $isOverdue = $slab?->due_date && now()->gt($slab->due_date);
  ```
- Show "Payment overdue as of {date}" warning banner
- Optional: Sahodaya can configure a late fee amount on the slab — apply it to `membership_fee_amount`

**Files:**
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php`
- `app/Models/MembershipFeeSlab.php` — add `late_fee_amount` nullable
- `database/migrations/tenant/` — migration

---

### R8 🟡 Receipt number generation has no concurrency lock
**What's broken:** `SahodayaProfile.receipt_next_number` is read-then-increment — two simultaneous
approvals can generate duplicate receipt numbers.

**Fix:**
- Use DB-level atomic increment:
  ```php
  DB::table('sahodaya_profiles')
      ->where('tenant_id', $tenantId)
      ->increment('receipt_next_number');
  $number = DB::table('sahodaya_profiles')
      ->where('tenant_id', $tenantId)
      ->value('receipt_next_number') - 1;
  ```
- Or use a `sequences` table with `SELECT FOR UPDATE`

**Files:**
- `app/Services/Membership/MembershipReceiptService.php`

---

## Part 4 — Fee Settings

### F1 🔴 Three fee configs with silent priority override
**What's broken:** Fees can be set in (1) legacy `fee_type`/`fee_amount`, (2) `fee_settings` JSON
on `FestEvent`, and (3) `level_fees` on `FestStateProgram`. `resolveSchedule()` merges these
with a non-obvious priority. Admins configure fees in one place while another silently overrides.

**Fix:**
- Add a `fee_config_source` readonly computed field returned to UI showing which source is active:
  `"state_program" | "event_settings" | "legacy" | "none"`
- In `FestEventSettingsController`, show a "Fee configuration" panel that clearly displays:
  - Where fees are currently set
  - What the resolved amounts are
  - A "Override for this event" button that writes to `fee_settings` JSON
- Deprecate the legacy `fee_type`/`fee_amount` on new events (show migration warning)

**Files:**
- `app/Services/Events/FestSchoolEventFeeService.php` — add `feeConfigSource()` method
- `app/Http/Controllers/SahodayaAdmin/FestEventFeesController.php` — expose source in response
- Frontend: event fees settings page

---

### F2 🔴 Sports age-group DB fees ignored — reads from config instead
**What's broken:** `FestSchoolEventFeeService::resolveSchedule()` for `item_catalog` sports events
calls `FestSportsAgeGroup::defaultFees()` which reads from config files, not from
`FestSportsAgeGroupConfig` DB table that admins manage via `SportsAgeGroupController`.

**Fix:**
- `FestSportsAgeGroup::defaultFees()` — add tenant-aware override:
  ```php
  if ($tenantId) {
      return self::registry($tenantId)->defaultFees();
  }
  ```
- In `resolveSchedule()`, pass `$event->tenant_id` when calling `FestSportsAgeGroup::defaultFees()`
- `FestSportsAgeGroupRegistry::defaultFees()` — return `default_fee` from `FestSportsAgeGroupConfig` table

**Files:**
- `app/Support/FestSportsAgeGroup.php` — `defaultFees()`
- `app/Services/Events/FestSportsAgeGroupRegistry.php` — `defaultFees()`
- `app/Services/Events/FestSchoolEventFeeService.php` — pass tenant ID

---

### F3 🟠 School `institution_level` missing → wrong event fee category
**What's broken:** `FestSchoolEventFeeService::schoolRegistrationAmount()` reads
`$school->application_payload['institution_level']` — if missing, silently defaults to `'secondary'`.
Primary schools get charged secondary-level event fees.

**Fix:**
- In `schoolRegistrationAmount()`, log a warning if key is missing
- In school application form, make `institution_level` a required field if school event fees use this key
- Add a Sahodaya admin override: per-school institution level setting stored in tenant settings
- In `FestSchoolEventFeeController`, show institution level per school in fee breakdown

**Files:**
- `app/Services/Events/FestSchoolEventFeeService.php`
- `app/Http/Controllers/SahodayaAdmin/MemberSchoolsController.php` — add institution level field
- School application form config

---

### F4 🟠 MCQ fee: per-student proofs impractical
*(See M4 above — same fix: `McqSchoolFee` aggregate model)*

---

### F5 🟠 Training fee: per-teacher proofs impractical
**What's broken:** Each `TrainingRegistration` has its own fee receipt. A school enrolling
5 teachers uploads 5 proofs and awaits 5 approvals.

**Fix:**
- Determine if school typically sends all teachers to one training program in a batch
- Add optional school-level training fee: `TrainingSchoolFee` with `program_id`, `school_id`,
  `teacher_count`, `total_due`, `fee_receipt_id`
- Or: bulk approval — Sahodaya can select multiple `TrainingRegistration` records for same school
  and approve the fee for all at once

**Files:**
- New model: `app/Models/TrainingSchoolFee.php`
- `database/migrations/tenant/`
- `app/Http/Controllers/SahodayaAdmin/TrainingProgramController.php`

---

### F6 🟡 No fee waiver or discount for specific schools
**What's broken:** No mechanism to waive or reduce membership/event fees for a specific school.

**Fix:**
- Add `fee_override` JSON on `Registration`: `{ "waiver_reason": "...", "override_amount": 0 }`
- Sahodaya admin can set an override amount from the school's registration detail page
- `MembershipFeeCalculator` checks for override before returning fee
- Same for event fees: `FestSchoolEventFee.override_amount` nullable field

**Files:**
- `app/Models/Registration.php` — `fee_override` JSON
- `app/Models/FestSchoolEventFee.php` — `override_amount` nullable
- `app/Services/Membership/MembershipFeeCalculator.php`
- `app/Http/Controllers/SahodayaAdmin/PaymentVerificationController.php` — waiver action

---

## Part 5 — Accounts / Ledger

### L1 🔴 Financial year uses academic year ID — wrong for Indian fiscal year
**What's broken:** `LedgerPostingService::postJournal()` sets `financial_year_id = AcademicYear::activeId()`.
India's fiscal year is April–March; academic year is June–May. Fees collected in April/May
are posted to the wrong financial year.

**Fix:**
- Add a separate `FinancialYearRecord` concept (already has `financial_years` table based on
  `AcademicYearController`) — confirm if it's April-March based
- Create `FinancialYear::currentId()` helper:
  ```php
  // If current month >= April → FY starts this year
  // e.g. June 2026 → FY 2026-27 (April 2026 – March 2027)
  ```
- Replace `AcademicYear::activeId()` with `FinancialYear::currentId()` in `postJournal()`
- Add a Sahodaya admin setting: "Financial year type" (April-March / June-May / Calendar year)

**Files:**
- `app/Services/Ledger/LedgerPostingService.php` — `postJournal()`
- New helper: `app/Support/FinancialYear.php`
- `app/Http/Controllers/SahodayaAdmin/AcademicYearController.php`

---

### L2 🔴 Ledger index hard-capped at 100 rows — no pagination
**What's broken:** `LedgerController::index()` has `->limit(100)` with no pagination.
A Sahodaya with 50 schools and multiple events easily exceeds this — older entries invisible.

**Fix:**
- Replace `->limit(100)->get()` with `->paginate(50)`
- Add filter controls to the index: by date range, account head, category, entry type
- Show total count and "Showing X–Y of N" indicator

**Files:**
- `app/Http/Controllers/SahodayaAdmin/LedgerController.php` — `index()`
- Frontend: `resources/js/Pages/Sahodaya/Ledger/Index.*`

---

### L3 🟠 `CASH-BANK` single account — no bank separation
**What's broken:** All income debits a single `CASH-BANK` account. No way to track which bank
received which payment. No bank reconciliation possible.

**Fix:**
- Allow Sahodaya admin to create named bank accounts:
  New model: `BankAccount` with `tenant_id`, `account_name`, `bank_name`, `account_no`, `ifsc`
- Add `bank_account_id` nullable FK to `FeeReceipt` and `MembershipPayment`
- When school uploads proof and selects a bank, store the bank reference
- Sahodaya settings payment details: move to per-bank-account (multiple accounts supported)
- Ledger: debit specific bank account head instead of generic `CASH-BANK`

**Files:**
- New model: `app/Models/BankAccount.php`
- `database/migrations/tenant/` — `bank_accounts` table + FKs
- `app/Models/FeeReceipt.php`, `app/Models/MembershipPayment.php`
- `app/Http/Controllers/SahodayaAdmin/MembershipSettingsController.php`
- `app/Services/Ledger/LedgerPostingService.php`

---

### L4 🟠 Re-approve after reversal skips ledger posting
**What's broken:** `LedgerPostingService::postJournal()` skips posting if a journal already
exists for the `(reference_type, reference_id)` pair. If receipt is approved → rejected →
re-approved, the second approval posts nothing to ledger.

**Fix:**
- In `postJournal()`, add a `$forceRepost = false` parameter
- When re-approving a receipt, call with `$forceRepost = true`
- Force-repost: delete existing journal lines for the reference, then re-post
- Or: use a reversal journal — post a negative journal on rejection, positive on re-approval

**Files:**
- `app/Services/Ledger/LedgerPostingService.php` — `postJournal()`
- `app/Observers/FeeReceiptObserver.php` — handle reversal scenario
- `app/Services/Ledger/FeeReceiptLedgerDispatcher.php`

---

### L5 🟠 MySQL-incompatible `to_char()` in ledger reports
**What's broken:** `LedgerController::reports()` uses `to_char(transaction_date, 'YYYY-MM')`
which is PostgreSQL-only. Fails on MySQL.

**Fix:**
- Use DB-agnostic date grouping:
  ```php
  $driver = DB::getDriverName();
  $monthExpr = $driver === 'mysql'
      ? "DATE_FORMAT(transaction_date, '%Y-%m')"
      : "to_char(transaction_date, 'YYYY-MM')";
  ```
- Or use Laravel's `DB::raw()` with conditional

**Files:**
- `app/Http/Controllers/SahodayaAdmin/LedgerController.php` — `reports()`

---

### L6 🟠 State remittance not linked to source collections
**What's broken:** `StateRemittance` (what Sahodaya owes the state) has `amount` and `title`
but no FK to which membership fees or event fees it's sourced from.

**Fix:**
- Add `source_breakdown` JSON to `StateRemittance`:
  `{ "membership_2025-26": 45000, "kalotsav_fees": 12000 }`
- In state remittance creation form (both state admin and Sahodaya admin side), allow admin
  to select source: membership collections for a year, or specific event fees
- Auto-compute suggested remittance amount from unremitted membership income in ledger

**Files:**
- `app/Models/StateRemittance.php` — `source_breakdown` JSON
- `database/migrations/` — migration
- `app/Http/Controllers/Admin/StateRemittanceController.php`
- `app/Http/Controllers/SahodayaAdmin/StateRemittanceController.php`

---

### L7 🟡 No expense account heads for real Sahodaya operations
**What's broken:** `LedgerAccountCatalog` has only `STATE-REMITTANCE` and `AWARDS-FUND` as
expense heads. Real Sahodaya expenses: venue, catering, printing, travel, prizes, staff honorarium.

**Fix:**
- Add default expense heads to `LedgerAccountCatalog::defaultCodes()`:
  `VENUE-COST`, `CATERING`, `PRINTING`, `TRAVEL-REIMB`, `PRIZES`, `HONORARIUM`, `ADMIN-EXP`
- Sahodaya admin can add custom expense heads from ledger settings
- Add expense entry form in ledger: debit expense head, credit CASH-BANK, with description and date

**Files:**
- `app/Support/LedgerAccountCatalog.php` — expand defaults
- `app/Http/Controllers/SahodayaAdmin/LedgerController.php` — `storeExpense()` action
- `routes/web.php`

---

## Part 6 — User Flow & Admin UX

### U1 🟠 `fest_registration_closed` per-school flag has no UI
**What's broken:** `$school->fest_registration_closed` can block a specific school from fest
registration, but there's no controller/route to set it from Sahodaya admin UI.

**Fix:**
- Add to `MemberSchoolsController` or a new `SchoolAccessController`:
  `POST /sahodaya-admin/{tenant}/schools/{school}/toggle-fest-registration`
- Show per-school access toggle in the Sahodaya member schools list
- Add audit log entry when toggled

**Files:**
- `app/Http/Controllers/SahodayaAdmin/MemberSchoolsController.php` — add toggle action
- `routes/web.php`
- Frontend: member schools list page

---

### U2 🟠 No consolidated admin action queue / dashboard
**What's broken:** Sahodaya admin must check 4+ separate pages daily for pending actions:
membership data reviews, payment verifications, MCQ fee approvals, fest fee approvals, appeals.

**Fix:**
- Upgrade `SahodayaAdmin/DashboardController` to return a unified `actionQueue`:
  ```php
  'actionQueue' => [
      'membership_data_pending'   => Registration::where(...)->count(),
      'membership_payments'       => MembershipPayment::where('status','submitted')->count(),
      'fest_fee_proofs'           => FestSchoolEventFee::where('status','proof_uploaded')->count(),
      'mcq_fee_proofs'            => McqSchoolFee::where('status','proof_uploaded')->count(),
      'fest_appeals'              => FestAppeal::where('status','open')->count(),
      'fest_registrations_review' => FestRegistration::where('status','submitted')->count(),
  ]
  ```
- Dashboard shows each category as a clickable alert card with count
- Zero-count categories are hidden

**Files:**
- `app/Http/Controllers/SahodayaAdmin/DashboardController.php`
- Frontend: `resources/js/Pages/Sahodaya/Dashboard.*`

---

### U3 🟠 No membership reminder notifications to schools
**What's broken:** No scheduled reminder fires when registration window opens or when payment
due date approaches. Schools only find out if they log in.

**Fix:**
- New console command: `SendMembershipRemindersCommand` (daily)
  - 7 days before `registration_ends_at`: remind schools that haven't started registration
  - 7 days before `due_date` on fee slab: remind schools in `payment_pending` status
  - Day of deadline: final reminder
- Use existing `SahodayaAdminNotifier`/`MembershipNotifier` infrastructure for email

**Files:**
- New: `app/Console/Commands/SendMembershipRemindersCommand.php`
- `app/Console/Kernel.php` — schedule daily
- `app/Services/Membership/MembershipNotifier.php` — add `reminderWindowClosing()`, `reminderPaymentDue()`

---

### U4 🟡 School portal shows all MCQ exams regardless of registration
**What's broken:** `McqRegistrationController::index()` shows all Sahodaya MCQ exams to all schools.
A school with zero registered students sees full exam list.

**Fix:**
- Group exams into "registered" (school has at least one registration) and "available" (can still register)
- Exams where status = `completed` and school has no registrations: don't show or collapse
- Add `my_registration_count` per exam to the index response

**Files:**
- `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php` — `index()`

---

### U5 🟡 Student count vs DB mismatch not flagged (`counts_only` mode)
**What's broken:** Schools submitting manual counts can report any number. If they also have
actual student records in the portal, no discrepancy is flagged.

**Fix:**
- In `AnnualRegistrationController::counts()`, compute `db_student_count`:
  ```php
  $dbCount = Student::where('tenant_id', $this->school->id)->active()->count();
  ```
- If `$dbCount > 0` and submitted counts differ by more than 10%: show a warning
  "Your DB has N active students but submitted counts total M"
- Sahodaya admin sees same discrepancy flag in submission review

**Files:**
- `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php` — `counts()` and `saveCounts()`
- `app/Http/Controllers/SahodayaAdmin/MembershipReportsController.php` — submission review

---

### U6 🟡 Kalotsav school-level events not auto-linked to Sahodaya parent
**What's broken:** School creates their own Kalotsav via `FestProgramController` but
`parent_event_id` is never set — Sahodaya's "promote all school rounds" finds zero school rounds.
(Same underlying issue as S2 for sports.)

**Fix:**
*(Same fix as S2 — link UI + retroactive linking route)*
- In `FestProgramController::store()`, add optional `sahodaya_event_id` to link as parent
- Sahodaya event list shows "X school rounds linked" count
- Sahodaya admin can view and manage linked school rounds from the event page

**Files:**
- `app/Http/Controllers/SchoolAdmin/FestProgramController.php`
- `app/Http/Controllers/SahodayaAdmin/FestEventController.php`

---

## Part 7 — Missing Notifications

### N1 — Sahodaya admin notified when school submits any data
Currently partial. Ensure notifications fire for:
- School submits student data/counts/teachers for membership review
- School re-submits after rejection
- School uploads membership payment proof
- School uploads fest event payment proof
- School registers for a Sahodaya sports/kalotsav event (if Sahodaya wants a digest)

### N2 — School notified for all status changes
Ensure school gets a notification for:
- Each track approved (full records / counts / teachers)
- Each track rejected with reason
- Membership registration completed (with receipt)
- Fest registration approved or rejected per item
- Fest results published
- MCQ results published
- Sports results published

---

## Implementation Order

### Phase 1 — Critical Bugs (do first, break real usage)
| # | Item | Est. effort |
|---|------|-------------|
| S1 | Fix open age group blocking sports registration | 1 hr |
| R1 | Fix variable fee preview (estimateFeeForSchool) | 2 hrs |
| F2 | Sports age-group DB fees used in fee resolver | 3 hrs |
| L2 | Ledger pagination (remove hard limit 100) | 1 hr |
| L5 | Fix MySQL-incompatible to_char() | 30 min |
| L4 | Fix re-approve skipping ledger post | 2 hrs |
| R8 | Receipt number concurrency lock | 1 hr |

### Phase 2 — High Priority (blocks key workflows)
| # | Item | Est. effort |
|---|------|-------------|
| S2/U6 | School events linked to Sahodaya parent | 4 hrs |
| R2 | Enforce registration window dates | 2 hrs |
| R3 | Supersede old payment proofs on re-upload | 2 hrs |
| R4 | membership_status renewal/lapse tracking | 3 hrs |
| M1 | MCQ rank field + auto-ranking on publish | 3 hrs |
| M4 | MCQ school-level fee aggregation | 6 hrs |
| F1 | Fee config source transparency in UI | 4 hrs |
| U1 | fest_registration_closed UI toggle | 1 hr |
| U2 | Consolidated admin action queue dashboard | 4 hrs |
| L1 | Financial year vs academic year — fix ledger | 3 hrs |

### Phase 3 — Medium Priority (improves completeness)
| # | Item | Est. effort |
|---|------|-------------|
| S3 | Winner-gate for Sahodaya sports registration | 4 hrs |
| S4 | Auto-rank by measurement for athletics | 3 hrs |
| S5 | Athletic record auto-flag on mark entry | 2 hrs |
| R5 | Prior year outstanding check | 2 hrs |
| R6 | Per-track rejection status clarity | 2 hrs |
| R7 | Due date warning + late fee option | 2 hrs |
| M2 | MCQ eligibility filter by class/group | 3 hrs |
| M3 | MCQ leaderboard / topper export | 2 hrs |
| F3 | institution_level missing → wrong fee | 2 hrs |
| F5 | Training fee school-level aggregation | 4 hrs |
| F6 | Fee waiver mechanism | 3 hrs |
| L3 | Bank account separation in ledger | 5 hrs |
| L6 | State remittance linked to source | 3 hrs |
| L7 | Expense account heads + entry form | 3 hrs |
| U3 | Membership reminder notifications | 3 hrs |
| U4 | MCQ exam visibility scoped to registered schools | 1 hr |
| U5 | Student count vs DB discrepancy flag | 2 hrs |
| S6 | Restrict sports to Sahodaya level only | 1 hr |
| S7 | House point athletics scoring presets | 3 hrs |
| N1/N2 | Complete notification coverage | 4 hrs |

---

## File Reference Index

| Area | Key Files |
|------|-----------|
| Sports eligibility | `app/Services/Events/FestRegistrationEligibilityService.php` |
| Sports promotion | `app/Services/Events/FestQualificationService.php` |
| Sports age groups | `app/Support/FestSportsAgeGroup.php`, `app/Services/Events/FestSportsAgeGroupRegistry.php` |
| School event creation | `app/Http/Controllers/SchoolAdmin/FestProgramController.php` |
| School registration | `app/Http/Controllers/SchoolAdmin/FestRegistrationController.php` |
| Sahodaya event admin | `app/Http/Controllers/SahodayaAdmin/FestEventController.php` |
| Fest results + promotion | `app/Http/Controllers/SahodayaAdmin/FestResultsController.php` |
| Mark entry | `app/Http/Controllers/SahodayaAdmin/FestMarkEntryController.php` |
| MCQ exam admin | `app/Http/Controllers/SahodayaAdmin/McqExamController.php` |
| MCQ school registration | `app/Http/Controllers/SchoolAdmin/McqRegistrationController.php` |
| Annual registration (school) | `app/Http/Controllers/SchoolAdmin/AnnualRegistrationController.php` |
| Membership settings (sahodaya) | `app/Http/Controllers/SahodayaAdmin/MembershipSettingsController.php` |
| Payment verification | `app/Http/Controllers/SahodayaAdmin/PaymentVerificationController.php` |
| Fee calculator | `app/Services/Membership/MembershipFeeCalculator.php` |
| Registration status | `app/Services/Membership/RegistrationStatusService.php` |
| Fest fee service | `app/Services/Events/FestSchoolEventFeeService.php` |
| Ledger posting | `app/Services/Ledger/LedgerPostingService.php` |
| Ledger controller | `app/Http/Controllers/SahodayaAdmin/LedgerController.php` |
| Ledger dispatcher | `app/Services/Ledger/FeeReceiptLedgerDispatcher.php` |
| Account catalog | `app/Support/LedgerAccountCatalog.php` |
| State remittance | `app/Models/StateRemittance.php`, `app/Http/Controllers/Admin/StateRemittanceController.php` |
| Sahodaya profile | `app/Models/SahodayaProfile.php` |
| Fee receipt observer | `app/Observers/FeeReceiptObserver.php` |
| Tenant model | `app/Models/Tenant.php` |
