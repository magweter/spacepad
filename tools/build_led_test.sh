#!/usr/bin/env bash
# Builds the Android LED test APK (Philips SICP test page enabled).
# Output: app/build/app/outputs/flutter-apk/app-release.apk
set -euo pipefail

cd "$(dirname "$0")/../app"
version="$(grep -E '^version:' pubspec.yaml | awk '{print $2}')"

fvm flutter build apk --release \
  --dart-define=LED_TEST=true \
  --dart-define=APP_VERSION="${version} (LED test)"
