# Phase 22 — QA, UAT, and Test Plans

## 1. Test Strategy

| Level | Scope | Owner |
|-------|-------|-------|
| Unit | Services, calculators, gates | Dev |
| Feature | HTTP controllers, policies | Dev |
| Integration | Payment→receipt→email→ledger | QA |
| E2E | Critical user journeys | QA + UAT |
| Performance | 105k student scale | QA |
| Security | OWASP, RBAC, tenant isolation | Security review |
| Regression | Full suite on release | CI |

---

## 2. Test Environments

| Env | Purpose |
|-----|---------|
| local | Developer |
| staging | QA + UAT mirror production config |
| production | Live — smoke tests only post-deploy |

Staging data: anonymized subset or generated fixtures (150 schools, 10k students minimum for perf smoke).

---

## 3. Critical User Journeys (E2E)

### J1 — Membership offline payment

1. School submits membership  
2. Upload proof  
3. Finance verifies  
4. **Assert:** receipt PDF exists, email queued, school payment report shows link, Sahodaya report shows link, ledger balanced  

### J2 — Student lifecycle

1. School creates student → STU code generated  
2. Submit verification → Sahodaya approves  
3. Student logs in with STU code → forced password change  
4. Register for sports item → eligibility passes  

### J3 — Teacher lifecycle

1. Create teacher without email → **must fail**  
2. Create with email → T code generated  
3. Verify teacher → assign as judge  
4. Judge logs in → score entry  

### J4 — Fest registration + fee

1. Open registration window  
2. School registers paid items  
3. Upload proof → verify  
4. Chest number assigned  

### J5 — MCQ exam

1. Register tier, pay fee  
2. Hall ticket generated  
3. Student exam start → timer → submit  
4. Results published  

### J6 — Training eligibility

1. Configure program with teaching_type rule  
2. Nominate ineligible teacher → blocked  
3. Nominate eligible → approve → attend → certificate  

---

## 4. Functional Test Areas

| Area | Key cases |
|------|-----------|
| RBAC | Each role cannot access forbidden routes |
| Tenant isolation | School A cannot read School B data |
| Masters | CRUD + uniqueness + deactivate with refs |
| Imports | CSV validation, error report, queue |
| Reports | Filters, pagination, async export |
| Email | Template render, retry, resend receipt |
| Certificates | QR verify, bulk queue |
| Appeals | Result adjustment audit trail |
| Waivers | Approval + ledger contra |

---

## 5. Performance Test Plan

| Scenario | Load | Pass criteria |
|----------|------|---------------|
| Student list API | 100 concurrent, 105k records | P95 < 2s |
| Dashboard load | 50 concurrent | P95 < 3s |
| Report export 50k rows | 5 concurrent jobs | Completes without OOM |
| MCQ exam submit | 200 concurrent | No lost answers |
| Bulk cert generation | 5000 PDFs | Queue completes < 2h |

Tools: k6, Laravel Dusk (E2E), PHPUnit for feature tests.

---

## 6. Security Test Plan

| Test | Method |
|------|--------|
| IDOR | Attempt cross-school IDs |
| SQL injection | Parameterized queries audit |
| XSS | Form inputs in CMS |
| CSRF | All state-changing forms |
| File upload | MIME/size validation on proofs |
| Auth brute force | Rate limit login |
| Mass assignment | Guarded fillable on models |
| Receipt URL | Signed/temporary URLs |

---

## 7. UAT Plan

### Participants

- Sahodaya secretary (2)  
- Finance clerk (2)  
- Sports coordinator (1)  
- School admin (3 schools)  
- Teacher judge (1)  
- Student (2)  

### UAT phases

| Week | Focus |
|------|-------|
| 1 | Organization, school, students, teachers |
| 2 | Membership, payments, receipts, emails |
| 3 | Sports + Kalotsavam registration and results |
| 4 | MCQ, training, reports, dashboards |

### Sign-off criteria

- Zero P1 bugs open  
- P2 bugs have workaround documented  
- J1–J6 journeys pass on staging  
- Receipt email 100% on UAT test payments  
- UAT sign-off form per module  

---

## 8. Regression Suite

Run on every release:

- Auth + RBAC smoke (20 tests)  
- Payment receipt pipeline (10 tests)  
- Student/teacher verification gates (8 tests)  
- Fest registration happy path (5 tests)  
- Ledger trial balance integrity (5 tests)  

CI: GitHub Actions / existing pipeline — `php artisan test` minimum.

---

## 9. Test Data Requirements

- 3 schools minimum with full coordinator set  
- Students: verified, pending, rejected, inactive  
- Teachers: with/without subjects, judges assigned  
- Open fest event with 20+ items  
- Finance chart of accounts seeded  

Seeders: `SahodayaMasterDataSeeder`, `RolesAndPermissionsSeeder`, UAT fixture seeder (future).

---

## 10. Defect Severity

| Level | Definition |
|-------|------------|
| P1 | Data loss, security breach, payment without receipt |
| P2 | Major feature blocked, no workaround |
| P3 | Minor feature, workaround exists |
| P4 | Cosmetic |

---

Next: [23-DEPLOYMENT_OPERATIONS.md](23-DEPLOYMENT_OPERATIONS.md)
