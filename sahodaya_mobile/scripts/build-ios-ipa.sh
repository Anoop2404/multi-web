#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

VERSION="$(grep '^version:' pubspec.yaml | awk '{print $2}' | cut -d+ -f1)"
OUT="dist/Malappuram-Central-Sahodaya-${VERSION}.ipa"

echo "==> Checking code signing identities..."
if ! security find-identity -v -p codesigning | grep -q "Apple Development\|Apple Distribution\|iPhone Distribution\|iPhone Developer"; then
  echo ""
  echo "No iOS signing certificate found on this Mac."
  echo "Set up signing in Xcode first:"
  echo "  1. open ios/Runner.xcworkspace"
  echo "  2. Runner target -> Signing & Capabilities"
  echo "  3. Select your Team (Apple Developer account)"
  echo "  4. Ensure bundle id com.sahodaya.sahodayaMobile is registered"
  echo ""
  exit 1
fi

echo "==> Building signed IPA..."
flutter build ipa --release

mkdir -p dist
IPA="$(find build/ios/ipa -name '*.ipa' -print -quit)"
if [[ -z "${IPA}" ]]; then
  echo "IPA not found under build/ios/ipa"
  exit 1
fi

cp "$IPA" "$OUT"
echo ""
echo "Done: $OUT"
ls -lh "$OUT"
