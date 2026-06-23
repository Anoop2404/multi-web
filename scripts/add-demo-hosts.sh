#!/usr/bin/env bash
# Adds Sahodaya demo domains to /etc/hosts (requires sudo).
set -euo pipefail

MARKER="# Sahodaya Platform (multi-web demo)"

REQUIRED_HOSTS=(
  "127.0.0.1  superadmin.test"
  "127.0.0.1  sahodaya.test"
  "127.0.0.1  malappuramsahodaya.test"
  "127.0.0.1  malappuram.sahodaya.test"
  "127.0.0.1  vadakarasahodaya.test"
  "127.0.0.1  vadakara.sahodaya.test"
  "127.0.0.1  amu-school.sahodaya.test"
  "127.0.0.1  kv-malappuram.sahodaya.test"
  "127.0.0.1  albirr.sahodaya.test"
  "127.0.0.1  mes-school.sahodaya.test"
  "127.0.0.1  devagiri-school.test"
)

add_missing_hosts() {
  local added=0
  for entry in "${REQUIRED_HOSTS[@]}"; do
    local host
    host=$(echo "$entry" | awk '{print $2}')
    if ! grep -qE "[[:space:]]${host}([[:space:]]|$)" /etc/hosts 2>/dev/null; then
      if grep -qF "$MARKER" /etc/hosts 2>/dev/null; then
        echo "$entry" | sudo tee -a /etc/hosts >/dev/null
      else
        if [[ $added -eq 0 ]]; then
          echo "" | sudo tee -a /etc/hosts >/dev/null
          echo "$MARKER" | sudo tee -a /etc/hosts >/dev/null
        fi
        echo "$entry" | sudo tee -a /etc/hosts >/dev/null
      fi
      echo "Added: $entry"
      added=1
    fi
  done
  if [[ $added -eq 0 ]]; then
    echo "All demo hosts already present."
  fi
  grep -A12 "$MARKER" /etc/hosts 2>/dev/null || true
}

add_missing_hosts
