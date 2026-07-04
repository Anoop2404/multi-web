# Full Project Audit Prompt

Paste this prompt into a new Claude session (with the `multi-web` folder connected) to run a deep audit of all known problem areas.

---

## PROMPT START

You are auditing a Laravel 11 multi-tenant SaaS called **multi-web** (Stancl Tenancy + Inertia.js + Vue 3 + Tailwind).

The project is a Sahodaya school management platform with these role tiers:
Super Admin → State Admin → Sahodaya Admin → School Admin / Principal / VP / Event Coordinator → Student Portal / Teacher Portal / Judge Portal / FestOps / Mark Coordinator / Group Admin / House Admin / Exam Supervisor / Public Portal.

Your job is to find **real, concrete issues** — bugs, missing features, broken flows, UI/UX problems, data inconsistencies, security gaps. Do NOT list generic best-practice advice. For every issue: state the file + line (or table/route), the exact problem, and what the correct fix looks like.

Work through each section below. Read the actual source files before concluding anything. Cross-check controllers against their Vue pages. Cross-check routes against controllers. Cross-check DB columns against what code actually reads/writes.

---

## SECTION 1 — MCQ SYSTEM

Files to audit:
- `app/Http/Controllers/SahodayaAdmin/McqController.php`
- `app/Http/Controllers/SchoolAdmin/McqController.php`
- `app/Http/Controllers/Portal/McqController.php` (student exam-taking)
- `app/Services/Mcq/` (all services)
- `resources/js/Pages/Admin/Sahodaya/Mcq/` (all Vue pages)
- `resources/js/Pages/Admin/School/Mcq/` (all Vue pages)
- `resources/js/Pages/Admin/Portal/Exam/` (student portal MCQ pages)
- `database/migrations/tenant/` — any migrations touching `mcq_*` tables
- `routes/web.php` — MCQ route groups

Check for:
1. **Exam lifecycle gaps**: Can a Sahodaya admin publish → schools register students → students take the exam → auto-submit fires → marks are locked → results are published? Trace the full flow. Identify any step where no controller action or Vue page exists.
2. **MCQ Series / multi-level** (`mcq_exam_series`, `series_id`, `exam_level`, `parent_exam_id` columns added in Feature Plan V2 migration): Are these columns used anywhere in code yet? If `McqSeriesPromotionService.php` exists, does any controller call it?
3. **Hall ticket generation**: Does `McqHallTicketService` get triggered? Is there a "print all hall tickets" route? Does the student portal show the hall ticket before exam day?
4. **Eligibility enforcement**: Does `McqEligibilityService` get called before a student can enter the exam room? Is there a door at exam start?
5. **Auto-submit cron**: Does the `mcq:auto-submit-expired` command exist? Does it correctly find sessions past `ends_at` and call `McqExamSessionService::autoSubmit()`?
6. **Payment gate**: Can a school access MCQ features without paying? Check `McqSchoolFeeService` — is it enforced as middleware or a gate check?
7. **Results page**: Does the student portal show MCQ results after exam closes? Does the Sahodaya admin have a ranklist/results view?
8. **Question bank ownership**: Can a school admin accidentally see another school's private question banks? Check `where('tenant_id', ...)` scoping.
9. **MCQ mark entry by Exam Supervisor portal**: Does the portal role `exam-supervisor` have routes and pages for offline mark entry?
10. **Missing Vue pages**: List any Sahodaya MCQ pages that a controller returns but whose Vue file does not exist in `Pages/Admin/Sahodaya/Mcq/`.

---

## SECTION 2 — MEMBERSHIP SYSTEM

Files to audit:
- `app/Http/Controllers/SahodayaAdmin/MembershipController.php`
- `app/Http/Controllers/SchoolAdmin/MembershipController.php` (or `AnnualRegistrationController.php`)
- `app/Services/Membership/` (all services)
- `resources/js/Pages/Admin/Sahodaya/Membership/`
- `resources/js/Pages/Admin/School/Registration/`
- `database/migrations/tenant/` — `*membership*` and `*registration*` migrations

