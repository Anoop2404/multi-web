#!/usr/bin/env bash
# Read the capacity plan from Laravel (bookings) and scale the engine fleet (and optionally Redis) to it.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: plan-and-scale.sh [options]

1. Runs `php artisan exam-engine:plan-capacity` on the Laravel server (over SSH, or locally when
   LARAVEL_SSH=local) -- or reads --plan-file.
2. A slot is "active" from SCALE_LEAD_MINUTES before its start until SCALE_TAIL_MINUTES after its end.
   Slots start when the waiting room opens (T0 - 60 min), so the default 120 scales out at T0 - 3h.
   Wanted engine nodes = max(1, node count of every active slot).
3. Fewer nodes than wanted -> scale-out.sh N. More -> scale-in.sh --keep N (unless --no-scale-in, or the
   hold file exists; scale-in.sh also refuses while too many attempts are writing).
4. REDIS_AUTO_RESIZE=yes (and Sentinel): an active slot with more than REDIS_LARGE_THRESHOLD booked
   students -> resize-redis.sh up; no active slot -> resize-redis.sh down.
Safe to run every 15 minutes from cron; it only acts when the plan and the fleet differ. Any error
reading or parsing the plan aborts without changing anything.

Plan JSON (tolerant): an array of slots, or {"slots": [...]}. Per slot:
  start: starts_at | start | from | live_starts_at     end: ends_at | end | until | live_ends_at
  nodes: nodes | engine_nodes | node_count             booked: students | booked | expected | expected_count
  redis: "large" (optional; otherwise booked > REDIS_LARGE_THRESHOLD)
exam-engine:plan-capacity prints {"generated_at", "capacity", "slots": [{starts_at, ends_at, students, nodes, bookings}]}.
Times: ISO-8601 (Z, +00:00, +05:30, fractional seconds ok) or unix seconds.

Options
  --plan-file FILE   use this JSON instead of running artisan (testing, manual override)
  --now TIME         evaluate as if it were TIME (ISO-8601 or unix seconds)
  --no-scale-in      never remove nodes in this run
  --no-redis         ignore Redis even if REDIS_AUTO_RESIZE=yes
  --dry-run          decide and print, pass --dry-run to the scaling scripts
  -h, --help

Cron (on the ops host, every 15 min):
  */15 * * * * cd /opt/exam-ops/exam-engine/deploy && scripts/plan-and-scale.sh >> .state/plan-and-scale.log 2>&1
EOF
}

plan_file='' now='' no_scale_in=0 no_redis=0
while (($#)); do
  case $1 in
    --plan-file) plan_file=${2:?}; shift 2 ;;
    --now) now=${2:?}; shift 2 ;;
    --no-scale-in) no_scale_in=1; shift ;;
    --no-redis) no_redis=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done

load_ops_env
require_cmd jq
acquire_lock plan-and-scale
dry=()
[[ $DRY_RUN == 1 ]] && dry=(--dry-run)

# ---------------------------------------------------------------- read the plan
if [[ -n $plan_file ]]; then
  [[ -r $plan_file ]] || die "plan file not found: $plan_file"
  raw=$(cat "$plan_file")
else
  raw=$(laravel_exec "$LARAVEL_ARTISAN exam-engine:plan-capacity $PLAN_CAPACITY_ARGS") ||
    die "exam-engine:plan-capacity failed on $LARAVEL_SSH -- nothing changed"
fi
# artisan may print notices before the JSON: keep from the first line that starts a JSON value.
json=$(printf '%s\n' "$raw" | sed -n '/^[[:space:]]*[[{]/,$p')
jq -e 'type == "array" or type == "object"' >/dev/null 2>&1 <<<"$json" ||
  die "plan is not JSON -- nothing changed. Output was: $(printf '%s' "$raw" | head -c 300)"

JQ_DEFS='
def epoch:
  if . == null then error("slot without a start/end time")
  elif type == "number" then .
  elif (type == "string" and test("^[0-9]+$")) then tonumber
  else
    (sub("\\.[0-9]+"; "")) as $s
    | if ($s | test("Z$")) then ($s | fromdateiso8601)
      elif ($s | test("[+-][0-9]{2}:?[0-9]{2}$")) then
        ($s | capture("^(?<base>.*)(?<sign>[+-])(?<h>[0-9]{2}):?(?<m>[0-9]{2})$")) as $c
        | (($c.base + "Z") | fromdateiso8601)
          - (($c.h | tonumber) * 3600 + ($c.m | tonumber) * 60) * (if $c.sign == "+" then 1 else -1 end)
      else (($s | sub(" "; "T")) + "Z" | fromdateiso8601)
      end
  end;
