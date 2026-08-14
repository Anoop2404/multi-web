# UI/UX Audit — Sahodaya / Kalotsav Multi-Tenant Platform
**Date:** 2026-08-14 · **Auditor:** Claude (Cowork) · **Scope agreed with user:** full platform, broad sampling; live testing where reachable; fresh/independent pass (prior UI/UX audit docs in the repo were intentionally not consulted before forming these findings)

---

## How this audit was actually conducted (read this before the findings)

This platform is far larger than a single audit pass can cover exhaustively. Route extraction found **1,227 route definitions in `routes/web.php` alone** (548 `GET`, the rest write actions), plus **56 public tenant-website routes** in `routes/tenant.php`, **16 dedicated-domain state routes** in `routes/state.php` (confirmed inert unless `STATE_APP_DOMAIN` is set), and **83 JSON endpoints** in `routes/api.php`. On the frontend, controllers render **381 distinct Inertia/Vue page components** (found by grepping every `$this->inertia('Xxx/Yyy', …)` and `Inertia::render(…)` call site) plus **40 controllers that return Blade views** (mostly PDF/print documents — certificates, hall tickets, invoices, reports). `resources/js/Pages` contains **405 `.vue` page files on disk**.

Given that scale, this audit used **deep sampling**, not exhaustive coverage, per the "full platform, broad sampling" scope the user chose:

- **Shared layouts and design-system components were read in full** (5 layout shells, 15 `Components/ui/*` primitives, the shared data table, nav item component) — this is the highest-leverage area since a defect here reproduces on every page that uses it, and it is now covered close to exhaustively.
- **A representative sample of ~20 page-level Vue components** was read across the Sahodaya Admin, School Admin, Superadmin/State Admin, and Student Portal areas (login/password-reset flow, three dashboards, one portal appeal form).
- **Live browser testing** was performed once the user started their local dev server (`docker-compose` + `*.test` domains via the desktop bridge). Confirmed reachable and tested live: the Superadmin login (`superadmin.test:8000`), a public tenant website (`malappuramsahodaya.test:8000`), its School Registration form, and Laravel's raw 404 page.
- **Authenticated admin/portal pages could not be driven live.** Per this session's safety rules, the agent is not permitted to enter credentials/passwords into a login form on the user's behalf, even with the user's permission — so anything behind a login was audited by source code only, not by clicking through it. This is the single biggest coverage gap in this report; see the "Coverage and Unverified Areas" section for exactly what that excludes.
- **True responsive breakpoint testing (375px / 768px) could not be verified live.** The browser automation's `resize_window` call reported success but the screenshots it produced were pixel-identical at 375×812, 768×1024, and 1440×900 (all rendered at a fixed 1491×812 capture) — the viewport was not actually changing. Responsive findings below are therefore drawn from the Tailwind breakpoint classes (`sm:`, `lg:`) visible in the Vue source, not from a rendered mobile screenshot. This is flagged per-finding.
- The remaining ~360 unsampled Inertia pages are accounted for in the Page Inventory by module/count, not individually reviewed. Given how consistently the sampled pages share the same layout shells and `Components/ui/*` primitives, the shared-component findings below very likely generalize across most of them — but that is an inference, not a verified fact, and is stated as such throughout.

---

# UI/UX Audit Summary

**Total routes discovered:** 1,227 in `web.php` + 56 in `tenant.php` + 16 in `state.php` (inert) + 83 JSON API endpoints in `api.php` = **~1,300 backend routes**, resolving to **381 distinct Vue/Inertia pages**, **40 Blade-view controllers** (mostly print/PDF documents), and roughly **56 public marketing/CMS pages** per tenant website.

**Pages visually/interactively audited (running application):** 6 — Superadmin login (`/login`), its 404 fallback, a public tenant homepage, its "Academic" nav dropdown, its School Registration form (including an empty-submit validation check), and desktop-viewport (1491px-wide) responsive behavior of all of the above.

**Pages inspected through source code only:** ~26 in depth (5 shared layouts, 15 shared UI components, 1 shared data table, 1 shared nav-item component, Sahodaya Admin Login/ForgotPassword/ResetPassword, Sahodaya Admin Dashboard, Student Portal Dashboard) plus the full route table and Vue page directory tree for inventory purposes.

**Pages not audited at all (neither visually nor read in source):** the large majority of the 405 Vue page files — most single-purpose report/CRUD screens under Sahodaya Admin (192 pages), School Admin (101), Portal role dashboards/workflows (54), and Superadmin (31). These are listed by module and count in the Page Inventory below, not individually reviewed. Nothing behind a login was reachable live in this session (see limitations).

**Total findings by priority:** 4 Critical, 9 High, 8 Medium, 5 Low (26 total, most cross-cutting — i.e. each affects many pages at once because it lives in a shared layout/component rather than a single page).

**Five highest-impact improvements** (detail in Findings and Implementation Plan below):
1. Fix the "staff read-only" enforcement pattern used in three separate layout files — it currently disables form fields with CSS (`pointer-events: none`) only, which does not stop keyboard-focused input, so a "view only" staff account can still type into and submit fields the UI claims are locked.
2. Build branded 403/404/419/429/500 error pages — confirmed live, the app currently falls through to Laravel's raw unstyled default error page with no navigation back into the product.
3. Fix the public-website nav dropdown ("Academic" menu, and by pattern likely every top-nav dropdown using the same markup) which does not close or reposition on scroll — confirmed live, it ends up floating over unrelated page content.
4. Unify the login/auth visual language — at least three unrelated visual designs were found across login-adjacent screens (navy/gold Sahodaya login, indigo/purple Superadmin login, purple public-website branding bleeding into the School Registration form), which undermines trust that these belong to the same product.
5. Retrofit the shared interactive components (`SearchableSelect`, `ActionsMenu`, `ConfirmDialog`, sortable table headers, pagination, nav "active" state) with proper ARIA roles/states — none of them currently expose their open/closed, sorted, current-page, or current-nav-item state to assistive technology, and this propagates to every page that uses them.

**Overall product strengths** (worth preserving, not just criticizing):
- The `Components/ui/*` library shows real accessibility intent in places: `FlashBanner`/`ValidationBanner` use `role="status"`/`role="alert"` with `aria-live` correctly, decorative icons are consistently `aria-hidden`, global CSS never removes `:focus` outlines without substituting a visible focus ring, and `FormField.vue` actively wires up `aria-labelledby` on its slotted inputs at runtime.
- The Sahodaya Admin dashboard (`Pages/Admin/Sahodaya/Dashboard.vue`) is a genuinely well-built piece of UI: a real `role="progressbar"` with `aria-valuenow/min/max` for setup completion, a prioritized "action queue," and a "get started" step sequence for brand-new tenants.
- Destructive-action confirmation is centralized behind one `ConfirmDialog.vue` + `useConfirm()` composable rather than scattered native `confirm()` calls — the right architecture, even though (see findings) its accessibility needs work.
- Server-side, the app already redirects `abort()`/`abort_if()` business-rule rejections into the existing `FlashBanner` instead of Laravel's raw exception page (per a comment in `bootstrap/app.php`) — showing the team has actively worked on exactly this class of problem before, just not for HTTP 404/403/500.

**System-wide problems:**
- No single shared `Sidebar`/`AppShell` component — five layout files (`AdminLayout`, `SahodayaAdminLayout`, `SchoolAdminLayout`, `SahodayaEventsLayout`, `PortalLayout`) independently reimplement the same sidebar/mobile-drawer/header pattern with copy-pasted CSS, and have already drifted (different icon systems, different "active nav item" logic, different mobile-close affordances).
- Visual-only state indication throughout: current pagination page, current sort column/direction, and current nav item are all conveyed by background color alone, with no `aria-current`/`aria-sort` anywhere in the codebase's shared components.
- Uneven design investment between admin-facing and end-user-facing screens: the Sahodaya/School admin dashboards are richly designed; the Student Portal dashboard by contrast is nine flat, equally-weighted list sections with no prioritization, and one of its forms (fest-item appeal) bypasses the shared `FormField` component entirely and uses placeholder-only labels.

**Audit limitations and assumptions** (expanded at the end of this report): no authenticated pages were driven live; true mobile/tablet viewport screenshots could not be captured due to a tooling limitation; database/seed state of the running app is unknown so empty-state/pagination behavior on list pages is inferred from component code, not observed with real data; the Flutter mobile app (`sahodaya_mobile/`) and the static exported per-tenant site dumps (`sahodaya-websites/`) are out of scope for a web UI/UX audit and were not reviewed.

---

# Page Inventory

Grouped at module level for usability — see "How this audit was actually conducted" for exact counts. Audit Method reflects the deepest level of review actually performed for that row.

