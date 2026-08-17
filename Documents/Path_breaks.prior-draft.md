# Comprehensive Audit of User Flows & Path Breaks

This document maps all user flows, pages, elements, buttons, options, and actions evaluated starting from Entry Pages across all portals and roles in the platform, highlighting every point where the workflow breaks, dead-ends, or behaves inconsistently.

---

## 1. Entry Pages & Authentication Routing

### 1.1 School Specialized Coordinator Roles Lockout
- **Entry Page**: Portal Login (`/login`, `/school-login`)
- **Element / Action**: Logging in with any of 5 specialized school coordinator accounts (`school_finance_coordinator`, `school_training_coordinator`, `school_mcq_coordinator`, `school_kalotsavam_coordinator`, `school_sports_coordinator`).
- **Intended Path**: User should be redirected to their role-scoped dashboard (e.g., `/school-admin/{tenant}/mcq` or `/school-admin/{tenant}/finance`).
- **Break Description**: `AuthController::homeFor()` has no routing handler for these 5 roles. The authentication succeeds, but the system immediately logs the user out and displays an error message: *"Your account has no portal assigned"*.

### 1.2 Mark Entry Admin Misdirection & Dead Code
- **Entry Page**: Portal Login (`/login`)
- **Element / Action**: Logging in as `mark_entry_admin`.
- **Intended Path**: Redirection to fest mark entry portal workspace.
- **Break Description**: `AuthController::homeFor()` redirects `mark_entry_admin` to the main Sahodaya Admin landing page (`/sahodaya-admin/{tenant_id}`). A secondary check at line 422 intended for portal routing is dead code that can never be reached.

### 1.3 School Event Coordinator Zero-Scope Redirect Loop
- **Entry Page**: School Admin Dashboard (`/school-admin/{tenant_id}`)
- **Element / Action**: Login or navigation by a `school_event_coordinator` with zero assigned event scopes (or whose event scope was removed).
- **Intended Path**: User should see an informative "No Assigned Events" empty state page with instructions or contact info.
- **Break Description**: The user lands on `/school-admin/{tenant_id}`, where `DashboardController::index` checks assigned scopes, finds none, and attempts to redirect back using `homeUrlFor()`, triggering a self-referential redirect loop or dead-end redirect.

### 1.4 School Application Pre-Approval Credential Issuance
- **Entry Page**: Public Membership / Join Sahodaya Form (`/join`)
- **Element / Action**: Submit "Apply for Sahodaya Membership" form.
- **Intended Path**: School submits application -> application stays in `pending` review -> admin approves application -> login credentials generated and sent.
- **Break Description**: `SchoolApplicationController::store()` creates the user account (`school_admin`) and emails full login credentials immediately upon submission before Sahodaya approval. Furthermore, applicants have no public application status lookup page.

---

## 2. Public & Unauthenticated Pages

### 2.1 Orphaned Public Fest & MCQ Pages
- **Entry Page**: Public Header Navbar & Footer (`resources/views/partials/navbars/*`)
- **Element / Action**: Navigating to Public Event Schedules, Results, Scoreboards, and MCQ Archives.
- **Intended Path**: Visitors can easily discover and access public event schedules (`/fest/{event}/schedule`), live scoreboards (`/scoreboard`), official results (`/fest/{event}/results`), and MCQ question papers (`/mcq/papers`).
- **Break Description**: The controller routes and blade views are completely built and functional, but there are zero links or navigation buttons anywhere in the public header or footer navigation to reach them. They can only be accessed by manually typing the URL.

### 2.2 Public Live Scoreboard vs Official Results Gating Leak
- **Entry Page**: Public Scoreboard (`/fest/{event}/scoreboard`) vs Public Results (`/fest/{event}/results`)
- **Element / Action**: Viewing event outcome before official publishing sign-off by Sahodaya admin.
- **Intended Path**: Both live scores and final results should respect the `results_published` status flag.
- **Break Description**: `/fest/{event}/scoreboard` lacks a `results_published` check and displays live/interim mark details publicly at any time, while `/fest/{event}/results` strictly blocks access with a 404 until published.

