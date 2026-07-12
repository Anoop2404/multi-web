# Live Server Deployment — Backend Scale & Tenant Users

> Runbook for deploying backend blocker fixes to production.  
> Target scale: **~1 lakh portal users** per platform instance.

---

## 1. Pre-deploy checklist

| Item | Command / check |
|------|-----------------|
| Backup central PostgreSQL | `pg_dump` central DB |
| Backup each Sahodaya tenant DB | `pg_dump sahodaya_{uuid}` per cluster |
| Redis running | `redis-cli ping` → `PONG` |
| Queue workers planned | Supervisor with 2+ workers |
| Maintenance window announced | Users must re-login after user migration |

---

## 2. Environment variables (production `.env`)

Set these **before** deploy:

```env
# Sessions, cache, queues — required at scale
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Tenancy
TENANCY_DATABASE_PER_SAHODAYA=true
DB_CENTRAL_CONNECTION=central

# Scale tuning
ERP_ASYNC_AUTH_AUDIT=true
ERP_ASYNC_EXPORT_THRESHOLD=5000
ERP_ASYNC_IMPORT_THRESHOLD=500
ERP_BULK_PORTAL_THRESHOLD=50
ERP_FEST_LAZY_STUDENT_THRESHOLD=300
ERP_LOGIN_MAX_ATTEMPTS=5
ERP_LOGIN_LOCKOUT_MINUTES=15
```

---

## 3. Deploy sequence

### Step 1 — Pull code & install dependencies

```bash
cd /var/www/multi-web   # your app path
git pull origin main    # or your release branch
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

### Step 2 — Central migrations

```bash
php artisan migrate --force
```

Expected new/updated migrations:
- `2026_07_06_160001_erp_central_scale_indexes` — users + audit_logs indexes
- Tenant user auth code (no central schema change beyond indexes)

### Step 3 — Provision + migrate Sahodaya tenant databases

```bash
php artisan sahodaya:provision-databases --seed
```

Expected per Sahodaya database:
- `2026_07_06_160002_erp_tenant_scale_indexes` — fest, ledger, teachers indexes
- `2026_07_06_170001_tenant_users_and_permissions` — **users**, roles, sanctum tables

Use this project command in production instead of raw `tenants:migrate`. Raw `tenants:migrate`
iterates every tenant row and aborts on the first missing PostgreSQL database. The provisioner
configures, creates missing databases by default, migrates, and reports individual Sahodaya
failures without blocking the rest.

For one tenant:

```bash
php artisan sahodaya:provision-databases --tenant=<sahodaya-uuid> --seed
```

To audit without creating missing databases:

```bash
php artisan sahodaya:provision-databases --no-create
```

Verify one tenant:

```bash
php artisan tinker
# TenancyDatabase::withTenantDatabase($sahodaya, fn() => Schema::hasTable('users'));
```

### Step 4 — Migrate portal users central → tenant DBs

**Dry run first:**

```bash
php artisan users:migrate-to-tenant-databases --dry-run
```

**Copy users (preserves IDs — students.user_id unchanged):**

```bash
php artisan users:migrate-to-tenant-databases --seed-roles
```

**Verify before purge:**
- [ ] School admin login on Sahodaya portal URL
- [ ] Student login with username (STU- code)
- [ ] Teacher login
- [ ] Sahodaya admin login
- [ ] Superadmin on central domain still works
- [ ] Mobile API login (school_admin) on portal host

**Remove central copies:**

```bash
php artisan users:migrate-to-tenant-databases --purge-central
```

Or one-shot after verification:

```bash
php artisan users:migrate-to-tenant-databases --seed-roles --purge-central
```

### Step 5 — Cache & config

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan permission:cache-reset
```

### Step 6 — Restart services

```bash
php artisan queue:restart
sudo supervisorctl restart laravel-worker:*
sudo systemctl reload php8.2-fpm   # or your PHP service
```

### Step 7 — Readiness check

```bash
php artisan platform:readiness
```

All checks must pass:
- `redis_sessions`
- `redis_cache`
- `redis_queue`
- `central_db`
- `tenant_users_migrated` (0 portal users left in central)

---

## 4. Post-deploy smoke tests

| Test | URL / action | Expected |
|------|----------------|----------|
| Superadmin login | `https://superadmin.example.com/login` | Dashboard loads |
| Sahodaya admin | `https://{sahodaya-portal}/portal/login` | Sahodaya dashboard |
| School admin | School portal login | School dashboard |
| Student portal | STU- username login | Student dashboard |
| Fest registration | School → event → register item | Submitted, no duplicate on retry |
| Bulk portal provision | School → Students → bulk provision | Queued if > 50 students |
| API login | `POST /api/v1/auth/login` on portal host | Token returned |

