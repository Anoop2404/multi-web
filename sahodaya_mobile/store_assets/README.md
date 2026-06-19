# App Store / Play Store assets

Place production marketing assets here before submission:

- `icon/` — 1024×1024 app icon (iOS + Android adaptive icon source)
- `screenshots/ios/` — 6.7" and 5.5" iPhone screenshots
- `screenshots/android/` — phone screenshots (1080×1920 minimum)
- `privacy-policy.md` — host publicly and link in store listings

Build commands (API URL is fixed in `lib/config/env.dart`):

```bash
flutter build appbundle
flutter build ipa
```