### 2.3 Absence of Public Circulars & MCQ Leaderboards
- **Entry Page**: Public Website Home (`/`)
- **Element / Action**: Looking for published circulars or MCQ talent search top rankers.
- **Intended Path**: Public can view published official circulars and MCQ exam toppers.
- **Break Description**: Circular controllers are authenticated-only with no public page equivalent. MCQ exams have no public results or leaderboard routes implemented.

---

## 3. Sahodaya Admin Portal

### 3.1 Custom Fest Events Nav Hidden & Incomplete Lifecycle
- **Entry Page**: Sahodaya Admin Sidebar (`sahodayaAdminNav.js`) -> Fest & Events
- **Element / Action**: Accessing Custom Fest Events.
- **Intended Path**: Admins create and manage custom fest events end-to-end (catalog -> registration -> schedule -> mark entry -> results -> certificates).
- **Break Description**: The sidebar navigation item for "Custom events" is hardcoded to `hidden: true`. When accessed directly via URL, Custom Events only support basic item creation and mark entry; fest-day desk, clash/substitution tools, results publishing, reports, and certificate generation are missing.

### 3.2 Absence of MCQ Certificate Pipeline
- **Entry Page**: Sahodaya Admin MCQ Hub (`/sahodaya-admin/{tenant}/mcq`)
- **Element / Action**: Generating or issuing certificates for students completing MCQ / Talent Search exams.
- **Intended Path**: Admin configures certificate templates and issues participation/merit certificates to MCQ candidates.
- **Break Description**: Unlike all fest programs (Kalotsav, Sports, Kids Fest, etc.), MCQ exams have no certificate routes, controllers, or database models.

### 3.3 Asymmetric State-Tier Rollup Dashboards
- **Entry Page**: State Admin Portal (`/admin/state/dashboard`)
- **Element / Action**: Viewing aggregated state-wide results for Kids Fest, Teacher Fest, Custom Events, and MCQ.
- **Intended Path**: State admins can view state-level rollups for all event types.
- **Break Description**: Dedicated state-tier aggregation views exist only for Kalotsav and Sports Meet. Kids Fest, Teacher Fest, Custom Events, and MCQ have no state-tier rollup controllers or pages.

### 3.4 Sports Meet vs Generic Fest Navigation Inconsistency
- **Entry Page**: Sahodaya Admin Fest Sidebar (`sportsEventNav.js` vs `sahodayaEventNavPermissions.js`)
- **Element / Action**: Accessing Judges, Marks Import, and Item Heads.
- **Intended Path**: Consistent navigation items across all fest event types.
- **Break Description**: Sports Meet sidebar omits "Judges" and "Marks import" links present in generic fest sidebars (even though the underlying routes work). Conversely, Sports sidebar includes "Item heads" which generic fest sidebars lack.

### 3.5 Sahodaya Finance Ledger Account Link Permission Gate
- **Entry Page**: Sahodaya Fest Finance Hub (`/sahodaya-admin/{tenant}/events/{event}/finance`)
- **Element / Action**: Clicking "Link Event to Ledger Account".
- **Intended Path**: `sahodaya_finance` user maps event fee revenue to specific general ledger accounts.
- **Break Description**: `TenantUserCatalog::writePermissionForPath()` incorrectly requires `fest.manage` instead of `fest.finance` for `updateLedgerAccount`, blocking finance staff from completing ledger account assignments.

---

## 4. School Admin Portal

### 4.1 School Custom Events & Teacher Training Parity Gap
- **Entry Page**: School Admin Fest & Training Hubs
- **Element / Action**: Managing Custom Events or Teacher Training.
- **Intended Path**: School admins can manage registrations, attendance, results, and certificates for custom events and teacher training programs.
- **Break Description**: Custom events lack fest-day desks, clash tools, results, and certificate views. Teacher training only supports registration and fee receipt upload, with zero execution tracking, attendance logs, results, or certificates.

