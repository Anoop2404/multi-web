#!/usr/bin/env bash
# Build an exam-engine release: client (Vite) + engine binary (+ loadgen) for linux/amd64 and arm64,
# packed as tarballs with a SHA256SUMS file. Runs on your machine or CI; needs go and npm.
set -euo pipefail
. "$(dirname "$0")/lib.sh"

ENGINE_DIR=$(cd "$DEPLOY_DIR/.." && pwd)

usage() {
  cat <<'EOF'
Usage: build-release.sh [options]

Builds, from exam-engine/:
  client/   npm ci && npm run build            -> shipped as <release>/client (EXAM_ENGINE_STATIC_DIR)
  engine    go build ./cmd/engine  (CGO off, -trimpath, version stamped via -ldflags -X)
  loadgen   go build ./cmd/loadgen (if present; for load-generator droplets)
and writes deploy/dist/exam-engine-<version>-linux-<arch>.tar.gz + deploy/dist/SHA256SUMS.
Tarball layout: exam-engine-<version>/{exam-engine, loadgen, client/, VERSION, ARCH, CONTRACT.md}
(the layout /usr/local/sbin/exam-engine-install expects).

Options
  --version V        release version (default: UTCdate.time-<git sha>[-dirty])
  --arch LIST        comma-separated: amd64,arm64 (default both; DigitalOcean droplets are amd64)
  --skip-client      reuse an existing client/dist
  --test             run `go vet ./...` and `go test ./...` first
  --out DIR          output directory (default deploy/dist)
  --dry-run          print the build steps only
  -h, --help

Environment
  VERSION_LDFLAG     Go symbol that receives the version (default main.version)
  CLIENT_EMBED_DIR   if the engine go:embeds the client, the directory (relative to exam-engine/)
                     to copy client/dist into before `go build`, e.g. internal/web/dist

After building: set ENGINE_RELEASE_TARBALL in env/ops.env to the amd64 tarball (pushed over SSH by
scale-out.sh / rolling-update.sh / snapshot-image.sh), or upload it and set ENGINE_RELEASE_URL + _SHA256.
EOF
}

version='' archs='amd64,arm64' skip_client=0 do_test=0 out="$DEPLOY_DIR/dist"
while (($#)); do
  case $1 in
    --version) version=${2:?}; shift 2 ;;
    --arch) archs=${2:?}; shift 2 ;;
    --skip-client) skip_client=1; shift ;;
    --test) do_test=1; shift ;;
    --out) out=${2:?}; shift 2 ;;
    --dry-run) DRY_RUN=1; shift ;;
    -h|--help) usage; exit 0 ;;
    *) usage >&2; die "unknown argument $1" ;;
  esac
done

require_cmd go tar
[[ $skip_client == 1 ]] || require_cmd npm
[[ -d $ENGINE_DIR/cmd/engine ]] || die "no $ENGINE_DIR/cmd/engine yet -- is the engine checked out?"

if [[ -z $version ]]; then
  sha=$(git -C "$ENGINE_DIR" rev-parse --short=10 HEAD 2>/dev/null || echo nogit)
  dirty=''
  git -C "$ENGINE_DIR" diff --quiet HEAD -- . 2>/dev/null || dirty='-dirty'
  version="$(date -u +%Y%m%d.%H%M)-$sha$dirty"
fi
[[ $version =~ ^[A-Za-z0-9._-]+$ ]] || die "version may only contain letters, digits, . _ -"
ldvar=${VERSION_LDFLAG:-main.version}

sha256_of() { if command -v sha256sum >/dev/null 2>&1; then sha256sum "$@"; else shasum -a 256 "$@"; fi; }

log "exam-engine release $version (arch: $archs)"
run mkdir -p "$out"

if [[ $do_test == 1 ]]; then
  log "go vet + go test"
  run bash -c "cd $(printf '%q' "$ENGINE_DIR") && go vet ./... && go test ./..."
fi

client_dist="$ENGINE_DIR/client/dist"
if [[ $skip_client == 0 ]]; then
  [[ -f $ENGINE_DIR/client/package.json ]] || die "no client/package.json (use --skip-client to build without it)"
  log "building client"
  run bash -c "cd $(printf '%q' "$ENGINE_DIR/client") && npm ci --no-audit --no-fund && npm run build"
fi
if [[ $DRY_RUN == 0 ]]; then
  [[ -f $client_dist/index.html ]] || die "client build missing: $client_dist/index.html"
fi

if [[ -n ${CLIENT_EMBED_DIR:-} ]]; then
  embed="$ENGINE_DIR/$CLIENT_EMBED_DIR"
  log "copying client into $embed for go:embed"
  run rm -rf "$embed"
  run mkdir -p "$embed"
  run cp -R "$client_dist/." "$embed/"
fi

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT
gnu_tar=0
tar --version 2>/dev/null | grep -q 'GNU tar' && gnu_tar=1

IFS=',' read -r -a arch_list <<<"$archs"
built=()
for arch in "${arch_list[@]}"; do
  case $arch in amd64|arm64) ;; *) die "unsupported arch $arch" ;; esac
  name="exam-engine-$version"
  stage="$work/$arch/$name"
  run mkdir -p "$stage"
  log "go build linux/$arch"
  run env CGO_ENABLED=0 GOOS=linux GOARCH="$arch" \
    go -C "$ENGINE_DIR" build -trimpath -ldflags "-s -w -X $ldvar=$version" -o "$stage/exam-engine" ./cmd/engine
  if [[ -d $ENGINE_DIR/cmd/loadgen ]]; then
    run env CGO_ENABLED=0 GOOS=linux GOARCH="$arch" \
      go -C "$ENGINE_DIR" build -trimpath -ldflags "-s -w -X $ldvar=$version" -o "$stage/loadgen" ./cmd/loadgen
  fi
  run cp -R "$client_dist" "$stage/client"
  run cp "$ENGINE_DIR/CONTRACT.md" "$stage/CONTRACT.md"
  if [[ $DRY_RUN == 0 ]]; then
    printf '%s\n' "$version" > "$stage/VERSION"
    printf '%s\n' "$arch" > "$stage/ARCH"
  fi
  tarball="$out/$name-linux-$arch.tar.gz"
  if [[ $gnu_tar == 1 ]]; then
    run tar -C "$work/$arch" --owner=0 --group=0 --numeric-owner -czf "$tarball" "$name"
  else
    run env COPYFILE_DISABLE=1 tar -C "$work/$arch" --uid 0 --gid 0 -czf "$tarball" "$name"
  fi
  built+=("$tarball")
done

if [[ $DRY_RUN == 0 ]]; then
  ( cd "$out" && for t in "${built[@]}"; do sha256_of "$(basename "$t")"; done ) > "$out/SHA256SUMS.$version"
  cp "$out/SHA256SUMS.$version" "$out/SHA256SUMS"
  log "done:"
  cat "$out/SHA256SUMS" >&2
  amd=$(printf '%s\n' "${built[@]}" | grep 'linux-amd64' || true)
  if [[ -n $amd ]]; then
    log "next: set ENGINE_RELEASE_TARBALL=${amd#"$DEPLOY_DIR"/} in env/ops.env"
  fi
fi
