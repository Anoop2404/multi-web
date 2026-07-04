# Full Project Gap Analysis
**Date:** 2026-07-03  
**Scope:** UI/UX · User Flows · Permissions · Role Pages · Workflows · Setup Wizard

---

## 1. UI / UX Issues

### 1.1 Global / Cross-Cutting

| # | Issue | Severity |
|---|-------|----------|
| U1 | No global loading indicator — Inertia page transitions have no spinner/progress bar at the top | Medium |
| U2 | Flash banners disappear too fast (or not at all on slow connections) — no persistent notification drawer | Medium |
| U3 | No breadcrumb trail anywhere — users lose context when navigating deeply (e.g., inside an event → item → mark-entry) | High |
| U4 | Mobile sidebars have no hamburger/off-canvas toggle — full sidebar pushes content off-screen on small devices | High |
| U5 | Empty-state pages show only grey text ("No items yet") with no CTA button — users don't know what to do next | Medium |
| U6 | Date/time fields throughout forms use plain `<input type="date">` — no local-timezone awareness, no Malayalam/regional date format option | Low |
| U7 | Error messages from the backend (422 validation) shown as raw field-level red text with no summary banner at top — easy to miss on long forms | Medium |
| U8 | No confirmation dialog on destructive actions (delete student, delete school, remove payment) — just direct POST/DELETE | High |
| U9 | `<a target="_blank">` PDF links have no loading state — user clicks, nothing seems to happen | Low |
| U10 | Inconsistent button labels: some say "Save", others "Update", others "Submit" — no design system rule | Low |

### 1.2 Sahodaya Admin

| # | Issue | Severity |
|---|-------|----------|
| S1 | Settings page has 7-tab layout but the "setup checklist" amber dots don't persist after first save — user closes tab and dots are gone until next page load | Medium |
| S2 | Academic year tab shows a list, but there is no clear "active year" highlighted — easy to edit the wrong year | High |
| S3 | Fee slab editor (per-class or fixed) has no preview of what a school would actually pay — users must mental-math it | Medium |
| S4 | Registration window form shows V2 fields (add_open/add_close/edit_open/edit_close) but the labels say "Registration opens/closes" — confusing; "edit" window meaning is unexplained | High |
| S5 | Membership submissions list (`/membership/submissions`) has no bulk approve/reject — approving 80 schools one-by-one is painful | High |
| S6 | Payment verification page shows uploaded proof as a link — no inline image preview, no PDF viewer | Medium |
| S7 | School list has no search/filter — once 50+ schools are added it becomes unusable | High |
| S8 | ZeptoMail settings form has no "Send test email" button — no way to verify config before going live | High |
| S9 | Events list mixes all event types (kalotsav, sports, training, MCQ) with no type filter | Medium |
| S10 | Dashboard "recent activity" timestamps show ISO string, not human-readable (e.g. "3 minutes ago") | Low |
| S11 | No "pending school applications" count on dashboard or sidebar badge — only visible if admin navigates to Schools → Pending | High |
| S12 | No way to resend invite email to a school admin directly from the Schools page | Medium |

### 1.3 School Admin

| # | Issue | Severity |
|---|-------|----------|
| SA1 | Student list has no column sorting or pagination controls visible on first load | Medium |
| SA2 | Student import via CSV shows no error summary if some rows fail — only a flash saying "X imported" | High |
| SA3 | Annual registration workflow has 5+ steps but no step progress indicator (step 1 of 5, etc.) | High |
| SA4 | Fee preview on registration page shows ₹ amount but not the breakdown (per student slab × count) | Medium |
| SA5 | Payment upload form accepts any file — no client-side format check (PDF/image only) | Medium |
| SA6 | School settings page is separate from setup — user goes back and forth between `/setup/code` and `/settings` | Medium |
| SA7 | Teachers page has no indication of which teachers are also portal users vs. data-only records | Medium |
| SA8 | Fest registrations page has no visual diff between "registered", "approved", "rejected" — all look the same, just a text badge | Medium |
| SA9 | Payment history page has no filter by year — lists all payments ever | Low |
| SA10 | No way to download/print school's own registration receipt from the registration workflow — must go to Payments → Receipts separately | Medium |

### 1.4 Portal UIs (Student, Teacher, Judge, FestOps, etc.)

