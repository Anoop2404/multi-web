# Deep UI/UX Audit Prompt — Sahodaya Admin & School Admin Portals

Use this prompt with an agent that has read access to the `multi-web` codebase
(`resources/js/Pages/Admin/Sahodaya/**`, `resources/js/Pages/Admin/School/**`,
`resources/js/Layouts/**`, `resources/js/Components/**`). It is scoped to be run
by an AI coding agent (e.g. as a Task/subagent) rather than a human, since the
surface area is ~180 Sahodaya-admin pages and ~90 School-admin pages — too many
to eyeball one at a time. The audit works by pattern-sampling across modules
rather than reading every file.

---

## Context to give the agent

This is a multi-tenant Laravel + Inertia/Vue platform for CBSE Sahodaya
associations (clusters of schools) in Kerala. There are two portals sharing
one codebase and one design system:

- **Sahodaya admin** (`resources/js/Pages/Admin/Sahodaya/`) — used by Sahodaya
  office staff. ~181 Vue pages across ~35 feature areas: Membership, Students,
  Teachers, Training, Fest/Kalotsav/Sports events, Mcq (Talent Search exams),
  Certificates, Notification Templates, Regions, Finance/Ledger, Reports,
  Website/Circulars, Settings, Users.
- **School admin** (`resources/js/Pages/Admin/School/`) — used by individual
  school staff (often less technical, high turnover). ~92 Vue pages: Students,
  Teachers, Registration (annual membership), Events/Fest, Training, Mcq,
  Payments, Achievements, Alumni, Staff, Downloads, Gallery, News, Job
  Vacancies, Testimonials.

The product owner's complaint, verbatim: pages "feel unique, not the same for
user flows" and are "hard for my users to maintain or use." The goal of this
audit is to find every place where the same *kind* of task (list + filter,
create/edit a record, upload a file, approve/reject something, show a status,
navigate between related pages) is solved with a *different* pattern in
different modules — and to produce a prioritized, concrete redesign plan, not
a vague list of impressions.

---

## What "done" looks like

A written report (Markdown) with these sections, each with concrete file
paths and screenshots/code snippets as evidence — no vague claims like "some
pages are inconsistent" without naming which ones:

1. **Executive summary** — top 5 issues ranked by (a) how many pages they
   touch and (b) how confusing they'd be to a non-technical school-office
   user.
2. **Navigation & information architecture audit**
3. **Page-pattern consistency audit** (lists, forms, modals, uploads, status
   display, empty states, bulk actions, filters)
4. **Terminology audit**
5. **Feedback & error-handling audit**
6. **Mobile/responsive audit**
7. **Accessibility spot-check**
8. **Module-by-module redesign priority list** (which of the ~35 Sahodaya
   areas and ~15 School areas most need a rebuild vs. a light pass vs. leave
   alone)
9. **Proposed shared component/pattern library** — the concrete set of
   reusable patterns that, if enforced, would fix most of the above at once

---

## Step-by-step audit method

### 1. Map the terrain first (don't read pages yet)

- `find resources/js/Pages/Admin/Sahodaya -name "*.vue" | sort` and same for
  `School` — build a full inventory grouped by top-level feature folder.
- `find resources/js/Layouts -name "*.vue"` — list every layout component
  (e.g. `SahodayaAdminLayout.vue`, `SchoolAdminLayout.vue`,
  `SahodayaEventsLayout.vue`) and which pages use which. Flag any page that
  uses a layout only it uses, or that doesn't use a shared layout at all.
- `find resources/js/Components -name "*.vue"` — list shared UI components
  (`SahodayaDataTable`, `FormField`, `FormGrid`, `PageHeader`, `ActionsMenu`,
  modals, etc.) and grep how many pages actually use each one vs. how many
  hand-roll their own version of the same thing (e.g. search for
  `<table class="data-table"` used directly vs. via a shared table
  component; search for raw `<div class="fixed inset-0 z-50` modal
  boilerplate repeated instead of a shared `Modal.vue`).

### 2. Sample 3-5 pages per feature cluster, not all 273

Group the ~35 Sahodaya + ~15 School feature areas into clusters by task type,
and read 3-5 representative pages per cluster (pick the newest-looking and
oldest-looking file by git log date to see how patterns drifted over time):

- **List/index pages** (Students, Teachers, Registrations, Payments,
  Circulars, Mcq exams, Fest registrations, etc.) — compare: filter bar
  layout, pagination component, bulk-action placement, empty-state copy,
  column sort affordance, row-action button style/position, whether search
  is instant/debounced or requires a submit.
- **Create/edit forms** (Student, Teacher, Event, Training Program,
  Certificate Template, Notification Template) — compare: single-page form
  vs. modal vs. wizard/tabs; field-level validation error style and
  placement; required-field marking; save/cancel button placement and
  labels ("Save" vs "Update" vs "Submit" vs "Save changes"); how file
  uploads with an existing value are shown (this was a real bug found this
  session — check whether other modules have the same "no preview of
  existing upload" gap: Teacher photos, School logos, Circular attachments,
  Gallery images, etc.).
- **Approve/reject/review pages** (Fest Registration Review, Payment
  Verification, Submission Review, Clash Review, Substitution Review,
  Document Review, Teacher Verification, Student Verification) — compare:
  is the approve/reject action a button, a dropdown, a modal with a reason
  field, or inline; is bulk approve/reject available uniformly; is the
  rejection-reason requirement consistent.
  is bulk approve/reject available uniformly; is the rejection-reason
  requirement consistent.
