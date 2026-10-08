#!/usr/bin/env bash
# Runs on the CyberPanel server. Called by .github/workflows/deploy.yml.
#
#   DEPLOY_PATH=/home/app.example.com PHP_BIN=/usr/local/lsws/lsphp83/bin/php \
#     bash cyberpanel-deploy.sh /tmp/release.tar.gz <git-sha>
#
# Layout under DEPLOY_PATH:
#   releases/<timestamp>-<sha>/   one directory per deploy
#   shared/.env                   production environment (created by hand once)
#   shared/storage/               Laravel storage, kept across releases
#   current -> releases/...       the live release
#   public_html -> current/public the subdomain's document root

set -euo pipefail

TARBALL="${1:?release tarball path required}"
SHA="${2:?git sha required}"
: "${DEPLOY_PATH:?DEPLOY_PATH is not set}"
PHP="${PHP_BIN:-/usr/local/lsws/lsphp83/bin/php}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"

RELEASES="$DEPLOY_PATH/releases"
SHARED="$DEPLOY_PATH/shared"
RELEASE="$RELEASES/$(date +%Y%m%d%H%M%S)-${SHA:0:7}"

log() { printf '\n==> %s\n' "$*"; }

if [[ ! -f "$SHARED/.env" ]]; then
  echo "Missing $SHARED/.env. Create it before the first deploy." >&2
  exit 1
fi

log "Preparing shared storage"
mkdir -p "$RELEASES" \
  "$SHARED/storage/app/public" \
  "$SHARED/storage/app/private" \
  "$SHARED/storage/framework/cache/data" \
  "$SHARED/storage/framework/sessions" \
  "$SHARED/storage/framework/views" \
  "$SHARED/storage/logs"

log "Unpacking $RELEASE"
mkdir -p "$RELEASE"
tar -xzf "$TARBALL" -C "$RELEASE"
rm -f "$TARBALL"

rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"
ln -s "$SHARED/.env" "$RELEASE/.env"
mkdir -p "$RELEASE/bootstrap/cache"

cd "$RELEASE"

log "Linking public storage"
"$PHP" artisan storage:link --force

log "Running migrations"
"$PHP" artisan migrate --force

log "Caching config, routes, views, and events"
"$PHP" artisan optimize

log "Switching current release"
ln -sfn "$RELEASE" "$DEPLOY_PATH/current.next"
mv -Tf "$DEPLOY_PATH/current.next" "$DEPLOY_PATH/current"

log "Restarting workers"
"$PHP" "$DEPLOY_PATH/current/artisan" queue:restart
# lsphp keeps OPcache and the realpath cache for the old release until it is restarted.
# OpenLiteSpeed starts fresh lsphp processes on the next request.
pkill -u "$(id -un)" lsphp || true

log "Pruning old releases (keeping $KEEP_RELEASES)"
ls -1dt "$RELEASES"/*/ | tail -n +"$((KEEP_RELEASES + 1))" | xargs -r rm -rf

rm -f "$0"
log "Deployed ${SHA:0:7}"
