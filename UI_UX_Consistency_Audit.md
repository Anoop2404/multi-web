# UI/UX Consistency Audit — Sahodaya & School Admin Portals

Scope: `resources/js/Pages/Admin/Sahodaya/**` (181 Vue files, ~35 feature areas), `resources/js/Pages/Admin/School/**` (92 Vue files, ~15 feature areas), shared `resources/js/Layouts/**` and `resources/js/Components/**`. A third, structurally parallel namespace, `resources/js/Pages/Admin/Portal/**` (50 files: Teacher/Student/Judge/FestCoordinator/FestOps/HouseAdmin/Group), exists outside the stated scope but reuses the same terminology and is worth folding into a future pass since it duplicates Fest/Mcq/Sports concepts under yet another naming scheme.

This is an audit and plan only — no redesign work has been done.

---

## 1. Executive Summary — Top 5 Issues

Ranked by (a) breadth (how many pages/modules it touches) and (b) how confusing it would be to a non-technical school-office user.

**1. There is no shared confirmation, table, or modal component in real use — every page reinvents them.**
`ui/ConfirmDialog.vue` exists but has **zero consumers**; 83 files instead call the browser's native `confirm()` for destructive actions (`School/Students/Index.vue:476,741`, `Sahodaya/SportsAgeGroups/Index.vue`, `Tenants/Index.vue`, `Billing/Index.vue`, and 79 more). `Components/SahodayaDataTable.vue` exists but only **2 of 188 files with a `<table>`** use it (`School/Students/Index.vue`, `Sahodaya/Training/Registrations.vue`) — the other ~186 hand-roll markup. `ui/ActionsMenu.vue` has **exactly 1 consumer**. No generic `Modal.vue` or `Pagination.vue` exists anywhere; 24 files hand-roll raw modal boilerplate (`fixed inset-0 z-50` overlays) instead. This is the single highest-leverage fix: three components already built, essentially unused.

**2. Approve/reject review workflows — the highest-stakes screens in the app — have no shared pattern and are already drifting from each other.**
Seven review-type pages (`Sahodaya/Events/SubstitutionReview.vue`, `ClashReview.vue`, `Documents/Review.vue`, `BoardResults/Verification.vue`, `Students/Verification.vue`, `Teachers/Verification.vue`, `Fest/Payments/Index.vue`) implement rejection three different ways: optional `window.prompt()` reason, required `window.prompt()` reason, and a proper modal with `<textarea>`. Approve is sometimes gated by `confirm()`, sometimes not. Worst of all, `Students/Verification.vue` and `Teachers/Verification.vue` are near-identical hand-copies of each other rather than a shared component — Students has a bulk-reject modal that Teachers lacks, meaning the two are actively diverging as separate codebases with separate bugs. This is a decision a school-office user watches nervously (did my rejection actually require me to explain why? did clicking approve just fire immediately?) — inconsistency here is the most anxiety-inducing kind.

**3. List/index pages — the single most common page type — use three unrelated filtering and pagination mechanisms.**
`School/Students/Index.vue` uses debounced auto-apply filters + a shared data table; `School/Teachers/Index.vue` requires a manual "Apply" submit with a hand-rolled table and no sort affordance; `Sahodaya/Circulars/Index.vue` filters an entire un-paginated client-side array with a duplicated mobile-card layout not used anywhere else. `Sahodaya/Mcq/Index.vue` has no filter bar at all. Across ~35+ Sahodaya and ~15 School feature areas, list pages are the most-repeated task type, so this pattern gap compounds — a school-office user who learns "type to filter" on Students has to relearn "click Apply" on Teachers.

**4. No toast/flash system exists at all — the app has no consistent way to tell a user "it worked" or "it failed."**
A grep for `useToast`, `$toast`, `Toast.vue`, `notify(` across the whole `resources/js` tree returns **zero files**. A page-level flash-banner mechanism exists but is used in only **6 of ~273 pages**. Combined with 85 files using `alert()`/`confirm()`, most save/delete/approve actions give the user no feedback beyond a full page reload — for a less technical school-office audience, silence after clicking "Save" reads as "did that work?" every single time.

