# School UI & Workflow Rebuild — Phase 1 Plan

**Status:** Proposed  
**Date:** 2026-08-13  
**Primary user:** School admin  
**Supporting users in test flows:** School principal, vice principal, school staff, event coordinator, Sahodaya admin  
**Technical baseline:** Laravel 13, Inertia 3, Vue 3, Tailwind 4, Playwright

## 1. Phase 1 outcome

Phase 1 will deliver one coherent, responsive school-admin experience and one fully tested school implementation journey from first login through ongoing program participation.

The work is not a visual reskin of 837 school-admin routes. It establishes a reusable application shell, a canonical information architecture, shared workflow/page patterns, and representative end-to-end coverage across every school-facing module. Existing routes and business rules remain stable unless a confirmed blocker prevents the journey.

At the end of Phase 1, a school administrator must be able to:

1. Sign in and understand what must be completed next.
2. Configure the school, classes, people, roles, and access.
3. Complete annual membership and track its approval/payment status.
4. Maintain student and teacher records.
5. Participate in a Fest program from event selection through registration, payment, event-day operations, results, and downloads.
6. Register for Talent Search and Teacher Training and track their outcomes.
7. Manage academic results, communications, records, and the optional website.
8. Complete every critical workflow on desktop and mobile without dead ends, duplicate navigation, hidden required steps, or unexplained status values.

## 2. Evidence from the current codebase

The current platform already has significant functional depth:

- 837 routes under `school-admin/{tenantId}`.
- Seven Fest program entries: Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, Science Fest, and Custom Events.
- Context-specific navigation already exists for membership, Fest programs, individual events, Talent Search exams, and training.
- Reusable UI components already exist for page headers, workflow steppers, forms, empty states, pagination, confirmation dialogs, status pills, and dashboard cards.
- Existing Playwright coverage audits a representative page catalog, but it does not yet execute a complete school lifecycle.

Confirmed planning concerns to address:

- Main navigation mixes entities, workflows, utilities, and settings; several destinations are duplicated in different groups.
- Payments appear under both School and Membership.
- Fest Hub and individual program links compete as two entry models.
- Reports are fragmented across multiple hubs.
- Required setup, pending actions, and current status are not expressed as one consistent system.
- Some existing audit findings are stale because later work fixed them; implementation must verify the live screen before changing it.
- Five specialized school coordinator roles are assignable but were reported as lacking a valid post-login destination. This must be re-tested and treated as a release blocker if still present.
- Custom Events and some downstream Training capabilities have historically been thinner than the six dedicated Fest programs. Phase 1 must clearly label unsupported stages rather than presenting false parity.
- The current feature-flag implementation is global, not tenant-scoped.

## 3. Scope

### In scope

- School-admin application shell: sidebar, top bar, mobile navigation, breadcrumbs, context header, page container, and user menu.
- Canonical school menu and terminology.
- Dashboard rebuilt as a role-aware action and status hub.
- Shared page and workflow patterns used across all school modules.
- School implementation/onboarding journey.
- Representative UI migration in every module listed in section 7.
- Navigation, permissions, loading, empty, success, validation, error, and locked-state behavior.
- Desktop, tablet, and mobile behavior.
- Deterministic seed data and Playwright workflows.
- Accessibility and visual-regression gates for the new shell and critical screens.
- A global rollback flag until tenant-scoped feature flags exist.

### Out of scope

- Rewriting all controllers, routes, services, or database models.
- Renaming internal route prefixes such as `mcq`; user-facing terminology will change while URLs stay compatible.
- Rebuilding Sahodaya, State, student, teacher, judge, or operations portals except for the minimum supporting actions needed to complete a school journey test.
- Creating missing Custom Event functional parity solely for visual consistency. Missing functionality becomes a separately approved product workstream.
- Replacing all 837 route views during Phase 1. Low-frequency pages inherit the new shell and shared patterns, then migrate incrementally.
- A tenant-by-tenant UI rollout before tenant-scoped feature flags are implemented.

## 4. Design and workflow principles

