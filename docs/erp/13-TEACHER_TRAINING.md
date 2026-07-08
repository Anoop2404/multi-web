# Phase 13 — Teacher Training Specification

## 1. Program Setup

| Field | Description |
|-------|-------------|
| title | Program name |
| academic_year | FK |
| dates | Start/end |
| venue | Location / online flag |
| capacity | Max nominations |
| fee | Per teacher or school bundle |
| eligibility_config | JSON — see below |
| certificate_template | FK |

Model: `TrainingProgram` with `eligibility_config`  
Config schema: `TrainingProgramEligibilityConfig`

---

## 2. Eligibility Rules

Evaluated by `TeacherTrainingEligibilityService`:

| Rule type | Example |
|-----------|---------|
| teaching_type | PRT only |
| designation | Headmaster excluded |
| min_experience | ≥ 2 years |
| subjects | Must teach Mathematics |
| prior_training | Must have completed Program X |
| verified | Teacher must be verified |

Block nomination with clear message if fail.

---

## 3. Nomination Flow

```mermaid
flowchart TD
    Open[Window open] --> Nom[School nominates teachers]
    Nom --> Elig[Eligibility check]
    Elig --> Submit[Submitted]
    Submit --> Approve[Training coordinator approve]
    Approve --> Fee[Fee invoice if applicable]
    Fee --> Proof[Offline proof]
    Proof --> Verify[Finance verify + receipt]
    Verify --> Confirm[Confirmed seat]
```

**Controllers:** `TrainingRegistrationController`, school admin training index

---

## 4. Offline Payment

Same as Phase 8:

- `TrainingSchoolFee` invoice  
- Proof upload  
- Receipt + email on verify  
- Ledger post via `ProgramFeeReceiptService`

---

## 5. Attendance

| Session | Mark |
|---------|------|
| Day 1 AM | present/absent |
| Day 1 PM | |
| ... | |

Minimum attendance % for certificate (configurable).

---

## 6. Feedback

Post-training survey (optional): rating + comments per teacher, aggregated in reports.

---

## 7. Certificates

Issued when:

- Attendance threshold met  
- Program completed  
- Fee verified (if paid)  

Bulk PDF + email to teacher `email` (mandatory).

---

## 8. Training Reports

| Report ID | Name |
|-----------|------|
| RPT-TRN-001 | Program list |
| RPT-TRN-002 | Nominations by school |
| RPT-TRN-003 | Eligibility rejection log |
| RPT-TRN-004 | Fee collection training |
| RPT-TRN-005 | Attendance register |
| RPT-TRN-006 | Certificate issued |
| RPT-TRN-007 | Teacher training history (cross-program) |
| RPT-TRN-008 | Feedback summary |
| RPT-TRN-009 | Capacity utilization |

---

## Implementation References

- `TrainingProgram`, `TrainingRegistration`, `TrainingSchoolFee`  
- `TeacherTrainingEligibilityService`  
- School: `School/Training/Index.vue`  

Next: [14-CERTIFICATES_ID_CARDS.md](14-CERTIFICATES_ID_CARDS.md)
