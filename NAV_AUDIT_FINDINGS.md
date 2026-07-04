# Navigation & Layout Audit Findings

**Audited:** July 2026  
**Implemented:** July 2026  
**Scope:** Sahodaya Admin sidebar (32 items), School Admin sidebar (38 items), 7 portal role navs, 2 dashboard home pages, scoped workspace navs

---

## Task A — Information architecture: item-by-item decisions

### Sahodaya Admin (target ≤18 sidebar items)

| Current item | Section | Recommendation |
|---|---|---|
| Dashboard | Home | **Keep — primary** |
| Sahodaya Site Builder | Website | **Keep** (rename → "Site Builder") |
| Website Content | Website | **Keep** (rename → "Content") |
| Office Bearers | Website | **Keep** |
| Circulars | Website | **Keep** |
| Academic Years | Membership | **Keep — primary** |
| Configuration | Membership | **Move to Settings section** — accessed from membership hub, not daily use |
| Schools | Membership | **Keep — primary** (badge: approved count) |
| Student change requests | Membership | **Keep** (badge: pending count) |
| Student Counts | Membership | **Remove from sidebar** — accessible inside Schools > Submissions tab |
| Membership fees | Membership | **Keep — primary** (badge: pending count) |
| Reports | Membership | **Remove from sidebar** — accessible from Membership fees page footer |
| Portal users | Administration | **Move to Settings section** |
| Notification templates | Administration | **Move to Settings section** |
| Kalotsav | Fest programs | **Keep — primary** |
| Sports Meet | Fest programs | **Keep — primary** |
| Kids Fest | Fest programs | **Keep — primary** |
| Teacher Fest | Fest programs | **Keep — primary** |
| Custom events | Fest programs | **Remove from sidebar** — accessible via All events with filter |
| All events | Fest programs | **Keep** (rename → "All events") |
| Fest payments queue | Fest tools | **Remove from sidebar** — accessible from event Finance page |
| Display screens | Fest tools | **Remove from sidebar** — accessible from event Overview |
| Certificate templates | Fest tools | **Remove from sidebar** — accessible from Certificates page |
| Certificate search | Fest tools | **Keep** (rename → "Find certificate") — genuinely cross-event |
| State remittances | Fest tools | **Keep** — unique cross-event financial feature |
| MCQ dashboard | Exams & training | **Keep** (rename → "MCQ exams") |
| All exams | Exams & training | **Remove from sidebar** — same destination as "MCQ dashboard" |
| Exam series | Exams & training | **Remove from sidebar** — accessible from MCQ hub page |
| Payments queue (MCQ) | Exams & training | **Remove from sidebar** — accessible inside MCQ exam workspace |
| Question banks | Exams & training | **Remove from sidebar** — accessible inside MCQ exam workspace |
| Training programs | Exams & training | **Keep** |
| Ledger | Exams & training | **Keep** |

**Result: 18 items remaining** (22 with website section, which is conditional)

---

### School Admin (target ≤16 items, website collapses to 1 link)

| Current item | Section | Recommendation |
|---|---|---|
| Dashboard | Home | **Keep — primary** |
| School Code | Students | **Keep** (only shown when not yet set — disappears after setup) |
| Students | Students | **Keep — primary** |
| School houses | Students | **Keep** |
| Teachers | Students | **Keep** |
| Portal users | Students | **Move to Settings** — not a daily task |
| Registration Details | Membership | **Merge with Annual Registration** — make it a tab on the same page |
| Annual Registration | Membership | **Keep — primary** |
| Payments & Receipts | Membership | **Keep** |
| Kalotsav | Fest programs | **Keep — primary** |
| Sports Meet | Fest programs | **Keep — primary** |
| Kids Fest | Fest programs | **Keep — primary** |
| Teacher Fest | Fest programs | **Keep — primary** |
| School events | Fest programs | **Remove from sidebar** — accessible from Dashboard fest program cards |
| Fest Hub | Fest tools | **Remove from sidebar** — replace with School events above, or access via dashboard |
| All fest reports | Fest tools | **Keep** (rename → "Reports") |
| School Events (duplicate) | Fest tools | **Remove** — exact duplicate |
| Food Coupons | Fest tools | **Remove from sidebar** — accessible from Kalotsav or Sports hub |
| Circulars | Fest tools | **Move to Settings** |
| Notifications | Fest tools | **Move to Settings** |
| MCQ exams | Exams & training | **Keep** |
| Teacher training | Exams & training | **Keep** |
| Site Builder | Website | **Collapse all 12 to single "School Website →" entry** |
| News | Website | ↑ (all moved inside Website hub page) |
| Events | Website | ↑ |
| Gallery | Website | ↑ |
| Staff | Website | ↑ |
| Achievements | Website | ↑ |
| Downloads | Website | ↑ |
| Job Vacancies | Website | ↑ |
| Board Results | Website | ↑ |
| Alumni | Website | ↑ |
| Testimonials | Website | ↑ |
| Contact Page | Website | ↑ |
| Enquiries | Admissions | **Move inside Website hub** |
| TC Requests | Admissions | **Move inside Website hub** |
| Settings | School | **Keep** (expand to include Portal users, Circulars, Notifications) |

