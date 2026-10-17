#!/usr/bin/env bash
# Ensure N engine nodes (total, including the always-on node) are running, healthy and in the LB.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: scale-out.sh [options] N

Makes sure at least N engine nodes (TOTAL, including the always-on exam-engine-1) are in service.
Missing nodes are created from the newest golden snapshot in DO_REGION (BLR1) with the tags
  exam-engine        <- the load balancer targets this tag, so new nodes join automatically
  exam-engine-node   <- cloud firewall + Prometheus discovery
  exam-engine-burst  <- scale-in.sh only ever removes these
and boot with env/engine.env (cloud-init/engine-node-from-snapshot.yaml). The script then waits until
every node answers /health locally, runs the same version as the base node, is attached to the LB,
and the public URL (Cloudflare -> LB) is healthy.

Idempotent: re-running with the same N creates nothing and only re-verifies. It never removes nodes
(see scale-in.sh).

Options
  --size SLUG             burst droplet size (default ENGINE_BURST_SIZE, e.g. c-4)
  --snapshot ID           image to use (default ENGINE_SNAPSHOT_ID or the newest prefixed snapshot)
  --no-wait               create droplets and return without health/LB verification
  --allow-version-mismatch  do not fail when node versions differ
  --dry-run               show what would be created (read-only doctl calls still run)
  -h, --help

Examples
  scale-out.sh 4            # 1 always-on + 3 burst nodes (60k booked, plan section 9 starting point)
  scale-out.sh --dry-run 4
EOF
}

target='' size='' snapshot='' do_wait=1 allow_mismatch=0
while (($#)); do
  case $1 in
    --size) size=${2:?}; shift 2 ;;
    --snapshot) snapshot=${2:?}; shift 2 ;;
    --no-wait) do_wait=0; shift ;;
    --allow-version-mismatch) allow_mismatch=1; shift ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    -*) usage >&2; die "unknown option $1" ;;
    *) [[ -z $target ]] || die "only one N"; target=$1; shift ;;
  esac
done
is_uint "$target" || { usage >&2; exit 2; }

load_ops_env
require_cmd doctl jq ssh scp perl curl
(( target >= 1 )) || die "N must be >= 1"
(( target <= MAX_ENGINE_NODES )) || die "N=$target exceeds MAX_ENGINE_NODES=$MAX_ENGINE_NODES (raise it in ops.env if intended)"
[[ $LB_KIND != nginx ]] || (( target == 1 )) || die "LB_KIND=nginx is single-droplet Stage A-lite; create the DO LB first (README Stage B)"
require_set DO_VPC_UUID DO_SSH_KEYS
[[ -n $size ]] || size=$ENGINE_BURST_SIZE
acquire_lock scale-out

# ---------------------------------------------------------------- current fleet
fleet=$(droplets_by_tag "$ENGINE_NODE_TAG")
in_service=() names=() base_ip=''
while IFS=$'\t' read -r id name pub priv status tags _size _created; do
  [[ -n ${id:-} ]] || continue
  names+=("$name")
  if has_tag "$tags" "$ENGINE_DRAIN_TAG"; then
    warn "$name ($id) is draining -- not counted (finish it with scale-in.sh)"
    continue
  fi
  in_service+=("$id"$'\t'"$name"$'\t'"$pub"$'\t'"$priv")
  if has_tag "$tags" "$ENGINE_BASE_TAG"; then base_ip=$pub; fi