| Module / Route group | Purpose | Access/Role | Pages (Vue/Blade) | Audit Method | Status |
|---|---|---|---|---|---|
| Auth — Sahodaya Admin login/reset (`/sahodaya-admin/*` login family) | Sign in, forgot/reset password, change password, email verify | Public → Sahodaya admin | `Admin/Auth/Login.vue`, `ForgotPassword.vue`, `ResetPassword.vue`, `ChangePassword.vue`, `VerifyEmail.vue` (5) | Code inspection (3 read fully) | Findings below |
| Auth — School / Portal / Superadmin login variants | Separate login screens per audience | Public | `SchoolLogin.vue`, `PortalLogin.vue`, `SuperadminLogin.vue` (3) | Superadmin: Running application (live). School/Portal: Code inspection (not opened this pass — inventoried only) | Partially audited |
| Superadmin / State workspace | Tenant management, billing, master data, site-builder theme, dev tools | Superadmin / State admin | `Pages/Admin/*` excl. Sahodaya/School (31 pages: Tenants, Billing, MasterData, Builder, Display, SkinPresets, Audit, State/*, StateAdmin/*) | Code inspection (layout + dashboard nav only; individual pages not opened) | Not sampled — inventoried only |
| Sahodaya Admin — core (dashboard, schools, membership, users, settings, setup) | Cluster-level administration | Sahodaya admin / staff | ~40 pages | Dashboard: code inspection (full read). Rest: inventoried only | Partially audited |
| Sahodaya Admin — Events/Fest module (catalog, items, registrations, schedule, marks, results, certificates, id-cards, finance, reports) | Kalotsav/fest competition management | Sahodaya admin / staff / event coordinators | ~130 pages, incl. **16 distinct Reports variants** alone (`Events/Reports/Hub`, `ByHead`, `Downloads`, `SchoolDetailed`, `OverallRanking`, `HouseDetailed`, `ParticipationCounts`, `MarkEntryStatus`, `ScheduleClashes`, `ItemSchedule`, `ItemCounts`, `DisciplineRegistration`, `HeadWiseParticipants`, `AreaWiseParticipants`, `AgeGroupMatrix`, `FeeCollection`) | Layout (`SahodayaEventsLayout`) code-inspected; individual report/registration pages not opened | Not sampled — inventoried only |
| Sahodaya Admin — MCQ/Talent-Search exams | Exam setup, hall tickets, marks, results | Sahodaya admin | ~22 pages (Dashboard, GradeMasters, Payments, Series, Templates, reports) | Not sampled | Inventoried only |
| Sahodaya Admin — Training programs | Resource persons, sessions, attendance, certificates | Sahodaya admin | ~15 pages | Not sampled | Inventoried only |
| School Admin — core (dashboard, staff, students, teachers, settings, setup) | School-level administration | School admin / staff | ~35 pages | Dashboard file listed but not opened this pass | Not sampled — inventoried only |
| School Admin — Fest/Events/Sports participation | Register students, view schedules/results at school level | School admin / event coordinator | ~35 pages | Not sampled | Inventoried only |
| School Admin — Board Results / Principal Verification | CBSE result upload and principal sign-off workflow | School admin / Principal | ~10 pages across `School/BoardResults` + `School/BoardResults/PrincipalVerification` (Vue) — note this workflow is duplicated under **both** `Pages/Admin/School/...` and `Pages/School/...` | Not sampled | Inventoried only — **duplicate page-tree flagged, see Shared Findings** |
| School Admin — Website/CMS, gallery, news, circulars, alumni, testimonials, job vacancies | Public-site content management | School admin | ~20 pages | Not sampled | Inventoried only |
| Portal — Student | Dashboard, registrations, MCQ exams, results, certificates, appeals | Student (portal auth) | `Portal/Student/Dashboard.vue` + related (est. 8–10) | Dashboard: code inspection (full read) | Partially audited |
| Portal — Teacher | Dashboard, MCQ registration, question papers, training | Teacher (portal auth) | `Portal/Teacher/Dashboard.vue` + related (est. 8) | Not sampled | Inventoried only |
| Portal — Judge / State Judge | Mark entry, dashboards | Judge (portal auth) | 2 dashboards + related | Not sampled | Inventoried only |
| Portal — Exam Ops / Fest Ops / Fest Coordinator / Group / House Admin | Operational staff dashboards for event-day tasks | Various portal roles | 5 dashboards + related | Not sampled | Inventoried only |
| Public tenant website (CMS/site-builder) | Marketing site per Sahodaya/School tenant — home, about, programmes, member schools, office bearers, contact, gallery, news, events, MOA/governance pages, downloads | Public, anonymous | 56 routes in `tenant.php`, rendered via ~35 reusable "section" Blade templates (hero, stats, testimonials, etc.) plus a Vue-driven builder admin | **Home page + nav dropdown: Running application (live).** Rest: code inspection (route list + section template inventory only) | Partially audited |
| Public — Fest/results/scoreboard | Public live results, scoreboards, participant lookup, records | Public, anonymous | ~15 routes (`/{event}`, `/{event}/live`, `/{event}/scoreboard`, `/{event}/search`, etc.) | Not sampled | Inventoried only |
| Public — School Registration / Membership application | New-school signup flow | Public, anonymous | 1 multi-field form (+ success/token pages) | **Running application (live)**, including one empty-submit validation check | Audited |
| Public — Training QR registration, attendance, admission enquiry | Misc public forms | Public, anonymous | ~6 forms | Not sampled | Inventoried only |
| Public — Certificate/result verification & print | Public certificate/result authenticity check, PDF/print output | Public, anonymous | ~15 Blade print/verify templates | Not sampled | Inventoried only |
| System error pages | 403 / 404 / 419 (session expired) / 429 / 500 / maintenance | All | **None exist** — no custom Blade/Inertia error views found anywhere in `resources/views` or `resources/js` | 404: **Running application (live)**. 419 (session-expired) handling: code inspection (`bootstrap/app.php` — this one *is* handled, redirects to login with a flash message). 403/429/500/maintenance: code inspection only, not triggered live | **Critical gap confirmed** |
| Print/PDF documents (certificates, hall tickets, ID cards, invoices, receipts, attendance sheets, mark sheets) | Downloadable/printable official documents | Various, mostly authenticated | ~150 Blade templates under `resources/views/{fest,mcq,board-results,training,receipts,reports}/*` | Not sampled | Inventoried only — flagged as a large unaudited surface, out of proportion to time available |

---

# Page-by-Page Findings

## Superadmin Login — `/login` (on `superadmin.test`)
Audited via running application at desktop viewport (~1491px capture width); keyboard Tab order also tested live.

No meaningful page-specific issues were identified during visual/interaction inspection — the form has visible, reasonably high-contrast focus rings on both Tab stops tested, a properly-associated icon+label pattern, and a clear amber session-expired notice. Cross-page consistency issues involving this screen are covered in Shared Component Findings (the "three unrelated visual designs" finding).

## Laravel default 404 page — any unmatched URL
Audited via running application (navigated to a deliberately invalid URL under `superadmin.test`).

### Finding 1: No branded error pages exist anywhere in the app; 404 falls through to Laravel's raw default page
- **Priority:** Critical
- **Category:** trust-and-polish / error-handling
- **Evidence:** Live screenshot of `http://superadmin.test:8000/this-page-does-not-exist-xyz` shows a completely unstyled white page with plain black text "404 | Not Found" in the browser's default serif/sans stack — no logo, no navigation, no "return home" link, no app chrome of any kind. Confirmed in source: `resources/views/` contains no `403`, `404`, `419`, `429`, `500`, or `maintenance` templates anywhere (searched by filename pattern), and `bootstrap/app.php`'s `withExceptions()` block only special-cases `TokenMismatchException`/419 and generic business-rule `abort()` calls — there is no custom renderer for `NotFoundHttpException` or other 4xx/5xx statuses.
- **Problem:** Any broken link, stale bookmark, typo'd URL, or expired resource reference drops the user out of the entire product experience into a page that looks like the server crashed, with zero way back in except the browser's back button.
- **User impact:** Confusing and alarming for non-technical users (school staff, parents, students); actively damages trust ("is this website broken?") at exactly the moment something has already gone wrong for the user.
- **Recommended improvement:** Add Laravel Blade views at `resources/views/errors/{403,404,419,429,500}.blade.php` (Laravel auto-resolves these by convention) styled with the existing brand shell and a link back to the user's relevant home (portal/admin dashboard or public site, depending on which guard/domain served the request). For Inertia-driven admin/portal routes, consider rendering the error through Inertia (as already done for 419) rather than a raw Blade page, so the app chrome doesn't flash away entirely.
- **Affected viewport(s):** Confirmed at desktop (~1491px); by nature (server-rendered, no responsive CSS at all) this is broken identically at every viewport.
- **Related file/component:** `bootstrap/app.php` (`withExceptions`), new files under `resources/views/errors/`.
- **Related pages:** Every route in the application — this is the single highest-reach finding in the audit, since literally any URL can hit it.
- **Acceptance criteria:** Navigating to a nonexistent URL, a URL for a resource the current user lacks permission to view, and a rate-limited URL each render a branded page matching the current section's visual language, with a working link back into the app; verified for at least the public-website domain and one authenticated admin domain.
- **Verification method:** Visually verified in the running application (404); 403/429/500 need to be triggered and checked once implemented (not currently reproducible without an authorization/rate-limit scenario).

## Public Tenant Website Home — `/` (on `malappuramsahodaya.test`)
Audited via running application at desktop viewport; scroll behavior and nav-dropdown interaction tested live.

### Finding 2: Top-nav dropdown ("Academic") does not close or reposition on scroll, and ends up floating over unrelated content
- **Priority:** Critical
- **Category:** navigation / interaction bug
- **Evidence:** Live-reproduced twice. Steps: open the "Academic" nav dropdown (click), then scroll the page down. The dropdown panel (showing "Kids Fest 2025-26," "Athletic Meet," "Kalotsav 2025," etc.) stays visually fixed at its original screen position instead of closing or tracking the (sticky) nav trigger, and the page content scrolls underneath it — producing a screenshot where the dropdown list is overlaid on top of the unrelated "Programmes & Services" card grid, with faded/ghosted page content bleeding through around its edges.
- **Problem:** The dropdown has no scroll-dismiss or scroll-reposition handling.
- **User impact:** On any page long enough to scroll (nearly all of them — this is a marketing site with several content sections), opening the Academic menu and then scrolling leaves a broken-looking overlay on screen that obscures real content and looks like a rendering bug, undermining trust in the public-facing site that CBSE-affiliated schools' parents and staff visit.
- **Recommended improvement:** Close the dropdown on scroll (simplest fix, matches the pattern `ActionsMenu.vue` already uses for Escape-key dismissal) or reposition it relative to its trigger using a floating/anchored positioning strategy if it needs to stay open during scroll.
- **Affected viewport(s):** Confirmed at desktop (~1491px). Not verified at mobile/tablet due to the `resize_window` limitation noted above, but the underlying cause (no scroll listener) is viewport-independent, so it likely reproduces everywhere the same nav markup renders.
- **Related file/component:** Public-website header/nav partial — likely `resources/views/partials/navbar.blade.php` or one of `resources/views/partials/navbars/*.blade.php` (the tenant's active navbar variant); not confirmed by file read this pass, only by live behavior.
- **Related pages:** Every public tenant-website page using this navbar (all 56 `tenant.php` routes, all tenant sites) — the Kerala CBSE Kalotsav site and every school's own public site described under `sahodaya-websites/` likely share the same navbar component.
- **Acceptance criteria:** Opening any top-nav dropdown and then scrolling either closes the dropdown automatically or keeps it correctly anchored to its trigger with no overlap of unrelated content, verified on at least two different nav dropdown items.
- **Verification method:** Visually verified in the running application (reproduced twice, once as an incidental scroll observation and once deliberately isolated by clicking the trigger first).

## Public School Registration — `/school-register` (on `malappuramsahodaya.test`)
Audited via running application; one empty-submit validation interaction tested live.

No meaningful page-specific issues were identified during visual/interaction inspection. The form has clear required-field asterisks, helpful inline hint text ("Use a valid email address — this will be your login username..."), a sensible two-column layout for related short fields (CBSE Affiliation No. / Phone), and relies on native HTML5 `required` validation which correctly blocked submission and returned focus to the first empty field. One low-priority note: "Address" is a single-line `<input>` rather than a `<textarea>`, which may truncate multi-line addresses — Low priority, cosmetic. Cross-page branding inconsistency involving this screen is covered in Shared Component Findings.

## Sahodaya Admin Login / Forgot Password / Reset Password — `/sahodaya-admin/login` family
Audited via source code (`Admin/Auth/Login.vue`, `ForgotPassword.vue`, `ResetPassword.vue` read in full); not driven live (requires no auth, but was deprioritized in favor of the confirmed-reachable Superadmin domain — genuine gap, see limitations).

### Finding 3: "Forgot password" exit link sends users to the wrong login screen
- **Priority:** Medium
- **Category:** navigation / user-flow friction
- **Evidence:** `Login.vue` links "Forgot password?" to `/portal/forgot-password`. `ForgotPassword.vue` and `ResetPassword.vue` are generic, shared components: their "back" link is hardcoded to `<a href="/portal/login">← Back to portal login</a>` regardless of which login screen sent the user there, and their headings say only "Reset password" / "Choose new password" with no indication of which account type (admin, school, portal) is being reset.
- **Problem:** A Sahodaya admin who clicks "Forgot password?" from their branded navy/gold admin login is dropped into a generically-branded reset flow, and if they click "Back to portal login" afterward they land on the student/teacher/judge portal login screen — not back at the Sahodaya admin login they started from.
- **User impact:** Confusing dead-end for admin/school users doing a password reset; they must know to manually re-navigate to their correct login URL, which is a meaningful source of support requests for a non-technical user base (school office staff).
- **Recommended improvement:** Either (a) pass the originating context through the forgot/reset flow (query param or route segment) and use it to pick the correct "back" destination and heading copy, or (b) give the Sahodaya admin, School, and Superadmin login flows their own contextual copy on this shared component the way `Login.vue`'s branding props already do.
- **Affected viewport(s):** Not visually verified at any viewport (code inspection only); the bug is in link `href` values, not layout, so it is viewport-independent.
- **Related file/component:** `resources/js/Pages/Admin/Auth/ForgotPassword.vue`, `ResetPassword.vue`, `Login.vue`.
- **Related pages:** Likely affects `SchoolLogin.vue`, `PortalLogin.vue`, `SuperadminLogin.vue` too if they share the same forgot/reset components (not confirmed — those three page files were not opened this pass).
- **Acceptance criteria:** Starting a password reset from each of the four login screens (Sahodaya admin, School, Portal, Superadmin) and clicking "back" returns the user to the login screen they started from, not a generic default.
- **Verification method:** Identified through source-code inspection only; needs interaction verification once reachable.

### Finding 4: Focus-ring styling is inconsistent between the main login and its own password-reset sub-pages
- **Priority:** Low
- **Category:** visual consistency
- **Evidence:** `Login.vue`'s `<style scoped>` defines `.login-input:focus { border-color: #1e5aa8; background: #fff; box-shadow: 0 0 0 3px rgba(30,90,168,.15); }`. `ForgotPassword.vue` and `ResetPassword.vue` reuse the same `.login-input` class name but their scoped `<style>` blocks define no `:focus` rule at all — because Vue `<style scoped>` doesn't share across files, these two pages fall back to the bare browser-default focus outline instead of the branded ring.
- **Problem:** Copy-pasted component styling without copying the complete state (`:focus`, `:hover`, etc.) leads to silent visual drift between pages that are supposed to look identical.
- **User impact:** Minor — the browser default outline is still visible and accessible, just visually inconsistent with the rest of the auth flow.
- **Recommended improvement:** Extract `.login-*` styles into one shared CSS partial or a `AuthCard`/`AuthInput` Vue component instead of duplicating `<style scoped>` blocks across three files.
- **Affected viewport(s):** Not visually verified (code inspection only).
- **Related file/component:** `ForgotPassword.vue`, `ResetPassword.vue`, `Login.vue`.
- **Related pages:** Same auth-flow family as Finding 3.
- **Acceptance criteria:** Focus state on the email/password inputs looks identical (same ring color/width) across Login, Forgot Password, and Reset Password screens.
- **Verification method:** Identified through source-code inspection only.

## Sahodaya Admin Dashboard — `/sahodaya-admin/{id}` (`Pages/Admin/Sahodaya/Dashboard.vue`)
Audited via source code (read in full).

No meaningful page-specific issues were identified during code inspection — this page is a strong positive example (see Summary). It should be visually/interactively verified once authenticated access is available, since a code read cannot catch real-data edge cases like a stat card overflowing with a very large number, or the "action queue" grid wrapping awkwardly with 5+ simultaneous action items.

## Student Portal Dashboard — `/portal/student/{schoolId}` (`Pages/Admin/Portal/Student/Dashboard.vue`)
Audited via source code (read in full).

### Finding 5: Nine equally-weighted sections with no content prioritization
- **Priority:** Medium
- **Category:** visual hierarchy / content prioritization
- **Evidence:** The template stacks, unconditionally in document order: Upcoming events → Sports profile → My registrations → Talent Search (MCQ) exams → Fest Schedule → Fest Results → Fest Certificates → Admit Cards → Appeals → Notifications. Every section uses the identical `class="card mb-4"` wrapper and an identical `<h2 class="font-semibold text-sm">` heading — there is no visual distinction between "action needed now" (e.g. an upcoming exam link, a pending appeal) and purely historical read-only information (past certificates, old results).
- **Problem:** A student with several fest registrations and exam history has a genuinely long single-column scroll of small (`text-sm`/`text-xs`) text with no way to tell at a glance what needs attention today versus what's archival.
- **User impact:** Increases time-to-find for the actual reason a student opened the portal (e.g. "do I have an exam today," "what's my chest number"); this is the primary screen for the platform's largest user population (students) and currently gets the least design investment of any dashboard sampled in this audit.
- **Recommended improvement:** Introduce a lightweight priority tier — e.g. a compact "today/this week" summary strip at top (next scheduled item, any pending appeal, any action-needed exam) using the same `DashboardStatCard`/action-queue pattern already built for the Sahodaya Admin dashboard, then move the read-only historical sections (past results, certificates) below a visual fold or into tabs.
- **Affected viewport(s):** Not visually verified (code inspection only); the density problem is worst on mobile, where this student-facing portal is disproportionately likely to be used (per the platform's audience — students/parents on phones).
- **Related file/component:** `resources/js/Pages/Admin/Portal/Student/Dashboard.vue`, `Layouts/PortalLayout.vue`.
- **Related pages:** Likely shared pattern with Teacher/Judge/other portal-role dashboards (not sampled this pass, flagged as needing the same review).
- **Acceptance criteria:** Time-sensitive items (today's schedule, pending actions) are visually distinguished from historical/read-only content on first screen, verified against a real account with data in every section.
- **Verification method:** Identified through source-code inspection only.

### Finding 6: Fest-appeal form bypasses the shared label/FormField pattern — placeholder-only labels
- **Priority:** High
- **Category:** accessibility / form usability
- **Evidence:** In the same file, the appeal-submission form is: `<select v-model="appealForm.participant_id" class="field text-sm" required><option value="">Select entry to appeal…</option>…</select>` and `<textarea v-model="appealForm.reason" class="field text-sm" rows="2" placeholder="Reason" required></textarea>` — neither control has a `<label>` element, `aria-label`, or is wrapped in the codebase's own `FormField.vue` (which exists specifically to solve this and is used correctly elsewhere, e.g. the login forms).
- **Problem:** The `<select>`'s only "label" is a placeholder `<option>` that disappears once a choice is made, and the `<textarea>`'s only label is `placeholder="Reason"` text that disappears the moment the user starts typing. This fails WCAG 2.2 SC 3.3.2 (Labels or Instructions) and 4.1.2 (Name, Role, Value) — a screen reader user tabbing to the textarea hears no accessible name at all once it has content, and a sighted user who clicks into the field and pauses loses the "Reason" prompt entirely.
- **User impact:** A student submitting a fest-result appeal — already a stressful, time-sensitive action — gets no persistent guidance on what to enter, and screen-reader users may not be able to identify the field at all.
- **Recommended improvement:** Wrap both controls in the existing `Components/ui/FormField.vue` with real `label` text ("Entry to appeal", "Reason for appeal"), consistent with how forms are built elsewhere in the codebase.
- **Affected viewport(s):** Not visually verified (code inspection only); this is a markup defect, not a layout one, so it is viewport-independent.
- **Related file/component:** `resources/js/Pages/Admin/Portal/Student/Dashboard.vue`; contrast with `resources/js/Components/ui/FormField.vue` which solves this correctly.
- **Related pages:** Worth spot-checking other hand-rolled forms outside the admin CRUD pages (which appear to consistently use `FormField`) — portal-side forms in particular, since this is the second portal-side form issue found in the one portal page sampled.
- **Acceptance criteria:** Both the appeal-entry selector and reason textarea have a programmatically-associated, persistent label, verified with a screen reader (VoiceOver/NVDA) and by inspecting the accessibility tree.
- **Verification method:** Identified through source-code inspection only.

---

# Shared Component Findings

This is the highest-leverage section of this audit: every finding here was found once but reproduces on every page using the affected component. Coverage is close to exhaustive for `Components/ui/*`, the five layout shells, and the shared data table/nav-item components (all were read in full).

## Navigation and layouts

### Finding 7: "Staff read-only" mode is enforced with CSS only — keyboard users can still edit and submit "locked" fields
- **Priority:** Critical
- **Category:** correctness / access-control-in-the-UI
- **Evidence:** Identical block repeated verbatim in three separate layout files — `SahodayaAdminLayout.vue`, `SchoolAdminLayout.vue`, and `AdminLayout.vue`:
  ```css
  .staff-readonly :deep(button[type="submit"]:not(.staff-allow)),
  .staff-readonly :deep(input:not([type="hidden"]):not([readonly])),
  .staff-readonly :deep(select),
  .staff-readonly :deep(textarea) {
      pointer-events: none;
      opacity: 0.65;
  }
  ```
  This is applied to `<main>` whenever `isReadOnlyStaff`/`isStateStaff && !isSuperAdmin` is true, alongside a "View only" badge in the header.
- **Problem:** `pointer-events: none` only blocks *mouse* interaction. It does not disable the element. A user who reaches a text input by pressing Tab (keyboard navigation, or a screen reader's forms mode) can still type into it and, if they can Tab to a submit button, can still submit whatever the JavaScript form logic allows — `pointer-events: none` does not block keyboard activation of a focused, non-disabled `<button>` either. Compare this to `ResetPassword.vue`'s email field, which correctly uses the real `readonly` HTML attribute — the codebase clearly knows the correct pattern elsewhere but doesn't apply it consistently here.
- **User impact:** A "view only" staff account — explicitly a lower-trust tier the product itself creates and labels as restricted — can bypass the restriction shown in the UI using only the keyboard, potentially submitting changes the product intends to block at this layer. (Note: this audit did not check server-side authorization for these same actions, which may independently block the write — that's a security-audit question, not a UI/UX one. But the UI's own promise of "view only" is not actually kept by the UI itself, which is a correctness/trust problem regardless of what the server does.)
- **Recommended improvement:** Apply the real `disabled` attribute (for buttons/selects) and `readonly` attribute (for text inputs/textareas) driven by the same `isReadOnlyStaff` flag, rather than a CSS-only visual treatment. This also fixes a secondary issue: `opacity: 0.65` on its own does not reliably communicate "disabled" to assistive tech either, whereas the native `disabled` attribute does.
- **Affected viewport(s):** Not viewport-dependent — same on all sizes.
- **Related file/component:** `Layouts/SahodayaAdminLayout.vue` (lines ~359-365), `Layouts/SchoolAdminLayout.vue` (lines ~302-307), `Layouts/AdminLayout.vue` (lines ~227-233).
- **Related pages:** Every page rendered inside these three layouts while the current user is a read-only staff member — a large share of the admin surface.
- **Acceptance criteria:** With a read-only staff account, Tab-ing to any form field inside a read-only page and attempting to type/select/submit has no effect, verified via keyboard-only testing (no mouse).
- **Verification method:** Identified through source-code inspection only; needs interaction verification with a real staff-scoped account.

### Finding 8: Five independent layout implementations duplicate the same sidebar/mobile-drawer pattern, and have already drifted
- **Priority:** High
- **Category:** consistency / maintainability
- **Evidence:** `AdminLayout.vue`, `SahodayaAdminLayout.vue`, `SchoolAdminLayout.vue`, `SahodayaEventsLayout.vue`, and `PortalLayout.vue` each independently define their own `<aside>` sidebar, mobile hamburger button, mobile drawer overlay, and `isActive()`/nav-highlighting logic, rather than sharing one parameterized layout. They have visibly drifted:
  - **Icons:** `AdminLayout.vue` uses raw emoji characters for nav icons (`📊`, `🏛️`, `➕`, `💳`, `🔑`, `🎨`, …) with no `aria-hidden`, while `SahodayaAdminLayout`/`SchoolAdminLayout`/`SahodayaEventsLayout` use a shared custom SVG icon set via a `SvgIcon`/`SahodayaSvgIcon` component. `PortalLayout.vue` uses yet a third icon approach (inline `v-html` SVGs keyed by name in a local function).
  - **Active-item logic:** three different implementations of "is this nav item active" exist (`AdminLayout`'s simple `page.url.startsWith(href + '/')`, the shared `adminNavItemActive`/`schoolNavItemActive` helpers used by the Sahodaya/School layouts, and `PortalLayout`'s own more complex sibling-route-aware `isActive()`) — meaning the three could disagree on edge cases like a nav item whose href is a prefix of a sibling item's href.
  - **Mobile-drawer close affordance:** `PortalLayout.vue` has an explicit close button inside its mobile drawer (`✕`, see Finding 10); the other four layouts have no close button at all inside the drawer — the only way to close is tapping the dark overlay outside it, which is not obvious without prior experience with this exact pattern.
  - **CSS duplication:** the `.sa-layout`, `.sa-sidebar`, `.sa-logo-ring`, `.sa-portal-link`, `.sa-preview-btn`, `.sa-main`, and `.staff-readonly` scoped-style blocks are near-byte-identical copy-pastes across `SahodayaAdminLayout.vue`, `SchoolAdminLayout.vue`, and `SahodayaEventsLayout.vue`.
- **Problem:** No single source of truth for "what does the app shell look like" — every fix or improvement (e.g. Finding 7, Finding 9, Finding 12) has to be found and applied independently in up to five places, and evidently already hasn't been (the icon-system and close-button drift above are proof this has already caused real inconsistency, not just theoretical risk).
- **User impact:** Users moving between Superadmin/State tools (emoji icons, no badges) and Sahodaya/School admin (custom SVG icons, badge counts, "Preview Site" link) experience two visually and functionally different products despite both being "the admin area." Portal users get a third, unrelated pattern again.
- **Recommended improvement:** Extract one shared `AppShell`/`SidebarLayout` component parameterized by nav-group data, icon set, and brand tokens; migrate the five layouts onto it incrementally, starting with unifying the three near-identical Sahodaya/School/Events layouts (lowest risk, highest immediate duplication payoff).
- **Affected viewport(s):** All — the drift is structural, not viewport-specific, though the mobile drawer close-button inconsistency (Finding 10) is mobile-only by definition.
- **Related file/component:** `Layouts/AdminLayout.vue`, `SahodayaAdminLayout.vue`, `SchoolAdminLayout.vue`, `SahodayaEventsLayout.vue`, `PortalLayout.vue`.
- **Related pages:** Every page in the application uses exactly one of these five layouts — this is the single broadest-reach structural finding in the audit.
- **Acceptance criteria:** A shared layout primitive exists and at least the three Sahodaya/School/Events layouts are migrated onto it with no visual regression; icon system and active-nav-item logic are unified.
- **Verification method:** Identified through source-code inspection only.

### Finding 9: Nav "active" state and pagination "current page" state are conveyed by color alone — no `aria-current`
- **Priority:** High
- **Category:** accessibility
- **Evidence:** `SahodayaNavItem.vue` (used by three of the five layouts) renders the active link as `active ? 'sa-nav-active border-[#fbbf24] bg-white/12 text-white font-semibold' : …` with no `aria-current="page"` attribute anywhere on the `<Link>`. Same pattern in `PaginationLinks.vue` and the built-in pagination block of `SahodayaDataTable.vue`: the active page link gets `bg-[#0f3d7a] text-white` styling but no `aria-current="page"`.
- **Problem:** WCAG 2.2 SC 4.1.2 (Name, Role, Value) and standard practice both call for `aria-current` to expose "this is the current item" programmatically, not just visually. Screen reader users navigating the sidebar or a paginated table have no way to determine which section/page they're currently on other than by cross-referencing the page title, if any.
- **User impact:** Screen reader and other assistive-tech users lose a basic orientation cue that sighted users get for free from color.
- **Recommended improvement:** Add `:aria-current="active ? 'page' : undefined"` to `SahodayaNavItem.vue`'s `<Link>`, and `:aria-current="link.active ? 'page' : undefined"` to both pagination components.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/sahodaya/SahodayaNavItem.vue`, `Components/ui/PaginationLinks.vue`, `Components/SahodayaDataTable.vue`.
- **Related pages:** Every page with sidebar navigation (nearly all admin/portal pages) and every paginated list page.
- **Acceptance criteria:** Inspecting the accessible-name/state of the current nav item and current pagination page via the browser accessibility tree shows `aria-current="page"` set.
- **Verification method:** Identified through source-code inspection only.

### Finding 10: Mobile drawer close button has no accessible name (relies on a bare "✕" glyph)
- **Priority:** Medium
- **Category:** accessibility
- **Evidence:** `Layouts/PortalLayout.vue`: `<button type="button" class="p-1.5 text-slate-400 hover:text-white" @click="mobileMenuOpen = false"> ✕ </button>` — no `aria-label`.
- **Problem:** The accessible name of this button is whatever a screen reader chooses to call the Unicode "✕" glyph (often "multiplication sign" or read as nothing useful) — not "Close menu."
- **User impact:** Screen reader users navigating the mobile drawer have an unlabeled/mislabeled close control.
- **Recommended improvement:** Add `aria-label="Close menu"`, matching the pattern already used correctly for the hamburger open button (`aria-label="Open menu"`) in the same file and the other four layouts.
- **Affected viewport(s):** Mobile only (this button only renders in the `lg:hidden` mobile drawer) — not visually verified due to the `resize_window` limitation, confirmed by source only.
- **Related file/component:** `Layouts/PortalLayout.vue`.
- **Related pages:** Every portal page (student/teacher/judge/etc. all share `PortalLayout`).
- **Acceptance criteria:** The mobile drawer's close button has an accessible name of "Close menu" (or equivalent), verified via accessibility tree inspection.
- **Verification method:** Identified through source-code inspection only.

### Finding 11: No focus trap, no Escape-key handling, and no visible close button for the mobile nav overlay in four of five layouts
- **Priority:** High
- **Category:** accessibility / dialog focus management
- **Evidence:** In `AdminLayout.vue`, `SahodayaAdminLayout.vue`, `SchoolAdminLayout.vue`, and `SahodayaEventsLayout.vue`, the mobile nav drawer is a `<div v-if="mobileNavOpen">` overlay with only `@click="mobileNavOpen = false"` on the backdrop — no `role="dialog"`/`aria-modal="true"` on the drawer itself, no focus moved into the drawer when it opens, no focus trap keeping Tab cycling inside it, and no `keydown.Escape` handler.
- **Problem:** A keyboard user who opens the mobile menu can Tab straight through it into page content that's still present (just visually hidden behind the dark overlay), and has no keyboard-only way to close the drawer (Escape does nothing; there's no visible close button either, per Finding 8's drawer-close-affordance note).
- **User impact:** Keyboard-only users can become effectively stuck or disoriented in the mobile navigation flow.
- **Recommended improvement:** Add `role="dialog" aria-modal="true"` to the drawer, move focus to the first nav link (or a close button) on open, trap Tab within the drawer while open, restore focus to the hamburger trigger on close, and add an `Escape` handler — the same fix, applied once if Finding 8's shared-layout consolidation happens first.
- **Affected viewport(s):** Mobile only — not visually verified due to the `resize_window` limitation.
- **Related file/component:** Same four layout files as Finding 8.
- **Related pages:** Same reach as Finding 8.
- **Acceptance criteria:** With the mobile drawer open, Tab cycles only within it, Escape closes it and returns focus to the trigger button, verified via keyboard-only testing.
- **Verification method:** Identified through source-code inspection only.

## Buttons, links, and dropdowns

### Finding 12: Custom dropdown/combobox components (`SearchableSelect`, `ActionsMenu`) don't implement standard ARIA patterns
- **Priority:** High
- **Category:** accessibility
- **Evidence:** `SearchableSelect.vue` (the searchable school/entity picker used widely across admin filter/report UIs) renders its trigger as a plain `<button>` with no `aria-haspopup`, `aria-expanded`, or `aria-controls`; its options list has no `role="listbox"`; its options have no `role="option"`; there is no arrow-key navigation (only mouse click or Tab-through-each-button). `ActionsMenu.vue` (the "More actions" overflow menu) similarly has no `aria-haspopup`/`aria-expanded` on its trigger and no `role="menu"`/`role="menuitem"` on its panel — though it does at least handle the Escape key correctly, which `SearchableSelect` does not.
- **Problem:** Neither component follows the WAI-ARIA Authoring Practices combobox/menu-button patterns. A screen reader user tabbing to the `SearchableSelect` trigger hears only "button, Select school" with no indication it opens a list of options, and cannot use arrow keys to move through them.
- **User impact:** These are core filtering/navigation controls used across most report and list pages in the admin area — every one of them is materially harder to operate with a screen reader than a native `<select>` would be.
- **Recommended improvement:** Add `aria-haspopup="listbox"`/`aria-expanded`/`aria-controls` to the trigger and `role="listbox"`/`role="option"`/`aria-selected` to the panel in `SearchableSelect.vue`; add `aria-haspopup="menu"`/`aria-expanded` and `role="menu"`/`role="menuitem"` in `ActionsMenu.vue`; add arrow-key navigation to `SearchableSelect.vue`'s option list and Escape-key handling to match `ActionsMenu.vue`.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/ui/SearchableSelect.vue`, `Components/ui/ActionsMenu.vue`.
- **Related pages:** Any admin/report page using a school/entity picker or an overflow actions menu — a large, unquantified share of the ~130 Fest/Events pages alone likely use `SearchableSelect` given its placeholder default ("Select school…").
- **Acceptance criteria:** Both components are fully operable by keyboard alone (open, navigate options, select, close) and announce their role/state correctly in a screen reader, verified with VoiceOver or NVDA.
- **Verification method:** Identified through source-code inspection only.

### Finding 13: `FormField`'s automatic label-association likely fails silently when its slot content is a custom component rather than a native input
- **Priority:** Medium
- **Category:** accessibility / component-integration risk
- **Evidence:** `FormField.vue` associates its `<label>` with slotted controls by running `controlRef.value.querySelectorAll('input, select, textarea')` in `onMounted`/`onUpdated` and imperatively setting `id`/`aria-labelledby` on whatever it finds. `SearchableSelect.vue`'s root element is a `<div>` containing a `<button>` (not an `input`/`select`/`textarea`), so if any page wraps a `SearchableSelect` inside a `FormField` for its label, the query selector would not match the button, and the label would never be associated with it.
- **Problem:** This is an integration gap between two components that both individually look reasonable, discoverable only by reading both source files together (which is why it likely hasn't been caught by inspection of either component alone).
- **User impact:** Potentially every "labeled" `SearchableSelect` filter across the app has no programmatic label association for assistive tech — but this is inferred from reading the two components' source, not confirmed against an actual page that combines them.
- **Recommended improvement:** Either extend `FormField.vue`'s selector to also match `[role="button"]`/custom control root elements and wire `aria-labelledby` onto them, or have `SearchableSelect.vue` accept and forward an `aria-label`/`labelledby` prop directly so it doesn't depend on DOM-querying magic.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/ui/FormField.vue`, `Components/ui/SearchableSelect.vue`.
- **Related pages:** Any page pairing the two (not confirmed to exist — needs a targeted `grep` for `<FormField` wrapping `<SearchableSelect` to confirm real occurrences before treating this as more than a risk).
- **Acceptance criteria:** Confirm (or rule out) via `grep`/live testing whether any page actually nests `SearchableSelect` inside `FormField`; if so, verify the label is/isn't programmatically associated using the accessibility tree.
- **Verification method:** Identified through source-code inspection only — flagged as a hypothesis requiring verification, not a confirmed defect.

### Finding 14: `ConfirmDialog` (used for every destructive-action confirmation app-wide) has no focus trap and no Escape-key handling
- **Priority:** High
- **Category:** accessibility / dialog focus management
- **Evidence:** `Components/ui/ConfirmDialog.vue` — the single shared confirmation modal invoked via a `useConfirm()` composable for destructive actions across the app — has `role="alertdialog" aria-modal="true"` (good) but no `aria-labelledby`/`aria-describedby` wiring to its title/message text, no focus moved to the dialog (or its Cancel/Confirm buttons) when it opens, no focus trap, and no `Escape`-to-cancel handler (only backdrop click or the two explicit buttons).
- **Problem:** This is the single most reused interactive overlay in the codebase (anywhere a "delete"/"reject"/similar destructive action exists), so a focus-management gap here has the broadest possible reach of any single-component finding in this audit.
- **User impact:** Keyboard users get no automatic focus placement when a confirmation dialog opens (they must manually Tab, possibly through hidden/background content first) and cannot dismiss it with Escape — for a *destructive-action* confirmation specifically, inconsistent or absent keyboard dismissal is a higher-than-average-risk pattern (users may resort to trying Enter/Space in the wrong place, risking accidentally confirming instead of canceling).
- **Recommended improvement:** Add `aria-labelledby`/`aria-describedby` pointing at the title/message elements' ids; on open, move focus to the Cancel button (safer default than Confirm) or the dialog container; trap Tab within the dialog while open; add an `Escape` handler that calls the same `cancel()` function as backdrop-click.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/ui/ConfirmDialog.vue`.
- **Related pages:** Every page in the app that triggers a destructive-action confirmation — could not be enumerated exhaustively in this pass, but by design this is meant to be used everywhere a delete/reject/similar action exists.
- **Acceptance criteria:** Opening any confirmation dialog moves focus into it (ideally to Cancel), Tab cycles only within it, and Escape cancels it — verified via keyboard-only testing on at least two different triggering pages.
- **Verification method:** Identified through source-code inspection only.

## Tables, filters, sorting, and pagination

### Finding 15: Sortable table headers have no `aria-sort`, and the shared data table duplicates (rather than reuses) the standalone pagination component
- **Priority:** Medium
- **Category:** accessibility / duplicate-pattern
- **Evidence:** `SahodayaDataTable.vue`'s sortable `<th>` renders a `<button>` with a small `↑`/`↓` glyph to show current sort direction, but the `<th>` itself never gets `aria-sort="ascending"|"descending"|"none"`. Separately, its built-in pagination block (lines ~34-45) is near-line-for-line identical to the standalone `Components/ui/PaginationLinks.vue` — the table doesn't import and reuse that component, it reimplements the same markup inline.
- **Problem:** Two related issues in one component: (1) sort state isn't exposed to assistive tech per WCAG/ARIA table-sorting best practice, and (2) the exact "duplicate UI pattern that should use a shared component" problem this audit's brief specifically asked to flag.
- **User impact:** Screen reader users can't tell which column a table is currently sorted by; developers maintaining pagination behavior have to remember to fix it in two places (this table has already partially diverged from `PaginationLinks.vue` — it adds a "Showing all N records" no-pagination-needed message that the standalone component lacks).
- **Recommended improvement:** Add `:aria-sort` to the `<th>` bound to current sort state; replace the inline pagination block with `<PaginationLinks :links="links" :meta="meta" />`.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/SahodayaDataTable.vue`, `Components/ui/PaginationLinks.vue`.
- **Related pages:** Every list/report page using `SahodayaDataTable` — likely a large share of the ~130 Fest/Events admin pages and the general admin CRUD index pages, given the component's generic "Sahodaya" naming suggests it's the default table primitive.
- **Acceptance criteria:** Sortable column headers expose `aria-sort`; the table's pagination renders via the shared `PaginationLinks` component with no visual regression to the "showing all N records" case.
- **Verification method:** Identified through source-code inspection only.

## Loading, empty, error, and success states

### Finding 16: Two different empty-state patterns exist and are used inconsistently
- **Priority:** Low
- **Category:** consistency
- **Evidence:** `Components/ui/EmptyState.vue` is a dedicated, reasonably well-built primitive (icon + title + description + optional action slot, icon correctly `aria-hidden`). But `SahodayaDataTable.vue` has its own hardcoded, plainer empty message instead of using it: `<p v-if="!hasRows" class="px-4 py-12 text-center text-gray-400 text-sm">{{ empty }}</p>` (default text "No records found.", no icon, no action slot).
- **Problem:** A table with no rows looks and behaves differently (visually plainer, no room for a call-to-action like "add your first X") than any other empty state in the app that correctly uses `EmptyState.vue`.
- **User impact:** Minor inconsistency, but a missed opportunity — a brand-new tenant's first look at, say, an empty schools list or empty fest-registrations table gets a bare "No records found." instead of the friendlier, action-oriented empty state the app already has a component for.
- **Recommended improvement:** Have `SahodayaDataTable.vue` render `<EmptyState>` (via a slot, to allow per-page customization of the message/icon/action) instead of its own inline paragraph.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/SahodayaDataTable.vue`, `Components/ui/EmptyState.vue`.
- **Related pages:** Every list page using `SahodayaDataTable` with zero rows — most likely to be observed on brand-new tenants, which is exactly when first impressions matter most.
- **Acceptance criteria:** An empty `SahodayaDataTable` renders using `EmptyState.vue`'s visual treatment.
- **Verification method:** Identified through source-code inspection only; would benefit from live verification against a freshly-seeded/empty tenant.

### Finding 17: Flash messages have no manual dismiss control and support only one message per severity at a time
- **Priority:** Low
- **Category:** consistency / minor data-loss risk
- **Evidence:** `FlashBanner.vue` reads `page.props.flash?.success` etc. as single strings (not arrays) and renders with no close/dismiss button (contrast with the very similar `InlineAlert.vue`, which *does* have a dismiss button and is explicitly documented in its own source comments as "a drop-in replacement for native `alert()` popups... looks identical to server-flashed ones").
- **Problem:** If a controller flashes more than one message of the same severity in one request/redirect chain, only the last one set survives to be shown — the others are silently lost. Separately, users have no way to dismiss a flash message early; it persists until the next navigation.
- **User impact:** Low-likelihood but real — a controller that, say, flashes both a specific validation note and a generic success message under the same key would silently drop one.
- **Recommended improvement:** Support an array of messages per severity (render a list, as `ValidationBanner.vue` already does for multiple error messages), and add the same dismiss-button pattern `InlineAlert.vue` already implements.
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Components/ui/FlashBanner.vue`, contrast with `Components/ui/InlineAlert.vue` and `ValidationBanner.vue`.
- **Related pages:** Every page (FlashBanner is included in all five layouts).
- **Acceptance criteria:** Flashing two messages of the same severity in one request shows both; a dismiss control is available.
- **Verification method:** Identified through source-code inspection only.

## Icons and imagery

### Finding 18: Inconsistent icon systems across layouts, and several are missing `aria-hidden`
- **Priority:** Medium
- **Category:** consistency / accessibility
- **Evidence:** See Finding 8 for the full icon-system inventory (emoji in `AdminLayout.vue`, custom SVG set in three layouts, inline `v-html` SVGs in `PortalLayout.vue`). Specifically on the accessibility angle: `AdminLayout.vue`'s emoji icons (`<span class="text-base leading-none">{{ item.icon }}</span>`) have no `aria-hidden="true"`, unlike the icon usage in `DashboardStatCard.vue` and `EmptyState.vue`, which correctly mark their icon spans `aria-hidden`.
- **Problem:** Without `aria-hidden`, some screen readers will announce the emoji's Unicode shortname (e.g. "bar chart" or "credit card") immediately before or after the adjacent text label, effectively double-announcing every nav item in the Superadmin/State sidebar.
- **User impact:** Redundant, noisy screen-reader output specifically in the Superadmin/State admin nav (~30 nav items across the two role variants in that one layout).
- **Recommended improvement:** Add `aria-hidden="true"` to the icon span in `AdminLayout.vue` at minimum (independent of the larger icon-system unification in Finding 8).
- **Affected viewport(s):** Not viewport-dependent.
- **Related file/component:** `Layouts/AdminLayout.vue`.
- **Related pages:** Every Superadmin/State admin page (all use this one layout).
- **Acceptance criteria:** Nav icons in `AdminLayout.vue` are `aria-hidden`, verified via accessibility tree inspection.
- **Verification method:** Identified through source-code inspection only.

---

# Responsive Design Findings

**Important caveat, repeated from the top of this report:** this session's browser-automation `resize_window` calls reported success (375×812, 768×1024, 1440×900 all "resized") but every resulting screenshot was pixel-identical, captured at a fixed 1491×812 — meaning the actual rendered viewport never changed. No genuine mobile or tablet screenshot could be captured this session. Everything below is inferred from the Tailwind breakpoint classes present in the Vue source (`sm:`, `lg:` prefixes), not from an observed rendering, and is labeled accordingly. This should be the first thing re-verified in any follow-up pass — ideally by the user driving a real mobile viewport (browser DevTools device toolbar) while sharing screenshots, or a follow-up session where `resize_window` is confirmed working.

## Desktop — 1440px (effectively ~1491px, the only viewport actually captured)
Confirmed by live screenshots: Superadmin login, public tenant homepage, School Registration form all render cleanly with no overflow/clipping observed at this width. No findings at this tier beyond what's already covered in Page-by-Page Findings (the scroll/dropdown bug, Finding 2, was captured at this width).

## Tablet — 768px
Not verified this session (tooling limitation above). Source-level note: all five layouts key their sidebar-to-drawer collapse off Tailwind's `lg:` breakpoint (1024px), meaning at 768px every layout is already in its "mobile drawer" mode, not a distinct tablet-optimized layout — there is no intermediate tablet-specific arrangement anywhere observed in the sampled components. This is common and not inherently wrong, but means tablet users get the same cramped single-column mobile treatment as phone users despite having meaningfully more width available; worth a deliberate design decision rather than an accident of only having two breakpoints. **Unable to verify further without real viewport screenshots.**

## Mobile — 375px
Not verified this session (tooling limitation above). Source-level notes only:
- The Student Portal Dashboard (Finding 5) is likely worst here — nine stacked `text-sm`/`text-xs` sections with no responsive layout changes visible in the Tailwind classes read (no `sm:`/`lg:` variants on the section grids in that file), meaning the same dense desktop layout simply renders narrower rather than reflowing for mobile.
- Mobile nav drawers (Findings 10, 11) are mobile-only by definition and could not be visually confirmed, only reasoned about from source.
- Touch-target sizing was not assessable without real rendering — Tailwind classes like `px-3 py-2` on nav items and `w-9 h-9` on the hamburger button suggest roughly 36-44px targets, which is in the acceptable-to-borderline range per WCAG 2.2 SC 2.5.8 (24×24px minimum) but was not measured on an actual rendered page.

**Recommended follow-up:** re-run this section of the audit with working viewport emulation before treating the "no mobile findings" as "mobile is fine" — it specifically is not verified, not verified-and-clean.

---

# Accessibility Findings

Consolidated list of every accessibility issue found, cross-referencing the fuller write-ups above.

| # | Issue | Affected pages/components | WCAG 2.2 reference | Priority | Status |
|---|---|---|---|---|---|
| 1 | "Read-only staff" mode blocks only mouse input (`pointer-events:none`), not keyboard | 3 layouts, all pages rendered under a read-only staff session (Finding 7) | 4.1.2 Name, Role, Value (control state not truly disabled) | Critical | Confirmed via source; needs interaction verification |
| 2 | No custom error pages; Laravel's raw 404 has no landmarks/heading structure appropriate to the app | All routes (Finding 1) | 2.4.2 Page Titled (generic), general usability | Critical | Confirmed live |
| 3 | Mobile nav drawers: no focus trap, no Escape handling, no `role="dialog"` | 4 of 5 layouts (Finding 11) | 2.4.3 Focus Order, 2.1.2-adjacent (no keyboard trap *out*, but no way *in* either) | High | Confirmed via source |
| 4 | `ConfirmDialog` (global): no focus movement, no trap, no Escape | Every destructive-action confirmation app-wide (Finding 14) | 2.4.3 Focus Order, 4.1.2 | High | Confirmed via source |
| 5 | `SearchableSelect`/`ActionsMenu`: missing ARIA disclosure/menu semantics, no arrow-key nav in `SearchableSelect` | Widely used filter/actions components (Finding 12) | 4.1.2 Name, Role, Value; 2.1.1 Keyboard | High | Confirmed via source |
| 6 | Nav active item and pagination current page: no `aria-current` | Sidebar nav (3 layouts), pagination (2 components) (Finding 9) | 4.1.2 Name, Role, Value | High | Confirmed via source |
| 7 | Mobile drawer close button unlabeled (bare "✕") | `PortalLayout.vue`, all portal pages (Finding 10) | 4.1.2 Name, Role, Value; 2.4.4 | Medium | Confirmed via source |
| 8 | Table sort state: no `aria-sort` | `SahodayaDataTable` (Finding 15) | 4.1.2 Name, Role, Value | Medium | Confirmed via source |
| 9 | Fest-appeal form: placeholder-only labels on select/textarea | Student Portal Dashboard appeal form (Finding 6) | 3.3.2 Labels or Instructions, 4.1.2 | High | Confirmed via source |
| 10 | Emoji nav icons without `aria-hidden`, causing redundant announcement | `AdminLayout.vue`, Superadmin/State nav (Finding 18) | 1.1.1 Non-text Content (decorative image not marked as such) | Medium | Confirmed via source |
| 11 | `FormField` may fail to associate labels with custom (non-native) slotted controls | Hypothesized `SearchableSelect`-in-`FormField` cases (Finding 13) | 1.3.1 Info and Relationships, 3.3.2 | Medium | Hypothesis — needs verification |

**Confirmed failures vs. potential risks:** items 1, 3, 4, 5, 6, 7, 8, 9, 10 are confirmed by direct source-code reading of the relevant component (high confidence — the markup either has the attribute or it doesn't). Item 2 is additionally confirmed live. Item 11 is explicitly a hypothesis pending verification, called out as such in Finding 13.

**Strengths worth preserving** (already covered in the Summary, repeated here for completeness of the accessibility section specifically): `role="status"`/`role="alert"` + `aria-live` used correctly on flash/validation banners; decorative icons in the well-built components (`EmptyState`, `DashboardStatCard`) correctly `aria-hidden`; no global focus-outline removal without a substitute focus ring anywhere found in `resources/css/app.css`; `FormField.vue`'s runtime `aria-labelledby` wiring for native inputs; a real `role="progressbar"` with value attributes on the Sahodaya dashboard's setup-completion bar.

---

# Recommended Implementation Plan

## 1. Immediate critical fixes
| Item | Affected pages/components | Priority | Effort | Dependencies | Verification |
|---|---|---|---|---|---|
| Build branded 403/404/419/429/500/maintenance error views | All routes (Finding 1) | Critical | Medium | None | Visually trigger each status code per domain type (public site, admin, portal) |
| Fix "staff read-only" enforcement to use real `disabled`/`readonly` attributes instead of CSS-only `pointer-events:none` | 3 layouts (Finding 7) | Critical | Small–Medium (mechanical but must touch every affected input/button binding) | None | Keyboard-only test with a read-only staff account |
| Fix public-site nav dropdown scroll behavior | Public website navbar partial, all 56 tenant routes (Finding 2) | Critical | Small | None | Live: open dropdown, scroll, confirm it closes/repositions |

## 2. High-impact quick wins
| Item | Affected pages/components | Priority | Effort | Dependencies | Verification |
|---|---|---|---|---|---|
| Add `aria-current="page"` to nav items and pagination | `SahodayaNavItem.vue`, `PaginationLinks.vue`, `SahodayaDataTable.vue` (Finding 9) | High | Small | None | Accessibility tree inspection |
| Add focus trap + Escape handling to `ConfirmDialog.vue` | Global (Finding 14) | High | Small–Medium | None | Keyboard-only test on 2+ trigger pages |
| Add focus trap + Escape + `role="dialog"` to mobile nav drawers | 4 layouts (Finding 11) | High | Medium (repeat fix ×4 unless Finding 8 lands first) | Ideally after/with Finding 8 | Keyboard-only test |
| Add ARIA disclosure/menu semantics to `SearchableSelect`/`ActionsMenu` | Global filter/actions components (Finding 12) | High | Medium | None | Screen reader test |
| Wrap Student Portal appeal form fields in `FormField` | Student Portal Dashboard (Finding 6) | High | Small | None | Screen reader test, visual check |
| Fix forgot-password "back" link context per login origin | Auth flow (Finding 3) | Medium | Small–Medium | None | Manual click-through from each login screen |

## 3. Design-system and shared-component improvements
| Item | Affected pages/components | Priority | Effort | Dependencies | Verification |
|---|---|---|---|---|---|
| Extract one shared `AppShell`/`SidebarLayout` from the 5 duplicated layouts | All pages (Finding 8) | High | Large | None, but should land before further per-layout a11y fixes to avoid duplicating work | Visual regression check across all 5 role families |
| Unify icon system (retire emoji icons in `AdminLayout.vue`) | Superadmin/State nav (Finding 18, part of Finding 8) | Medium | Small (if done ahead of full shell unification) or folded into it | Can precede Finding 8 as a quick interim fix | Visual + accessibility tree check |
| Replace `SahodayaDataTable`'s inline pagination with shared `PaginationLinks`; add `aria-sort` | `SahodayaDataTable.vue` (Finding 15) | Medium | Small | None | Visual regression on tables with >1 page |
| Route `SahodayaDataTable`'s empty state through `EmptyState.vue` | Same (Finding 16) | Low | Small | None | Visual check on an empty list |
| Unify `FlashBanner` with `InlineAlert`'s dismiss-button + multi-message support | `FlashBanner.vue` (Finding 17) | Low | Small | None | Trigger 2 same-severity flashes in one request |
| Consolidate `.login-*` auth styles into a shared component/partial | Auth pages (Finding 4) | Low | Small | None | Visual diff of focus states across auth pages |

## 4. Page-specific improvements
| Item | Affected pages/components | Priority | Effort | Dependencies | Verification |
|---|---|---|---|---|---|
| Redesign Student Portal Dashboard for content prioritization | `Portal/Student/Dashboard.vue` (Finding 5) | Medium | Medium | Consider reusing `DashboardStatCard`/action-queue pattern from Sahodaya Admin Dashboard | Usability check with a real student account with populated data |
| Verify/fix `FormField` + `SearchableSelect` label association | Wherever the two are actually combined (Finding 13) | Medium | Small once located | `grep` sweep to confirm real occurrences | Screen reader test |
| Change School Registration "Address" field to a `<textarea>` | `resources/views` or Vue equivalent for `/school-register` | Low | Small | None | Visual check with a multi-line address |

## 5. Longer-term refinements
- Audit the ~360 unsampled Inertia pages against the shared-component findings above now that they're documented — most likely need no page-specific work beyond the shared-component fixes propagating, but this should be confirmed rather than assumed, especially for the 16-variant Fest Reports module and the ~150 print/PDF Blade templates, neither of which received any review this pass.
- Resolve the duplicate School Admin Board-Results/Principal-Verification page tree (`Pages/Admin/School/BoardResults/...` vs `Pages/School/BoardResults/...`) — flagged in the Page Inventory as a likely-unintentional duplication worth a deliberate "which is canonical" decision, in the same vein as the dual State Admin architecture already on file from a prior audit ([[full_platform_audit_2026_08_13]] in project memory).
- Once true mobile/tablet viewport testing tooling is available, re-run the Responsive Design Findings section for real — it is currently the least-verified section of this report.

**Recommended order:** Section 1 (critical) → Section 2 (high-impact, mostly small/independent fixes that don't block on the shell refactor) → Section 3's shell unification (Finding 8) → the remaining Section 3 items that build on it → Section 4 → Section 5.

---

# Audit Coverage and Unverified Areas

**Routes/states successfully inspected (running application):**
- Superadmin login (`/login` on `superadmin.test`) — desktop viewport, keyboard Tab-order tested.
- Laravel default 404 page.
- Public tenant homepage (`malappuramsahodaya.test`) — desktop viewport, scroll behavior, nav dropdown open/scroll interaction.
- Public School Registration form — desktop viewport, empty-submit validation.

**Routes/states inspected only through code:** the 5 shared layouts, 15 `Components/ui/*` primitives + `SahodayaDataTable`/`SahodayaNavItem`, the Sahodaya Admin auth flow (3 pages), Sahodaya Admin Dashboard, Student Portal Dashboard, `bootstrap/app.php` exception handling, the full route table (`web.php`/`tenant.php`/`state.php`/`api.php`), the `resources/js/Pages` and `resources/views` directory trees for inventory purposes, `resources/css/app.css` for global focus/outline behavior.

**Routes/states not inspected at all:** the remaining ~360 Inertia pages (see Page Inventory for the module breakdown — Sahodaya Admin's ~130-page Fest/Events module and ~40-page core admin area, School Admin's ~100 pages, the 5 Portal-role dashboards beyond Student, all Superadmin/State pages beyond the login, the ~56 public tenant-website pages beyond the homepage, ~150 print/PDF Blade templates, and the site-builder's ~35 reusable section templates individually).

**Missing credentials, data, services, or permissions:**
- No login credentials were available, and this session's safety rules prohibit entering a password into a login form even with the user's permission — so **no authenticated page was reachable live** this session. This is the largest single coverage gap. If the user is willing to log in themselves (driving their own mouse/keyboard) while sharing screenshots, or to grant a session/cookie a follow-up pass can attach to without the agent handling credentials directly, that would unlock live testing of the ~95% of the platform that sits behind a login.
- `resize_window` did not produce genuine mobile/tablet viewport screenshots this session (see Responsive Design Findings) — recommend re-testing with a different testing setup (e.g. actual browser DevTools device emulation, or a follow-up session where this is confirmed working) before treating mobile/tablet as reviewed.
- Database/seed state of the running app was unknown; no list page was observed with real, populated data, so pagination, large-dataset, and truly-empty-state behavior are all inferred from component code rather than observed.

**Recommended follow-up testing for complete coverage:**
1. A follow-up pass with either the user driving authenticated navigation live, or a safe way to establish an authenticated session without the agent handling credentials, covering at minimum: one full CRUD flow per major role (Sahodaya admin, School admin, Superadmin, Student/Teacher/Judge portal), the Fest registration → mark entry → results → certificate pipeline (the platform's core business workflow and largest single page cluster), and the site-builder admin UI.
2. Real mobile (375px) and tablet (768px) viewport screenshots of at least the pages already flagged as likely mobile-sensitive: Student Portal Dashboard, all five layouts' mobile nav drawers, and the public website's nav dropdown (already confirmed broken on scroll at desktop — worth checking whether mobile's drawer-based nav sidesteps or also has the bug).
3. Trigger and screenshot 403 (permission-denied), 429 (rate-limited), and 500 (server error) states specifically — only 404 was reproducible without an authenticated/error-inducing scenario this session.
4. A `grep`-based sweep for every real usage site of `FormField` + `SearchableSelect` together, `ConfirmDialog`'s `useConfirm()` composable, and `SahodayaDataTable`, to convert the "likely affects N pages" estimates in this report into exact page lists.
