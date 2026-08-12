# State Kalotsavam — Pending Sign-off & Launch Checklist

Generated 2026-08-10, updated 2026-08-11. Companion to `STATE_KALOTSAV_MASTER_IMPLEMENTATION_PLAN.md`
§23 (policy register) and §29 (work packages). This file exists because the remaining pending
items are things code alone can't finish: they need either a few clicks in the live admin UI,
or a human/policy decision. Each section below is written so you can act on it directly.

## 0. Full gap reconciliation (2026-08-11)

A more thorough 9-category audit was supplied and checked against the code. It's accurate —
one item (candidate-pool aggregation, 2.2 below) was verified as a real bug and fixed on the
spot. This section is now the authoritative "what's actually left" list; §§1–5 below still
stand but are narrower slices of the same picture. Legend: ✅ done, 🟡 partial, ⬜ not started,
🚫 not code (infra/policy/pilot).

**1. State infrastructure**
- ⬜ `state_kalotsav` database doesn't exist locally / `state:health` fails — this can only be
  fixed on your machine (`state:migrate`, then check the DB connection in `.env`); I have no
  live DB access from this sandbox to diagnose further.
- 🚫 Production domain, DNS/TLS, session handling across the state domain, queues, storage,
  backups — all real infrastructure work, not buildable here.

**2. Regional nominations**
- ✅ *(fixed just now, commit `1f5323b`)* Candidate pool only read marks off the hub event
  itself — for a region-wise Sahodaya without a re-competing Finale, every Region child
  event's winners were silently excluded. Now expands to all partitions, same as the direct
  qualifier-submission path already did.
- 🟡 Direct raw-mark submission when no certified nomination exists — this is an intentional
  fallback (`FestStateQualifierPayloadBuilder::entriesFromCertifiedNomination()` returns null
  when no batch exists, direct-mark path takes over), not a bug, but it does mean nomination
  isn't actually *mandatory* the way P-14 implies it should be end-state. Worth a decision:
  keep permissive (what let registration open without waiting on this feature) or make
  nomination a hard gate once every Sahodaya has adopted the workspace.
- ⬜ State eligibility / per-student cap enforcement — only the per-item quota is enforced
  today (`FestStateNominationService::select()`); a student nominated twice across different
  items isn't caught.
- ⬜ Withdrawal/replacement/revision workflow — `unselect()` marks a row withdrawn but there's
  no "promote a reserve to primary" or "replace after certification" flow yet.

**3. External Sahodaya flow**
- 🚫 OTP checkpoint added then reverted same day (2026-08-11) — decided code-only per P-02.
- ⬜ No named user/membership model — access code alone identifies the record, no persisted
  login/account, no second factor.
- ⬜ No real external student registry, individual/team registration tables, or payment-proof
  workflow — schools still enter qualifier-level details directly (name/item/position/grade)
  rather than registering as students first, per §2.2's original design tradeoff.
- ⬜ No Region/Phase conduct, marks, results, or appeals for external orgs.

**4. Qualifier API and scrutiny**
- ⬜ Signed body/timestamp/batch-revision contract — the outbox (`FestStateSubmissionOutbox`)
  has an idempotency key and content hash, but no request signing or replay-window contract.
- ⬜ Shallow validation, no mixed per-entry accept/reject/return — `StateQualifierReviewController::approve()`
  approves the whole intake at once; there's no per-entry decision UI.
- ⬜ Correction/supersession/evidence-review lifecycle — not built.

**5. Team handling**
- ⬜ Full roster (leader/members/standby) isn't preserved through nomination or materialization
  — `FestStateNominationSelection` and `StateFestParticipant` both model one participant per
  row, no team-roster structure.

**6. State event conduct**
- ⬜ Scheduling, venues, stages, judge panels, attendance, double-verified mark entry,
  provisional results, appeals, final certification/points/trophies, certificates, official
  reports — none of this exists yet. `StateConductService` currently only does chest-number
  assignment; `state_fest_marks` table exists in the schema but nothing writes to it.

**7. Payments**
- 🟡 `StateRemittanceService::calculateDemand()` takes a manually-supplied accepted-count,
  it doesn't query real accepted State entries itself — flagged in §3 below as deliberately
  not wired blind (grouping schools under the right Sahodaya for a financial figure needs a
  live DB to verify against).
- ⬜ Team/item fee breakdown, partial payments, adjustments, credits, immutable proof history
  — the existing `Admin\StateRemittanceController` handles single lump-sum demands with
  proof upload/verify/reject; it doesn't itemize or track partial payment history.

**8. Region and Phase**
- ⬜ `advancement_mode` exists on the program model but registration routing still uses
  stage/team heuristics rather than reading it directly.
- ⬜ Region→Finale promotion incomplete.
- ⬜ Phase lifecycle (`FestPhaseLifecycleService`, added by the other session) isn't wired
  into the conduct pipeline — its own docblock says this is deliberately future work.

**9. Production approval**
- 🚫 Policy sign-off, managed/external pilot, security/load/concurrency testing, backup and
  restore rehearsal, finance/privacy/State-authority approval — all human process, not code.

## 1. Policy sign-off (§23, P-01–P-15) — condensed

Full detail and alternatives are in the master plan. This table only flags where the *code*
has already committed to an option (so approving is a formality) versus where nothing is
built yet (so it's a real open decision) versus where there's a conflict worth knowing about.

| # | Topic | Status | Note |
|---|---|---|---|
| P-01 | School→Sahodaya, no school event | **Already built as Option A** | `level_round=school` disabled for this rollout; direct registration confirmed working. |
| P-02 | Auth: named accounts + MFA vs code-only | **Decided: code-only** | Email+OTP checkpoint (`ExternalPortalOtpService`, added 2026-08-11) was reverted 2026-08-11 — access code alone is the credential again, same shape as the manual's own "Sahodaya heads get a password" process. No persisted external-user login, no second factor. Accepts the P-02 risk (URL is a bearer credential) for a fast pilot rather than holding external intake on named accounts. |
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
- [ ] Open an external Sahodaya/school portal link with a valid access code — confirm it opens
      directly (access-code-only, no OTP step, per the 2026-08-11 P-02 reversal)
- [ ] `php artisan test --filter=FestStateNomination` and
      `--filter=StateConductAndRemittance` to confirm nothing regressed
