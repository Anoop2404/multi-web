# Sahodaya ERP — Product Specification Suite

Version: 1.0  
Status: Draft — implementation-ready specification  
Prepared: 2026-07-06  
Repository: `multi-web`

This folder is the **single source of truth** for business, development, QA, UI/UX, database design, API, and operations. It extends [SOFTWARE_REQUIREMENTS_SPECIFICATION.md](../SOFTWARE_REQUIREMENTS_SPECIFICATION.md) with phase-wise detail.

## Ground Rules

| Rule | Detail |
|------|--------|
| No removals now | Existing routes, reports, and legacy surfaces are **retained** until compatibility review. Mark as `retain`, `alias`, `duplicate`, or `future cleanup`. |
| Offline payments only | Current release: proof upload → verification → receipt → email → ledger. No online gateway. |
| Email-only external notifications | SMS, WhatsApp, push are **future scope** only. |
| Scale baseline | 150 schools, 105,000 active students per Sahodaya tenant; design for multi-year growth. |
| Teacher email | Mandatory on teacher profile. |
| Student login | Generated unique code with `STU` prefix (e.g. `STU000001`). |
| Teacher login | Generated unique code with `T` prefix (e.g. `T000001`); email for communication. |
| Database design | **After** phases 1–18 (common engines and workflows frozen). |

## Document Index

| Phase | Document | Topic |
|-------|----------|-------|
| 1 | [01-PRODUCT_SPEC_FOUNDATION.md](01-PRODUCT_SPEC_FOUNDATION.md) | BRS, scope, KPIs, scale, current/future scope |
| 2 | [02-COMMON_MASTERS.md](02-COMMON_MASTERS.md) | All shared masters |
| 3 | [03-RBAC_CREDENTIALS.md](03-RBAC_CREDENTIALS.md) | Roles, permissions, STU/T login codes |
| 4 | [04-COMMON_ENGINES.md](04-COMMON_ENGINES.md) | Registration, approval, fee, schedule, result, etc. |
| 5 | [05-ORGANIZATION_SCHOOL.md](05-ORGANIZATION_SCHOOL.md) | Sahodaya org + school lifecycle |
| 6 | [06-STUDENT_MANAGEMENT.md](06-STUDENT_MANAGEMENT.md) | Student lifecycle at scale |
| 7 | [07-TEACHER_MANAGEMENT.md](07-TEACHER_MANAGEMENT.md) | Teacher lifecycle |
| 8 | [08-MEMBERSHIP_PAYMENTS.md](08-MEMBERSHIP_PAYMENTS.md) | Membership + offline payment + receipts |
| 9 | [09-FEE_ACCOUNTS.md](09-FEE_ACCOUNTS.md) | Fee engine + accounts |
| 10 | [10-SPORTS.md](10-SPORTS.md) | Sports meet |
| 11 | [11-KALOTSAVAM.md](11-KALOTSAVAM.md) | Kalotsavam |
| 12 | [12-MCQ.md](12-MCQ.md) | MCQ competitions |
| 13 | [13-TEACHER_TRAINING.md](13-TEACHER_TRAINING.md) | Teacher training |
| 14 | [14-CERTIFICATES_ID_CARDS.md](14-CERTIFICATES_ID_CARDS.md) | Certificates + ID cards |
| 15 | [15-EMAIL_NOTIFICATIONS.md](15-EMAIL_NOTIFICATIONS.md) | Email-only notifications |
| 16 | [16-REPORT_ENGINE.md](16-REPORT_ENGINE.md) + [REPORT_CATALOGUE.md](REPORT_CATALOGUE.md) | Report engine + catalogue |
| 17 | [17-DASHBOARDS.md](17-DASHBOARDS.md) | Role-wise dashboards |
| 18 | [18-AUDIT_DOCUMENTS_CALENDAR.md](18-AUDIT_DOCUMENTS_CALENDAR.md) | Audit, documents, calendar, config |
| 19 | [19-DATABASE_DESIGN.md](19-DATABASE_DESIGN.md) | Schema (post engine freeze) |
| 20 | [20-API_SPECIFICATION.md](20-API_SPECIFICATION.md) | REST API |
| 21 | [21-UI_SPECIFICATION.md](21-UI_SPECIFICATION.md) | Screen specification framework |
| 22 | [22-QA_UAT.md](22-QA_UAT.md) | Testing and UAT |
| 23 | [23-DEPLOYMENT_OPERATIONS.md](23-DEPLOYMENT_OPERATIONS.md) | Deploy and ops |
| 24 | [24-LIVE_SERVER_DEPLOYMENT.md](24-LIVE_SERVER_DEPLOYMENT.md) | Live server runbook (scale + tenant users) |

## Build Order

1. Phases 1–4: Foundation, masters, RBAC, engines  
2. Phases 5–8: Organization, school, student, teacher, membership/payments  
3. Phases 9–13: Finance, sports, kalotsavam, MCQ, training  
4. Phases 14–18: Certificates, email, reports, dashboards, audit  
5. Phases 19–23: Database, API, UI spec, QA, deployment  

## Related

- [PLATFORM_GUIDE.md](../PLATFORM_GUIDE.md) — current product routes and features  
- [SOFTWARE_REQUIREMENTS_SPECIFICATION.md](../SOFTWARE_REQUIREMENTS_SPECIFICATION.md) — baseline FR/NFR traceability  
