# Sahodaya Connect — Feature Plan V2
> Plan only. No code changes. Covers: student registration windows, lock system, school roles,
> auto-logins/usernames, profile edit approval chain, MCQ multi-level exams.

---

## 1. Student Registration & Edit Windows

### 1.1 Two Separate Windows Per Academic Year

Each academic year has **two distinct windows** controlled by Sahodaya:

| Window | Purpose | Who controls |
|--------|---------|-------------|
| **Registration Window** | Schools can **add new students** | Sahodaya (global + per-school override) |
| **Edit Window** | Schools can **edit/delete existing students** | Sahodaya (global + per-school override) |

These windows can overlap or be sequential. Example:
- Registration open: 1 Jun – 31 Jul (add new students)
- Edit open: 1 Jun – 15 Aug (fix existing records)
- Both locked: 16 Aug onward (change request mode)

### 1.2 Current vs. Required Lock Architecture

**Currently:** One global lock flag on `SahodayaProfile` (`student_edit_lock_enabled` + `student_edit_lock_at`). No per-school control. No separation of add vs. edit.

**Required:** Three-layer lock system:

```
Layer 1: Global (Sahodaya) — SahodayaRegistrationWindow  
  └─ add_open / add_close / edit_open / edit_close (datetime)
  └─ Applies to ALL schools by default

Layer 2: Per-school override — SchoolLockOverride (new)
  └─ Sahodaya can unlock/re-lock any individual school
  └─ Override supersedes global window
  └─ Has expiry (auto-reverts to global after N days)

Layer 3: Emergency lock — SahodayaProfile.student_edit_lock_enabled (existing)
  └─ Instant hard lock — overrides everything including school overrides
  └─ Used for audit/freeze periods
```

### 1.3 Database Changes Needed

**`sahodaya_registration_windows` table** (extend existing):
```
+ add_open       datetime nullable
+ add_close      datetime nullable
+ edit_open      datetime nullable
+ edit_close     datetime nullable
  (existing: start_date / end_date can map to add_open / add_close for backward compat)
```

**New `school_lock_overrides` table:**
```
id
sahodaya_id         string (FK tenant)
school_id           string (FK tenant)
override_type       enum: unlock_add | unlock_edit | lock_add | lock_edit | unlock_all | lock_all
reason              text nullable
expires_at          datetime nullable  (null = permanent until revoked)
created_by_user_id  int
created_at / updated_at
```

### 1.4 Lock Resolution Logic

`StudentEditLockService::resolveWindowState(school)` returns:

```
{
  can_add:  bool   — can this school add new students right now?
  can_edit: bool   — can this school edit existing students right now?
  source:   string — 'global_window' | 'school_override' | 'emergency_lock'
  message:  string — human-readable reason if locked
  override_expires_at: datetime|null
}
```

Resolution order (highest priority wins):
1. Emergency lock (`SahodayaProfile.student_edit_lock_enabled = true`) → everything locked, no overrides
2. School-level override in `school_lock_overrides` (if active, not expired)
3. Global `sahodaya_registration_windows` dates for the academic year
4. Default: locked

### 1.5 Sahodaya Admin Controls

**Global settings page** (`Sahodaya Admin → Students → Registration Windows`):
- Set add_open / add_close / edit_open / edit_close for current academic year
- Enable/disable emergency hard lock with reason
- Preview: "X schools currently unlocked for add, Y for edit"

**Per-school override panel** (`Sahodaya Admin → Schools → [School] → Lock Override`):
- Grant temporary unlock for add / edit / both
- Set expiry date (mandatory for unlock overrides)
- View override history per school

---

## 2. Change Request Approval Chain

### 2.1 Current Flow (Problem)

```
School submits change → Sahodaya sees it → Sahodaya approves/rejects
```

No internal school review. Any school_staff can submit. Change goes straight to Sahodaya queue.

### 2.2 New Two-Stage Approval Flow

```
UNLOCK PERIOD (free editing):
  Student/teacher edits own profile
    → School Principal OR School Admin sees it in queue
    → Approves → applied immediately (no Sahodaya involvement)

LOCK PERIOD (change request mode):
  Student/teacher or school staff submits change request
    → School Principal OR School Admin reviews first
    → If approved at school level → auto-escalates to Sahodaya queue
    → Sahodaya approves/rejects → applied (or rejected back to school)
```

### 2.3 Database Changes to `student_edit_change_requests`

