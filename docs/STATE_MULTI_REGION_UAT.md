# Multi-Region & State Flow UAT Scenarios

## 1. Standard single-region Sahodaya (regression)

| Step | Action | Expected |
|------|--------|----------|
| 1 | Create Kalotsav event without preset | `conduct_mode=standard` |
| 2 | Register school for off-stage item | Registration on same event |
| 3 | Enter marks, publish results | Scoreboard unchanged from before |
| 4 | No partition UI required | Levels page shows child spawn only |

## 2. MCS multi-region (Tirur + Manjeri + District)

| Step | Action | Expected |
|------|--------|----------|
| 1 | Apply `mcs_kalotsav` preset on hub | Tirur, Manjeri, District children created |
| 2 | Assign schools to regions | `fest_event_school_partitions` saved |
| 3 | School registers off-stage item | Registration stored in assigned region child |
| 4 | School registers on-stage item | Registration stored in district finale child |
| 5 | Enter marks per region | Regional scoreboards independent |
| 6 | View hub scoreboard | Sum of region + finale per `aggregation_config` |
| 7 | MCS combo validation | Accept 2 off+3 on OR 3 off+2 on; reject invalid |
| 8 | Submit qualifiers to State | Outbox row + API intake on State DB |

## 3. State domain workspace

| Step | Action | Expected |
|------|--------|----------|
| 1 | Configure `state_domains` + link program | Program has `state_domain_id` |
| 2 | Sahodaya submits qualifiers | `state_qualifier_intakes` + entries created |
| 3 | State admin reviews intake | Approve updates entry status |
| 4 | Create `state_fest_events` | State championship workspace draft |
| 5 | Retry failed outbox | `fest:process-state-outbox` resends |

## 4. Kids Fest backward compatibility

| Step | Action | Expected |
|------|--------|----------|
| 1 | Spawn cluster on Kids Fest hub | Works via `FestPartitionService` |
| 2 | Combined cluster scoreboard | Unchanged behavior |

## 5. Idempotency

| Step | Action | Expected |
|------|--------|----------|
| 1 | Submit same qualifier batch twice | Same `idempotency_key` returns existing intake |
| 2 | Outbox duplicate enqueue | No duplicate pending rows |