**5. Mobile responsiveness is close to absent, and School-portal users are the most likely to be on a phone.**
188 files contain a `<table>`; only 75 wrap it in `overflow-x-auto`, and **zero files anywhere** implement a card-based mobile fallback. Roughly 60% of table-bearing pages have no horizontal-scroll handling at all — meaning on a 375px screen the table is simply unusable (not scrollable, not stacked). This is compounded by icon-only buttons almost never carrying an accessible label (`aria-label` appears only 19 times across 7 of ~273 files) — the two problems stack worst for exactly the audience (School staff, on phones) the product owner is most worried about.

---

## 2. Navigation & Information Architecture Audit

**Nav config files** (fully read): `resources/js/support/sahodayaAdminNav.js`, `resources/js/support/schoolAdminNav.js`, each exporting a main nav plus several "scoped" sub-navs (`sahodayaMembershipScopedNav`, `sahodayaMcqExamScopedNav`, `schoolFestScopedNav`, etc.). Several more portal-specific nav files exist for other user types (judge, fest coordinator, teacher, student) that were out of scope but follow the same structure.

**Orphaned pages (no sidebar entry anywhere):**
- `Sahodaya/BoardResults/Masters.vue`, `SubjectMeritRegister.vue`, `ExcellenceReport.vue` — only `verification` and `reports` sub-pages are linked from the sidebar.
- `Sahodaya/Website/FormSubmissions.vue` — Forms/Sites/Domains are linked, submissions are not.
- `Sahodaya/Kalotsav/SchoolRounds.vue` — a standalone Kalotsav sub-feature with no sidebar link at all.

These are reachable only by URL guessing or deep-linking from another page — for office staff, that means "the feature exists but nobody finds it."

**Depth-to-task check** (5 common school-admin tasks, from the School sidebar):
- View students — 1 click. OK.
- Register for an event — 1-2 clicks (program page, then in-page workflow tabs). OK.
- **Upload payment proof — 3 levels deep**: Dashboard → Membership → Annual Registration → Payment tab. The payment step only appears in the *scoped* sub-nav once you're already inside Registration; the main sidebar shows just one "Annual Registration" link with no indication a payment step is nested inside. Flagged as exceeding the 2-level threshold.
- Check membership status — 2 clicks, borderline OK (same Annual Registration page).
- Add a teacher — 1 click to list + 1 in-page action. OK.

**Same concept, different labels** (nav vs. scoped nav vs. page title — exact strings):
- `"Annual Registration"` (main nav, Title Case, `schoolAdminNav.js:307`) vs. `"Annual registration"` (scoped nav, sentence case, `schoolAdminNav.js:89`) vs. `title="Annual Registration"` on `School/Registration/Index.vue:2`. Same feature, three casings.
- `School/Registration/Profile.vue:2` is titled `"Registration Details"` on-page, but is called `"Profile & account"` in the scoped nav — two different labels for one destination.
- `Sahodaya/Membership/Payments.vue:8` is titled "Membership payments," while the nav item pointing at it (`sahodayaAdminNav.js:363`) is labeled `"Membership fees"` — "fees" vs. "payments" for the same page.
- `Sahodaya/Membership/Submissions.vue:2,7` is titled "Annual submissions," while the nav item pointing at it (`sahodayaAdminNav.js:371`) is labeled `"Student counts"` — a user searching the sidebar for "counts" lands on a page that never uses that word.

A `BreadcrumbTrail.vue` component and a `PageHeader.vue` "eyebrow" mini-breadcrumb prop both exist, but the eyebrow text isn't standardized against the nav section label it belongs to (e.g., `eyebrow="Review"` on a page that lives under the "Membership" or "Fest & events" nav section depending on which program it's for).

---

## 3. Page-Pattern Consistency Audit