- **Status/badge display** — grep for status pill/badge class patterns
  (`status-pill`, `bg-green-100 text-green-700`, etc.) across registration
  status, payment status, verification status, event status. Are the same
  five or six states (pending/approved/rejected/completed/cancelled/draft)
  styled with the same colors everywhere, or does green mean "approved" on
  one page and "active" on another with a different shade?
- **Settings/tabbed pages** (Membership Settings, Event Settings, Sahodaya
  Settings) — compare tab navigation implementation (query param vs. local
  state vs. separate routes) and whether unsaved-changes are handled
  consistently when switching tabs.
- **Dashboards** (`Dashboard.vue` in both Sahodaya and School) — compare
  card/stat-tile patterns, whether both dashboards share a visual language
  or look like different products.
- **Reports pages** (the Fest Reports Hub alone has 25-30 report pages) —
  are they generated from one shared report-shell component or is each one
  a bespoke page? This alone is probably the single biggest consistency
  risk given the count.

### 3. Cross-cutting checks (grep-driven, fast, high-signal)

Run these and record raw counts — they're objective evidence, not opinion:

- Count of distinct button classes in use (`btn-primary`, `btn-secondary`,
  `btn-ghost`, one-off `class="px-4 py-2 bg-..."` inline Tailwind buttons
  that don't use the shared classes at all).
- Count of distinct date-formatting approaches (`toLocaleDateString`,
  a shared `formatDate` helper, raw ISO strings shown unformatted — this
  was a real bug fixed this session across 26 pages; check whether more
  remain or whether new pages have reintroduced it).
- Count of distinct "confirm before destructive action" patterns
  (`confirm()` browser dialog vs. a proper confirmation modal vs. no
  confirmation at all).
- Count of pages that build their own pagination links vs. use the shared
  data-table's pagination.
- Search for hardcoded color hex values outside the design tokens (e.g.
  `#0f3d7a` appearing literally in dozens of files vs. a CSS variable/
  Tailwind theme color) — a sign the "brand navy" has drifted or been
  copy-pasted inconsistently.
- Grep for duplicated inline `<style>` blocks per-page vs. shared CSS.

### 4. Navigation audit specifics

- Read `resources/js/support/sahodayaAdminNav.js` (and the equivalent for
  School admin, if it exists) fully. Map the declared nav structure against
  the actual page inventory from step 1: are there pages with no nav entry
  (orphaned/hard-to-find features)? Nav entries pointing at pages that no
  longer exist?
- Check how many clicks/menus deep a school admin has to go for the 5 most
  common tasks (view students, register for an event, upload payment proof,
  check membership status, add a teacher) — flag anything more than 2 levels
  deep.
- Check whether the same concept has different labels in nav vs. page title
  vs. breadcrumb (e.g. "Annual Registration" vs "Membership" vs
  "Registration").

### 5. Terminology audit

Grep across both page trees for near-duplicate terms used for the same
concept, and list every instance with file path:

- "Sahodaya" vs "Association" vs "Cluster"
- "Program" vs "Event" vs "Fest" (this platform uses all three for
  overlapping concepts — Training Program, Fest Event, Kalotsav — confirm
  whether a school admin can tell these apart from the UI alone)
- "Registration" meaning: annual membership registration, event
  registration, exam registration, training registration — four different
  things sharing one word. Check whether page titles/breadcrumbs
  disambiguate clearly enough for a non-technical user.
- Status word variants: "Verified" vs "Approved" vs "Confirmed" vs
  "Completed" used for what should be the same state.

### 6. Feedback & error handling

- Are success/error messages shown via a consistent toast/flash pattern
  everywhere, or do some pages use `alert()`/browser `confirm()` and others
  use a styled banner?
- Are validation errors shown inline under each field consistently, or do
  some forms only show a generic top-of-form error?
- When a background action fails (e.g. PDF generation, email send), is the
  failure surfaced to the user at all, or silently swallowed (grep for
  `catch (\Throwable)` / `catch {}` blocks that don't message the user)?

### 7. Mobile & accessibility spot-check

- Pick 5 List pages and 5 form pages from each portal and check: does the
  data table degrade to something usable on a 375px-wide viewport, or does
  it just horizontally scroll a desktop table? School admin users are more
  likely to be on phones — weight this heavily for the School portal.
- Check color-contrast of the status pill patterns found in step 3 against
  WCAG AA.
- Check whether interactive elements (especially icon-only buttons) have
  accessible labels.

---

## Output format requirements

- Every finding must cite at least one real file path as evidence (e.g.
  "`Registration/Index.vue` uses `confirm()` for delete, but
  `Students/Index.vue` uses a styled modal — pick one pattern and apply it
  everywhere").
- Quantify wherever possible ("14 of 92 School pages hand-roll their own
  pagination markup instead of the shared component").
- The module-by-module priority list (§8) should classify each of the ~35
  Sahodaya feature areas and ~15 School feature areas into: **Rebuild**
  (pattern is actively confusing/broken), **Restyle** (functionally fine,
  visually inconsistent), **Leave alone** (already matches the target
  pattern).
- End with a proposed **shared pattern library** — a short, concrete list
  (not a full design system doc) of the exact components/conventions every
  page should use going forward: one table component, one modal component,
  one confirm-destructive-action pattern, one date formatter, one status
  badge component with a fixed color map, one file-upload-with-existing-
  preview component (the certificate template fix from this session is a
  good reference implementation to standardize from).

---

## Explicit non-goals

- Do not redesign anything yet — this is an audit + plan only.
- Do not flag every minor Tailwind class inconsistency as a finding; focus
  on things that would actually confuse or slow down a real school-office
  user, or that would make future maintenance harder (module-specific
  one-off components that duplicate a shared one).