**Result: 14 items visible** (15 when website enabled with single entry)

---

## Task B — Proposed section groupings with replacement JS

### Sahodaya Admin — new structure (5 sections, 18 items)

```
HOME (1)
  Dashboard

MEMBERSHIP (4)
  Schools  [badge: approved count]
  Membership fees  [badge: pending]
  Student change requests  [badge: pending]
  Academic Years

FEST (6)
  Kalotsav
  Sports Meet
  Kids Fest
  Teacher Fest
  All events
  Find certificate

EXAMS & TRAINING (3)
  MCQ exams
  Training programs
  Ledger

SETTINGS (4 — collapsed / lower visual weight)
  Configuration
  Portal users
  Notification templates
  State remittances

WEBSITE (4 — conditional, only if feature enabled)
  Site Builder
  Content
  Office Bearers
  Circulars
```

**Items removed from sidebar but still in nav data (findable via sidebar search):**
Student Counts, Reports, Custom events, Fest payments queue, Display screens, Certificate templates, All exams, Exam series, MCQ Payments queue, Question banks — all remain as hidden items in the `navGroups` data so the search box can surface them.

---

### School Admin — new structure (5 sections, 14 items)

```
HOME (1)
  Dashboard

SCHOOL (4)
  Students
  Teachers
  School houses
  Settings

MEMBERSHIP (2)
  Annual Registration
  Payments & Receipts

FEST (5)
  Kalotsav
  Sports Meet
  Kids Fest
  Teacher Fest
  Reports

EXAMS & TRAINING (2)
  MCQ exams
  Teacher training

WEBSITE (1 — conditional)
  School Website →
```

---

### Replacement JavaScript — `sahodayaAdminNav.js`

Replace the `sahodayaAdminNav()` function body with:

```js
export function sahodayaAdminNav(sahodayaId, options = {}) {
    const {
        canNav = () => true,
        websiteEnabled = false,
        approvedSchoolsCount = 0,
        pendingPaymentsCount = 0,
        pendingChangeRequests = 0,
    } = options;

    const base = `/sahodaya-admin/${sahodayaId}`;
    const groups = [];

    // ── Home ─────────────────────────────────────────────────────────
    groups.push({
        section: 'Home',
        items: [
            { label: 'Dashboard', href: base, icon: 'grid', exact: true },
        ],
    });

    // ── Website (conditional) ─────────────────────────────────────────
    if (websiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'Site Builder',   href: `${base}/site-builder`,    icon: 'layers' },
                { label: 'Content',        href: `${base}/public-content`,  icon: 'edit' },
                { label: 'Office Bearers', href: `${base}/office-bearers`,  icon: 'users' },
                { label: 'Circulars',      href: `${base}/circulars`,       icon: 'file-text' },
            ],
        });
    }

    // ── Membership ────────────────────────────────────────────────────
    if (canNav('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Schools',                  href: `${base}/schools`,                     icon: 'building',     badge: approvedSchoolsCount },
                { label: 'Membership fees',          href: `${base}/membership/payments`,          icon: 'credit-card',  badge: pendingPaymentsCount },
                { label: 'Student change requests',  href: `${base}/student-change-requests`,      icon: 'inbox',        badge: pendingChangeRequests },
                { label: 'Academic Years',           href: `${base}/academic-years`,               icon: 'calendar' },
                // Hidden — accessible via membership pages; still searchable
                { label: 'Configuration',       href: `${base}/membership/settings`,         icon: 'settings',  hidden: true },
                { label: 'Student Counts',      href: `${base}/membership/submissions`,      icon: 'inbox',     hidden: true },
                { label: 'Membership reports',  href: `${base}/membership/reports`,         icon: 'bar-chart', hidden: true },
            ],
        });
    }

    // ── Fest & events ─────────────────────────────────────────────────
    if (canNav('fest')) {
        groups.push({
            section: 'Fest & events',
            items: [
                { label: 'Kalotsav',         href: `${base}/kalotsav`,                              icon: 'star' },
                { label: 'Sports Meet',      href: `${base}/sports`,                                icon: 'award' },
                { label: 'Kids Fest',        href: `${base}/kids-fest`,                             icon: 'users' },
                { label: 'Teacher Fest',     href: `${base}/teacher-fest`,                          icon: 'users' },
                { label: 'All events',       href: `${base}/events`,                                icon: 'calendar', exact: true },
                { label: 'Find certificate', href: `${base}/events/certificates/search`,            icon: 'file-text' },
                // Hidden — accessible from event pages; still searchable
                { label: 'Custom events',         href: `${base}/programs/custom`,                  icon: 'layers',      hidden: true },
                { label: 'Fest payments queue',   href: `${base}/fest/payments`,                    icon: 'credit-card', hidden: true },
                { label: 'Display screens',       href: `${base}/display-screens`,                  icon: 'monitor',     hidden: true },
                { label: 'Certificate templates', href: `${base}/certificate-templates`,            icon: 'award',       hidden: true },
                { label: 'State remittances',     href: `${base}/state-remittances`,               icon: 'credit-card', hidden: true },
            ],
        });
    }

    // ── Exams & training ──────────────────────────────────────────────
    const examItems = [];
    if (canNav('mcq')) {
        examItems.push(
            { label: 'MCQ exams', href: `${base}/mcq`, icon: 'clipboard' },
            // Hidden — accessible from MCQ hub/exam workspace
            { label: 'All exams',      href: `${base}/mcq-exams`,        icon: 'clipboard',   hidden: true },
            { label: 'Exam series',    href: `${base}/mcq-series`,       icon: 'layers',      hidden: true },
            { label: 'MCQ payments',   href: `${base}/mcq/payments`,     icon: 'credit-card', hidden: true },
            { label: 'Question banks', href: `${base}/mcq/question-banks`, icon: 'layers',    hidden: true },
        );
    }
    if (canNav('training')) {
        examItems.push({ label: 'Training programs', href: `${base}/training`, icon: 'users' });
    }
    if (canNav('ledger')) {
        examItems.push({ label: 'Ledger', href: `${base}/ledger`, icon: 'credit-card' });
    }
    if (examItems.length) {
        groups.push({ section: 'Exams & training', items: examItems });
    }

    // ── Settings (lower visual weight — config + admin) ───────────────
    if (canNav('users') || canNav('membership')) {
        const settingsItems = [];
        if (canNav('membership')) {
            settingsItems.push({ label: 'Configuration',        href: `${base}/membership/settings`,    icon: 'settings' });
            settingsItems.push({ label: 'State remittances',    href: `${base}/state-remittances`,      icon: 'credit-card' });
        }
        if (canNav('users')) {
            settingsItems.push({ label: 'Portal users',          href: `${base}/users`,                  icon: 'users' });
            settingsItems.push({ label: 'Notification templates', href: `${base}/notification-templates`, icon: 'file-text' });
        }
        if (settingsItems.length) {
            groups.push({ section: 'Settings', items: settingsItems });
        }
    }

    return groups;
}
```

> **Note on `hidden: true`:** Add `hidden` support to `SahodayaSidebarNavSearch` / `filterNavGroups` so hidden items appear in search results but not in the rendered nav list. One-line change in `filterNavGroups.js`: include items where `item.hidden` when filtering by query, exclude them when rendering the full list.

---

### Replacement JavaScript — `schoolAdminNav.js`

Replace the `schoolAdminNav()` function body with:

