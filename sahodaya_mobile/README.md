# Sahodaya Mobile

Flutter app for **School** and **Sahodaya** admins (Phase 1: membership MVP).

Each Sahodaya gets a **separate branded app** (own store listing, icon, name, API host). See [tenants/README.md](tenants/README.md) for onboarding a new Sahodaya.

## Requirements

- Flutter 3.12+
- Laravel API running with Sanctum (`/api/v1/...`)

## Run (default tenant: Malappuram)

```bash
cd sahodaya_mobile
flutter pub get
./scripts/build-tenant.sh malappuram run
```

Or manually:

```bash
flutter run --flavor malappuram --dart-define-from-file=tenants/malappuram.env.json
```

## Build release for a tenant

```bash
./scripts/build-tenant.sh malappuram apk   # Android APK
./scripts/build-tenant.sh malappuram aab   # Play Store bundle
./scripts/build-tenant.sh malappuram ipa   # App Store / TestFlight
```

Artifacts are written to `dist/`.

## Demo logins

Use portal accounts on that Sahodaya's production domain (same credentials as the web admin panel).

## Features (Phase 1)

### School admin
- Dashboard (setup checklist, registration status)
- Students (list, add/edit, photo upload, CSV import)
- Annual registration wizard (begin, submit tracks, payment proof upload)

### Sahodaya admin
- Dashboard (pending schools/payments)
- Member schools (list, detail, reject)
- Payment verification (verify/reject, proof viewer)
- Submission review

## Release checklist (per Sahodaya)

### Android (Play Store)
- [ ] Unique `ANDROID_APPLICATION_ID` in `tenants/<slug>.env.json`
- [ ] Matching flavor in `android/app/build.gradle.kts`
- [ ] Release keystore + `android/key.properties`
- [ ] `./scripts/build-tenant.sh <slug> aab`
- [ ] Play Console listing (privacy policy, screenshots)

### iOS (App Store / TestFlight)
- [ ] Unique `IOS_BUNDLE_ID` in tenant env file
- [ ] Apple Developer registration for that bundle ID
- [ ] Xcode signing for the tenant
- [ ] `./scripts/build-tenant.sh <slug> ipa`
- [ ] Photo library / camera usage strings in `Info.plist`

### API production
- [ ] HTTPS on the Sahodaya domain (required for iOS ATS)
- [ ] `MOBILE_APP_ORIGIN` in Laravel `.env` if needed
- [ ] CORS in `config/cors.php`

## Project structure

```
lib/
  config/tenant_config.dart   # Build-time tenant values (--dart-define-from-file)
  config/env.dart             # API base URL wrapper
tenants/
  malappuram.env.json         # Per-Sahodaya build config
  _template.env.json          # Copy when onboarding
assets/tenants/<slug>/logo.png
scripts/build-tenant.sh       # White-label build helper
```
