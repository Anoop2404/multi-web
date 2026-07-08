# State Flow Gap Audit

**Date:** July 2026  
**Status:** Pre-implementation baseline

## What Exists

| Component | Location | Capability |
|-----------|----------|------------|
| State roles | `state_admin`, `state_staff` | Login to `/admin` |
| State program master | `FestStateProgram` (central) | Create/publish programs |
| Propagation | `FestStateProgramService` | Push Sahodaya-level events to tenants |
| State Kalotsav views | `KalotsavStateController` | Read-only cluster status + qualifications |
| Same-tenant promotion | `FestQualificationService` | School → Sahodaya within one tenant DB |
| Kids Fest clusters | `FestKidsFestClusterService` | Multi-cluster scoreboard (Kids Fest only) |

## Gaps (Addressed By Implementation)

| Gap | Required | Solution |
|-----|----------|----------|
| Multi-region Sahodaya | Tirur/Manjeri separate scoreboards + overall | `conduct_mode=partitioned`, `FestPartitionService` |
| Registration routing | Hub UI, child event storage | `FestRegistrationRouterService` |
| MCS rules | Catalog, grades, fees, combos | MCS presets + `mcs_kalotsav_items.php` |
| Cross-Sahodaya State promotion | Cannot use tenant `FestQualification` | API + `fest_state_submission_outbox` |
| State execution DB | No State registration/marks workspace | State domain tenant + state DB migrations |
| State API intake | No qualifier submission endpoint | `StateQualifierIntakeController` |
| State admin workspace | Read-only pages only | State-domain controllers + models |

## Architecture Decision

- **Central:** program master, state domain registry, API credentials, propagation links
- **Sahodaya tenant DB:** regional events, marks, results, submission outbox
- **State tenant DB:** qualifier intake, registrations, marks, results, certificates
- **Communication:** authenticated APIs with idempotency keys and outbox retries
