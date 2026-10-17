# AUDIT 03 — Results, Appeals and Certificates

## 1. Scoring model

### 1.1 Official point table is already encoded

The most important finding in this document: the 2026 manual's scoring table is already present in
the repository, in [config/fest_confed_kalotsav_scoring.php](../config/fest_confed_kalotsav_scoring.php).
Its docblock states it is derived directly from the manual's "CALCULATION OF POINTS" tables and
applies at both Sahodaya and State level per the manual's own instruction.

| Component | Individual | Group |
|---|---|---|
| Grade A | 5 grade points | 10 grade points |
| Grade B | 3 | 6 |
| Grade C | 1 | 2 |
| 1st place | 5 | 10 |
| 2nd place | 3 | 6 |
| 3rd place | 1 | 2 |
| **Total, A/1st** | **10** | **20** |
| **Total, A/2nd** | **8** | **16** |
| **Total, A/3rd** | **6** | **12** |
| **Total, B/1st** | **8** | **16** |
| **Total, B/2nd** | **6** | **12** |
| **Total, B/3rd** | **4** | **8** |
| **Total, C/1st** | **6** | **12** |
| **Total, C/2nd** | **4** | **8** |
| **Total, C/3rd** | **2** | **4** |

Grade bands: A ≥ 70, B ≥ 60, C ≥ 50. `appeal_fee` is 3000.

This means the compliance question in this area is not "is the formula missing" but "is the encoded
formula correct against the manual PDF". The encoded formula is self-consistent and is asserted in
three test files (`FestGradePointServiceBreakdownTest`, `FestPointRuleRecalculationTest`,
`StateGradingAndEligibilityTest`, `FestPublicScoreboardTest`). Because the manual PDF could not be
retrieved in this audit, the formula is **UNVERIFIED against source**, not verified.

### 1.2 There are three separate scoring tiers, deliberately

`StateGradePointService` resolves points in precedence order, and the docblock explains why:

1. A State-local `StatePointRule` / `StateGradeBand` row.
2. The event's `scoring_preset`.
3. `fest_confed_kalotsav_scoring`.

The docblock records a real bug that was fixed: before the third tier existed, this service read
`scoring_preset` and **every mark resolved to zero points and no grade, silently**. A Sahodaya
ranking of A/1st would score nothing. That class of failure mode — a silently-empty table rather
than an error — is now guarded by tier 3.

This is worth recording as a verified strength: the fallback chain is explicit, and
`StateGradePointService::sourceFor()` tells a screen which tier is in force rather than leaving it
to be guessed.

### 1.3 The Sahodaya default table is a different thing

`config/fest_default_kalotsav_grading.php` is a *different* table. Its docblock states it is only
read by `FestEventSettingsController::applyDefaultKalotsavGrading()` to seed per-event-editable
`FestGradeConfig` / `FestPointRule` rows — it is not consulted live at scoring time. Its numbers
(A/1st individual = 10, group = 20) match the confed table, but its grade bands are
A ≥ 70, B ≥ 60, C ≥ 50, plus a "No Grade" fallback below 50.

And `config/fest_mcs_scoring.php` is a third table, for a different Sahodaya's own custom rules.
Its docblock explicitly says do not merge the two.

**Risk:** three tables with overlapping semantics. An admin who edits one thinking it is the other
will produce silently wrong results. Mitigated by the docblocks, which are unusually good, but
this is a P2 maintainability finding.

### 1.4 Precision and rounding

- Marks are `decimal:2` (`FestMark::$casts`, `StateFestMark::$casts`, `StateJudgeScore::$casts`).
- Grades resolve on a percentage or score band, and the code prefers an explicit `grade` on the
  mark over a band-derived one (`$mark->grade ?: $this->gradePoints->resolveGradeFromScore(...)`).
- Points are integers.

No rounding step was found that could produce a float drift. The `decimal:2` cast plus integer
points is the right shape.

### 1.5 Duplicate score submission

Row-level locking plus an idempotent re-submit path on judge score entry. Verified by code
inspection and passing tests.

### 1.6 Incomplete-mark guard

`StateResultPublicationService::publish()` refuses to publish unless every approved registration
has a score:

```php
if ($scoredRegistrationIds->count() < $approvedRegistrationCount) {
    throw new HttpException(422, "State mark entry is incomplete (...).");
}
```