1. **Next action first:** every hub shows current status, blocking reason, next action, owner, and deadline.
2. **One destination, one name:** avoid duplicate links and terminology such as Membership / Annual Registration / Registration for the same concept.
3. **Progressive disclosure:** the main sidebar contains stable hubs; deep task links appear only inside the relevant workspace.
4. **Context is never lost:** program, event, academic year, school, and workflow status remain visible while working.
5. **Status must explain itself:** every status pill includes plain-language meaning and the next possible transition.
6. **Safe mutations:** destructive or irreversible actions use consistent confirmation, loading, success, and recovery behavior.
7. **Responsive by default:** tables must have a deliberate small-screen treatment; navigation and critical actions cannot disappear at mobile breakpoints.
8. **Permission-aware UI:** hidden, disabled, and read-only states must match server authorization. A visible link must not lead the current role to a 403.
9. **Route stability:** Phase 1 changes navigation and presentation first; legacy URLs redirect or continue working.
10. **Test the workflow, not only the page:** a successful GET response is not proof that a school can complete a task.

## 5. Proposed information architecture

### Main sidebar

```text
HOME
  Dashboard

GET STARTED                         conditional; disappears when complete
  School setup
  Setup progress                    badge shows incomplete count

PEOPLE & ACCESS
  Students
  Teachers
  Houses
  Users & roles
  Profile requests                  badge shows pending count
  Import history

MEMBERSHIP
  Membership overview
  Annual submission
  Documents

PROGRAMS
  Events & competitions             single parent hub
  Talent Search
  Teacher Training

ACADEMICS
  Board Results
  Question Papers                   permission/feature gated

FINANCE & RECORDS
  Payments & receipts               one global payment history
  Reports                           one school-wide report entry
  Program calendar
  Activity log

COMMUNICATION
  Circulars
  Notifications                     unread badge

WEBSITE                             feature gated
  School Website
  Enquiries                         badge when pending

SYSTEM
  Settings
```

### Navigation rules

- `Get Started` appears only while required setup is incomplete.
- Programs remain visibly locked, with a reason and link to the required membership step, instead of disappearing.
- `Events & competitions` is the only main-sidebar Fest entry. Kalotsav, Sports Meet, Kids Fest, Teacher Fest, English Fest, Science Fest, and Custom Events are cards/filter options inside that hub.
- `Payments & receipts` is a single global ledger/history destination. Membership, event, Talent Search, and Training workspaces retain contextual `Pay now` and `View invoice` actions.
- Reports have one school-wide entry with filters for module, academic year, event, and report type. Event-specific reports remain available inside the event workspace.
- Website content types remain inside the Website hub rather than becoming 10+ permanent sidebar entries.
- Search includes visible and permitted deep links, but required actions must never depend on search.

### Contextual workspace navigation

The main sidebar remains stable. Opening a complex workflow adds a contextual workspace header and workflow tabs rather than replacing the user's entire mental model.

**Membership**

```text
Overview → School profile → Students → Counts → Teachers → Documents → Payment → Submission status
```

**Fest program**

```text
Program overview → Available events → My registrations → Results → Qualifiers → Reports
```

**Individual Fest event**

```text
Overview → Event registration → Item registration → Billing & payment
         → ID cards / schedule → Requests → Event-day view → Results / certificates
```

Sports-specific stages appear inside the same model as explicitly named additions, such as age eligibility, athlete registration, winner submission, records, and championship.

**Talent Search exam**

```text
Overview → Register students → Registered students → Fee & payment → Hall tickets → Results → Reports
```

**Teacher Training**

```text
Overview → Register teachers → Fee & payment → Attendance → Certificates → Reports
```

Any stage not supported by the backend must display `Not available for this program` with an explanation; it must not link to a dead or misleading page.

## 6. Application shell specification

### Sidebar

- 240 px desktop width; 288 px mobile drawer.
- School identity and environment/academic-year context at the top.
- Collapsible groups with persisted state.
- Active parent and active child indication.
- Badges only for actionable counts.
- Footer contains signed-in user, role, help, and sign out.
- Keyboard-accessible focus order, Escape-to-close drawer, focus return to trigger.
- WCAG AA text contrast; do not use low-opacity text for essential labels.

### Top bar

- Mobile menu button.
- Breadcrumbs and page title.
- Current academic year/context switcher where relevant.
- Global search/command entry.
- Notifications with unread count.
- User menu; no duplicate sign-out action.
- Contextual primary action on larger screens; sticky bottom action area on mobile forms when needed.

### Page pattern

Every migrated page uses:

1. Breadcrumbs.
2. Page title, concise description, status, and primary action.
3. Optional workflow stepper or contextual tabs.
4. Main content with consistent maximum width and spacing.
5. Explicit loading, empty, error, locked, and permission-denied states.
6. A consistent action footer for forms.
7. A success confirmation that tells the user what changed and what to do next.

### Shared components to consolidate

Reuse and strengthen the existing components instead of starting a second design system:

- `PageShell`, `PageHeader`, and `BreadcrumbTrail`.
- `WorkflowStepper` and module-specific adapters.
- `FormField`, `FormGrid`, `FormSection`, and `FormActions`.
- `EmptyState`, `ValidationBanner`, `FlashBanner`, and `ConfirmDialog`.
- `PaginationLinks`, `ActionsMenu`, `SearchableSelect`, and the existing data-table patterns.
- `DashboardStatCard`, `QuickActionCard`, and `TrackStatusPill`.

Add only the missing primitives:

- `SchoolAppShell` split into `SchoolSidebar`, `SchoolTopbar`, and `SchoolMobileNav`.
- `ContextWorkspaceHeader`.
- `ActionQueue`.
- `StatusSummary` with reason/owner/deadline/next action.
- `ResponsiveDataView` for table-to-card behavior.
- `LoadingSkeleton` and `InlineErrorState`.
- `UnsavedChangesGuard`.

## 7. School implementation journey and module coverage

### Stage 0 — Provision and access

**Flow:** tenant provisioned → school admin invited → first login/password change → correct school dashboard.

**UI deliverables:** login clarity, school identity, role label, first-login welcome, support path.

**Critical tests:** correct tenant isolation; invalid/inactive school blocked with useful copy; all supported school management/coordinator roles land on a valid permitted page.

### Stage 1 — School setup

**Flow:** school code → profile/contact → classes → academic-year context → required contacts → setup complete.

**UI deliverables:** setup checklist, completion percentage, dependencies, resume action, field-level validation, irreversible school-code warning.

**Critical tests:** incomplete setup keeps a visible next action; completed setup removes `Get Started`; refresh/resume preserves progress.

### Stage 2 — People and access

**Flow:** add/import students → review import → add teachers → create houses → provision portal access → assign roles/scopes → review profile requests.

**UI deliverables:** unified list/filter/action layout, bulk-action feedback, DOB/eligibility visibility, import results, credential status, role descriptions.

**Critical tests:** create/edit/search student; bulk import and open import history; create teacher; provision login; assign coordinator scope; approve/reject a profile request; cross-school IDs rejected.

### Stage 3 — Membership

**Flow:** open annual cycle → verify profile → update students/teachers/counts → upload documents → calculate fee → pay → submit → Sahodaya review → view receipt/status.

**UI deliverables:** one canonical stepper, deadline banner, validation summary linked to fields, fee breakdown, locked/rejected/resubmission states, status timeline.

**Critical tests:** blocked outside window; incomplete submission rejected with clear steps; proof upload; submit; supporting Sahodaya approval/rejection; school sees updated state and receipt.

### Stage 4 — Events and competitions

Use **Kalotsav as the canonical Fest path** and **Sports Meet as the exception path**. Validate the other dedicated programs through a shared contract suite rather than duplicating the full scenario six times.

**Canonical flow:** choose program/event → event registration → item registration → eligibility/quota checks → fee calculation → proof/payment → approval state → ID card/schedule → clash/substitution/food requests → results → certificates/reports.

**Sports additions:** age group/DOB eligibility, athlete registration, item registration, winner submission, sports results and records.

**Critical tests:** duplicate registration prevention; invalid eligibility blocked; quota feedback; totals recalculate; payment state round trip; locked registration behavior; result publication visibility; certificate/report download returns a valid file.

**Custom Events:** Phase 1 exposes only supported stages and records missing-parity work as a separate functional epic.

### Stage 5 — Talent Search

**Flow:** discover exam → inspect eligibility/deadline → register student/by class → pay → download hall tickets → view published results/toppers/reports.

**UI deliverables:** exam cards with current status/next action, eligibility explanation before selection, exam-scoped tabs, payment and publication gating.

**Critical tests:** ineligible student excluded/blocked; duplicate registration prevented; payment and hall-ticket gating; results hidden before publication and visible after publication.

