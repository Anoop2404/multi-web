# School model exams (Phase 1)

School-owned MCQ model exams reuse the existing `Mcq*` stack and student portal / exam-engine delivery. They are **not** Talent Search.

## Product surface

| Path | Purpose |
|------|---------|
| `/school-admin/{school}/model-exams` | Create / list school model exams |
| `/school-admin/{school}/model-exams/question-banks` | School-scoped banks (`school_id` set) |
| `/school-admin/{school}/model-exams/{exam}/paper` | Attach Ready banks, shuffle, partial credit |
| Student portal `/portal/student/{school}/mcq/...` | Same timed attempt + autosave path as Talent Search online |

## Data rules

- `conductor_level = school`, `tenant_id = parent Sahodaya` (shared tenant DB), `school_id = owning school`.
- `fee_type = none`, `requires_hall_ticket = false`, default `multi_marking = partial`.
- Talent Search hubs exclude `conductor_level = school` so association fee/hall-ticket flows stay untouched.

## Scale

- Small school cohorts: Laravel portal path (`McqExamSessionService`) is fine.
- Large windows (~2k+ concurrent): use `exam-engine` per [ONLINE_EXAM_RUNBOOK.md](ONLINE_EXAM_RUNBOOK.md) and [ONLINE_EXAM_DEPLOYMENT.md](ONLINE_EXAM_DEPLOYMENT.md). Do not push high concurrency through PHP draft autosave alone.

## Phase 2+ (remaining)

- Sahodaya “model exam” product mode vs competitive Talent Search presets (hide unused fee/series UI).
- Association bank sharing policy (who may contribute / copy).
- Membership edge cases and association rollup polish for non–Talent Search association exams.
- Stage A/B ops gates and capacity booking for large school or Sahodaya online windows.