| # | Issue | Severity |
|---|-------|----------|
| P1 | Portal layout header is very minimal — only role label + sign-out. No school name in context for Judge/FestOps who may manage multiple Sahodayas | Medium |
| P2 | Student dashboard crams everything into one scrolling page (registrations + MCQ + schedule + results + certs) — no tabbed or sectioned UX | High |
| P3 | Teacher dashboard same problem — MCQ banks, training, fest reg, schedule, admit cards, results, certs, fees, appeals all stacked vertically | High |
| P4 | FestOps dashboard shows event list but after clicking an event, the back button returns to root, not the event list — no breadcrumb | Medium |
| P5 | Judge portal has no "all done" state — when all marks are entered there's no confirmation or completion badge | Low |
| P6 | Exam portal (ExamOps) has no date/time shown for when each exam starts — only list of rooms | Medium |
| P7 | Student profile page (`/portal/student/{id}/profile`) exists but there's no link to it from the dashboard nav | High |
| P8 | Group Admin portal nav only shows 5 items; "Fest Schedule" and "Admit Cards" are not visually grouped or labelled | Low |
| P9 | House Admin portal has no stats on dashboard — just a list of registrations with no summary | Low |
| P10 | FestCoordinator portal has only 2 pages (Dashboard + MarkEntry) with no event/item summary visible | Medium |
| P11 | MCQ exam page has no timer UI even though time limits exist in the data model | High |
| P12 | MCQ result page shows "Score: X" but no pass/fail, no percentile, no comparison to class average | Medium |

---

## 2. User Flow Issues

### 2.1 Sahodaya Onboarding Flow (First-Time Admin)

Current flow has no guided path. A new Sahodaya admin logs in and sees the dashboard with an action queue — but the 7 required settings are buried in a tabbed Settings page. The sequence is not obvious:

**Missing:** A first-time setup wizard that blocks or redirects until all 7 checklist items are done.

**Specific gaps:**
- F1: After logging in, a new Sahodaya admin lands on the dashboard. If nothing is set up, the dashboard shows an empty action queue + a basic "Get started" step list — but only if `approved_schools == 0`. Once even one school is approved, this guide disappears permanently.
- F2: No enforced order — admin can try to create an event before setting an academic year, and get a confusing DB error rather than a friendly block.
- F3: Setting up ZeptoMail (step 7) requires API key from a third-party service — no in-app link to ZeptoMail signup or documentation.
- F4: No concept of "Sahodaya profile complete" state — the amber warning dots on Settings tabs are the only signal, but they don't appear on the dashboard.

### 2.2 School Joining Flow

- F5: A school applies via the Sahodaya's public subdomain. Once submitted, the school admin gets an email (if mail is configured) and waits. There is no application status page — if the email is missed, the school has no way to check if they were approved.
- F6: After approval, the school admin receives a welcome email with credentials. There is no guided first-login flow — they land directly on the school dashboard.
- F7: School dashboard shows a 3-step guide (set code → register students → membership) but step 2 ("register students") is optional for membership — confusing for schools whose Sahodaya requires only teacher counts.
- F8: No indication anywhere that the school code, once set and locked, cannot be changed — this has major downstream effects (all reg numbers change) but is not warned.

### 2.3 School Annual Registration Flow

- F9: Registration has no "save draft" — if the school admin navigates away mid-flow, their changes may be lost.
- F10: Track approval flow (school → Sahodaya → per-track status) has no notification to school admin when a track is approved or rejected — they must poll the registration page.
- F11: When the edit window closes (`edit_close`), the registration is hard-blocked. But the school receives no pre-closure reminder notification.
- F12: Payment upload requires a specific format but there's no example or template link shown.
- F13: After registration is "completed", the school has to go to Payments → Receipts to find their membership receipt — no direct link on the completion screen.

### 2.4 Portal User First Login

- F14: All portal users (Student, Teacher, Judge, FestOps, etc.) have `must_change_password` enforced. The password change page is functional but has no context — it just says "Change password" with no explanation of why.
- F15: After changing password, portal users land on their dashboard with no introduction to what they can do — no welcome message, no role explanation, no links to most common actions.
- F16: Student portal: if a student has no fest registrations yet, the dashboard shows multiple empty sections ("No fest registrations yet", "No scheduled fest items yet", "No published results yet") — looks broken, not "you're all set".
- F17: Judge portal: if a judge has no assignments, dashboard just says "No active events with assignments for your account" — no contact info for who to reach out to.
- F18: FestOps portal: assignments are shown but there's no explanation of what each duty role means (e.g., "Coordinator", "Stage Manager") for first-time users.

