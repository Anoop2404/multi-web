# End-to-End User Journey Audit Findings

Companion report to `USER_JOURNEY_AUDIT_PROMPT.md`. Produced by walking every role's full journey (login → onboarding → registration → configuration → execution → review/approval → publishing/results → post-result) against the actual code, not the nav files alone. Every citation below is a real file/line found in this repo at time of audit; where a prior audit doc already logged the same issue, it's marked `(known — see FILE)` rather than re-derived.

**Legend:** ✅ complete · ⚠️ partial/has a caveat · ❌ missing/broken · 🚫 not applicable to this role/event-type combo.

---

## 0. Top findings (read this first)

These are new, previously-undocumented, journey-shaped gaps — the kind that only surface when you walk a role's entire path rather than auditing one screen at a time.

1. **Release-blocking login bug, not a dead role**: `school_finance_coordinator`, `school_training_coordinator`, `school_mcq_coordinator`, `school_kalotsavam_coordinator`, `school_sports_coordinator` are fully assignable today through the real staff-creation UI (`Pages/Admin/School/Users/Index.vue` → `TenantUserController::store()` → `TenantUserProvisioner::upsert()` → `syncRoles()`), but `AuthController::homeFor()` (`app/Http/Controllers/Admin/AuthController.php:365-439`) has no branch for any of the 5. A user assigned only one of these roles passes login, then is **immediately logged back out** with "Your account has no portal assigned" (line 224-235). `TenantUserCatalog.php` and `EnsureSchoolAdmin.php` both fully model these roles (permission defaults, middleware gating) — only `homeFor()` was never updated. This is a shipped, reachable, 100%-reproducible lockout for anyone given one of these titles.
2. **`mark_entry_admin` login routing has dead code**: `homeFor()` line 383-392 sends `mark_entry_admin` to `/sahodaya-admin/{tenant_id}` and returns immediately; a second check at line 422 that would send it to `/portal/fest-coordinator/{tenant_id}` can never execute because PHP's early-return `if` chain already matched. Cross-checked against `docs/USER_FLOWS_AND_PAGES.md`, `SYSTEM_PROMPT.md`, and `PortalWelcomeController::portalRoles()` (which excludes `mark_entry_admin`) — the Sahodaya-admin landing is the intended/real behavior, so the line-422 reference to `mark_entry_admin` is misleading dead code that should be removed.
3. **Custom fest events are structurally incomplete** compared to the 6 dedicated programs (Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, Science Fest — note: the live roster is 6 programs, not the 4 named in the audit brief). `FestProgramController` only wires create → items/policy → marks. No fest-day/attendance view, no clash/substitution workflow, no results/qualifiers page, no reports suite, no certificates/ID cards exist for Custom events at the school tier. On the Sahodaya side, the "Custom events" nav item itself is hardcoded `hidden: true` (`sahodayaAdminNav.js:375`) despite the backend/pages working — reachable only by direct URL.
4. **MCQ exams have no certificate pipeline at all**, unlike every fest event type. No `mcq.certificates.*` route exists anywhere. Students who pass/top an MCQ exam get no certificate.
5. **MCQ has no public results/leaderboard route** — not merely admin-only as `FULL_AUDIT_REPORT.md` describes, but a full absence of any route stub. The only public MCQ surface is a question-paper archive (`/mcq/papers`), not a leaderboard.
6. **`group_admin` has zero post-result visibility** — no Results or Certificates page/route/controller-method exists anywhere in `GroupAdminController` or `groupPortalNav.js`, in contrast to the structurally similar `house_admin`, which has a full `Ranking.vue` results-equivalent view. The clearest single missing-stage finding for any portal role.
7. **`exam_staff` sees a nav link that 403s**: `examPortalNav.js` unconditionally shows "Mark entry," but `ExamOpsController::marks`/`storeMark` hard-blocks `exam_staff` server-side. Also, neither `exam_controller` nor `exam_staff` has any in-portal results/ranklist view after entering marks all exam day.
8. **Public results/schedule pages are orphaned, not missing**: `/fest/{event}/results`, `/fest/{event}/schedule`, `/fest` (index), and `/mcq/papers` are fully built and correctly gated, but have **zero nav entry points** anywhere in `NavConfigDefaults`/`PortalNavLinks`. A parent or school can only reach them via a manually shared direct link.
9. **Public results gating inconsistency**: `/fest/{event}/scoreboard` has no `results_published` check (visible any time), while `/fest/{event}/results` strictly 404s until published — a visitor can see live/interim scores through one URL while the "official" one is still gated.
10. **School-application flow issues credentials before approval, and has no public status-check page**: `SchoolApplicationController::store()` creates the `User` (role `school_admin`) and emails login credentials immediately at submission — before Sahodaya approval — and there's no public "check my application status" page. Recommend cross-checking that `membership_status='pending'` actually locks down dashboard functionality (not verified in this pass).
11. **State-tier rollup coverage is asymmetric**: dedicated state-level pages exist only for Kalotsav and Sports Meet; Kids Fest, Teacher Fest, Custom, and MCQ have no state-tier aggregation view at all.
12. **Sports Meet's dedicated sidebar is missing "Judges" and "Marks import" links** that the generic event sidebar gives every other event type (routes/controllers work identically for Sports); inversely, Sports' sidebar has "Item heads" links the other event types lack even though the same routes exist for all of them.
13. **Coarse-vs-fine permission gating split** (Sahodaya tier): the main dashboard sidebar unlocks an entire section (e.g. "Fest & events") if a staff user has *any one* relevant permission, but the event-scoped sidebar then applies fine-grained per-tab checks — so `sahodaya_staff` and several narrow roles can open a section and find almost every tab inside it hidden ("a door to an empty room").
14. **Naming-vs-grant mismatches** worth reconciling: `registration_coordinator` has zero `membership.*` permissions (only fest-event registration access) despite its name; `data_entry` additionally receives `fest.manage` (not just `fest.marks`), giving it nav visibility into registrations/results/certificates/catering beyond what "data entry" implies (writes remain correctly gated server-side); `sahodaya_finance` cannot itself link an event to a ledger account because that specific action falls back to requiring `fest.manage` rather than `fest.finance`.
15. **`school_event_coordinator` has a reachable dead-end**: a coordinator whose scope rows are later cleared (e.g. their assigned event is deleted, or their role is toggled off/on) lands on `/school-admin/{id}`, which `DashboardController::index` immediately redirects back to the same `homeUrlFor()` result — a self-referential redirect loop. Also, `scopesForUser()` has no `ORDER BY`, so a coordinator with multiple assignments lands on an arbitrary one each login.

