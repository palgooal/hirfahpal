#!/usr/bin/env bash
# HIRFAH cPanel deployment - stage 2.1: PREFLIGHT CHECKS ONLY.
#
# Read-only except for appending to storage/deploy/preflight.log (and creating that folder if missing).
# No value read from .env, or from the config cache built from it, is ever printed or logged.
# No copy to public_html, no Composer/npm/migrations/seeders, no cache commands, no .env or database change,
# no deletion, no chmod, and no network access (origin/main is the locally known ref; no git fetch).
#
# Any failed check prints "FAIL: <reason>", writes it to the log and exits non-zero, so the deployment fails.
# Paths can be overridden for local tests: HIRFAH_APP, HIRFAH_WEB, HIRFAH_PHP, HIRFAH_BRANCH.
# A manual failure drill: create storage/deploy/FORCE_PREFLIGHT_FAIL.log (ignored by /storage/deploy/*.log).

set -eu
set -o pipefail

APP="${HIRFAH_APP:-/home/hirfahpal/hirfah-app}"
WEB="${HIRFAH_WEB:-/home/hirfahpal/public_html}"
PHP_BIN="${HIRFAH_PHP:-php}"
BRANCH="${HIRFAH_BRANCH:-main}"
LOG_DIR="$APP/storage/deploy"
LOG="$LOG_DIR/preflight.log"
STAMP="$(date '+%Y-%m-%d %H:%M:%S %z')"
WARNINGS=0

if [ ! -d "$APP" ]; then
    echo "FAIL: application path not found: $APP"
    exit 1
fi
/bin/mkdir -p "$LOG_DIR"

log() { printf '%s %s\n' "$STAMP" "$*" | tee -a "$LOG"; }
pass() { log "PASS: $*"; }
warn() { WARNINGS=$((WARNINGS + 1)); log "WARN: $*"; }
fail() { DONE=1; log "FAIL: $*"; log "RESULT: PREFLIGHT FAILED (nothing was changed)"; exit 1; }

# Any unexpected error (set -e) is reported as a failure too.
trap 'status=$?; if [ "$status" -ne 0 ] && [ "${DONE:-0}" != 1 ]; then log "FAIL: unexpected error (exit $status) at line $LINENO"; fi' EXIT

log "----- preflight start (app=$APP, web=$WEB)"

# 0. Manual failure drill.
[ -e "$LOG_DIR/FORCE_PREFLIGHT_FAIL.log" ] && fail "forced failure drill ($LOG_DIR/FORCE_PREFLIGHT_FAIL.log exists)"

# 1. Paths and core Laravel files.
[ -d "$WEB" ] || fail "web root not found: $WEB"
for file in artisan composer.json composer.lock bootstrap/app.php vendor/autoload.php routes/web.php \
    config/site-availability.php public/index.php .env; do
    [ -f "$APP/$file" ] || fail "missing application file: $APP/$file"
done
for file in index.php .htaccess; do
    [ -f "$WEB/$file" ] || fail "missing web root file: $WEB/$file"
done
pass "application and web root files present"

# 2. PHP CLI version (read-only).
command -v "$PHP_BIN" >/dev/null 2>&1 || fail "PHP CLI not found: $PHP_BIN"
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || fail "PHP CLI is older than 8.2: $("$PHP_BIN" -r 'echo PHP_VERSION;')"
pass "PHP CLI $("$PHP_BIN" -r 'echo PHP_VERSION;')"

# 3. Git: repository, branch, clean tree, HEAD == locally known origin/BRANCH (no fetch).
git -C "$APP" rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "not a git work tree: $APP"
current_branch="$(git -C "$APP" symbolic-ref --quiet --short HEAD || true)"
[ "$current_branch" = "$BRANCH" ] || fail "branch is '${current_branch:-detached HEAD}', expected '$BRANCH'"
# --no-optional-locks: git status must not refresh (rewrite) .git/index.
dirty="$(git --no-optional-locks -C "$APP" status --porcelain --untracked-files=normal)"
[ -z "$dirty" ] || fail "working tree is not clean: $(printf '%s' "$dirty" | head -5 | tr '\n' ';')"
head_sha="$(git -C "$APP" rev-parse HEAD)"
remote_sha="$(git -C "$APP" rev-parse --verify --quiet "refs/remotes/origin/$BRANCH" || true)"
[ -n "$remote_sha" ] || fail "no local ref origin/$BRANCH (run Update from Remote first)"
[ "$head_sha" = "$remote_sha" ] || fail "HEAD $head_sha differs from the locally known origin/$BRANCH $remote_sha"
pass "git: branch $BRANCH, clean, HEAD = origin/$BRANCH = $head_sha"

