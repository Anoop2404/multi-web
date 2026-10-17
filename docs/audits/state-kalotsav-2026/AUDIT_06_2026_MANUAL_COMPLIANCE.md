# AUDIT 06 — 2026 Manual Compliance

## Source

The Confederation of Kerala Sahodaya Complexes Kalotsavam Manual 2026 Edition was retrieved from
the official download page (`https://www.confedsahodaya.com/download`). It is 40 pages; the text
layer extracts cleanly to 2,166 lines, and the grade table, point tables, and appeal rules quoted
below are verbatim from it. The full text has been saved to `docs/audits/state-kalotsav-2026/refs/Kalotsavam_Manual_2026.txt` for
future reference.

The download page describes the 2026 edition as a restructured set of "Guidelines, Criteria and
Instructions for Conducting School Level, Sahodaya Level and State Level Kalotsavam", applying from
the 2026-27 academic year, with several guidelines, eligibility conditions, evaluation criteria,
and administrative instructions revised, clarified, consolidated, or newly added. That is
consistent with what the repo already encodes: `config/fest_confed_kalotsav_scoring.php` cites
"Keralam State Sahodaya Kalotsavam Manual 2026" by name and states it applies at both levels.

One caution about the repo's own notes: the working-memory entry `reference_frd08_docs.md` points to
FRD-08 planning docs and flags that a gap-analysis doc was stale as of 2026-08-18. The manual itself
is now on disk, so any gap analysis older than that should be re-derived against the PDF, not
trusted.

## Compliance matrix

