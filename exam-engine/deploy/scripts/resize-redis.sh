#!/usr/bin/env bash
# Grow / shrink redis-exam for busy slots, and set up Sentinel (Stage B).
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: resize-redis.sh [options] up|down|status|sentinel-setup

up              prepare Redis for a busy slot
down            return to the always-on footprint
status          show nodes, roles, Sentinel view, memory and replication
sentinel-setup  one-time Stage B step: run Sentinel on redis-exam-1 and exam-engine-1 and print the
                engine env lines (then set REDIS_SENTINEL=yes and run rolling-update.sh --env-only)

Methods (--method, default: failover when REDIS_SENTINEL=yes, else offline)
  failover  No downtime beyond a Sentinel failover blip (a few seconds; clients retry/resync).
            up:   create the burst replica (REDIS_LARGE_SIZE) from cloud-init/redis-node.yaml, wait
                  until it is in sync, then SENTINEL FAILOVER so the big node becomes primary
                  (--no-promote keeps the current primary and only adds the replica).
            down: fail back to redis-exam-1 if the burst node is primary, delete the burst node,
                  SENTINEL RESET the remaining sentinels.
  offline   Stage A only (no Sentinel). Power-cycle resize of redis-exam-1 (CPU/RAM only, disk kept so
            it can shrink again): Redis is DOWN for ~2-5 minutes -- every login/sync fails meanwhile.
            Only when nothing is writing, checking in or waiting.

Options
  --method failover|offline
  --no-promote    (up, failover) add the replica but keep the current primary
  --force         skip the "attempts are writing" guard
  --yes           do not ask for confirmation (offline method)
  --dry-run       show the steps (read-only calls still run)
  -h, --help
EOF
}

cmd='' method='' promote=1 force=0 assume_yes=0
while (($#)); do
  case $1 in
    --method) method=${2:?}; shift 2 ;;
    --no-promote) promote=0; shift ;;
    --force) force=1; shift ;;
    --yes) assume_yes=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    up|down|status|sentinel-setup) [[ -z $cmd ]] || die "one command only"; cmd=$1; shift ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done
[[ -n $cmd ]] || { usage >&2; exit 2; }

load_ops_env
require_cmd doctl jq ssh perl
[[ -n $method ]] || method=$REDIS_RESIZE_METHOD
[[ -n $method ]] || { if [[ $REDIS_SENTINEL == yes ]]; then method=failover; else method=offline; fi; }
[[ $method == failover || $method == offline ]] || die "--method must be failover or offline"
[[ $cmd == status ]] || acquire_lock resize-redis

M=$REDIS_MASTER_NAME

# ---------------------------------------------------------------- inventory
row_of_tag() { droplets_by_tag "$1" | awk 'NR == 1'; }
base_row=$(row_of_tag "$REDIS_BASE_TAG")
[[ -n $base_row ]] || die "no droplet tagged $REDIS_BASE_TAG (create redis-exam-1 first, README step 4)"
IFS=$'\t' read -r base_id base_name base_pub base_priv _ _ base_size _ <<<"$base_row"
burst_row=$(row_of_tag "$REDIS_BURST_TAG" || true)
burst_id='' burst_name='' burst_pub='' burst_priv=''
if [[ -n $burst_row ]]; then IFS=$'\t' read -r burst_id burst_name burst_pub burst_priv _ <<<"$burst_row"; fi
eng_row=$(row_of_tag "$ENGINE_BASE_TAG" || true)
eng_pub='' eng_priv=''
if [[ -n $eng_row ]]; then IFS=$'\t' read -r _ _ eng_pub eng_priv _ <<<"$eng_row"; fi

pub_for_priv() {
  if [[ $1 == "$base_priv" ]]; then printf '%s' "$base_pub"
  elif [[ -n $burst_priv && $1 == "$burst_priv" ]]; then printf '%s' "$burst_pub"
  else return 1; fi
}
rcli() { local h=$1; shift; ssh_node "$h" sudo exam-redis-cli "$@"; }
scli() { local h=$1; shift; ssh_node "$h" redis-cli -p 26379 "$@"; }
info_field() { tr -d '\r' | awk -F: -v k="$1" '$1 == k { print $2; exit }'; }
sentinel_primary() { scli "$1" SENTINEL get-master-addr-by-name "$M" 2>/dev/null | tr -d '\r' | awk 'NR == 1'; }