### 2.5 MCQ Student Flow

- F19: Student must navigate to MCQ Hub → click exam → read details → then click "Take exam". There's no "Start exam" button visible without clicking through to the exam detail.
- F20: After exam submission, the student sees "Submitted" status but no confirmation receipt/timestamp that they can screenshot.
- F21: If an MCQ exam auto-submits (expired), the student sees "submitted" with no explanation that it was auto-submitted.

---

## 3. Permission / Middleware Gaps

| # | Issue | Severity |
|---|-------|----------|
| PM1 | Sahodaya Staff role exists in the 13-tier model but has no dedicated sidebar nav or scoped permission set — it appears to share the same access as Sahodaya Admin | High |
| PM2 | School Staff role exists but is not distinguished from School Admin in the routing middleware — no separate portal or reduced-access variant | High |
| PM3 | `password.change` middleware is on all portal routes. But Sahodaya Admin and School Admin routes (`/sahodaya-admin/*`, `/school-admin/*`) do NOT have this middleware — a Sahodaya admin with a system-generated password can access everything without being forced to change it | Critical |
| PM4 | No rate limiting on the public school application endpoint (`POST /apply`) — open to spam | Medium |
| PM5 | School admin can view `/school-admin/{tenantId}/users` and reset any user password — including other admins. No "can only reset passwords for lower-role users" guard | Medium |
| PM6 | FestOps duty roles are string labels (e.g., "Coordinator", "Gate check") but there's no enforcement that only users with duty X can access route Y within the event — all FestOps users see all sub-pages | Medium |
| PM7 | MCQ exam: `can_take_online` flag is computed on the frontend from `delivery_mode` and window status — no server-side guard that prevents a student from POST-ing exam answers outside the window | High |
| PM8 | Judge mark entry has no "items assigned to you" filter at the server level — all judges assigned to an event can submit marks for any item in that event, not just their assigned items | High |
| PM9 | No audit trail for admin user management actions (create user, reset password, delete user) — only MCQ/registration/mark-entry have audit logging | Medium |
| PM10 | Group Admin can see all students in their group's schools (`/portal/group/{id}/students`) — no check that the group actually belongs to the current Sahodaya | Medium |

---

## 4. Missing / Incomplete Dedicated Pages Per Role

### 4.1 Sahodaya Admin — Missing Pages

| Missing Page | Description |
|---|---|
| Bulk school approve/reject | No UI to select multiple pending schools and approve/reject in one action |
| Notification templates UI | Route and controller exist but the page at `/notification-templates` likely has minimal CRUD — no preview/test-send per template |
| School application status tracker | No page showing all school applications with timeline (applied → reviewed → approved/rejected) |
| Ledger export | Ledger page exists but no CSV/Excel export of full ledger |
| MCQ payments dashboard | MCQ payment nav was previously hidden; now visible but needs a dedicated verification workflow page |
| Office bearers public page | `/office-bearers` page exists but no way to set "current term" vs. "past term" per bearer |
| Training attendance | Training programs exist but no consolidated attendance register per session |
| State remittance tracker | Page exists but no way to mark individual remittances as sent/received/confirmed |

### 4.2 School Admin — Missing Pages

| Missing Page | Description |
|---|---|
| School profile / about page | No page to edit school's own info (name, address, logo, principal name) beyond the public website builder |
| Class management | Classes come from Sahodaya — school admin cannot see which classes are assigned to them in a clear list |
| Bulk student operations | No bulk promote (e.g., move all Class 9 students to Class 10 at year-end), no bulk deactivate |
| Student profile detail | Student list shows basic info but clicking a student shows no detail page — no history, no class history, no registration history |
| Annual registration history | No page showing past years' registration records and receipts in one place |
| Fest payment breakdown | School can see total fest fee but no itemised breakdown per program |
| Contact us / support | No support/help page within school admin panel |

### 4.3 Portal Roles — Missing/Thin Pages

