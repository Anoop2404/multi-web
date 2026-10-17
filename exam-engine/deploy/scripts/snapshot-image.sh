#!/usr/bin/env bash
# Build the golden engine image: create a droplet from cloud-init (image mode), install the release,
# verify, scrub per-instance state, power off, snapshot, delete the droplet.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: snapshot-image.sh [options]

Creates droplet "exam-engine-golden-<ts>" (size ENGINE_IMAGE_BUILD_SIZE, 25 GB disk so the snapshot
fits every engine size) from cloud-init/engine-node.yaml in image mode (no secrets), installs the release
(ENGINE_RELEASE_TARBALL over SSH, or ENGINE_RELEASE_URL during cloud-init), checks the binary and unit,
cleans cloud-init/machine-id/logs, powers off and snapshots it as "<ENGINE_SNAPSHOT_PREFIX>-<version>-<ts>".
scale-out.sh uses the newest snapshot with that prefix unless ENGINE_SNAPSHOT_ID is set.

Re-run after every engine release that should be used for burst nodes (or rely on scale-out.sh pushing
the current release onto older images).

Options
  --keep-droplet     leave the (powered-off) golden droplet instead of deleting it
  --prune N          afterwards delete older snapshots with the prefix, keeping the newest N
  --dry-run          print what would be done (read-only doctl calls still run)
  -h, --help
EOF
}

keep=0 prune=''
while (($#)); do
  case $1 in
    --keep-droplet) keep=1; shift ;;
    --prune) prune=${2:?}; is_uint "$prune" || die "--prune needs a number"; shift 2 ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done

load_ops_env
require_cmd doctl jq ssh scp perl
require_set DO_VPC_UUID DO_SSH_KEYS
[[ -n $ENGINE_RELEASE_TARBALL || -n $ENGINE_RELEASE_URL ]] || die "set ENGINE_RELEASE_TARBALL or ENGINE_RELEASE_URL (scripts/build-release.sh)"
acquire_lock snapshot-image

ts=$(date -u +%Y%m%d-%H%M)
name="exam-engine-golden-$ts"
userdata="$STATE_DIR/rendered/engine-image.yaml"
engine_template_env image
render_template "$DEPLOY_DIR/cloud-init/engine-node.yaml" "$userdata"

create=(compute droplet create "$name" --region "$DO_REGION" --size "$ENGINE_IMAGE_BUILD_SIZE"
  --image ubuntu-24-04-x64 --vpc-uuid "$DO_VPC_UUID" --ssh-keys "$DO_SSH_KEYS"
  --tag-names exam-engine-golden --user-data-file "$userdata" --wait -o json)
[[ -n $DO_PROJECT_ID ]] && create+=(--project-id "$DO_PROJECT_ID")

log "creating $name"
if [[ $DRY_RUN == 1 ]]; then
  run dctl "${create[@]}"
  log "dry-run: would wait for SSH + cloud-init, install the release, scrub, power off, snapshot"
  exit 0
fi
json=$(dctl "${create[@]}")
id=$(jq -r '.[0].id' <<<"$json")
ip=$(jq -r '[.[0].networks.v4[] | select(.type == "public") | .ip_address][0]' <<<"$json")
[[ $id =~ ^[0-9]+$ && $ip != null ]] || die "unexpected doctl output: $json"
log "droplet $id at $ip"

cleanup_on_fail() {
  local rc=$?
  if (( rc != 0 )) && [[ $keep == 0 ]]; then
    warn "failed; the golden droplet $name ($id) is left for inspection -- delete it with: doctl compute droplet delete $id --force"
  fi
  release_lock
}
trap cleanup_on_fail EXIT

forget_host "$ip"
wait_ssh "$ip" 300 || die "no SSH to $ip"
wait_cloud_init "$ip" || die "cloud-init did not finish cleanly on $ip"
if [[ -n $ENGINE_RELEASE_TARBALL ]]; then push_release "$ip"; fi

log "verifying image contents"
version=$(ssh_node "$ip" 'cat /opt/exam-engine/current/VERSION' 2>/dev/null || true)
[[ -n $version ]] || die "no engine release installed on $ip"
ssh_node "$ip" 'test -x /opt/exam-engine/current/exam-engine && test -f /opt/exam-engine/current/client/index.html && systemctl is-enabled --quiet exam-engine && test ! -e /etc/exam-engine/engine.env' ||
  die "image check failed (binary/client/unit/env-file)"
log "engine $version installed"

log "scrubbing per-instance state"
ssh_node "$ip" 'sudo bash -s' <<'EOS'
set -euo pipefail
systemctl stop exam-engine 2>/dev/null || true
rm -f /etc/exam-engine/engine.env /etc/exam-engine/install.env
apt-get clean
# New droplets get a new instance-id: cloud-init then regenerates SSH host keys and machine-id.
cloud-init clean --logs --machine-id
journalctl --rotate >/dev/null 2>&1 || true
journalctl --vacuum-time=1s >/dev/null 2>&1 || true
rm -f /root/.bash_history /home/ops/.bash_history
sync
EOS

log "powering off"
dctl compute droplet-action shutdown "$id" --wait >/dev/null || dctl compute droplet-action power-off "$id" --wait >/dev/null
snap="$ENGINE_SNAPSHOT_PREFIX-$version-$ts"
snap=$(printf '%s' "$snap" | tr -c 'A-Za-z0-9._-' '-')
log "snapshotting as $snap (takes a few minutes)"
dctl compute droplet-action snapshot "$id" --snapshot-name "$snap" --wait >/dev/null
snap_id=$(dctl compute snapshot list --resource droplet -o json | jq -r --arg n "$snap" '[.[] | select(.name == $n)][0].id // empty')
[[ -n $snap_id ]] || die "snapshot $snap not found after creation"
log "snapshot $snap_id ($snap) ready"

if [[ $keep == 0 ]]; then
  dctl compute droplet delete "$id" --force
  log "deleted golden droplet $id"
fi

if [[ -n $prune ]]; then
  dctl compute snapshot list --resource droplet -o json |
    jq -r --arg p "$ENGINE_SNAPSHOT_PREFIX" '[.[] | select(.name | startswith($p))] | sort_by(.created_at) | reverse | .[] | "\(.id)\t\(.name)"' |
    tail -n +"$((prune + 1))" | while IFS=$'\t' read -r old_id old_name; do
      log "pruning old snapshot $old_name ($old_id)"
      dctl compute snapshot delete "$old_id" --force
    done
fi

trap - EXIT
release_lock
printf '%s\n' "$snap_id"
