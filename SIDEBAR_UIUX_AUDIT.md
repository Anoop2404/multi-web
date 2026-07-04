# Sidebar & Layout Deep UI/UX Audit
**Date:** July 2026 · **Status:** Implemented July 2026

---

## Summary scorecard

| Area | Status | Severity |
|---|---|---|
| Sahodaya admin main sidebar | ✅ Fixed (SA-1–4) | Low |
| School admin main sidebar | ✅ Fixed (SB-1–3) | Low |
| Event workspace sidebar (inner) | ✅ Restructured (EV-1–8) | High |
| EventSubNav tab bar | ✅ Canonical setup tabs; sidebar deduped | High |
| Portal navs (Teacher, Judge, FestOps) | ✅ Teacher expanded; Judge mark link | High |
| Section label typography | ✅ 11px / 90% opacity | Medium |
| Inactive nav item contrast | ✅ white/70 → white/95 hover | Medium |
| Icon uniqueness | ✅ Improved in event + school nav | Medium |
| Sign-out button duplication | ✅ Header sign-out removed (Sahodaya admin) | Low |
| Mobile portal nav | ✅ Horizontal scroll on small screens | Medium |

---

## 1. Sahodaya Admin main sidebar

### Current state (post-implementation)

```
HOME (1)
  Dashboard

WEBSITE (4, conditional)
  Sahodaya Site Builder
  Website Content
  Office Bearers
  Circulars

MEMBERSHIP (4 visible + 3 hidden)
  Academic Years
  Schools  [badge: pending]
  Membership fees  [badge: pending]
  Student change requests  [badge: pending]
  — hidden: Configuration, Student Counts, Membership reports

FEST & EVENTS (6 visible + 5 hidden)
  Kalotsav
  Sports Meet
  Kids Fest
  Teacher Fest
  All events
  Certificate search
  — hidden: Custom events, Fest payments queue, Display screens, Certificate templates, State remittances

EXAMS & TRAINING (3 visible + 4 hidden)
  MCQ dashboard
  Training programs
  Ledger
  — hidden: All exams, Exam series, MCQ payments, Question banks

SETTINGS (4)
  Configuration
  State remittances    ← conditional
  Portal users
  Notification templates
```

**Total visible:** ~18–22 items (depending on website + state remittances flags)

### Problems

**SA-1 — Duplicate items between sections**
`Configuration` appears in the Settings section AND as a hidden item in Membership. `State remittances` appears in both Settings AND as a hidden item in Fest & events. These items exist at two addresses simultaneously — if someone searches and finds "Configuration" in Membership, it goes to `/membership/settings`, but "Configuration" in Settings may point elsewhere, or is the same page surfaced twice. Confirm URLs are identical; if so, remove one.