current_primary() {
  if [[ $REDIS_SENTINEL == yes ]]; then
    local p
    p=$(sentinel_primary "$base_pub" || true)
    [[ -n $p ]] || { [[ -n $eng_pub ]] && p=$(sentinel_primary "$eng_pub" || true); }
    [[ -n $p ]] || die "no sentinel answered get-master-addr-by-name $M"
    printf '%s' "$p"
  else
    printf '%s' "$base_priv"
  fi
}

guard_writing() {
  [[ $force == 1 ]] && return 0
  [[ -n $eng_pub ]] || { warn "no engine base node found -- cannot check live load"; return 0; }
  local w
  w=$(writing_attempts "$eng_pub" || true)
  if [[ -z $w ]]; then warn "engine metric exam_attempts not available -- live-load guard skipped"; return 0; fi
  (( w == 0 )) || die "$w attempts are writing right now; retry after the exam or use --force"
}

wait_replica_in_sync() { # PUBLIC_IP_OF_REPLICA TIMEOUT
  local h=$1 end info link sync roff moff pprimary
  end=$(( $(now_epoch) + ${2:-900} ))
  while (( $(now_epoch) < end )); do
    info=$(rcli "$h" INFO replication 2>/dev/null || true)
    link=$(info_field master_link_status <<<"$info")
    sync=$(info_field master_sync_in_progress <<<"$info")
    roff=$(info_field slave_repl_offset <<<"$info")
    if [[ $link == up && $sync == 0 && -n $roff ]]; then
      pprimary=$(pub_for_priv "$(info_field master_host <<<"$info")" || true)
      if [[ -n $pprimary ]]; then
        moff=$(rcli "$pprimary" INFO replication | info_field master_repl_offset)
        if [[ -n $moff ]] && (( moff - roff < 1048576 )); then
          log "replica in sync (offset lag $(( moff - roff )) bytes)"
          return 0
        fi
      fi
    fi
    log "waiting for replica sync (link=${link:-?} syncing=${sync:-?})"
    sleep 10
  done
  return 1
}

wait_primary_is() { # PRIV_IP TIMEOUT
  local want=$1 end p
  end=$(( $(now_epoch) + ${2:-120} ))
  while (( $(now_epoch) < end )); do
    p=$(sentinel_primary "$base_pub" || true)
    [[ $p == "$want" ]] && return 0
    sleep 3
  done
  return 1
}

engine_still_healthy() {
  [[ -n $eng_pub ]] || return 0
  local h
  h=$(wait_node_health "$eng_pub" 60) || die "engine base node unhealthy after the change"
  log "engine base node healthy (redis: $(jq -r '.redis // "?"' <<<"$h"))"
}

show_status() {
  log "redis nodes: $base_name ($base_priv, $base_size)${burst_name:+, $burst_name ($burst_priv)}"
  local h p info
  for h in "$base_pub" ${burst_pub:+"$burst_pub"}; do
    info=$(rcli "$h" INFO replication 2>/dev/null | tr -d '\r' || true)
    printf '%s: role=%s connected_slaves=%s link=%s\n' "$h" \
      "$(info_field role <<<"$info")" "$(info_field connected_slaves <<<"$info")" \
      "$(info_field master_link_status <<<"$info")"
    rcli "$h" INFO memory 2>/dev/null | tr -d '\r' | awk -F: '$1=="used_memory_human"||$1=="maxmemory_human"{printf "  %s=%s\n",$1,$2}'
  done
  if [[ $REDIS_SENTINEL == yes ]]; then
    p=$(sentinel_primary "$base_pub" || true)
    printf 'sentinel primary: %s\n' "${p:-?}"
    scli "$base_pub" SENTINEL ckquorum "$M" 2>/dev/null || true
  fi
}

