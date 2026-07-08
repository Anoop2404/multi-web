# Phase 19 — Database Design Specification

**Prerequisite:** Phases 1–18 frozen. This document defines conceptual and physical schema; migrations follow in implementation sprints.

## 1. Architecture

| Layer | Database | Contents |
|-------|----------|----------|
| Central | `central` | tenants, domains, central users, shared masters (optional), platform audit |
| Tenant | `tenant_{id}` | schools, students, teachers, programs, finance, audit |

Multi-tenancy via `stancl/tenancy` pattern (`TenancyDatabase` support class).

---

## 2. Central Tables (Key)

| Table | Purpose |
|-------|---------|
| tenants | Tenant registry |
| domains | Hostname mapping |
| users | Platform super admins |
| subjects | Shared subject master (optional central copy) |
| designations | Shared designation master |
| age_categories | Shared age categories |
| teaching_types | PRT/TGT/PGT/PPT + class ranges |

Migration ref: `2026_07_18_000001_erp_common_masters.php`

---

## 3. Tenant Core Tables

### Organization & school

| Table | Key columns | Indexes |
|-------|-------------|---------|
| sahodaya_profiles | name, settings JSON | — |
| registrations | school application workflow | status, created_at |
| schools | code, official_email, membership_status | official_email |
| school_lock_overrides | school_id, module | school_id |

### Users & RBAC

| Table | Key columns | Indexes |
|-------|-------------|---------|
| users | email, password | email UNIQUE |
| roles, permissions | Spatie RBAC | — |
| model_has_roles | school scope | model_id, role_id |

### Students

| Table | Key columns | Indexes |
|-------|-------------|---------|
| students | school_id, class_id, login_code, admission_no, verification_status | UNIQUE(login_code), INDEX(school_id, class_id), INDEX(verification_status), INDEX(last_name) |
| student_edit_change_requests | student_id, status | student_id |
| achievements | student_id | student_id |

### Teachers

| Table | Key columns | Indexes |
|-------|-------------|---------|
| teachers | school_id, email, login_code, teaching_type_id, verification_status | UNIQUE(email), UNIQUE(login_code), INDEX(school_id) |
| teacher_subject | teacher_id, subject_id | composite UNIQUE |
| teacher_school_class | teacher_id, school_class_id | composite UNIQUE |
| user_profile_change_requests | teacher user changes | status |

Migration ref: `2026_07_18_000002_erp_teacher_expansion.php`

---

## 4. Finance Tables

| Table | Key columns | Indexes |
|-------|-------------|---------|
| account_heads | code, type, parent_id | UNIQUE(code) |
| ledger_opening_balances | account_head_id, financial_year | composite UNIQUE |
| ledger_transactions | account_head_id, voucher_id, amount, cleared_at | INDEX(posted_at), INDEX(cleared_at) |
| fee_receipts | receipt_number, school_id, amount, receipt_emailed_at, receipt_email_status | UNIQUE(receipt_number), INDEX(school_id) |
| sahodaya_payables | school_id, module, status | INDEX(status) |
| membership_payments | school_id, year, status | INDEX(school_id, year) |
| membership_fee_slabs | year, category | — |

---

## 5. Fest / Event Tables

| Table | Purpose |
|-------|---------|
| fest_events | Meet container |
| fest_catalog_items | Item master |
| fest_event_items | Event-item link |
| fest_registrations | Student registrations |
| fest_level_registrations | Level progression |
| fest_school_event_fees | School fee invoices |
| fest_event_invoices | Invoice headers |
| fest_item_heads | Item head grouping |
| fest_judge_scores / fest_judge_scores | Judge marks |
| fest_clash_requests, fest_substitution_requests | Ops workflows |
| fest_sports_age_group_configs | Age rules |
| fest_food_coupons | Ops |

Indexes: `(fest_event_id, school_id)`, `(fest_event_id, fest_event_item_id)`, registration status columns.

---

## 6. MCQ & Training

| Table | Key indexes |
|-------|-------------|
| mcq_registrations | (exam_id, student_id) UNIQUE |
| mcq_school_fees | (school_id, exam_id) |
| mcq_question_banks | exam_id |
| training_programs | eligibility_config JSON |
| training_registrations | (program_id, teacher_id) |
| training_school_fees | school_id |

---

## 7. Supporting Tables

| Table | Purpose |
|-------|---------|
| platform_audit_logs | Audit engine |
| notification_logs | Email delivery |
| notification_templates | Email templates |
| tenant_settings | Key-value config |
| downloads / exports | Queued export metadata |
| tc_requests | Transfer certificate |
| gallery, news, cms | Public site |

---

## 8. History & Archive Strategy

| Mechanism | Use |
|-----------|-----|
| Soft deletes | teachers, students (deleted_at) |
| status history JSON | Optional on registrations |
| audit log | All critical changes |
| archive tables (future) | students_archive, registrations_archive after year close |
| read replicas (ops) | Report heavy queries |

---

## 9. Foreign Key Rules

- All tenant tables: implicit tenant scope (separate DB)  
- school_id FK → schools ON DELETE RESTRICT  
- student_id, teacher_id → RESTRICT  
- Financial tables: no CASCADE delete  
- Orphan prevention on registration lines before parent delete  

---

## 10. Index Plan Summary

High-volume indexes (105k students):

```sql
-- students
INDEX idx_students_school_class (school_id, class_id)
INDEX idx_students_verification (verification_status)
UNIQUE idx_students_login_code (login_code)
UNIQUE idx_students_admission (school_id, admission_no)

-- fest_registrations
INDEX idx_fest_reg_event_school (fest_event_id, school_id)
INDEX idx_fest_reg_student (student_id)

-- fee_receipts
INDEX idx_receipts_school_date (school_id, verified_at)
UNIQUE idx_receipts_number (receipt_number)

-- ledger_transactions
INDEX idx_ledger_account_date (account_head_id, transaction_date)
```

---

## 11. Migration Plan

| Sprint | Migrations |
|--------|------------|
| M1 | Common masters (done) |
| M2 | Teacher expansion (done) |
| M3 | fee_receipts email tracking columns |
| M4 | login_code sequences STU/T |
| M5 | notification_logs table |
| M6 | report_definitions registry |
| M7 | archive tables (year close) |
| M8 | Index optimization pass |

**Rule:** Always `php artisan migrate` central then `tenants:migrate`. Rollback scripts for each migration.

---

## 12. Data Seeding

- `SahodayaMasterDataSeeder` — PPT, subjects, designations, age categories  
- `RolesAndPermissionsSeeder` — coordinator roles  
- `LedgerAccountSetupService` — default chart of accounts per tenant  

---

Next: [20-API_SPECIFICATION.md](20-API_SPECIFICATION.md)