Check for:
1. **Registration window enforcement**: Does `AnnualRegistrationController::begin()` check that today is inside the Sahodaya's open window (`add_open`/`add_close`)? What happens if a school tries to register outside the window — is there a user-facing error or a silent 403?
2. **Fee calculation**: Does `MembershipFeeCalculator` receive the correct student count? Trace from the controller that calls it — is it reading from DB or from a form field?
3. **Dual registration windows** (Feature V2 adds `add_open`, `add_close`, `edit_open`, `edit_close` to `sahodaya_registration_windows`): Are all four columns used? Is the edit window enforced separately from the add window?
4. **Renewal flow**: Does `membership:update-renewal-status` cron exist and run `RegistrationStatusService`? What does it do — check it.
5. **Reminder emails**: Does `membership:send-reminders` trigger `MembershipNotifier`? What email is sent and to whom?
6. **School payment status**: Can a school that hasn't paid membership still access fest registration? Trace `SchoolPaymentStatusResolver` — is its result checked at any gate?
7. **State remittances**: Is there a working flow for Sahodaya to record that they've remitted fees to the State office? Check `StateRemittanceLedgerService` and its controller/Vue.
8. **Ledger accuracy**: Does `LedgerService` / `LedgerReportingService` correctly reflect membership payments vs. fest payments vs. MCQ payments as separate line items?
9. **Receipt generation**: Does `MembershipReceiptService` generate a PDF receipt? Is it downloadable from the school admin panel?
10. **Missing pages**: Is there a Sahodaya admin page to see all schools' membership status for the current year in one table?

---

## SECTION 3 — ROLE PERMISSIONS & MIDDLEWARE

Files to audit:
- `app/Http/Middleware/` (all middleware)
- `routes/web.php` (all middleware stacks on route groups)
- `app/Http/Controllers/SchoolAdmin/SchoolAdminController.php` (base controller, staff permissions)
- `app/Policies/` (if any exist)
- `app/Models/User.php` (roles, permissions)

Check for:
1. **School staff sub-roles**: Principal, VP, Event Coordinator each exist as roles. Does `SchoolAdminController` restrict actions based on these sub-roles? E.g., can a VP access financial routes that should be Principal-only?
2. **Portal role isolation**: Can a Student Portal user navigate to `/school-admin/...` by manually typing the URL? Is there a middleware that blocks this?
3. **Judge portal access**: Does a judge only see items they're assigned to, or can they see all items? Check `FestJudgeGateService` and the Judge portal controller.
4. **Mark Coordinator**: Can a Mark Coordinator access mark entry for items they are not assigned to? Trace `FestMarkCoordinatorAccess`.
5. **FestOps portal**: What exactly can FestOps do that a normal school admin cannot? Is there a distinct FestOps middleware or is it role-checked per action?
6. **Event Coordinator scope**: The V2 migration adds `school_user_event_scopes`. Is this table populated anywhere? Is it enforced in any controller?
7. **Super Admin vs State Admin separation**: Can a State Admin access Super Admin routes? Check middleware on the `Admin/` route group vs. the `SahodayaAdmin/` group.
8. **`must_change_password` middleware**: Is `EnsurePasswordChanged` applied to all portal routes? Does it correctly skip the password-change page itself to avoid a redirect loop?
9. **CSRF / API routes**: Are there any API routes under `routes/api.php` that are missing auth middleware?
10. **Permission caching**: Does the staff permissions check in `SchoolAdminController` hit the DB on every request, or is it cached?

---

## SECTION 4 — UI/UX ACROSS ALL PAGES

