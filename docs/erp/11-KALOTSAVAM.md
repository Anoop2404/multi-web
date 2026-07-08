# Phase 11 — Kalotsavam Specification

Kalotsavam shares the fest/event infrastructure with Sports but uses **cultural item** taxonomy, **judge scoring**, and **appeals**.

## 1. Festival Setup

| Config | Description |
|--------|-------------|
| fest_event | Kalotsavam edition |
| catalog sync | `SyncFestCatalog` command, `FestCatalogController` |
| levels | School → state propagation via `FestStateProgramPropagation` |
| judging mode | Single / multi-judge |
| appeal window | Days after publish |

---

## 2. Items and Taxonomy

- Items from `fest_catalog_items` with Kalotsavam taxonomy codes  
- Item head groups for mark coordinators  
- Subject/category linkage where applicable  

Services: `FestItemHeadService`, `FestHeadItemNavigationService`

**Legacy routes:** Retain all existing Kalotsav report URLs; catalogue marks duplicates as `alias`.

---

## 3. Judges and Scoring

| Concept | Implementation |
|---------|----------------|
| Judge assignment | Per item, per level |
| Judge portal | `JudgeDashboardController` |
| Score entry | `FestJudgeScoreService`, `FestJudgeScore` model |
| Gate | `FestJudgeGateService` — verified teacher only |

### Scoring rules

- Configurable max marks per item  
- Multi-judge: average or trim-mean (config)  
- Tie-break: item-specific order  

---

## 4. Registration

Same registration engine as sports:

- Eligibility: class category, gender, age where applicable  
- Individual and group items  
- Fee via fest school event fee + offline proof  

Controllers: `FestRegistrationController`, `FestEventStudentRegistrationController`

---

## 5. Scheduling

- Stage / room / time slot  
- Clash detection for same student across items  
- `FestItemScheduleService`

---

## 6. Appeals

**Hub:** `FestAppearsHubController` / appeals workflow

| Status | Flow |
|--------|------|
| submitted | School/coordinator files appeal |
| under_review | Discipline admin |
| upheld / rejected | Adjust results if upheld |
| closed | Final |

Audit all result changes post-publish.

---

## 7. Results & Publish

Pipeline: marks entered → verified by item head → published → appeal window → final

Public portal: `FestPortalController`, `Public/FestPortalController`

---

## 8. Certificates

- Merit / participation templates  
- Bulk by item/school  
- QR verification  

Service: `FestCertificateService`

---

## 9. Kalotsavam Reports (Extract)

| Report ID | Name |
|-----------|------|
| RPT-KAL-001 | Item-wise registration |
| RPT-KAL-002 | School participation summary |
| RPT-KAL-003 | Judge assignment list |
| RPT-KAL-004 | Score sheet (item) |
| RPT-KAL-005 | Tabulation sheet |
| RPT-KAL-006 | Rank list by item |
| RPT-KAL-007 | School trophy points |
| RPT-KAL-008 | Appeal register |
| RPT-KAL-009 | Schedule by stage |
| RPT-KAL-010 | Group item participants |
| RPT-KAL-011 | Fee collection kalotsav |
| RPT-KAL-012 | Unpublished marks pending |
| RPT-KAL-013 | Certificate issue log |

---

## 10. Notifications

Email triggers: registration window open/close, schedule publish, results publish, appeal outcome.

Templates: see Phase 15.

---

## Implementation References

- `KalotsavEvent`, `KalotsavResult` models (legacy retain)  
- `FestMarkCoordinatorController`, `StateFestProgramController`  
- `FestEventReportAnalyticsService`, `FestSchoolReportAnalyticsService`  

Next: [12-MCQ.md](12-MCQ.md)
