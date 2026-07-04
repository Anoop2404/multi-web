#!/usr/bin/env bash
# Deprecated: use ./scripts/build-tenant.sh malappuram ipa
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
exec "$ROOT/scripts/build-tenant.sh" malappuram ipa