```js
export function schoolAdminNav(schoolId, options = {}) {
    const {
        canNav = () => true,
        websiteEnabled = false,
        schoolHasPrefix = true,
    } = options;

    const base = schoolAdminHref(schoolId);
    const groups = [];

    // ── Home ──────────────────────────────────────────────────────────
    groups.push({
        section: 'Home',
        items: [{ label: 'Dashboard', href: base, icon: 'grid', exact: true }],
    });

    // ── School (students + core records) ──────────────────────────────
    if (canNav('students')) {
        const schoolItems = [];
        if (!schoolHasPrefix) {
            schoolItems.push({ label: 'School Code', href: schoolAdminHref(schoolId, 'setup', 'code'), icon: 'hash' });
        }
        schoolItems.push(
            { label: 'Students', href: schoolAdminHref(schoolId, 'students'), icon: 'users' },
            { label: 'Teachers', href: schoolAdminHref(schoolId, 'teachers'), icon: 'users' },
            { label: 'School houses', href: schoolAdminHref(schoolId, 'houses'), icon: 'award' },
            { label: 'Settings', href: schoolAdminHref(schoolId, 'settings'), icon: 'settings' },
        );
        // Hidden — accessible from Settings page
        if (canNav('users')) {
            schoolItems.push({ label: 'Portal users', href: schoolAdminHref(schoolId, 'users'), icon: 'users', hidden: true });
        }
        groups.push({ section: 'School', items: schoolItems });
    }

    // ── Membership ────────────────────────────────────────────────────
    if (canNav('membership')) {
        groups.push({
            section: 'Membership',
            items: [
                { label: 'Annual Registration', href: schoolAdminHref(schoolId, 'registration'),         icon: 'clipboard' },
                { label: 'Payments & Receipts', href: schoolAdminHref(schoolId, 'payments'),             icon: 'credit-card' },
                // Hidden — tab on Annual Registration page
                { label: 'Registration Details', href: schoolAdminHref(schoolId, 'registration', 'profile'), icon: 'user', hidden: true },
            ],
        });
    }

    // ── Fest ──────────────────────────────────────────────────────────
    if (canNav('fest')) {
        groups.push({
            section: 'Fest',
            items: [
                ...SCHOOL_FEST_PROGRAMS.map((p) => ({
                    label: p.label,
                    href: schoolProgramHref(schoolId, p.slug),
                    icon: p.icon,
                })),
                { label: 'Reports', href: schoolAdminHref(schoolId, 'fest', 'reports'), icon: 'file-text', exact: true },
                // Hidden — accessible from program pages and dashboard
                { label: 'Fest Hub',       href: schoolAdminHref(schoolId, 'fest', 'hub'),    icon: 'star',      hidden: true },
                { label: 'School events',  href: schoolAdminHref(schoolId, 'fest-programs'),  icon: 'calendar',  hidden: true },
                { label: 'Food Coupons',   href: schoolAdminHref(schoolId, 'food-coupons'),   icon: 'clipboard', hidden: true },
                { label: 'Circulars',      href: schoolAdminHref(schoolId, 'circulars'),      icon: 'file-text', hidden: true },
                { label: 'Notifications',  href: schoolAdminHref(schoolId, 'notifications'),  icon: 'bell',      hidden: true },
            ],
        });
    }

    // ── Exams & training ──────────────────────────────────────────────
    const examItems = [];
    if (canNav('mcq')) {
        examItems.push({ label: 'MCQ exams', href: schoolAdminHref(schoolId, 'mcq'), icon: 'clipboard' });
    }
    if (canNav('training')) {
        examItems.push({ label: 'Teacher training', href: schoolAdminHref(schoolId, 'training'), icon: 'award' });
    }
    if (examItems.length) {
        groups.push({ section: 'Exams & training', items: examItems });
    }

    // ── Website (collapses to single hub entry) ────────────────────────
    if (websiteEnabled && canNav('website')) {
        groups.push({
            section: 'Website',
            items: [
                { label: 'School Website →', href: `${base}/site-builder`, icon: 'layers' },
                // Hidden — all accessible from site-builder hub; searchable
                { label: 'News',          href: `${base}/news`,          icon: 'file-text', hidden: true },
                { label: 'Events',        href: `${base}/events`,        icon: 'calendar',  hidden: true },
                { label: 'Gallery',       href: `${base}/gallery`,       icon: 'image',     hidden: true },
                { label: 'Staff',         href: `${base}/staff`,         icon: 'users',     hidden: true },
                { label: 'Achievements',  href: `${base}/achievements`,  icon: 'star',      hidden: true },
                { label: 'Downloads',     href: `${base}/downloads`,     icon: 'folder',    hidden: true },
                { label: 'Job Vacancies', href: `${base}/job-vacancies`, icon: 'briefcase', hidden: true },
                { label: 'Board Results', href: `${base}/board-results`, icon: 'bar-chart', hidden: true },
                { label: 'Alumni',        href: `${base}/alumni`,        icon: 'award',     hidden: true },
                { label: 'Testimonials',  href: `${base}/testimonials`,  icon: 'star',      hidden: true },
                { label: 'Contact Page',  href: `${base}/contact`,       icon: 'file-text', hidden: true },
                { label: 'Enquiries',     href: `${base}/enquiries`,     icon: 'inbox',     hidden: true },
                { label: 'TC Requests',   href: `${base}/tc-requests`,   icon: 'file-text', hidden: true },
            ],
        });
    }

    return groups;
}
```