| Role | Missing Page | Description |
|---|---|---|
| Student | My Profile (deep link) | Profile page exists but not linked from dashboard nav |
| Student | Notifications | No in-app notification centre — relies entirely on email |
| Student | MCQ Hall ticket list | Hall tickets are linked per-exam on dashboard, no consolidated "my hall tickets" page |
| Teacher | My students list | Teacher can see fest registrations but no easy "which students are in my class" view |
| Teacher | Profile edit | Profile.vue exists but no edit form — read-only |
| Judge | Assignment detail | No page showing the full judging criteria or rubric for assigned items |
| FestOps | Kitchen management | Kitchen.vue exists but appears minimal — no meal count summary or per-school breakdown |
| FestOps | Certificates bulk print | Certificates.vue exists but no bulk-print or download-all option |
| ExamOps | Mark entry status | Attendance.vue + MarkEntry.vue exist but no progress overview across all rooms |
| Group Admin | Group profile | No page to view/edit the group's own details or member school list |
| House Admin | Points tally | Ranking.vue exists but no historical points timeline or event-by-event breakdown |
| FestCoordinator | Item schedule | Dashboard shows assignments; no dedicated schedule view per item |

---

## 5. Workflow Issues

### 5.1 Membership Registration Workflow

| # | Issue |
|---|---|
| W1 | When Sahodaya rejects a track, the school sees "rejected" but there's no field for the rejection reason — school doesn't know what to fix |
| W2 | `allApplicableTracksApproved()` blocks payment until all tracks are approved, but if a Sahodaya never reviews a track, the school is permanently blocked |
| W3 | Once a registration is "completed", there's no way to amend data — even clerical errors require manual DB intervention |
| W4 | Academic year activation does not warn if there's already a registration in progress for the old year — schools mid-flow get silently broken |
| W5 | No workflow for partial membership (school with 0 students) — the system requires at least one class to have students before fee calculation works |

### 5.2 Fest / Kalotsav Workflow

| # | Issue |
|---|---|
| W6 | No "fest publish checklist" — Sahodaya admin can publish an event without setting venue, schedule, or items, leading to an empty event page for schools |
| W7 | Appeals workflow: school submits appeal, FestOps reviews — but there's no in-app notification to either party at each stage |
| W8 | Chest number assignment is manual — no auto-assign based on registration order or item grouping |
| W9 | Results entry (mark entry) is per-judge per-item — but if two judges are assigned to the same item, there's no conflict resolution or averaging logic |
| W10 | Certificate generation requires `uuid` on the result record — no batch "generate all certificates" action, must be done record-by-record |
| W11 | No "close event" workflow — events stay "open" indefinitely unless manually changed |
| W12 | Program/catalog seeding auto-runs on first FestOps hub visit, but if the catalog changes mid-event, there's no "re-seed" button for Sahodaya admin |

### 5.3 MCQ Workflow

| # | Issue |
|---|---|
| W13 | MCQ fee payment: school uploads proof, Sahodaya verifies — but the MCQ payments nav was hidden until recently; workflow unclear for Sahodaya staff |
| W14 | No concept of "MCQ session" — if a student loses connection mid-exam, there's no resume mechanism |
| W15 | Question bank: teacher uploads questions, but there's no review/approval step by Sahodaya — questions go live immediately |
| W16 | Offline MCQ delivery mode exists (`delivery_mode = 'offline'`) but there's no workflow page for ExamOps to record offline scores in bulk |
| W17 | MCQ series (collection of exams) exists in the data model but the series hub navigation and workflow are incomplete |

### 5.4 Academic Year Workflow

| # | Issue |
|---|---|
| W18 | Academic year activation copies fee slabs and registration windows, but doesn't copy notification templates or event catalog — Sahodaya must manually recreate each year |
| W19 | No "year close" action — old academic years just become inactive; historical data accessible but no formal archiving |
| W20 | When a new academic year is activated mid-year, schools that already completed registration for the old year see their status silently shift — no notification |

---

## 6. Setup Wizard Design — Full Recommendations

### 6.1 Sahodaya Admin First-Time Setup Wizard

**When:** Triggered on first login (flag: `sahodaya_setup_complete = false` on SahodayaProfile).  
**Pattern:** Full-page wizard overlay with 7 steps, skippable but with a persistent "X of 7 steps completed" badge on the sidebar until complete.

**Steps:**