---

## 1. Sahodaya-tier roles

### Ground truth used

`AuthController::homeFor()` (`app/Http/Controllers/Admin/AuthController.php:365-439`) is the single source of truth for post-login landing. Permission-to-role mapping lives in `app/Support/TenantUserCatalog.php::defaultPermissionsForRole()` (lines 280-289) — **not** in the seeder, which only creates empty role/permission rows.

**Nav architecture finding**: the main Sahodaya sidebar (`sahodayaAdminNav.js`, gated via `SahodayaAdminLayout.vue:159-175`'s `STAFF_NAV` map) only checks coarse section-level permissions. Fine-grained per-tab gating (`FEST_REGISTRATIONS`, `FEST_MARKS`, `FEST_RESULTS`, `FEST_FINANCE`, `FEST_CERTIFICATES`, `FEST_SETTINGS`, `FEST_CATERING` bundles) only applies once inside an event, via `sahodayaEventNavPermissions.js` / `SahodayaEventsLayout.vue`.

### superadmin

**Landing dashboard:** `AuthController.php:368` → `route('admin.dashboard')`.

No per-event-type breakdown — superadmin manages tenant provisioning (`admin.sahodayas.index`, `admin.schools.index`, master data, Website Builder, storage migration) one level above Sahodaya tenants. To touch any event-type operations it must impersonate a tenant. Stage-skeleton doesn't apply.

**Verdict:** Complete for its actual scope.

### state_admin / state_staff

**Landing dashboard:** `AuthController.php:372` → `route('admin.state.dashboard')` → `StateAdminDashboardController::index` → `State/Dashboard.vue`.

Aggregated, cross-Sahodaya, read-mostly — no execution authority over any single event.

| Area | Route | Controller | Status | Note |
|---|---|---|---|---|
| Dashboard rollup | `admin.state.dashboard` | `StateAdminDashboardController::index` | ✅ | Cluster-wide rollup + propagation status |
| Kalotsav state rollup | `admin.kalotsav.*` | `Admin\KalotsavStateController` | ✅ | Has `results`, `winners`, `exportWinners` |
| Sports rollup | `admin.sports.index` | `Admin\SportsResultsController::index` | ✅ | No drill-down comparable to Kalotsav's |
| State remittances | `admin.state-remittances.*` | `Admin\StateRemittanceController` | ✅ | Verify/reject present |
| Sahodaya directory | `admin.sahodayas.index` | `TenantController::indexSahodayas` | ✅ | Read-only |
| Kids Fest / Teacher Fest / Custom / MCQ rollup | — | — | ❌ | **No dedicated state-tier controller exists for any of these four** |

**Verdict:** Has gaps — event-type rollup coverage is asymmetric (only Kalotsav/Sports get dedicated pages).

### sahodaya_admin

Full 8-stage journeys traced per event type. All fest types (Kalotsav, Sports Meet, Kids Fest, Teacher Fest, Custom) share the same underlying route set (`routes/web.php:738-939`, `routes/includes/sahodaya_event_programs.php`) via `FestRegistrationReviewController`, `FestEventSettingsController`, `FestScheduleController`, `FestMarkEntryController`, `FestResultsController`, `FestCertificateController`, etc.

**Kalotsav** — ✅ complete (all 8 stages wired: catalog setup → registrations → settings → schedule/chest-numbers/marks → clash/substitution/appeals review → results publish+promote → certificates/championship). Minor orphan: `events.item-heads.*`/`events.competition.index` routes exist but the generic event sidebar has no "Item heads" link (Sports' sidebar is the only one that surfaces it).

**Sports Meet** — ✅ complete, with its own dedicated `sportsEventNav.js` (not the generic sidebar): extra age-group setup, a "Setup hub," program-level Results/Rankings/Championship pages, athletic-record tracking, and record-break certificates that no other event type gets. But its sidebar is **missing "Judges" and "Marks import"** links present in the generic sidebar for other types, even though the same routes/controllers work for Sports too.

**Kids Fest** / **Teacher Fest** — ✅ complete, simpler than Sports (no age-group/item-head split), same generic 8-stage wiring. Teacher Fest correctly routes through the separate teacher-verification membership flow rather than student verification.

**Custom events** — ⚠️ has gaps. Backend/pages are fully functional (catalog → registrations → settings → schedule/marks → review → results → certificates), but the nav entry is hardcoded `hidden: true` (`sahodayaAdminNav.js:375`) — reachable only via direct URL or the "All events" list. Earliest broken stage: **Login/access** (entry point suppressed).

**MCQ exams** — ⚠️ has gaps. Dashboard → question banks → registration/fee approval → exam series/levels → attendance/hall-tickets/session monitor → payments review → results publish/leaderboard/ranking → reports/ledger, all wired and nav-linked. But **no certificate stage exists at all** (no `mcq.certificates.*` route anywhere) — the only event type missing this final stage.

**Membership / Annual Registration** — ✅ complete. Setup wizard → schools/applications → document types/registration windows → student/teacher verification → payment/document review → reports/receipts, all wired; Publishing/results stage correctly 🚫 N/A (membership is an ongoing registry, not an event with a results phase).

### sahodaya_staff

**Permissions:** `TenantUserCatalog.php:206` → `['fest.view', 'mcq.view', 'membership.view', 'website.view']` — view-only across every module, no `*.manage`/`*.marks`/`*.results`/`*.finance`/`*.certificates`/`*.settings`/`*.registrations`.

Result: unlocks every top-level section/hub (Fest & events, MCQ, Membership) but once inside a specific event, nearly every sub-tab (Registrations, Marks, Results, Finance, Certificates, Settings) is hidden — the "door to an empty room" pattern. This is a correct view-only design, not a bug, but worth naming since it's identical across all 7 combos (Kalotsav/Sports/Kids Fest/Teacher Fest/Custom/MCQ/Membership) — only the top hub differs.

**Verdict:** Has gaps by design (view-only role) — Complete for its intended scope.

### registration_coordinator

**Permissions:** `TenantUserCatalog.php:282` → `['fest.view', 'fest.registrations']` — despite the role name, **zero `membership.*` permissions**. The Membership section (schools/students) is entirely hidden. Only the Registrations tab inside any fest event actually works; every other fest tab (Marks/Results/Finance/Certificates/Settings/Schedule/Catering) and all of MCQ are hidden.

**Verdict:** Has gaps if the role is meant to own membership registration (per its name); Complete if meant only for fest-event registrations (its actual grant).

### event_coordinator

**Permissions:** `TenantUserCatalog.php:286` → `['fest.view', 'fest.manage', 'fest.schedule', 'fest.settings']`. `fest.manage` alone satisfies 6 of 8 `FEST_*` nav bundles (registrations, marks, results, certificates, catering, plus its own schedule/settings grants) — broader than the role's narrow name implies, though clearly an intentional "execution lead" role. Only Finance (`fest.finance` required specifically) and MCQ/Membership (no `mcq.view`/`membership.view`) are excluded.

**Verdict:** Complete for fest execution/scheduling across all event types; N/A for MCQ/Membership (correctly out of scope).

### sahodaya_finance

**Permissions:** `TenantUserCatalog.php:283` → `['fest.view', 'fest.finance', 'finance.view', 'membership.view']`. Finance tab inside every fest event works fully; Sahodaya-wide Ledger + State remittances work (`finance.view`/`membership.view` unlock them). Two gaps: (1) `updateLedgerAccount` (linking an event to its ledger account) falls through to a generic `fest.manage` requirement in `TenantUserCatalog::writePermissionForPath()` rather than `fest.finance` — the finance role can't do this specific finance-adjacent action; (2) no `mcq.view`, so MCQ ledger/payments are hidden from nav (though the MCQ ledger page itself has no additional gate and is reachable by direct URL).

**Verdict:** Has gaps (ledger-account-linking stage misassigned) — otherwise complete for viewing/recording finance.

### certificate_collector

**Permissions:** `TenantUserCatalog.php:284` → `['fest.view', 'fest.certificates']`. The cleanest-scoped narrow role found in the whole audit: exactly one permission maps 1:1 to certificate routes (event certificates, global certificate templates, certificate search, Sports record-break certificates) across every fest type, with correct exclusion of every other tab. N/A for MCQ (no certificate system exists there regardless of role).

**Verdict:** Complete for all fest types; N/A for MCQ.

### data_entry

**Permissions:** `TenantUserCatalog.php:285` → `['fest.view', 'fest.manage', 'fest.marks']`. Marks entry works correctly across all fest types (Sports' missing marks-import nav link affects this role too, same as `mark_entry_admin`). The extra `fest.manage` grant (beyond just `fest.marks`) means Registrations/Results/Certificates/Catering tabs are also nav-visible — broader than "data entry" implies, though writes on those pages remain correctly gated by their own specific permission strings server-side. No MCQ/Membership access.

**Verdict:** Has gaps/inconsistency (over-broad nav visibility, not an actual write-access breach).

### mark_entry_admin

**Restated routing bug**: always lands on `/sahodaya-admin/{tenant_id}` (line 383-392 matches first), never the portal — see Top Finding #2. **Permissions:** `TenantUserCatalog.php:287` → `['fest.view', 'fest.marks']` — the cleanest marks-only scope alongside `certificate_collector`; no MCQ access.

**Verdict:** Complete for the fest marks-entry stage itself; the role's identity/routing (not its permission scope) is the actual defect.

---

## 2. School-tier roles

### Ground truth used

`AuthController::homeFor()` lines 394-404: `school_admin`/`school_principal`/`school_vice_principal` → `/school-admin/{tenant_id}`; `school_event_coordinator` → scoped via `SchoolUserScopeService::homeUrlFor()`; `school_staff` → `/school-admin/{tenant_id}`. `EnsureSchoolAdmin` middleware (`app/Http/Middleware/EnsureSchoolAdmin.php:54-58`) write-gates only `school_staff`, `group_admin`, `house_admin` — the 5 suspect coordinator roles are **not** in that gated list either, one more sign the code never expected them to reach this path as intended.

**Live program roster correction**: the actual coded roster (`schoolProgramNav.js:5-12`, `SCHOOL_FEST_PROGRAMS`) is **Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, Science Fest** — 6 dedicated programs, not the 4 named in the audit brief — plus a 7th, separately-architected "Custom events" mechanism via `FestProgramController` that isn't part of that list at all.

### school_admin

**Landing dashboard:** `AuthController.php:394-396` → `/school-admin/{tenant_id}` → `DashboardController::index` → `Dashboard.vue`.

**Kalotsav / Sports Meet / Kids Fest / Teacher Fest / English Fest / Science Fest** — ✅ complete, byte-identical `ForwardsFestProgramActions` wiring across all 6: setup/school-code → per-program registration → event overview → fest-day view → clash/substitution review → results (read-only display; publishing authority correctly sits at Sahodaya tier) → qualifiers/reports. Sports Meet additionally has item-registration and submit-winners stages the others don't (intentional, sport-specific). Orphan found: `download-all certificates` and `appeals` routes (`FestEventPortalController`) have no sidebar entry in any of the three school nav files.

**Custom events** — ❌ has gaps, the most significant finding in this section. Only create → items/policy → marks exist. No fest-day/attendance/registration-desk page, no clash/substitution workflow, no dedicated Results/Qualifiers view, no reports suite, no certificates/ID cards — everything the 6 dedicated programs get downstream of Marks is simply unbuilt for Custom.

**MCQ Exams** — ✅ complete: hub → register (individual/by-class) → fee upload → hall tickets → results (gated on Sahodaya publish) → toppers/reports.

**Teacher Training** — ⚠️ has gaps: only registration + fee-payment upload exist (`TrainingController`, `TrainingRegistrationController`). No execution/attendance tracking, no results view, no certificates, no reports anywhere under Training at school tier — much thinner than MCQ or the fest programs.

**Membership / Annual Registration** — ✅ complete: begin → students/teachers entry → counts → submit-track → payment → receipt; approval correctly happens one tier up at Sahodaya, with the school side only tracking status.

### school_principal / school_vice_principal

Both share `AuthController.php:394-396`'s landing and are treated identically to `school_admin` by every `SchoolAdmin/*Controller.php` for every event-type stage (`EnsureSchoolAdmin` groups all three as `schoolManagementRoles()`). Only difference found: `school_principal` alone can assign `school_admin`/`school_vice_principal` roles to other staff (`TenantUserController::assertRoleCombinationAllowed`) — outside event-type scope. A vestigial, dead-in-practice permission branch exists for `school_vice_principal` in `defaultPermissionsForRole()` (grants `users.manage` that's never actually checked, since vice principal bypasses the permission gate entirely) — code-cleanliness note, not user-facing.

**Verdict:** Complete (identical to school_admin) for both roles.

### school_event_coordinator

**Landing:** `AuthController.php:398-400` → `SchoolUserScopeService::homeUrlFor()` (`app/Services/School/SchoolUserScopeService.php:172-201`), routing to a scoped MCQ/training/fest-event/fest-program URL based on the coordinator's first assigned scope row. Two real gaps: (1) `scopesForUser()` has no `ORDER BY`, so a coordinator with multiple assignments lands on an arbitrary one each login; (2) a zero-scope coordinator (reachable if their assigned event/scope is later cleared) lands on `/school-admin/{id}`, which `DashboardController::index`'s coordinator-redirect immediately sends back to the same URL via `homeUrlFor()` again — a self-referential dead end, and the "No assignments yet" nav fallback item links into the same loop.

Within an assigned scope, registrations/config/execution/review/results/reports are all correctly filtered to just that program/event (`SchoolUserScopeService::filterFestEventsForUser`).

**Verdict:** Has gaps (Login/access stage: non-deterministic landing + reachable dead-end loop for the zero-scope edge case).

### The 5 suspect coordinator roles (finance / training / mcq / kalotsavam / sports)

See Top Finding #1 — **fully assignable, but login-broken.** `TenantUserCatalog::schoolAdminCreatableRoles()` (line 47-50) includes all 5; `Pages/Admin/School/Users/Index.vue` renders them as real radio-button options (though `TenantUserCatalog::roleLabels()` is missing entries for all 5, so the UI shows the raw role slug rather than a label — a second, cosmetic bug stacked on the same defect); `TenantUserController::store()` validates and assigns them via `syncRoles()` with no guard. `TenantUserCatalog::defaultPermissionsForRole()` has fully-built bespoke permission sets for each (e.g. `school_mcq_coordinator` → `['mcq.view','mcq.manage']`), and `EnsureSchoolAdmin.php` explicitly lists all 5 for email-verification and school-status gating — every layer of the system expects these users to log in successfully except `AuthController::homeFor()`, which has no branch for them and returns `null`, forcing an immediate logout with "Your account has no portal assigned."

**Verdict:** Not a dead-role scenario — reclassify as a release-blocking Login/access bug affecting 100% of the journey for any of these 5 roles.

### school_staff

**Landing:** `AuthController.php:402-404` → same dashboard as school_admin. Write-gated via `EnsureSchoolAdmin`/`writePermissionForPath()`; default permissions `['fest.view', 'website.view']` unless the creating admin grants more at creation time. View access to every fest/MCQ/membership stage works as designed; write access correctly requires explicit extra permission grants.

**Verdict:** Complete (permission-gated view working as designed).

---

## 3. Portal-tier roles

### Ground truth used

`AuthController::homeFor()` lines 406-436 map `group_admin`/`house_admin`/`student`/`teacher`/`mark_entry_coordinator`+`mark_entry_admin`(dead branch)/`exam_controller`+`exam_staff`/`judge`/`fest_ops` to their respective `/portal/*` landings.

### student

**MCQ Exam** — ✅ complete: welcome → MCQ hub → hall ticket → exam session (autosave confirmed wired, contradicting an older "no autosave" note — that item is already marked ✅ Fixed in `UIUX_AUDIT_FINDINGS.md` UX-002) → auto-submit on expiry → results (gated on `results_published`). ❌ No certificate stage — MCQ is the only event type where students never get a certificate.

**Kalotsav / Kids Fest / Teacher-adjacent** — ✅ complete: welcome → fest registrations (self-register if the school allows it) → fest schedule → appeals → fest results (gated on `results_published`) → certificates. Certificates are only generated for podium positions (top 3) — a platform policy choice, not a bug, but it means most participants see an empty Post-result stage.

**Sports Meet** — ✅ complete, with its own richer `SportsResults.vue` (adds athletic-record comparisons) in place of the generic fest-results page.

**Student sees results/certificates — the single most important end-of-journey check:** Kalotsav/Kids Fest/Sports Meet — ✅ results and ✅ certificates (top-3 only). MCQ — ✅ results, ❌ certificates (missing entirely).

### teacher

**Teacher Fest** — ✅ complete: welcome → fest page (registration done by school admin; portal is read/appeal) → schedule → appeals → results → certificates.

**MCQ (question-bank authoring, not exam-taking)** — ✅ complete for its actual scope: welcome → question banks → add questions (text/options/document upload). Correctly 🚫 N/A beyond that — exam assembly is Sahodaya-tier. One thin spot: no feedback loop showing the teacher how their authored questions were used or how students performed on them.

### judge

**Kalotsav / Kids Fest / Teacher Fest (scoring)** — ✅ complete: welcome → mark entry (server-side item-scoped via `FestMarkEntryScopeService::judgeItemIds`, addressing a previously-known gap) → save mark (grade enum-validated, not free text — the UX-006 fix already reflected in code). Publishing correctly 🚫 N/A (Sahodaya-tier action).

**Sports Meet** — 🚫 N/A by design: `JudgeDashboardController::marks` explicitly 404s for `event_type==='sports'`; sports scoring goes through `mark_entry_coordinator`/`fest_ops` instead.

### mark_entry_coordinator (+ mark_entry_admin, dead-code caveat)

**Sports Meet (and other fest types generically)** — ✅ complete for `mark_entry_coordinator`: welcome → mark entry (item-head/attendance/rank-points scoped) → attendance + auto-rank (sports-specific). `mark_entry_admin`'s real, working landing is the Sahodaya-admin world (see Top Finding #2), so its "portal" row here is informational only — the middleware technically still accepts it if it reaches the URL directly, but it never lands there via normal login.

### exam_controller / exam_staff

**MCQ** — ⚠️ has gaps: welcome → attendance (bulk CSV import) → supervision (live status) all work for both roles. Mark entry is correctly restricted to `exam_controller` (`exam_staff` is explicitly `abort_unless`-blocked) — but `examPortalNav.js` shows the "Mark entry" link unconditionally, so **`exam_staff` sees a nav item that will 403 if clicked**. Neither sub-role has any in-portal results/ranklist view after entering marks — only 4 Vue pages exist under `Portal/Exam/*` (Attendance, Dashboard, MarkEntry, Supervision), none of them a results page.

### group_admin

**Sports Meet / Kalotsav (class-group oversight)** — ❌ has gaps: welcome → fest registrations (read-only, scoped to assigned classes) → fest schedule → clashes → admit cards, all work. But **no Results or Certificates stage exists at all** — no nav item, no controller method — the only portal role with zero post-event visibility for the students it's scoped to oversee. Stands out because the structurally similar `house_admin` role does have a results-equivalent (`Ranking.vue`).

### house_admin

**Sports Meet (house-points system)** — ✅ complete: welcome → registrations (read-only, house-scoped) → students → house ranking (doubles as the results view via `SchoolHouseFestPointsService`). Correctly 🚫 N/A for a house-level certificate (individual student certs cover this).

### fest_ops

**Kalotsav / Sports Meet / Kids Fest / Teacher Fest (event-day operations, all duty types)** — ✅ complete: welcome (generic duty-agnostic text — known minor gap, `FULL_GAP_ANALYSIS.md` F18) → registrations (duty-gated, fee-gated) → stage/attendance/kitchen/participant-search (finest-grained server-side scoping of any portal role, including per-stage assignment) → mark entry (duty-gated, reuses the fest-coordinator Vue component — good reuse) → appeals → certificates (known: no bulk-print, `FULL_GAP_ANALYSIS.md` §4.3) → gate-check (camera/photo gaps already ✅ Fixed per `UIUX_AUDIT_FINDINGS.md`). Minor cosmetic issue: the "discipline" and "admit_cards" duty keys both map to the same `participants/search` page — harmless but confusing labeling.

---

## 4. Public / unauthenticated journeys

**Architecture note:** every public route in the app renders a classic Blade view (`resources/views/public/*`, `resources/views/sections/*`, `resources/views/fest/certificate-*`) — none of them render a Vue/Inertia page. This is architecturally sound (marketing/verification pages don't need SPA behavior) but means "confirm the Vue page exists" doesn't apply to this whole section.

### Sahodaya/School public site (homepage, about, office bearers, gallery, news, admission enquiry)

✅ Complete where present. Gated behind `website.enabled` + a `public.website.enabled` tenant toggle. **Circulars have no public equivalent at all** — both the Sahodaya and School circular controllers are authenticated-only, so a visitor can never see a published circular.

### School-application / join-a-Sahodaya flow

⚠️ Has gaps. Discovery (homepage CTA) → form → submission all work (`SchoolApplicationController`, throttled, creates tenant + `school_admin` user immediately, emails credentials). Two issues: (1) **no public "check my application status" page** — an applicant can only discover approval state indirectly by trying to log in; (2) **credentials are issued and emailed at submission time, before Sahodaya approval** — inverting the expected order. Recommend a follow-up check that `membership_status='pending'` actually locks down dashboard functionality for a not-yet-approved school.

### Portal landing / login / password-reset pages

✅ Complete — `/portal`, `/login`, `/school-login`, `/portal/login`, `/portal/s/{schoolCode}/login`, forgot/reset-password, and the signed email-verification link are all correctly public with no accidental double-gating.

### Event schedules & results (Kalotsav / Sports Meet / Kids Fest / Teacher Fest, via generic `FestEvent`)

✅ Complete but **orphaned** — `/fest` (index, all event types), `/fest/{event}/schedule`, `/live`, `/scoreboard`, `/search`, `/records`, `/results`, `/items/{item}/results(.pdf)`, and winner-poster SVGs are all real, working, and correctly gated on `results_published`/schedule-publish flags — but **no default nav config links to any of them**. Reachable only via a direct/shared URL. Gating inconsistency: `/scoreboard` has no `results_published` check while `/results` strictly does — live/interim scores can leak through the un-gated URL before official publish.

### MCQ exams (public side)

❌ Missing. Only `/mcq/papers` (question-paper archive) is public, and it too has no nav entry point. No public results/leaderboard route exists for MCQ at all — worse than "admin-only," there isn't even a route stub.

### Public school directory

⚠️ Has gaps. Only a static, unlinked logo grid on the homepage (`/#member-schools` anchor) — no per-school detail page, no search/filter, and it only appears at all if the Sahodaya opted the section into their homepage layout.

### Certificate verification

✅ Complete — the one fully clean, always-on public journey found in this audit. `/certificates/verify/{uuid}` and `/certificates/print/{uuid}` work independent of the `website.enabled` toggle chain that gates almost everything else; QR-code-only discovery (from printed certificates) is correct by design, not an orphan.

---

## 5. Summary matrix

✅ complete · ⚠️ partial/caveat · ❌ missing/broken · 🚫 not applicable

| Role | Kalotsav | Sports Meet | Kids Fest | Teacher Fest | Custom events | MCQ | Membership / Training |
|---|---|---|---|---|---|---|---|
| superadmin | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 |
| state_admin / state_staff | ✅ | ✅ | ⚠️ no rollup | ⚠️ no rollup | ⚠️ no rollup | ⚠️ no rollup | 🚫 |
| sahodaya_admin | ✅ | ✅ (nav asym.) | ✅ | ✅ | ⚠️ hidden nav | ⚠️ no cert stage | ✅ |
| sahodaya_staff | ⚠️ view-only | ⚠️ view-only | ⚠️ view-only | ⚠️ view-only | ⚠️ view-only | ⚠️ view-only | ⚠️ view-only |
| registration_coordinator | ⚠️ registr. only | ⚠️ | ⚠️ | ⚠️ | ⚠️ | 🚫 | ❌ no membership perm |
| event_coordinator | ✅ | ✅ | ✅ | ✅ | ✅ | 🚫 | 🚫 |
| sahodaya_finance | ⚠️ ledger gap | ⚠️ | ⚠️ | ⚠️ | ⚠️ | 🚫 | ✅ view |
| certificate_collector | ✅ | ✅ | ✅ | ✅ | ✅ | 🚫 n/a | 🚫 |
| data_entry | ⚠️ over-broad | ⚠️ + no import link | ⚠️ | ⚠️ | ⚠️ | 🚫 | 🚫 |
| mark_entry_admin | ✅ | ✅ + no import link | ✅ | ✅ | ✅ | 🚫 | 🚫 |
| school_admin / principal / VP | ✅ | ✅ | ✅ | ✅ | ❌ major gaps | ✅ | ✅ (Training ⚠️ thin) |
| school_event_coordinator | ⚠️ scoped, landing bug | ⚠️ | ⚠️ | ⚠️ | ⚠️ | ⚠️ | 🚫 |
| 5 suspect coordinator roles | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ (login itself broken) |
| school_staff | ✅ view/gated | ✅ | ✅ | ✅ | ⚠️ | ✅ | ✅ |
| student | ✅ | ✅ richer | ✅ | 🚫 | ⚠️ untested | ✅ (no cert) | 🚫 |
| teacher | 🚫 | 🚫 | 🚫 | ✅ | 🚫 | ✅ authoring only | 🚫 |
| judge | ✅ | 🚫 by design | ✅ | ✅ | ⚠️ untested | 🚫 | 🚫 |
| mark_entry_coordinator | ✅ | ✅ | ✅ | ✅ | ⚠️ untested | 🚫 | 🚫 |
| exam_controller | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 | ⚠️ no results view | 🚫 |
| exam_staff | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 | ❌ misleading nav | 🚫 |
| group_admin | ❌ no results | ❌ no results | ⚠️ untested | ⚠️ untested | 🚫 | 🚫 | 🚫 |
| house_admin | 🚫 | ✅ | 🚫 | 🚫 | 🚫 | 🚫 | 🚫 |
| fest_ops | ✅ | ✅ | ✅ | ✅ | ⚠️ untested | 🚫 | 🚫 |
| Public (unauthenticated) | ✅ orphaned nav | ✅ orphaned nav | ✅ orphaned nav | ✅ orphaned nav | ⚠️ untested | ❌ no results route | 🚫 |

Cells marked "untested" reflect combinations the underlying subagent passes didn't have evidence for either way (e.g., a judge scoring a Custom event) rather than a confirmed gap — worth a targeted follow-up rather than assuming either complete or broken.

---

## 6. Suggested next steps

1. Fix the two login-routing bugs first (`homeFor()` missing branches for the 5 school coordinator roles; the dead `mark_entry_admin` reference at line 422) — both are one-file changes with outsized real-world impact.
2. Decide whether Custom fest events should be brought up to parity with the 6 dedicated programs, or intentionally left as a lightweight "quick event" type — the current in-between state (registration + marks only, everything else missing) reads as unfinished rather than designed.
3. Decide on MCQ certificates and public MCQ results as a pair — both are "first-class exam type missing the exact features every fest type has," and likely share an implementation approach.
4. Add nav entries for the orphaned-but-complete public fest pages (`/fest`, `/fest/{event}/schedule`, `/fest/{event}/results`, `/mcq/papers`) — low effort, since the backend work is already done.
5. Give `group_admin` a results/certificates view, mirroring `house_admin`'s `Ranking.vue` pattern.
6. This document plus `USER_JOURNEY_AUDIT_PROMPT.md` are ready to convert into the per-role/per-event Mermaid flowchart docs whenever you want that next phase.