---

### Required change to `filterNavGroups.js`

Add `hidden` item support — hidden items appear in search but not in default render:

```js
// resources/js/support/filterNavGroups.js
export function filterNavGroups(groups, query = '') {
    const q = (query ?? '').trim().toLowerCase();

    return groups
        .map((group) => ({
            ...group,
            // Without search: show only non-hidden items
            // With search: show any item whose label matches, including hidden
            items: group.items.filter((item) =>
                q
                    ? item.label.toLowerCase().includes(q)
                    : !item.hidden,
            ),
        }))
        .filter((group) => group.items.length > 0);
}
```

---

## Task C — Dashboard redesign

### Sahodaya Admin Dashboard ✅

Action queue first, 3 linked primary stats, program status grid, recent audit activity, get-started empty state. Removed hero strip, finance summary, and school participation wall.

### School Admin Dashboard ✅

Hide Welcome/stepper when setup complete; linked stat tiles; fest programs only when open events exist; registration deadline alert; membership complete banner.

---

## Task D — Role-based access review

### Sahodaya Admin permissions ✅ (partial)

Staff nav maps and `TenantUserCatalog` updated for `training.view` / `training.manage` / `finance.view` (backward-compatible OR with fest/membership perms). State remittances gated when cluster has no `FestStateProgram` records. School `students` section scoping and event-coordinator-only nav deferred.

---

## Task E — Portal nav completeness ✅

Student portal uses `studentPortalNav.js` on all pages (including MCQ exam/result). Teacher portal uses `teacherPortalNav.js` with dedicated `/training` and `/fest` routes.

---

## Task F — Scoped workspace nav review ✅

`SahodayaEventsLayout` breadcrumb pills + event status badge. `sahodayaMcqExamScopedNav()` trimmed to 6 visible items. MCQ hub sidebar shows dashboard only (other hub links searchable via `hidden`).

---

**Current sections (top to bottom):**
1. Hero strip (name + 2 CTA buttons)
2. Stats row (up to 8 stat tiles, conditional)
3. Fest & program hubs (program links grid + 3 quick actions)
4. Program status grid (open events, registrations, results pending per program)
5. Finance summary row (fees by category)
6. School participation badges (all schools, coloured by active/inactive)
7. Action queue (pending approvals banner cards)

**Problems:**
- Action queue is at the **bottom** — it's the most urgent content and should be first
- 8 stat tiles are mostly decorative (no click-through to filtered lists)
- Finance summary and School participation appear even when empty
- Hero strip takes 80px of prime real estate for branding that's already in the sidebar

**Proposed layout:**

