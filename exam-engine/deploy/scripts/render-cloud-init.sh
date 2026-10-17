#!/usr/bin/env bash
# Render a cloud-init template with the values from env/ops.env, env/engine.env and env/redis.password.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

usage() {
  cat <<'EOF'
Usage: render-cloud-init.sh KIND [options]

Renders a cloud-init user-data file (mode 0600) for `doctl compute droplet create --user-data-file`.
The output contains secrets (engine env, Redis password): keep it under .state/ and delete it after use.

KIND
  engine            full engine node (the always-on exam-engine-1)        <- cloud-init/engine-node.yaml
  engine-image      golden image droplet (no secrets; snapshot-image.sh)   <- cloud-init/engine-node.yaml
  engine-snapshot   burst node booted from the golden snapshot             <- cloud-init/engine-node-from-snapshot.yaml
  redis-primary     redis-exam-1                                           <- cloud-init/redis-node.yaml
  redis-replica     burst replica (needs --primary-ip)                     <- cloud-init/redis-node.yaml

Options
  -o, --out FILE        output path (default .state/rendered/KIND.yaml)
  --primary-ip IP       current Redis primary VPC IP (redis-replica)
  --sentinel yes|no     enable Sentinel on the redis node (default: REDIS_SENTINEL from ops.env)
  -h, --help            this help

Example (README step 4):
  scripts/render-cloud-init.sh redis-primary
  doctl compute droplet create redis-exam-1 --region blr1 --size s-2vcpu-4gb --image ubuntu-24-04-x64 \
    --vpc-uuid "$DO_VPC_UUID" --ssh-keys "$DO_SSH_KEYS" --tag-names exam-redis,exam-redis-base,exam-monitor \
    --user-data-file .state/rendered/redis-primary.yaml --enable-monitoring --enable-backups --wait
EOF
}

kind='' out='' primary_ip='' sentinel=''
while (($#)); do
  case $1 in
    -o|--out) out=${2:?}; shift 2 ;;
    --primary-ip) primary_ip=${2:?}; shift 2 ;;
    --sentinel) sentinel=${2:?}; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    -*) usage >&2; die "unknown option $1" ;;
    *) [[ -z $kind ]] || die "only one KIND"; kind=$1; shift ;;
  esac
done
[[ -n $kind ]] || { usage >&2; exit 2; }

load_ops_env
require_cmd perl
[[ -n $sentinel ]] || sentinel=$REDIS_SENTINEL
[[ $sentinel == yes || $sentinel == no ]] || die "--sentinel must be yes or no"
[[ -n $out ]] || out="$STATE_DIR/rendered/$kind.yaml"

case $kind in
  engine)          engine_template_env node;     tpl=cloud-init/engine-node.yaml ;;
  engine-image)    engine_template_env image;    tpl=cloud-init/engine-node.yaml ;;
  engine-snapshot) engine_template_env snapshot; tpl=cloud-init/engine-node-from-snapshot.yaml ;;
  redis-primary)   redis_template_env primary '' "$sentinel"; tpl=cloud-init/redis-node.yaml ;;
  redis-replica)
    [[ $primary_ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "redis-replica needs --primary-ip <VPC IP>"
    redis_template_env replica "$primary_ip" "$sentinel"; tpl=cloud-init/redis-node.yaml ;;
  *) usage >&2; die "unknown KIND $kind" ;;
esac

render_template "$DEPLOY_DIR/$tpl" "$out"
chmod 600 "$out"
log "rendered $tpl -> $out"
printf '%s\n' "$out"
