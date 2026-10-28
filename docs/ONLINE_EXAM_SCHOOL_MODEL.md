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

## Sahodaya association model exams (Phase 2)

Create with **Product mode → Sahodaya model exam** on `/sahodaya-admin/{id}/mcq-exams`.

- Fee / hall-ticket / payments / ledger / centre-code tabs are hidden; Talent Search exams keep them.
- Publish without a fee is allowed for model exams (Talent Search still requires a fee for assessment/competitive).
- **Allow member school banks** (Overview): off = only Sahodaya-owned Ready banks; on = member school Ready banks may be attached.
- Overview + Reports show membership/roster flags for inactive, unpaid, or empty-roster member schools.
- Reports emphasize association rollup + school performance (fee exports stay Talent Search–only).

## Scale (Phase 3 gate)

- Config: `EXAM_ENGINE_FORCE_THRESHOLD` (default **500** enrolled). Online exams at/above this count get `settings_json.engine = engine` automatically on publish/update (still must **arm** before T0).
- Platform slot capacity / Stage A–B scale-out: [ONLINE_EXAM_RUNBOOK.md](ONLINE_EXAM_RUNBOOK.md), [ONLINE_EXAM_DEPLOYMENT.md](ONLINE_EXAM_DEPLOYMENT.md).
- Small school cohorts: Laravel portal path (`McqExamSessionService`) is fine.
- Do not push high concurrency through PHP draft autosave alone.

## Remaining

- Full Stage A/B ops go-live (load/chaos drills) — ops program.
- Richer bank contribution workflow (HOD attribution / copy-into-private) beyond `allow_member_banks`.
- CDN/media hardening checklist already in engine deploy docs.