Files to audit:
- `resources/js/Layouts/` (all layout files)
- `resources/js/support/` (all nav JS files)
- `resources/js/Pages/Admin/School/` (all Vue pages — scan for patterns)
- `resources/js/Pages/Admin/Sahodaya/` (all Vue pages — scan for patterns)
- `resources/js/Pages/Admin/Portal/` (all portal Vue pages)
- `resources/js/Components/` (shared components)

Check for:
1. **Empty states**: Do list pages (students, teachers, registrations, events, etc.) show a proper empty state with a call-to-action when there's no data, or do they show a blank table?
2. **Flash messages**: After form submissions, is there a consistent `<FlashMessage>` or toast component? Are success/error messages shown on every form that POSTs?
3. **Loading/disabled states**: Do buttons disable themselves while a form is submitting (`form.processing`)? Check key action buttons — bulk provision, approve, reject, submit marks.
4. **Pagination**: Do all list pages that return paginated data render a paginator? Are there pages that cap at 25 records with no way to page through?
5. **Confirmation dialogs**: Are destructive actions (delete student, reject request, close registration) protected by a confirm dialog or modal? Or do they fire immediately on click?
6. **Form validation feedback**: Do forms show inline field-level error messages from the Inertia `form.errors` object? Or do errors only appear as a generic flash?
7. **Mobile responsiveness**: Do sidebar layouts collapse to a hamburger on mobile? Do wide tables overflow or scroll horizontally?
8. **Broken/placeholder pages**: Are there any Vue pages that render "Coming soon" or "TODO" or are effectively blank?
9. **Nav items with no matching page**: Walk through all items in `sahodayaAdminNav.js` and `schoolAdminNav.js`. For each `href`, verify a route exists in `web.php` and a Vue page exists. List any orphaned links.
10. **Page titles / `<Head>` tags**: Do all pages set a meaningful `<Head title="...">` via Inertia, or are many pages missing titles (bad for browser tabs and accessibility)?
11. **Breadcrumb consistency**: Do pages that are 3+ levels deep show breadcrumbs? Or does the user lose their place?

---

## SECTION 5 — SPORTS MEET

Files to audit:
- `app/Http/Controllers/SahodayaAdmin/SportsMeetController.php`
- `app/Http/Controllers/SchoolAdmin/SportsMeetController.php`
- `app/Services/Events/FestSportsAgeGroupRegistry.php`
- `app/Services/Events/FestSportsAutoRankService.php`
- `app/Services/Events/FestSportsCompositeFeeService.php`
- `resources/js/Pages/Admin/Sahodaya/Sports/`
- `resources/js/Pages/Admin/School/Sports/`

Check for:
1. **Open-age group bug**: When a student is registered in an "open" age group (no upper/lower bound), does the eligibility check pass or fail? Trace `FestSportsAgeGroupRegistry` — does it handle `null` bounds?
2. **School-to-state promotion**: Does a sport result at school level automatically qualify the winner for state-level? Or is there a manual step? Is the state-level event automatically created?
3. **Auto-ranking**: Does `FestSportsAutoRankService` correctly rank by time (for athletics — lower is better) vs. points (for other sports — higher is better)? Check how it determines the ranking direction.
4. **Fee composite**: Does `FestSportsCompositeFeeService` handle the case where a student enters multiple sports events — is there a combined fee cap?
5. **Result entry UI**: Is there a page for a school admin or sports-meet admin to enter results (time/points/rank) per student per event? Or are results only entered via import?
6. **Athletic records**: Does `FestAthleticRecordService` correctly detect new records after results are saved? Does it notify anyone?
7. **Sports registration close**: Is there a separate registration window for sports vs. fest? Can schools register for sports after the fest registration window closes?
8. **Missing state-level pages**: Do `Pages/Admin/Sahodaya/Sports/` pages exist for: results list, individual item results, final rankings, export?

---

## SECTION 6 — KALOTSAV (FEST / CULTURAL EVENTS)