### Stage 6 — Teacher Training

**Flow:** discover program → register teacher(s) or import → pay → attendance → certificate/report.

**UI deliverables:** program status cards, capacity/deadline messaging, registrant list, fee state, attendance/certificate availability.

**Critical tests:** register/cancel; bulk registration/import; duplicate/capacity validation; payment upload; attendance and certificate availability where supported.

### Stage 7 — Academics

**Flow:** select class/year → add/import board results → verify → principal review → reports/toppers/certification.

**UI deliverables:** clear class/year context, staged verification state, correction path, consistent report navigation.

**Critical tests:** Class X/XII separation; validation and duplicate handling; principal accept/return; reports use the selected year; PDF download succeeds.

### Stage 8 — Finance, records, and communications

**Flow:** review all payment statuses → open receipt/invoice → view calendar/deadlines → read/acknowledge circular → review notifications → audit activity.

**UI deliverables:** unified status/filter vocabulary, module deep links, unread/action badges, auditable confirmation.

**Critical tests:** totals reconcile with module records; filters persist in URL; circular acknowledgement updates; notification link reaches the correct context; audit entry appears after a tested mutation.

### Stage 9 — Website (feature gated)

**Flow:** configure branding/navigation → manage key content → preview → handle enquiries.

**UI deliverables:** one Website hub, setup state, preview/publish distinction, content status, enquiry badge.

**Critical tests:** feature disabled hides entry and blocks route; feature enabled supports edit/preview; unpublished changes are clear; public page reflects published content only.

## 8. Delivery workstreams

### Workstream A — Baseline and contracts

- Capture current desktop/mobile screenshots for critical pages.
- Generate a route-to-module inventory and identify visible, searchable, contextual, legacy, and orphan routes.
- Define a single terminology/status dictionary.
- Verify old audit findings against current code before opening implementation tasks.
- Record server authorization for every proposed navigation item.
- Add a global `SCHOOL_UI_V2` rollback switch. Document that it is global until tenant-scoped flags exist.

**Exit:** approved sitemap, workflow diagrams, screen inventory, design tokens, route compatibility map, and baseline test report.

### Workstream B — Shell and design system

- Split the existing `SchoolAdminLayout` into maintainable shell components.
- Implement the proposed main sidebar and top bar.
- Implement breadcrumbs, context workspace header, mobile drawer, badges, and command search.
- Consolidate existing primitives and document supported states.
- Migrate Dashboard, Students, and Membership Overview as reference screens.

**Exit:** shell passes desktop/mobile/accessibility tests and reference screens demonstrate all component states.

### Workstream C — Setup, people, and membership golden path

- Rebuild setup checklist and first-login resume experience.
- Standardize Students, Teachers, Houses, Users, Imports, and Profile Requests.
- Rebuild the membership journey and cross-tier status feedback.
- Fix any release-blocking login/landing or visible-link/403 mismatches discovered for school roles.

**Exit:** a newly seeded school can complete setup, people creation, and membership submission without direct URLs or developer intervention.

### Workstream D — Program workspaces

- Build Events & Competitions hub.
- Apply the canonical program/event workspace structure.
- Complete the Kalotsav journey and Sports exception journey.
- Apply shared contracts to Kids Fest, Teacher Fest, English Fest, and Science Fest.
- Migrate Talent Search and Teacher Training hubs/workspaces.
- Consolidate report entry points and clarify food-order/coupon terminology.

**Exit:** the program workflows have consistent navigation and complete transaction tests for canonical paths.

### Workstream E — Academics, operations, and website

- Migrate Board Results, Question Papers, Finance & Records, Communications, Settings, and Website hubs.
- Standardize filtering, pagination, downloads, and status terminology.
- Ensure each module exposes a clear return path and contextual next action.

**Exit:** every main-sidebar module has at least one fully migrated reference path and passes its module contract tests.

### Workstream F — Stabilization and pilot readiness

- Run full Playwright, Laravel feature/unit, accessibility, visual, and responsive suites.
- Perform a seeded pilot drill with school and Sahodaya roles.
- Resolve all P0/P1 defects; triage lower-priority visual debt.
- Document rollback, support scripts, known limitations, and Phase 2 backlog.

**Exit:** release checklist signed off and the old shell can be restored through the global flag without data changes.

## 9. Suggested sprint sequence