# ---------------------------------------------------------------- commands
cmd_sentinel_setup() {
  [[ -n $eng_pub ]] || die "no engine node tagged $ENGINE_BASE_TAG"
  log "enabling Sentinel on $base_name"
  run ssh_node "$base_pub" "sudo sed -i \"s/^ENABLE_SENTINEL=.*/ENABLE_SENTINEL='yes'/\" /etc/exam-redis/node.env && sudo systemctl enable redis-sentinel && sudo systemctl restart redis-sentinel"
  primary=$(rcli "$base_pub" ROLE | awk 'NR == 1' | tr -d '\r')
  [[ $primary == master ]] || die "$base_name is not the primary ($primary); fix replication first"

  log "installing Sentinel on the engine base node ($eng_priv)"
  conf="$STATE_DIR/rendered/sentinel-engine.conf"
  TPL_REDIS_PASSWORD=$(redis_password); export TPL_REDIS_PASSWORD
  export TPL_REDIS_MASTER_NAME="$M"
  render_template "$DEPLOY_DIR/redis/sentinel.conf.example" "$conf.tpl"
  sed -e "s/@PRIVATE_IP@/$eng_priv/g" -e "s/@PRIMARY_IP@/$base_priv/g" "$conf.tpl" > "$conf"
  rm -f "$conf.tpl"
  chmod 600 "$conf"
  run ssh_node "$eng_pub" 'sudo DEBIAN_FRONTEND=noninteractive apt-get install -y redis-sentinel redis-tools >/dev/null && (sudo systemctl disable --now redis-server 2>/dev/null || true)'
  if [[ $DRY_RUN == 1 ]]; then
    printf '[dry-run] upload %s to %s:/etc/redis/sentinel.conf\n' "$conf" "$eng_pub" >&2
  else
    ssh_node "$eng_pub" 'sudo install -d -m 0755 /etc/systemd/system/redis-sentinel.service.d &&
      printf "[Service]\nRestart=always\nRestartSec=2\n" | sudo tee /etc/systemd/system/redis-sentinel.service.d/90-exam.conf >/dev/null &&
      sudo tee /etc/redis/sentinel.conf.new >/dev/null && sudo install -o redis -g redis -m 0640 /etc/redis/sentinel.conf.new /etc/redis/sentinel.conf &&
      sudo rm -f /etc/redis/sentinel.conf.new && sudo systemctl daemon-reload && sudo systemctl enable redis-sentinel && sudo systemctl restart redis-sentinel' < "$conf"
  fi
  rm -f "$conf"
  if [[ $DRY_RUN == 0 ]]; then
    sleep 5
    scli "$base_pub" SENTINEL ckquorum "$M" || die "sentinel quorum check failed (firewall: allow 26379 between exam-redis and exam-engine-node)"
  fi
  cat <<EOF

Sentinel is running on $base_name ($base_priv) and the engine base node ($eng_priv).
Next:
  1. env/engine.env: add
       EXAM_ENGINE_REDIS_SENTINELS=$base_priv:26379,$eng_priv:26379
       EXAM_ENGINE_REDIS_MASTER=$M
     (keep EXAM_ENGINE_REDIS_URL -- the engine takes the password from it)
  2. env/ops.env: REDIS_SENTINEL=yes
  3. scripts/rolling-update.sh --env-only   (outside exam hours)
EOF
}

cmd_up_failover() {
  [[ $REDIS_SENTINEL == yes ]] || die "failover method needs Sentinel (resize-redis.sh sentinel-setup) -- or use --method offline"
  local primary userdata json
  primary=$(current_primary)
  require_set DO_VPC_UUID DO_SSH_KEYS
  if [[ -z $burst_id ]]; then
    userdata="$STATE_DIR/rendered/redis-replica.yaml"
    redis_template_env replica "$primary" yes
    render_template "$DEPLOY_DIR/cloud-init/redis-node.yaml" "$userdata"
    local create=(compute droplet create "$REDIS_BURST_NAME" --region "$DO_REGION" --size "$REDIS_LARGE_SIZE"
      --image ubuntu-24-04-x64 --vpc-uuid "$DO_VPC_UUID" --ssh-keys "$DO_SSH_KEYS"
      --tag-names "$REDIS_TAG,$REDIS_BURST_TAG" --user-data-file "$userdata" --enable-monitoring --wait -o json)
    if [[ -n $DO_PROJECT_ID ]]; then create+=(--project-id "$DO_PROJECT_ID"); fi
    log "creating $REDIS_BURST_NAME ($REDIS_LARGE_SIZE) as replica of $primary"
    if [[ $DRY_RUN == 1 ]]; then
      run dctl "${create[@]}"
      log "dry-run: would wait for sync, then promote it with SENTINEL FAILOVER (unless --no-promote)"
      return 0
    fi
    json=$(dctl "${create[@]}")
    rm -f "$userdata"
    burst_id=$(jq -r '.[0].id' <<<"$json")
    burst_name=$REDIS_BURST_NAME
    burst_pub=$(jq -r '[.[0].networks.v4[] | select(.type=="public") | .ip_address][0]' <<<"$json")
    burst_priv=$(jq -r '[.[0].networks.v4[] | select(.type=="private") | .ip_address][0]' <<<"$json")
    forget_host "$burst_pub"
    wait_ssh "$burst_pub" 300 || die "no SSH to $burst_pub"
    wait_cloud_init "$burst_pub" || die "cloud-init failed on $burst_pub"
  else
    log "$burst_name already exists ($burst_priv)"
  fi
  if [[ $primary != "$burst_priv" ]]; then
    wait_replica_in_sync "$burst_pub" 900 || die "replica did not catch up in 15 minutes"
    if [[ $promote == 1 ]]; then
      log "promoting $burst_name via SENTINEL FAILOVER"
      run scli "$base_pub" SENTINEL FAILOVER "$M"
      if [[ $DRY_RUN == 0 ]]; then
        wait_primary_is "$burst_priv" 120 || die "failover did not complete in 120 s"
        wait_replica_in_sync "$base_pub" 900 || warn "$base_name is not yet in sync as replica"
      fi
    fi
  fi
  if [[ $DRY_RUN == 0 ]]; then engine_still_healthy; fi
  show_status
}