And it refuses with no approved registrations at all. Both are the correct guards, and both are
good news for launch: results cannot be published from partial data.

## 2. Ranking, ties, and grade resolution

From `StateResultPublicationService::publish()`:

```php
$ordered = $itemMarks->sortByDesc(fn (StateFestMark $mark) => (float) $mark->score)->values();
$previousScore = null;
$previousPosition = 0;

foreach ($ordered as $index => $mark) {
    $score = (float) $mark->score;
    $position = $previousScore !== null && $score === $previousScore
        ? $previousPosition
        : $index + 1;
    ...
}
```

Equal scores share the same position. This matches the Kalotsav convention (two students with the
same score both get 3rd; the next is 5th, not 4th). Verified correct by inspection.

School rankings tie-break on: points desc, then firsts desc, then seconds desc, then thirds desc,
then school name asc. The name-based final tiebreak is deterministic, which matters more than being
"right" — it means a re-run produces the same order.

**UNVERIFIED:** whether the manual specifies a different championship tie-break (for example, a
judge's combined score, or a head-to-head rule). The manual is needed to answer this.

## 3. Group events and points deduplication

`FestMark::deduplicationKey()` is the mechanism that prevents a 6-member group from scoring six
times. Its logic, and the reasoning it documents:

- Group items with a `group_id` on the participant → one points row per group.
- Group items without a `group_id`, but with a school and item → one points row per
  (school, item, chest).
- Individual items → one points row per participant, **never** collapsed by `group_id`, because a
  stray `group_id` on an individual's row (left over from a family or batch registration flow)
  would otherwise silently merge two different students from the same school into a single result.

That last point is a real bug that was found and defended against. It is the kind of defect that
would be invisible in a small test and catastrophic at State scale, where a school's championship
position would silently lose points.

On the State side, `StateResultPublicationService` decides individual vs. group by
`FestTeamSquadRules::isMultiPerson(...) || $mark->registration?->participants()->count() > 1`, so a
group scores group points. Verified by inspection.

**UNVERIFIED:** the manual's exact treatment of a group where one member is substituted after
publication, and whether a substituted member is certificate-eligible. The
`State/Fest/Substitutions.vue` page exists; the rule is manual-dependent.

## 4. Appeals

### 4.1 Two levels exist

| Level | Route | Controller | Roles |
|---|---|---|---|
| Sahodaya | `/admin/events/{event}/appeals/*` | `FestAppealController` | Sahodaya admin |
| State | `/admin/state/fest/{event}/appeals*` | `StateFest*` | State admin |

Sahodaya appeals have a richer feature set than State appeals: wildcard entry
(`appeals/wildcard/students`, `appeals/wildcard`), fee tracking (`mark-fee-paid`), and resolution
(`appeals/{appeal}/resolve`). State appeals have submit and decide only.

### 4.2 State appeal model

`StateAppeal` fields: `grounds`, `evidence_path`, `fee_amount`, `fee_status`, `status`,
`review_notes`, `outcome_summary`, `decided_at`. Open statuses are `submitted` and `under_review`.

So: grounds captured, evidence file upload supported, fee tracked, decision notes captured,
decision timestamped. That is the data the manual needs.

### 4.3 Effect on results and points — implemented, and carefully

`StateAppealService` (in `app/Services/State/Fest/StateAppealService.php`) does substantially more
than record a decision. Four verified behaviors:

**a) Outcome validation.** An appeal is either `upheld` or `dismissed`, and a decision with blank
notes is refused:

```php
if (blank($data['notes'] ?? null)) {
    throw ValidationException::withMessages(['notes' => 'Record the reasons for the decision.']);
}
```

The comment states the reasoning plainly: a decision without reasons cannot be defended later, and
an appeal is precisely the thing that gets questioned.

**b) Fee treatment follows outcome.** `fee_status` becomes `refunded` on uphold and `forfeited` on
dismissal — except when the fee was already `waived`, which stays `waived`. That is the correct
three-state handling, and the comment says so explicitly.

**c) An upheld appeal takes the result off public view.** `reopenResult()` sets
`StateItemResult` to `PROVISIONAL` and nulls `published_at`, so the corrected ranking is not
published by accident. Critically, it refuses to do this to a locked result:

> A locked result is left alone: locking is a deliberate statement that the result is final, and an
> appeal against it needs a person to unlock it first rather than the system doing so quietly.

