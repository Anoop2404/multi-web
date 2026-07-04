# Navigation & Layout Audit Prompt

Paste this into a new session to audit and redesign the navigation, sidebar layout, dashboard home sections, and role-based page access across all admin tiers.

---

## Project context

Laravel 11 multi-tenant SaaS (Sahodaya school cluster management). Three separate admin tiers each have their own layout with a left sidebar:

1. **Sahodaya Admin** — cluster office staff managing member schools, membership fees, fest events, MCQ exams, training
2. **School Admin** — individual school principal/admin managing students, annual registration, fest registrations, website
3. **Portal users** — students, teachers, judges, FestOps, Group, HouseAdmin, Exam supervisor — each has a slim `PortalLayout` with a top nav (not a sidebar)

Nav is defined in:
- `resources/js/support/sahodayaAdminNav.js` → `sahodayaAdminNav()`
- `resources/js/support/schoolAdminNav.js` → `schoolAdminNav()`
- Portal layouts use inline `navItems` computed arrays in each Dashboard.vue

---

## Current sidebar inventories (exact, as of July 2026)

### Sahodaya Admin sidebar — 32 items, 7 sections

```
HOME (1)
  Dashboard

WEBSITE (4, only if feature enabled)
  Sahodaya Site Builder
  Website Content
  Office Bearers
  Circulars

MEMBERSHIP (7)
  Academic Years
  Configuration
  Schools  [badge: count]
  Student change requests
  Student Counts
  Membership fees  [badge: pending]
  Reports

ADMINISTRATION (2)
  Portal users
  Notification templates

FEST PROGRAMS (6)
  Kalotsav
  Sports Meet
  Kids Fest
  Teacher Fest
  Custom events
  All events

FEST TOOLS (5)
  Fest payments queue
  Display screens
  Certificate templates
  Certificate search
  State remittances

EXAMS & TRAINING (7)
  MCQ dashboard
  All exams
  Exam series
  Payments queue
  Question banks
  Training programs
  Ledger
```

### School Admin sidebar — 38 items, 9 sections (website enabled)

```
HOME (1)
  Dashboard

STUDENTS (5)
  School Code  (only shown if no prefix set)
  Students
  School houses
  Teachers
  Portal users

MEMBERSHIP (3)
  Registration Details
  Annual Registration
  Payments & Receipts

FEST PROGRAMS (5)
  Kalotsav
  Sports Meet
  Kids Fest
  Teacher Fest
  School events

FEST TOOLS (6)
  Fest Hub
  All fest reports
  School Events       ← DUPLICATE of "School events" above
  Food Coupons
  Circulars
  Notifications

EXAMS & TRAINING (2)
  MCQ exams
  Teacher training

WEBSITE (12)
  Site Builder
  News
  Events
  Gallery
  Staff
  Achievements
  Downloads
  Job Vacancies
  Board Results
  Alumni
  Testimonials
  Contact Page

ADMISSIONS (2)
  Enquiries
  TC Requests

SCHOOL (1)
  Settings
```

---

## Known problems (user-reported + self-observed)

1. **Too many menu items** — both sidebars feel overwhelming. Sahodaya admin: 32 items. School admin: 38 items. The school admin sidebar is especially bad when the school website feature is enabled.
2. **School Admin sidebar has a duplicate** — "School events" appears under both Fest Programs and Fest Tools.
3. **Dashboard home sections feel bloated** — Sahodaya admin dashboard shows: hero strip, 8+ stat tiles, Fest & program hubs, Program status grid, Finance summary row, School participation badges, Action queue. School admin dashboard shows: leadership warning, membership complete banner, welcome card, get-started stepper, 3 stat tiles, Fest programs grid, and MCQ/training extras. Both are dense.
4. **Website section has 12 items** — when a school enables the website, 12 links appear in the sidebar for content like Alumni, Testimonials, Job Vacancies. These are low-priority and inflate the nav dramatically.
5. **Fest programs and Fest tools are separate sections** — but they're logically related. A user clicking "Kalotsav" then wants to get to "Certificate search" — these are in different sections with no visual grouping between related items.
6. **No visual hierarchy** — every item has the same weight. There's no distinction between primary daily-use items (Schools, Events, Students) and secondary configuration items (Notification templates, Certificate templates, Display screens).
7. **Context-switching nav** — the sidebar switches to scoped nav when inside an MCQ exam or fest event workspace. This is smart but the "back to main" entry is just a plain nav item ("Main dashboard") with no visual differentiation.
8. **Portal nav is minimal** — most portal dashboards have only 1 nav item ("Dashboard"). Students are missing nav to Schedule, Results, Certificates. Teacher portal has everything on one long scroll with no nav.

---

## Audit tasks

### Task A — Navigation information architecture

Read the nav files:
- `resources/js/support/sahodayaAdminNav.js`
- `resources/js/support/schoolAdminNav.js`
- `resources/js/support/schoolProgramNav.js`

For each sidebar, produce a **two-column table**:

| Current item | Recommendation |
|---|---|
| Academic Years | Keep — primary |
| Notification templates | Move to Settings / config |
| … | … |

Recommendation options: Keep as primary · Move to secondary/config · Merge with X · Remove (accessible from Y) · Rename to Z

Target: **Sahodaya admin max 18 items**. **School admin max 16 items** (excluding website section which should collapse under a single "Website" entry).

---

### Task B — Section grouping redesign

Propose revised section groupings for both sidebars. Rules:
- Max **5 sections** per sidebar
- Max **5 items per section** (overflow becomes accessible from a hub/settings page, not from the sidebar)
- Primary daily-use items in the first two sections
- Configuration / rarely-used items in a collapsible "Settings" or accessible from the relevant hub page
- The "Website" 12-item section in School admin should collapse to a single "School Website →" link that opens a dedicated website admin hub page