cmd_down_failover() {
  if [[ -z $burst_id ]]; then
    log "no burst Redis node; nothing to remove"
    return 0
  fi
  guard_writing
  local primary
  primary=$(current_primary)
  if [[ $primary == "$burst_priv" ]]; then
    wait_replica_in_sync "$base_pub" 900 || die "$base_name is not in sync; refusing to fail back"
    log "failing back to $base_name"
    run scli "$base_pub" SENTINEL FAILOVER "$M"
    if [[ $DRY_RUN == 0 ]]; then wait_primary_is "$base_priv" 120 || die "failback did not complete in 120 s"; fi
  fi
  log "removing $burst_name ($burst_id)"
  run ssh_node "$burst_pub" 'sudo systemctl stop redis-sentinel redis-server' || warn "could not stop services on $burst_name"
  run dctl compute droplet delete "$burst_id" --force
  if [[ $DRY_RUN == 0 ]]; then
    log "waiting 70 s for the primary to drop the replica, then SENTINEL RESET one sentinel at a time"
    sleep 70
    scli "$base_pub" SENTINEL RESET "$M" >/dev/null
    if [[ -n $eng_pub ]]; then sleep 30; scli "$eng_pub" SENTINEL RESET "$M" >/dev/null || true; fi
    engine_still_healthy
  fi
  show_status
}

# Power-cycle resize of redis-exam-1 (Stage A, no Sentinel). Arg: target size slug.
cmd_offline_resize() {
  local target_size=$1 answer end
  [[ $REDIS_SENTINEL != yes ]] || die "Sentinel is enabled: use the failover method (power-cycling the primary would trigger a failover)"
  if [[ $base_size == "$target_size" ]]; then log "$base_name is already $target_size"; return 0; fi
  guard_writing
  warn "OFFLINE resize of $base_name $base_size -> $target_size: Redis is down for several minutes; logins and syncs fail meanwhile."
  if [[ $assume_yes == 0 && $DRY_RUN == 0 ]]; then
    printf 'Type "resize" to continue: ' >&2
    read -r answer
    [[ $answer == resize ]] || die "aborted"
  fi
  # A clean stop fsyncs the AOF before the power-off.
  run ssh_node "$base_pub" 'sudo systemctl stop redis-server'
  run dctl compute droplet-action power-off "$base_id" --wait
  run dctl compute droplet-action resize "$base_id" --size "$target_size" --wait
  run dctl compute droplet-action power-on "$base_id" --wait
  if [[ $DRY_RUN == 0 ]]; then
    wait_ssh "$base_pub" 300 || die "no SSH after power-on"
    end=$(( $(now_epoch) + 600 ))
    until [[ $(rcli "$base_pub" INFO persistence 2>/dev/null | info_field loading) == 0 ]]; do
      (( $(now_epoch) < end )) || die "Redis did not finish loading in 10 minutes"
      sleep 5
    done
    ssh_node "$base_pub" 'sudo exam-redis-tune'
    engine_still_healthy
  fi
  show_status
}

case $cmd in
  status) show_status ;;
  sentinel-setup) cmd_sentinel_setup ;;
  up)
    if [[ $method == failover ]]; then cmd_up_failover; else cmd_offline_resize "$REDIS_LARGE_SIZE"; fi ;;
  down)
    if [[ $method == failover ]]; then cmd_down_failover; else cmd_offline_resize "$REDIS_BASE_SIZE"; fi ;;
esac