That is exactly the right call. It prevents an appeal from silently un-publishing a finalized
result while still letting the workflow correct one that is not final.

**d) Certificates are marked stale.** `certificates->markItemStale($event, $appeal->item_id,
'Appeal upheld')` — so already-issued certificates for that item are flagged rather than left
showing a superseded ranking.

**e) Every change is audited.** `adjustMark()` writes a `StateConductAudit` row with `kind => 'mark'`,
the previous score, the new score, the reason, and the deciding user. `decide()` writes one with
`kind => 'result'`, `value_from` = the appeal id, `value_to` = the outcome.

**f) Mark adjustment is gated.** `adjustMark()` refuses to change a mark unless the appeal is
already `upheld` — "Uphold the appeal before changing a mark under it." The ordering is enforced in
code rather than left to convention.

So the earlier concern that appeals do not affect results was **wrong and is withdrawn**. What
remains genuinely unverified is narrower:

- **No automatic recalculation.** `adjustMark()` is a manual, per-participant action. After an
  upheld appeal, a human must adjust the mark. Nothing recomputes positions, grades, or points
  across the item automatically. The appeal takes the result off public view and flags
  certificates, which is the safe half; the recalculation is the human's job. This is defensible
  design (appeals are rare and judgment-heavy) but it is a **P2 operational finding**: the launch
  checklist should state explicitly that every upheld appeal requires a manual re-rank, or the
  State results page should show a "pending re-rank" banner.
- **School and Sahodaya championship points** after an upheld appeal: not recomputed. Same root
  cause, same severity.

### 4.4 Verified against the manual, and the gap it exposes

The manual has since been retrieved (`refs/Kalotsavam_Manual_2026.txt`), so three items above are
now resolved. Rule C.1 states verbatim:

> "Appeals on genuine grounds shall be entertained, if submitted by the participant within one hour
> of the announcement of result to the Chairman of the appeal committee. The appeal shall be
> submitted along with an appeal fee of Rs. 3000/-. Appeal shall be filled only by the Principal of
> the concerned school or the Team manager. In the case of State Kalotsavam the appeal shall be duly
> counter signed by the concerned Sahodaya head/ official delegate."

Resolved from that text:

- **Appeal window.** One hour. Still not implemented (P1-03 in AUDIT_10). No window field exists on
  `StateAppeal`, and no submit path computes it.
- **Who may file at State level.** The manual is explicit: the Principal or the **Team manager**, and
  at State level it must be counter-signed by the Sahodaya head. The code's submit route sits behind
  `state.fest:appeals` middleware, so it is role-gated, but there is no counter-signature field and
  no distinct team-manager filer. **P2.**
- **Reopen.** No reopen path. Correct: the manual says the committee's decision is final and binding.

Still open: the committee quorum of four (manual §22) is not modeled.

### 4.5 Appeals cannot be raised for a group or team item — P1 finding

This is the most serious appeal gap found, and it is not visible from the route or controller names
alone, so it is worth tracing in full.

**The manual expects group appeals.** Rule C.1 names the *Team manager* as a filer, and §15 and §16
are written throughout in terms of "participants/teams". A group item's result is as appealable as an
individual's.

**Sahodaya level.** Both submission paths bind an appeal to a single student:

- `PortalFestAppealController::storeStudent` validates `participant_id` and then asserts
  `$participant->student_id === $student->id`. A group registration's first participant resolves to
  one student, so the form can be filled, but the appeal is filed against that one member, not the
  team. There is no way to say "this team's result is wrong."
- `FestAppealController::storeWildcard` validates a single `student_id`, and
  `FestAppealWildcardService::grantSahodayaSlot` grants the slot by creating exactly one
  `FestParticipant`. For a group item this produces a one-person group.

**State level.** Same shape. `StateCertificateController::submitAppeal` accepts a free-text
`participant_name` plus optional `participant_id` and `registration_id`, with **no** check that the
registration is individual. A group registration can be appealed, but the appeal identifies no member
and carries no notion of the team as the affected party.

The result is that a group appeal is either rejected, or accepted against an arbitrary member and
then mis-decided.

**Downstream, the grant path is worse.** `FestAppealWildcardService::grantStateSlot` builds its
payload at line 155 as:

```php
'participant_type' => $item->participant_type,
'participants'      => [],
```