Add fields:
```
school_approval_status   enum: pending_school | school_approved | school_rejected | bypassed
school_approved_by       int nullable (user_id)
school_approved_at       datetime nullable
school_rejection_note    text nullable
submitted_by_role        string  (who submitted: student | teacher | school_staff | school_admin | school_principal)
escalation_type          enum: direct_to_sahodaya | via_school_principal  (based on lock state at time of submit)
```

**Status flow:**

```
During unlock period:
  submitted → pending_school → school_approved → [applied immediately, no sahodaya step]
                             → school_rejected → [done]

During lock period:
  submitted → pending_school → school_approved → pending (sahodaya) → approved → [applied]
                             → school_rejected → [done]
                                                              → rejected → [done]
```

### 2.4 Who Can Approve at School Level

| Action | school_admin | school_principal | school_staff | school_event_coordinator |
|--------|:-----------:|:----------------:|:------------:|:------------------------:|
| Submit change request | ✓ | ✓ | ✓ (own profile only) | — |
| Approve at school level | ✓ | ✓ | — | — |
| Reject at school level | ✓ | ✓ | — | — |
| View all pending requests | ✓ | ✓ | — | — |

---

## 3. School Roles & User Management

### 3.1 New/Revised Role Hierarchy

```
school_principal          ← NEW — highest school-level role
  ├─ Full school_admin access
  ├─ Can create / deactivate school_admin users
  ├─ Can approve change requests (school level)
  ├─ Can create event coordinators
  └─ Cannot be created by school_admin (only Sahodaya admin creates principals)

school_admin              ← EXISTING (unchanged in access scope)
  ├─ Full school management access
  ├─ Can create event coordinators
  ├─ Can approve change requests (school level)
  └─ Created by: school_principal OR sahodaya_admin

school_event_coordinator  ← NEW — event-scoped staff
  ├─ Access ONLY to assigned event type(s) or specific events
  ├─ Cannot access student records, membership, fees
  ├─ Created by: school_admin OR school_principal
  └─ Scoped to: specific program slug(s) (kalotsav, sports-meet, mcq, etc.)

school_staff              ← EXISTING
  ├─ Read-only + submit (no approve)
  └─ Created by: school_admin OR school_principal
```

### 3.2 Event Coordinator Scoping

New table **`school_user_event_scopes`**:
```
id
school_id       string
user_id         int
program_slug    string   (kalotsav | sports-meet | kids-fest | mcq | training | all)
event_id        int nullable  (null = all events of that program type)
created_by      int
created_at
```

Middleware: `EventCoordinatorScope` — when coordinator accesses an event route, checks this table. 403 if not scoped.

### 3.3 User Creation Flow

**Principal creates school_admin:**
1. Sahodaya admin provisions the first principal (or school is set up with a principal)
2. Principal goes to School Admin → Users → Add Admin
3. Fills: name, email, phone, role = school_admin
4. System auto-creates login (see Section 4)

**school_admin or school_principal creates event coordinator:**
1. Goes to School Admin → Users → Add Coordinator
2. Fills: name, email, role = school_event_coordinator
3. Selects: which program(s) this coordinator can manage
4. System auto-creates login

---

## 4. Auto-Logins & Username System

### 4.1 Username Format

All users in the system get a unique **username** in addition to email:

| User type | Username format | Example |
|-----------|----------------|---------|
| Student | `{sahodaya_prefix}/{school_prefix}/{year_suffix}/{seq}` | `MLCS/AMU/27/0001` (same as reg_no) |
| Teacher | `{school_prefix}/TCH/{seq:04d}` | `AMU/TCH/0012` |
| school_admin | `{school_prefix}/ADM/{seq:03d}` | `AMU/ADM/001` |
| school_principal | `{school_prefix}/PRI/001` | `AMU/PRI/001` |
| school_event_coordinator | `{school_prefix}/COR/{seq:03d}` | `AMU/COR/003` |
| sahodaya_admin | `{sahodaya_prefix}/SA/{seq:03d}` | `MLCS/SA/001` |
| judge / examiner | `{sahodaya_prefix}/JDG/{seq:03d}` | `MLCS/JDG/007` |

### 4.2 Auto-Generation on User Creation

When ANY new user is created (student, teacher, any school role):
1. System auto-generates username using above format
2. System auto-generates a temporary password (8-char alphanumeric, uppercase first letter)
3. `User.username` is stored (new column — unique)
4. `User.must_change_password = true` flag set
5. At creation screen: admin sees username + temp password **once** (not stored in plain text)
6. Email is **required** for all users — welcome email sent with username + temp password

### 4.3 Database Changes to `users` table

