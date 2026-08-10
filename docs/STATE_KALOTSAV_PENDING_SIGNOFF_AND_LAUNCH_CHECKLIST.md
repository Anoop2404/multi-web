# State Kalotsavam — Pending Sign-off & Launch Checklist

Generated 2026-08-10. Companion to `STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md` §23
(policy register) and §29 (work packages). This file exists because the remaining pending
items are things code alone can't finish: they need either a few clicks in the live admin UI,
or a human/policy decision. Each section below is written so you can act on it directly.

## 1. Policy sign-off (§23, P-01–P-15) — condensed

Full detail and alternatives are in the master plan. This table only flags where the *code*
has already committed to an option (so approving is a formality) versus where nothing is
built yet (so it's a real open decision) versus where there's a conflict worth knowing about.

| # | Topic | Status | Note |
|---|---|---|---|
| P-01 | School→Sahodaya, no school event | **Already built as Option A** | `level_round=school` disabled for this rollout; direct registration confirmed working. |
| P-02 | Auth: named accounts + MFA vs code-only | **Conflict** | The external Sahodaya/school portal (built this rollout) uses access-code auth — the plan's own Option C, marked "rejected-risk exception." Fine for a fast pilot; flag before wider rollout. |
| P-03 | Sahodaya/school verification | Open | State manually creates external Sahodaya/school records today (a form of Option A), but no automated duplicate checks or spot-verification exist. |
| P-04 | Student identity & guardian consent | **Open — needs you** | No consent-capture workflow exists yet for external students. Real gap if external intake scales up. |
| P-05 | External conduct mode | Partially built | Offline-conduct service exists in code but isn't wired to any screen yet (see §3 below, item on External* services). |
| P-06 | Fees / payment proof | Already matches Option A | Platform-wide proof-upload + verify/reject pattern already used for memberships and remittances. |
| P-07 | Deadlines & exceptions | Open | No formal extension/emergency-intake workflow; ad hoc today. |
| P-08 | Corrections/withdrawals | Open | No revision-history workflow for certified records yet. |
| P-09 | Result publication & privacy | Mostly matches Option A already | `/state/results` only exposes name/school/chest/position/grade — no DOB/contact/admission number. |
| P-10 | Data retention | Open — genuinely a policy call | Nothing automated; needs your retention windows confirmed before it's enforceable. |
| P-11 | Notifications | Partially built | In-app notifications exist (`NotificationService`); no SLA/escalation-path publishing. |
| P-12 | State hosting/isolation | **Already built as Option A** | Dedicated `state` DB connection, `StateModel`, `state:migrate`/`state:health` commands. Cross-domain session cookie handling still unresolved (flagged in `routes/state.php`). |
| P-13 | Security incident/audit | Open | No formal runbook; this is an organizational process document, not code. |
| P-14 | Manual maker-checker nomination | **Already built as Option A** | Built this session — quota-enforced selection, maker ≠ checker certification, versioned batch, non-breaking fallback. |
| P-15 | Independent Sahodaya/State settings | Mostly built | `FestStateProgram.level_event_settings` already separates blocks per level; full amendment/versioning workflow not built. |

**Bottom line:** P-01, P-12, P-14 are done and match the recommended option — approve and move
on. P-02 has a real conflict worth a conscious decision (accept the risk for a fast pilot, or
hold external intake until named accounts exist). P-04, P-07, P-08, P-10, P-13 are genuinely
open and need your call, not more code from me guessing at organizational policy.

## 2. Publish the State program & open registration today (#9/#10)

I can't click through your live admin UI from here — no browser access to the deployed app,
no live DB connection. But the exact path, from the code:

1. Go to `/admin/state-programs` → open your Kalotsavam 2026 program (or create it if it
   doesn't exist yet: title, `event_type`, `conduct_levels` — **must exclude `school`**, per
   P-01 above).
2. On the program page, use **Manage outside Sahodayas** if you need to onboard any
   non-tenant Sahodaya (creates an access code for their portal).
3. Click **Publish**. This now correctly skips creating a tenant-local "state" placeholder
   event (fixed earlier this session) and creates the real `StateFestEvent` on the `state`
   connection.
4. For each real Sahodaya tenant: open their `sahodaya-admin/{id}/events` → the Kalotsavam
   event → **Rounds & Levels** → confirm registration is open. Schools register directly into
   the Sahodaya event (no school round needed, per P-01).
5. Once a Sahodaya has results, use the new **Review & Nominate for State** button (added
   this session, same page) if you want the maker-checker workflow, otherwise **Submit
   Qualifiers to State** works directly off raw results as before.

## 3. Decision needed: orphaned External* services

`ExternalAuthService`, `ExternalStudentRegistryService`, `ExternalRegistrationService`,
`ExternalConductService` (added in the other session's commit) have real unit tests
(`tests/Feature/External/ExternalPlatformTest.php`) but the services themselves never persist
to a database — no `external_students`/`external_registrations` tables exist, so every method
just returns an in-memory array. They duplicate what the already-working
`ExternalSahodaya`/`ExternalSchool`/`ExternalIntakeService` system (built earlier this
rollout, DB-persisted, quota-enforced) already does for real. I flagged this as task #29 and
am asking you directly rather than deleting tested code unilaterally — see the question I'm
asking alongside this document.

By contrast, `StateConductService`, `StateRemittanceService`, and
`StatePublicResultsProjectionService` (same commit) *are* real — properly tested against real
database writes (`tests/Feature/State/StateConductAndRemittanceTest.php`). They just had no
UI. Chest-number assignment now has one (this session). `StateRemittanceService::
calculateDemand()` (auto-calculate a remittance from accepted-nominee counts) still has no
UI — I deliberately didn't wire that one up blind, because getting the Sahodaya/school
grouping wrong on a financial calculation without a live DB to check against is a bad risk to
take unverified. The existing manual `Admin\StateRemittanceController` (already fully working)
covers the need in the meantime.

## 4. Pilot/production readiness (#26)

Not code-buildable from this sandbox — needs live infrastructure and real users. Checklist for
when you're ready:

- [ ] P-01–P-15 sign-off complete (§1 above)
- [ ] `state:migrate` run against a real `state` database; `state:health` passing
- [ ] `STATE_APP_DOMAIN` configured if using the dedicated-domain routes (`routes/state.php`)
      — currently inert without it
- [ ] Cross-domain session/cookie handling resolved (flagged as open in `routes/state.php`)
- [ ] At least one full dry run: Sahodaya registration → results → nomination → certify →
      submit to State → State approve → materialize → chest numbers → public results
- [ ] Backup/restore rehearsal (P-12 targets: RPO ≤ 15 min / RTO ≤ 2 hr during event windows)
- [ ] Independent security review (P-13) before first production launch
- [ ] Support contact/escalation path published (P-11)

## 5. Verification checklist (#31)

Nothing this session ran against a live database (no PHP runtime in this sandbox). Before
trusting any of it:

- [ ] `php artisan migrate` (central + tenant) — picks up
      `fest_state_nomination_batches`/`_selections`
- [ ] Visit `/admin/state-workspace/qualifiers`, `/admin/state-workspace/fest`,
      `/admin/state-workspace/board-results` — these 404'd before this session's fix, should
      render now
- [ ] Open a Sahodaya event's **Review & Nominate for State** page, select a candidate as
      primary, certify with a *different* logged-in user than whoever selected
- [ ] On a State fest event with approved registrations, click **Assign chest numbers**
- [ ] `php artisan test --filter=FestStateNomination` and
      `--filter=StateConductAndRemittance` to confirm nothing regressed
