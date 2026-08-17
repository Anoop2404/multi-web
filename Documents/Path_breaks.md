# Path Breaks — Flow Audit

Starting from the Entry Page (`http://localhost:8000` / the central domain, which is where an unauthenticated visitor on a non-tenant host lands), this document maps every role's navigation and flags every place a flow breaks: dead links, blank/crashing pages, wrong destinations, orphaned routes, and misrouted logins. **No application code was changed while producing this document.**

*Status: as complete as this pass could get. See "Coverage status" and the closing note for exactly what was and wasn't exhaustively verified.*

---

## How this was produced (read this before the findings)

This app is far larger than "click every button" can cover literally: route extraction found **2,246 routes** (1,285 of them `GET` pages) across a central super-admin domain, a dedicated State-admin domain, and 9 multi-tenant Sahodaya/school sites, spanning roughly 23 distinct login-routed roles. Per your direction, coverage was scoped to **one pass per role** (using one representative tenant — Malappuram Sahodaya and its AMU Residential School — rather than repeating the same page for all 9 tenants), combining two methods:

1. **Live browser testing** (what "go through each element, remember it, move forward" literally means) — done for the central entry page, login, and the full Super Admin portal. Partway into testing the State Admin role, the browser automation stopped reliably registering clicks on the login form (confirmed via JS inspection: fields were correctly filled, the submit button was enabled, but no network request ever fired across six different interaction methods). Rather than keep burning time on the tooling itself, you directed switching the remaining roles to method 2.
2. **Static code cross-reference** — 4 background passes, each reading every portal's sidebar/nav config file, cross-referencing every link against the actual registered routes and Vue page files on disk, and cross-referencing every role's granted permissions against what its nav actually shows. This catches the same class of bug (dead links, orphaned pages, permission/nav mismatches) without needing a live login, and turned out to catch things live-clicking alone would likely have missed (e.g. a route whose target Vue component doesn't exist on disk).

**Accounts used:** 33 demo accounts were provisioned (one per role that had no working login), covering every role in `AuthController::homeFor()` plus `state_judge` and `region_admin` specifically because those two roles are *absent* from that method (see Finding below). Full list and credentials are in the "Test accounts" appendix at the end of this document.

**Important — this repo already has an active, ongoing self-audit trail.** Before writing new findings, this document cross-checks itself against docs already in the repo:
- `UI_UX_AUDIT_2026_08_14.md` (committed, with uncommitted edits on top) — a full UI/UX pass covering accessibility, visual consistency, and some navigation issues. Its methodology note independently describes the *exact same* live-browser-testing friction encountered here (an accidental live mutation via a native `prompt()` dialog led that session to also pivot to code-first auditing). Where it already covers a flow-relevant finding, this document references it rather than re-deriving it.
- `docs/FLOW_GAP_FIX_PLAN.md` + `docs/CROSS_SYSTEM_FLOW_GAP_AUDIT.md` + `docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md` (all committed, dated 24 Jul 2026) — a financial/workflow gap audit with a phased fix plan. **Verified by reading the current code that most of Phase 1 (money/cancellation gaps) is already implemented** — see "Already fixed" section below. This matters because an earlier, uncommitted draft of this exact document (found on disk, backed up to `Documents/Path_breaks.prior-draft.md`) reported several of these as open bugs; they are not, and are corrected here.
- Code comments throughout reference still more prior audit rounds (a "functional audit, 2026-08-11/12" with `LIFE-0x` fix tags), confirming this is a codebase with a genuine, ongoing gap-finding-and-fixing cadence, not a one-off ask.

---

## Coverage status

| Area | Method | Status |
|---|---|---|
| Central entry page + login | Live | ✅ Done |
| Super Admin portal (`/admin/*`) | Live | ✅ Done, exhaustive |
| Public tenant site / portal landing / fest portal / State | Static | ✅ Done, exhaustive (full agent pass) |
| Sahodaya Admin (full admin + 10 scoped sub-roles) | Static | ⚠️ Targeted only — 4 of 5 prior claims checked directly; no exhaustive route-by-route nav sweep |
| School Admin (full admin + 9 scoped sub-roles) | Static | ⚠️ Targeted only — 3 of 4 prior claims checked directly; no exhaustive route-by-route nav sweep |
| Portal roles (Teacher/Student/Judge/Group/House/Exam/Fest-Coordinator/Fest-Ops/State-Judge) | Static | ✅ Done, exhaustive (full agent pass) |
| Prior-draft claim reconciliation (20 claims) | Mixed | ✅ 18 of 20 reconciled; 2 explicitly flagged as not re-verified (3.3, 4.1) |

**Why Sahodaya Admin and School Admin are lighter than the other three areas:** the dedicated background research agents for these two portals were relaunched after a process interruption, then both failed a second time — the account hit its monthly Claude API spend limit mid-run (worsened by those agents spawning their own nested sub-agents). Rather than keep retrying a mechanism that had just failed twice, the remaining verification for these two portals was done directly, by hand, targeted at the specific prior-draft claims rather than the full "every route, every nav file" sweep the other three areas got. This means: **the specific claims below are solidly verified with file:line evidence, but these two portals — the largest in the app (408 and 625 routes respectively) — were not swept for the general class of bug (orphaned routes, broken nav links, role/permission mismatches) the way Public/Central/State and Portal-roles were.** If exhaustive coverage of these two matters, that sweep still needs to happen in a follow-up pass.

---

## Confirmed live bugs (personally reproduced in-browser)

### 1. Super Admin → entire "State Workspace" nav group renders a blank white page
- **Where:** Super Admin sidebar → "Qualifier Intakes" (`/admin/state-workspace/qualifiers`), "State Finals" (`/admin/state-workspace/fest`), and the sidebar's "Board Results" (`/admin/board-results`). Also reachable via the (working) `/admin/kalotsav` page's "Review Intakes" button, which points at the same broken URL.
- **What happens:** Completely blank page. No error, no message — just white. Browser console: `Uncaught (in promise) Error: Page not found: ./Pages/Admin/StateAdmin/Qualifiers/Index.vue` (same shape for BoardResults).
- **Root cause:** An in-progress, **uncommitted** refactor in the working tree. Six controllers (`StateQualifierReviewController`, `StateFestWorkspaceController` ×2, `StateAttendanceController`, `StateBoardResultsController`) render Inertia pages named `StateAdmin/...`. These routes are served through `resources/views/admin.blade.php` + `resources/js/admin.js`, whose page-loader **only** looks inside `./Pages/Admin/**` (`admin.js` line ~41). The actual Vue files have already been moved to `resources/js/Pages/StateAdmin/**` (outside that folder) — `git status` shows the old files under `Pages/Admin/StateAdmin/**` as deleted-but-uncommitted (backed up to `Pages/_to_delete/Admin_StateAdmin_2026_08_15/`) and the new ones at `Pages/StateAdmin/**` as modified/untracked. The controllers were never updated to match, so every one of these pages now points at a location the page-loader can't see.
- **Likely relation to other work:** `routes/state.php`'s own comments reference `docs/STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md` as the governing plan for an active State-domain rollout — this is very likely a regression mid-way through that rollout, not an unrelated one-off mistake.
- **Who's affected:** Superadmin (bypass access, confirmed live) and real `state_admin`/`state_staff` users hitting the identical controllers (not yet independently live-verified for that role — see Coverage status).
- **Fix direction (not applied — reporting only):** either move the six `Inertia::render()` calls to a path admin.js's loader can see, or add `./Pages/StateAdmin/**` to `admin.js`'s glob and update the six render calls to drop the "Admin/" implied prefix consistently — whichever matches where this refactor is actually headed.

### 2. Super Admin → "Sports Results" (`/admin/sports`) — 500 Internal Server Error, every time
- **What happens:** Full Laravel debug crash page. `SQLSTATE[42P01]: Undefined table: relation "fest_events" does not exist`.
- **Root cause:** `SportsResultsController::index()` (`app/Http/Controllers/Admin/SportsResultsController.php:21-40`) queries the `FestEvent`/`FestMark` models directly with no tenant context. This app runs one **separate Postgres database per Sahodaya cluster** (`TENANCY_DATABASE_PER_SAHODAYA=true`) — `fest_events` only exists inside each cluster's own database, never on the shared central one this controller runs against. This isn't data-dependent — it will 500 on any environment with per-cluster databases, regardless of what's seeded. Contrast with the *working* `/admin/kalotsav` page (`KalotsavStateController`), which correctly queries `FestStateProgram` — a genuinely central table — instead.
- **Severity:** High — hard crash on a page reachable directly from the main sidebar, for every superadmin.

### 3. Super Admin → "External Schools" list (under a State Program's external Sahodayas) — blank page
- **Where:** State Programs → an External Sahodaya → "Schools" (`admin.state-programs.external-schools.index`, `/admin/state-programs/external-sahodayas/{externalSahodaya}/schools`). Linked from `resources/js/Pages/Admin/StatePrograms/ExternalSahodayas.vue:40`, so a real admin clicking through the normal UI will hit this.
- **Root cause:** Same family of bug as Finding 1, different mistake. `ExternalSchoolController.php:34` calls `inertia('Admin/ExternalSchools/Index', ...)`. Because the page-loader (`admin.js`) already prefixes every render call with `./Pages/Admin/`, this resolves to `./Pages/Admin/Admin/ExternalSchools/Index.vue` — a doubled "Admin/Admin/" path that doesn't exist. The real file is one level up, at `Pages/Admin/ExternalSchools/Index.vue`.
- **Not yet live-verified** (found via static cross-reference) but the mechanism is identical to the confirmed Finding 1, so confidence is high.

### 4. Super Admin sidebar → "Site Builder & Themes" group never renders (dead code, not a missing feature)
- **Where:** `resources/js/Layouts/AdminLayout.vue:143-220`.
- **What happens:** The Theme / Nav / Footer / Widgets builder pages (`/admin/builder/theme`, `/nav`, `/footer`, `/widgets`) exist, work, and have real controllers — but there is **no link to any of them anywhere in the app**. A superadmin can only reach them by typing the URL directly.
- **Root cause:** `AdminLayout.vue`'s `superNavGroups` computed property has an unconditional `return [...]` at line ~203, followed immediately by dead code (an `if (websiteEnabled.value) { groups.push(...) }` block, referencing a `groups` variable that is never declared anywhere in the file). That block can never execute — it's unreachable regardless of any feature flag. This looks like an incomplete refactor (the intent to conditionally add the group is clearly there in the code), not an intentional omission.

---

## Already fixed — do not re-report (verified against current code)

An earlier, uncommitted draft of a similar audit was found on disk during this pass (backed up to `Documents/Path_breaks.prior-draft.md`). Several of its claims describe real gaps that the project's own `docs/FLOW_GAP_FIX_PLAN.md` (24 Jul 2026) already identified **and has since fixed**. Verified directly against the current code (not just the plan document):

| Prior claim | Verified current state |
|---|---|
| "Marking an event/exam/training program `cancelled` only updates a status field — no cascade to registrations, no refunds, no notifications" | **Fixed.** `FestEventStatusService`, `McqExamStatusService`, and `TrainingProgramStatusService` all implement `transitionToCancelled($container, $confirmCreditAll)` — guards against cancelling with unresolved payments, cascades to child registrations in a transaction, issues credits, and notifies. |
| "Sahodaya Admin has no action to cancel an individual MCQ registration; the school-side error message points at a workflow that doesn't exist" | **Fixed.** `McqExamController::cancelRegistration()` exists (`routes/web.php:1341`) — reason required, blocks once started/submitted, voids the hall ticket, frees the seat, issues a fee credit, audits, and notifies the school. |
| "Cancelling a paid MCQ/Training registration shrinks `total_due` but leaves `amount_paid` untouched — stranded money, invisible to everyone" | **Fixed.** A `ProgramFeeCredit` model now exists and is wired through 13 files (fee sync services, payment history, reconciliation, ledger, credit notes, credit payouts). |
| "`SchoolMembershipCancellationService::canCancel()` hard-blocks cancellation once any payment is verified, with no exit path" | **Fixed** for the general case — `cancelWithSettlement()` offers `credit_next_year` (default) or `forfeit` (reason required), fully wired to the ledger/credit-note/audit/notify pipeline. `canCancel()`/the no-payment path is unchanged by design (separate method, same pattern as fest's `cancel()` vs `cancelWithRefund()`). |
| "5 school coordinator roles (finance/training/mcq/kalotsavam/sports) get no portal assigned after login" | **Refuted — not a real gap.** `AuthController::homeFor()` has explicit, correct routing for all 5 (verified by reading the live method). Either already fixed independently, or this specific claim was simply incorrect. |
| "`mark_entry_admin` has a dead second routing branch at homeFor() line ~422" | **Partially refuted.** The role does route to the generic `/sahodaya-admin/{tenant}` dashboard rather than a dedicated portal (that part is accurate), but there is only ever one `mark_entry_admin` case in the method — no dead second branch exists as described. |

**Verified STILL OPEN** (read the current method bodies directly — these have not been fixed):

| Prior claim (from `docs/CROSS_SYSTEM_FLOW_GAP_AUDIT.md`, 24 Jul) | Verified current state |
|---|---|
| F3 — "Fee-proof rejection never notifies the school; the school only discovers it by revisiting their billing panel." | **Still open.** `FestSchoolEventFeeController::reject()` (`app/Http/Controllers/SahodayaAdmin/FestSchoolEventFeeController.php:133-165`) reverses/rejects the receipt and writes an audit log — but never calls a notifier. Read the full method body to confirm; no notification call exists anywhere in it. |
| F4 — "School withdraw is silent to the admin — an approved participant can vanish from an item without the Sahodaya or event staff hearing about it." | **Still open.** `SchoolAdmin\FestRegistrationController::withdraw()` (`app/Http/Controllers/SchoolAdmin/FestRegistrationController.php:1280-1297`) calls `FestRegistrationService::cancel()` and audits, but has no notifier call to sahodaya_admin/event_coordinator users. |
| T1 — "Schools cannot cancel a training registration at all — every drop-out is a phone call." | **Still open.** No school-side training cancel/withdraw route exists in `routes/web.php` — only Sahodaya-side (`TrainingProgramController::cancelRegistration`) and the teacher-portal's own payment/feedback routes were found. |

**Also verified FIXED** (in addition to the Phase 1 items above):

| Prior claim | Verified current state |
|---|---|
| F2 — "Registration reject takes no reason." | **Fixed.** `FestRegistrationReviewController::reject()` (line ~377) now validates `rejection_reason` as `required|string|max:500`, persists it with `rejected_at`/`rejected_by_user_id`, and notifies via `FestEventNotifier::registrationRejected()`. |
| M4 — "School-initiated MCQ cancel doesn't notify the Sahodaya (audit log only)." | **Fixed.** `SchoolAdmin\McqRegistrationController::cancel()` calls `McqExamNotifier::registrationCancelledBySchool()` (wrapped in a non-blocking try/catch) in addition to the fee-credit sync and audit log. |
| P4 — "Membership rejection reason is hardcoded `null` in the payment-history row." | **Fixed.** `SchoolPaymentHistoryService::mapMembershipRow()` now reads the real stored reason. |

**Partially implemented** — the receipt-level payment-history rebuild (Phase 3/3b of the fix plan). `resources/js/Pages/Admin/School/Payments/Index.vue` already renders a `reversal_reason` line and a distinct `credit`-status row type (with a code comment explicitly citing `docs/FLOW_GAP_FIX_PLAN.md Phase 3b.2`) — so P2 ("cancelled things look approved with no marker") and P3 ("reversed receipts invisible to school") look at least partly addressed. However, a `grep` for the specific `receipt_rows` mode / `receiptHistoryPayload()` pattern the plan describes for P1 (one row per individual `FeeReceipt`, so a rejected-then-reuploaded receipt stays visible in history) found no match in `SchoolPaymentHistoryService.php` — so P1 specifically looks unconfirmed/likely still open. Not exhaustively verified either way; flagged for a closer follow-up look rather than a firm verdict.

---

## Entry-page & authentication routing

Two Spatie roles exist in the system (assignable, with a fully-built destination for both) but have **no case in `AuthController::homeFor()`** (`app/Http/Controllers/Admin/AuthController.php:410-512`, confirmed by reading the complete method) — meaning a user with only that role authenticates successfully and is then immediately logged back out with "Your account has no portal assigned" (the `homeFor()` fallthrough at line 511 returns `null`, which triggers a forced logout at lines 268-280 right after password verification succeeds):

- **`state_judge`** — has a fully-built dashboard at `/portal/state-judge` (`routes/web.php:1607-1614`, `EnsureStateJudgePortal` middleware, `Portal\StateJudgeDashboardController`, real Vue pages at `Pages/Admin/Portal/StateJudge/{Dashboard,MarkEntry}.vue`) that a state_judge-only user can never reach through the one shared `/login` form — everything downstream of login works, only the redirect is missing. Corroborated two ways: (a) no `homeFor()` branch for this role exists; (b) the Super Admin's own "New State User" form (`/admin/state-users`) only offers "State admin" / "State staff" as creatable roles — there's no in-app way to even provision a `state_judge` account, only via direct database access (which is how this audit created a test one).
- **`region_admin`** — same gap. Also has a real destination: `EnsureSahodayaAdmin` middleware includes it in the roles allowed into `/sahodaya-admin/{tenantId}/*` (`TenantUserCatalog::sahodayaAdminPanelRoles()`), and `FestEventStaffController.php:209-210` actively assigns this role to real users — so this isn't a theoretical/unused role, it's assigned in practice and then can't log in.

---

## Public / Tenant site / Central Admin / State — static findings

*(Full agent report — completed on its second attempt after the first was interrupted.)*

### New confirmed bug: Super Admin → "External Sahodayas → Schools" page is blank
- **Where:** `/admin/state-programs/external-sahodayas/{externalSahodaya}/schools` — reached by clicking through from a State Program's "External Sahodayas" tab (`StatePrograms/ExternalSahodayas.vue:40`), so a real admin following the normal path will hit this.
- **Root cause:** Same family of bug as the State Workspace blank page above, different specific mistake. `ExternalSchoolController.php:34` calls `inertia('Admin/ExternalSchools/Index', ...)`. Because this controller is itself inside the `Admin` namespace, and admin.js's loader already implicitly roots at `Pages/Admin/`, the literal string `'Admin/ExternalSchools/Index'` resolves to `Pages/Admin/Admin/ExternalSchools/Index.vue` — a doubled "Admin/Admin/" path that doesn't exist. The real file is at `Pages/Admin/ExternalSchools/Index.vue` (no "Admin/" in the render-call string needed).
- **Fix direction:** change the render call to `inertia('ExternalSchools/Index', ...)`.

### Confirmed dead sidebar code: Super Admin never shows "Site Builder & Themes"
- `resources/js/Layouts/AdminLayout.vue:143-220` — the nav-building function has an unconditional `return [...]` at line 203-204, followed by more code (lines 205-219) that pushes a "Site Builder & Themes" section referencing a `groups` variable that's never declared in the file. That code can never run.
- **Effect:** `/admin/builder/theme`, `/admin/builder/nav`, `/admin/builder/footer`, `/admin/builder/widgets` are fully built, working pages with **no way to reach them from the sidebar at all** — confirmed orphaned (no other page links to them either).

### Confirmed broken link: "Live Scoreboards" default nav points at a URL that doesn't exist
- `app/Support/NavConfigDefaults.php:62` defines a "Live Scoreboards" link as `/scoreboard` — no such route exists anywhere (the real one is event-scoped: `/fest/{event}/scoreboard`). In practice this specific link rarely fires (see next finding), but if it ever does, it 404s.

### Other orphaned pages
- **`/academic-results`** (`tenant.academic-results.index`) — a full form+results page with zero incoming links from any nav/footer default.
- **`/mcq/papers`** (public MCQ paper archive) — effectively unreachable in practice (only wired into a nav-defaults path that's dead code in real tenant provisioning — see next finding).
- `routes/state.php`'s entire dedicated-domain route group is *intentionally* inert pending DNS cutover (documented in its own file header) — not a bug, noted for completeness only.

### Public nav: three different, inconsistent "default navigation" implementations exist
This is more interesting than a simple missing-link bug. There are three separate places that decide what a tenant's public nav/footer contains:
1. `NavConfigDefaults::forSahodaya()` — the most complete one (has Fest, MCQ archive, etc.) but only fires as a fallback when a tenant's `nav_config` is literally empty, which the actual provisioning path never produces. Its own Scoreboard link is also broken (previous finding).
2. `SahodayaSiteTemplate` — the template actually used at tenant creation. Has **zero** links to fest schedule/scoreboard/results or the MCQ archive; only anchor links plus School Register + Login.
3. `CkscSiteTemplate` — an opt-in alternate template. Adds one link ("Kalotsav" → the fest hub `/fest`), from which schedule/scoreboard/results/live are then well cross-linked — but still never links the MCQ archive.
- **Net effect:** confirms the prior draft's claim that public visitors can't discover fest results/schedules or the MCQ archive through normal navigation — just not because of a single missing link; it's because the richest nav config is dead code and neither real template fills the gap.

### homeFor() role-gap — now with full evidence
Both `state_judge` and `region_admin` are fully wired to real, working destinations everywhere *except* the login redirect:
- **`state_judge`**: `EnsureStateJudgePortal` middleware (`app/Http/Middleware/EnsureStateJudgePortal.php:19`) correctly allows this role into `/portal/state-judge` (`routes/web.php:1607-1614`, controller `StateJudgeDashboardController`), and its Vue pages exist and are built (`Dashboard.vue`, `MarkEntry.vue`). `AuthController::homeFor()` simply never routes there.
- **`region_admin`**: included in `TenantUserCatalog::sahodayaAdminPanelRoles()` (`app/Support/TenantUserCatalog.php:28,297-310`) and actively assignable via `FestEventStaffController.php:209-210` — but again, no `homeFor()` case.
- Both cases: a user with only that role authenticates successfully, then gets force-logged-out with "no portal assigned" (`AuthController.php:268-280`).

### Prior-draft claim verdicts (Section 1 & 2)

| # | Claim | Verdict | Evidence |
|---|---|---|---|
| 1.4 | School application emails full login credentials before Sahodaya approval | **CONFIRMED** | `SchoolApplicationController.php:73-98,111` — tenant created `membership_status='pending'`, plaintext password generated and emailed in the same request, before any approval step. |
| 2.1 | Public nav has zero links to fest schedule/results/scoreboard/MCQ archive | **CONFIRMED**, more nuanced than described — see "three implementations" finding above. Root cause is dead-code nav defaults + two incomplete real templates, not one missing link. |
| 2.2 | Public scoreboard leaks live marks before results are published (unlike `/results`, which correctly 404s) | **REFUTED.** `FestPortalController::scoreboard()` (lines 375-443) does gate on `results_published`: data forced empty, winners query neutered, and the view shows a "not published yet" placeholder when unpublished. No leak found — this part of the original draft appears to have been simply incorrect, or described a since-fixed state. |
| 2.3 | No public circulars page; no public MCQ results/leaderboard route | **CONFIRMED**, both halves — no `tenant.circulars.*` route exists at all (circulars are admin-only); all MCQ results/leaderboard routes live inside the authenticated Sahodaya Admin panel only. |

---

## Portal roles (Teacher / Student / Judge / Group / House / Exam / Fest-Coordinator / Fest-Ops / State-Judge)

Fully covered by a completed background pass — every one of the 8 portal nav files was read in full and cross-referenced against `routes/web.php` and the actual Vue page tree.

**Broken nav links across all 8 portals: zero.** Every href produced by every nav helper resolves to a real, registered route with a matching controller method.

### Confirmed bug: Group Admin's "Results" page is broken, not just hidden
- **Route:** `portal.group.fest.results` (`routes/web.php:1680`, `GroupAdminController::festResults()`).
- **What happens:** Not linked from `groupPortalNav.js` anywhere — **and** if visited directly, it fails: it renders `inertia('Portal/Group/Results', ...)`, but `resources/js/Pages/Admin/Portal/Group/Results.vue` doesn't exist on disk. This was the only miss out of 49 `inertia('Portal/...')` calls checked across every Portal controller.
- **Contrast:** the equivalent House Admin role has a working "Ranking" results page; Group Admin has registrations/schedule/admit-cards but no working results or certificates view at all.

### Confirmed gating mismatch: `exam_staff` sees a "Mark entry" link that 403s
- `examPortalNav.js:4-6,21-23` shows the link unconditionally to `exam_staff` (the role-based hide condition it's built to support is never actually populated by any real caller — checked `Attendance.vue:108`, `Supervision.vue:66`, `MarkEntry.vue:76`, all omit passing `role`).
- `ExamOpsController.php:122,144` then explicitly blocks `exam_staff` server-side. The link is real (not a routing bug) — it's a UI-vs-server authorization mismatch: the nav shows something the backend refuses.
- Related dead code: `ExamOpsController` checks for a `mark_entry_admin` role at 4 call sites, but that role is absent from `EnsureExamPortal::ALLOWED` — a pure `mark_entry_admin` user can never pass the portal's own middleware to reach that code at all.

### Confirmed: no results/rank-list page exists anywhere in the Exam portal
Exam controllers/staff can enter marks, track attendance, and manage supervision — but there is no page in `/portal/exam/*` to view results or a rank list after marks are entered. Confirmed via full route enumeration, not just a nav-file read.

### Orphaned (but not broken) route
- `portal.student.fest.id-card` (`routes/web.php:1695`) works fine if visited directly, it's just never linked from any Student portal page (`Dashboard.vue`, `FestSchedule.vue`, `FestRegistrations.vue` all checked).

### Minor findings
- `resources/js/Pages/Portal/Group/` exists on disk but is empty and sits outside `admin.js`'s resolve root — harmless dead weight, not a live bug, but the same "file placed where the loader can't see it" mistake pattern as the two blank-page bugs above.
- `FestOps/Kitchen.vue` hardcodes a smaller duty-nav subset instead of the shared pattern its 8 sibling pages use — cosmetic sidebar gap only, no access-control impact (server-side duty checks are independent of the nav).
- No TODO/FIXME/`v-if="false"`/commented-out-link stubs found anywhere in Portal Vue pages — a clean skim otherwise.

---

## Prior-draft claims — Sahodaya Admin section (3.1–3.5)

*(Verified directly — the dedicated background agent for this portal failed twice due to an account-level API spend limit; see note at the end of this document. This is a lighter, targeted pass covering the 5 specific claims rather than an exhaustive route-by-route sweep.)*

| # | Claim | Verdict | Evidence |
|---|---|---|---|
| 3.1 | "Custom events" nav item hardcoded `hidden: true`; feature is thin | **REFUTED as described** — `sahodayaAdminNav.js:436` shows Custom events gated by `programOn('custom')` (a per-tenant visibility toggle), not a hardcoded `hidden: true`. **However, a real version of this bug exists for different items**: `sahodayaAdminNav.js:463-465` hardcodes `hidden: true` on "Talent Search question banks", "Talent Search series", and "All Talent Search exams" — three fully-built, routed pages permanently invisible in the sidebar regardless of tenant config. The underlying pattern (hidden-but-built nav items) is real; the specific item named in the prior draft was wrong. |
| 3.2 | MCQ exams have no certificate routes/controllers/models | **REFUTED.** `McqCertificate` and `McqCertificateTemplate` models exist; `McqExamOpsController` has `previewCertificate`/`generateCertificates`/`printCertificate` (`routes/web.php:1417-1419`), plus a student-portal certificate view (`routes/web.php:1713`). Fully built. |
| 3.3 | Only Kalotsav/Sports have state-tier rollup views | **Not independently re-verified this pass** (deprioritized under time/budget constraints — flagged for follow-up, not confirmed or refuted). |
| 3.4 | `sportsEventNav.js` omits Judges/Marks-import vs generic nav; has "Item heads" generic nav lacks | **Partially confirmed, with important context.** `sportsEventNav.js` (full file read) genuinely has no "Judges" item, while `sahodayaEventNav.js` has "Judges & staff" (line 156) — that part is real. But neither file has a "Marks import"-labeled item (sports has plain "Marks"), and "Item heads" as a concept doesn't appear in either — `sportsEventNav.js`'s own code comments explain why: the app went through a documented "Head = Event unification" (referencing a `SPORTS_NAV_CLEANUP_PLAN.md` doc not yet read) that intentionally retired the old head/event split, leaving `isSportsSeasonEvent()` hardcoded to `return false` and season-hub nav code deliberately unreachable-but-not-deleted "in case it's re-enabled later." This reads as an intentional, documented post-refactor state, not an overlooked inconsistency — though the missing "Judges" item specifically may still be worth a product decision. |
| 3.5 | `TenantUserCatalog` requires `fest.manage` instead of `fest.finance` for ledger-account updates, blocking finance staff | **REFUTED.** `TenantUserCatalog::writePermissionForPath()` (`app/Support/TenantUserCatalog.php:401-471`) matches any path containing `/ledger` (via substring match, which also catches `/ledger-account`) and correctly returns `'fest.finance'` — which `sahodaya_finance`'s role permissions already include. No block found. |

---

## Prior-draft claims — remaining sections (School Admin 4.x, Portal 5.x)

Section 5 (Portal-tier) is fully covered — see the Portal-roles findings folded in above (5.1 CONFIRMED and found to be worse than described — the route is actually broken, not just hidden; 5.2 nuanced-confirmed as a gating mismatch rather than a broken link; 5.3 CONFIRMED).

### School Admin (4.1–4.4) — verified directly (targeted pass, same constraint as above)

| # | Claim | Verdict | Evidence |
|---|---|---|---|
| 4.1 | Custom events & Teacher training lack fest-day desk/results/certs/attendance parity with built-out programs | **Not independently re-verified this pass** — deprioritized under time/budget constraints. |
| 4.2 | "Download All Certificates" and "Event Appeals" routes exist and work, but have no sidebar/page links | **Half-confirmed, half-refuted.** Appeals is **NOT** orphaned — `FestEventPortalController::appeals` (`/fest/{event}/appeals`) is linked three times from `Events/FestHub.vue` (lines 108, 124, 142) and has its own dedicated `Appeals.vue` page. "Download All Certificates" (`FestEventPortalController::downloadCertificatesZip`, route name `fest.certificates.download-all`) genuinely has **zero** references anywhere in `schoolAdminNav.js`, `schoolEventNav.js`, or `FestHub.vue` — that half of the claim holds. |
| 4.3 | Payment history only shows the current `fee_receipt_id` pointer; a rejected-then-reuploaded receipt disappears from view entirely | **Nuanced — depends which page.** Fest's own **event-level billing panel** already does this correctly: `FestRegistrationController::receiptHistoryPayload()` (`app/Http/Controllers/SchoolAdmin/FestRegistrationController.php:1335-1360`) returns full history via the `receipts()` relation (every upload/rejection/supersession/approval), explicitly built to close exactly this gap per a code comment citing `docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §7`. But the **shared, cross-program "Payment History" page** (`/school-admin/{tenant}/payments`, backed by `SchoolPaymentHistoryService`) — which is what the prior draft's "Entry Page" pointed at — was separately confirmed earlier in this document to *not yet* have that generalized receipt-level rebuild (Phase 3 of the fix plan). So: fixed for fest's own panel, likely still open on the unified cross-program history page. |
| 4.4 | No "edit & resubmit" action for a rejected registration | **CONFIRMED.** `Events/Registration.vue:1598-1604` — `canEdit(reg)` explicitly returns `false` whenever `reg.status` is `'rejected'` (or `'withdrawn'`) before any other check. A rejected registration cannot be edited through this UI; the school's only options are to leave it or start an unrelated new registration. |

---

## Summary matrix

| Area | Issue | Status |
|---|---|---|
| Super Admin | State Workspace nav group (Qualifiers/Fest/BoardResults) — blank page | ❌ Confirmed live, broken |
| Super Admin | Sports Results — 500 crash | ❌ Confirmed live, broken |
| Super Admin | External Sahodayas → Schools — blank page | ❌ Confirmed (static, same mechanism as above) |
| Super Admin | Site Builder & Themes sidebar group — dead code, never renders | ❌ Confirmed |
| Super Admin / Public | "Live Scoreboards" nav link → `/scoreboard` (doesn't exist) | ❌ Confirmed |
| Public site | Fest schedule/results/scoreboard/MCQ archive undiscoverable via nav | ⚠️ Confirmed, more nuanced than described (dead-code nav defaults, not one link) |
| Public site | No public circulars page or MCQ leaderboard | ❌ Confirmed |
| Public site | Scoreboard leaks live marks pre-publish | ✅ Refuted — properly gated |
| Auth | `state_judge` role — fully built portal, unreachable via login | ❌ Confirmed |
| Auth | `region_admin` role — actively assigned, unreachable via login | ❌ Confirmed |
| Auth | School application emails credentials pre-approval | ❌ Confirmed |
| Auth | 5 school coordinator roles get "no portal assigned" | ✅ Refuted — all route correctly |
| Sahodaya Admin | 3 Talent Search nav items hardcoded hidden (not "Custom events" as originally claimed) | ❌ Confirmed (different item than described) |
| Sahodaya Admin | MCQ has no certificates | ✅ Refuted — fully built |
| Sahodaya Admin | Ledger permission blocks finance staff | ✅ Refuted |
| Sahodaya Admin | Sports nav missing "Judges" vs generic nav | ⚠️ Confirmed, but likely intentional post-refactor state |
| Sahodaya Admin | State-tier rollups only for Kalotsav/Sports | ❓ Not re-verified |
| School Admin | "Download All Certificates" unlinked | ❌ Confirmed |
| School Admin | "Event Appeals" unlinked | ✅ Refuted — linked 3x |
| School Admin | Rejected registration has no edit/resubmit path | ❌ Confirmed |
| School Admin | Payment history: rejected receipt vanishes on re-upload | ⚠️ Fixed on fest's own panel; likely still open on the shared cross-program history page |
| School Admin | Custom events / training feature parity gap | ❓ Not re-verified |
| Portal | Group Admin "Results" route broken (missing Vue page) | ❌ Confirmed — worse than originally described (broken, not just hidden) |
| Portal | `exam_staff` sees Mark Entry link that 403s | ⚠️ Confirmed — real gating mismatch |
| Portal | No results/rank-list page in Exam portal | ❌ Confirmed |
| Financial/cancellation (event & exam & training cancel cascades, MCQ Sahodaya-cancel, fee-credit stranding, membership cancel-with-credit) | Prior draft's whole "Operational & Financial" section | ✅ Refuted — already fixed per `docs/FLOW_GAP_FIX_PLAN.md` |
| Financial (fee-rejection notify, fest-withdraw notify, school-side training cancel) | 3 specific sub-items from that same plan | ❌ Confirmed still open |

---

## Test accounts (for reference / re-verification)

All demo accounts use password `Demo@Pass2026` unless noted. Dev environment also has a working developer pass-token (`sahodaya-dev-pass-2026` from `.env DEV_LOGIN_PASS_TOKEN`) that authenticates as any user by email/username through the *same* `login()`/`homeFor()` code path as a real password — useful for re-testing without juggling passwords.

| Role | Email | Tenant |
|---|---|---|
| superadmin | admin@sahodaya.test | central |
| state_admin | state_admin@e2e.test | central |
| state_judge | demo.state_judge@central.test | central |
| sahodaya_admin, sahodaya_staff, registration_coordinator, event_coordinator, sahodaya_finance, certificate_collector, data_entry, mark_entry_admin, event_admin, region_admin, training_admin, exam_controller, exam_staff, mark_entry_coordinator, judge, fest_ops | demo.\<role\>@malappuram.test | Malappuram Sahodaya |
| school_admin, school_principal, school_vice_principal, school_event_coordinator, school_finance_coordinator, school_training_coordinator, school_mcq_coordinator, school_kalotsavam_coordinator, school_sports_coordinator, school_staff, teacher, student, group_admin, house_admin | demo.\<role\>@amu.test | AMU Residential School |

Pre-existing accounts also usable: `sahodaya@malappuram.test` (event_admin role), and per-school admin logins visible in Super Admin → Schools (e.g. `admin@amu-school.test` / `password`).