**SA-2 — "Certificate search" is out of place**
Certificate search is a lookup tool (find any student's certificate by chest number). It sits in the "Fest & events" section alongside the four program hubs. It belongs in settings or as a quick-action button inside a hub page, not as a peer of "Kalotsav."

**SA-3 — "Ledger" in Exams section has no icon context**
`Ledger` refers to MCQ payment ledger but the label alone doesn't say that. Users unfamiliar with the product may not know what ledger is being referenced. Rename to `MCQ payments` or `Exam ledger`.

**SA-4 — Section label typography is too small and dim**
`text-[10px] font-bold text-[#fbbf24]/75 uppercase tracking-widest`
At 10px, this is 13px CSS pixels on a 1.3 DPR screen — borderline. The 75% opacity on already-muted gold makes it further dim. On any screen with slight glare, section dividers become invisible.

**Fixes**

```
SA-1: Remove duplicate hidden items — Configuration and State remittances should only 
      appear in SETTINGS, not also as hidden items in other sections.

SA-2: Move "Certificate search" to inside the Fest & events section as a hidden item
      (searchable) or remove from sidebar and link from each program hub page.

SA-3: Rename "Ledger" → "Exam ledger"

SA-4: Update section label classes:
      BEFORE: text-[10px] font-bold text-[#fbbf24]/75 uppercase tracking-widest
      AFTER:  text-[11px] font-bold text-[#fbbf24]/90 uppercase tracking-widest
      (in SahodayaAdminLayout.vue and SahodayaEventsLayout.vue, both use the same class)
```

---

## 2. School Admin main sidebar

### Current state (post-implementation)

```
HOME (1)
  Dashboard

SCHOOL (4 visible + 1 hidden)
  Students  [badge: count]
  Teachers
  School houses
  Portal users
  — hidden: School Code (only if no prefix set — this should actually be visible, not hidden)

MEMBERSHIP (2 visible + 1 hidden)
  Annual Registration
  Payments & receipts
  — hidden: Registration details

FEST (5 visible + 5 hidden)
  Kalotsav
  Sports Meet
  Kids Fest
  Teacher Fest
  Fest Hub
  — hidden: All fest reports, School events, Food Coupons, Circulars, Notifications

EXAMS & TRAINING (2)
  MCQ exams
  Teacher training

WEBSITE (1 visible + 13 hidden, conditional)
  School Website →
  — hidden: 12 content pages
```

**Total visible:** ~14–15 items ✓ Clean

### Problems

**SB-1 — "School Code" hidden when it should be conditionally visible**
`School Code` is hidden by default with `hidden: true`. But this item only exists when the school hasn't set a student ID prefix yet — it's a **required setup step** that new school admins must complete. Hiding a critical onboarding action behind Cmd+K search defeats the purpose. It should be conditionally visible (not hidden) when `prefix` is not yet set, then disappear after setup.

**SB-2 — "Fest Hub" in the Fest section is redundant**
The Fest section has: Kalotsav, Sports Meet, Kids Fest, Teacher Fest, **Fest Hub**. The first four are program hubs; "Fest Hub" is the parent hub. Users will click "Kalotsav" to get into the Kalotsav workspace. "Fest Hub" is a landing page that lists all programs — which is what the four program entries already do directly. Either remove "Fest Hub" (the four programs are enough) or keep only "Fest Hub" and let users branch from there. Having both creates redundant navigation paths.

**SB-3 — Icon uniqueness is poor**
`file-text` used 8×, `clipboard` used 8×, `star` used 6× across the full school nav (including hidden items). On a visible nav with 14 items, users can't tell items apart at a glance.

**Fixes**

```
SB-1: In schoolAdminNav.js, change School Code item:
      BEFORE: { label: 'School Code', href: `${base}/school/prefix`, icon: 'settings', hidden: true }
      AFTER:  { label: 'Set school code', href: `${base}/school/prefix`, icon: 'alert-circle',
                hidden: !!school.student_id_prefix }
      (Requires passing `school.student_id_prefix` to the nav function — it already receives `school`)

SB-2: Remove "Fest Hub" from the Fest section. The four program entries are sufficient.
      If users need the hub, the school admin dashboard already links to it.

SB-3: Assign unique icons for visible items:
      Students          → 'users'       (keep)
      Teachers          → 'user-check'  (currently also 'users')
      School houses     → 'layers'      (currently 'award' which doesn't say "houses")
      Portal users      → 'shield'      (currently 'users' — third use of 'users')
      Annual Registration → 'clipboard' (keep)
      Payments & receipts → 'credit-card' (currently 'clipboard' — same as Registration)
      MCQ exams         → 'book-open'   (currently 'clipboard')
      Teacher training  → 'award'       (currently also 'award' for school houses — different item)
```

---

## 3. Event workspace sidebar — MAJOR ISSUES

This is the most critical area. The event workspace (`SahodayaEventsLayout`) uses `eventScopedNav()` which produces up to **7 sections with 38 items** in the worst case (sports event with championship, fees, athletic records, houses, catering, food coupons, and 6 sibling events).

### Current structure

```
MAIN MENU (2)            ← Back links (redundant — already in sidebar HEAD)
  ← Sahodaya home
  ← Kalotsav

EVENT (5)                ← Duplicated in EventSubNav.vue tab bar
  Overview
  Settings
  Event items
  Levels & cascade
  Activity log

PARTICIPANTS (5)
  Registrations
  Attendance
  Venue & schedule     ← same icon as below, confusing name overlap
  Performance order    ← same icon as above, confusing name overlap
  Judges & staff

COMPETITION (4–6)
  Mark entry
  Import marks         ← sub-action of Mark entry, not a sibling page
  Results & publish
  Leaderboard
  Championship (conditional)
  Chest numbers (conditional)

FINANCE & OUTPUT (3–5)
  Registration fees (conditional)
  Payment ledger (conditional)
  Reports
  Certificates
  ID cards

MORE (4–8)              ← "More" is not a section name
  Athletic records (conditional)
  Appeals
  Event staff
  Item listing         ← duplicated in EventSubNav.vue
  School invoices
  Houses (conditional)
  Catering (conditional)
  Food coupons (conditional)

SWITCH EVENT (1–7)      ← Useful but takes too much vertical space
  [event 1..6]
  All N events…
```

### Problems

**EV-1 — "Main menu" section is redundant**
The sidebar HEAD (`sa-sidebar-head`) already renders "← Sahodaya home" and "← Kalotsav" as pill buttons with `border border-white/15 bg-white/5` styling. The "Main menu" section in the nav body then renders the exact same two links again as nav items. This means users see each back link twice — once at the top of the sidebar, once in the nav.

**EV-2 — EventSubNav.vue duplicates the "Event" sidebar section**
`EventSubNav.vue` renders a horizontal tab bar inside event pages with tabs:
- Overview → `/events/{id}`
- Items setup → `/events/{id}/items`
- Item listing → `/events/{id}/items/list`
- Levels & cascade → `/events/{id}/levels`
- Activity log → `/events/{id}/activity`

The sidebar "Event" section has:
- Overview → same URL
- Settings → `/events/{id}/settings` (only in sidebar)
- Event items → `/events/{id}/items` (same page as "Items setup")
- Levels & cascade → same URL
- Activity log → same URL

Four of the five EventSubNav tabs are the same pages as the sidebar Event section. Users see two navigation controls pointing to the same destinations — one in the sidebar, one as a horizontal tab bar inside the page content area. This creates confusion about which is "canonical." Also note the label mismatch: "Event items" (sidebar) vs. "Items setup" (tab) for the same `/items` page.

**EV-3 — "Venue & schedule" and "Performance order" are confusingly named and identical-icon**
Both use `icon: 'calendar'`. Their URLs are:
- Venue & schedule → `${base}/schedule/items`
- Performance order → `${base}/schedule`

So `/schedule` is the parent and `/schedule/items` is a child. The parent is named "Performance order" but it's nested _under_ "Venue & schedule" in the URL. This is backwards. Also, "performance order" implies the sequence of performers, while "venue & schedule" implies place + time assignments. These need clearer names AND separate icons.

**EV-4 — "Import marks" is a sub-action, not a sibling page**
"Import marks" (`/marks/import`) is a secondary action available FROM the mark entry page. Exposing it as a peer nav item suggests it's a distinct workflow of equal importance to Mark entry, Results & publish, and Leaderboard — it's not. It's a CSV upload utility. It belongs as a button on the Mark entry page, not a nav item.

**EV-5 — "Item listing" appears in both the sidebar "More" section AND in EventSubNav**
`/events/{id}/items/list` is reachable via two paths. Remove from sidebar "More" section; keep in EventSubNav where it makes contextual sense.

**EV-6 — "More" is not a usable section label**
Users scanning the sidebar don't know what to expect from "More." The items in it span different concerns: Appeals (grievance), Event staff (personnel), Item listing (reports-style output), School invoices (finance), Houses (logistics), Catering (logistics). These should be grouped by concern or placed in existing sections.

**EV-7 — "Switch event" section consumes too much vertical space**
When a program has many events, "Switch event" shows up to 6 items + "All N events…" = 7 more nav items pushing everything else down. Most users won't need to switch events frequently enough to justify 7 slots.

**EV-8 — Icon monotony in event nav**
| Icon | Used for |
|---|---|
| `file-text` | Activity log, Import marks, Item listing, School invoices |
| `bar-chart` | Mark entry, Leaderboard, Reports, Payment ledger |
| `users` | Attendance, Judges & staff, Event staff |
| `star` | Overview, Results & publish, sibling events |
| `award` | Championship, Certificates, Athletic records |
| `credit-card` | Registration fees, ID cards, Food coupons |

Items with completely different functions share the same icon. A user scanning the sidebar at a glance cannot distinguish "Mark entry" from "Reports" or "Leaderboard" since all three show a bar chart.

---

### Proposed event workspace sidebar restructure

**Goal:** Reduce from 7 sections / up to 38 items → 5 sections / max 20 items

```
[SIDEBAR HEAD — unchanged]
  ← Sahodaya home
  ← Kalotsav
  [event icon + title card]

[NO "Main menu" section — remove entirely, head already handles this]

EVENT SETUP (2)
  Settings               icon: settings
  Activity log           icon: clock        ← changed from file-text

  (Overview, Event items, Levels & cascade, Item listing 
   are now ONLY in EventSubNav tab bar — see below)

PARTICIPANTS (4)
  Registrations          icon: inbox
  Attendance             icon: check-square
  Stage schedule         icon: calendar      ← renamed from "Performance order"
  Item scheduling        icon: map-pin       ← renamed from "Venue & schedule"
  (Judges & staff → move to Administration section)

COMPETITION (4)
  Mark entry             icon: edit-3
  Results & publish      icon: trophy        ← changed from star
  Leaderboard            icon: bar-chart     ← keep
  Chest numbers (cond.)  icon: hash

REPORTS & FINANCE (3–4)
  Reports                icon: file-text
  Certificates           icon: award
  ID cards               icon: credit-card
  Registration fees (c.) icon: dollar-sign
  Payment ledger (cond.) icon: bar-chart-2

ADMINISTRATION (3–6)
  Judges & staff         icon: user-check
  Appeals                icon: message-circle
  Event staff            icon: users
  School invoices        icon: receipt
  Houses (conditional)   icon: layers
  Athletic records (c.)  icon: activity
  Catering (cond.)       icon: coffee

SWITCH EVENT (collapsed)
  → Replace section with a "Switch event" dropdown button at sidebar foot
    (compact, doesn't consume nav slots)
```

**Total visible (typical event, no conditionals):** ~15 items across 5 sections ✓

---

### EventSubNav restructure

The current `EventSubNav.vue` should cover **event setup tabs only** — the 5 pages that relate to configuring the event before it goes live:

```
Proposed EventSubNav tabs (rendered on Overview, items, levels, listing pages):
  Overview   →  /events/{id}
  Items      →  /events/{id}/items
  Item list  →  /events/{id}/items/list
  Rounds     →  /events/{id}/levels      ← renamed from "Levels & cascade"
  Log        →  /events/{id}/activity
```

Remove these from the sidebar "Event section" (except Settings and the context header, which stays in sidebar). EventSubNav becomes the canonical navigation for event-setup pages; the sidebar only shows "Settings" and "Activity log" as quick-access in the EVENT SETUP section.

**Code change for EventSubNav.vue:**

```html
<!-- EventSubNav.vue — simplified, keep as tab bar -->
<template>
    <nav class="flex flex-wrap gap-1.5 border-b border-slate-200 pb-3 mb-4">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key
                  ? 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold bg-[#0f3d7a] text-white'
                  : 'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-slate-600 hover:bg-slate-100'">
            {{ tab.label }}
        </Link>
    </nav>
</template>
```

**Code change — eventScopedNav() in sahodayaEventNav.js:**

```js
// REMOVE the entire "Main menu" section push:
// groups.push({ section: 'Main menu', items: festMainMenuNavItems(...) });
// ← This is redundant with sidebar head back buttons

// CHANGE "Event" section to only Settings + Activity log:
groups.push({
    section: 'Event setup',
    items: [
        { label: 'Settings', href: `${base}/settings`, icon: 'settings', permissions: FEST_SETTINGS },
        { label: 'Activity log', href: `${base}/activity`, icon: 'clock', permissions: FEST_VIEW },
    ],
});

// CHANGE "Participants" section — rename items + fix icons:
groups.push({
    section: 'Participants',
    items: [
        { label: 'Registrations', href: `${base}/registrations`, icon: 'inbox', permissions: FEST_REGISTRATIONS },
        { label: 'Attendance', href: `${base}/attendance`, icon: 'check-square', permissions: FEST_REGISTRATIONS },
        { label: 'Stage schedule', href: `${base}/schedule`, icon: 'calendar', permissions: FEST_SCHEDULE },
        { label: 'Item scheduling', href: `${base}/schedule/items`, icon: 'map-pin', permissions: FEST_SCHEDULE },
        { label: 'Judges & staff', href: `${base}/judges`, icon: 'user-check', permissions: FEST_MANAGE },
    ],
});

// CHANGE Competition — remove "Import marks" from sidebar (it's a page action, not a nav item):
const competitionItems = [
    { label: 'Mark entry', href: `${base}/marks`, icon: 'edit-3', permissions: FEST_MARKS },
    // REMOVED: Import marks — add as a button on the Mark entry page instead
    { label: 'Results & publish', href: `${base}/results`, icon: 'trophy', permissions: FEST_RESULTS },
    { label: 'Leaderboard', href: `${base}/leaderboard`, icon: 'bar-chart', permissions: FEST_RESULTS },
];
if (caps.championship) {
    competitionItems.push({ label: 'Championship', href: `${base}/championship`, icon: 'award', permissions: FEST_RESULTS });
}
if (caps.isSports) {
    competitionItems.push({ label: 'Chest numbers', href: `${base}/chest-numbers`, icon: 'hash', permissions: FEST_MANAGE });
}
groups.push({ section: 'Competition', items: competitionItems });

// CHANGE Finance & output — fix icons, remove Item listing (now in EventSubNav):
const outputItems = [
    { label: 'Reports', href: `${base}/reports`, icon: 'file-text', permissions: FEST_VIEW },
    { label: 'Certificates', href: `${base}/certificates`, icon: 'award', permissions: FEST_CERTIFICATES },
    { label: 'ID cards', href: `${base}/id-cards`, icon: 'credit-card', permissions: FEST_VIEW },
];
if (caps.hasEventFees) {
    outputItems.unshift(
        { label: 'Registration fees', href: `${base}/fees`, icon: 'dollar-sign', permissions: FEST_FINANCE },
        { label: 'Payment ledger', href: `${base}/fees/ledger`, icon: 'layers', permissions: FEST_FINANCE },
    );
}
groups.push({ section: 'Reports & finance', items: outputItems });

// RENAME "More" → "Administration", reorder, fix icons:
const adminItems = [
    { label: 'Appeals', href: `${base}/appeals`, icon: 'message-circle', permissions: FEST_MANAGE },
    { label: 'Event staff', href: `${base}/event-staff`, icon: 'users', permissions: FEST_MANAGE },
    // REMOVED: Item listing (now in EventSubNav)
    { label: 'School invoices', href: `${base}/finance`, icon: 'receipt', permissions: FEST_FINANCE },
];
if (caps.athleticRecords) {
    adminItems.unshift({ label: 'Athletic records', href: `${base}/athletic-records`, icon: 'activity', permissions: FEST_MANAGE });
}
if (caps.houses) {
    adminItems.push({ label: 'Houses', href: `${base}/houses`, icon: 'layers', permissions: FEST_MANAGE });
}
if (caps.catering) {
    adminItems.push({ label: 'Catering', href: `${base}/catering`, icon: 'coffee', permissions: FEST_CATERING });
}
if (caps.foodCoupons) {
    adminItems.push({ label: 'Food coupons', href: `${base}/food-coupons`, icon: 'tag', permissions: FEST_CATERING });
}
groups.push({ section: 'Administration', items: adminItems });

// CHANGE "Switch event" — show max 3, rest accessible via program hub link:
if (programEvents.length) {
    const visible = programEvents.slice(0, 3);
    const items = visible.map((ev) => ({
        label: ev.title,
        href: `${tenantBase}/events/${ev.id}`,
        icon: Number(ev.id) === Number(eventId) ? 'star' : 'layers',
        permissions: FEST_VIEW,
    }));
    if (programEvents.length > 3) {
        items.push({
            label: `All ${programEvents.length} events…`,
            href: `${sahodayaProgramHref(sahodayaId, program?.slug)}${eq}`,
            icon: 'grid',
            permissions: FEST_VIEW,
        });
    }
    groups.push({ section: 'Switch event', items });
}
```

---

## 4. Portal layouts

### Student portal

**Current nav:** Home · MCQ Exams · Schedule · Results · Certificates · Profile (6 items) ✓

This is solid. Minor issue: "Schedule" links to `/fest/schedule` — the word "fest" is in the URL but not the label. If MCQ exam schedules become available separately, this label will be ambiguous. Consider renaming to `Fest schedule` for clarity.

**Mobile issue:** With 6 nav items as pills in a horizontal bar, on iPhone SE (320px width) these wrap to two lines, pushing content down. The `PortalLayout` has no hamburger. Fix: at `sm:` and below, collapse nav items to a scrollable row with `overflow-x-auto` and `whitespace-nowrap`.

```html
<!-- In PortalLayout.vue nav element -->
<nav class="flex gap-1.5 overflow-x-auto pb-1 scrollbar-none -mx-4 px-4 sm:mx-0 sm:px-0 sm:flex-wrap">
```

### Teacher portal

**Current nav:** Home · Training · Fest · MCQ Banks (4 items)

**Problem:** The dashboard page contains 9 distinct content sections: MCQ banks, Training, Fest registrations, Schedule, Admit cards, Results, Certificates, Fees, Appeals, Notifications. The top nav has 4 items. Clicking "Fest" shows the whole dashboard — not a filtered view. The scroll-spy section jumping (using `sections` array computed from data availability) is invisible to users; they don't know that "Fest" in the nav = scroll to fest section.

**Proposed nav expansion:**

```js
// teacherPortalNav.js — proposed update
export function teacherPortalNavItems(schoolId) {
    const base = `/portal/teacher/${schoolId}`;
    return [
        { href: base,                       label: 'Home' },
        { href: `${base}/fest`,             label: 'Fest' },
        { href: `${base}/fest/schedule`,    label: 'Schedule' },
        { href: `${base}/results`,          label: 'Results' },
        { href: `${base}/certificates`,     label: 'Certificates' },
        { href: `${base}/training`,         label: 'Training' },
        { href: `${base}/question-banks`,   label: 'MCQ Banks' },
    ];
}
```

This requires dedicated route + controller for `/fest`, `/results`, `/certificates` pages, or those sections from the dashboard can be split into sub-pages. If full sub-pages aren't available yet, at minimum add `#section-id` hash anchor links as interim:

```js
{ href: `${base}#section-fest-reg`, label: 'Fest' },
{ href: `${base}#section-schedule`, label: 'Schedule' },
```

### Judge portal

**Current nav:** Dashboard only (1 item)

The judge dashboard shows their event assignments and a per-event "Enter marks" button. This is OK for judges with 1 event. But judges with multiple events just scroll and click inline buttons. There's no nav item for mark entry.

**Proposed nav:**

```js
// In Judge/Dashboard.vue, expand navItems:
const navItems = computed(() => {
    const base = `/portal/judge/${props.sahodaya.id}`;
    const items = [{ href: base, label: 'Dashboard' }];
    // If judge has exactly 1 event, add direct mark entry link
    if (props.events?.length === 1) {
        items.push({
            href: `${base}/events/${props.events[0].id}/marks`,
            label: 'Enter marks',
        });
    }
    return items;
});
```

### FestOps portal

**Current nav:** Dashboard · Gate Check (2 items) ✓

This is minimal but appropriate for the role. Gate check is the primary action; the dashboard gives an overview. No major changes needed.

### Exam supervisor portal

**Current nav:** Exams (1 item)

The exam portal dashboard shows per-exam cards with Attendance and Live supervision buttons. These are important enough for nav:

```js
// In Exam/Dashboard.vue, expand navItems:
const navItems = computed(() => [
    { href: `/portal/exam/${props.sahodaya.id}`, label: 'Exams' },
    // Could add if dedicated pages exist:
    // { href: `/portal/exam/${props.sahodaya.id}/attendance`, label: 'Attendance' },
]);
```

---

## 5. Cross-cutting issues

### CC-1 — Sign-out button appears twice in admin layouts

`SahodayaAdminLayout.vue` renders a SignOutButton in two places:
1. **Sidebar footer** — `flex items-center gap-2 w-full px-3 py-2.5 rounded-lg text-sm text-white/80` (full-width, icon + text)
2. **Top header** (desktop only, `hidden sm:flex`) — `text-xs text-gray-600 hover:text-red-600 hover:bg-red-50`

Having two sign-out buttons is unusual and wastes header space. The sidebar footer is the better location (natural for sidebar-based UIs). Remove the sign-out from the top header; keep the sidebar footer version. The header can just show the user's name without the sign-out button.

```html
<!-- SahodayaAdminLayout.vue — in <header>, REMOVE this block: -->
<div class="hidden sm:flex items-center gap-2 pl-2 border-l border-gray-200">
    <span v-if="$page.props.auth?.user?.name" ...>{{ name }}</span>
    <SignOutButton ...>
        <SvgIcon name="log-out" />
        Sign out
    </SignOutButton>
</div>

<!-- Replace with just: -->
<span v-if="$page.props.auth?.user?.name" class="hidden sm:inline text-xs text-gray-500 max-w-[10rem] truncate">
    {{ $page.props.auth.user.name }}
</span>
```

### CC-2 — Inactive nav item contrast is borderline

`text-white/60` on the dark navy gradient sidebar = approximately #99aabb (hex) against #041525. This gives roughly 4.4:1 contrast ratio — just below WCAG AA (4.5:1) for small text. On any slightly degraded screen, inactive items will be hard to read.

```
BEFORE: text-white/60 hover:text-white/90
AFTER:  text-white/70 hover:text-white/95
```

Change in `SahodayaNavItem.vue`:
```html
<!-- Line 7 — inactive state class -->
: 'border-transparent text-white/70 hover:bg-white/8 hover:text-white/95'
```

### CC-3 — Section label legibility

Both admin sidebar layouts render section headers with:
```html
class="px-3 pt-4 pb-1 text-[10px] font-bold text-[#fbbf24]/75 uppercase tracking-widest"
```

10px + 75% opacity gold is at the edge of legibility. Two small changes significantly improve readability:

```html
<!-- In SahodayaAdminLayout.vue and SahodayaEventsLayout.vue -->
class="px-3 pt-4 pb-1 text-[11px] font-bold text-[#fbbf24]/90 uppercase tracking-widest"
```

### CC-4 — SchoolAdminLayout mobile — no user name in footer

`SahodayaAdminLayout` shows the user's name in the sidebar footer (`text-[11px] text-white/50 truncate`). Check if `SchoolAdminLayout` does the same — if not, add it for consistency.

### CC-5 — "Levels & cascade" label is jargon

"Cascade" refers to automatic result promotion between levels (school → cluster → district → state). But to a new admin, "Levels & cascade" is opaque. "Rounds & promotion" would be clearer:
- "Rounds" = the competition levels
- "Promotion" = the cascade rule

```
Rename across sahodayaEventNav.js + EventSubNav.vue:
  "Levels & cascade"  →  "Rounds & promotion"
```

---

## 6. Visual design consistency

### VD-1 — Event workspace sidebar width inconsistency

Main admin sidebar: `w-72 lg:w-60` (288px → 240px)
Events layout sidebar: `w-72 lg:w-64` (288px → 256px)

The events sidebar is 16px wider than the main admin sidebar at `lg`. This causes the main content area to shift when navigating between admin pages and event workspace pages. Standardise to `lg:w-60` in both.

### VD-2 — Event context card status badges

The sidebar head shows event status as small colored badges:
- Registration open: `bg-emerald-500/20 text-emerald-200 border-emerald-400/30`
- Live: `bg-amber-500/20 text-amber-100 border-amber-400/30`

These are subtle and well-designed. No changes needed, but consider adding:
- Completed: `bg-slate-500/20 text-slate-300 border-slate-400/30`
- Results published: `bg-purple-500/20 text-purple-200 border-purple-400/30`

### VD-3 — EventSubNav tab styling gap

The current EventSubNav uses only CSS classes `subnav-link` and `subnav-link--active`. These are defined in `app.css` but the active state needs to be more visually distinct from inactive. The proposed restyle in the code above (solid `bg-[#0f3d7a] text-white` for active) makes the active tab clearly the current page.

---

## 7. Implementation priority

| Issue | File(s) | Effort | Impact |
|---|---|---|---|
| EV-1: Remove "Main menu" section | `sahodayaEventNav.js` | 2 min | High |
| EV-2: Remove sidebar Event items duplicated in EventSubNav | `sahodayaEventNav.js` | 5 min | High |
| EV-6: Rename "More" → "Administration" | `sahodayaEventNav.js` | 1 min | Medium |
| EV-3/8: Rename schedule items + fix icons | `sahodayaEventNav.js` | 5 min | Medium |
| EV-4: Remove "Import marks" from nav | `sahodayaEventNav.js` | 2 min | Medium |
| EV-5: Remove "Item listing" from "More" | `sahodayaEventNav.js` | 1 min | Medium |
| EV-7: Limit Switch event to 3 items | `sahodayaEventNav.js` | 3 min | Medium |
| CC-2: Inactive nav contrast | `SahodayaNavItem.vue` | 1 min | Medium |
| CC-3: Section label typography | Both layout files | 2 min | Low |
| CC-1: Remove duplicate sign-out | `SahodayaAdminLayout.vue` | 3 min | Low |
| SA-2: Remove "Certificate search" from Fest section | `sahodayaAdminNav.js` | 1 min | Low |
| SA-3: Rename "Ledger" | `sahodayaAdminNav.js` | 1 min | Low |
| SB-1: School Code visible until prefix set | `schoolAdminNav.js` | 5 min | Medium |
| SB-2: Remove "Fest Hub" from School Fest section | `schoolAdminNav.js` | 1 min | Low |
| VD-1: Standardise sidebar width | `SahodayaEventsLayout.vue` | 1 min | Low |
| Portal mobile nav | `PortalLayout.vue` | 10 min | Medium |
| Teacher portal nav expansion | `teacherPortalNav.js` | 15 min (needs routes) | High |
| Judge portal nav | `Judge/Dashboard.vue` | 5 min | Medium |
| CC-5: Rename "Levels & cascade" | `sahodayaEventNav.js`, `EventSubNav.vue` | 3 min | Low |

---

## Implementation notes (July 2026)

All priority items above were implemented. Key files: `sahodayaEventNav.js`, `EventSubNav.vue`, `sahodayaAdminNav.js`, `schoolAdminNav.js`, `SahodayaNavItem.vue`, `SahodayaNavIcons.js`, admin/event layouts, `PortalLayout.vue`, `studentPortalNav.js`, `teacherPortalNav.js`, teacher portal pages + routes, judge dashboard nav.