| Sprint | Focus | Demonstrable result |
|---|---|---|
| 0 | Inventory and UX contracts | Approved IA, live workflow map, baseline screenshots/tests |
| 1 | Shell and dashboard | Responsive sidebar/top bar, action-first dashboard, shared components |
| 2 | Setup, people, and membership | New school reaches submitted membership state |
| 3 | Events and competitions | Kalotsav end-to-end plus Sports-specific registration path |
| 4 | Talent Search, Training, Academics | Each module completes its primary school transaction |
| 5 | Finance, communications, website, stabilization | All main modules integrated; regression and pilot report complete |

Sprint length and staffing should be estimated after Sprint 0 inventory; route count alone is not a reliable effort estimate.

## 10. Test strategy

### Deterministic workflow fixtures

Create an idempotent E2E scenario seeder/command with:

- One Sahodaya and two schools to test isolation.
- An active academic year and membership window.
- A new school with incomplete setup.
- A school with submitted/approved membership.
- Students across classes, genders, and sports age boundaries.
- Teachers, houses, portal users, and scoped coordinator users.
- Open and closed Fest events, a Sports event, Talent Search exam, and Training program.
- Draft and published results.
- Pending, approved, rejected, and paid records.
- Website feature-on and feature-off test configurations where the global environment permits separate runs.

The seed command must be rerunnable and must not depend on production data.

### Playwright suites to add

| Suite | Purpose |
|---|---|
| `school-shell.spec.ts` | Sidebar grouping, active state, search, badges, breadcrumbs, mobile drawer, role menu |
| `school-first-login.spec.ts` | Provisioning, password/setup entry, resume behavior, coordinator landing |
| `school-people.spec.ts` | Student, teacher, import, house, user, scope, and profile-request transactions |
| `school-membership-journey.spec.ts` | Full annual membership round trip including supporting Sahodaya action |
| `school-fest-journey.spec.ts` | Kalotsav event/item/payment/result journey |
| `school-sports-journey.spec.ts` | DOB/age eligibility, athlete/item registration, winner/result path |
| `school-program-contracts.spec.ts` | Shared route/nav/status contract for all dedicated Fest programs |
| `school-talent-search-journey.spec.ts` | Eligibility, registration, fee, hall ticket, publication gating |
| `school-training-journey.spec.ts` | Registration, payment, attendance/certificate states |
| `school-academics-journey.spec.ts` | Results submission, review, reporting, download |
| `school-operations.spec.ts` | Payment reconciliation, circular acknowledgement, notifications, audit trail |
| `school-website.spec.ts` | Feature gating, edit, preview, publish/public verification |
| `school-permissions.spec.ts` | Principal, vice principal, staff, and coordinator visible-action/server-policy agreement |
| `school-responsive-a11y.spec.ts` | 390 px, tablet, desktop; keyboard/focus/labels/contrast checks |

### Test pyramid

- **Unit:** nav builders, active-route matching, status mapping, fee/eligibility display helpers.
- **Feature:** authorization, tenant isolation, validation, state transitions, route redirects, downloads.
- **E2E:** critical user journeys and cross-role round trips.
- **Visual:** shell, dashboard, key table/form/workflow pages at mobile and desktop sizes.

### CI gates

- No unexpected 4xx/5xx responses in a normal journey.
- No uncaught console errors or failed Inertia requests.
- No visible navigation destination returns 403 for the current role.
- No critical horizontal overflow at 390 px.
- Keyboard operation and visible focus for shell and critical forms.
- WCAG AA for essential text and controls.
- Laravel tests, production frontend build, and critical Playwright journeys pass.
- Screenshots/traces retained for failures.

## 11. Acceptance criteria

Phase 1 is complete only when all of the following are true:

1. The approved main menu is implemented with no duplicate primary destinations.
2. The sidebar, top bar, breadcrumbs, mobile drawer, page headers, status, and form behavior are consistent across migrated modules.
3. A new school completes the Stage 0–3 implementation journey from a clean seeded state.
4. The canonical Kalotsav and Sports journeys complete through downstream result visibility.
5. Talent Search, Training, Academics, Finance/Records, Communications, and Website each pass their primary transaction journey.
6. Every other dedicated Fest program passes the shared navigation/status/permission contract.
7. All supported school roles land on valid pages and see only permitted actions.
8. Required actions are visible without menu search.
9. Empty, loading, validation, locked, rejected, success, and server-error states are intentionally designed on critical workflows.
10. Desktop and 390 px mobile layouts pass the defined gates.
11. Existing URLs and bookmarks remain usable or redirect safely.
12. The global rollback flag restores the prior shell without reversing data.
13. Known functional gaps—especially Custom Events parity—are documented and not disguised by the new UI.