| Manual § | Rule | Code location | Status | Required action |
|---|---|---|---|---|
| B-1 (p13) | Merit certificates for 1st/2nd/3rd with ≥50% marks, individual and group | `StateCertificateService` (read but not exhaustively cited) | **VERIFIED IN CONFIG, NOT TESTED IN CODE** | Add a unit test that asserts a State mark of 60% in a 1st-place slot generates a merit certificate |
| B-2 | Participation certificates to all non-podium finishers | Same | **PARTIAL** | Confirm the certificate generator's filter for "not 1/2/3" |
| Grade A | ≥70% = 5 grade pts individual, 10 group | `config/fest_confed_kalotsav_scoring.php` | **VERIFIED MATCH** | — |
| Grade B | 60-69% = 3 individual, 6 group | same | **VERIFIED MATCH** | — |
| Grade C | 50-59% = 1 individual, 2 group | same | **VERIFIED MATCH** | — |
| Below 50% | No grade, no points | same | **VERIFIED MATCH** | — |
| 1st place | +5 individual, +10 group | same | **VERIFIED MATCH** | — |
| 2nd place | +3 individual, +6 group | same | **VERIFIED MATCH** | — |
| 3rd place | +1 individual, +2 group | same | **VERIFIED MATCH** | — |
| Trophy to schools: 1/2/3 overall and per category, + 1st in each Sahodaya | `FestTrophyService` | **PRESUMED MATCH** (not deeply audited) | `FestTrophyService` was listed in the service tree, not read in full |
| Trophies to Sahodayas: 1/2 in every category + 1/2 overall | `StateResultService` aggregates per Sahodaya | **PARTIAL** — Sahodaya aggregation works, but trophy table not verified | Read `StateResultService` to confirm 1/2 per category trophy calc |
| C-1 (p14) | Appeal filed within **one hour** of result announcement | `StateAppeal` model, no deadline field, no window check | **NOT IMPLEMENTED** | **P1**: add `appeal_window` to `FestStateProgram` (or `StateFestEventItem`), enforce in `submitAppeal` |
| C-1 | Appeal by Principal/Team Manager; State appeals counter-signed by Sahodaya head | Sahodaya routes accept by Sahodaya-side users; State submit route role-gated | **PARTIAL** — no counter-signature field, no team-manager filer | Add a `counter_signed_by` field and a distinct filer role |
| C-1 | Appeals may be filed for **group and team items** (rule names "participant/team" and the "Team manager") | Both levels bind an appeal to a single student; `FestAppealWildcardService::grantStateSlot` sends `'participants' => []` for group items | **NOT IMPLEMENTED** | **P1**: team identity on the appeal + a wildcard grant that populates the member list. See AUDIT_03 §4.5 |
| C-1 | Appeal fee **Rs. 3000** | `config/fest_confed_kalotsav_scoring.php` `appeal_fee = 3000` | **VERIFIED MATCH** | — |
| C-2 | Sahodaya head delegate needs attested letter to sign | Not in code | **NOT IMPLEMENTED** | **P2**: document or upload evidence path |
| C-3 | Appeal committee: quorum 4, members (a-d) | Single `decided_by_user_id` on `StateAppeal` | **NOT MODELED** | **P2**: either add committee-membership model, or document that the operator IS the committee chair acting for the committee |
| C-4 | Fee forfeited on rejection | `fee_status = 'forfeited'` on dismiss | **VERIFIED MATCH** | — |
| C-5 | Fee refunded on uphold | `fee_status = 'refunded'` on uphold | **VERIFIED MATCH** | — |
| General 1 | One school = one Sahodaya at State level | Tenant parent linking in `Tenant` model | **PARTIAL** — model has parent field; enforcement not deeply audited | Confirm a school cannot be linked to two Sahodayas at registration time |
| General 2 | Registration through one Sahodaya only | `source_tenant_id` on intake entries | **VERIFIED** (by construction) | — |
| General 6 | **Max 5 single items + 2 group items per student; of 5 single only 3 stage** | `FestParticipationPolicy.max_events_per_student` etc. exist as schema | **NOT ENFORCED** in any service found | **P1**: write the enforcement; manual rule 6 is a hard cap |
| General 7 | Lower category cannot compete in higher | Not in code search | **NOT VERIFIED** | Audit `FestRegistration` validation |
| General 8 | Identity card issued by committee compulsory | `StateFestParticipant` has no ID-card field | **PARTIAL** | Decide: model the ID card, or rely on chest numbers? |
| General 9-10 | Common group items (irrespective of category/sex) | `participant_type` and `is_common` on items | **VERIFIED** by data model | — |
| General 11 | Even one entry gets grade, prize, points | `StateResultService::computeItem()` runs regardless of count | **VERIFIED** | — |
| General 13 | Sahodaya may frame rules in accordance with State manual | Not in code | **DOCUMENTED** elsewhere | — |
| General 14 | **Only one participant/team from a school per Sahodaya-level item** | Not found in State code | **NOT ENFORCED** at State level | This is a Sahodaya-level rule, enforced at Sahodaya, not State |
| General 15 | **Only 1st and 2nd place qualify from Sahodaya to State**, with **max 2 per Sahodaya per item**; **English One Act Play max 1 per Sahodaya** | `StateParticipationLimitService` docblock: "qualify_count = 2" is the State two-slots-per-item rule, "max_per_school is an explicit override for items that qualify fewer (English One Act Play is top-1)" | **VERIFIED MATCH** | — |
| General 16 | Sahodaya General Convener prepares the winners list | `FestStateSlotCollectionService` is the Sahodaya-side mechanism | **VERIFIED** | — |
| General 17 | Late-registered participants not entertained | `reg_start`/`reg_end` on items | **PARTIAL** — fields exist; closure not deeply read | Confirm registration window closure behavior |
| General 18 | Mark sheets, tabulation, result sheets in safe custody of Sahodaya convener | Operational, not code | **N/A** | — |
| General 19-20 | All decisions by core committee | `FestStateProgram` has `core_committee` and roles | **PARTIAL** | — |
| Court Appeals (24) | **Court-directed entries get grades/prizes but NO points to school or Sahodaya** | `StateResultService::ranking()` does not filter on `is_wildcard` or `court_order` flag | **NOT IMPLEMENTED** | **P1**: gate the school/Sahodaya aggregation on the registration's wildcard/court-order flag, leaving the per-item grade/prize intact |
| Substitutes (25) | **2 standbys per group, no on-stage substitution, certificates to on-stage performers only, no participation certificate to standbys, no registration fee for standbys** | `StateFestParticipant.is_standby`, `StateFestTeamService`, `isCompeting()`, `exclude_standbys_from_limits` flag | **PARTIAL** — the data model and a few guards are present; on-stage certification, certificate exclusion, and fee exclusion not directly read | **P1**: confirm `StateCertificateService` excludes standbys and that the fee calculator excludes them |
| Substitutes (25) | Substitution only in case of emergency | Not in code | **NOT ENFORCED** | **P2**: at least log the substitution with a reason field |
| Promotion (28) | **If 1st or 2nd place declines, Sahodaya may promote the next position; the State Committee only approves the forwarded list** | `FestStateSlotCollectionService::respond(opt_out)` with the auto-fill chain | **VERIFIED MATCH** at the slot-collection level | — |
| Reporting (29a) | Arrive 30 min early; ID card | Operational | **N/A** | — |
| Reporting (29c) | Perform when chest number called | `Fest/ChestNumbers.vue` | **VERIFIED** | — |

