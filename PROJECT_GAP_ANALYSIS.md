# Full Project Gap Analysis — Resolution Status

**Date:** 2026-07-03 (updated)  
**Scope:** UI/UX · User Flows · Permissions · Role Pages · Workflows · Setup Wizard

---

## Completed in latest pass

### Setup wizards & onboarding (Section 6)

| Item | Status |
|------|--------|
| F1–F4 Sahodaya 7-step setup wizard | ✅ `/sahodaya-admin/{id}/setup` — redirects from dashboard until complete or dismissed |
| F6–F8 School onboarding | ✅ Dismissible 3-step guide; optional student step for counts-only Sahodayas; code-lock warning (F8) |
| F15–F18 Portal welcome | ✅ `/portal/welcome` after password change for portal roles |
| S1 Settings tab persistence | ✅ Tab restored from URL `?tab=` + sessionStorage |

### UX polish

| Item | Status |
|------|--------|
| U1 Page loading indicator | ✅ Global `PageProgressBar` on Inertia navigation |
| U3 Breadcrumbs | ✅ `BreadcrumbTrail` component on `PageHeader` (pass `breadcrumbs` prop) |
| U8 Confirm dialogs | ✅ Global `ConfirmDialog` + `useConfirm()` composable |
| W6 Fest publish checklist | ✅ Blocks publish without items + venue/start date |
| W2 Unreviewed tracks | ✅ “Approve all pending” on submissions list |

### Previously completed

PM3, PM7, PM8, W1, S4–S8, S11, SA3, F13–F14, F21, PM4, P7, P11, U4

---

## Migrations required

```bash
php artisan migrate
php artisan tenants:migrate
```

Adds: `sahodaya_profiles.setup_wizard_*`, `users.portal_welcome_seen`, `tenants.school_setup_wizard_dismissed`

---

## Still open (lower priority)

| Area | Items |
|------|-------|
| UI polish | U2 notification drawer, U5 empty-state CTAs, U6–U10, S2–S3, S6, S9–S10, S12, SA1–SA2, SA4–SA10, P1–P12 |
| Flows | F5 application status page, F9–F12 registration draft/reminders, F19–F20 MCQ flow |
| Permissions | PM9 audit trail for user mgmt, PM10 group Sahodaya check (PM1/PM2 partially done via staff middleware) |
| Missing pages | Bulk cert print, training attendance, school profile editor, notification centre, etc. |
| Workflows | W3, W7–W20, academic year close, platform admin impersonation wizard |

---

## Key routes

| Route | Purpose |
|-------|---------|
| `/sahodaya-admin/{id}/setup` | Sahodaya first-time setup wizard |
| `/portal/welcome` | One-time portal orientation |
| `/school-admin/{id}/setup/dismiss-wizard` | Dismiss school onboarding card |

---

*See `SETUP_WORKFLOW_AUDIT_REPORT.md` and `AUDIT_REPORT.md` for earlier fixes.*
