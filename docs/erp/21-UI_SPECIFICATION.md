# Phase 21 — UI Specification Framework

Target: **450–550 screens** across Sahodaya admin, school admin, portals, and public site. This document defines the **screen specification template** and enumerates modules with screen counts. Detailed per-screen specs follow the template below in implementation wikis or phased UI tickets.

## 1. Screen Specification Template

Each screen document (or ticket) MUST include:

```yaml
screen_id: SCR-SAH-FIN-001
name: Payment Verification Queue
route: /sahodaya/finance/payments
component: Sahodaya/Finance/PaymentVerification.vue
roles: [finance_manager, finance_clerk, sahodaya_admin]
layout: SahodayaLayout

fields:
  - name: filter_school
    type: select
    source: api/schools
  - name: filter_status
    type: select
    options: [pending, verified, rejected]

table_columns:
  - school_name
  - module
  - amount
  - proof_link
  - submitted_at

actions:
  - id: approve
    label: Verify Payment
    permission: payments.verify
    confirms: true
  - id: reject
    label: Reject
    requires: rejection_reason

validations:
  - rejection_reason required on reject

messages:
  success_verify: Payment verified. Receipt emailed to school.
  error_already_verified: This payment was already processed.

backend:
  controller: PaymentVerificationController@index
  endpoints: [GET /payments, POST /payments/{id}/verify]

audit:
  - payment.verified
  - payment.rejected
```

---

## 2. Layouts

| Layout | Used by |
|--------|---------|
| SahodayaLayout | Sahodaya admin/staff |
| SchoolLayout | School admin |
| PortalLayout | Student/teacher/judge |
| PublicLayout | Public website, fest portal |
| AuthLayout | Login, password reset |
| PrintLayout | Reports, certificates |

---

## 3. Screen Inventory by Module

| Module | Sahodaya | School | Portal | Public | Est. total |
|--------|----------|--------|--------|--------|------------|
| Auth & profile | 8 | 6 | 6 | 4 | 24 |
| Dashboard | 6 | 4 | 5 | — | 15 |
| Organization | 12 | — | — | 8 | 20 |
| Master data | 25 | 5 | — | — | 30 |
| Schools & membership | 35 | 15 | — | 5 | 55 |
| Students | 25 | 20 | 8 | — | 53 |
| Teachers | 22 | 18 | 6 | — | 46 |
| Finance | 40 | 12 | — | — | 52 |
| Sports | 55 | 25 | 15 | 10 | 105 |
| Kalotsavam | 55 | 25 | 15 | 10 | 105 |
| MCQ | 25 | 15 | 10 | 5 | 55 |
| Training | 20 | 12 | 5 | — | 37 |
| Certificates/ID | 15 | 8 | 3 | 2 | 28 |
| Reports hub | 30 | 20 | — | — | 50 |
| Audit/settings | 15 | 3 | — | — | 18 |
| CMS/public | 20 | 10 | — | 40 | 70 |
| **Subtotal** | **~358** | **~183** | **~73** | **~84** | **~698 routes/pages** |

Many routes are variants (filters/tabs) → **effective unique screens ~480** within 450–550 target when consolidated.

---

## 4. Key Screen Specifications (Samples)

### SCR-STU-001 — Student List (School)

- **Route:** `/school/students`  
- **Table:** paginated, search by name/admission_no/login_code  
- **Filters:** class, verification status, gender  
- **Actions:** Add, Import CSV, Export, View, Edit, Submit verification  
- **Roles:** school_admin, school coordinators (view)  
- **Scale:** server-side pagination mandatory  

### SCR-TCH-001 — Teacher Form (School)

- **Route:** `/school/teachers/create`  
- **Required:** email, employee_id, teaching_type, designation  
- **Auto:** login_code on save  
- **Multi-select:** subjects, classes_handled  
- **Validation messages:** email required, email unique  

### SCR-FIN-001 — Unified Payment Hub (Sahodaya)

- **Route:** `/sahodaya/finance/payments`  
- **Shows:** all modules, receipt link column, email status  
- **Actions:** verify, reject, resend receipt, view ledger  

### SCR-FEST-001 — Item Registration (School)

- **Route:** `/school/fest/{event}/register`  
- **Wizard:** select students → select items → fee summary → submit → upload proof  
- **Gates:** student verified, window open, eligibility  

### SCR-PORT-STU-001 — Student Dashboard

- **Route:** `/portal/student`  
- **Widgets:** registrations, hall tickets, results, profile link  
- **Auth:** STU login code  

---

## 5. UI Patterns

| Pattern | Usage |
|---------|-------|
| DataTable | All list screens — paginated |
| FilterDrawer | Reports, long lists |
| StatusBadge | Workflow statuses |
| ConfirmModal | Destructive/financial actions |
| Toast | Success/error feedback |
| FileUpload | Proofs, photos, documents |
| StepWizard | Multi-step registration |
| EmptyState | No records guidance |
| PermissionGate | Hide actions by role |

---

## 6. Validation & Messages

- Client-side: immediate field validation (VeeValidate or equivalent)  
- Server-side: authoritative; display `errors` bag per field  
- Financial actions: always confirm modal + audit  
- Standard error codes documented in API spec  

---

## 7. Accessibility & i18n

- WCAG 2.1 AA target for forms and tables  
- English primary; Malayalam labels future  
- Print styles for reports and certificates  

---

## 8. Legacy Screen Policy

Screens mapped to legacy routes marked `retain` in nav — no removal. `SahodayaNavVisibility` hides disabled modules.

---

## 9. Screen ID Convention

`SCR-{SCOPE}-{MODULE}-{SEQ}`

Scopes: SAH (Sahodaya), SCH (School), PRT (Portal), PUB (Public)

---

## 10. Traceability

Each screen links to:

- FR IDs in main SRS  
- API endpoints (Phase 20)  
- Report IDs where applicable (Phase 16)  
- Audit events (Phase 18)  

Implementation: expand samples into full catalogue in issue tracker per sprint.

---

Next: [22-QA_UAT.md](22-QA_UAT.md)