For a group item this sends `participant_type = 'group'` with an empty member list. The State receives
a group registration with no members. There is no route, controller action, or service method to add
members to it — the State's group-management surface (`StateTeamController`) only sets a leader or
marks a standby on participants that already exist.

**Why `adjustMark` does not rescue it.** `StateAppealService::adjustMark` resolves the mark by
`participant_id`. A group item's mark is stored against the registration's first participant, which
is how `StateJudgeScoreService::save` enforces it ("Score the team or group once using its first
listed participant"). So the mark can be adjusted, but the appeal record never says which team, and
nothing in the audit row does either.

**Required.** A group or team appeal needs a team identity on the appeal (`registration_id` first,
member as secondary), a filing path that accepts the team manager, and a wildcard grant that builds
the full member list rather than an empty array. Until then, group-item appeals are effectively
unsupported at both levels.

## 5. Certificates

### 5.1 What exists

- Models: `StateCertificate`, `StateCertificateBatch`.
- Print view: `resources/views/state/print/certificate.blade.php`, plus `sheet.blade.php` and
  `cards.blade.php` for related print output.
- Public verification: `resources/views/state/public/verify.blade.php`.
- Tests: `StateAppealCertificateTest` exists.

### 5.2 Required-by-manual vs. present

The manual's certificate requirements (which types, what fields, signature authority, numbering
scheme, QR verification) are **UNVERIFIED** — the manual is unavailable. What can be said from
code:

- State-level identity is available (`state_id`, `state_program_id`).
- School and Sahodaya identification is available (`sahodaya_name` on the intake, `school_name` on
  entries).
- Event/category is available (`item_id`, `item_code`, `item_name`).
- Duplicate issuance is not obviously guarded: no dedupe check was read on
  `StateCertificate` creation. **P2 finding** — worth a unique index on
  `(state_event_id, registration_id, certificate_type)`.
- Reissue after correction: no path found. **P1/P2 finding.**
- Withdrawn results: if a mark is unpublished or its registration is rejected, does an
  already-issued certificate get invalidated? No code read. **P1 finding** given that
  appeals can change results.

## 6. Cross-Sahodaya correctness

The audit brief asks to test "multiple Sahodayas containing schools with similar names and
overlapping local student IDs."

The data model handles this correctly **by construction**: the join keys are
`(source_tenant_id, source_event_id, source_registration_id)`, and `source_tenant_id` differs
between Sahodayas, so two students with the same admission number at the same-named school in two
different Sahodayas are two distinct State participants. The State side groups standings on the
resolved `sahodaya_id`, not on `school_name`.

**What is not tested:** there is no test fixture that sets up two Sahodayas with colliding local
IDs and asserts the State side keeps them distinct. `AUDIT_09` scenario 6 specifies it; it is
**NOT WRITTEN**. This is a P1 test gap, not a code defect, but it is the single highest-value test
to add before launch because it covers the failure mode that would be hardest to detect in
production and impossible to fix retroactively.

## 7. Summary of findings in this document

| ID | Finding | Severity | Evidence |
|---|---|---|---|
| F-03-01 | Upheld appeal does not auto-recalculate positions/grades/points; manual `adjustMark()` per participant | P2 | `StateAppealService::adjustMark()` is manual; no consumer recomputes item ranks |
| F-03-02 | No certificate dedupe constraint read; stale-marking exists but reissue path not found | P2 | `markItemStale()` exists; no unique index or reissue method read |
| F-03-03 | No appeal window/deadline gating found | P1 | No window check in submit routes |
| F-03-04 | Three overlapping scoring tables, one live, two seed-only/custom | P2 | `fest_confed_kalotsav_scoring`, `fest_default_kalotsav_grading`, `fest_mcs_scoring` |
| F-03-05 | Multi-Sahodaya colliding-ID test not written | P1 | No such test in `tests/Feature/State` |
| F-03-06 | Point formula encoded but unverified against manual PDF | P1 | `config/fest_confed_kalotsav_scoring.php` |
| F-03-07 | School/Sahodaya championship points not recomputed after upheld appeal | P2 | No recalculation service found |

**Corrected during audit:** an initial reading suggested appeals had no effect on results. That was
wrong. `StateAppealService` reopens the item result, marks certificates stale, gates mark
adjustment on the uphold, and audits every change. Withdrawn and recorded here so the correction is
on the record.