**Step 1 — Identity**
- Set Sahodaya prefix (short code)
- Set display name, logo, address
- Explain: "Your prefix appears in all school and student registration numbers (e.g. KLR-SCH-001)"

**Step 2 — Academic Year**
- Create or activate the current academic year
- Show calendar picker for start/end dates
- Explain: "All membership registrations, fees, and events are tied to the active academic year"

**Step 3 — Membership Fees**
- Choose fee type (fixed / per-student slab / none)
- Enter amounts
- Show live preview: "A school with 350 students would pay ₹X"

**Step 4 — Registration Window**
- Set add_open / add_close (new registrations)
- Set edit_open / edit_close (amendments)
- Tooltip: "Schools can only begin registration during the add window. They can make corrections during the edit window."

**Step 5 — Payment Details**
- Add bank account / UPI details that schools see when paying
- Upload receipt template (optional)
- Explain: "Schools will see these details when uploading payment proof"

**Step 6 — Classes**
- Define class list (LKG, UKG, 1–12, etc.)
- These provision to all member schools automatically
- Explain: "Classes you add here will be available to all your member schools for student registration"

**Step 7 — Email (ZeptoMail)**
- Enter ZeptoMail API key, from-name, from-email
- Add a "Send test email" button that sends to the admin's own email
- Link: "Get your ZeptoMail API key →" (opens zepto.mail in new tab)
- Explain: "Without this, all emails to schools go from the platform's default address, not your Sahodaya's"

**Completion screen:**
- "Your Sahodaya is ready! Here's what you can do next:" with 3 quick-action cards (Invite schools, Create first event, Browse settings)
- Mark `sahodaya_setup_complete = true`

---

### 6.2 School Admin First-Time Onboarding Wizard

**When:** Triggered on first login after Sahodaya approves the school (`membership_status = 'active'`, `school_setup_seen = false`).  
**Pattern:** 3-step mini-wizard shown as a card on the dashboard until complete. Non-blocking — can dismiss and return.

**Step 1 — Set School Code**
- Input: short prefix (e.g., "AHS")
- Show example reg number: "Your students will get numbers like KLR-AHS-001"
- **Warn prominently:** "This cannot be changed once set and students are registered. Choose carefully."
- Link directly to `/setup/code`

**Step 2 — Register Students**
- Two options: manual entry or CSV import
- Show current student count
- Link to `/students?register=1` and `/students?import=1`
- If classes not yet set by Sahodaya, show: "Waiting for your Sahodaya to configure classes"

**Step 3 — Complete Annual Registration**
- Show registration window dates
- Show fee preview (₹X based on current student count)
- If window is closed: show next window open date
- If window is open: CTA "Begin annual registration →"

**Completion banner:** "Setup complete — your school is ready for Sahodaya membership."  
Persist membership reg number + academic year in a top banner for the rest of the year.

---

### 6.3 Portal User First-Login Experience

**All portal roles** (Student, Teacher, Judge, FestOps, FestCoordinator, GroupAdmin, HouseAdmin, ExamOps):

**Current state:** User changes password → lands on dashboard with no orientation.

**Recommended flow:**

1. **Password change page** — add context text: "Welcome to [Sahodaya Name]. Your account has been set up by the administrator. Please set a password to continue."

2. **Role welcome screen** (one-time, shown once after first password change):
   - Show role name + icon
   - 2–3 sentences explaining what this portal is for
   - List of the 3 most common actions with direct links
   - "Got it, take me to my dashboard →" button
   - Store `portal_welcome_seen = true` on the user record

**Role-specific welcome content:**

| Role | Welcome text | Top 3 actions |
|---|---|---|
| Student | "Your student portal shows your fest registrations, MCQ exams, schedule, and results." | View registrations · Take MCQ exam · Download certificates |
| Teacher | "Your teacher portal shows your MCQ question banks, training programs, and fest assignments." | Manage question banks · View training · Download admit cards |
| Judge | "You have been assigned as a judge. Enter marks for your assigned events and items here." | View assignments · Enter marks · Check progress |
| FestOps | "You are an event operations volunteer. Manage attendance, gate check, and stage assignments." | Gate check · My assignments · Attendance |
| FestCoordinator | "You coordinate mark entry for assigned fest items." | Enter marks · View assignments |
| GroupAdmin | "Your group admin portal shows student registrations, schedules, and admit cards for your group's schools." | View registrations · Download admit cards · Check schedule |
| HouseAdmin | "Manage house points, student assignments, and rankings." | View students · Check ranking · My registrations |
| ExamOps | "Manage MCQ exam supervision, attendance, and mark recording for offline exams." | Attendance · Supervision · Mark entry |

