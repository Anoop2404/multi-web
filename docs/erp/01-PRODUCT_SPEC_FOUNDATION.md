# Phase 1 — Product Specification Foundation

## 1. Business Requirements Summary (BRS)

### 1.1 Purpose

Sahodaya ERP is a **multi-tenant** platform for Catholic Sahodaya associations to manage:

- Member schools and membership fees  
- Students and teachers (with verification and portal access)  
- Sports, Kalotsavam, MCQ, and teacher training programs  
- Offline fee collection, receipts, ledger, and financial reporting  
- Certificates, ID cards, and operational reports  

Each Sahodaya operates as an isolated tenant with shared platform code and central master data where applicable.

### 1.2 Stakeholders

| Stakeholder | Primary needs |
|-------------|---------------|
| Sahodaya Secretary / Admin | Full configuration, approvals, finance, reports |
| Finance team | Payment verification, receipts, ledger, reconciliation |
| Event coordinators | Sports / Kalotsavam / MCQ / training operations |
| School admin | Student/teacher data, registrations, fee proofs |
| Teachers | Portal login, training, judging, mark entry |
| Students | Portal login, registrations, hall tickets, results |

### 1.3 Business Goals

1. **Single system of record** for schools, students, teachers, and all program registrations.  
2. **Standardized offline payment flow** — every approved payment produces a receipt, emails the school, and appears in payment reports.  
3. **Verification gates** — students and teachers verified before sensitive actions (registration, judging, etc.).  
4. **Operational scale** — support 150 schools and 105,000+ students without degradation.  
5. **Auditability** — who changed what, when, for approvals and finance.  
6. **Email communication** — all external notifications via email in current release.

### 1.4 Out of Scope (Current Release)

- Online payment gateway integration  
- SMS / WhatsApp notifications  
- Removal of legacy Kalotsav routes or duplicate reports  
- Mobile native apps (responsive web only)

### 1.5 Future Scope (Documented, Not Removed)

- Payment gateway (Razorpay / similar) with callback reconciliation  
- SMS channel (MSG91 or equivalent)  
- Report consolidation / alias cleanup after UAT  
- Advanced analytics and BI exports  

---

## 2. Product Scope

### 2.1 In Scope Modules

| Module | Scope summary |
|--------|---------------|
| Common Masters | Classes, categories, subjects, designations, age categories, teaching types, etc. |
| Organization & School | Sahodaya profile, school registration, coordinators, documents |
| Students | CRUD, import, verification, STU login, ID cards |
| Teachers | CRUD, mandatory email, T login, verification, training history |
| Membership | Fee slabs, renewal, verification, receipts |
| Fee & Accounts | Fee heads, invoices, offline proof, ledger, vouchers, reconciliation |
| Sports | Meet setup, items, registration, fees, schedule, results, championship |
| Kalotsavam | Festival, items, judges, scoring, appeals, certificates |
| MCQ | Tiers, registration, fees, exam, results |
| Teacher Training | Eligibility, nomination, fees, attendance, certificates |
| Certificates & ID Cards | Templates, bulk generation, QR, email |
| Reports | 220–250 reports via common report engine |
| Dashboards | Role-wise KPI cards and tasks |
| Audit & Config | Platform audit log, tenant settings, calendar |

### 2.2 Current vs Future Scope Matrix

| Capability | Current | Future |
|------------|---------|--------|
| Offline payment + receipt + email | Required | Retain |
| Online payment | — | Gateway + webhooks |
| Email notifications | Required | Retain + templates |
| SMS notifications | — | Optional channel |
| Legacy Kalotsav routes | Retain | Alias/consolidate |
| Duplicate fest reports | Retain | Merge in report engine |
| Student STU login | Required | Retain |
| Teacher T login + email | Required | Retain |
| Queued large exports | Required | Scale tuning |
| Multi-year archive | Partial | Full archive policy |

---

## 3. Constraints

| Type | Constraint |
|------|------------|
| Technical | Laravel multi-tenant (central + tenant DBs), Vue/Inertia frontend |
| Financial | Offline-only; no PCI scope in current release |
| Communication | Email-only for external notifications |
| Data | No hard deletes on financial/audit records; soft delete where applicable |
| Compatibility | Do not remove existing routes until migration path documented |
| Identity | Teacher email mandatory; STU/T prefixed login codes globally unique per tenant |

