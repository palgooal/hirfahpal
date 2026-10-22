#!/usr/bin/env bash
# HIRFAH cPanel deployment - stage 2.2.3: PREFLIGHT CHECKS + VITE PACKAGE VALIDATION (prepare mode, no install).
#
# Read-only except for appending to storage/deploy/preflight.log (and creating that folder if missing) and for the
# validator's private work directory under deploy-packages/work (created and always removed by the validator).
# No value read from .env, or from the config cache built from it, is ever printed or logged.
# No copy to public_html or public/build, no Composer/npm/migrations/seeders, no cache commands, no .env or
# database change, no deletion, no chmod, and no network access (origin/main is the locally known ref; no git fetch).
#
# The Vite package for HEAD must be uploaded beforehand as deploy-packages/incoming/<package_id>/ (the two files
# made by deploy/make-vite-package.sh). Exactly one package for HEAD must exist, and it must pass
# deploy/vite-package-validate.php --mode=prepare with a verified source equal to HEAD. Nothing is installed.
#
# Any failed check prints "FAIL: <reason>", writes it to the log and exits non-zero, so the deployment fails.
# Paths can be overridden for local tests: HIRFAH_APP, HIRFAH_WEB, HIRFAH_PHP, HIRFAH_BRANCH, HIRFAH_PACKAGES,
# HIRFAH_GIT, HIRFAH_TAR.
# A manual failure drill: create storage/deploy/FORCE_PREFLIGHT_FAIL.log (ignored by /storage/deploy/*.log).

set -eu
set -o pipefail

APP="${HIRFAH_APP:-/home/hirfahpal/hirfah-app}"
WEB="${HIRFAH_WEB:-/home/hirfahpal/public_html}"
PHP_BIN="${HIRFAH_PHP:-php}"
BRANCH="${HIRFAH_BRANCH:-main}"
PACKAGES="${HIRFAH_PACKAGES:-/home/hirfahpal/deploy-packages}"
GIT_BIN="${HIRFAH_GIT:-/usr/local/cpanel/3rdparty/lib/path-bin/git}"
TAR_BIN="${HIRFAH_TAR:-/bin/tar}"
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

# 5. Installed Vite build in both paths (integrity): valid JSON, every referenced file present, the two manifests
#    identical, no dev-server marker. A vite.config.js input of HEAD that this build does not contain yet is a
#    warning, not a failure: the build is installed separately, and step 8 requires a validated package for HEAD
#    that contains every input.
[ -f "$APP/vite.config.js" ] || fail "missing application file: $APP/vite.config.js (needed to check the Vite inputs)"
check_manifest() {
    local dir="$1" label="$2" result status
    [ -f "$dir/manifest.json" ] || fail "$label manifest missing: $dir/manifest.json"
    result="$(cd "$APP" && "$PHP_BIN" -r '
        $dir = $argv[1];
        $manifest = json_decode((string) file_get_contents($dir."/manifest.json"), true);
        if (! is_array($manifest)) { echo "invalid JSON"; exit(1); }
        foreach ($manifest as $entry) {
            foreach (array_merge([$entry["file"] ?? ""], $entry["css"] ?? [], $entry["assets"] ?? []) as $file) {
                if ($file === "" || ! is_file($dir."/".$file)) { echo "file missing: ".$file; exit(1); }
            }
        }
        preg_match_all("#[\x27\"](resources/[^\x27\"]+\.(?:js|css))[\x27\"]#", (string) file_get_contents("vite.config.js"), $m);
        $missing = array_values(array_diff(array_unique($m[1]), array_keys($manifest)));
        echo count($manifest)." entries";
        if ($missing) { echo "; HEAD vite.config.js inputs not in this build yet: ".implode(", ", $missing); exit(3); }
    ' "$dir" 2>&1)" && status=0 || status=$?
    case "$status" in
        0) pass "$label manifest OK: $result" ;;
        3) warn "$label build files OK but not current: $result (step 8 requires a validated package for HEAD)" ;;
        *) fail "$label manifest invalid ($dir): $result" ;;
    esac
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

# 8. Vite package for HEAD: exactly one deploy-packages/incoming/hirfah-vite-<sha12>-* directory, validated in
#    prepare mode (nothing is installed). Required: PASS, package valid, source verified, commit = HEAD.
INCOMING="$PACKAGES/incoming"
WORK_ROOT="$PACKAGES/work"
[ -d "$INCOMING" ] || fail "Vite package directory not found: $INCOMING"
[ -d "$WORK_ROOT" ] || fail "Vite validator work directory not found: $WORK_ROOT"
[ -f "$APP/deploy/vite-package-validate.php" ] || fail "missing application file: $APP/deploy/vite-package-validate.php"
[ -x "$GIT_BIN" ] || fail "git not found or not executable: $GIT_BIN"
[ -x "$TAR_BIN" ] || fail "tar not found or not executable: $TAR_BIN"
shopt -s nullglob
candidates=("$INCOMING/hirfah-vite-${head_sha:0:12}-"*)
shopt -u nullglob
case "${#candidates[@]}" in
    0) fail "no Vite package for HEAD $head_sha in $INCOMING (expected hirfah-vite-${head_sha:0:12}-<stamp>/)" ;;
    1) package_dir="${candidates[0]}" ;;
    *) fail "${#candidates[@]} Vite packages for HEAD in $INCOMING, exactly one is required: $(for c in "${candidates[@]}"; do printf '%s ' "${c##*/}"; done)" ;;
esac
package_id="${package_dir##*/}"
validator_out="$("$PHP_BIN" "$APP/deploy/vite-package-validate.php" --mode=prepare --package-dir "$package_dir" \
    --app "$APP" --web "$WEB" --work-root "$WORK_ROOT" --tar "$TAR_BIN" --git "$GIT_BIN" 2>&1)" \
    && validator_status=0 || validator_status=$?
printf '%s\n' "$validator_out" | while IFS= read -r line; do log "validator: $line"; done
field() { printf '%s\n' "$validator_out" | sed -n "s/^$1=//p" | head -n 1; }
[ "$validator_status" -eq 0 ] && [ "$(field RESULT)" = PASS ] && [ "$(field MODE)" = prepare ] \
    && [ "$(field PACKAGE_ID)" = "$package_id" ] && [ "$(field PACKAGE_VALID)" = true ] \
    && [ "$(field SOURCE_VERIFIED)" = true ] && [ "$(field SOURCE_STATUS)" = verified ] \
    && [ "$(field SOURCE_COMMIT)" = "$head_sha" ] && [ "$(field INSTALL_APPROVED)" = false ] \
    || fail "Vite package $package_id not accepted (validator exit $validator_status): $(field REASON)"
pass "Vite package $package_id valid for HEAD (prepare mode; not installed)"

DONE=1
log "RESULT: PREFLIGHT PASSED (commit $head_sha, Vite package $package_id validated, not installed; warnings: $WARNINGS; nothing was changed)"