---

### 6.4 Platform Admin / Super Admin Setup Guide

**When:** First deployment / new tenant creation.

**Gaps currently:**
- No guided Sahodaya creation flow — Super Admin manually creates a Tenant and sets up subdomain
- No health check dashboard showing which Sahodayas are "setup complete" vs. partial
- No way to impersonate a Sahodaya admin for support purposes

**Recommended additions:**
- "New Sahodaya" wizard in Platform Admin: enter name, subdomain, region → auto-create tenant + first Sahodaya Admin user → send welcome email
- Platform dashboard: list all Sahodayas with setup completion % (based on the 7-item checklist)
- "Impersonate" button on Sahodaya record (with audit log)

---

## 7. Summary Priority Table

### P0 — Critical (Security / Data Integrity)

| # | Gap | Recommended Fix |
|---|---|---|
| PM3 | Sahodaya Admin and School Admin routes missing `password.change` middleware | Add `EnsurePasswordChanged` to both route groups |
| PM7 | MCQ exam answers accepted server-side outside time window | Add server-side window check in `McqController::submit()` |
| PM8 | Judges can submit marks for non-assigned items | Filter items server-side by assignment in mark-entry controller |

### P1 — High (Workflow Blockers)

| # | Gap | Recommended Fix |
|---|---|---|
| W1 | Track rejection has no reason field | Add `rejection_reason` to registration track model + UI |
| W2 | Unreviewed tracks permanently block payment | Add Sahodaya-side "mark all reviewed" action or auto-approve timeout |
| S5 | No bulk school approve/reject | Add checkboxes + bulk action to Submissions page |
| S8 | No ZeptoMail test email | Add test-send endpoint + button in email settings tab |
| PM1 | Sahodaya Staff role has no scoped access | Define Staff permission set, add middleware guard |
| PM2 | School Staff role same as School Admin | Define Staff permission set, separate portal or restricted nav |
| SA3 | Registration flow has no step progress indicator | Add step indicator (1/5, 2/5...) to annual registration pages |
| U8 | No confirmation on destructive actions | Add modal confirm before delete/reset operations |

### P2 — Medium (UX / Completeness)

| # | Gap |
|---|---|
| F1–F4 | Sahodaya setup wizard — persistent, enforced setup flow |
| F6–F8 | School onboarding wizard — guided first-login experience |
| F14–F18 | Portal role welcome screen — orientation after first login |
| S7 | School list search/filter |
| S11 | Pending school applications badge on sidebar/dashboard |
| U4 | Mobile sidebar off-canvas |
| U3 | Breadcrumb navigation |
| P7 | Student profile linked from dashboard nav |
| P11 | MCQ timer UI |
| W6 | Fest publish checklist |
| W10 | Batch certificate generation |

### P3 — Low (Polish)

| # | Gap |
|---|---|
| U10 | Consistent button label language |
| U6 | Timezone-aware date pickers |
| S10 | Human-readable activity timestamps |
| P9 | House Admin dashboard stats |
| W11 | Formal "close event" workflow |
| W19 | Academic year archiving / formal year-end close |

---

## 8. Recommended Implementation Order

1. **Security first:** PM3 (`password.change` on admin routes), PM7 (MCQ server-side window guard), PM8 (judge item filter)
2. **Workflow unblocks:** W1 (track rejection reason), S5 (bulk school approve), W2 (track review timeout)
3. **Sahodaya setup wizard** — drives new client adoption and reduces support tickets
4. **School onboarding wizard** — same; most friction is at first login
5. **Portal welcome screens** — low backend effort, high UX improvement
6. **Role separation:** PM1 (Sahodaya Staff), PM2 (School Staff)
7. **UX polish:** U3 breadcrumbs, U4 mobile sidebar, U8 delete confirmations, S8 test email

---

*End of gap analysis. See SETUP_WORKFLOW_AUDIT_REPORT.md for the earlier P0/P1/P2 fixes that have already been applied.*
