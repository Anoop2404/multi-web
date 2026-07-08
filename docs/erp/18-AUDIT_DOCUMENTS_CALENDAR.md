# Phase 18 — Audit, Documents, Calendar, and Configuration

## Part A — Audit Module

### Scope

Log all security-sensitive and financial actions platform-wide.

### Logged events (minimum)

| Category | Actions |
|----------|---------|
| Auth | login, logout, failed_login, password_reset |
| CRUD | created, updated, deleted (soft) |
| Workflow | status_changed, approved, rejected |
| Finance | payment_verified, receipt_issued, receipt_emailed, ledger_posted |
| Export | report_exported, bulk_export_started |
| Admin | role_assigned, permission_changed, setting_changed |

### Log schema

| Field | Type |
|-------|------|
| id | bigint |
| tenant_id | FK |
| user_id | FK nullable |
| action | string |
| subject_type | morph |
| subject_id | morph |
| properties | JSON (old/new diff) |
| ip_address | string |
| user_agent | string |
| created_at | timestamp |

**Service:** `PlatformAuditLogger`

### Retention

- Online: 2 years queryable  
- Archive: cold storage after 2 years (ops policy)  
- No hard delete  

### Reports

RPT-AUD-001 through RPT-AUD-005 (see REPORT_CATALOGUE)

---

## Part B — Document Management

### Document types (tenant config)

| Type | Required for |
|------|--------------|
| affiliation_letter | School membership |
| fire_safety | Compliance |
| recognition_certificate | Renewal |
| custom | Admin-defined |

### Document record

| Field | Description |
|-------|-------------|
| school_id | FK |
| document_type_id | FK |
| file_path | Storage |
| valid_from / valid_to | Dates |
| status | pending/approved/rejected/expired |
| reviewed_by | User |
| reviewed_at | Timestamp |

### Workflow

Upload → pending → approve/reject → expiry monitoring (scheduled job emails 30/7 days before)

---

## Part C — Calendar Module

### Event sources (aggregated calendar)

| Source | Display |
|--------|---------|
| Registration windows | Sports, Kalotsav, MCQ, Training |
| Exam dates | MCQ |
| Fest schedule days | Sports/Kalotsav |
| Training program dates | Training |
| Membership due dates | Membership |
| Sahodaya meetings | Manual entries (future) |

### Views

- Sahodaya admin: all schools  
- School admin: own school + cluster events  
- iCal export (future)  

### Implementation

Unified `calendar_events` view or API aggregating module tables; no duplicate scheduling data.

---

## Part D — Configuration Module

### Tenant settings (`tenant_settings`)

| Key area | Examples |
|----------|----------|
| branding | logo, colors, public site |
| academic | current year default |
| registration | global locks |
| finance | receipt prefixes, FY start month |
| email | from name, reply-to |
| features | module toggles via `SahodayaNavVisibility` |
| identity | STU/T padding length (default 6) |

### Change control

- Settings change → audit log  
- Critical settings require `sahodaya_admin` role  
- Export/import settings JSON for disaster recovery  

### Platform config (central)

- Tenant provisioning  
- Subscription/plan (if applicable)  
- Central master seed triggers  

---

## Permissions

| Permission | Scope |
|------------|-------|
| `audit.view` | Sahodaya admin, auditor role |
| `documents.review` | Secretary, admin |
| `documents.upload` | School admin |
| `settings.manage` | Sahodaya admin |
| `calendar.view` | All authenticated |

---

Next: [19-DATABASE_DESIGN.md](19-DATABASE_DESIGN.md)