---

### Task C — Home/dashboard redesign

Read:
- `resources/js/Pages/Admin/Sahodaya/Dashboard.vue`
- `resources/js/Pages/Admin/School/Dashboard.vue`

For each dashboard, audit:

1. **Above the fold** — what does a user see on a 1280px screen without scrolling? Is it the most important thing for their daily workflow?
2. **Stat tile count** — how many stat tiles are shown? Which ones are actually actionable (link to something) vs. decorative?
3. **Action queue** — does the dashboard surface the most urgent items first? (e.g., "3 payments pending approval" should be more prominent than "10 circulars")
4. **Empty state** — what does a brand-new Sahodaya admin see on day 1 with no schools? What does a new school admin see with no students?
5. **Section ordering** — list the current sections top-to-bottom, then propose an ideal ordering

Propose a dashboard wireframe in plain text (ASCII or markdown table describing each row/card):

```
[Hero: name + quick stat strip — 1 row]
[Action queue — only if items pending]
[Primary grid: 2–3 cards for most-used workflows]
[Secondary: program status / recent activity]
```

---

### Task D — Role-based access review

Check which nav items are gated by `canNav()` in both nav files. List:

1. **What a full Sahodaya admin sees** vs. **what a staff-only user sees** (STAFF_NAV permissions)
2. Whether the current permission sections (`website`, `membership`, `fest`, `mcq`, `training`, `ledger`, `users`) are granular enough for real-world staff roles
3. Whether any nav items appear even when the feature is disabled (e.g., does `State remittances` show for all clusters even if they don't use state rounds?)
4. For School admin: same analysis — what does a Principal see vs. an Events Coordinator role?

---

### Task E — Portal nav completeness

Read these files and list current nav items vs. what's missing:

- `resources/js/Pages/Admin/Portal/Student/Dashboard.vue`
- `resources/js/Pages/Admin/Portal/Teacher/Dashboard.vue`
- `resources/js/Pages/Admin/Portal/Judge/Dashboard.vue`
- `resources/js/Pages/Admin/Portal/FestOps/Dashboard.vue`
- `resources/js/Pages/Admin/Portal/Group/Dashboard.vue`
- `resources/js/Pages/Admin/Portal/HouseAdmin/Dashboard.vue`
- `resources/js/Pages/Admin/Portal/Exam/Dashboard.vue`

For each portal role, produce:

| Role | Current nav items | Missing nav items | Sections buried in scroll that should be nav |
|---|---|---|---|

---

### Task F — Scoped nav review (MCQ exam + fest event workspaces)

When a Sahodaya admin enters an MCQ exam workspace, the sidebar switches to `sahodayaMcqExamScopedNav()` with 11 items in "This exam". When entering a fest event, a separate `SahodayaEventsLayout` with its own sub-nav is used.

Read `resources/js/Layouts/SahodayaEventsLayout.vue` and `resources/js/Components/sahodaya/EventSubNav.vue` (if it exists).

Audit:
1. How clear is the "you are in a workspace" context? Is there a breadcrumb?
2. Is there a clear "exit workspace / back to all events" affordance?
3. Are the 11 items in the MCQ exam scoped nav actually distinct pages, or could some be combined (e.g., "Results & marks" and "Leaderboard" could merge)?
4. Propose a max-8-item scoped nav for both MCQ exam and fest event workspaces.

---

## Output format

For each task, produce:

1. **Current state** — exact item list or screenshot description
2. **Problems** — numbered list, concrete
3. **Proposed change** — specific enough to implement (rename X to Y, move item Z to section W, remove item Q and add it to hub page at URL P)
4. For nav changes: produce the **replacement `navGroups` array** (JavaScript object literal) that can replace the relevant section in `sahodayaAdminNav.js` or `schoolAdminNav.js`

---

## Key files

```
resources/js/support/sahodayaAdminNav.js          ← Sahodaya sidebar data
resources/js/support/schoolAdminNav.js            ← School sidebar data
resources/js/support/schoolProgramNav.js          ← Program workflow items
resources/js/support/sahodayaPrograms.js          ← Program definitions
resources/js/Layouts/SahodayaAdminLayout.vue      ← Sahodaya sidebar shell
resources/js/Layouts/SchoolAdminLayout.vue        ← School sidebar shell
resources/js/Layouts/SahodayaEventsLayout.vue     ← Fest event workspace layout
resources/js/Pages/Admin/Sahodaya/Dashboard.vue   ← Sahodaya home page
resources/js/Pages/Admin/School/Dashboard.vue     ← School home page
resources/js/Pages/Admin/Portal/Student/Dashboard.vue
resources/js/Pages/Admin/Portal/Teacher/Dashboard.vue
resources/js/Pages/Admin/Portal/Judge/Dashboard.vue
resources/js/Pages/Admin/Portal/FestOps/Dashboard.vue
resources/js/Pages/Admin/Portal/Group/Dashboard.vue
resources/js/Pages/Admin/Portal/HouseAdmin/Dashboard.vue
resources/js/Pages/Admin/Portal/Exam/Dashboard.vue
```

---

## Constraints

- Do not change backend routes or controllers — nav items should only point to existing routes
- Do not change the `PortalLayout` component structure — only the `navItems` arrays inside each portal Dashboard.vue
- Changes to `sahodayaAdminNav.js` and `schoolAdminNav.js` are the primary deliverable for nav changes
- Dashboard layout changes are Vue template changes only (no controller changes needed for reordering sections)
- The sidebar search box (`SahodayaSidebarNavSearch`) already exists — any items removed from the primary nav but kept accessible should be findable via it (i.e., they must still exist somewhere in the nav data, just not as top-level section items)
