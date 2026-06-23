#!/usr/bin/env bash
# Run adb with the default Android SDK path (works even if PATH is not set yet).
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export PATH="$ANDROID_HOME/platform-tools:$PATH"
exec adb "$@"