---

## 5. Rollback plan

If login fails after user migration **before** `--purge-central`:

1. Portal users still exist in central — revert code to previous release
2. Tenant DB `users` tables can be truncated (data duplicated in central)
3. Users log in again on old release

If already purged central copies:

1. Restore central DB from pre-migration `pg_dump`
2. Restore tenant DBs from backup
3. Redeploy previous release

**Always take backups before Step 4.**

---

## 6. Ongoing operations

### Queue workers (Supervisor example)

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/multi-web/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600 --queue=default,mail,exports,imports
autostart=true
autorestart=true
numprocs=2
user=www-data
```

### Scheduler (cron)

```cron
* * * * * cd /var/www/multi-web && php artisan schedule:run >> /dev/null 2>&1
```

### Periodic commands

Source of truth: `routes/console.php` (all entries use `withoutOverlapping()`).

| Schedule | Command | Purpose |
|----------|---------|---------|
| After deploy | `php artisan permissions:sync-staff` | Backfill staff permissions per tenant DB |
| After deploy | `php artisan sahodaya:provision-databases --no-create` | Apply pending tenant migrations to all Sahodayas |
| Weekly Mon 09:30 | `board-results:upload-reminders` | Board result upload nudges |
| Daily 09:00 | `fest:registration-reminders` | Fest registration window reminders |
| Every 15 min | `fest:schedule-reminders` | Upcoming schedule reminders |
| Daily 09:15 | `training:reminders --payment` | Training payment reminders |
| Hourly | `training:session-reminders` | Upcoming session reminders |
| Every 5 min | `mcq:auto-submit-expired` | Auto-submit expired MCQ attempts |
| Every 15 min | `mcq:transition-exam-windows` | Open/close MCQ windows |
| Hourly | `mcq:exam-reminders` | MCQ exam reminders |
| Daily 02:00 | `membership:update-renewal-status` | Renewal status from annual registration |
| Daily 08:30 | `membership:send-reminders` | Membership renewal reminders |
| Hourly | `erp:retry-failed-receipt-emails` | Retry failed fee receipt emails |
| Daily 08:00 | `erp:school-document-expiry-reminders` | Document expiry nudges |
| Daily 02:30 | `erp:mark-school-documents-expired` | Mark expired school documents |
| Weekly | `php artisan platform:readiness` | Config drift check |

Require `CACHE_STORE=redis` in production — reminder dedup and scheduler overlap locks rely on atomic cache operations.

---

## 7. What changed in this release (code summary)

| Area | Change |
|------|--------|
| **Users** | Portal users in Sahodaya DB; superadmin/state stay central |
| **Auth** | `platform` guard (central) + `web` guard (tenant); host-based resolution |
| **Indexes** | Central users/audit; tenant fest/ledger/teachers |
| **Fest registration** | DB transactions + unique constraint on active entries |
| **Fest UI** | Lazy-load students when school has > 300 students |
| **Audit** | Login/logout events queued (`LogAuthEventJob`) |
| **Session refresh** | Cached 60s — fewer DB hits per request |
| **Bulk portal** | > 50 students → `ProvisionPortalUsersJob` queue |
| **Notifications** | `FestEventNotifier` queries users in tenant DB context |
| **Readiness** | `php artisan platform:readiness` |

---

## 8. Troubleshooting

| Symptom | Likely cause | Fix |
|---------|--------------|-----|
| Login works on old sessions only | User migration incomplete | Run `users:migrate-to-tenant-databases --seed-roles` |
| "Invalid credentials" for all portal users | Tenancy not initialized on login host | Check domain → tenant mapping in `domains` table |
| School admin created from superadmin missing | Tenant DB users table empty | Run tenant migrate + user migration |
| Fest duplicate entries | Old code without transaction | Deploy latest; unique index blocks duplicates |
| Slow registration page | Large student body | `ERP_FEST_LAZY_STUDENT_THRESHOLD=300`; lazy endpoint loads per event |
| `platform:readiness` fails redis | `.env` not updated | Set `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION=redis` |
| Queue jobs not running | Workers stopped | `supervisorctl status` |

---

## 9. Contact & escalation

- **Maintenance window:** announce 30 min before user migration (all users re-login)
- **DB backups:** retain 7 days minimum
- **Monitoring:** watch PostgreSQL connections, Redis memory, queue depth during fest season

---

Previous: [23-DEPLOYMENT_OPERATIONS.md](23-DEPLOYMENT_OPERATIONS.md)