```
┌─────────────────────────────────────────────────────────────┐
│  ACTION QUEUE (only rendered if items exist)                │
│  [🔴 5 payments pending →] [🟡 3 change requests →]        │
└─────────────────────────────────────────────────────────────┘
┌──────────────────────┬──────────────────────┬──────────────┐
│  Schools             │  Membership fees     │  Fest events │
│  42 approved         │  ₹1.2L collected     │  3 active    │
│  2 pending →         │  ₹18k pending →      │              │
└──────────────────────┴──────────────────────┴──────────────┘
┌─────────────────────────────────────────────────────────────┐
│  PROGRAM STATUS (grid: 1 card per active program)           │
│  [Kalotsav: 14 open · 220 regs · 4 pending results]        │
│  [Sports Meet: 8 open · 180 regs]                          │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│  RECENT ACTIVITY (last 5 audit log entries)                 │
└─────────────────────────────────────────────────────────────┘
```

**Changes:**
- Remove hero strip (name is in sidebar header)
- Move action queue to top — it's the "inbox zero" of the dashboard
- Keep only 3 stat tiles, each linking to the filtered list
- Program status grid stays (it's genuinely useful)
- Replace school participation badge wall with "Recent activity" list (last 5 audit events)
- Remove finance summary row — accessible from Ledger / Membership fees pages

**Empty state (day 1, no schools):**
Show a single "Get started" card with 3 steps: add academic year → invite schools → publish first event. Currently the empty dashboard shows all the same tiles with zeros, which is confusing.

---

### School Admin Dashboard

**Current sections (top to bottom):**
1. Leadership contacts warning banner (conditional)
2. Membership complete banner (conditional)
3. Welcome card (static text)
4. Get-started stepper (3 steps)
5. 3 stat tiles (students by category)
6. Fest programs grid (links to each program)
7. MCQ/training/activity extras (dashboardExtras)

**Problems:**
- The "Welcome" card and stepper both show even after setup is complete — they should disappear after all 3 steps are done
- Once setup is complete (school code set, students added, registration done) the dashboard has almost no useful content
- No "today's pending tasks" — nothing tells the admin there are 3 pending change requests or a registration deadline in 2 days
- Fest program grid duplicates the sidebar

**Proposed layout:**

```
┌─────────────────────────────────────────────────────────────┐
│  SETUP STEPPER  (only shown until all 3 steps complete)     │
│  1 ✓ School code  2 ✓ Students  3 → Annual registration     │
└─────────────────────────────────────────────────────────────┘
┌─────────────────────────────────────────────────────────────┐
│  ALERTS (conditional — leadership warning, deadline, etc.)  │
└─────────────────────────────────────────────────────────────┘
┌──────────────┬──────────────┬──────────────┬───────────────┐
│  Students    │  Registration│  MCQ         │  Training     │
│  248 active  │  Approved ✓  │  2 upcoming  │  1 active     │
└──────────────┴──────────────┴──────────────┴───────────────┘
┌─────────────────────────────────────────────────────────────┐
│  FEST PROGRAMS (only if any program has open events)        │
│  [Kalotsav: 12 regs · fees pending] [Sports: 8 regs]      │
└─────────────────────────────────────────────────────────────┘
```

**Changes:**
- Hide stepper once `setup.hasRegistration` is true — show a "Membership active" status badge instead
- Add deadline alert: if registration window closes within 3 days, show a red banner
- Stat tiles should each link (students → `/students`, registration → `/registration`)
- Fest programs grid stays but only if there are open events (hide when empty)
- Replace static "Welcome" card with nothing — onboarding is done via the stepper

---

## Task D — Role-based access review

### Sahodaya Admin permissions

**STAFF_NAV permission map (current):**

| Section | Permissions required |
|---|---|
| website | `website.view`, `website.manage`, `website.news` |
| membership | `membership.view`, `membership.manage` |
| fest | `fest.view`, `fest.manage`, `fest.marks`, `fest.registrations`, `fest.results`, `fest.settings`, `fest.finance`, `fest.certificates`, `fest.catering`, `fest.schedule` |
| mcq | `mcq.view`, `mcq.manage`, `mcq.attendance`, `mcq.marks` |
| training | `fest.view`, `fest.manage` |
| ledger | `membership.view`, `membership.manage` |
| users | `users.manage` |

**Problems:**
1. `training` reuses `fest.view` / `fest.manage` — a fest-only staff member gets training access they shouldn't need. Add a dedicated `training.view` / `training.manage` permission.
2. `State remittances` has no permission gate — shows for all clusters including those that don't send to state level. Add: only show if `features.state_remittances_enabled` flag is true on the tenant.
3. `Ledger` requires membership permissions — a finance-only staff role wanting ledger access also gets full membership management. Split into `finance.view` permission.
4. `fest` permission is all-or-nothing for 10 sub-permissions. Staff who only do mark entry see the full Fest nav. Adequate for now but will need sub-section gating as the team grows.

**Recommended additions to STAFF_NAV:**
```js
training: ['training.view', 'training.manage', 'fest.view', 'fest.manage'], // OR
ledger:   ['finance.view', 'membership.view', 'membership.manage'],
```

---

### School Admin permissions

**STAFF_NAV permission map (current):**

| Section | Permissions required |
|---|---|
| students | `fest.view`, `website.view`, `website.manage`, `membership.view` |
| membership | `membership.view`, `membership.manage` |
| fest | `fest.view`, `fest.manage` |
| mcq | `mcq.view`, `mcq.manage` |
| training | `fest.view`, `fest.manage` |
| website | `website.view`, `website.manage`, `website.news` |
| users | `users.manage` |

**Problems:**
1. `students` section requires *any one of* fest.view, website.view, website.manage, membership.view — essentially any staff role gets student list access. For a judge portal user or fest coordinator this is overly broad. Should require a dedicated `students.view` permission or be restricted to Principal/VP/Office roles only.
2. Event Coordinator role (created by principal) currently has no defined permission scope in the nav — they get the same sidebar as a full school admin. Add `events_coordinator` role gating: fest + students (read-only) only.
3. No distinction in sidebar between Principal (full access) and VP / office staff (may lack users.manage, website access). The sidebar search and full item count is the same for all.

---

## Task E — Portal nav completeness

| Role | Current nav items | Missing | Sections buried in scroll that should be nav |
|---|---|---|---|
| **Student** | Dashboard, Fest Schedule (#anchor), Results (#anchor), Certificates (#anchor), Profile | None (anchors should be real pages, not hash links) | MCQ Exams section → own nav item to `/portal/student/{id}/mcq` |
| **Teacher** | Dashboard, Question Banks | Training, Fest schedule, Admit cards, Results, Appeals | All the stacked sections (7+) should be nav items or separate pages |
| **Judge** | Dashboard | Nothing — mark entry is reached via assignment card ✓ | Mark entry progress should be visible on dashboard (it is, already) |
| **FestOps** | Dashboard, Gate Check | Event-specific pages (reached via "Open event" card) | Fine as-is — slim portal |
| **Group Admin** | Dashboard, Students, Fest registrations, Fest schedule, Clashes, Admit cards | Nothing significant | House standings could be a nav item |
| **House Admin** | Dashboard, Students, Registrations, House ranking | Nothing significant | Fine |
| **Exam Supervisor** | Exams (single item) | Nothing significant | Fine |

**Priority fixes for portal nav:**

```js
// Student — replace hash anchors with real page routes
const navItems = computed(() => {
    const base = `/portal/student/${props.school.id}`;
    return [
        { href: base,                      label: 'Home' },
        { href: `${base}/mcq`,             label: 'MCQ Exams' },
        { href: `${base}/fest/schedule`,   label: 'Schedule' },
        { href: `${base}/results`,         label: 'Results' },
        { href: `${base}/certificates`,    label: 'Certificates' },
        { href: `${base}/profile`,         label: 'Profile' },
    ];
});

// Teacher — split dashboard scroll into nav items
const navItems = computed(() => [
    { href: `/portal/teacher/${props.school.id}`,              label: 'Home' },
    { href: `/portal/teacher/${props.school.id}/training`,     label: 'Training' },
    { href: `/portal/teacher/${props.school.id}/fest`,         label: 'Fest' },
    { href: `/portal/teacher/${props.school.id}/question-banks`, label: 'MCQ Banks' },
]);
```

---

## Task F — Scoped workspace nav review

### SahodayaEventsLayout (fest event workspace)

**Current state:**
- Sidebar shows "← Sahodaya home" and "← Kalotsav" (or "← All events") as small dim links at top of header — good, but visually weak
- A separate `FestEventWorkflowStepper` component is rendered on each page (Overview → Registration → Items → Scheduling → Scoring → Results) — this is good UX for linear flow
- An `EventSubNav` component (horizontal tabs) is used on event pages for sub-sections
- No item count on sidebar — e.g. "12 registrations pending" not shown

**Verdict:** This layout is well-designed. The breadcrumb links + stepper + horizontal sub-nav is the right pattern. No major restructuring needed.

**Minor fixes:**
- Make "← Sahodaya home" and "← Kalotsav" links visually more distinct (e.g., styled as a `<Link>` pill with a back arrow icon, not just dim text)
- Add the event's `registration_open` status as a coloured badge in the sidebar header below the event name

---

### MCQ Exam scoped nav (11 items — too many)

**Current items:**
1. Overview
2. Payments
3. Question banks
4. Hall tickets
5. Attendance
6. Results & marks
7. Reports
8. Live session
9. Leaderboard
10. Exam staff
11. Activity log

**Analysis:**
- "Overview" and "Live session" could merge (live session is a sub-tab on overview)
- "Results & marks" and "Leaderboard" could merge (leaderboard is derived from marks)
- "Activity log" is rarely accessed — move to footer link or Reports
- "Exam staff" is setup-phase only — move to Overview sidebar card

**Proposed 7-item scoped nav:**

```js
export function sahodayaMcqExamScopedNav(sahodayaId, examId, options = {}) {
    const base = `/sahodaya-admin/${sahodayaId}`;
    const examBase = `${base}/mcq-exams/${examId}`;

    return [
        {
            section: 'Sahodaya',
            items: [{ label: '← Dashboard', href: base, icon: 'grid', exact: true }],
        },
        {
            section: 'This exam',
            items: [
                { label: 'Overview',     href: examBase,                        icon: 'file-text' },
                { label: 'Payments',     href: `${examBase}/payments`,          icon: 'credit-card' },
                { label: 'Hall tickets', href: `${examBase}/hall-tickets`,      icon: 'clipboard' },
                { label: 'Attendance',   href: `${examBase}/attendance`,        icon: 'users' },
                { label: 'Results',      href: `${examBase}/results`,           icon: 'bar-chart' },
                { label: 'Reports',      href: `${examBase}/reports`,           icon: 'inbox' },
                // Hidden but searchable:
                { label: 'Question banks', href: `${examBase}/question-banks`,  icon: 'layers',  hidden: true },
                { label: 'Leaderboard',   href: `${examBase}/leaderboard`,      icon: 'star',    hidden: true },
                { label: 'Live session',  href: `${examBase}/session`,          icon: 'monitor', hidden: true },
                { label: 'Exam staff',    href: `${examBase}/staff`,            icon: 'users',   hidden: true },
                { label: 'Activity log',  href: `${examBase}/activity`,         icon: 'inbox',   hidden: true },
            ],
        },
    ];
}
```

---

## Implementation checklist

```
✅ 1. Add `hidden` field support to filterNavGroups.js
✅ 2. Replace sahodayaAdminNav() in sahodayaAdminNav.js
✅ 3. Replace schoolAdminNav() in schoolAdminNav.js
✅ 4. Replace sahodayaMcqExamScopedNav() in sahodayaAdminNav.js
✅ 5. Update Student/Dashboard.vue navItems (real routes: /fest/schedule, /results, /certificates)
✅ 6. Update Teacher/Dashboard.vue navItems (Training, Fest section anchors)
✅ 7. Sahodaya dashboard — action queue above stat tiles
✅ 8. School dashboard — hide stepper when all 3 setup steps complete
✅ 9. School dashboard — registration window deadline alert
✅ 10. SahodayaEventsLayout — styled breadcrumb pills + event status badge
✅ 11. Pass pendingChangeRequests count via SahodayaAdminController → layout nav badge
```

### Additional implementation (Tasks C–F)

- Sahodaya dashboard: action queue first, 3 linked stats, program status, recent audit activity, get-started empty state
- School dashboard: conditional Welcome/stepper, linked stats, open-events-only fest grid, deadline banner
- Permissions: `training.view`, `training.manage`, `finance.view` in catalog + staff nav maps
- Student/teacher portal shared nav modules; MCQ hub route for students
- MCQ exam scoped nav + trimmed MCQ hub sidebar

Backend touched: `DashboardController` (both panels), `SahodayaAdminController` (badges + state remittance flag), `TenantUserCatalog`, portal routes/controllers.