def slots: if type == "array" then . else (.slots // .data // []) end;
def num(f): (f // 0) | if type == "string" then tonumber else . end;
'
if [[ -z $now ]]; then now_s=$(now_epoch)
elif is_uint "$now"; then now_s=$now
else now_s=$(jq -n --arg t "$now" "$JQ_DEFS"' $t | epoch') || die "bad --now"; fi

decision=$(jq -c --argjson now "$now_s" \
  --argjson lead $(( SCALE_LEAD_MINUTES * 60 )) --argjson tail $(( SCALE_TAIL_MINUTES * 60 )) \
  --argjson thr "$REDIS_LARGE_THRESHOLD" "$JQ_DEFS"'
  [slots[] | {
      start: ((.starts_at // .start // .from // .live_starts_at) | epoch),
      end:   ((.ends_at // .end // .until // .live_ends_at) | epoch),
      nodes: num(.engine_nodes // .nodes // .node_count // 1),
      booked: num(.booked // .expected // .expected_count // .students),
      redis: (.redis // null)
  }] as $s
  | [$s[] | select(.start - $lead <= $now and $now <= .end + $tail)] as $active
  | { slots: ($s | length),
      active: ($active | length),
      nodes: ([1] + [$active[].nodes] | max),
      booked: ([0] + [$active[].booked] | max),
      redis_large: ([$active[] | select(.redis == "large" or .booked > $thr)] | length > 0),
      next: ([$s[] | select(.start - $lead > $now)] | sort_by(.start) | first
             | if . == null then null else {start: (.start | todate), nodes, booked} end) }
' <<<"$json") || die "could not interpret the plan -- nothing changed"

want=$(jq -r '.nodes' <<<"$decision")
is_uint "$want" || die "plan gave a non-integer node count ($want)"
(( want <= MAX_ENGINE_NODES )) || die "plan wants $want nodes > MAX_ENGINE_NODES=$MAX_ENGINE_NODES -- check the bookings; nothing changed"
log "plan @ $(jq -rn --argjson t "$now_s" '$t | todate'): $(jq -r '"\(.slots) slot(s), \(.active) active, max booked \(.booked), wanted nodes \(.nodes), redis large \(.redis_large)"' <<<"$decision")"
log "next slot: $(jq -r '.next // "none" | if type == "object" then "\(.start) (\(.nodes) nodes, \(.booked) booked)" else . end' <<<"$decision")"

# ---------------------------------------------------------------- engine fleet
require_cmd doctl
have=0
while IFS=$'\t' read -r id _name _pub _priv _status tags _rest; do
  [[ -n ${id:-} ]] || continue
  if has_tag "$tags" "$ENGINE_DRAIN_TAG"; then continue; fi
  have=$((have + 1))
done <<<"$(droplets_by_tag "$ENGINE_NODE_TAG")"
log "engine nodes in service: $have"

if (( want > have )); then
  log "scaling out to $want"
  "$SCRIPTS_DIR/scale-out.sh" ${dry[@]+"${dry[@]}"} "$want"
elif (( want < have )); then
  if [[ $no_scale_in == 1 ]]; then log "would scale in to $want (--no-scale-in)"
  elif [[ -e $HOLD_FILE ]]; then log "scale-in held by $HOLD_FILE"
  else
    log "scaling in to $want"
    "$SCRIPTS_DIR/scale-in.sh" ${dry[@]+"${dry[@]}"} --keep "$want" || warn "scale-in refused or failed; will retry next run"
  fi
else
  log "engine fleet matches the plan"
fi

# ---------------------------------------------------------------- redis
if [[ $REDIS_AUTO_RESIZE == yes && $no_redis == 0 ]]; then
  if [[ $REDIS_SENTINEL != yes ]]; then
    warn "REDIS_AUTO_RESIZE=yes needs Sentinel (offline resizes are manual) -- skipping Redis"
  else
    burst=$(droplets_by_tag "$REDIS_BURST_TAG" | awk 'NR == 1')
    large=$(jq -r '.redis_large' <<<"$decision")
    active=$(jq -r '.active' <<<"$decision")
    if [[ $large == true && -z $burst ]]; then
      log "adding the Redis replica for the busy slot"
      "$SCRIPTS_DIR/resize-redis.sh" ${dry[@]+"${dry[@]}"} up
    elif [[ $large == false && $active == 0 && -n $burst ]]; then
      if [[ $no_scale_in == 1 || -e $HOLD_FILE ]]; then log "Redis scale-down held"
      else
        log "removing the Redis replica (no active slot)"
        "$SCRIPTS_DIR/resize-redis.sh" ${dry[@]+"${dry[@]}"} down || warn "resize-redis down refused or failed; will retry next run"
      fi
    else
      log "Redis matches the plan (large=$large, burst node: ${burst:+yes}${burst:-no})"
    fi
  fi
fi