### Layouts
5 layout components exist: `AdminLayout.vue`, `SahodayaAdminLayout.vue`, `SahodayaEventsLayout.vue`, `SchoolAdminLayout.vue`, `PortalLayout.vue`. School is fully consistent (92/92 files use `SchoolAdminLayout`). Sahodaya is split: 91 files use `SahodayaAdminLayout`, 76 use `SahodayaEventsLayout` — but several **non-event master-data pages incorrectly use the Events layout**: `Sahodaya/Certificates/Templates.vue`, `Sahodaya/SportsAgeGroups/Index.vue`, `Sahodaya/StateRemittances/Index.vue`, `Sahodaya/TaxonomyMasters/Index.vue`, `Sahodaya/CompetitionTypes/Index.vue`. `Portal/Welcome.vue` is the one Portal page that doesn't use `PortalLayout` at all.

### Lists/Index pages
Three incompatible filter mechanisms observed: debounced auto-apply (`School/Students/Index.vue`, via `useDebouncedInertiaFilters`), manual "Apply" submit button (`School/Teachers/Index.vue`), and full client-side filtering with no server round-trip or pagination (`Sahodaya/Circulars/Index.vue`). `Sahodaya/Mcq/Index.vue` has no filter bar at all. Table implementation: shared `SahodayaDataTable` (2 files) vs. hand-rolled `<table>` (186 files). Pagination: shared component delegation (2 files), hand-coded Link-based pager (`School/Teachers/Index.vue`), or none (`Sahodaya/Circulars/Index.vue`, fully client-side). Empty states: shared `<EmptyState>` component in some places (`Sahodaya/Circulars/Index.vue`), a bare `<tr><td colspan>` in others (`School/Teachers/Index.vue`), and no true empty state at all in `School/Students/Index.vue`. Row actions vary from 2 text links (Circulars: "View"/"Delete") to 3 (Teachers: "Edit"/"Deactivate"/"Credentials").

### Create/Edit forms
Save/cancel button text has at least 7 different strings for the same action across sampled pages: "Save Changes," "Add Staff Member," "Save Settings," "Save student," "Save" (modal), "Create Event," and — the one file that does it well — `Sahodaya/Certificates/Templates.vue`, which dynamically swaps between "Save template" / "Update template" based on create-vs-edit mode.