New columns:
```
username              string unique — login handle
must_change_password  boolean default true
last_login_at         datetime nullable
created_by_user_id    int nullable
```

### 4.4 Login Methods

Users can log in with **either**:
- Email + password
- Username + password

`AuthController` checks both `email` and `username` columns.

### 4.5 Admin Credential Visibility

**School admin/principal** can:
- See username of every user they manage (visible in user list)
- See last login date
- **Reset password** → generates new temp password shown once in UI
- Cannot see current hashed password (no plaintext storage ever)

**Sahodaya admin** can:
- See username of every school user
- Reset password for any school user
- View login activity log (last_login_at)

**Password reset flow:**
1. Admin clicks "Reset Password" for a user
2. System generates new 8-char temp password
3. Shows in modal: "New temp password: `Xy4k9Mwz`" (shown once)
4. Sets `must_change_password = true`
5. Optionally emails new password to user

### 4.6 First Login — Forced Password Change

When `must_change_password = true`:
- After login, user is immediately redirected to `/change-password`
- Cannot access any other page until password is changed
- Middleware: `EnsurePasswordChanged`

---

## 5. User Profile Editing Flow

### 5.1 What Users Can Edit

| Field | Student | Teacher | School staff |
|-------|:-------:|:-------:|:-----------:|
| Display name | ✓ | ✓ | ✓ |
| Email | ✓ | ✓ | ✓ |
| Phone | ✓ | ✓ | ✓ |
| Profile photo | ✓ | ✓ | ✓ |
| DOB | — (change request only) | — | — |
| Class | — (change request only) | — | — |
| Gender | — (change request only) | — | — |

### 5.2 Edit Flow by Lock State

**Case 1: Edit window open (unlocked)**
```
User edits profile
  → Submission goes to school admin/principal queue (auto-notification)
  → School admin/principal approves → applied immediately
  → School admin/principal rejects → user notified
```

**Case 2: Edit window closed (locked)**
```
User submits change request with reason
  → School admin/principal reviews (school level)
    → School rejects → done, user notified
    → School approves → escalates to Sahodaya queue
      → Sahodaya approves → applied
      → Sahodaya rejects → school + user notified
```

**Case 3: Emergency lock**
```
Users see: "Record edits are currently frozen. Contact your school admin."
No submissions accepted until lock lifted.
```

### 5.3 New Tables / Models

**`user_profile_change_requests`** (separate from student_edit_change_requests):
```
id
user_id                     int
school_id                   string
changes_json                json  (name, email, phone, photo_path)
reason                      text nullable
status                      enum: pending_school | school_approved | school_rejected
                                  | sahodaya_pending | approved | rejected
school_approval_status      enum: pending | approved | rejected
school_approved_by          int nullable
school_approved_at          datetime nullable
sahodaya_approved_by        int nullable
sahodaya_approved_at        datetime nullable
resolution_note             text nullable
created_at / updated_at
```

---

## 6. MCQ Multi-Level Exam System

### 6.1 Level Structure

MCQ exams are now organized in **series** (1 to N levels):

```
McqExamSeries
  ├─ title: "District Science Olympiad 2025"
  ├─ tenant_id (sahodaya)
  ├─ academic_year_id
  └─ levels:
       ├─ Level 1: Open to all eligible students
       ├─ Level 2: Based on Level 1 cutoff/rank
       └─ Level 3: Based on Level 2 (optional)
```

### 6.2 Database — New/Changed Models

**New table `mcq_exam_series`:**
```
id
tenant_id           string
title               string
academic_year_id    int
description         text nullable
status              enum: draft | active | completed
created_at / updated_at
```

**Changes to `mcq_exams` table:**
```
+ series_id         int nullable (FK mcq_exam_series)
+ exam_level        tinyint default 1  (1, 2, 3...)
+ parent_exam_id    int nullable (FK mcq_exams — the Level N-1 exam)
+ eligibility_mode  enum: open | cutoff_marks | top_rank | manual
                    (open = no filter, cutoff_marks = min score from parent, 
                     top_rank = top N from parent, manual = admin selects)
+ cutoff_score      decimal(5,2) nullable  (for cutoff_marks mode)
+ top_rank_count    int nullable           (for top_rank mode, e.g. top 50)
+ promotion_locked  boolean default false  (when true, Level 2 reg list is frozen)
```

### 6.3 Level 1 → Level 2 Promotion Flow

