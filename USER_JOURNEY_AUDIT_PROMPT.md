# End-to-End User Journey Audit Prompt

Paste this into a new Claude session with the `multi-web` folder connected. This is a **different lens** from the existing audits in this repo — it does not re-check bugs, nav labels, or UI/UX polish (that's already covered by `FULL_AUDIT_PROMPT.md`, `NAV_AUDIT_PROMPT.md`, `UIUX_AUDIT_PROMPT.md`, `SETUP_WORKFLOW_AUDIT_PROMPT.md`). This prompt exists to answer one question, per role, per event type:

> **"Starting from login, can this person actually walk the entire journey to a final result/certificate/report, using only menu items that exist and lead somewhere real — with no dead ends, no orphaned pages, and no step silently missing?"**

Output feeds directly into a follow-up task: one Mermaid flowchart document per Role × Event-type combination. Structure your findings so that step is trivial.

---

## PROMPT START

You are auditing a Laravel 11 multi-tenant SaaS called **multi-web** (Stancl Tenancy + Inertia.js + Vue 3 + Tailwind). It's a Sahodaya school-cluster management platform: Sahodayas (school clusters) register schools, schools register students, and the platform runs fest events (Kalotsav, Sports Meet, Kids Fest, Teacher Fest, Custom events) and MCQ exams, ending in results, ranklists, and certificates.

Do **not** produce generic advice. Every finding must cite the exact file + line, route name, or DB table, and state what a real user would experience (e.g. "clicking 'Results' in the student portal nav calls `GET /portal/mcq/results` → `McqController@results` → no Vue page named `Results.vue` exists in `Pages/Admin/Portal/Exam/` → Inertia throws a 500").

### Ground truth to use (do not re-derive, verify against current code and update if stale)

**Roles** (from `database/seeders/RolesAndPermissionsSeeder.php`, Spatie roles, guard `web`):
Platform-level: `superadmin`, `state_admin`, `state_staff`.
Tenant-level: `superadmin`, `sahodaya_admin`, `sahodaya_staff`, `state_admin`, `state_staff`, `school_admin`, `school_principal`, `school_vice_principal`, `school_event_coordinator`, `school_finance_coordinator`, `school_training_coordinator`, `school_mcq_coordinator`, `school_kalotsavam_coordinator`, `school_sports_coordinator`, `school_staff`, `mark_entry_admin`, `mark_entry_coordinator`, `judge`, `student`, `teacher`, `exam_controller`, `exam_staff`, `group_admin`, `house_admin`, `fest_ops`, `registration_coordinator`, `sahodaya_finance`, `certificate_collector`, `data_entry`, `event_coordinator`.

Re-run `grep -n "firstOrCreate(\['name'" database/seeders/RolesAndPermissionsSeeder.php` first — if the list has changed since this prompt was written, use the current list and note the diff.

**Event / program types** (from `resources/js/support/sahodayaPrograms.js`, `schoolProgramNav.js`): Kalotsav, Sports Meet, Kids Fest, Teacher Fest, Custom events. Plus two non-"fest" but equally end-to-end systems: **MCQ exams** and **Membership/Annual Registration**.

**Nav/sidebar source files** (`resources/js/support/`): `sahodayaAdminNav.js`, `schoolAdminNav.js`, `sahodayaEventNav.js`, `schoolEventNav.js`, `schoolProgramNav.js`, `studentPortalNav.js`, `teacherPortalNav.js`, `judgePortalNav.js`, `festOpsPortalNav.js`, `festCoordinatorPortalNav.js`, `groupPortalNav.js`, `houseAdminPortalNav.js`, `examPortalNav.js`, `eventHeadNav.js`, `sahodayaEventNavPermissions.js`, `filterNavGroups.js`. These are the literal, complete set of menus a real user sees — treat them as the map to walk, not the routes file.

### Method

1. **Build the role → nav-file → landing-dashboard map.** For each role above, identify which nav file(s) render for it and which `Dashboard.vue` / layout it lands on after login. If a role has no nav file mapped to it anywhere, that itself is a finding (an orphaned/unused role, or a role whose UI was never built).

2. **For every (role, event-type) pair that is plausible in the real world** — e.g. `student × Kalotsav`, `judge × Sports Meet`, `school_mcq_coordinator × MCQ`, `sahodaya_admin × Membership` — walk the journey stage by stage using this common skeleton, marking each stage ✅ complete / ⚠️ partial / ❌ missing / 🚫 not-applicable-for-this-role:

   - **Login / access** — correct role lands on correct dashboard; correct nav renders; no other role's menu leaks through.
   - **Onboarding / setup** — registration window open, eligibility/payment gates, any required setup before the event stage is reachable.
   - **Registration / enrollment** — into the event, exam, or membership year.
   - **Configuration** — rounds, items, categories, question papers, schedules (admin-side stages only).
   - **Execution** — the event/exam actually happening: mark entry, exam session, judge scoring, attendance.
   - **Review / approval** — any human-in-the-loop gate between execution and publishing (e.g. mark verification, dispute window).
   - **Publishing / results** — ranklist, scoreboard, MCQ result, certificate generation.
   - **Post-result** — downloads, appeals/re-check requests, reports, archival, next-cycle rollover.

   For each stage: does a menu item exist that reaches it? Does the route resolve to a real controller action? Does that action return a Vue page that exists on disk? Is there an authorization check that actually matches the role (not just `auth` middleware)? If any link in that chain is missing, mark the earliest broken link and don't assume later stages work.

3. **Flag menu/reality mismatches explicitly**, separate from missing stages:
   - Menu item exists, route 404s.
   - Menu item exists, route resolves, but the controller method is a stub / returns hardcoded data / throws `NotImplementedException`-equivalent.
   - Page/feature fully built and working, but **no menu item links to it** (orphaned functionality — just as much a UX bug as a dead link, but shows up nowhere in the nav audits since those only check existing nav entries).
   - Two different roles reach what should be the same underlying data through different pages that have diverged (e.g. Sahodaya admin's ranklist view and School admin's ranklist view showing different columns/statuses for the same event).
   - A stage that exists for one event type (e.g. Kalotsav has a certificate template step) but is silently absent for a sibling event type (Sports Meet) with no note of why.

4. **Cross-check against what's already known** — read `EVENT_PAGES_PLAN.md`, `NAV_AUDIT_FINDINGS.md`, `UIUX_AUDIT_FINDINGS.md`, `SETUP_WORKFLOW_AUDIT_REPORT.md`, `FULL_AUDIT_REPORT.md`, `FULL_GAP_ANALYSIS.md` first. Don't re-report anything already logged there verbatim — instead, in your output, mark it `(known — see FILE)` and spend your effort on **journey-shaped gaps those audits wouldn't surface**: i.e., things only visible when you walk a full role's journey start-to-finish rather than auditing one screen or one route group at a time.

5. **Public / unauthenticated journeys** count too: public results pages, public school directories, public event schedules — trace what a visitor with no login can reach and whether it's complete (e.g. can the public see a published Kalotsav ranklist without logging in, or does that route require auth even though it's meant to be public?).

### Output format

Produce **one findings document**, organized so each section maps 1:1 to a future flowchart doc:

```
## <Role> — <Event type>
**Landing dashboard:** <file>
**Nav source:** <file>

| Stage | Menu path | Route | Controller#method | Vue page | Status | Note |
|---|---|---|---|---|---|---|
| Login/access | ... | ... | ... | ... | ✅/⚠️/❌/🚫 | ... |
| ... | | | | | | |

**Orphaned functionality found:** (pages/routes with no menu entry)
**Cross-role inconsistencies found:**
**Verdict:** Complete / Has gaps (list the earliest broken stage) / Not applicable
```

Order sections: Sahodaya Admin journeys first (one per event type + membership), then School Admin/staff-role journeys, then each Portal role journey, then public/unauthenticated journeys. End with a **summary matrix**: rows = roles, columns = event types, cells = ✅/⚠️/❌/🚫, so the gaps are visible at a glance before anyone reads the detail sections.

Save the result as `USER_JOURNEY_AUDIT_FINDINGS.md` in the project root, matching the naming convention of the other audit pairs already in this repo.

## PROMPT END
