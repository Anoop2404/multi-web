# Sahodaya mobile — per-tenant app builds

Each Sahodaya gets its **own Play Store / App Store listing** (separate bundle ID, name, icon, API host). All apps share the same Flutter codebase; branding and backend URL are injected at build time.

## Add a new Sahodaya app

1. Copy the template:
   ```bash
   cp tenants/_template.env.json tenants/<subdomain>.env.json
   ```
2. Edit `<subdomain>.env.json`:
   - `API_BASE_URL` — production HTTPS URL for that Sahodaya (must match `tenants.domain` in Laravel).
   - `APP_NAME`, `NAME_LINE_1`, `NAME_LINE_2`, `EYEBROW` — shown in login and shell headers.
   - `ANDROID_APPLICATION_ID` / `IOS_BUNDLE_ID` — unique reverse-DNS IDs (required for separate store listings).
3. Add logo: `assets/tenants/<subdomain>/logo.png` (square, ≥512×512).
4. Add Android flavor in `android/app/build.gradle.kts` (see comment block in that file).
5. Register iOS bundle ID in Apple Developer + Xcode signing for that tenant.
6. Build:
   ```bash
   ./scripts/build-tenant.sh <subdomain> apk    # sideload / testing
   ./scripts/build-tenant.sh <subdomain> aab    # Play Store
   ./scripts/build-tenant.sh <subdomain> ipa    # App Store / TestFlight
   ```

Output lands in `dist/<App-Name>-<version>.<ext>`.

## How it works

| Layer | What changes per Sahodaya |
|-------|---------------------------|
| Build config | `tenants/<slug>.env.json` → `--dart-define-from-file` |
| Android | Product flavor → `applicationId` + launcher label |
| iOS | Bundle ID patched before `flutter build ipa` |
| Runtime | App calls **that Sahodaya's** API only; login uses same accounts as the web portal |

The Laravel API is already multi-tenant (`/api/v1/school/{tenantId}`, `/api/v1/sahodaya/{tenantId}`). Each branded app points at one host, so `login-branding` and auth resolve the correct Sahodaya automatically.

## Current tenants

| Slug | App name | API |
|------|----------|-----|
| `malappuram` | Malappuram Central Sahodaya | https://malappuramcentralsahodaya.org |