done <<<"$fleet"
current=${#in_service[@]}
log "engine nodes in service: $current, wanted: $target"
[[ -n $base_ip ]] || warn "no node tagged $ENGINE_BASE_TAG -- version parity is checked against the first node"

base_version=''
if [[ -n $base_ip && $base_ip != - ]]; then
  base_version=$(node_health "$base_ip" | jq -r '.version // empty' 2>/dev/null || true)
  log "base node version: ${base_version:-unknown}"
fi

release_version=''
if [[ -n $ENGINE_RELEASE_TARBALL ]]; then
  tb=$(deploy_path "$ENGINE_RELEASE_TARBALL")
  [[ -r $tb ]] || die "ENGINE_RELEASE_TARBALL not found: $tb"
  release_version=$(tar -tzf "$tb" | awk -F/ 'NR == 1 { sub(/^exam-engine-/, "", $1); print $1 }')
fi

# ---------------------------------------------------------------- create the missing nodes
new_ids=()
missing=$(( target - current ))
if (( missing > 0 )); then
  [[ -n $snapshot ]] || snapshot=$ENGINE_SNAPSHOT_ID
  [[ -n $snapshot ]] || snapshot=$(latest_snapshot_id)
  [[ -n $snapshot ]] || die "no snapshot with prefix $ENGINE_SNAPSHOT_PREFIX in $DO_REGION (run snapshot-image.sh)"

  userdata="$STATE_DIR/rendered/engine-snapshot.yaml"
  engine_template_env snapshot
  render_template "$DEPLOY_DIR/cloud-init/engine-node-from-snapshot.yaml" "$userdata"

  new_names=() i=1
  while (( ${#new_names[@]} < missing )); do
    candidate=$(printf '%s-%02d' "$ENGINE_BURST_PREFIX" "$i")
    taken=0
    for n in ${names[@]+"${names[@]}"} ${new_names[@]+"${new_names[@]}"}; do
      [[ $n == "$candidate" ]] && taken=1
    done
    (( taken )) || new_names+=("$candidate")
    i=$(( i + 1 ))
  done
  log "creating $missing node(s) from snapshot $snapshot, size $size: ${new_names[*]}"

  create=(compute droplet create "${new_names[@]}" --region "$DO_REGION" --size "$size" --image "$snapshot"
    --vpc-uuid "$DO_VPC_UUID" --ssh-keys "$DO_SSH_KEYS"
    --tag-names "$ENGINE_LB_TAG,$ENGINE_NODE_TAG,$ENGINE_BURST_TAG"
    --user-data-file "$userdata" --enable-monitoring --wait -o json)
  [[ -n $DO_PROJECT_ID ]] && create+=(--project-id "$DO_PROJECT_ID")

  if [[ $DRY_RUN == 1 ]]; then
    run dctl "${create[@]}"
    log "dry-run: would then wait for SSH, cloud-init, /health, version parity and LB admission"
    exit 0
  fi
  created=$(dctl "${create[@]}")
  while IFS=$'\t' read -r id name pub priv; do
    [[ -n ${id:-} ]] || continue
    in_service+=("$id"$'\t'"$name"$'\t'"$pub"$'\t'"$priv")
    new_ids+=("$id")
    forget_host "$pub"
    log "created $name ($id) public $pub private $priv"
  done < <(jq -r '.[] | [(.id|tostring), .name,
      ([.networks.v4[]? | select(.type=="public") | .ip_address][0] // "-"),
      ([.networks.v4[]? | select(.type=="private") | .ip_address][0] // "-")] | @tsv' <<<"$created")
else
  log "nothing to create"
fi

if [[ $DRY_RUN == 1 || $do_wait == 0 ]]; then exit 0; fi

# ---------------------------------------------------------------- verify every in-service node
is_new() { local x; for x in ${new_ids[@]+"${new_ids[@]}"}; do [[ $x == "$1" ]] && return 0; done; return 1; }
failed=0 versions=()
for row in ${in_service[@]+"${in_service[@]}"}; do
  IFS=$'\t' read -r id name pub priv <<<"$row"
  if is_new "$id"; then
    wait_ssh "$pub" 300 || { warn "$name: no SSH"; failed=1; continue; }
    wait_cloud_init "$pub" || warn "$name: cloud-init not clean, continuing with health checks"
    if [[ -n $release_version ]]; then
      have=$(ssh_node "$pub" 'cat /opt/exam-engine/current/VERSION 2>/dev/null' || true)
      if [[ $have != "$release_version" ]]; then
        log "$name: image has ${have:-nothing}, installing release $release_version"
        push_release "$pub"
      fi
    fi
  fi
  if health=$(wait_node_health "$pub" 240); then
    v=$(jq -r '.version // "unknown"' <<<"$health")
    versions+=("$name=$v")
    log "$name healthy (version $v, redis $(jq -r '.redis // "?"' <<<"$health"))"
    if [[ -n $base_version && $v != "$base_version" ]]; then
      if [[ $allow_mismatch == 1 ]]; then warn "$name runs $v, base runs $base_version"
      else warn "$name runs $v but the base node runs $base_version"; failed=1; fi
    fi
  else
    warn "$name ($pub) is not healthy: ssh ops@$pub 'journalctl -u exam-engine -n 50'"
    failed=1
  fi
done
(( failed == 0 )) || die "some nodes are not ready (see above); re-run this script after fixing -- it is idempotent"

# ---------------------------------------------------------------- load balancer admission
case $LB_KIND in
  do)
    require_set LB_ID
    lb_tagged=$(droplets_by_tag "$ENGINE_LB_TAG" | cut -f1 | sort)
    end=$(( $(now_epoch) + 180 ))
    while :; do
      attached=$(lb_droplet_ids | sort)
      missing_ids=$(comm -23 <(printf '%s\n' "$lb_tagged") <(printf '%s\n' "$attached") | sed '/^$/d')
      [[ -z $missing_ids ]] && break
      (( $(now_epoch) < end )) || die "LB $LB_ID still lacks droplets: $(echo "$missing_ids" | tr '\n' ' ')"
      sleep 10
    done
    log "all $(printf '%s\n' "$lb_tagged" | sed '/^$/d' | wc -l | tr -d ' ') tagged nodes attached to LB $LB_ID; waiting ${LB_ADMIT_SECONDS}s for its health checks"
    sleep "$LB_ADMIT_SECONDS"
    ;;
  haproxy)
    "$SCRIPTS_DIR/haproxy-sync-backends.sh"
    sleep 10
    ;;
esac

if [[ -n $EXAM_PUBLIC_URL ]]; then
  ok=0
  for _ in 1 2 3 4 5 6 7 8 9 10; do
    body=$(curl -fsS --max-time 5 "$EXAM_PUBLIC_URL/health" 2>/dev/null || true)
    jq -e '.ok == true' >/dev/null 2>&1 <<<"$body" && ok=$(( ok + 1 ))
    sleep 1
  done
  (( ok == 10 )) || die "public $EXAM_PUBLIC_URL/health ok only $ok/10 times"
  log "public health 10/10 ok"
fi

log "scale-out complete: ${#in_service[@]} node(s): ${versions[*]+${versions[*]}}"
