#!/usr/bin/env bash
# Roll a new engine release and/or env file over every engine node, one node at a time.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: rolling-update.sh [options]

For each in-service engine node (burst nodes first, the always-on node last), one at a time:
  leave the LB (untag exam-engine; skipped when it is the only node) -> wait DRAIN_SECONDS ->
  push env/engine.env (--env) and/or install the release -> restart -> wait for /health ->
  rejoin the LB -> wait LB_ADMIT_SECONDS.
Stops at the first node that does not come back healthy (the rest keep the old version).

Release source: --tarball FILE, else ENGINE_RELEASE_TARBALL / ENGINE_RELEASE_URL from ops.env.

Exam-day freeze: refuses while attempts are writing unless --force. Never run it in the T-2h .. close
window except as an incident fix agreed with the Sahodaya exam controller.

Options
  --tarball FILE   release tarball from build-release.sh
  --env-only       push env/engine.env and restart; do not install a release
  --env            also push env/engine.env with the release
  --only NAME      update a single node
  --force          skip the writing-attempts guard
  --dry-run        show the steps
  -h, --help
EOF
}

env_only=0 push_env=0 only='' force=0
while (($#)); do
  case $1 in
    --tarball) ENGINE_RELEASE_TARBALL_ARG=${2:?}; shift 2 ;;
    --env-only) env_only=1; push_env=1; shift ;;
    --env) push_env=1; shift ;;
    --only) only=${2:?}; shift 2 ;;
    --force) force=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done

load_ops_env
require_cmd doctl jq ssh scp
if [[ -n ${ENGINE_RELEASE_TARBALL_ARG:-} ]]; then ENGINE_RELEASE_TARBALL=$ENGINE_RELEASE_TARBALL_ARG; ENGINE_RELEASE_URL=''; fi
if [[ $env_only == 0 && -z $ENGINE_RELEASE_TARBALL && -z $ENGINE_RELEASE_URL ]]; then
  die "nothing to install: pass --tarball, set ENGINE_RELEASE_TARBALL/URL, or use --env-only"
fi
env_file=''
[[ $push_env == 1 ]] && env_file=$(engine_env_file)
acquire_lock rolling-update

rows=() base_row=''
while IFS=$'\t' read -r id name pub priv status tags _rest; do
  [[ -n ${id:-} ]] || continue
  if has_tag "$tags" "$ENGINE_DRAIN_TAG"; then continue; fi
  [[ -z $only || $name == "$only" ]] || continue
  if has_tag "$tags" "$ENGINE_BASE_TAG"; then base_row="$id"$'\t'"$name"$'\t'"$pub"; continue; fi
  rows+=("$id"$'\t'"$name"$'\t'"$pub")
done <<<"$(droplets_by_tag "$ENGINE_NODE_TAG")"
[[ -n $base_row ]] && rows+=("$base_row")
(( ${#rows[@]} > 0 )) || die "no matching engine nodes"
in_lb=$(droplets_by_tag "$ENGINE_LB_TAG" | sed '/^$/d' | wc -l | tr -d ' ')

if [[ $force == 0 ]]; then
  IFS=$'\t' read -r _ _ probe <<<"${rows[0]}"
  w=$(writing_attempts "$probe" || true)
  if [[ -n $w ]] && (( w > 0 )); then die "$w attempts are writing (exam in progress). Use --force only for an agreed incident fix."; fi
fi

for row in "${rows[@]}"; do
  IFS=$'\t' read -r id name pub <<<"$row"
  log "== $name ($pub)"
  solo=0
  (( in_lb <= 1 )) && solo=1
  if [[ $solo == 0 && $LB_KIND == do ]]; then
    run dctl compute droplet untag "$id" --tag-name "$ENGINE_LB_TAG"
    log "left the LB; draining ${DRAIN_SECONDS}s"
    [[ $DRY_RUN == 1 ]] || sleep "$DRAIN_SECONDS"
  elif [[ $solo == 1 ]]; then
    warn "$name is the only node in the LB: restarting in place (a few seconds of errors; clients retry)"
  fi
  if [[ -n $env_file ]]; then
    if [[ $DRY_RUN == 1 ]]; then
      printf '[dry-run] upload %s to %s:/etc/exam-engine/engine.env\n' "$env_file" "$pub" >&2
    else
      ssh_node "$pub" 'sudo tee /etc/exam-engine/engine.env.new >/dev/null &&
        sudo install -o root -g exam -m 0640 /etc/exam-engine/engine.env.new /etc/exam-engine/engine.env &&
        sudo rm -f /etc/exam-engine/engine.env.new' < "$env_file"
    fi
  fi
  if [[ $env_only == 0 ]]; then
    push_release "$pub"
  fi
  run ssh_node "$pub" 'sudo systemctl restart exam-engine'
  if [[ $DRY_RUN == 0 ]]; then
    h=$(wait_node_health "$pub" 120) || die "$name did not come back healthy -- stopping; it is OUT of the LB. Check: ssh $OPS_SSH_USER@$pub journalctl -u exam-engine -n 100"
    log "$name healthy, version $(jq -r '.version // "?"' <<<"$h")"
  fi
  if [[ $solo == 0 && $LB_KIND == do ]]; then
    run dctl compute droplet tag "$id" --tag-name "$ENGINE_LB_TAG"
    log "rejoining the LB; waiting ${LB_ADMIT_SECONDS}s"
    [[ $DRY_RUN == 1 ]] || sleep "$LB_ADMIT_SECONDS"
  fi
done
if [[ $LB_KIND == haproxy ]]; then run "$SCRIPTS_DIR/haproxy-sync-backends.sh"; fi
log "rolling update complete (${#rows[@]} node(s))"
