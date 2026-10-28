# School & Sahodaya model exams

Model exams reuse the existing `Mcq*` stack and student portal / exam-engine delivery. They are **not** Talent Search competitive flows.

## School model exams (Phase 1)

| Path | Purpose |
|------|---------|
| `/school-admin/{school}/model-exams` | Create / list school model exams |
| `/school-admin/{school}/model-exams/question-banks` | School-scoped banks (`school_id` set) |
| `/school-admin/{school}/model-exams/{exam}/paper` | Attach Ready banks, shuffle, partial credit |
| Student portal `/portal/student/{school}/mcq/...` | Same timed attempt + autosave path as Talent Search online |

### Data rules
- `conductor_level = school`, `tenant_id = parent Sahodaya`, `school_id = owning school`
- `fee_type = none`, `requires_hall_ticket = false`, default `multi_marking = partial`
- Talent Search hubs exclude `conductor_level = school`

## Question bank class / category scope

Banks may set `class_category_id` and/or `master_class_id` (create/edit on bank forms).

- **Unscoped** banks (both null) are broader and always attachable.
- Paper builders filter Ready banks to those matching the exam’s eligibility (`assignment_type` class/category), plus unscoped banks.
- Applies to school model papers and Sahodaya papers (Talent Search + Sahodaya model).

## HOD contribution / copy-into-private

On school **Question banks**:

1. **Association Ready banks** → **Copy to my school** — deep-clones into a school draft (`source_bank_id` set).
2. School Ready bank → **Contribute copy to Sahodaya** — creates a Sahodaya-owned draft (`school_id` null, `contributed_by_school_id` set). Sahodaya marks Ready for association papers.

Does not mutate the source bank. Complements exam-level `allow_member_banks` (attach live school banks without copying).

## Sahodaya association model exams (Phase 2)

Create with **Product mode → Sahodaya model exam** on `/sahodaya-admin/{id}/mcq-exams`.

- Fee / hall-ticket / payments / ledger / centre-code tabs are hidden; Talent Search exams keep them.
- Publish without a fee is allowed for model exams (Talent Search still requires a fee for assessment/competitive).
- **Allow member school banks** (Overview): off = only Sahodaya-owned Ready banks; on = member school Ready banks may be attached.
- Overview + Reports show membership/roster flags for inactive, unpaid, or empty-roster member schools.

## Scale (Phase 3)

- Config: `EXAM_ENGINE_FORCE_THRESHOLD` (default **500**). Online exams at/above this count get `settings_json.engine = engine` on publish/update (still must **arm** before T0).
- Automatable gates: `php artisan exam-engine:stage-gates --stage=A|B`
- Ops checklist (Project store): `docs/online-exam-stage-ab-checklist.md`
- Full Stage A/B load/chaos: [ONLINE_EXAM_RUNBOOK.md](ONLINE_EXAM_RUNBOOK.md) §6–§8 — needs DO / multi-node infra.

## Remaining (ops)

- Stage A/B load & chaos go-live on production-like infra (see checklist blockers).
