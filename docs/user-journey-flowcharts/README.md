# User Journey Flowcharts — Index

Generated from `USER_JOURNEY_AUDIT_FINDINGS.md` (repo root). Each file is one role's complete login-to-result journey, as a Mermaid flowchart per event type, with a stage-by-stage table and known issues. Legend used throughout: ✅ complete · ⚠️ partial/caveat · ❌ missing/broken · 🚫 not applicable.

Read `../../USER_JOURNEY_AUDIT_FINDINGS.md` first for the full narrative audit — these docs are the flowchart-shaped version of the same findings, one file per role instead of one long report.

## Sahodaya tier

- [superadmin](superadmin.md) — platform/tenant oversight, no event-type breakdown
- [state-admin](state-admin.md) — state_admin + state_staff; Kalotsav/Sports rollup only, gap on Kids Fest/Teacher Fest/Custom/MCQ
- [sahodaya-admin](sahodaya-admin.md) — full operational owner; gaps on Custom events (hidden nav) and MCQ (no certificates)
- [sahodaya-staff](sahodaya-staff.md) — view-only across every module by design
- [registration-coordinator](registration-coordinator.md) — fest registrations only, no membership access despite the name
- [event-coordinator](event-coordinator.md) — near-full fest execution, no finance/MCQ/membership
- [sahodaya-finance](sahodaya-finance.md) — fest + Sahodaya-wide ledger; gap on ledger-account linking
- [certificate-collector](certificate-collector.md) — cleanest-scoped role in the audit
- [data-entry](data-entry.md) — marks entry; over-broad nav visibility from an extra permission grant
- [mark-entry-admin](mark-entry-admin.md) — marks entry; **flags the dead-code login-routing bug**

## School tier

- [school-admin](school-admin.md) — school_admin + school_principal + school_vice_principal; Custom events and Training both have major gaps
- [school-event-coordinator](school-event-coordinator.md) — scoped landing; non-deterministic + dead-end-loop login bugs
- [school-domain-coordinators](school-domain-coordinators.md) — **the 5 roles that can't log in at all** (release-blocking bug)
- [school-staff](school-staff.md) — permission-gated view, working as designed

## Portal tier

- [student](student.md) — includes the results/certificates-per-event-type summary table
- [teacher](teacher.md) — Teacher Fest + MCQ question-bank authoring
- [judge](judge.md) — Kalotsav/Kids Fest/Teacher Fest scoring; correctly N/A for Sports Meet
- [mark-entry-coordinator](mark-entry-coordinator.md) — includes the mark_entry_admin dead-code callout
- [exam-portal-roles](exam-portal-roles.md) — exam_controller + exam_staff; flags the misleading "Mark entry" nav link for exam_staff
- [group-admin](group-admin.md) — **zero post-result visibility, the clearest missing-stage finding for any portal role**
- [house-admin](house-admin.md) — complete, no gaps found
- [fest-ops](fest-ops.md) — all duty types, most granular server-side scoping of any portal role

## Public / unauthenticated

- [public-journeys](public-journeys.md) — site, school-application flow, login/reset, event schedules & results, school directory, certificate verification (the one fully clean public journey)

## Reading these diagrams

Every diagram uses the same 8-stage skeleton: Login/access → Onboarding/setup → Registration/enrollment → Configuration → Execution → Review/approval → Publishing/results → Post-result (public journeys use a shorter Discovery → Content/Info → Data/Result → Action → Post-action skeleton instead). Stages that don't apply to a role are marked 🚫 rather than omitted, so you can see at a glance whether something is missing versus simply irrelevant to that role.