# 4. Required .env values. Only the three keys below are read, and no value taken from .env (or from the config
#    cache built from it) is ever printed or logged: a mismatch names the key and the expected value only.
env_value() {
    # Last assignment of $1 in .env, without surrounding spaces, quotes, a trailing comment or CR.
    grep -E "^[[:space:]]*$1[[:space:]]*=" "$APP/.env" | tail -n 1 \
        | sed -E 's/^[^=]*=[[:space:]]*//; s/[[:space:]]*(#.*)?$//; s/^"(.*)"$/\1/; s/^'"'"'(.*)'"'"'$/\1/' | tr -d '\r' || true
}
for pair in HIRFAH_SITE_AVAILABILITY=coming_soon APP_ENV=production APP_DEBUG=false; do
    key="${pair%%=*}"
    expected="${pair#*=}"
    [ "$(env_value "$key")" = "$expected" ] || fail ".env $key is missing or not '$expected' (value not shown)"
done
pass ".env: HIRFAH_SITE_AVAILABILITY=coming_soon, APP_ENV=production, APP_DEBUG=false"
if [ -f "$APP/bootstrap/cache/config.php" ]; then
    cached_mode="$(cd "$APP" && "$PHP_BIN" -r '$c = require "bootstrap/cache/config.php"; echo $c["site-availability"]["override"] ?? "";' 2>/dev/null || true)"
    [ "$cached_mode" = "coming_soon" ] || fail "config cache site-availability.override is missing or not 'coming_soon' (stale config cache; value not shown)"
    pass "config cache: site-availability.override = coming_soon"
else
    pass "no config cache: .env value applies"
fi
[ -f "$APP/bootstrap/cache/routes-v7.php" ] && warn "route cache present (bootstrap/cache/routes-v7.php): new routes stay invisible until it is refreshed"

# 5. Vite manifests in both paths: valid JSON, every vite.config.js input present, every referenced file present,
#    and the two manifests identical.
[ -f "$APP/vite.config.js" ] || fail "missing application file: $APP/vite.config.js (needed to check the Vite inputs)"
check_manifest() {
    local dir="$1" label="$2" result
    [ -f "$dir/manifest.json" ] || fail "$label manifest missing: $dir/manifest.json"
    result="$(cd "$APP" && "$PHP_BIN" -r '
        $dir = $argv[1];
        $manifest = json_decode((string) file_get_contents($dir."/manifest.json"), true);
        if (! is_array($manifest)) { echo "invalid JSON"; exit(1); }
        preg_match_all("#[\x27\"](resources/[^\x27\"]+\.(?:js|css))[\x27\"]#", (string) file_get_contents("vite.config.js"), $m);
        $missing = array_values(array_diff(array_unique($m[1]), array_keys($manifest)));
        if ($missing) { echo "entries missing: ".implode(", ", $missing); exit(1); }
        foreach ($manifest as $entry) {
            foreach (array_merge([$entry["file"] ?? ""], $entry["css"] ?? [], $entry["assets"] ?? []) as $file) {
                if ($file === "" || ! is_file($dir."/".$file)) { echo "file missing: ".$file; exit(1); }
            }
        }
        echo count($manifest)." entries";
    ' "$dir" 2>&1)" || fail "$label manifest invalid ($dir): $result"
    pass "$label manifest OK: $result"
}
check_manifest "$APP/public/build" "app"
check_manifest "$WEB/build" "web"
app_manifest_sum="$(sha256sum < "$APP/public/build/manifest.json" | cut -d' ' -f1)"
web_manifest_sum="$(sha256sum < "$WEB/build/manifest.json" | cut -d' ' -f1)"
[ "$app_manifest_sum" = "$web_manifest_sum" ] \
    || fail "app and web manifests differ (app sha256 $app_manifest_sum, web sha256 $web_manifest_sum)"
pass "app and web manifests identical (sha256 $app_manifest_sum)"
[ -e "$APP/public/hot" ] && fail "Vite dev-server marker present: $APP/public/hot"
[ -e "$WEB/hot" ] && fail "Vite dev-server marker present: $WEB/hot"

# 6. Storage link: public_html/storage -> hirfah-app/storage/app/public.
[ -L "$WEB/storage" ] || fail "$WEB/storage is not a symbolic link"
link_target="$(readlink -f "$WEB/storage" || true)"
expected_target="$(readlink -f "$APP/storage/app/public" || true)"
[ -n "$link_target" ] && [ "$link_target" = "$expected_target" ] && [ -d "$link_target" ] \
    || fail "$WEB/storage points to '${link_target:-<broken>}', expected '$expected_target'"
pass "storage link OK"

# 7. Writable directories (tested with -w only; nothing is created).
for dir in storage storage/app/public storage/framework/cache storage/framework/sessions storage/framework/views \
    storage/logs storage/deploy bootstrap/cache; do
    [ -d "$APP/$dir" ] && [ -w "$APP/$dir" ] || fail "not a writable directory: $APP/$dir"
done
pass "writable directories OK"

DONE=1
log "RESULT: PREFLIGHT PASSED (commit $head_sha, warnings: $WARNINGS; nothing was changed)"
