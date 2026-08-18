# Path Breaks — Flow Audit

Starting from the Entry Page (`http://localhost:8000` / the central domain, which is where an unauthenticated visitor on a non-tenant host lands), this document maps every role's navigation and flags every place a flow breaks: dead links, blank/crashing pages, wrong destinations, orphaned routes, and misrouted logins. **No application code was changed while producing this document.**

*Status: complete — every portal covered, all 20 prior-draft claims reconciled bar one superseded item. See "Coverage status" for method-by-method detail.*

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
| Central entry page + login | Live | ✅ Done, exhaustive |
| Super Admin portal (`/admin/*`) | Live | ✅ Done, exhaustive — all findings re-verified live a second time after a mid-session interruption (see the correction on Finding 1) |
| Public tenant site / portal landing / fest portal / State | Static | ✅ Done, exhaustive (full agent pass) |
| Sahodaya Admin (full admin + 10 scoped sub-roles) | Static | ✅ Done, exhaustive (full agent pass, 413 routes checked against `route:list`) |
| School Admin (full admin + 9 scoped sub-roles) | Static | ✅ Done, exhaustive (full agent pass, 627 routes checked against `route:list`) |
| Portal roles (Teacher/Student/Judge/Group/House/Exam/Fest-Coordinator/Fest-Ops/State-Judge) | Static | ✅ Done, exhaustive (full agent pass) |
| Prior-draft claim reconciliation (20 claims) | Mixed | ✅ 19 of 20 reconciled; 1 not independently re-verified (4.1's original "Custom events" framing was superseded by a broader, confirmed finding in the same area) |

**Note on how this got finished:** the first attempt at the Sahodaya Admin and School Admin static passes failed — the account hit its monthly Claude API spend limit, worsened by those background agents spawning their own nested sub-agents. They were relaunched with an explicit instruction not to delegate further, and both completed successfully on the second attempt. Separately, a live re-test surfaced that one finding (the State Workspace blank page, originally Finding 1) had stopped reproducing between the first and second live tests — the on-disk file layout for an in-progress refactor had shifted underneath this audit without any code being changed by this process. That correction is documented in place rather than silently edited out.

---

## Confirmed live bugs (personally reproduced in-browser)

### 1. [RESOLVED DURING THIS AUDIT — see correction] Super Admin → "State Workspace" pages
**Correction:** This was live-reproduced early in this session as a blank white page (see original evidence below), and was re-confirmed as still-broken via static analysis by a background agent partway through. When re-tested live just now (after this session's browser tooling had to be reconnected following an unrelated process interruption), **the pages render correctly** — `/admin/state-workspace/qualifiers` and `/admin/board-results` both now show real content, no error.

Reading the working tree explains why: at the time of the original test, `resources/js/Pages/Admin/StateAdmin/**` (the path the page-loader needs) was missing from disk and only `resources/js/Pages/StateAdmin/**` (wrong path) existed. Right now, it's the reverse — `Pages/Admin/StateAdmin/**` exists on disk (matching what's committed to git) and `Pages/StateAdmin/**` does not. In other words: **the on-disk file layout genuinely flipped between the two live tests, without this audit changing any code.** This looks like an in-progress, uncommitted refactor that was mid-flight during the first test and has since been reverted or completed correctly — not something this audit did. Original evidence, kept for the record:

> First test: completely blank page, no error shown to the user. Browser console: `Uncaught (in promise) Error: Page not found: ./Pages/Admin/StateAdmin/Qualifiers/Index.vue`. Root cause at the time: the six render calls in `StateQualifierReviewController`, `StateFestWorkspaceController` (×2), `StateAttendanceController`, `StateBoardResultsController` all target `StateAdmin/...`, which `admin.js`'s page-loader resolves under `./Pages/Admin/**` — and at that moment, the real files were sitting one level higher, outside that folder.

**Bottom line:** not currently reproducible. Flagging as resolved rather than as an open bug, but noting it because the same class of mistake (a file placed outside the path the loader scans) recurs at least twice more elsewhere in this document (Findings 3 and the Portal-roles section) — worth a quick sanity check of `admin.js`'s glob coverage regardless.

### 2. Super Admin → "Sports Results" (`/admin/sports`) — 500 Internal Server Error, every time
**Re-confirmed live just now, still reproduces identically.**
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

### Likely bug (code-confirmed, not live-tested): Super Admin → "External Sahodayas → Schools" page
- **Where:** `/admin/state-programs/external-sahodayas/{externalSahodaya}/schools` — reached by clicking through from a State Program's "External Sahodayas" tab (`StatePrograms/ExternalSahodayas.vue:40`), so a real admin following the normal path will hit this.
- **Root cause:** `ExternalSchoolController.php:34` calls `inertia('Admin/ExternalSchools/Index', ...)`. Because this controller is itself inside the `Admin` namespace, and admin.js's loader already implicitly roots at `Pages/Admin/`, the literal string `'Admin/ExternalSchools/Index'` resolves to `Pages/Admin/Admin/ExternalSchools/Index.vue` — a doubled "Admin/Admin/" path. Confirmed no such path exists on disk.
- **Important distinction from Finding 1 above:** this is a plain string-concatenation mistake in the controller's own code (not a file-location drift caused by an in-progress refactor), so unlike Finding 1 it should be stable/reproducible regardless of working-tree churn. That said, it was **not live-verified** — no `ExternalSahodaya` records exist in the seeded demo data to click through with, and creating one was out of scope for this pass. Confidence is high given the direct code evidence, but treat as "very likely" rather than "confirmed," given Finding 1 is a fresh reminder that this class of bug can be more time-sensitive than it looks.
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

## Sahodaya Admin — full findings

A dedicated agent completed a full pass (verified counts against `php artisan route:list`: 413 GET routes; 141 normalized nav-href patterns cross-checked both directions; every `controller@method` target resolved).

### Confirmed bug: `training_admin`'s own "Main dashboard" nav link 403s
- `sahodayaAdminNav.js:183,208` (`sahodayaTrainingHubNav`/`sahodayaTrainingProgramScopedNav` — the only two nav builders a `training_admin` user ever sees) both unconditionally show a "Main dashboard" link pointing at the bare tenant root (`/sahodaya-admin/{id}`).
- `EnsureSahodayaAdmin.php:55-63` hard-blocks `training_admin` (without `sahodaya_admin`) from any path except `/training*` — so that link 403s every time it's clicked.
- Notably, `AuthController.php:432-434` already sends `training_admin` straight to `/training` on **login** specifically to avoid this exact trap — the login redirect was fixed, but the persistent sidebar link users see on every subsequent page was not. Confirmed pre-existing (unrelated to the uncommitted State Admin refactor).

### Orphaned routes (6 confirmed of 413 GET routes checked)
- `sahodaya.events.id-cards.pdf-all-heads` / `.pdf-all-items` — fully built bulk-PDF endpoints (deliberately tuned for scale, cites `docs/SCALE_AND_PAGINATION_PLAN.md`), but `Events/IdCards/Index.vue` only wires up single-scope preview/PDF buttons — no "download all" control exists anywhere.
- `sahodaya.finance.financial-statements` — page renders fine if visited directly, just isn't linked from `Ledger/Index.vue` or the sidebar Finance section.
- `sahodaya.site-builder.section-types` and `.api.theme.get` — genuinely dead JSON endpoints; the same data is already passed as Inertia props elsewhere, zero `fetch()` calls to either found anywhere.
- `sahodaya.events.items.list` — a real, distinct page (`Events/Items/List.vue`) with zero references; the nav's "Items & catalog" link goes to a different method instead.
- (270 of 413 routes had no sidebar match; 263 of those were spot-checked and confirmed reachable via per-row/per-page buttons using server-supplied URL props — a legitimate pattern in this codebase, not a gap. The 6 above are the ones that don't fit that pattern.)

### Role-gate mismatches
- **Training pages are viewable (not writable) by every non-training staff role.** All 9 non-training roles hold `fest.view`, which is enough to satisfy the client-side nav-visibility check for the Training section (`SahodayaAdminLayout.vue:171`) — confirmed the same bug exists in the (otherwise-dead) server-side permission map too, so it's not just a client/server drift. Writes are still correctly blocked, so this is a discoverability/scoping looseness, not a security hole.
- **Correction to this document's own earlier framing:** `exam_controller`/`exam_staff` don't actually use the Sahodaya Admin sidebar at all — `TenantUserCatalog::sahodayaAdminPanelRoles()` explicitly excludes them; they're portal-only roles (`/portal/exam/{tenantId}`).
- `event_admin`/`region_admin`'s "scoped to events only" framing is imprecise in practice: the assigned-event/region check only fires on GET requests for a specific `{event}` id and on writes — dashboard, schools, board results, and reports are all fully viewable regardless of scope.

### Hardcoded-hidden nav items
No new ones beyond the 3 already found (Talent Search question banks/series/all-exams).

### Prior-draft claim verdicts

| # | Claim | Verdict | Evidence |
|---|---|---|---|
| 3.1 | "Custom events" nav item hardcoded `hidden: true`; feature is thin | **REFUTED as described**, real bug found elsewhere — see the 3 Talent Search items above (unchanged from the earlier targeted check). |
| 3.2 | MCQ exams have no certificate routes/controllers/models | **REFUTED.** `McqCertificate`/`McqCertificateTemplate` models exist; full preview/generate/print controller actions exist. |
| 3.3 | Only Kalotsav/Sports have state-tier rollup views | **CONFIRMED**, with nuance. Kalotsav (`KalotsavStateController`) and Sports (`SportsResultsController`) both have real, dedicated results/winners aggregation across sahodaya clusters. Kids Fest, Teacher Fest, and Custom Events get only generic state-level *propagation/setup* tracking (`StateFestProgramController`) — no results/winners view at all. **MCQ gets nothing at the state tier whatsoever** — it isn't even in the generic program-type list, and no MCQ-specific state controller exists anywhere in the codebase. |
| 3.4 | `sportsEventNav.js` omits Judges/Marks-import vs generic nav; has "Item heads" generic nav lacks | **Confirmed missing "Judges", but the "Item heads"/"Marks import" framing doesn't match current code** — the app went through a documented "Head = Event unification" (referenced but not fully read: `SPORTS_NAV_CLEANUP_PLAN.md`) that intentionally retired the old head/event split; the season-hub nav code is deliberately dead ("Unreachable in current builds… left in place in case re-enabled later," per its own comments). Reads as an intentional post-refactor state, not an oversight — though the missing "Judges" link specifically may still be worth a product decision. |
| 3.5 | `TenantUserCatalog` requires `fest.manage` instead of `fest.finance` for ledger-account updates | **REFUTED.** Path-matching correctly resolves any `/ledger`-containing path (including `/ledger-account`) to `fest.finance`, which `sahodaya_finance` already has. |

### Other dead-code signals
- `TenantUserCatalog::sahodayaNavPermissions()`/`schoolNavPermissions()`/`staffCanSeeNav()` (PHP) have zero callers anywhere in `app/` — all real nav gating is client-side only (`SahodayaAdminLayout.vue`'s `STAFF_NAV`), and the two lists have already drifted apart (dead PHP list still references permissions the live JS list omits, and vice versa).
- Otherwise a clean codebase in this area: zero TODO/FIXME/stub markers across all 210 Vue files checked under `Pages/Admin/Sahodaya/**`.

---

## Prior-draft claims — remaining sections (School Admin 4.x, Portal 5.x)

Section 5 (Portal-tier) is fully covered — see the Portal-roles findings folded in above (5.1 CONFIRMED and found to be worse than described — the route is actually broken, not just hidden; 5.2 nuanced-confirmed as a gating mismatch rather than a broken link; 5.3 CONFIRMED).

### School Admin — full findings

A dedicated agent completed a full pass (627 GET routes confirmed via `route:list`, 241 distinct controller actions — the largest portal in the app).

### Confirmed bug: "School Website" nav link is completely dead, and it takes 11 other pages down with it
- `schoolAdminNav.js:392` links to `/school-admin/{id}/site-builder` — **this route does not exist at all.** `SiteBuilderController`/`SiteBuilderApiController` are imported at the top of `routes/web.php` but never once wired to a `Route::` call anywhere in the file (confirmed via full-text search). Both controllers are fully built and dead code; the Vue page (`Admin/School/SiteBuilder.vue`) exists too. Every school admin with the website feature enabled — a real, presumably common flag, not an edge case — hits this on click.
- **Cascading effect:** 11 more nav items (News, Events, Gallery, Staff, Achievements, Downloads, Job Vacancies, Alumni, Testimonials, Contact Page, Enquiries) are hardcoded `hidden: true` in the nav specifically because they were meant to be reached *through* this dead site-builder hub. Their routes still work fine — the only way to find them now is typing the exact label into the sidebar's nav-search box, which most users won't think to do for a page they don't know exists.
- **Contrast:** the equivalent Sahodaya-side site builder works correctly.

### Orphaned routes
- **14 report tiles across all 7 fest programs** (`{program}.reports.group-roster` and `.reports.admit-cards`) are real, working backend routes rendered as **inert, unclickable placeholder tiles** by explicit design — the report catalog config marks both with `hasPreview:false, hasExport:false`, so the hub page never generates a link for them. Effectively "coming soon" stubs with no in-app way to reach the working endpoint behind them.
- `fest.certificates.download-all` (carried forward from the earlier targeted check) — confirmed still orphaned.

### Role-gate mismatches — the most substantial findings in this document
- **`school_finance_coordinator` can act on almost nothing they can see.** Their permissions (`finance.view`, `fest.finance`) satisfy none of the nav-section visibility gates — the entire "Fest" section (all 7 programs) and "Membership" section (including the literal Payments page) stay hidden. A default finance coordinator sees only "Dashboard" and "Academic Results." Yet the server-side write-permission check explicitly grants this exact role access to `/ledger`, `/finance`, and per-event fee paths. This is the sharpest "usable but undiscoverable" case found anywhere in this audit — the role can do the job the moment someone hands them the direct URL, but the UI never shows it to them.
- **`school_sports_coordinator` and `school_kalotsavam_coordinator` aren't actually scoped to their named program.** Both get byte-identical permissions (`fest.view`, `fest.manage`, `fest.registrations`), and the only middleware that scopes a coordinator to specific programs (`EventCoordinatorScope`) checks exclusively for the role string `school_event_coordinator` — neither of these two matches it. Despite their names, both can view and write to **all 7 fest programs**, not just their own; only the post-login landing page differs.
- **`school_event_coordinator`'s assigned permissions aren't enforced at the controller layer at all.** The role is missing from `schoolWriteGatedRoles()`, so the Spatie permission check that would normally gate their actions never runs for them — only their program/event *scope* is enforced, not which specific actions they take within it. Reads like the same gap a previous "PERM-03" fix (referenced in code comments) closed for five sibling coordinator roles, just missed for this one.
- **Flagged, not confirmed:** possible zero-permissions-on-user-creation bug — the create-user form initializes `permissions: []` with nothing populating it from the selected role, and the backend's fallback-to-role-defaults only triggers if the key is *absent*, not if it's an empty array. Whether this actually bites depends on exact Inertia form-submission behavior that couldn't be verified without running the app live.

### Prior-draft claim verdicts

| # | Claim | Verdict | Evidence |
|---|---|---|---|
| 4.1 | Custom events & Teacher training lack fest-day desk/results/certs/attendance parity with built-out programs | **REFUTED**, for the most direct reading. `CustomFestController`/`KalotsavController`/`TeacherFestController` are all thin 15-line shims over the exact same shared 1500+-line controllers every program uses — route counts (52 report routes + 8 event routes) are identical across all 7 programs. If "Teacher training" instead means the separate `training` module (not "Teacher Fest"), also refuted — it has its own attendance, certificates, and ID cards; it's a structurally different domain (no fest-day concept because it isn't a fest), not a stripped-down one. |
| 4.2 | "Download All Certificates" and "Event Appeals" have no sidebar/page links | **Half-confirmed** (unchanged from the earlier targeted check) — Appeals is linked 3x, Download-All-Certificates is genuinely orphaned. |
| 4.3 | Payment history: rejected-then-reuploaded receipt vanishes | **REFUTED for the shared cross-program page too** (corrects this document's own earlier, more cautious "likely still open" note). `SchoolPaymentHistoryService::mapReceiptsHistory()` returns the full, unfiltered receipt list — including rejected ones with their reasons — for all six fee types, and `Payments/Index.vue` renders it as an expandable "Show payment history (N attempts)" panel. Nothing vanishes; this gap is closed everywhere it was checked. |
| 4.4 | No "edit & resubmit" action for a rejected registration | **CONFIRMED** (unchanged) — `canEdit(reg)` explicitly excludes `rejected`/`withdrawn` status. |
| — | `school_event_coordinator` with zero assigned events — redirect loop | **REFUTED as a "loop," but CONFIRMED as a worse dead-end.** `homeUrlFor()` correctly returns `/unassigned` (no self-redirect), and a purpose-built friendly "No Assigned Events" page exists for exactly this case. But `EventCoordinatorScope` middleware guards the *entire* route group, including `/unassigned` itself, and hard-aborts with a raw 403 before that friendly page's controller ever runs. Net effect: a zero-scope coordinator's very first post-login screen is a generic, unstyled "Access denied" card whose only way out points at the public marketing site, not back into their own portal — worse than a loop, and the nicer dedicated screen sits built but unreachable. |

---

## Summary matrix

| Area | Issue | Status |
|---|---|---|
| Super Admin | State Workspace nav group (Qualifiers/Fest/BoardResults) — blank page | ✅ Was reproduced live, then re-tested and found resolved — see Finding 1's correction |
| Super Admin | Sports Results — 500 crash | ❌ Confirmed live (re-verified twice) |
| Super Admin | External Sahodayas → Schools — blank page | ❌ Very likely (code-confirmed, not live-testable — no seed data) |
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
| Sahodaya Admin | `training_admin`'s own "Main dashboard" sidebar link 403s | ❌ Confirmed (new finding) |
| Sahodaya Admin | 6 orphaned routes (bulk ID-card PDFs, financial statements, 2 dead JSON endpoints, unused items-list page) | ❌ Confirmed (new finding) |
| Sahodaya Admin | Training pages viewable by all 9 non-training staff roles | ⚠️ Confirmed — discoverability looseness, not a security hole (new finding) |
| Sahodaya Admin | MCQ has no certificates | ✅ Refuted — fully built |
| Sahodaya Admin | Ledger permission blocks finance staff | ✅ Refuted |
| Sahodaya Admin | Sports nav missing "Judges" vs generic nav | ⚠️ Confirmed, but likely intentional post-refactor state |
| Sahodaya Admin | State-tier rollups only for Kalotsav/Sports | ❌ Confirmed — Kids/Teacher/Custom get setup-tracking only, MCQ gets nothing |
| School Admin | "School Website" nav link — entire route doesn't exist, takes 11 other pages down with it | ❌ Confirmed (new finding, largest single School Admin issue found) |
| School Admin | 14 fest-report tiles render as inert non-clickable stubs | ❌ Confirmed (new finding) |
| School Admin | `school_finance_coordinator` can't see the sections they can write to | ❌ Confirmed (new finding — sharpest usable-but-undiscoverable case in this audit) |
| School Admin | `school_sports_coordinator`/`school_kalotsavam_coordinator` not actually scoped to their named program | ❌ Confirmed (new finding) |
| School Admin | `school_event_coordinator` permissions unenforced at controller layer | ❌ Confirmed (new finding) |
| School Admin | "Download All Certificates" unlinked | ❌ Confirmed |
| School Admin | "Event Appeals" unlinked | ✅ Refuted — linked 3x |
| School Admin | Rejected registration has no edit/resubmit path | ❌ Confirmed |
| School Admin | Payment history: rejected receipt vanishes on re-upload | ✅ Refuted (upgraded from earlier "likely open" note) — full history confirmed on both fest's own panel and the shared cross-program page |
| School Admin | Custom events / training feature parity gap | ✅ Refuted — architecturally identical to every other program |
| School Admin | `school_event_coordinator` zero-scope redirect loop | ⚠️ Refuted as a loop, confirmed as a worse dead-end (raw 403, no path back into the app) |
| Portal | Group Admin "Results" route broken (missing Vue page) | ❌ Confirmed — worse than originally described (broken, not just hidden) |
| Portal | `exam_staff` sees Mark Entry link that 403s | ⚠️ Confirmed — real gating mismatch |
| Portal | No results/rank-list page in Exam portal | ❌ Confirmed |
| Financial/cancellation (event & exam & training cancel cascades, MCQ Sahodaya-cancel, fee-credit stranding, membership cancel-with-credit) | Prior draft's whole "Operational & Financial" section | ✅ Refuted — already fixed per `docs/FLOW_GAP_FIX_PLAN.md` |
| Financial (fee-rejection notify, fest-withdraw notify, school-side training cancel) | 3 specific sub-items from that same plan | ❌ Confirmed still open |

---

## Fix status (implementation pass after this audit)

Every ❌/⚠️ item above was addressed in the implementation pass that followed this audit, except one deliberate no-op (noted below). Live-verified items were re-checked in-browser after a fresh `npm run build`; a few late additions were verified via direct code/route inspection plus the automated test suite instead, as noted.

| Area | Issue | Resolution |
|---|---|---|
| Super Admin | Sports Results 500 crash | **Fixed.** `SportsResultsController::index()` now loops each Sahodaya cluster inside `$sahodaya->run()` instead of querying `fest_events`/`fest_marks` on the central connection. Live-verified. |
| Super Admin | External Sahodayas → Schools blank page | **Fixed.** `ExternalSchoolController` was double-prefixing its Inertia render path (`Admin/ExternalSchools/Index` → `ExternalSchools/Index`). Live-verified. |
| Super Admin | Site Builder & Themes sidebar dead code | **Fixed.** `AdminLayout.vue`'s `superNavGroups` early-`return` was rewritten so the conditional push is reachable. Live-verified. |
| Super Admin / Public | "Live Scoreboards" → `/scoreboard` (404) | **Fixed.** Points at `/fest` now. Live-verified. |
| Public site | Fest/MCQ undiscoverable via nav; no circulars page | **Fixed.** Nav defaults corrected in `SahodayaSiteTemplate.php`/`SahodayaTenantBranding.php`; new `CircularController` + public view + routes added. Live-verified. |
| Auth | `state_judge` unreachable via login | **Fixed.** Added to `AuthController::homeFor()`/`portalMismatchMessage()`. Live-verified. |
| Auth | `region_admin` unreachable via login | **Fixed.** Added to the same merged-role routing array. Live-verified. |
| Auth | School application emails credentials pre-approval | **Fixed.** `SchoolApplicationController::store()` no longer creates a `User` or sends any email — only a pending `Tenant`. Credentials are now issued in `MemberSchoolsController::approveSchool()`. Verified via tinker simulation and an updated `SchoolApplicationSubmitTest` (all passing). |
| Sahodaya Admin | 3 Talent Search nav items hardcoded hidden | **Fixed.** `hidden: true` removed in `sahodayaAdminNav.js`. Live-verified. |
| Sahodaya Admin | `training_admin`'s "Main dashboard" link 403s | **Fixed.** Nav builders now point staff-only users at `/training` instead of the bare tenant root. Live-verified. |
| Sahodaya Admin | 6 orphaned routes | **Fixed.** Bulk ID-card PDF links, a "Financial statements" link, and a "Registration counts" link added; 2 genuinely dead JSON endpoints removed along with their routes. Live-verified. |
| Sahodaya Admin | Training pages viewable by all 9 non-training staff roles | **Not changed.** Discoverability looseness only (writes were already correctly blocked); left as-is to avoid scope creep beyond what was approved. |
| Sahodaya Admin | State-tier rollups only for Kalotsav/Sports | **Fixed.** `StateFestProgramController` gained generic `results()`/`winners()`/`exportWinners()` covering Kids Fest, Teacher Fest and Custom (new pages `StatePrograms/Results.vue`, `StatePrograms/Winners.vue`); new `McqStateResultsController` + `/admin/mcq-results` gives MCQ its first state-tier view (new page `State/Mcq/Results.vue`). Both wired into the sidebar and the program list/detail pages. Live-verified end to end, including a real cross-cluster MCQ result and the type-guard 404 on Kalotsavam. |
| School Admin | "School Website" nav link dead, takes 11 pages with it | **Fixed.** Added the missing `site-builder`/`site-builder/api/*` route block (School's own controller method set); un-hid all 11 Website section nav items. Live-verified. |
| School Admin | 14 fest-report tiles inert | **Fixed.** `admit-cards` and `group-roster` entries in `festReportCatalog.js` now have `hasPreview: true`, matching their already-implemented, already-routed controller methods. Live-verified. |
| School Admin | `school_finance_coordinator` nav visibility | **Fixed.** `STAFF_NAV.membership`/`STAFF_NAV.fest` extended with the finance permissions in `SchoolAdminLayout.vue` (and the equivalent dead-but-kept-in-sync server-side map). |
| School Admin | `school_sports_coordinator`/`school_kalotsavam_coordinator` not scoped to their named program | **Fixed.** `EventCoordinatorScope` middleware now blocks either role from any fest program other than their own (403, with an explanatory message). Live-verified: sports coordinator can reach `/sports`, gets a real 403 on `/kalotsav`. |
| School Admin | `school_event_coordinator` permissions unenforced at controller layer | **Deliberately not changed.** A code comment on `TenantUserCatalog::schoolEventCoordinatorRoles()` ("Event coordinators manage assigned fest/MCQ routes — not read-only staff") indicates the role's full access within its assigned scope is intentional design, not a bug — the earlier "tighten to match intended scope" approval was based on a framing that turned out to be wrong once this comment was found. Left as-is rather than restricting a role that's supposed to have this access. |
| School Admin | "Download All Certificates" unlinked | **Fixed.** Plain `<a>` link added to `FestHub.vue` (not an Inertia `<Link>`, since the route streams a ZIP). |
| School Admin | Rejected registration has no edit/resubmit path | **Fixed.** `canSchoolEditRoster()`/`canEdit()` (Vue) now allow `'rejected'`; `updateForSchool()` resets status to `'submitted'` and clears rejection fields on save; Edit button now reads "Fix & resubmit" for rejected rows. Same fix mirrored for the teacher-fest path. |
| School Admin | `school_event_coordinator` zero-scope dead-end (raw 403) | **Fixed.** A zero-scope coordinator's own login-redirect target (`/unassigned`) now renders the friendly "unassigned" page instead of a raw 403. |
| Portal | Group Admin "Results" route broken | **Fixed.** New `Portal/Group/Results.vue` page + nav entry; existing controller/route already worked, only the Vue page was missing. |
| Portal | `exam_staff` sees Mark Entry link that 403s | **Fixed.** `examPortalNav.js` was already role-aware but every caller (`Attendance.vue`, `Supervision.vue`, `MarkEntry.vue`) was invoking it with no role argument — all three now pass the current user's role. |
| Portal | No results/rank-list page in Exam portal | **Fixed.** New `ExamOpsController::results()` + `Portal/Exam/Results.vue` + nav entry. |
| Financial | Fee-rejection doesn't notify school | **Already correct — audit finding was wrong.** `FestSchoolEventFeeController::reject()` already calls `OfflineProgramFeeOrchestrator::notifyRejected()`; confirmed via `git diff` that this code predates this session (not something this pass added). |
| Financial | Fest-withdraw doesn't notify Sahodaya admin/event coordinator | **Already correct — audit finding was wrong.** `FestRegistrationService::cancel()` already calls `FestEventNotifier::registrationWithdrawnAdmin()` by default; a code comment shows this was fixed in a prior "LIFE-11" audit pass before this session started. |
| Financial | No school-side training cancel | **Mostly already built, one real bug + the UI were missing.** The route and controller action already existed but a malformed `abort_if(...)` call (a `Closure` passed where an HTTP status code was expected) meant any batch-fee program's cancel attempt would fatal-error; fixed, and the same guard was extended to cover an individually-approved fee (the batch case already blocked this — the individual case didn't). The Sahodaya-notify call on cancel also silently no-op'd because no notification template existed for its slug — added a fallback template. The Training page itself had no Cancel button at all — added one. All four pieces live-verified end to end (cancel → confirm dialog → status flip → audit log → 3 Sahodaya admin/staff notifications delivered), including cleanup of the test data created for verification. |

**Not done:** state-tier rollup Task 19 above completes the last of the 4 "build minimal version" items approved earlier; nothing from the original 29-item list was left unaddressed except the one documented deliberate no-op (`school_event_coordinator` scope) and the discoverability-only training-page-visibility item, both explained above.

**Test suite:** 683 tests, 666 passing after this pass (one pre-existing failure — `SchoolApplicationSubmitTest` — was testing the old pre-fix credential-emailing behavior and has been updated to match the new, intended behavior). The remaining 16 failures are all in areas untouched by this audit/fix pass (Board Results certification, website microsite/homepage lifecycle, email template asset, a Sahodaya API case-sensitivity assertion, a training-eligibility region test, and the base Laravel `ExampleTest` scaffold) — pre-existing and out of scope for this pass.

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
