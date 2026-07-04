#!/usr/bin/env bash
# Build a white-label Sahodaya mobile app for one tenant.
# Usage: ./scripts/build-tenant.sh <tenant-slug> [apk|aab|ipa|ios|run]
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

TENANT="${1:-}"
TARGET="${2:-apk}"

if [[ -z "$TENANT" ]]; then
  echo "Usage: $0 <tenant-slug> [apk|aab|ipa|ios|run]"
  echo ""
  echo "Configured tenants:"
  for f in tenants/*.env.json; do
    [[ "$(basename "$f")" == _* ]] && continue
    echo "  - $(basename "$f" .env.json)"
  done
  exit 1
fi

ENV_FILE="tenants/${TENANT}.env.json"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE — copy tenants/_template.env.json and edit it."
  exit 1
fi

APP_NAME="$(python3 -c "import json; print(json.load(open('$ENV_FILE'))['APP_NAME'])")"
IOS_BUNDLE="$(python3 -c "import json; print(json.load(open('$ENV_FILE'))['IOS_BUNDLE_ID'])")"
VERSION="$(grep '^version:' pubspec.yaml | awk '{print $2}' | cut -d+ -f1)"
SAFE_NAME="$(echo "$APP_NAME" | tr ' ' '-' | tr -cd '[:alnum:]-')"
DEFINE_FILE="$(cd "$(dirname "$ENV_FILE")" && pwd)/$(basename "$ENV_FILE")"

mkdir -p dist

echo "==> Tenant: $TENANT"
echo "==> App:    $APP_NAME"
echo "==> Config: $ENV_FILE"

patch_ios_bundle() {
  local plist="ios/Runner/Info.plist"
  local pbx="ios/Runner.xcodeproj/project.pbxproj"
  /usr/bin/sed -i '' "s/PRODUCT_BUNDLE_IDENTIFIER = .*/PRODUCT_BUNDLE_IDENTIFIER = ${IOS_BUNDLE};/g" "$pbx" 2>/dev/null || \
    sed -i "s/PRODUCT_BUNDLE_IDENTIFIER = .*/PRODUCT_BUNDLE_IDENTIFIER = ${IOS_BUNDLE};/g" "$pbx"
  /usr/bin/plutil -replace CFBundleDisplayName -string "$APP_NAME" "$plist" 2>/dev/null || true
}

case "$TARGET" in
  run)
    flutter run --dart-define-from-file="$DEFINE_FILE"
    ;;
  apk)
    flutter build apk --release \
      --flavor "$TENANT" \
      --dart-define-from-file="$DEFINE_FILE"
    OUT="dist/${SAFE_NAME}-${VERSION}.apk"
    cp build/app/outputs/flutter-apk/app-${TENANT}-release.apk "$OUT"
    echo "Done: $OUT"
    ;;
  aab)
    flutter build appbundle --release \
      --flavor "$TENANT" \
      --dart-define-from-file="$DEFINE_FILE"
    OUT="dist/${SAFE_NAME}-${VERSION}.aab"
    cp build/app/outputs/bundle/${TENANT}Release/app-${TENANT}-release.aab "$OUT"
    echo "Done: $OUT"
    ;;
  ipa|ios)
    patch_ios_bundle
    flutter build ipa --release --dart-define-from-file="$DEFINE_FILE"
    IPA="$(find build/ios/ipa -name '*.ipa' -print -quit)"
    OUT="dist/${SAFE_NAME}-${VERSION}.ipa"
    cp "$IPA" "$OUT"
    echo "Done: $OUT"
    ;;
  *)
    echo "Unknown target: $TARGET (use apk, aab, ipa, ios, or run)"
    exit 1
    ;;
esac