## 12. Measurement

Capture a baseline during Sprint 0, then compare:

- First-login-to-setup completion rate.
- Median steps and time to membership submission.
- Registration completion rate per program.
- Validation errors per completed submission.
- Dead-end, 403, and 500 response count in E2E journeys.
- Mobile horizontal-overflow and inaccessible-control count.
- Support requests categorized as `cannot find`, `do not understand status`, or `permission denied`.
- Percentage of main-menu modules covered by at least one transaction test.

Suggested Phase 1 targets:

- 100% of main-menu modules covered by a transaction-level E2E test.
- 0 visible-link/403 mismatches for tested roles.
- 0 P0/P1 defects in the release candidate.
- 0 critical mobile overflow issues on golden-path pages.
- At least 90% of pilot users complete setup and membership without assistance.

## 13. Risks and controls

| Risk | Control |
|---|---|
| Scope expands to all 837 routes | Migrate by shared patterns and representative workflows; keep a Phase 2 inventory |
| UI masks backend workflow defects | Add feature/state-transition tests before styling the affected step |
| Old audits lead to duplicate fixes | Reproduce every finding against the current branch first |
| Navigation changes break bookmarks | Preserve routes and add redirects/route contract tests |
| Role permissions drift from UI | Derive permission expectations from server policy and test both layers |
| Feature flag affects all tenants | Deploy only after staging drill; maintain immediate global rollback; plan tenant flags separately |
| Custom Events imply unsupported parity | Show only real capabilities and publish a separate functional parity backlog |
| Existing uncommitted work overlaps shell/nav | Reconcile ownership before editing shared files; avoid overwriting unrelated changes |

## 14. Phase 1 backlog priority

### P0 — Release blockers

- Tenant isolation or authorization bypass.
- Any supported school role unable to log in or landing in a redirect loop.
- Visible navigation leading to 403/404/500 in the normal workflow.
- Membership or program registration cannot be completed from the UI.
- Incorrect fee total, duplicate participant, or lost submission.
- Mobile user cannot access navigation or the primary submit action.

### P1 — Must complete in Phase 1

- New shell, menu, responsive behavior, and consistent page pattern.
- Setup/people/membership golden journey.
- Kalotsav and Sports transaction journeys.
- Talent Search, Training, Academics, Finance/Records, Communications, and Website primary journeys.
- Status vocabulary, report entry consolidation, and permission contract tests.

### P2 — Phase 1 if capacity allows

- Command palette refinements.
- Saved filters and personalized shortcuts.
- Extended visual migration of low-frequency pages.
- Performance tuning beyond identified slow golden-path pages.

### Phase 2 candidates

- Tenant-scoped feature flags.
- Full Custom Events parity.
- Remaining low-frequency page migration.
- Advanced dashboard personalization and cross-module analytics.
- Broader portal redesign for student, teacher, judge, exam, and operations roles.

## 15. First implementation tickets

1. Produce the school route/module/permission matrix from the live route list.
2. Approve terminology and the sidebar tree in section 5.
3. Create the idempotent school-journey E2E seeder.
4. Add coordinator login/landing regression tests and resolve confirmed failures.
5. Add the global `SCHOOL_UI_V2` rollback flag and shell selection point.
6. Extract `SchoolSidebar`, `SchoolTopbar`, and `SchoolMobileNav` from `SchoolAdminLayout`.
7. Build nav contract tests for visible links, active state, badges, and locked programs.
8. Rebuild the Dashboard as setup progress + action queue + deadlines + status summary.
9. Migrate Students and Membership Overview as reference list/workflow pages.
10. Implement the first complete E2E path: first login → setup → people → membership submission.

---

**Decision needed before implementation:** approve the proposed main-sidebar model—especially using a single `Events & competitions` hub instead of listing every Fest program permanently. All other Phase 1 work can proceed from the structure above once that information architecture is accepted.
