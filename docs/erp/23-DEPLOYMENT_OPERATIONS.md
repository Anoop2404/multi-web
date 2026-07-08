# Phase 23 — Deployment and Operations Specification

## 1. Environments

| Environment | Host | DB | Queue | Storage |
|-------------|------|-----|-------|---------|
| Production | app.sahodaya.example | RDS/Aurora multi-DB | Redis SQS | S3 |
| Staging | staging.sahodaya.example | Staging RDS | Redis | S3 staging bucket |
| Local | localhost | Docker MySQL | sync/database | local disk |

Tenancy: one central DB + N tenant databases (150+ schools per tenant).

---

## 2. Infrastructure Requirements

| Component | Minimum (production) |
|-----------|---------------------|
| App servers | 2+ (horizontally scaled) |
| PHP | 8.2+ |
| MySQL | 8.0+ |
| Redis | Queue + cache |
| Workers | 2+ queue workers, 1 scheduler |
| Storage | S3 compatible for uploads/PDFs |
| Mail | SES / SMTP with bounce handling |

---

## 3. Queue Configuration

| Queue | Jobs |
|-------|------|
| default | General async |
| mail | All outbound email |
| exports | Large CSV/PDF reports |
| certificates | Bulk cert/ID generation |
| imports | Student CSV import |

**Supervisor** config example:

```ini
[program:laravel-worker]
command=php artisan queue:work redis --queue=default,mail,exports,certificates,imports --sleep=3 --tries=3
numprocs=2
```

Failed jobs: `php artisan queue:failed` + Horizon dashboard (if used).

---

## 4. Scheduler (Cron)

| Schedule | Command |
|----------|---------|
| Daily 00:05 | Membership expiry check |
| Daily 06:00 | Document expiry reminders |
| Daily 07:00 | Dashboard counter refresh |
| Hourly | Retry failed receipt emails |
| Weekly | Log rotation archive |

Single cron: `* * * * * php artisan schedule:run`

---

## 5. Storage Layout

| Path | Content |
|------|---------|
| `proofs/` | Payment proof uploads |
| `receipts/` | Generated receipt PDFs |
| `certificates/` | Certificate PDFs |
| `id-cards/` | ID card PDFs |
| `students/{id}/` | Photos |
| `exports/` | Temporary export files (TTL 7 days) |
| `school-documents/` | Compliance docs |

S3 lifecycle: exports delete after 7 days; proofs/receipts retain 7+ years.

---

## 6. Backup Strategy

| Target | Frequency | Retention |
|--------|-----------|-----------|
| Central DB | Daily full + hourly binlog | 30 days |
| Each tenant DB | Daily full | 30 days |
| S3 files | Cross-region replication | Per compliance |
| App config | Git tagged releases | Indefinite |

Restore drill: quarterly staging restore test.

---

## 7. Monitoring and Alerting

| Metric | Alert |
|--------|-------|
| HTTP 5xx rate | > 1% for 5 min |
| Queue depth | > 1000 for 15 min |
| Failed jobs | > 10/hour |
| DB connections | > 80% pool |
| Disk / storage | > 85% |
| Email bounce rate | > 5% |

Tools: CloudWatch, Sentry (errors), uptime ping, Laravel log channels.

---

## 8. Release Process

1. Feature branch → PR → CI tests pass  
2. Deploy staging → QA smoke  
3. Tag release `vX.Y.Z`  
4. Maintenance window (if migrations) — notify tenants  
5. `php artisan migrate --force`  
6. `php artisan tenants:migrate --force`  
7. `php artisan config:cache && route:cache && view:cache`  
8. Restart queue workers  
9. Production smoke: login, payment verify, receipt email  
10. Rollback plan: previous release tag + migration down (if safe)  

---

## 9. Migration Strategy (Multi-Tenant)

```bash
# Central
php artisan migrate --force

# All tenants
php artisan tenants:migrate --force

# Single tenant (hotfix)
php artisan tenants:migrate --tenants=uuid --force
```

Long-running migrations: use `--pretend` first; run off-peak; avoid table locks on `students` — use online schema change if > 100k rows.

---

## 10. Secrets Management

| Secret | Store |
|--------|-------|
| DB credentials | Env / secrets manager |
| APP_KEY | Env — never rotate without plan |
| Mail credentials | Env |
| S3 keys | IAM role preferred |
| Future gateway keys | Secrets manager (unused now) |

---

## 11. Security Operations

- TLS everywhere  
- WAF on public endpoints  
- Rate limiting on auth and MCQ  
- Regular dependency updates (`composer audit`)  
- Tenant DB credentials isolated  
- No production data on laptops  

---

## 12. Disaster Recovery

| RTO | 4 hours |
| RPO | 1 hour (binlog) |

Runbook: restore central → restore tenant DBs → restore S3 prefix → verify tenant domain DNS → smoke test J1 payment journey.

---

## 13. Operational Runbooks

| Incident | Action |
|----------|--------|
| Email outage | Pause verify UI message; queue mails; retry hourly |
| Worker down | Scale workers; process backlog |
| Tenant DB full | Archive old exports; expand storage |
| Receipt numbering conflict | Stop verify; fix sequence; audit duplicate |

---

## 14. Completion

All 23 phases of the ERP Product Specification Suite are documented under `docs/erp/`.

Return to [README.md](README.md) for index.
