#!/usr/bin/env bash
# Shared helpers for exam-engine/deploy/scripts. Sourced, never executed.
# Works with bash 3.2 (macOS) and bash 5 (Linux): no associative arrays, no mapfile, and every
# possibly-empty array is expanded as ${arr[@]+"${arr[@]}"} because of `set -u`.
# shellcheck shell=bash disable=SC2034

DEPLOY_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
SCRIPTS_DIR="$DEPLOY_DIR/scripts"
STATE_DIR="$DEPLOY_DIR/.state"
DRY_RUN=${DRY_RUN:-0}

# ---------------------------------------------------------------- logging / control
log()  { printf '%s %s\n' "$(date -u +%H:%M:%SZ)" "$*" >&2; }
warn() { log "WARN  $*"; }
die()  { log "ERROR $*"; exit 1; }

# run CMD...  executes CMD, or only prints it under --dry-run. Use for every state-changing call.
run() {
  if [[ $DRY_RUN == 1 ]]; then
    printf '[dry-run] %s\n' "$(quote_cmd "$@")" >&2
    return 0
  fi
  "$@"
}

quote_cmd() {
  local out='' a
  for a in "$@"; do out="$out $(printf '%q' "$a")"; done
  printf '%s' "${out# }"
}

require_cmd() {
  local c
  for c in "$@"; do command -v "$c" >/dev/null 2>&1 || die "missing command: $c"; done
}

is_uint() { [[ ${1:-} =~ ^[0-9]+$ ]]; }

# require_set VAR... : each must be non-empty and not a CHANGE_ME placeholder
require_set() {
  local v
  for v in "$@"; do
    case ${!v:-} in
      ""|CHANGE_ME*) die "set $v in env/ops.env" ;;
    esac
  done
}

now_epoch() { date -u +%s; }

