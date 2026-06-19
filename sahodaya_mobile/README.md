# Sahodaya Mobile

Flutter app for **School** and **Sahodaya** admins (Phase 1: membership MVP).

## Requirements

- Flutter 3.12+
- Laravel API running with Sanctum (`/api/v1/...`)

## Configure API URL

The app always uses the live API:

**`https://malappuramcentralsahodaya.org`**

No `--dart-define` or local override is needed.

## Run

```bash
cd sahodaya_mobile
flutter pub get
flutter run
```

## Demo logins

Use your live portal accounts (same as [malappuramcentralsahodaya.org](https://malappuramcentralsahodaya.org/)).

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

## Release checklist

### Android (Play Store)
- [ ] Create release keystore and configure `android/key.properties`
- [ ] Set `applicationId` / signing in `android/app/build.gradle.kts`
- [ ] Build: `flutter build appbundle`
- [ ] Play Console: privacy policy URL, screenshots (phone), feature graphic
- [ ] `minSdk` 21+ (default)

### iOS (App Store / TestFlight)
- [ ] Apple Developer account + bundle ID `com.sahodaya.sahodaya_mobile`
- [ ] Configure signing in Xcode
- [ ] Add `NSPhotoLibraryUsageDescription` / camera usage strings in `Info.plist` (image picker)
- [ ] Build: `flutter build ipa`
- [ ] TestFlight internal testing before App Store submission
- [ ] ATS: production API must use HTTPS

### API production
- [ ] HTTPS on API host (required for iOS ATS)
- [ ] Set `MOBILE_APP_ORIGIN` in Laravel `.env` if using web previews
- [ ] CORS configured in `config/cors.php`

## Project structure

```
lib/
  config/env.dart          # API base URL
  core/api/                # Dio client
  core/auth/               # Sanctum token storage + session
  features/school/         # School admin screens
  features/sahodaya/       # Sahodaya admin screens
  router/app_router.dart   # Role-based navigation
```