**Existing-file-upload preview** (the bug class flagged from this session's Certificate Template fix): every sampled Edit page that has a file field *does* correctly show a preview of the existing file (`School/Staff/Edit.vue:48-56`, `School/News/Edit.vue:35-44`, `School/Events/Edit.vue:41-50`, `School/Settings/Index.vue:41-51`, `Sahodaya/Certificates/Templates.vue:54-58,220-236,246-250`, `Sahodaya/Membership/Settings.vue:124-139`). No live recurrence of the bug was found in this sample. However, **no shared "existing-file preview" component exists** — every page reimplements the preview box with different sizing/wrapper conventions (`h-16 w-auto` vs `h-10` vs `h-16 w-16 rounded-full`), so this remains a latent risk: any new Edit page built by copying the wrong template could reintroduce the exact bug that was just fixed.

Photo-upload UX also diverges sharply: `School/Students/Create.vue` uses a dedicated `<ProfilePhotoCropper>` with required client-side validation, while `School/Teachers/Index.vue` (inline add-teacher form) and `School/Staff/Create.vue` use a plain `file:`-styled `<input type="file">` with no cropper — two different idioms for the same "person photo" field.

### Approve/reject/review pages
See Executive Summary #2 for the full breakdown. Summary table:

| Page | Reject reason UI | Reason required? | Confirm before approve? |
|---|---|---|---|
| `Sahodaya/Events/SubstitutionReview.vue` | `window.prompt()` | No | No |
| `Sahodaya/Events/ClashReview.vue` | `window.prompt()` | No | No (labeled "Resolve" not "Approve") |
| `Sahodaya/Documents/Review.vue` | `window.prompt()` | Yes | No |
| `Sahodaya/BoardResults/Verification.vue` | `window.prompt()` | Yes | No (3-stage: Verify/Approve/Publish) |
| `Sahodaya/Students/Verification.vue` | Modal + `<textarea>`, plus separate bulk-reject modal | Yes | `confirm()` on bulk-verify |
| `Sahodaya/Teachers/Verification.vue` | Modal + `<textarea>` (single only, no bulk-reject) | Yes | `confirm()` on bulk-verify |
| `Sahodaya/Fest/Payments/Index.vue` | `window.prompt()` | No | Yes, `confirm()` |

### Settings/tabbed pages
Three incompatible architectures for the same "tabbed settings" task: `Sahodaya/Membership/Settings.vue` uses a hand-rolled `ref`-based tab state persisted to `sessionStorage`, with 11 separate `<form>`s in one 1450-line file; `Sahodaya/Events/Settings.vue` uses a dedicated `EventSettingsSubNav` component with 14 tabs split into separate imported files sharing state via `provide/inject`; `School/Settings/Index.vue` has no tabs at all — one long scrolling form. None of the three protect against losing unsaved changes when switching tabs.

### Dashboards
The most consistent pair in the audit. `Sahodaya/Dashboard.vue` and `School/Dashboard.vue` share `dash-hero`/`dash-badge` header classes, `<DashboardStatCard>`, `<QuickActionCard>`, and `activity-timeline` classes. Minor divergence: Sahodaya gates sections behind a `canSee()` staff-permission helper that School's dashboard has no equivalent of, and Sahodaya defines a one-off inline `h()`-rendered `ActionBanner` instead of a reusable SFC.

### Reports (Fest Reports Hub)
The best-built cluster in the app. All ~15+ sampled report pages share `<ReportsSubNav>`, an `<EventPageActivityLog>` footer, and a consistent `PageHeader` + export-action pattern. Only inconsistency: export button label text varies ("Download PDF ↓" / "Export Excel ↓" / "Export spreadsheet ↓"). This cluster reads as having been built as one coherent batch — a good reference for what "done right" looks like elsewhere.

---

## 4. Terminology Audit

- **"Sahodaya"** is used consistently as the tenant/org concept throughout the UI — no "Association" or "Cluster" synonym was found in actual product text (those terms only appear in internal/planning documentation, not the app itself). No action needed here.
- **"Program" / "Event" / "Fest" / "Kalotsav"** are used interchangeably for overlapping concepts, sometimes on the same page tree: the nav section is literally named `"Fest & events"` (listing Kalotsav, Sports Meet, Kids Fest as individual links plus a generic "All events" and "Program calendar"); `Sahodaya/Events/ProgramIndex.vue` lives under `Events/` but is named `Program*`; `Sahodaya/Training/*` uses "program" for training courses while `Sahodaya/Events/*` uses "event" for fest competition items. A school admin cannot tell from folder or file naming alone whether "Program" means a Fest competition category, a training course, or an entire Kalotsav.
- **"Registration"** legitimately means four different things — annual membership registration (`School/Registration/Index.vue`), fest/event registration (`School/Events/Registration.vue`, `Sahodaya/Events/Registrations.vue`), training-program registration (`Sahodaya/Training/Registrations.vue`), and exam/sports item registration (`School/Mcq/ExamDetail.vue`, `School/Sports/EventItemRegistration.vue`). Each page title itself disambiguates reasonably well (prefixed with the specific event/program name), but the **sidebar label is usually just "Registration"/"Registrations" with no qualifier** — disambiguation happens one click later than it should.
- **Status vocabulary is invented independently per module**, not shared from one enum: `Sahodaya/AcademicYears/Index.vue:280` uses `closed`; `Sahodaya/Events/Registrations.vue:577` uses `withdrawn`; `StateRemittances/Index.vue:137` uses `{pending, submitted, verified, rejected}` where "verified" is the terminal-success state; other modules use "approved" as the terminal-success state instead. There is no shared status enum or color map, so the same color can mean different lifecycle stages depending on which module you're in.

---

## 5. Feedback & Error-Handling Audit

- **No toast/notification system exists anywhere in the codebase** (`useToast`, `$toast`, `Toast.vue`, `notify(` — zero matches across all of `resources/js`).
- A flash-banner mechanism (`usePage().props.flash`) exists but is used in only 6 of ~273 pages: `School/Settings/Index.vue`, `School/Students/Show.vue`, `School/Training/Index.vue`, `School/Teachers/Index.vue`, `Components/school/StudentBulkUploadModal.vue`, `Auth/VerifyEmail.vue`.
- `alert()`/`confirm()` browser dialogs appear in 85 files, including for genuinely destructive, irreversible actions (`School/Students/Index.vue:476,741` — "Assign formatted student IDs," "Withdraw student"). A purpose-built `ui/ConfirmDialog.vue` component exists and is used nowhere.
- Field-level validation errors are handled well where sampled: `School/Students/Create.vue` renders a `<p v-if="form.errors.X" class="form-error">` immediately after each field (lines 32, 37, 43, 55, 59, 65) — a solid reference pattern, though it wasn't verified across every form in the app.
- Silent-failure backend patterns: `app/Services/Notifications/SahodayaAdminNotifier.php:15` has `catch (\Throwable) { return; }` with no logging and no user-facing surface at all — a notification-send failure simply vanishes. `app/Http/Controllers/Admin/TenantController.php:238,817,847,882,920` has multiple similar silent `catch (\Throwable)` blocks. By contrast, `app/Http/Controllers/Public/SchoolApplicationController.php:105-123` does this correctly: it catches the mail failure, calls `report($e)` to log it, sets a `$mailFailed` flag, and shows the user an explicit message ("...your account was created, but we could not send email..."). That file is the reference implementation the others should be brought up to.

---

## 6. Mobile/Responsive Audit

188 files contain a `<table>`; only 75 wrap it in `overflow-x-auto` (100 occurrences total) — meaning roughly 113 of 188 table pages (~60%) have no horizontal-scroll handling at all. A search for any card-based mobile fallback pattern (`hidden md:table`, `sm:hidden` + card markup) returned **zero matches anywhere** in either portal — there is no mobile-card-view pattern in use anywhere in the codebase. `Sahodaya/Circulars/Index.vue` is the one exception that builds its own duplicated mobile card layout, but that pattern isn't shared or reused elsewhere. Given the product brief's own note that School-portal users skew mobile, and School has the same ~60% gap as Sahodaya, this is a broad, unaddressed risk rather than a handful of exceptions — dozens of the `Sahodaya/Events/Reports/*` one-off report tables in particular have no wrapper.

---

## 7. Accessibility Spot-Check

- **Status badge contrast**: sampled combos (`bg-green-100 text-green-700`, `bg-amber-100 text-amber-700`, `bg-red-50 text-red-600`, etc.) generally read as WCAG-AA-safe. One combo is borderline and worth a manual contrast check: `bg-gray-100 text-gray-400`, used for "inactive/hidden" badges in `School/SiteBuilder.vue:243`, `Sahodaya/SiteBuilder.vue:385`, `School/JobVacancies/Index.vue:67`, `SkinPresets/Index.vue:27`.
- **Icon-only buttons**: `aria-label` appears only 19 times across 7 of ~273 files (`School/Registration/Counts.vue`, `School/Students/Show.vue`, `Portal/Teacher/QuestionBankShow.vue`, `Sahodaya/Mcq/Attendance.vue`, `Sahodaya/Mcq/Results.vue`, `Sahodaya/Events/Settings/Tabs/GradesTab.vue`, `Sahodaya/Users/Index.vue`). A `title=` attribute is used more often as a partial substitute (e.g. `School/Students/Index.vue:141,154`), but `title` has no reliable screen-reader guarantee and doesn't help touch/mobile users. No `sr-only` span pattern was found anywhere. The large majority of icon-only buttons across both portals have no accessible name at all.

---

## 8. Module-by-Module Redesign Priority List

Classification: **Rebuild** (pattern actively confusing/broken or duplicated-and-diverging), **Restyle** (functionally fine, visually/structurally inconsistent), **Leave alone** (already matches target pattern).

### Sahodaya (35 areas)

| Module | Classification | Why |
|---|---|---|
| Students/Teachers Verification | **Rebuild** | Copy-pasted, already diverging (bulk-reject present on one, absent on other); highest-stakes workflow in the app |
| Membership Settings | **Rebuild** | 1450-line single-file monolith, hand-rolled tab/session-storage state, no unsaved-changes protection |
| Mcq (exam list + related) | **Rebuild** | No filter bar at all on the index; inconsistent with every other list page |
| Circulars | **Rebuild** | Fully client-side, unpaginated, one-off mobile card layout not shared |
| Substitution/Clash Review | **Rebuild** | `window.prompt()` for reasons, no confirm-before-approve, inconsistent verb ("Resolve" vs "Approve") |
| Document Review | **Restyle** | Reason required (good) but still `window.prompt()` instead of a proper modal |
| BoardResults Verification | **Restyle** | 3-stage workflow (Verify/Approve/Publish) is a legitimately different shape, but still uses `window.prompt()` |
| Events/Reports (Fest Reports Hub, ~30 pages) | **Leave alone** | Best-built cluster in the app — shared subnav, activity log, consistent header/export pattern |
| Events Settings | **Restyle** | Architecturally sound (provide/inject, split tab files) but inconsistent with Membership Settings' approach — pick one pattern |
| Dashboard | **Leave alone** | Already shares components with School Dashboard |
| Finance (Hub, Receivables, Payables, UnifiedPayments, ReceiptEmailReport, EmailDelivery) | **Restyle** | Functional, but each of the 6 pages likely has its own table/filter treatment (not deeply sampled — recommend a follow-up pass) |
| Catalog (Hub, Heads, Master, List, Assign) | **Restyle** | Uses hand-rolled pagination (one of only 6 files that do); align to shared table/pagination |
| Certificates/Templates | **Leave alone** | Best-in-class file-upload-preview reference; dynamic Save/Update label — use as the template for other Create/Edit forms |
| SportsAgeGroups, StateRemittances, TaxonomyMasters, CompetitionTypes | **Restyle** | Master-data pages incorrectly using `SahodayaEventsLayout` instead of `SahodayaAdminLayout` — layout scoping fix, not a content rebuild |
| BoardResults/Masters, SubjectMeritRegister, ExcellenceReport | **Restyle** (+ nav fix) | Functional but orphaned from nav entirely — add nav entries before any visual work |
| Website (Domains, Sites, Forms, FormSubmissions) | **Restyle** | FormSubmissions orphaned from nav; heavy hardcoded-hex usage in SiteBuilder |
| Remaining singleton areas (Teachers, AcademicYears, DisplayScreens, Regions, Calendar, Setup, Kalotsav, NotificationTemplates, SportsAgeGroups, Auth, OfficeBearers, PublicContent, Users, Documents, Fest/AppealsQueue) | **Restyle** (default) | Not individually sampled in depth; treat as restyle-by-default pending a follow-up pass, except Auth (see below) |
| Auth (Login, SchoolLogin, SuperadminLogin, PortalLogin, ResetPassword, ForgotPassword) | **Rebuild (visual only)** | The only pages with per-page `<style>` blocks and the heaviest hardcoded hex-color usage (20-25 per file) — visually disconnected from the rest of the design system, though functionally fine |

### School (15 areas)

| Module | Classification | Why |
|---|---|---|
| Students | **Leave alone** | Uses the shared `SahodayaDataTable`, debounced filters, cropper-based photo upload — the best-built list/form pair in School |
| Teachers | **Rebuild** | Hand-rolled table, no sort affordance, manual-Apply filters, plain file input instead of cropper, inline "add many" toggle instead of a modal — every pattern differs from Students despite being the same task shape |
| Staff | **Restyle** | Functionally fine (correct upload preview) but plain file input, different Save-label convention than Students |
| Registration (Annual Membership) | **Restyle** (+ nav fix) | Payment step buried 3 levels deep from main nav; label drift ("Registration Details" vs "Profile & account" vs "Membership Payment" vs "Membership payment") |
| Events (incl. 27 Report* sub-pages) | **Rebuild** | Largest School folder (40 files); Registration.vue uses different filter idiom than Students; Report* pages not verified against a shared report-shell — recommend aligning to the Sahodaya Fest Reports Hub pattern, which is proven |
| Settings | **Restyle** | No tabs at all (one long form) — fine functionally, but a third distinct "settings page" architecture in the app; needs to converge with Sahodaya's approach once one is chosen |
| Mcq | **Restyle** | Align to whatever list-page standard is chosen; not deeply sampled |
| Sports (ItemRegistrationEvents, EventItemRegistration, MyEvent, SubmitWinners) | **Restyle** | Registration-flavored naming overlaps with Events/Registration — terminology check needed |
| News, Gallery, Downloads, JobVacancies, Testimonials, Alumni, Houses, Enquiries, Contact | **Restyle** (default) | CMS-style pages, correct upload-preview pattern observed in News — likely consistent with each other; light pass only |
| Dashboard | **Leave alone** | Shares components with Sahodaya Dashboard |
| Payments | **Restyle** | Has the `overflow-x-auto` wrapper (good), otherwise not deeply sampled |
| Users | **Restyle** | Not deeply sampled |
| Fest | **Restyle** | Overlaps with Events terminology; needs disambiguation pass alongside Events |
| Imports, Setup, Calendar, Documents, Circulars | **Restyle** (default) | Not deeply sampled; treat as light pass pending follow-up |

---

## 9. Proposed Shared Component/Pattern Library

A short, concrete list — not a full design system — of what every page should be converted to use going forward. Several of these components already exist in the codebase; the fix is adoption, not invention.

1. **One table component: `Components/SahodayaDataTable.vue`.** Already built, already handles sorting and pagination slots. Currently used by 2 of 188 tables. Every list page should migrate to it — this single change fixes the filter/sort/pagination/empty-state inconsistency in Section 3 in one motion.
2. **One modal component.** No generic one exists — build a `Modal.vue` (or promote one of the 5 existing bespoke modals to generic) and retire the 24 files of hand-rolled `fixed inset-0 z-50` boilerplate.
3. **One confirm-destructive-action pattern: `ui/ConfirmDialog.vue`.** Already built, zero consumers. Replace all 83 `confirm()`/`alert()` call sites with it — highest-leverage fix in the whole audit given it's a one-line swap per file once the component is wired up.
4. **One date formatter: `support/calendarDates.js`'s `formatDateTime()`.** Already built, used in 32 of 71 files that format dates; the other 39 use raw `toLocaleDateString`. Standardize on the existing helper.
5. **One status badge component with a fixed color map.** `ui/TrackStatusPill.vue` exists but is imported directly in only 4 files; 86 files build ad hoc status coloring inline. Define one enum of lifecycle states (pending/verified/approved/rejected/completed/cancelled/withdrawn/closed — consolidate the module-specific variants found in Section 4) with one color per state, and route every status display through it.
6. **One file-upload-with-existing-preview component**, generalizing the pattern already proven correct in `Sahodaya/Certificates/Templates.vue` (preview box + "Leave blank to keep current X" hint + dynamic Save/Update label). This retires the risk noted in Section 3 that every Edit page currently reimplements the preview from scratch with different sizing conventions.
7. **One toast/flash notification system.** None exists today. Introduce a single `useToast()`/`<ToastHost>` pattern and retire both the ad hoc 6-file flash-banner usage and the `alert()` calls that currently stand in for success/error feedback.
8. **One review/approve-reject shell**, modeled on the more complete `Sahodaya/Students/Verification.vue` implementation (modal + textarea, required reason, bulk actions with `confirm()`) rather than the `window.prompt()` pattern used in 5 of 7 review pages. Should be the single component `Teachers/Verification.vue` and future review pages import, ending the current copy-paste drift.
9. **One tabbed-settings shell.** Pick one of the two existing architectures (recommend the `Events/Settings.vue` provide/inject + split-tab-file approach, since it scales better than a 1450-line monolith) and add unsaved-changes-on-tab-switch protection once, at the shell level.
10. **One responsive table treatment**: either enforce `overflow-x-auto` universally as a floor, or — better, given School users skew mobile — build a card-view fallback into the shared `SahodayaDataTable` component itself so every migrated list page gets mobile support for free instead of needing a bespoke mobile layout per page (as Circulars currently has).
11. **Layout scoping cleanup**: move the 5 misassigned master-data pages (SportsAgeGroups, StateRemittances, TaxonomyMasters, CompetitionTypes, Certificates/Templates) from `SahodayaEventsLayout` to `SahodayaAdminLayout` — a small, mechanical fix that removes a source of visual "why does this admin page look like an event page" confusion.
