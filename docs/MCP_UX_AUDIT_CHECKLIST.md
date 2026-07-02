# MCP Browser UX Audit Checklist

Use with **Cursor Browser MCP** (`cursor-ide-browser`) for interactive audits.
For repeatable CI runs, use `./scripts/run-e2e.sh` (Playwright).

## Setup

1. `php artisan serve --host=0.0.0.0 --port=8000`
2. `npm run dev` or `npm run build`
3. `./scripts/add-demo-hosts.sh`
4. `php artisan db:seed --class=DemoTenantsSeeder`

## Credentials (demo)

| Role | URL | Email | Password |
|------|-----|-------|----------|
| Sahodaya | http://malappuramsahodaya.test/login | sahodaya@malappuram.test | password |
| School | same | admin@amu-school.test | password |
| Superadmin | http://superadmin.test/login | admin@sahodaya.test | password |

## Audit flows (ask agent to run each)

### A. School — Kalotsav registration
- [ ] Login as school admin
- [ ] Open Kalotsav registration
- [ ] Quota widget visible (on-stage / off-stage / group)
- [ ] Items grouped in dropdown
- [ ] Fee accumulator shows when fee required
- [ ] Standby field appears for group items
- [ ] Submit disabled/hidden when registration closed

### B. School — MCQ & Training
- [ ] MCQ list loads, can select exam
- [ ] Training list loads, register teacher
- [ ] Training fee upload visible when program has fee

### C. Sahodaya — Fest event lifecycle
- [ ] Events list (kalolsavam filter)
- [ ] Event settings: participation policy section
- [ ] Registrations table + school fee summary
- [ ] Event Fees: per-school rows, approve/reject
- [ ] Mark entry, results publish pages load

### D. Sahodaya — MCQ
- [ ] Create exam, link question bank
- [ ] Hall tickets page
- [ ] Attendance, staff assignment
- [ ] Publish results

### E. Mobile viewport (375px)
- [ ] School registration usable without horizontal scroll
- [ ] Login form usable on phone

## UX red flags to report

- HTTP 500 / SQL error text on page
- Blank white page after navigation
- Broken layout / horizontal scroll
- Missing labels on form fields
- Dead links (404)
- "COMING SOON" on production paths
- Actions visible but backend returns 422

## Agent prompt template

```
Use browser MCP to audit [FLOW NAME]:
1. Navigate and login
2. Screenshot each step
3. Check mobile viewport (375px) for registration pages
4. Report UX issues with URL and severity (critical/warning)
```
