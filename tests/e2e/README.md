# E2E / UX Browser Testing

Automated UI audits using **Playwright**, plus optional **Cursor Browser MCP** for exploratory testing.

## Prerequisites

1. Demo hosts in `/etc/hosts`:
   ```bash
   ./scripts/add-demo-hosts.sh
   ```

2. Database seeded:
   ```bash
   php artisan migrate
   php artisan db:seed --class=DemoTenantsSeeder
   php artisan tenants:migrate
   ```

3. App running (must answer on tenant domains, not only localhost):
   ```bash
   php artisan serve --host=0.0.0.0 --port=8000
   npm run build   # or npm run dev
   ```

4. Copy env:
   ```bash
   cp tests/e2e/.env.e2e.example .env.e2e
   ```

## Run all audits

```bash
chmod +x scripts/run-e2e.sh
./scripts/run-e2e.sh
```

Run a subset:

```bash
./scripts/run-e2e.sh public
./scripts/run-e2e.sh sahodaya
./scripts/run-e2e.sh school
./scripts/run-e2e.sh superadmin
```

View HTML report:

```bash
npx playwright show-report tests/e2e/report
```

UX summary JSON: `tests/e2e/report/ux-audit.json`

## What gets checked

| Check | Severity |
|-------|----------|
| HTTP 4xx/5xx on page load | Error |
| Server error text (SQLSTATE, 500, etc.) | Error |
| Empty page body | Error |
| Horizontal overflow (mobile) | Warning |
| "COMING SOON" placeholders | Warning |
| Missing document title | Warning |
| Many unlabeled form fields | Info |

### Page coverage

- **Public:** home, login
- **Sahodaya:** dashboard, events, MCQ, training, membership, ledger, circulars, schools
- **Sahodaya event** (if seeded): settings, registrations, fees, marks, results, reports
- **School:** dashboard, kalotsav/sports/kids registration, MCQ, training, students, payments
- **Superadmin:** dashboard, tenants, state programs, remittances

## Cursor Browser MCP (interactive)

The **cursor-ide-browser** MCP is enabled in Cursor for manual/exploratory audits:

1. Start the app locally
2. Ask the agent: *"Use browser MCP to audit school registration page UX"*
3. Agent will navigate, snapshot, screenshot, and report layout/issues

Playwright = repeatable CI/batch runs. Browser MCP = ad-hoc deep dives.

### Suggested MCP audit prompts

- Login as school admin and walk through Kalotsav registration + fee upload
- Login as Sahodaya admin and verify Event Fees approve flow
- Check mobile viewport on school registration page
- Verify training confirm button visibility after school registers

## CI (optional)

```yaml
# .github/workflows/e2e.yml snippet
- run: npx playwright install chromium --with-deps
- run: ./scripts/run-e2e.sh all
  env:
    E2E_SAHODAYA_URL: http://127.0.0.1:8000
```

For CI without custom domains, map hosts or use a single-tenant test URL in `.env.e2e`.

## Adding new pages

Edit `tests/e2e/support/page-catalog.ts` and re-run `./scripts/run-e2e.sh`.

## Troubleshooting

| Problem | Fix |
|---------|-----|
| `Invalid credentials` | Re-run `DemoTenantsSeeder` |
| Connection refused | Start `php artisan serve` |
| Blank Inertia pages | Run `npm run build` |
| Event sub-pages skipped | Create/publish a kalolsavam event |
| School blocked by email verify | Seeder sets `email_verified_at` for demo school |