### 4.2 Missing Sidebar Links for Download All Certificates & Appeals
- **Entry Page**: School Admin Fest Event Overview
- **Element / Action**: Accessing "Download All Certificates" or "Event Appeals".
- **Intended Path**: School admin downloads all student certificates in bulk or files an event appeal.
- **Break Description**: The controller routes (`download-all` and `appeals` in `FestEventPortalController`) exist and work, but no sidebar or page links exist in the school navigation to reach them.

### 4.3 Rejection History & Reversed Receipt Invisibility
- **Entry Page**: School Payment History (`/school-admin/{tenant}/payments`)
- **Element / Action**: Reviewing payment history after a fee receipt is rejected or reversed.
- **Intended Path**: School admin sees a complete audit log of all payment attempts, rejections with reasons, and reversals.
- **Break Description**: The view only displays the current fee receipt pointer (`feeReceipt`). Re-uploading a corrected receipt after a rejection overwrites the pointer, causing the rejected receipt and reason to permanently disappear from history. Reversed receipts also appear as un-submitted/zero due without explanation.

### 4.4 Lack of Resubmit Path for Rejected Registrations
- **Entry Page**: School Event Registrations (`/school-admin/{tenant}/events/{event}/registrations`)
- **Element / Action**: Resolving a rejected student registration.
- **Intended Path**: Clicking "Edit & Resubmit" on a rejected registration to fix errors.
- **Break Description**: Rejected registrations offer no resubmit button or option. Schools are forced to delete or leave the rejected item and re-enter a new registration from scratch without clear UI guidance.

---

## 5. Student, Teacher, Judge & Portal-Tier

### 5.1 Group Admin Complete Absence of Results & Certificates
- **Entry Page**: Group Admin Portal (`/portal/group-admin/{tenant_id}`)
- **Element / Action**: Viewing event results or student certificates for assigned class groups.
- **Intended Path**: Class group supervisors (`group_admin`) view performance, results, and certificates of students in their group.
- **Break Description**: Group admin has pages for registrations, schedules, and admit cards, but zero Results or Certificates pages exist (unlike `house_admin` which features `Ranking.vue`).

### 5.2 Exam Staff Misleading 403 Navigation Link
- **Entry Page**: Exam Staff Portal Sidebar (`examPortalNav.js`)
- **Element / Action**: Clicking "Mark Entry" link.
- **Intended Path**: Exam staff enter marks or link is hidden if unpermitted.
- **Break Description**: `examPortalNav.js` unconditionally displays the "Mark entry" link to `exam_staff`. However, server-side authorization in `ExamOpsController` explicitly blocks `exam_staff` with a 403 HTTP error.

### 5.3 Absence of In-Portal Results for Exam Controllers & Staff
- **Entry Page**: Exam Portal (`/portal/exam-controller/{tenant_id}`)
- **Element / Action**: Reviewing published exam rank lists or student scores after mark entry.
- **Intended Path**: Exam controllers view exam results and rank lists within their portal.
- **Break Description**: No results or rank list page exists under `/portal/exam-*`. Controllers can enter marks and monitor attendance, but cannot view final results in their portal.

---

## 6. Operational & Financial Transition Gaps

### 6.1 Container Cancellation Cascade Failure
- **Entry Page**: Sahodaya Event / Exam / Training Admin Pages
- **Element / Action**: Changing status of an Event, MCQ Exam, or Training Program to `cancelled`.
- **Intended Path**: System prompts admin, cancels child registrations, issues fee credits/refunds to schools, and sends notifications.
- **Break Description**: Marking an event or exam as `cancelled` only updates the container status field. Child registrations remain active, collected payments remain un-refunded, and zero notifications are dispatched to enrolled schools.

### 6.2 MCQ & Training Registration Cancel Money Stranding
- **Entry Page**: Sahodaya / School MCQ & Training Fee Panels
- **Element / Action**: Cancelling a paid MCQ or Training registration.
- **Intended Path**: Cancelled registration triggers an automated fee credit entry (`FestFeeCredit` equivalent) for the school.
- **Break Description**: Cancelling a paid registration reduces `total_due` but leaves `amount_paid` untouched without creating a fee credit record, leaving school funds stranded and unreconciled.