Files to audit:
- `app/Http/Controllers/SahodayaAdmin/` — all Fest/Event controllers
- `app/Http/Controllers/SchoolAdmin/FestController.php` (or equivalent)
- `app/Services/Events/` — all Fest* services
- `resources/js/Pages/Admin/Sahodaya/Events/`
- `resources/js/Pages/Admin/Sahodaya/Kalotsav/`
- `resources/js/Pages/Admin/School/Fest/`
- `resources/js/Pages/Admin/School/Events/`
- `resources/js/support/sahodayaEventNav.js`

Check for:
1. **Item registration eligibility**: When a school registers a student for an item, does `FestRegistrationEligibilityService` correctly check: (a) age/class eligibility, (b) participation limits per student, (c) combo-rule violations (`FestComboRuleService`), (d) mandatory item requirements (`FestMandatoryItemService`)?
2. **Chest number assignment**: Does `FestChestNumberService` assign numbers without gaps or duplicates? What happens if a registration is cancelled — does the number get freed or stay assigned?
3. **Schedule conflicts**: Does `FestScheduleConflictService` prevent a student from being assigned to two items at the same time? Is this enforced during schedule import?
4. **Mark entry flow**: Can a Judge portal user submit marks? Does `FestMarkSaveService` validate that marks are within the defined range? Does it prevent double-submission?
5. **Results publication**: Is there a "publish results" action? What does it do — does it lock marks and make results visible to schools and the public portal?
6. **Grade point calculation**: Does `FestGradePointService` correctly compute A+/A/B/C based on the sahodaya's configured thresholds? Are thresholds per-item or global?
7. **Certificate generation**: Does `FestCertificateService` generate per-participant certificates? Can a school admin download all certificates for their students in one ZIP?
8. **Registration approval**: Is `FestRegistrationApprovalService` used — i.e., does Sahodaya admin need to approve school registrations, or is it automatic?
9. **Cascade / levels**: Does `FestCascadeService` correctly promote winners from school-level → Sahodaya-level → State-level? Are there pages for each level?
10. **Public visibility**: Does `FestPublicVisibilityService` gate what the public portal shows? Can results be visible publicly before Sahodaya publishes them?
11. **Event workspace inner sidebar**: After the July 2026 audit, the event workspace sidebar was restructured. Verify the current `sahodayaEventNav.js` has 6 correct sections (no "Main menu", no duplicate icons, "Administration" not "More"), and that all `href` values match actual routes.
12. **Kids Fest cluster**: Does `FestKidsFestClusterService` exist and work? What pages does it expose?
13. **Performance order**: Is there a page for Sahodaya admin to set the performance order (draw of lots)? Does it export to a printable schedule?

---

## SECTION 7 — STUDENT & TEACHER PORTALS

Files to audit:
- `app/Http/Controllers/Portal/` (all portal controllers)
- `app/Services/Portal/`
- `app/Services/Students/`
- `resources/js/Pages/Admin/Portal/Student/`
- `resources/js/Pages/Admin/Portal/Teacher/`
- `resources/js/support/studentPortalNav.js`
- `resources/js/support/teacherPortalNav.js`

