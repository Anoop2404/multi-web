# Phase 16 — Report Engine Specification

Target catalogue size: **220–250 reports**. Full list: [REPORT_CATALOGUE.md](REPORT_CATALOGUE.md).

## 1. Report Definition Schema

Each report registered in `FestReportCatalog` / central report registry:

```yaml
id: RPT-SPT-001
name: Registered students by item
module: sports
classification: retain | alias | duplicate | new
permissions: [reports.export, fest.reports.view]
default_filters:
  - fest_event_id
  - school_id
  - item_id
columns:
  - key: chest_number
    label: Chest No
  - key: student_name
    label: Student
export_formats: [csv, pdf, xlsx]
async_threshold: 5000
cache_ttl: 0
```

---

## 2. Report Engine API (Internal)

| Method | Purpose |
|--------|---------|
| `ReportRegistry::all()` | List available reports for user |
| `ReportRunner::preview(id, filters, page)` | Paginated HTML/JSON |
| `ReportRunner::export(id, filters, format)` | Sync or queue |
| `ReportRunner::authorize(user, id)` | Permission + scope |

---

## 3. Filter Types

| Type | UI control |
|------|------------|
| fest_event_id | Select |
| school_id | Select + search |
| date_range | From/to |
| status | Multi-select enum |
| class_id | Select |
| tier_id | MCQ select |
| financial_year | Select |

All filters validated server-side; school roles auto-inject `school_id`.

---

## 4. Column Types

string, integer, decimal, date, datetime, boolean, link (receipt PDF), badge (status)

---

## 5. Export Rules

| Rows | Behavior |
|------|----------|
| ≤ 5,000 | Sync download |
| > 5,000 | Queue job, email link when ready |
| > 100,000 | Chunked CSV stream job only |

Memory: use `chunk()` / cursor; never load full dataset in web request.

---

## 6. Performance Rules

- Indexed filter columns documented in Phase 19  
- Report SQL reviewed for N+1 and missing indexes  
- Optional materialized summaries for championship/points (nightly job)  
- Duplicate reports (`classification: duplicate`) share same query class — alias only in UI  

---

## 7. Legacy Report Policy

| Classification | Action now |
|----------------|------------|
| retain | Keep route + nav |
| alias | New canonical ID, old URL redirects |
| duplicate | Same backend query, two menu entries until UAT |
| consolidate-later | Document only |

**Do not remove** legacy Kalotsav/fest report routes in this phase.

---

## 8. Role-Based Report Access

Report menu built from:

1. User permissions  
2. `SahodayaNavVisibility` flags  
3. Module enabled for tenant  

---

## 9. Report UI Pattern

- Filter panel (collapsible)  
- Preview table (paginated)  
- Export buttons  
- Save filter preset (future)  
- Print-friendly view  

Component pattern: existing fest report pages + `ReportHeadSubNav.vue`

---

## 10. Module Report Counts (Target)

| Module | Target count |
|--------|--------------|
| Organization/School | 15 |
| Students | 15 |
| Teachers | 12 |
| Membership/Payments | 20 |
| Finance | 20 |
| Sports | 45 |
| Kalotsavam | 45 |
| MCQ | 15 |
| Training | 12 |
| Certificates/ID | 8 |
| Email/Audit | 10 |
| Dashboards/Admin | 15 |
| **Total** | **~232** |

See [REPORT_CATALOGUE.md](REPORT_CATALOGUE.md) for enumerated IDs.

---

## Implementation References

- `FestReportController`, `FestReportService`, `FestEventReportAnalyticsService`  
- `FestSchoolReportExportService`, `MembershipReportsController`  
- `app/Support/FestReportCatalog.php`  

Next: [17-DASHBOARDS.md](17-DASHBOARDS.md)