### 6.3 Missing Sahodaya Action for School MCQ Registration Cancellation
- **Entry Page**: School MCQ Panel -> Cancel Registration
- **Element / Action**: School tries to cancel an approved MCQ registration.
- **Intended Path**: School is instructed to contact Sahodaya admin -> Sahodaya admin cancels registration in admin portal.
- **Break Description**: The system tells the school to contact Sahodaya, but Sahodaya Admin has no route or controller action to cancel an individual MCQ registration.

### 6.4 Absence of Membership Cancellation Exit Path
- **Entry Page**: Sahodaya Membership Fees Page
- **Element / Action**: Processing a school withdrawing mid-year from Sahodaya membership.
- **Intended Path**: Admin executes membership cancellation with pro-rated fee credit or refund record.
- **Break Description**: `SchoolMembershipCancellationService::canCancel()` hard-blocks cancellation once payment is verified, offering no exit workflow or credit mechanism.

---

## Summary Matrix of Evaluated Pathways

| Portal / Module | Pathway / Action | Status | Flow Break Description |
|---|---|---|---|
| **Auth / Login** | School Coordinator Roles Login | ❌ Broken | 5 roles locked out with "No portal assigned" |
| **Auth / Login** | Mark Entry Admin Login | ⚠️ Divergent | Redirected to Sahodaya admin; portal branch dead code |
| **Auth / Login** | School Event Coordinator (0 scope) | ❌ Broken | Self-referential redirect loop |
| **Public Site** | Join Sahodaya Application | ⚠️ Insecure | Emails credentials pre-approval; no public status check |
| **Public Site** | Nav links to Schedules / Results | ❌ Missing | Controller & views exist, but 0 links in navbar/footer |
| **Public Site** | Live Scoreboard vs Official Results | ⚠️ Inconsistent | Scoreboard leaks interim marks prior to publish sign-off |
| **Public Site** | Circulars & MCQ Leaderboards | ❌ Missing | No public circular views or MCQ leaderboard routes |
| **Sahodaya Admin** | Custom Fest Events Navigation | ⚠️ Hidden/Thin | Nav hardcoded hidden; missing fest-day, results, certs |
| **Sahodaya Admin** | MCQ Certificate Generation | ❌ Missing | No certificate models or routes for MCQ exams |
| **Sahodaya Admin** | State Rollups (Kids/Teacher/MCQ) | ❌ Missing | Only Kalotsav and Sports have state rollup views |
| **Sahodaya Admin** | Sports Meet Navigation Parity | ⚠️ Inconsistent | Missing Judges & Marks Import links in sidebar |
| **Sahodaya Admin** | Finance Ledger Account Assignment | ❌ Blocked | Requires `fest.manage` instead of `fest.finance` |
| **School Admin** | Custom Events & Teacher Training | ⚠️ Thin | Missing fest-day desk, results, certs, attendance |
| **School Admin** | Download All Certs & Appeals Links | ❌ Missing | Routes exist, but no navigation links exist in UI |
| **School Admin** | Payment History & Rejections | ❌ Missing | Rejected/reversed receipts vanish upon re-upload |
| **School Admin** | Rejected Registration Resubmit | ❌ Missing | No "edit & resubmit" button for rejected registrations |
| **Portals** | Group Admin Results & Certs | ❌ Missing | Group admin has 0 post-result visibility for students |
| **Portals** | Exam Staff Mark Entry Link | ❌ 403 Error | Nav item shown to staff but 403s on click |
| **Portals** | Exam Controller Results Page | ❌ Missing | No in-portal results view after entering exam marks |
| **Operations** | Event/Exam Container Cancellation | ❌ Broken | Does not cancel registrations, refund fees, or notify |
| **Operations** | MCQ/Training Cancel Money Refund | ❌ Broken | Strands paid fees without creating credit records |
| **Operations** | Sahodaya MCQ Cancel Action | ❌ Missing | Sahodaya admin cannot cancel individual MCQ rows |
| **Operations** | Membership Cancellation Path | ❌ Missing | Hard-blocked once fee paid; no refund/exit path |
