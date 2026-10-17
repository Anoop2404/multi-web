#!/usr/bin/env bash
# Exam-day preflight: read-only checks of the whole path (Cloudflare -> LB -> engines -> Redis -> Laravel).
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: exam-day-preflight.sh [options]

Read-only. Prints PASS / WARN / FAIL per check and exits 1 if anything FAILs. Run it at T-3h (after
scale-out), again at T-65 min (before the waiting room opens) and after any incident fix.

Checks
  public path   https://exam.<domain>/health through Cloudflare (cf-ray), /internal/* and /metrics
                blocked from the internet
  LB            DigitalOcean LB active, health check /health, sticky sessions off, every node tagged
                exam-engine attached (or HAProxy running)
  engine nodes  service active, /health ok + redis ok, one version everywhere, open-files limit,
                load / memory / disk, NTP sync, in the LB, no unattended-upgrades running
  Redis         primary role, AOF on and healthy, noeviction, memory < 70 %, replica link + lag,
                Sentinel quorum, ex:events length and Laravel consumer-group lag/pending
  Laravel       the app can reach the engine with its own config (tinker -> ExamEngineClient::health),
                the bearer token is accepted on /internal/*, consumer supervisor programs RUNNING
  clocks        every host NTP-synchronised; offset vs. this machine < 1 s
  monitoring    Prometheus ready, targets up, firing alerts (when MONITOR_HOST is set)

Options
  --expect-nodes N   FAIL unless at least N engine nodes are healthy (default: 1)
  --expect-replica   FAIL unless a Redis replica is connected and in sync (Stage B busy slot)
  --skip-laravel     skip the Laravel checks
  --dry-run          list the checks and exit
  -h, --help
EOF
}

expect_nodes=1 expect_replica=0 skip_laravel=0
while (($#)); do
  case $1 in
    --expect-nodes) expect_nodes=${2:?}; is_uint "$expect_nodes" || die "--expect-nodes needs a number"; shift 2 ;;
    --expect-replica) expect_replica=1; shift ;;
    --skip-laravel) skip_laravel=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done
if [[ $DRY_RUN == 1 ]]; then usage | sed -n '/^Checks/,/^Options/p' | sed '$d'; exit 0; fi

load_ops_env
require_cmd doctl jq ssh curl perl awk

npass=0 nwarn=0 nfail=0
section() { printf '\n== %s\n' "$*"; }
pass() { npass=$((npass + 1)); printf 'PASS  %s\n' "$*"; }
wrn()  { nwarn=$((nwarn + 1)); printf 'WARN  %s\n' "$*"; }
fail() { nfail=$((nfail + 1)); printf 'FAIL  %s\n' "$*"; }
kv() { sed -n "s/^$1=//p" <<<"$2" | awk 'NR == 1'; }
local_time() { perl -MTime::HiRes=time -e 'printf "%.3f", time'; }
abs_gt() { awk -v x="$1" -v l="$2" 'BEGIN { if (x < 0) x = -x; exit !(x > l) }'; }

# Remote host report used for clocks on every host.
HOST_REPORT='
echo "now=$(date +%s.%N)"
echo "ntp=$(timedatectl show -p NTPSynchronized --value 2>/dev/null || echo unknown)"
echo "chrony=$(chronyc tracking 2>/dev/null | awk -F": " "/^System time/ { split(\$2, a, \" \"); print a[1] }")"
'
check_clock() { # LABEL REPORT T0 T1
  local label=$1 report=$2 t0=$3 t1=$4 now ntp chrony off
  now=$(kv now "$report"); ntp=$(kv ntp "$report"); chrony=$(kv chrony "$report")
  if [[ $ntp != yes ]]; then fail "$label: NTP not synchronised (timedatectl NTPSynchronized=$ntp)"; return; fi
  if [[ -n $chrony ]] && abs_gt "$chrony" 0.1; then wrn "$label: chrony offset ${chrony}s"; fi
  # The SSH round trip makes this coarse; it catches gross skew, NTP catches the rest.
  off=$(awk -v n="$now" -v a="$t0" -v b="$t1" 'BEGIN { printf "%.2f", n - (a + b) / 2 }')
  if abs_gt "$off" 2; then fail "$label: clock differs from this machine by ${off}s"
  elif abs_gt "$off" 1; then wrn "$label: clock differs from this machine by ${off}s (check this machine's NTP too)"
  else pass "$label: NTP synced${chrony:+ (chrony ${chrony}s)}, offset vs here ${off}s"; fi
}

# ---------------------------------------------------------------- public path
section "Public path ($EXAM_PUBLIC_URL)"
if [[ -z $EXAM_PUBLIC_URL ]]; then
  fail "EXAM_PUBLIC_URL is not set"
else
  hdrs=$(mktemp); trap 'rm -f "$hdrs"' EXIT
  body=$(curl -sS --max-time 10 -D "$hdrs" "$EXAM_PUBLIC_URL/health" 2>&1 || true)
  if jq -e '.ok == true' >/dev/null 2>&1 <<<"$body"; then
    pass "/health ok (version $(jq -r '.version // "?"' <<<"$body"), redis $(jq -r '.redis // "?"' <<<"$body"))"
  else
    fail "/health not ok: $(printf '%s' "$body" | head -c 200)"
  fi
  if grep -qi '^cf-ray:' "$hdrs"; then pass "served through Cloudflare"; else wrn "no cf-ray header: is the DNS record proxied (orange cloud)?"; fi
  code=$(http_status "$EXAM_PUBLIC_URL/internal/runs/preflight%3A0/summary")
  case $code in
    403) pass "/internal/* blocked from the internet (403)" ;;
    401|404) wrn "/internal/* reachable from the internet ($code) -- only the bearer token protects it; add the Cloudflare WAF rule" ;;
    *) wrn "/internal/* from here returned $code" ;;
  esac
  code=$(http_status "$EXAM_PUBLIC_URL/metrics")
  case $code in
    403|404) pass "/metrics not public ($code)" ;;
    200) fail "/metrics is public -- add the Cloudflare WAF rule / proxy deny" ;;
    *) wrn "/metrics returned $code" ;;
  esac
fi

# ---------------------------------------------------------------- load balancer
section "Load balancer ($LB_KIND)"
lb_ids=''
case $LB_KIND in
  do)
    require_set LB_ID
    if lbj=$(dctl compute load-balancer get "$LB_ID" -o json 2>/dev/null | jq -c '.[0]'); then
      st=$(jq -r '.status' <<<"$lbj")
      [[ $st == active ]] && pass "LB $LB_ID active" || fail "LB $LB_ID status $st"
      hc=$(jq -r '.health_check | "\(.protocol):\(.port)\(.path // "")"' <<<"$lbj")
      [[ $hc == *"/health" ]] && pass "health check $hc" || fail "health check is $hc (want http:8090/health)"
      sticky=$(jq -r '.sticky_sessions.type // "none"' <<<"$lbj")
      [[ $sticky == none ]] && pass "sticky sessions off" || fail "sticky sessions are $sticky (must be none)"
      tag=$(jq -r '.tag // ""' <<<"$lbj")
      [[ $tag == "$ENGINE_LB_TAG" ]] && pass "targets tag $tag" || wrn "LB targets tag '$tag' (expected $ENGINE_LB_TAG)"
      lb_ids=$(jq -r '.droplet_ids // [] | .[] | tostring' <<<"$lbj" | sort)
      tagged=$(droplets_by_tag "$ENGINE_LB_TAG" | cut -f1 | sed '/^$/d' | sort)
      if [[ $lb_ids == "$tagged" ]]; then pass "LB has all $(printf '%s\n' "$tagged" | sed '/^$/d' | wc -l | tr -d ' ') tagged node(s)"
      else fail "LB droplets [$(echo $lb_ids)] != tagged [$(echo $tagged)]"; fi
    else
      fail "cannot read LB $LB_ID (doctl context $DO_CONTEXT)"
    fi
    ;;
  haproxy)
    if out=$(ssh_node "$HAPROXY_HOST" 'systemctl is-active haproxy && sudo haproxy -c -q -f /etc/haproxy/conf.d && echo config-ok' 2>&1); then
      pass "HAProxy active, config valid"
    else
      fail "HAProxy on $HAPROXY_HOST: $out"
    fi
    ;;
  nginx) pass "Stage A-lite (nginx on the engine droplet): no separate LB" ;;
esac

# ---------------------------------------------------------------- engine nodes
section "Engine nodes"
NODE_REPORT='
pid=$(systemctl show -p MainPID --value exam-engine)
echo "active=$(systemctl is-active exam-engine)"
echo "health=$(curl -fsS --max-time 5 http://127.0.0.1:8090/health 2>/dev/null | tr -d "\n")"
echo "nofile=$(awk "/Max open files/ {print \$4}" /proc/$pid/limits 2>/dev/null)"
echo "load=$(cut -d" " -f1 /proc/loadavg)"
echo "cpus=$(nproc)"
echo "memavail=$(awk "/^MemTotal/ {t=\$2} /^MemAvailable/ {a=\$2} END {printf \"%d\", a*100/t}" /proc/meminfo)"
echo "disk=$(df -P / | awk "NR==2 {sub(\"%\", \"\", \$5); print \$5}")"
echo "upgrades=$(pgrep -f unattended-upgrade >/dev/null && echo running || echo idle)"
echo "release=$(cat /opt/exam-engine/current/VERSION 2>/dev/null)"
'
healthy=0 versions='' base_version=''
fleet=$(droplets_by_tag "$ENGINE_NODE_TAG")
[[ -n $fleet ]] || fail "no droplets tagged $ENGINE_NODE_TAG"
while IFS=$'\t' read -r id name pub priv status tags size _created; do
  [[ -n ${id:-} ]] || continue
  if has_tag "$tags" "$ENGINE_DRAIN_TAG"; then wrn "$name is draining (finish with scale-in.sh)"; continue; fi
  t0=$(local_time)
  rep=$(ssh_node "$pub" "sudo bash -s" <<<"$NODE_REPORT$HOST_REPORT" 2>/dev/null || true)
  t1=$(local_time)
  if [[ -z $rep ]]; then fail "$name ($pub): no SSH"; continue; fi
  h=$(kv health "$rep")
  if [[ $(kv active "$rep") == active ]] && jq -e '.ok == true' >/dev/null 2>&1 <<<"$h"; then
    v=$(jq -r '.version // "?"' <<<"$h"); r=$(jq -r '.redis // "?"' <<<"$h")
    healthy=$((healthy + 1)); versions="$versions$v"$'\n'
    if has_tag "$tags" "$ENGINE_BASE_TAG"; then base_version=$v; fi
    if [[ $r == ok ]]; then pass "$name ($size) healthy, version $v, redis ok"; else fail "$name redis=$r"; fi
  else
    fail "$name: service $(kv active "$rep"), /health: ${h:-no answer}"
  fi
  nf=$(kv nofile "$rep")
  if is_uint "$nf" && (( nf >= 100000 )); then pass "$name open-files limit $nf"; else fail "$name open-files limit '${nf}' (< 100000)"; fi
  load=$(kv load "$rep"); cpus=$(kv cpus "$rep")
  if awk -v l="$load" -v c="$cpus" 'BEGIN { exit !(l > c * 0.8) }'; then wrn "$name load $load on $cpus vCPU"; fi
  ma=$(kv memavail "$rep"); is_uint "$ma" && (( ma < 20 )) && wrn "$name only $ma% memory available"
  dk=$(kv disk "$rep"); is_uint "$dk" && (( dk > 80 )) && wrn "$name disk $dk% full"
  if [[ $(kv upgrades "$rep") == running ]]; then wrn "$name: unattended-upgrades is running (stop apt timers during the freeze)"; fi
  if [[ $LB_KIND == do ]]; then
    if has_tag "$tags" "$ENGINE_LB_TAG" && grep -qx "$id" <<<"$lb_ids"; then :; else fail "$name is not in the LB"; fi
  fi
  check_clock "$name clock" "$rep" "$t0" "$t1"
done <<<"$fleet"
nver=$(printf '%s' "$versions" | sed '/^$/d' | sort -u | wc -l | tr -d ' ')
if (( nver == 1 )); then pass "all healthy nodes run $(printf '%s' "$versions" | sed '/^$/d' | sort -u)"
elif (( nver > 1 )); then fail "mixed versions: $(printf '%s' "$versions" | sed '/^$/d' | sort | uniq -c | tr '\n' ' ')"; fi
if (( healthy >= expect_nodes )); then pass "$healthy healthy engine node(s) (expected >= $expect_nodes)"
else fail "$healthy healthy engine node(s), expected >= $expect_nodes (scale-out.sh $expect_nodes)"; fi

# ---------------------------------------------------------------- Redis
section "Redis"
REDIS_REPORT='
c() { exam-redis-cli "$@" 2>&1 | tr -d "\r"; }
c INFO replication | sed "s/^/repl./; s/:/=/"
c INFO persistence | sed "s/^/pers./; s/:/=/"
c INFO memory | sed "s/^/mem./; s/:/=/"
echo "policy=$(c CONFIG GET maxmemory-policy | sed -n 2p)"
echo "xlen=$(c XLEN ex:events)"
c XINFO GROUPS ex:events | paste - - | sed "s/^/grp./; s/\t/=/"
if systemctl is-active --quiet redis-sentinel; then echo "sentinel=active"; echo "ckquorum=$(redis-cli -p 26379 SENTINEL ckquorum '"$REDIS_MASTER_NAME"' 2>&1 | tr -d "\r")"; echo "smaster=$(redis-cli -p 26379 SENTINEL get-master-addr-by-name '"$REDIS_MASTER_NAME"' | head -n 1 | tr -d "\r")"; else echo "sentinel=inactive"; fi
'
redis_rows=$(droplets_by_tag "$REDIS_TAG")
primary_pub='' replica_pubs=() redis_names=''
while IFS=$'\t' read -r id name pub priv status tags size _c; do
  [[ -n ${id:-} ]] || continue
  t0=$(local_time)
  rep=$(ssh_node "$pub" "sudo bash -s" <<<"$REDIS_REPORT$HOST_REPORT" 2>/dev/null || true)
  t1=$(local_time)
  if [[ -z $rep ]]; then fail "$name ($pub): no SSH"; continue; fi
  role=$(kv repl.role "$rep")
  if [[ $role != master && $role != slave ]]; then fail "$name: no INFO from Redis (is redis-server running? sudo exam-redis-cli PING)"; continue; fi
  check_clock "$name clock" "$rep" "$t0" "$t1"
  if [[ $role == master ]]; then
    if [[ -n $primary_pub ]]; then fail "two Redis primaries ($redis_names and $name): split brain, see runbook 'Redis failover'"; fi
    primary_pub=$pub; redis_names="$name"
    pass "$name is primary ($size)"
    [[ $(kv pers.aof_enabled "$rep") == 1 ]] && pass "AOF enabled" || fail "AOF disabled on the primary"
    [[ $(kv pers.aof_last_write_status "$rep") == ok ]] && pass "AOF last write ok" || fail "AOF write status $(kv pers.aof_last_write_status "$rep")"
    [[ $(kv pers.aof_last_bgrewrite_status "$rep") == ok ]] || wrn "AOF last rewrite $(kv pers.aof_last_bgrewrite_status "$rep")"
    [[ $(kv pers.rdb_last_bgsave_status "$rep") == ok ]] || wrn "last backup BGSAVE $(kv pers.rdb_last_bgsave_status "$rep")"
    [[ $(kv policy "$rep") == noeviction ]] && pass "maxmemory-policy noeviction" || fail "maxmemory-policy is $(kv policy "$rep")"
    used=$(kv mem.used_memory "$rep"); max=$(kv mem.maxmemory "$rep")
    if is_uint "$max" && (( max > 0 )); then
      pct=$(( used * 100 / max ))
      if (( pct >= 85 )); then fail "memory $pct% of maxmemory"
      elif (( pct >= 70 )); then wrn "memory $pct% of maxmemory (alert threshold 70 %)"
      else pass "memory $pct% of maxmemory ($(kv mem.used_memory_human "$rep") / $(kv mem.maxmemory_human "$rep"))"; fi
    else
      fail "maxmemory is not set"
    fi
    slaves=$(kv repl.connected_slaves "$rep")
    if (( expect_replica == 1 )); then
      [[ ${slaves:-0} -ge 1 ]] && pass "$slaves replica(s) connected" || fail "no replica connected (resize-redis.sh up)"
    fi
    xlen=$(kv xlen "$rep")
    if is_uint "$xlen"; then
      if (( xlen > 400000 )); then fail "ex:events has $xlen entries (MAXLEN ~500000): unconsumed events will be trimmed"
      else pass "ex:events length $xlen"; fi
    else
      wrn "ex:events not readable (${xlen:-empty}) -- no run armed yet?"
    fi
    lag=$(kv grp.lag "$rep"); pending=$(kv grp.pending "$rep"); gname=$(kv grp.name "$rep")
    if [[ -z $gname ]]; then wrn "no consumer group on ex:events yet (Laravel consumer never claimed?)"
    else
      if is_uint "$lag" && (( lag > 5000 )); then fail "consumer group $gname lag $lag (consumers behind)"
      elif is_uint "$lag" && (( lag > 0 )); then wrn "consumer group $gname lag $lag, pending ${pending:-?}"
      else pass "consumer group $gname lag ${lag:-?}, pending ${pending:-?}"; fi
    fi
  else
    replica_pubs+=("$pub")
    link=$(kv repl.master_link_status "$rep")
    [[ $link == up ]] && pass "$name is replica of $(kv repl.master_host "$rep"), link up" || fail "$name replica link $link"
  fi
  if [[ $(kv sentinel "$rep") == active ]]; then
    q=$(kv ckquorum "$rep")
    [[ $q == OK* ]] && pass "$name sentinel: $q" || fail "$name sentinel: $q"
  elif [[ $REDIS_SENTINEL == yes ]]; then
    fail "$name: sentinel not running but REDIS_SENTINEL=yes"
  fi
done <<<"$redis_rows"
[[ -n $primary_pub ]] || fail "no Redis primary found among nodes tagged $REDIS_TAG"
if (( expect_replica == 1 && ${#replica_pubs[@]} == 0 )); then fail "no replica node (resize-redis.sh up)"; fi

# ---------------------------------------------------------------- Laravel
if [[ $skip_laravel == 0 ]]; then
  section "Laravel ($LARAVEL_SSH:$LARAVEL_PATH)"
  LARAVEL_REPORT='
envget() { grep -m1 "^$1=" .env | cut -d= -f2- | sed -e "s/^[\"\x27]//" -e "s/[\"\x27]\r\{0,1\}$//" | tr -d "\r"; }
url=$(envget EXAM_ENGINE_INTERNAL_URL); tok=$(envget EXAM_ENGINE_INTERNAL_TOKEN)
echo "url=$url"
[ -n "$tok" ] && echo "token=set" || echo "token=missing"
[ -n "$(envget EXAM_ENGINE_LAUNCH_SECRET_KEY)" ] && echo "launchkey=set" || echo "launchkey=missing"
echo "public=$(envget EXAM_ENGINE_PUBLIC_URL)"
if [ -n "$url" ] && [ -n "$tok" ]; then
  echo "authcode=$(printf "header = \"Authorization: Bearer %s\"\n" "$tok" | curl -s -o /dev/null -w "%{http_code}" --max-time 10 -K - "$url/internal/runs/preflight%3A0/summary")"
fi
echo "tinker=$('"$LARAVEL_ARTISAN"' tinker --execute="echo json_encode(app(App\Services\ExamEngine\ExamEngineClient::class)->health());" 2>&1 | tail -n 1)"
echo "consumers=$('"$SUPERVISORCTL"' status "'"$CONSUMER_PROGRAM"':*" 2>&1 | awk "{print \$2}" | sort | uniq -c | tr "\n" " ")"
'
  t0=$(local_time)
  rep=$(laravel_exec "bash -s" <<<"$LARAVEL_REPORT$HOST_REPORT" 2>/dev/null || true)
  t1=$(local_time)
  if [[ -z $rep ]]; then
    fail "cannot run checks on the Laravel host ($LARAVEL_SSH)"
  else
    [[ -n $(kv url "$rep") ]] && pass "EXAM_ENGINE_INTERNAL_URL=$(kv url "$rep")" || fail "EXAM_ENGINE_INTERNAL_URL missing in .env"
    [[ $(kv token "$rep") == set ]] && pass "EXAM_ENGINE_INTERNAL_TOKEN set" || fail "EXAM_ENGINE_INTERNAL_TOKEN missing in .env"
    [[ $(kv launchkey "$rep") == set ]] || wrn "EXAM_ENGINE_LAUNCH_SECRET_KEY missing (portal 'Enter exam' button will fail)"
    [[ -n $(kv public "$rep") ]] || wrn "EXAM_ENGINE_PUBLIC_URL missing (defaults to 127.0.0.1)"
    case $(kv authcode "$rep") in
      404) pass "internal API reachable from Laravel, token accepted" ;;
      401) fail "internal API rejects Laravel's token (EXAM_ENGINE_INTERNAL_TOKEN differs between Laravel and engine.env)" ;;
      403) fail "internal API blocked for Laravel (Cloudflare WAF rule must allow the aaPanel server IP)" ;;
      000|'') fail "internal API unreachable from Laravel" ;;
      *) wrn "internal API probe returned $(kv authcode "$rep")" ;;
    esac
    tk=$(kv tinker "$rep")
    if jq -e '.ok == true' >/dev/null 2>&1 <<<"$tk"; then pass "Laravel (config as loaded) -> engine /health ok"
    else wrn "tinker health check: ${tk:-no output} (run php artisan optimize after .env changes)"; fi
    cons=$(kv consumers "$rep")
    if [[ $cons == *RUNNING* && $cons != *STOPPED* && $cons != *FATAL* && $cons != *BACKOFF* && $cons != *EXITED* ]]; then
      pass "consumer programs: $cons"
    else
      fail "consumer programs ($CONSUMER_PROGRAM): ${cons:-not found}"
    fi
    check_clock "Laravel host clock" "$rep" "$t0" "$t1"
  fi
fi

# ---------------------------------------------------------------- monitoring
if [[ -n $MONITOR_HOST ]]; then
  section "Monitoring ($MONITOR_HOST)"
  mon=$(ssh_node "$MONITOR_HOST" 'curl -fsS --max-time 5 http://127.0.0.1:9090/-/ready >/dev/null && echo ready=yes || echo ready=no
    echo "down=$(curl -fsS --max-time 5 "http://127.0.0.1:9090/api/v1/query?query=up%3D%3D0" | jq -r "[.data.result[].metric.instance] | join(\",\")")"
    echo "firing=$(curl -fsS --max-time 5 "http://127.0.0.1:9090/api/v1/query?query=ALERTS%7Balertstate%3D%22firing%22%7D" | jq -r "[.data.result[].metric.alertname] | unique | join(\",\")")"' 2>/dev/null || true)
  [[ $(kv ready "$mon") == yes ]] && pass "Prometheus ready" || fail "Prometheus not ready on $MONITOR_HOST"
  d=$(kv down "$mon"); [[ -z $d ]] && pass "all scrape targets up" || wrn "targets down: $d"
  f=$(kv firing "$mon"); [[ -z $f ]] && pass "no alerts firing" || wrn "alerts firing: $f"
fi

# ---------------------------------------------------------------- summary
section "Summary"
printf '%d pass, %d warn, %d fail\n' "$npass" "$nwarn" "$nfail"
printf 'Not checked here (runbook): deploy freeze since T-2h, apt timers stopped, on-call roster, booking matches scale.\n'
(( nfail == 0 ))
