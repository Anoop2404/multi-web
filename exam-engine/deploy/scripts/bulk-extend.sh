#!/usr/bin/env bash
# Incident tool: extend time for every attempt of a run in the given states (engine internal API).
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: bulk-extend.sh --run RUN --minutes N --reason TEXT --actor TEXT [options]

Calls POST /internal/runs/{run}/attempts/{reg_id}/actions {"action":"extend",...} for every attempt of
RUN in the selected states. Each action is emitted as an attempt.action event, so Laravel's audit log
records it with the actor. Use it after a platform-side outage (runbook "Total engine outage" and
"Extend time after an outage"); individual students are extended from the live monitor instead.

Must run where the internal API accepts you: on the aaPanel server (the Cloudflare WAF only lets that
IP reach /internal/*). URL and token are read from the environment or from Laravel's .env.

Required
  --run RUN           tenantId:examId (shown in the live monitor; also GET /api/sahodaya/{code})
  --minutes N         minutes to add (1..120)
  --reason TEXT       e.g. "platform outage 10:42-10:51 IST"
  --actor TEXT        e.g. "platform-oncall:anoop (incident 2026-11-20)"

Options
  --state S           attempt state to include; repeatable (default: writing and locked)
  --school-id ID      only this school
  --laravel-env FILE  read EXAM_ENGINE_INTERNAL_URL / _TOKEN from this .env
                      (default: environment, else /www/wwwroot/sahodaya.truecampus.in/.env)
  --parallel N        concurrent requests (default 8)
  --dry-run           list how many attempts would be extended
  -h, --help
EOF
}

run_id='' minutes='' reason='' actor='' school='' laravel_env='' parallel=8 states=()
while (($#)); do
  case $1 in
    --run) run_id=${2:?}; shift 2 ;;
    --minutes) minutes=${2:?}; shift 2 ;;
    --reason) reason=${2:?}; shift 2 ;;
    --actor) actor=${2:?}; shift 2 ;;
    --state) states+=("${2:?}"); shift 2 ;;
    --school-id) school=${2:?}; shift 2 ;;
    --laravel-env) laravel_env=${2:?}; shift 2 ;;
    --parallel) parallel=${2:?}; shift 2 ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done
[[ -n $run_id && -n $reason && -n $actor ]] || { usage >&2; exit 2; }
is_uint "$minutes" && (( minutes >= 1 && minutes <= 120 )) || die "--minutes must be 1..120"
is_uint "$parallel" && (( parallel >= 1 && parallel <= 32 )) || die "--parallel must be 1..32"
(( ${#states[@]} > 0 )) || states=(writing locked)
require_cmd curl jq xargs

envget() { grep -m1 "^$1=" "$2" | cut -d= -f2- | sed -e 's/^["'\'']//' -e 's/["'\'']$//' | tr -d '\r'; }
url=${EXAM_ENGINE_INTERNAL_URL:-} token=${EXAM_ENGINE_INTERNAL_TOKEN:-}
if [[ -z $url || -z $token ]]; then
  f=${laravel_env:-/www/wwwroot/sahodaya.truecampus.in/.env}
  [[ -r $f ]] || die "set EXAM_ENGINE_INTERNAL_URL/TOKEN or pass --laravel-env (cannot read $f)"
  url=$(envget EXAM_ENGINE_INTERNAL_URL "$f"); token=$(envget EXAM_ENGINE_INTERNAL_TOKEN "$f")
fi
[[ -n $url && -n $token ]] || die "internal URL/token not found"
url=${url%/}
run_enc=$(jq -rn --arg r "$run_id" '$r | @uri')

# Token goes through a curl config on stdin, never on the command line.
api() { # METHOD PATH [JSON]
  local m=$1 p=$2 d=${3:-}
  if [[ -n $d ]]; then
    printf 'header = "Authorization: Bearer %s"\n' "$token" |
      curl -sS --max-time 20 -K - -X "$m" -H 'Content-Type: application/json' -H 'Accept: application/json' --data "$d" "$url$p"
  else
    printf 'header = "Authorization: Bearer %s"\n' "$token" |
      curl -sS --max-time 20 -K - -X "$m" -H 'Accept: application/json' "$url$p"
  fi
}

ids_file=$(mktemp); trap 'rm -f "$ids_file"' EXIT
for st in "${states[@]}"; do
  page=1
  while :; do
    q="state=$st&page=$page&per_page=200"
    [[ -n $school ]] && q="$q&school_id=$(jq -rn --arg s "$school" '$s | @uri')"
    resp=$(api GET "/internal/runs/$run_enc/attempts?$q") || die "listing attempts failed"
    jq -e '.data' >/dev/null 2>&1 <<<"$resp" || die "unexpected response: $(printf '%s' "$resp" | head -c 300)"
    n=$(jq '.data | length' <<<"$resp")
    jq -r '.data[].reg_id' <<<"$resp" >> "$ids_file"
    total=$(jq -r '.total // 0' <<<"$resp")
    got=$(( (page - 1) * $(jq -r '.per_page // 200' <<<"$resp") + n ))
    (( n > 0 && got < total )) || break
    page=$((page + 1))
  done
done
sort -un "$ids_file" -o "$ids_file"
count=$(wc -l < "$ids_file" | tr -d ' ')
log "$count attempt(s) in state(s) ${states[*]} for run $run_id${school:+ (school $school)}"
if [[ $DRY_RUN == 1 || $count == 0 ]]; then
  [[ $DRY_RUN == 1 ]] && log "dry-run: would extend each by $minutes min (reason: $reason; actor: $actor)"
  exit 0
fi

body=$(jq -cn --argjson m "$minutes" --arg r "$reason" --arg a "$actor" '{action: "extend", minutes: $m, reason: $r, actor: $a}')
export -f api
export url token run_enc body
fails=$(xargs -P "$parallel" -n 1 bash -c '
  out=$(api POST "/internal/runs/$run_enc/attempts/$1/actions" "$body" 2>&1) || { echo "FAIL $1 $out"; exit 0; }
  if printf "%s" "$out" | grep -q "\"error\""; then echo "FAIL $1 $out"; fi
  exit 0' _ < "$ids_file" | tee /dev/stderr | grep -c '^FAIL' || true)
log "extended $(( count - fails )) of $count attempt(s) by $minutes min; $fails failed"
(( fails == 0 )) || die "do NOT re-run the whole command (extend is not idempotent): extend the FAIL reg_ids above individually"
