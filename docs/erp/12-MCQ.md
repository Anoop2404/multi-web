# Phase 12 — MCQ Specification

## 1. Competition Setup

| Config | Description |
|--------|-------------|
| mcq_exam / program | Container per year |
| tiers / levels | Class or category based |
| question banks | `McqQuestionBank` per tier |
| exam window | Start/end datetime |
| duration | Minutes per attempt |
| passing score | Optional |

**Controller:** `McqExamController`

---

## 2. Registration

| Step | Actor |
|------|-------|
| Window open | Sahodaya config |
| Student select | School MCQ coordinator |
| Eligibility | Verified student + class tier match |
| Submit | School |
| Approve | Sahodaya if required |

Service: `McqEligibilityService`, model `McqRegistration`

---

## 3. Fee (Offline)

- School-level fee invoice: `McqSchoolFee`  
- Proof upload → finance verify → receipt + email  
- Service: `McqFeeLedgerService`, `McqSchoolFeeService`  
- Late fee: columns on mcq fee config + `LateFeeCalculator`

---

## 4. Hall Ticket

Generated after fee verified (or free tier):

- PDF with exam date, venue, student photo, STU code  
- Bulk by school  
- Email optional to guardian  

---

## 5. Attendance

Exam day check-in via ops portal or school coordinator mark present before allowing exam start.

---

## 6. Exam Delivery

**Student portal:** `StudentMcqController`

| Rule | Detail |
|------|--------|
| Auth | STUB student = STU login |
| Timer | Server-side expiry |
| Questions | Randomized order per student |
| Autosave | Periodic answer save |
| Submit | Final; no reopen unless admin reset |

Anti-cheat: IP log, single session, audit `mcq.exam.started`, `mcq.exam.submitted`

---

## 7. Results

| Mode | Description |
|------|-------------|
| Auto | Score on submit from answer key |
| Manual override | Coordinator with audit |
| Publish | Visible in school + student portal |
| Rank | Tier-wise rank list |

---

## 8. MCQ Reports

| Report ID | Name |
|-----------|------|
| RPT-MCQ-001 | Registration by school |
| RPT-MCQ-002 | Registration by tier |
| RPT-MCQ-003 | Fee collection MCQ |
| RPT-MCQ-004 | Hall ticket issued |
| RPT-MCQ-005 | Attendance sheet |
| RPT-MCQ-006 | Exam session log |
| RPT-MCQ-007 | Result sheet tier-wise |
| RPT-MCQ-008 | Rank list |
| RPT-MCQ-009 | Question analysis |
| RPT-MCQ-010 | Absent / incomplete attempts |
| RPT-MCQ-011 | School performance summary |

---

## Implementation References

- API: `PaymentsApiController` (Sahodaya payments)  
- `McqRegistration`, `McqQuestionBank`  
- School UI: coordinator flows in school admin section  

Next: [13-TEACHER_TRAINING.md](13-TEACHER_TRAINING.md)