## Numerical verification: point table against config

Manual states (individual items, verbatim from p13 of the PDF):

> 1st Place: 5 grade + 5 place = 10
> 2nd Place: 5 grade + 3 place = 8
> 3rd Place: 5 grade + 1 place = 6
> 1st B: 3 grade + 5 place = 8
> 2nd B: 3 grade + 3 place = 6
> 3rd B: 3 grade + 1 place = 4
> 1st C: 1 grade + 5 place = 6
> 2nd C: 1 grade + 3 place = 4
> 3rd C: 1 grade + 1 place = 2

Config states (`config/fest_confed_kalotsav_scoring.php`):

```
'individual_points' => [
    'A' => ['1' => 10, '2' => 8, '3' => 6],
    'B' => ['1' => 8, '2' => 6, '3' => 4],
    'C' => ['1' => 6, '2' => 4, '3' => 2],
],
```

Match: **9 of 9 cells exact**. Group: same shape, double the numbers. **Match: 9 of 9 cells
exact**. The docblock on the config is correct.

## Top compliance gaps in priority order

1. **Manual rule 24 — court-order entries not excluded from school/Sahodaya points.** This is a
   P1, because the manual is explicit that court-order entries "will not be added to the school or
   to the Sahodaya in deciding the toppers of the Kalotsavam" and this is currently not enforced
   in any code path that was read.
2. **Manual rule 6 — per-student 5-single/2-group limit not enforced.** The schema fields exist on
   `FestParticipationPolicy` (`max_events_per_student`, `max_onstage_per_student`,
   `max_offstage_per_student`, `max_group_per_student`) but no service was found that reads them at
   registration time. P1.
3. **Manual rule C-1 — one-hour appeal window not gated.** The model has no window field, and no
   submit route was read that computes it. P1.
4. **Manual rule C-1 — group and team items have no appeal path.** Both levels bind an appeal to a
   single student, and the wildcard grant sends an empty participant list for a group item. The
   manual names the *Team manager* as a filer, so group appeals are expected to work. P1, and the
   most serious of the appeal gaps. Full trace in AUDIT_03 §4.5.
5. **Manual rule 25 — standby treatment is partial.** Standbys are recognized in the data model
   and used for limit exclusion and team-size checks, but certificate exclusion, fee exclusion, and
   on-stage performer identification were not confirmed. P1.
6. **Manual rule 15 — top-1 override for English One Act Play.** The docblock describes the
   override, but no test was found asserting the override wins. P2.
7. **Manual rules 7, 8, 17, 28 — minor** (category direction, ID card, registration window closure,
   promotion documentation). P2.

## Where the manual's language is silent and the code must decide

- **What counts as a "wildcard" / "court-order" registration.** The code uses `appeal_type` on
  `FestAppeal` (`sahodaya_wildcard`, `state_wildcard`), but there is no separate "court order"
  flag. The `sahodaya_wildcard` plus a `meta.court_order = true` boolean is one option; a
  dedicated `court_order` column is another. **Decision needed from the team.**
- **How the appeal committee membership is recorded.** The manual names four roles; the code
  records one decider. Either add a committee model or document that the decider is acting for the
  committee. **Decision needed.**
- **What happens to a school's championship points when a wildcard entry is included.** Manual
  says "shall not be added to the school". Does that mean (a) zero points credited to school, but
  the entry's mark still appears in the per-item result, or (b) the entry is treated as
  exhibition entirely? The code's `participation_type` field is the right place to encode this.
  **Decision needed.**

## Verdict on manual compliance

The platform has read and encoded the manual's central numerical rule (the point table) and the
appeal fee. Most of the operational manual rules are reflected in the data model and the
workflows, with notable exceptions around court-orders, per-student item caps, the appeal window,
group-item appeals, and standby certificates. None of the gaps is a structural blocker, but the
per-student cap, the appeal window, and group-item appeals are rules a State inspector would expect
to see enforced, and the court-order points exclusion is a fairness rule the manual is explicit about.