Check for:
1. **Student portal provisioning**: Does `StudentPortalProvisioner::ensureRegNoLogin()` correctly handle: (a) student with no `reg_no` (should skip), (b) student already has a `user_id` (should skip), (c) `$notify: true` sends the welcome email via `PortalWelcomeNotifier`?
2. **Must-change-password on first login**: After provisioning, is `must_change_password = true` set? Does `EnsurePasswordChanged` middleware intercept the student's first request and redirect to the change-password page?
3. **Student edit lock**: Does `StudentEditLockService` correctly resolve the 3-layer lock (emergency → school override → global window) when the student tries to edit their profile?
4. **Student change requests**: When `StudentEditChangeService` submits a change request, does it go to the right approval queue? Is there a school admin page to review and approve/reject student change requests?
5. **Teacher portal profile**: The new `TeacherProfileController` (`edit`, `update`, `updatePassword`) was created. Verify: (a) the route is registered, (b) `Portal/Teacher/Profile.vue` exists, (c) the form correctly PUTs to `/portal/teacher/{id}/profile`, (d) password update sets `must_change_password = false`.
6. **Teacher portal nav completeness**: `teacherPortalNav.js` now has 8 items (Home, Fest, Schedule, Results, Certificates, Training, MCQ Banks, Profile). Do all 8 `href` values have matching routes and Vue pages?
7. **Student results page**: Does `Portal/Student/Results.vue` exist? Does it show fest item results, MCQ results, and sports results separately?
8. **Student certificates page**: Does `Portal/Student/Certificates.vue` exist? Can a student download their certificates?
9. **Teacher training**: Can a teacher view and enroll in training programs from the portal? Is the enrollment confirmed by email?
10. **Judge portal**: Does a judge see only their assigned items? Can they submit marks from the portal? Is there a mark-entry form?

---

## SECTION 8 — NOTIFICATIONS & EMAILS

Files to audit:
- `app/Services/Notifications/`
- `app/Services/Mail/`
- `app/Services/Events/FestEventNotifier.php`
- `app/Services/Mcq/McqExamNotifier.php`
- `app/Services/Membership/MembershipNotifier.php`
- `routes/console.php` (all scheduled commands)

Check for:
1. **Cron jobs registered**: Confirm all 5 crons in `routes/console.php` are present: `fest:registration-reminders` (daily 09:00), `fest:schedule-reminders` (every 15 min), `mcq:auto-submit-expired` (every 5 min), `membership:update-renewal-status` (daily 02:00), `membership:send-reminders` (daily 08:30).
2. **Email driver**: Is the mail driver configured for production (ZeptoMail / SMTP), or is it still set to `log` in `.env.example`?
3. **Missing notification triggers**: Are there events that should send an email but don't? E.g.: (a) when Sahodaya publishes results, do schools get notified? (b) when a judge is assigned to an item, do they get notified? (c) when MCQ results are published, do students/schools get notified?
4. **`ZeptoMailApiClient`**: Does this service correctly implement the ZeptoMail API? Does it handle errors gracefully (retry, log failure)?
5. **FCM push**: Does `FcmPushService` send push notifications to the student/teacher mobile app? Is the FCM token stored on the user model?

---

## SECTION 9 — DATA INTEGRITY & EDGE CASES

Check for:
1. **Tenant scoping**: Do all tenant-scoped queries include `where('tenant_id', ...)` or use the tenant DB connection? Look for any query that reads from a `students`, `teachers`, `registrations`, or `events` table without a tenant scope.
2. **Soft deletes**: Do models that support soft delete (`deleted_at`) correctly exclude deleted records in all queries? Use `grep -r "withTrashed\|withoutTrashed\|onlyTrashed"` to see where they're used.
3. **Race conditions**: Receipt number generation — is it protected by a DB lock? Student chest number assignment — is it atomic?
4. **File uploads**: Do image/document uploads validate file type and size? Is there a disk configured for tenant file storage?
5. **Feature Plan V2 migration columns**: The migration `2026_07_03_000001_feature_plan_v2.php` adds many columns. For each new column, check whether any controller/service actually reads or writes it, or if it's all still dormant.

---

## OUTPUT FORMAT

For each section, produce a table:

| # | File / Route / Table | Issue | Severity (P0/P1/P2/P3) | Suggested Fix |
|---|---|---|---|---|

Severity guide:
- **P0** — data loss, security breach, broken core flow (no workaround)
- **P1** — feature doesn't work, user gets error or wrong data
- **P2** — missing page/route, workflow incomplete but workaround exists
- **P3** — UI/UX problem, cosmetic, or nice-to-have

At the end, summarise: total issues by severity, top 5 most critical to fix first, and any cross-cutting patterns you noticed (e.g., "tenant scoping missing in 3 controllers").

## PROMPT END