---

## 4. Scale Baseline

### 4.1 Volume Assumptions (Per Sahodaya Tenant)

| Entity | Baseline | Growth planning |
|--------|----------|-----------------|
| Member schools | 150 | 200+ |
| Active students | 105,000 (~700/school) | 150,000+ |
| Teachers | ~15,000 (~100/school) | 20,000+ |
| Sports registrations / year | ~50,000 line items | Linear with students |
| Kalotsavam registrations / year | ~30,000 | Linear |
| MCQ registrations / year | ~20,000 | Linear |
| Training nominations / year | ~5,000 | Moderate |
| Fee receipts / year | ~10,000 | All modules combined |
| Audit log rows / year | ~500,000 | Indexed, partitioned later |

### 4.2 Performance Requirements

| Operation | Target |
|-----------|--------|
| List/search (paginated) | < 2 s P95 |
| Single record save | < 1 s P95 |
| Dashboard KPI load (cached) | < 3 s P95 |
| Report preview (10k rows) | < 5 s or async export |
| Bulk export 100k rows | Queued job; email download link |
| Concurrent school admins | 50+ |
| Concurrent student MCQ exam | 500+ (session scoped) |

### 4.3 Scale Design Rules

- **Pagination** mandatory on all list APIs and UI tables (default 25, max 100).  
- **Indexed filters** on `tenant_id`, `school_id`, `academic_year`, `status`, foreign keys.  
- **Queued exports** for CSV/PDF > 5,000 rows.  
- **Cached dashboards** TTL 5–15 minutes; invalidate on approval events.  
- **Eager loading** on registration lists; avoid N+1.  
- **Archive strategy** — inactive students/teachers moved to archive tables after N years (future migration).

---

## 5. KPIs and Success Metrics

| KPI | Definition | Target |
|-----|------------|--------|
| Receipt coverage | % approved payments with generated receipt | 100% |
| Receipt email delivery | % receipts emailed to school contact | 100% (retry on failure) |
| Student verification SLA | Days from submit to verify | < 7 days (operational) |
| Payment verification SLA | Days from proof upload to verify | < 5 days |
| Import error rate | Failed rows / total import rows | < 2% after validation |
| Report export success | Completed queued jobs / requested | > 99% |
| Uptime | Platform availability | 99.5% monthly |
| Audit completeness | Critical actions with audit row | 100% |

---

## 6. Assumptions and Dependencies

1. Each school has at least one valid **email** for receipt delivery.  
2. Sahodaya admin configures academic year and program windows before registrations open.  
3. Central platform admin manages tenant provisioning and master seed data.  
4. Email infrastructure (SMTP/SES) is configured per environment.  
5. Schools accept generated STU/T codes as portal usernames.  
6. Offline payment proofs are uploaded as image/PDF within size limits (e.g. 5 MB).

---

## 7. Risks and Mitigations

| Risk | Mitigation |
|------|------------|
| Large export memory exhaustion | Queue + chunk + streaming CSV |
| Duplicate report maintenance burden | Report engine + catalogue; alias later |
| Email delivery failures | Queue retries, `email_sent_at` tracking, admin resend |
| Login code collisions | DB unique index + sequential generator |
| Legacy route confusion | Document as `retain` in UI spec; nav visibility flags |

---

## 8. Glossary

| Term | Definition |
|------|------------|
| Sahodaya | Regional association tenant |
| Tenant | Isolated school + program database |
| Central DB | Platform tenants, shared masters where applicable |
| STU code | Student portal username (e.g. STU000042) |
| T code | Teacher portal username (e.g. T000015) |
| Proof | Uploaded bank transfer / cheque evidence for offline payment |
| Receipt | Official numbered fee receipt after finance approval |
| Item | A sports/Kalotsavam competitive entry |
| Event head | Category of items under a fest/sports meet |

---

## 9. Traceability

This document satisfies Phase 1 deliverables:

- [x] BRS summary  
- [x] Scope (in / out / future)  
- [x] Goals and constraints  
- [x] Scale baseline and performance rules  
- [x] KPIs  
- [x] Current vs future scope matrix  

Next: [02-COMMON_MASTERS.md](02-COMMON_MASTERS.md)