# Absolute path for a deploy-relative path.
deploy_path() {
  case $1 in
    /*) printf '%s' "$1" ;;
    *) printf '%s/%s' "$DEPLOY_DIR" "$1" ;;
  esac
}

# Simple cross-host mutual exclusion (no flock on macOS): mkdir is atomic.
LOCK_DIR=''
acquire_lock() {
  local name=$1 dir pid
  mkdir -p "$STATE_DIR"
  dir="$STATE_DIR/$name.lock"
  if ! mkdir "$dir" 2>/dev/null; then
    pid=$(cat "$dir/pid" 2>/dev/null || true)
    if [[ -n $pid ]] && kill -0 "$pid" 2>/dev/null; then
      die "another $name is running (pid $pid); lock $dir"
    fi
    warn "removing stale lock $dir"
    rm -rf "$dir"
    mkdir "$dir" || die "cannot take lock $dir"
  fi
  echo $$ > "$dir/pid"
  LOCK_DIR=$dir
  trap 'release_lock' EXIT
}
release_lock() { [[ -n $LOCK_DIR ]] && rm -rf "$LOCK_DIR"; LOCK_DIR=''; }

# ---------------------------------------------------------------- configuration
load_ops_env() {
  local f=${OPS_ENV:-$DEPLOY_DIR/env/ops.env}
  [[ -r $f ]] || die "ops config not found: $f (copy env/ops.env.example to env/ops.env)"
  set -a
  # shellcheck disable=SC1090
  . "$f"
  set +a
  : "${DO_CONTEXT:=}" "${DO_REGION:=blr1}" "${DO_VPC_UUID:=}" "${DO_SSH_KEYS:=}" "${DO_PROJECT_ID:=}"
  : "${ENGINE_LB_TAG:=exam-engine}" "${ENGINE_NODE_TAG:=exam-engine-node}"
  : "${ENGINE_BASE_TAG:=exam-engine-base}" "${ENGINE_BURST_TAG:=exam-engine-burst}"
  : "${ENGINE_DRAIN_TAG:=exam-engine-draining}"
  : "${REDIS_TAG:=exam-redis}" "${REDIS_BASE_TAG:=exam-redis-base}" "${REDIS_BURST_TAG:=exam-redis-burst}"
  : "${MONITOR_TAG:=exam-monitor}"
  : "${ENGINE_BASE_NAME:=exam-engine-1}" "${ENGINE_BASE_SIZE:=c-2}" "${ENGINE_BURST_SIZE:=c-4}"
  : "${ENGINE_BURST_PREFIX:=exam-engine-burst}" "${MAX_ENGINE_NODES:=12}"
  : "${ENGINE_IMAGE_BUILD_SIZE:=s-1vcpu-1gb}" "${ENGINE_SNAPSHOT_PREFIX:=exam-engine-image}"
  : "${ENGINE_SNAPSHOT_ID:=}" "${ENGINE_ENV_FILE:=env/engine.env}"
  : "${ENGINE_RELEASE_TARBALL:=}" "${ENGINE_RELEASE_URL:=}" "${ENGINE_RELEASE_SHA256:=}"
  : "${INSTALL_NODE_EXPORTER:=yes}" "${INSTALL_REDIS_EXPORTER:=yes}"
  : "${REDIS_BASE_NAME:=redis-exam-1}" "${REDIS_BASE_SIZE:=s-2vcpu-4gb}" "${REDIS_LARGE_SIZE:=s-4vcpu-8gb}"
  : "${REDIS_BURST_NAME:=redis-exam-burst}" "${REDIS_PASSWORD_FILE:=env/redis.password}"
  : "${REDIS_SENTINEL:=no}" "${REDIS_MASTER_NAME:=exam}" "${REDIS_RESIZE_METHOD:=}"
  : "${LB_KIND:=do}" "${LB_ID:=}" "${HAPROXY_HOST:=}" "${EXAM_PUBLIC_URL:=}"
  : "${LB_ADMIT_SECONDS:=40}" "${DRAIN_SECONDS:=60}"
  : "${OPS_SSH_USER:=ops}" "${SSH_IDENTITY:=}" "${MONITOR_HOST:=}"
  : "${LARAVEL_SSH:=local}" "${LARAVEL_PATH:=/www/wwwroot/sahodaya.truecampus.in}"
  : "${LARAVEL_ARTISAN:=sudo -u www php artisan}" "${PLAN_CAPACITY_ARGS:=}"
  : "${SUPERVISORCTL:=supervisorctl}" "${CONSUMER_PROGRAM:=exam-engine-consumer}"
  : "${SCALE_LEAD_MINUTES:=120}" "${SCALE_TAIL_MINUTES:=30}" "${REDIS_LARGE_THRESHOLD:=2000}"
  : "${REDIS_AUTO_RESIZE:=no}" "${SCALE_IN_WRITING_PER_NODE:=2000}" "${HOLD_FILE:=.state/hold-scale-in}"
  HOLD_FILE=$(deploy_path "$HOLD_FILE")
  mkdir -p "$STATE_DIR"
  chmod 700 "$STATE_DIR" 2>/dev/null || true
}

redis_password() {
  local f pw
  f=$(deploy_path "$REDIS_PASSWORD_FILE")
  [[ -r $f ]] || die "redis password file not found: $f (openssl rand -hex 32 > $f)"
  pw=$(tr -d ' \r\n' < "$f")
  [[ $pw =~ ^[A-Za-z0-9]{24,}$ ]] || die "redis password in $f must be >= 24 letters/digits (use: openssl rand -hex 32)"
  printf '%s' "$pw"
}

engine_env_file() {
  local f
  f=$(deploy_path "$ENGINE_ENV_FILE")
  [[ -r $f ]] || die "engine env not found: $f (copy env/engine.env.example)"
  if grep -v '^[[:space:]]*#' "$f" | grep -q 'CHANGE_ME'; then die "engine env $f still contains CHANGE_ME placeholders"; fi
  printf '%s' "$f"
}

# ---------------------------------------------------------------- templates
# render_template TEMPLATE OUT
#   Lines "<indent>__BLOCK_NAME__" are replaced by the file named in $BLOCK_NAME (each line indented).
#   Tokens __NAME__ are replaced by $TPL_NAME. Missing values abort. Values must not contain ' or newlines
#   (templates place them inside single-quoted shell assignments or plain config values).
render_template() {
  local tpl=$1 out=$2
  [[ -r $tpl ]] || die "template not found: $tpl"
  mkdir -p "$(dirname "$out")"
  ( umask 077; perl -e '
    use strict; use warnings;
    my ($tpl) = @ARGV; my @missing; my %seen;
    sub value_of {
      my ($name) = @_;
      my $k = "TPL_$name";
      if (defined $ENV{$k}) {
        die "render: value of $k contains a quote or newline\n" if $ENV{$k} =~ /[\x27\n\r]/;
        return $ENV{$k};
      }
      push @missing, $k unless $seen{$k}++;
      return "__${name}__";
    }
    sub subst { my ($s) = @_; $s =~ s/__([A-Z][A-Z0-9_]*)__/value_of($1)/ge; return $s; }
    open(my $fh, "<", $tpl) or die "render: cannot read $tpl: $!\n";
    while (my $line = <$fh>) {
      if ($line =~ /^(\s*)__BLOCK_([A-Z0-9_]+)__\s*$/) {
        my ($indent, $name) = ($1, "BLOCK_$2");
        my $path = $ENV{$name};
        if (!defined $path || !-r $path) { push @missing, $name unless $seen{$name}++; next; }
        open(my $bf, "<", $path) or die "render: cannot read $path: $!\n";
        while (my $b = <$bf>) { chomp $b; $b =~ s/\r$//; $b = subst($b); print(length($b) ? "$indent$b\n" : "\n"); }
        close $bf; next;
      }
      print subst($line);
    }
    if (@missing) { print STDERR "render: missing values for: @missing\n"; exit 3; }
  ' "$tpl" > "$out" ) || { rm -f "$out"; die "rendering $tpl failed"; }
}

# Exports the TPL_/BLOCK_ variables for an engine template. Arg: node|image|snapshot.
engine_template_env() {
  local mode=$1
  export BLOCK_EXAM_ENGINE_SERVICE="$DEPLOY_DIR/systemd/exam-engine.service"
  if [[ $mode == image ]]; then
    local stub="$STATE_DIR/engine.env.image-stub"
    printf '# golden image: the real env file is written at boot by the node user-data\n' > "$stub"
    export BLOCK_ENGINE_ENV="$stub"
    export TPL_NODE_MODE=image
  else
    BLOCK_ENGINE_ENV=$(engine_env_file); export BLOCK_ENGINE_ENV
    export TPL_NODE_MODE=node
  fi
  export TPL_ENGINE_RELEASE_URL="$ENGINE_RELEASE_URL"
  export TPL_ENGINE_RELEASE_SHA256="$ENGINE_RELEASE_SHA256"
  export TPL_INSTALL_NODE_EXPORTER="$INSTALL_NODE_EXPORTER"
}

# Exports the TPL_/BLOCK_ variables for redis-node.yaml. Args: ROLE PRIMARY_IP SENTINEL(yes|no)
redis_template_env() {
  local role=$1 primary=${2:-} sentinel=${3:-no}
  TPL_REDIS_PASSWORD=$(redis_password); export TPL_REDIS_PASSWORD
  export TPL_REDIS_MASTER_NAME="$REDIS_MASTER_NAME"
  export TPL_REDIS_ROLE="$role" TPL_REDIS_PRIMARY_IP="$primary" TPL_ENABLE_SENTINEL="$sentinel"
  export TPL_INSTALL_REDIS_EXPORTER="$INSTALL_REDIS_EXPORTER" TPL_INSTALL_NODE_EXPORTER="$INSTALL_NODE_EXPORTER"
  export BLOCK_REDIS_CONF="$DEPLOY_DIR/redis/redis.conf.snippet"
  export BLOCK_SENTINEL_CONF="$DEPLOY_DIR/redis/sentinel.conf.example"
}

# ---------------------------------------------------------------- DigitalOcean (doctl)
dctl() {
  if [[ -n ${DO_CONTEXT:-} ]]; then doctl --context "$DO_CONTEXT" "$@"; else doctl "$@"; fi
}

# droplets_by_tag TAG -> TSV lines: id name public_ip private_ip status tags(comma) size created
droplets_by_tag() {
  dctl compute droplet list --tag-name "$1" -o json | jq -r '
    (. // [])[] | [
      (.id | tostring), .name,
      ([.networks.v4[]? | select(.type == "public")  | .ip_address][0] // "-"),
      ([.networks.v4[]? | select(.type == "private") | .ip_address][0] // "-"),
      .status, ((.tags // []) | if length == 0 then "-" else join(",") end), (.size_slug // .size.slug // "-"), .created_at
    ] | @tsv'
}

# has_tag "tag1,tag2" TAG
has_tag() { case ",$1," in *",$2,"*) return 0 ;; esac; return 1; }

# droplet_field ID FIELD -> json value via jq path on the first droplet (e.g. '.status')
droplet_json() { dctl compute droplet get "$1" -o json | jq -c '.[0]'; }

latest_snapshot_id() {
  dctl compute snapshot list --resource droplet -o json |
    jq -r --arg p "$ENGINE_SNAPSHOT_PREFIX" --arg r "$DO_REGION" '
      [(. // [])[] | select((.name | startswith($p)) and ((.regions // []) | index($r)))]
      | sort_by(.created_at) | last | (.id // empty) | tostring'
}

lb_droplet_ids() {
  dctl compute load-balancer get "$LB_ID" -o json | jq -r '.[0].droplet_ids // [] | .[] | tostring'
}

# ---------------------------------------------------------------- SSH
KNOWN_HOSTS="$STATE_DIR/known_hosts"
ssh_base_opts() {
  printf '%s\n' -o BatchMode=yes -o ConnectTimeout=10 -o ServerAliveInterval=15 \
    -o StrictHostKeyChecking=accept-new -o "UserKnownHostsFile=$KNOWN_HOSTS" -o LogLevel=ERROR
  if [[ -n ${SSH_IDENTITY:-} ]]; then printf '%s\n' -i "$SSH_IDENTITY"; fi
}

# ssh_node HOST CMD...   (HOST may be user@host; default user OPS_SSH_USER)
ssh_node() {
  local host=$1; shift
  [[ $host == *@* ]] || host="$OPS_SSH_USER@$host"
  local opts=() o
  while IFS= read -r o; do opts+=("$o"); done < <(ssh_base_opts)
  ssh "${opts[@]}" "$host" "$@"
}

scp_to_node() {
  local src=$1 host=$2 dst=$3
  [[ $host == *@* ]] || host="$OPS_SSH_USER@$host"
  local opts=() o
  while IFS= read -r o; do opts+=("$o"); done < <(ssh_base_opts)
  scp -q "${opts[@]}" "$src" "$host:$dst"
}

# A new droplet may reuse an IP: forget any old host key for it.
forget_host() { ssh-keygen -R "$1" -f "$KNOWN_HOSTS" >/dev/null 2>&1 || true; }

wait_ssh() {
  local ip=$1 timeout=${2:-300} end
  end=$(( $(now_epoch) + timeout ))
  while (( $(now_epoch) < end )); do
    ssh_node "$ip" true 2>/dev/null && return 0
    sleep 5
  done
  return 1
}

wait_cloud_init() {
  local ip=$1 status
  status=$(ssh_node "$ip" 'sudo cloud-init status --wait >/dev/null 2>&1; sudo cloud-init status' 2>/dev/null || true)
  case $status in
    *done*) return 0 ;;
    *) warn "$ip cloud-init: ${status:-unknown} (see /var/log/cloud-init-output.log)"; return 1 ;;
  esac
}

# node_health IP -> prints /health JSON from the node itself (engine on 127.0.0.1:8090)
node_health() { ssh_node "$1" 'curl -fsS --max-time 5 http://127.0.0.1:8090/health' 2>/dev/null; }

wait_node_health() {
  local ip=$1 timeout=${2:-300} end out
  end=$(( $(now_epoch) + timeout ))
  while (( $(now_epoch) < end )); do
    out=$(node_health "$ip" || true)
    if [[ -n $out ]] && jq -e '.ok == true' >/dev/null 2>&1 <<<"$out"; then
      printf '%s\n' "$out"
      return 0
    fi
    sleep 5
  done
  return 1
}

# push_release IP : install ENGINE_RELEASE_TARBALL (local) or ENGINE_RELEASE_URL on a node
push_release() {
  local ip=$1 tarball url
  tarball=${ENGINE_RELEASE_TARBALL:-}
  url=${ENGINE_RELEASE_URL:-}
  if [[ -n $tarball ]]; then
    tarball=$(deploy_path "$tarball")
    [[ -r $tarball ]] || die "release tarball not found: $tarball"
    run scp_to_node "$tarball" "$ip" /tmp/exam-engine-release.tgz
    run ssh_node "$ip" "sudo /usr/local/sbin/exam-engine-install /tmp/exam-engine-release.tgz && rm -f /tmp/exam-engine-release.tgz"
  elif [[ -n $url ]]; then
    run ssh_node "$ip" "sudo /usr/local/sbin/exam-engine-install $(printf '%q' "$url") $(printf '%q' "$ENGINE_RELEASE_SHA256")"
  fi
}

# Sum of attempts currently writing, read from one engine's /metrics (exam_attempts{state="writing"}).
# Prints nothing if the metric is not exported.
writing_attempts() {
  local ip=$1
  ssh_node "$ip" 'curl -fsS --max-time 5 http://127.0.0.1:8090/metrics' 2>/dev/null |
    awk '/^exam_attempts[{]/ && /state="writing"/ { s += $NF; n++ } END { if (n) printf "%d\n", s }'
}

# ---------------------------------------------------------------- Laravel host
# laravel_exec CMD : run a shell command in LARAVEL_PATH on the Laravel host (or locally)
laravel_exec() {
  local cmd="cd $(printf '%q' "$LARAVEL_PATH") && $1"
  if [[ $LARAVEL_SSH == local || -z $LARAVEL_SSH ]]; then
    bash -c "$cmd"
  else
    ssh_node "$LARAVEL_SSH" "$cmd"
  fi
}

# ---------------------------------------------------------------- HTTP
# http_status URL [curl args...] -> prints the status code (000 when unreachable)
http_status() {
  local url=$1; shift
  curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$@" "$url" || true
}
