# School-Admin Side — Cleanup Plan

Status: proposed, not yet implemented.
Scope: school-admin panel — `resources/js/Pages/Admin/School/` and
`app/Http/Controllers/SchoolAdmin/`. This is the school's own admin panel
(students, teachers, event/exam/training registration, fees, ID cards, etc.),
as distinct from the Sahodaya-admin panel covered in
`SPORTS_NAV_CLEANUP_PLAN.md`.

Overall finding: this side is in noticeably better shape than the Sahodaya
sports flow — no double-stepper bug, no dead "head" cruft, nav is
context-aware (`schoolAdminNav.js` swaps nav sets per module: membership,
MCQ, training, fest). Issues found are smaller and mostly about
discoverability and naming, not broken flows.

---

## Issues, in priority order

### 1. Three orphaned pages (route + Vue page exist, no nav link)
- **`Imports/History.vue`** — a school that bulk-imports students via
  `Students/BulkCreate.vue` has no way to find their import history or
  re-download what was imported. No sidebar entry anywhere.
- **`Users/ProfileChangeRequests.vue`** — not linked from nav, `Users/Index.vue`,
  or `Settings`. Presumably where a school reviews profile-change requests
  from staff/teachers, but currently only reachable by direct URL.
- **`Events/Catering.vue`** ("Meal requests") — only reachable via a `HubCard`
  buried inside `FestHub.vue`. Its sibling feature "Food Coupons" has a full
  sidebar entry; Catering doesn't, despite looking like the same tier of
  feature to a school user.

**Fix:** add sidebar entries for all three (`schoolAdminNav.js`), scoped to
the same context group as their sibling features (Imports History next to
Bulk Create; Profile Change Requests next to Users; Catering next to Food
Coupons in the fest nav group).

**Files:** `resources/js/support/schoolAdminNav.js`.

### 2. "Reports" is fragmented across three hub pages
`Events/Reports.vue`, `Events/ReportsHub.vue`, and `Events/ReportEventHub.vue`
all exist alongside ~23 individual `Report*.vue` pages and a separate
`fest/reports` route. No clear canonical entry point — reads as leftover
from iterative builds rather than one intentional reports section.

**Fix:** Audit which of the three hub pages is actually linked from nav
today (likely only one is), confirm the other two are either dead or serve
a genuinely different purpose (e.g. one per-event, one cross-event), then
either consolidate into a single hub with tabs/filters, or rename the two
survivors so their distinct purposes are obvious from the label.

**Files:** the three hub Vue pages, `schoolAdminNav.js`,
`app/Http/Controllers/SchoolAdmin/` (whichever controller(s) serve them).

### 3. Terminology drift
- **"Food Coupons" vs "Meal requests" (Catering)** — read as overlapping
  features to a school user; if they're genuinely different (e.g. one is
  a per-student meal plan, the other a bulk coupon allocation), the labels
  should say so explicitly rather than relying on the user to infer it.
- **"Annual Registration" (sidebar label) vs "Membership" (section header)
  vs "Registration" (route prefix)** — three labels for one concept. Low
  risk in practice since the actual flow is unified by one
  `MembershipWorkflowNav` stepper, but worth picking one label for external
  consistency (recommend "Membership" as the umbrella term, "Annual
  Registration" only for the specific yearly renewal action within it).
- **MCQ branding** — user-facing label is "Talent Search"
  (`mcqSchoolLabels.js`) but the sidebar section says "Exams & training" and
  routes/controllers still say `mcq` internally. Worth a quick pass over
  `Pages/Admin/School/Mcq/*.vue` to confirm no page leaks the internal "MCQ"
  name where a school user would see "Talent Search" everywhere else.

**Fix:** Standardize labels per the recommendations above. Low effort,
cosmetic, safe to batch together.

**Files:** `schoolAdminNav.js`, `Events/Catering.vue`,
`Events/FoodCoupons.vue` (or equivalent), `Pages/Admin/School/Mcq/*.vue`.

### 4. No unified "my status" view
A school checking their own fee-payment status, attendance records, and
certificate/hall-ticket availability currently has to visit four separate
pages: Payments, Documents, Training/Attendance, and per-exam Hall Tickets.
There's no single "where do I stand" summary.

**Fix:** Lower priority than the above — this is a net-new feature, not a
bug fix. If pursued, a dashboard widget or dedicated summary page pulling a
read-only rollup from each of those four sources (fee balance, latest
attendance %, certificates ready to download, upcoming hall tickets) would
close the gap without duplicating the underlying pages.

**Files:** likely a new `Dashboard.vue` widget or new
`Pages/Admin/School/StatusSummary.vue` + a small aggregation
service/controller endpoint.

---

## Suggested order of work

1. Fix #1 (add the three missing nav links) — trivial, zero risk.
2. Fix #3 (terminology) — cosmetic, can batch with #1.
3. Investigate and fix #2 (Reports consolidation) — needs a quick audit of
   which hub is actually live before deciding consolidate-vs-rename.
4. Consider #4 (unified status view) — only if there's a real user complaint
   backing it; not identified as broken, just a possible convenience gap.

## Out of scope for this plan

- The event REGISTRATION flow itself (event reg → item reg → payment) is
  covered separately in `SCHOOL_EVENT_REGISTRATION_REDESIGN_PLAN.md`, since
  it involves a fee-logic change (₹1500 school minimum) as well as a
  possible page-structure redesign.
