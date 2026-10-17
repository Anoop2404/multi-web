#!/usr/bin/env bash
# Remove burst engine nodes gracefully; the always-on node is never touched.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: scale-in.sh [options]

Removes burst engine nodes (tag exam-engine-burst), newest names first, until --keep N nodes remain in
service (default 1 = only the always-on exam-engine-1). Nodes tagged exam-engine-base are never touched.

Per node:  tag exam-engine-draining -> untag exam-engine (the LB stops routing to it) -> wait --drain
           seconds -> stop the engine (graceful) -> delete the droplet.
Nodes left "draining" by an interrupted run are finished first. Idempotent.

Guards (skip with --force):
  * the hold file (HOLD_FILE in ops.env) exists -> refuse (on-call creates it during incidents)
  * attempts currently writing (engine metric exam_attempts{state="writing"}) exceed
    N x SCALE_IN_WRITING_PER_NODE -> refuse

Options
  --keep N         nodes to keep in service in total (default 1)
  --node NAME      remove exactly this burst node (repeatable; ignores --keep)
  --drain S        seconds between leaving the LB and stopping (default DRAIN_SECONDS)
  --force          ignore the guards above
  --dry-run        show what would be removed
  -h, --help
EOF
}

keep=1 force=0 drain='' only=()
while (($#)); do
  case $1 in
    --keep) keep=${2:?}; is_uint "$keep" || die "--keep needs a number"; shift 2 ;;
    --node) only+=("${2:?}"); shift 2 ;;
    --drain) drain=${2:?}; is_uint "$drain" || die "--drain needs seconds"; shift 2 ;;
    --force) force=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done

load_ops_env
require_cmd doctl jq ssh
(( keep >= 1 )) || die "--keep must be >= 1 (the always-on node stays)"
[[ -n $drain ]] || drain=$DRAIN_SECONDS
acquire_lock scale-in

if [[ -e $HOLD_FILE && $force == 0 ]]; then
  die "hold file $HOLD_FILE exists -- scale-in is on hold (remove it or use --force)"
fi

fleet=$(droplets_by_tag "$ENGINE_NODE_TAG")
burst=() draining=() total=0 base_ip=''
while IFS=$'\t' read -r id name pub priv status tags _size _created; do
  [[ -n ${id:-} ]] || continue
  if has_tag "$tags" "$ENGINE_DRAIN_TAG"; then draining+=("$id"$'\t'"$name"$'\t'"$pub"); continue; fi
  total=$(( total + 1 ))
  if has_tag "$tags" "$ENGINE_BASE_TAG"; then base_ip=$pub; continue; fi
  if has_tag "$tags" "$ENGINE_BURST_TAG"; then burst+=("$name"$'\t'"$id"$'\t'"$pub"); fi
done <<<"$fleet"

# choose victims
victims=()
if (( ${#only[@]} > 0 )); then
  for want in "${only[@]}"; do
    hit=''
    for row in ${burst[@]+"${burst[@]}"}; do
      IFS=$'\t' read -r name id pub <<<"$row"
      if [[ $name == "$want" ]]; then hit="$id"$'\t'"$name"$'\t'"$pub"; fi
    done
    [[ -n $hit ]] || die "$want is not an in-service burst node"
    victims+=("$hit")
  done
else
  remove=$(( total - keep ))
  if (( remove > 0 )); then
    while IFS=$'\t' read -r name id pub; do
      [[ -n ${id:-} ]] || continue
      (( ${#victims[@]} < remove )) || break
      victims+=("$id"$'\t'"$name"$'\t'"$pub")
    done < <(printf '%s\n' ${burst[@]+"${burst[@]}"} | sort -r)
  fi
fi

log "in service: $total, keep: $keep, to remove: ${#victims[@]}, draining leftovers: ${#draining[@]}"
if (( ${#victims[@]} == 0 && ${#draining[@]} == 0 )); then
  log "nothing to do"
  exit 0
fi

# live-load guard
if (( ${#victims[@]} > 0 )) && [[ $force == 0 ]]; then
  remaining=$(( total - ${#victims[@]} ))
  probe=$base_ip
  [[ -n $probe && $probe != - ]] || { IFS=$'\t' read -r _ _ probe <<<"${victims[0]}"; }
  writing=$(writing_attempts "$probe" || true)
  if [[ -z $writing ]]; then
    warn "could not read exam_attempts{state=\"writing\"} from $probe -- live-load guard skipped"
  elif (( writing > remaining * SCALE_IN_WRITING_PER_NODE )); then
    die "$writing attempts are writing; $remaining node(s) would remain (limit $SCALE_IN_WRITING_PER_NODE each). Use --force to override."
  else
    log "writing attempts now: $writing (ok for $remaining node(s))"
  fi
fi

run dctl compute tag create "$ENGINE_DRAIN_TAG" >/dev/null 2>&1 || true

# 1) take every victim out of the LB at once, so the drain waits overlap
for row in ${victims[@]+"${victims[@]}"}; do
  IFS=$'\t' read -r id name pub <<<"$row"
  log "draining $name ($id)"
  run dctl compute droplet tag "$id" --tag-name "$ENGINE_DRAIN_TAG"
  run dctl compute droplet untag "$id" --tag-name "$ENGINE_LB_TAG"
  draining+=("$id"$'\t'"$name"$'\t'"$pub")
done
if [[ $LB_KIND == haproxy ]]; then run "$SCRIPTS_DIR/haproxy-sync-backends.sh"; fi
if (( ${#victims[@]} > 0 )); then
  log "waiting ${drain}s for in-flight requests and LB health checks"
  [[ $DRY_RUN == 1 ]] || sleep "$drain"
fi

# 2) stop and delete every draining node
for row in ${draining[@]+"${draining[@]}"}; do
  IFS=$'\t' read -r id name pub <<<"$row"
  if [[ $pub != - ]]; then
    run ssh_node "$pub" 'sudo systemctl stop exam-engine' || warn "$name: could not stop the engine cleanly (deleting anyway)"
  fi
  run dctl compute droplet delete "$id" --force
  if [[ $DRY_RUN == 1 ]]; then log "would delete $name ($id)"; else log "deleted $name ($id)"; fi
done

log "scale-in complete"