```
1. Sahodaya publishes Level 1 results (McqRankingService::rankExam())

2. Sahodaya creates Level 2 exam, links parent_exam_id = Level 1 exam
   Configures: eligibility_mode, cutoff_score or top_rank_count

3. System calculates eligible students:
   - cutoff_marks mode: McqMark.score >= cutoff_score AND status = 'submitted'
   - top_rank mode: McqMark.rank <= top_rank_count
   - manual mode: Sahodaya admin selects from Level 1 participants list

4. Each school sees their eligible students on MCQ hub
   School registers eligible students for Level 2 (cannot register ineligible ones)
   McqRegistrationController::store() validates against eligibility list

5. Level 2 exam proceeds identically to Level 1 (hall tickets, attendance, marks, ranking)
```

### 6.4 Eligibility Check at Registration Time

`McqEligibilityService::isEligible()` extended to check:
```
1. exam.eligibility_config (existing — class group, gender)
2. exam.exam_level > 1:
   → Fetch parent exam's McqMark for this student
   → Apply eligibility_mode check:
     cutoff_marks: student.mark.score >= exam.cutoff_score
     top_rank:     student.mark.rank <= exam.top_rank_count
     manual:       student_id in McqExam.promoted_student_ids (JSON list)
   → Fail → 422 "Student did not qualify in Level 1"
```

### 6.5 Sahodaya Admin — Level 2 Setup UI

`Sahodaya Admin → MCQ → [Series] → Add Level 2`:
- Select parent exam (Level 1 from same series)
- Set eligibility mode + cutoff value
- Preview: "Based on current results, 43 students across 12 schools are eligible"
- Option: "Generate eligible student list" → auto-registers all eligible students (or just shows who qualifies)
- Set Level 2 exam date, venue, duration, fee, hall ticket settings

### 6.6 School MCQ Hub — Level Indicator

`School Admin → MCQ` shows per-series cards:
```
District Science Olympiad 2025
  Level 1: [Completed] Your registrations: 15 | Your qualifiers: 8
  Level 2: [Registration Open] Eligible: 8 | Registered: 5 | Pending: 3
```

---

## 7. Implementation Phases

| Phase | Features | Estimated Effort |
|-------|---------|-----------------|
| **Phase 1** | Dual window (add + edit) + enhanced lock logic + per-school overrides | 3–4 days |
| **Phase 2** | school_principal role + event_coordinator role + event scoping | 2–3 days |
| **Phase 3** | Username system + auto-login generation + EnsurePasswordChanged middleware | 3–4 days |
| **Phase 4** | Two-stage change request approval (school first, then Sahodaya) | 2–3 days |
| **Phase 5** | User profile self-edit with school approval flow | 2 days |
| **Phase 6** | MCQ series + multi-level + Level 2 eligibility + promotion flow | 4–5 days |
| **Total** | | **16–21 days** |

---

## 8. What Already Exists (Don't Rebuild)

| Feature | Current state | What to extend |
|---------|--------------|---------------|
| Global lock | `SahodayaProfile.student_edit_lock_enabled` + `student_edit_lock_at` | Keep — add per-school override layer on top |
| Change requests | `StudentEditChangeRequest` + `StudentEditChangeService` | Add school_approval_status field + school approval step |
| MCQ eligibility | `McqEligibilityService` checks class_group + gender | Add parent_exam result check |
| MCQ ranking | `McqRankingService::rankExam()` writes rank to McqMark | No changes — Level 2 eligibility reads from this |
| Registration window | `SahodayaRegistrationWindow` model | Add edit_open / edit_close columns |
| Portal provisioner | `StudentPortalProvisioner`, `TeacherPortalProvisioner` | Add auto-username + auto-password generation |
| Audit logging | `PlatformAuditLogger` already handles all categories | Add entries for new approval steps |

---

## 9. Key Decisions Needed Before Implementation

1. **Username uniqueness scope**: Is username unique globally (across all sahodayas) or per-sahodaya? Recommended: globally unique.

2. **School principal provisioning**: Who creates the first principal for a school? Recommended: Sahodaya admin only (not school_admin).

3. **Edit window vs. registration window**: Should they be configured together (same admin page) or separately? Recommended: same page, two date ranges.

4. **MCQ Level 2 auto-registration**: When Sahodaya creates Level 2, does the system auto-register all eligible students, or do schools still manually register? Recommended: Schools register (so they confirm intent), but system pre-filters to eligible only.

5. **Temp password delivery**: Shown on-screen once only, OR also emailed? Recommended: Both — shown once in UI + emailed with welcome message.

6. **Profile edit scope for teachers/staff**: Should teachers be able to change their own DOB/class-related data, or only name/email/phone? Recommended: name/email/phone only; DOB and joining date = change request.
