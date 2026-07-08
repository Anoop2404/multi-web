# Phase 17 — Dashboard Specification

## 1. Dashboard Engine

### Widget contract

```php
interface DashboardWidget {
    public function key(): string;
    public function title(): string;
    public function authorize(User $user): bool;
    public function data(array $context): array;
    public function cacheTtl(): int; // seconds
}
```

### Context keys

`tenant_id`, `school_id`, `academic_year`, `fest_event_id`, `date`

### Cache invalidation triggers

- Payment verified  
- Student/teacher verified  
- Registration window changed  
- Results published  

---

## 2. Sahodaya Admin Dashboard

| Widget | KPI / content |
|--------|---------------|
| schools_total | Active member schools |
| students_total | Verified students count |
| teachers_total | Verified teachers |
| membership_pending | Schools pending renewal |
| payments_pending | Finance verification queue |
| registrations_sports | Current fest registrations |
| registrations_kalotsav | Current kalotsav registrations |
| mcq_registrations | Active MCQ tier counts |
| training_nominations | Open program nominations |
| recent_audit | Last 10 audit events |
| tasks | Action items (verify queues) |
| shortcuts | Links to common admin screens |

**Controller:** `DashboardController` (SahodayaAdmin)

---

## 3. Finance Dashboard

| Widget | Content |
|--------|---------|
| today_collection | Sum receipts today |
| month_collection | MTD |
| pending_proofs | Count awaiting verify |
| failed_emails | Receipt emails failed |
| outstanding | Unpaid invoices |
| bank_balance | Ledger bank heads |
| unreconciled | Bank reco pending |
| chart_monthly | 12-month collection line chart |

**Controller:** `FinanceHubController`

---

## 4. School Admin Dashboard

| Widget | Content |
|--------|---------|
| students_count | By class breakdown mini chart |
| teachers_count | |
| membership_status | Current year badge |
| pending_payments | Module-wise |
| fest_registrations | Open window status |
| mcq_registrations | |
| training_nominations | |
| verification_pending | Students/teachers |
| document_alerts | Expired docs |
| shortcuts | Register, upload proof, reports |

**Controller:** `SchoolAdmin/DashboardController`

---

## 5. Event Coordinator Dashboards

### Sports / Kalotsavam coordinator

- Registration stats by item head  
- Schedule today  
- Mark entry pending count  
- Unpublished results  
- Appeals open  

### MCQ coordinator

- Registrations by tier  
- Fee pending schools  
- Exam session active count  
- Results pending publish  

### Training coordinator

- Program capacity fill rate  
- Nomination approval queue  
- Attendance today  

---

## 6. Portal Dashboards

| Role | Dashboard |
|------|-----------|
| Student | My registrations, hall tickets, results, profile |
| Teacher | Assignments, judge items, training, profile |
| Judge | Assigned items, score entry status |
| Mark coordinator | Items pending verification |
| Event ops | Gate, attendance, venue schedule |

---

## 7. Performance

| Rule | Value |
|------|-------|
| Widget cache TTL | 5–15 min |
| Parallel widget load | Async API per widget (future) or single aggregated endpoint |
| Count queries | Use cached counters table where needed |
| Charts | Pre-aggregated nightly for historical |

---

## 8. Nav Visibility

`SahodayaNavVisibility` — hide modules/widgets when disabled for tenant.

---

Next: [18-AUDIT_DOCUMENTS_CALENDAR.md](18-AUDIT_DOCUMENTS_CALENDAR.md)
