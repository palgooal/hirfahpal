#!/usr/bin/env bash
# HIRFAH - local Vite package builder (deployment phase 2.2.1). LOCAL ONLY: never run on the server.
#
# Builds public/build for ONE published commit inside a temporary, clean git worktree and publishes ONE directory
#   <out>/<package_id>/<package_id>.tar.gz          (PACKAGE.json + build/)
#   <out>/<package_id>/<package_id>.tar.gz.sha256   (sha256sum -c format)
# where package_id = hirfah-vite-<sha12>-<UTC stamp>.
#
# Atomic publication without replacement: both files are written and checked in <out>/.partial-<package_id>/
# (reserved with an exclusive mkdir). The final name <out>/<package_id> is then reserved with a second exclusive
# mkdir, which fails if that name exists at all (empty or not), and the .partial- directory is renamed onto this
# reservation in one step on the same file system. rename(2) can only replace an EMPTY directory - here only our own
# reservation; if anything was written into it meanwhile, the rename fails and nothing is replaced. (No
# RENAME_NOREPLACE is available in coreutils 8.32 / Git Bash, and `mv -n` is not relied upon.) On failure only this
# run's .partial- directory and its own still-empty reservation are removed; published packages and other runs'
# directories are never touched. Only directories holding exactly the two package files are packages; an empty
# <package_id> directory may exist for an instant during publication and is not a package.
#
# Never touches the project working tree: it refuses a dirty tree, builds only in the temporary worktree
# (npm ci + npm run build there), writes the package outside the repository, and always removes the temporary
# worktree. The only trace in the repository is git's own worktree metadata, added and removed by git itself.
# No network except what `npm ci` needs locally. No server access.
#
# Usage: make-vite-package.sh [--commit <ref>] [--branch <ref>] [--repo <path>] [--out <dir>]
#   --commit  commit to package (default HEAD)
#   --branch  approved branch ref that must contain the commit, as last fetched (default origin/main; no fetch)
#   --repo    repository (default: the repository containing this script)
#   --out     output directory, outside the repository (default: <repo parent>/hirfah-deploy-packages)

set -euo pipefail

fail() { printf 'FAIL: %s\n' "$*" >&2; exit 1; }
info() { printf '%s\n' "$*"; }

COMMIT_REF="HEAD"
BRANCH_REF="${HIRFAH_PACKAGE_BRANCH:-origin/main}"
REPO="${HIRFAH_REPO:-}"
OUT="${HIRFAH_PACKAGE_OUT:-}"
while [ $# -gt 0 ]; do
    case "$1" in
        --commit) COMMIT_REF="${2:?--commit needs a value}"; shift 2 ;;
        --branch) BRANCH_REF="${2:?--branch needs a value}"; shift 2 ;;
        --repo) REPO="${2:?--repo needs a value}"; shift 2 ;;
        --out) OUT="${2:?--out needs a value}"; shift 2 ;;
        -h|--help) sed -n '2,/^$/p' "$0"; exit 0 ;;
        *) fail "unknown argument: $1" ;;
    esac
done

for tool in git node npm tar gzip sha256sum mktemp; do
    command -v "$tool" >/dev/null 2>&1 || fail "required tool not found: $tool"
done

# --- Repository and output location -------------------------------------------------------------------------------
if [ -z "$REPO" ]; then
    REPO="$(git -C "$(dirname "$0")" rev-parse --show-toplevel 2>/dev/null)" || fail "cannot find the repository; use --repo"
fi
[ -d "$REPO" ] || fail "repository not found: $REPO"
REPO="$(cd "$REPO" && pwd -P)"
[ "$(cd "$(git -C "$REPO" rev-parse --show-toplevel)" && pwd -P)" = "$REPO" ] || fail "--repo is not the top of a git repository: $REPO"
[ -n "$OUT" ] || OUT="$(dirname "$REPO")/hirfah-deploy-packages"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd -P)"
case "$OUT/" in "$REPO/"*) fail "output directory must be outside the repository: $OUT" ;; esac

# --- Clean source, published commit ------------------------------------------------------------------------------
dirty="$(git --no-optional-locks -C "$REPO" status --porcelain --untracked-files=all)"
[ -z "$dirty" ] || fail "working tree is not clean (commit, stash or remove changes first): $(printf '%s' "$dirty" | head -5 | tr '\n' ';')"
SHA="$(git -C "$REPO" rev-parse --verify --quiet "${COMMIT_REF}^{commit}")" || fail "unknown commit: $COMMIT_REF"
git -C "$REPO" rev-parse --verify --quiet "${BRANCH_REF}^{commit}" >/dev/null || fail "branch ref not found: $BRANCH_REF"
git -C "$REPO" merge-base --is-ancestor "$SHA" "$BRANCH_REF" \
    || fail "commit $SHA is not contained in $BRANCH_REF (as last fetched): push it first, then fetch"
TREE="$(git -C "$REPO" rev-parse "${SHA}^{tree}")"
COMMIT_TIME="$(git -C "$REPO" show -s --format=%cI "$SHA")"
COMMIT_EPOCH="$(git -C "$REPO" show -s --format=%ct "$SHA")"
ORIGIN_URL="$(git -C "$REPO" remote get-url origin 2>/dev/null | sed -E 's#(://)[^/@]*@#\1#' || true)"   # credentials stripped
STAMP="${HIRFAH_PACKAGE_STAMP:-$(date -u +%Y%m%dT%H%M%SZ)}"   # override only for tests (fixed package id)
[[ "$STAMP" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] || fail "invalid package stamp: $STAMP"
PKG_ID="hirfah-vite-${SHA:0:12}-$STAMP"
ARCHIVE_NAME="$PKG_ID.tar.gz"
PKG_DIR="$OUT/$PKG_ID"
PARTIAL="$OUT/.partial-$PKG_ID"
for existing in "$PKG_DIR" "$PARTIAL" "$OUT/$ARCHIVE_NAME" "$OUT/$ARCHIVE_NAME.sha256"; do
    [ ! -e "$existing" ] && [ ! -L "$existing" ] || fail "package name already in use (nothing replaced): $existing"
done
for stale in "$OUT"/.partial-*; do
    if [ -e "$stale" ]; then
        printf 'WARN: unfinished package directory from another run left untouched: %s\n' "$stale" >&2
    fi
done

# Test-only fault injection (HIRFAH_PACKAGE_TEST_FAULT=<point>); does nothing unless that variable is set.
fault_point() { [ "${HIRFAH_PACKAGE_TEST_FAULT:-}" != "$1" ] || fail "test fault injected at: $1"; }

# --- Temporary workspace (always cleaned) ---------------------------------------------------------------------------
TMP="$(mktemp -d "${TMPDIR:-/tmp}/hirfah-vite-build.XXXXXX")"
WT="$TMP/worktree"
STAGE="$TMP/package"
VERIFY="$TMP/verify"
WT_ADDED=0
PARTIAL_OWNED=0   # 1 once this run has created $PARTIAL (exclusive mkdir), 0 again once it is published
FINAL_RESERVED=0  # 1 while this run holds an (empty) reservation of $PKG_DIR, 0 once the rename has filled it
cleanup() {
    local status=$?
    if [ "$PARTIAL_OWNED" = 1 ]; then
        case "$PARTIAL" in "$OUT"/.partial-hirfah-vite-*) rm -rf -- "$PARTIAL" ;; esac   # only this run's directory
    fi
    if [ "$FINAL_RESERVED" = 1 ]; then
        # rmdir removes only an EMPTY directory: our untouched reservation. Anything written into it is left alone.
        rmdir -- "$PKG_DIR" 2>/dev/null \
            || printf 'WARN: %s is no longer empty and was left untouched (not created by this package)\n' "$PKG_DIR" >&2
    fi
    if [ "$WT_ADDED" = 1 ]; then
        git -C "$REPO" worktree remove --force "$WT" >/dev/null 2>&1 || true
        if git -C "$REPO" worktree list --porcelain | grep -qF "worktree $WT"; then
            printf 'WARN: temporary worktree still registered: %s (run: git worktree remove --force "%s")\n' "$WT" "$WT" >&2
        fi
    fi
    case "$TMP" in */hirfah-vite-build.*) rm -rf -- "$TMP" ;; esac   # only our own mktemp directory
    [ "$status" -eq 0 ] || printf 'Build aborted: temporary worktree and files removed; the project was not changed.\n' >&2
    exit "$status"
}
trap cleanup EXIT
trap 'exit 130' INT TERM

info "Packaging $SHA (tree $TREE) from $BRANCH_REF"
git -C "$REPO" worktree add --detach --quiet "$WT" "$SHA" || fail "cannot create the temporary worktree"
WT_ADDED=1
[ "$(git -C "$WT" rev-parse HEAD)" = "$SHA" ] || fail "temporary worktree is not at $SHA"
[ -z "$(git --no-optional-locks -C "$WT" status --porcelain --untracked-files=all)" ] || fail "temporary worktree is not clean"
[ -f "$WT/package-lock.json" ] || fail "package-lock.json missing at $SHA (npm ci needs it)"
[ -f "$WT/vite.config.js" ] || fail "vite.config.js missing at $SHA"

# --- Build inside the worktree only ----------------------------------------------------------------------------------
( cd "$WT" && npm ci --no-audit --no-fund --loglevel=error ) || fail "npm ci failed in the temporary worktree"
( cd "$WT" && npm run build --silent ) || fail "npm run build failed in the temporary worktree"
NODE_VERSION="$(node -v)"
NPM_VERSION="$(npm -v)"
VITE_VERSION="$(cd "$WT" && node -p "require('vite/package.json').version" 2>/dev/null)" || fail "cannot read the installed Vite version"

# --- Validate, hash and describe the build (helper written to the temp dir, not to the project) ------------------------
cat > "$TMP/package-helper.cjs" <<'NODE'
const fs = require('fs'), path = require('path'), crypto = require('crypto');
const fail = (m) => { console.error('FAIL: ' + m); process.exit(1); };
const SAFE = /^[A-Za-z0-9._@-]+(\/[A-Za-z0-9._@-]+)*$/;
const EXT = /\.(js|mjs|css|json|map|woff2?|ttf|otf|eot|svg|png|jpe?g|gif|webp|avif|ico)$/i;
const MAX_FILE = 10 * 1024 * 1024, MAX_TOTAL = 100 * 1024 * 1024, MAX_FILES = 5000;
const sha = (f) => crypto.createHash('sha256').update(fs.readFileSync(f)).digest('hex');

function walk(root) {             // regular files only; any symlink or special file is an error
    const files = [];
    (function visit(dir, rel) {
        for (const name of fs.readdirSync(dir).sort()) {
            const abs = path.join(dir, name), r = rel ? rel + '/' + name : name, st = fs.lstatSync(abs);
            if (st.isSymbolicLink()) fail('symbolic link not allowed: ' + r);
            if (st.isDirectory()) { visit(abs, r); continue; }
            if (!st.isFile()) fail('not a regular file: ' + r);
            files.push({ rel: r, abs, size: st.size });
        }
    })(root, '');
    return files;
}

function checkBuild(buildDir, viteConfig) {
    const mf = path.join(buildDir, 'manifest.json');
    if (!fs.existsSync(mf)) fail('manifest missing: build/manifest.json');
    let manifest;
    try { manifest = JSON.parse(fs.readFileSync(mf, 'utf8')); } catch (e) { fail('manifest is not valid JSON'); }
    if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) fail('manifest is not a JSON object');
    const inputs = [...new Set([...fs.readFileSync(viteConfig, 'utf8').matchAll(/['"](resources\/[^'"]+\.(?:js|css))['"]/g)].map((m) => m[1]))];
    if (inputs.length === 0) fail('no Vite inputs found in vite.config.js');
    const missing = inputs.filter((i) => !(i in manifest));
    if (missing.length) fail('manifest entries missing: ' + missing.join(', '));
    const files = walk(buildDir);
    const present = new Set(files.map((f) => f.rel));
    for (const [key, entry] of Object.entries(manifest)) {
        for (const ref of [entry.file, ...(entry.css || []), ...(entry.assets || [])]) {
            if (typeof ref !== 'string' || !SAFE.test(ref) || ref.split('/').includes('..')) fail('unsafe manifest path in ' + key + ': ' + ref);
            if (!present.has(ref)) fail('file referenced by the manifest is missing: ' + ref);
        }
        for (const imp of [...(entry.imports || []), ...(entry.dynamicImports || [])]) {
            if (!(imp in manifest)) fail('manifest import without entry: ' + imp);
        }
    }
    let total = 0;
    if (files.length > MAX_FILES) fail('too many files: ' + files.length);
    for (const f of files) {
        if (!SAFE.test(f.rel)) fail('unsafe file name: ' + f.rel);
        if (!EXT.test(f.rel)) fail('file type not allowed: ' + f.rel);
        if (f.size > MAX_FILE) fail('file too large: ' + f.rel);
        total += f.size;
    }
    if (total > MAX_TOTAL) fail('build too large: ' + total + ' bytes');
    return { manifest, inputs, files, total };
}

const [mode, ...args] = process.argv.slice(2);
if (mode === 'describe') {          // describe <worktree> <stage dir> <meta json>
    const [wt, stage, metaJson] = args, meta = JSON.parse(metaJson);
    const { manifest, inputs, files, total } = checkBuild(path.join(wt, 'public', 'build'), path.join(wt, 'vite.config.js'));
    const pkg = {
        format: 'hirfah-vite-package',
        format_version: 1,
        package_id: meta.package_id,
        source: { repository: meta.origin_url || null, branch_ref: meta.branch_ref, commit: meta.commit, tree: meta.tree, commit_time: meta.commit_time },
        builder: { node: meta.node, npm: meta.npm, vite: meta.vite, platform: process.platform, created_at: meta.created_at },
        build: {
            root: 'build',
            manifest: 'build/manifest.json',
            manifest_sha256: sha(path.join(wt, 'public', 'build', 'manifest.json')),
            manifest_entries: Object.keys(manifest).length,
            file_count: files.length,
            total_bytes: total,
        },
        vite_inputs: inputs.sort(),
        files: files.map((f) => ({ path: 'build/' + f.rel, sha256: sha(f.abs), size: f.size })),
    };
    fs.writeFileSync(path.join(stage, 'PACKAGE.json'), JSON.stringify(pkg, null, 2) + '\n');
    console.log(pkg.build.manifest_entries + ' manifest entries, ' + files.length + ' files, ' + total + ' bytes');
} else if (mode === 'verify') {     // verify <extracted dir> <vite.config.js at the commit>: exact files and hashes
    const [dir, viteConfig] = args;
    const pkg = JSON.parse(fs.readFileSync(path.join(dir, 'PACKAGE.json'), 'utf8'));
    const expected = new Map(pkg.files.map((f) => [f.path, f]));
    const actual = walk(dir).filter((f) => f.rel !== 'PACKAGE.json');
    if (actual.length !== expected.size) fail('archive holds ' + actual.length + ' files, PACKAGE.json lists ' + expected.size);
    for (const f of actual) {
        const e = expected.get(f.rel);
        if (!e) fail('unexpected file in archive: ' + f.rel);
        if (e.size !== f.size || e.sha256 !== sha(f.abs)) fail('hash mismatch in archive: ' + f.rel);
    }
    if (sha(path.join(dir, 'build', 'manifest.json')) !== pkg.build.manifest_sha256) fail('manifest hash mismatch in archive');
    checkBuild(path.join(dir, 'build'), viteConfig);
    console.log('archive content verified: ' + actual.length + ' files');
} else {
    fail('unknown helper mode');
}
NODE

mkdir -p "$STAGE"
META="$(node -e 'console.log(JSON.stringify({package_id:process.argv[1],origin_url:process.argv[2],branch_ref:process.argv[3],commit:process.argv[4],tree:process.argv[5],commit_time:process.argv[6],node:process.argv[7],npm:process.argv[8],vite:process.argv[9],created_at:new Date().toISOString()}))' \
    "$PKG_ID" "$ORIGIN_URL" "$BRANCH_REF" "$SHA" "$TREE" "$COMMIT_TIME" "$NODE_VERSION" "$NPM_VERSION" "$VITE_VERSION")"
node "$TMP/package-helper.cjs" describe "$WT" "$STAGE" "$META" || fail "build validation failed"
cp -R "$WT/public/build" "$STAGE/build"

# --- Archive: sorted, fixed owner/mode, mtime = commit time, gzip without name/time --------------------------------------
( cd "$STAGE" && tar --force-local --sort=name --owner=0 --group=0 --numeric-owner --mtime="@$COMMIT_EPOCH" \
    --mode='u+rwX,go+rX,go-w' -cf - PACKAGE.json build ) | gzip -n -9 > "$TMP/$ARCHIVE_NAME" || fail "cannot create the archive"

# --- Verify the archive itself: listing (types, paths) and extracted content -------------------------------------------
listing="$(tar --force-local -tvzf "$TMP/$ARCHIVE_NAME")" || fail "cannot list the archive"
bad_type="$(printf '%s\n' "$listing" | awk '{print substr($1,1,1)}' | grep -v '^[-d]$' || true)"
[ -z "$bad_type" ] || fail "archive contains links or special files"
bad_path="$(tar --force-local -tzf "$TMP/$ARCHIVE_NAME" | grep -vE '^(PACKAGE\.json|build/|build/[A-Za-z0-9._@-]+(/[A-Za-z0-9._@-]+)*/?)$' || true)"
[ -z "$bad_path" ] || fail "archive contains unexpected paths: $(printf '%s' "$bad_path" | head -3 | tr '\n' ';')"
mkdir -p "$VERIFY/pkg"
tar --force-local -xzf "$TMP/$ARCHIVE_NAME" --no-same-owner -C "$VERIFY/pkg" || fail "cannot extract the archive for verification"
git -C "$REPO" show "$SHA:vite.config.js" > "$VERIFY/vite-config-at-commit.js"
node "$TMP/package-helper.cjs" verify "$VERIFY/pkg" "$VERIFY/vite-config-at-commit.js" || fail "archive verification failed"

# --- Atomic publication -----------------------------------------------------------------------------------------------
# 1. Reserve: exclusive mkdir (fails if another run with the same package id holds it).
mkdir "$PARTIAL" 2>/dev/null || fail "package directory is reserved by another run or left by an earlier failure: $PARTIAL"
PARTIAL_OWNED=1
# 2. Fill and check inside the reserved directory.
cp "$TMP/$ARCHIVE_NAME" "$PARTIAL/$ARCHIVE_NAME" || fail "cannot copy the archive into $PARTIAL"
fault_point after-archive-before-checksum
( cd "$PARTIAL" && sha256sum "$ARCHIVE_NAME" > "$ARCHIVE_NAME.sha256" ) || fail "cannot write the checksum file"
( cd "$PARTIAL" && sha256sum -c --quiet "$ARCHIVE_NAME.sha256" ) || fail "checksum verification failed in $PARTIAL"
[ "$(ls -A "$PARTIAL" | LC_ALL=C sort | tr '\n' ' ')" = "$ARCHIVE_NAME $ARCHIVE_NAME.sha256 " ] \
    || fail "unexpected content in $PARTIAL"
fault_point before-publish
# 3. Reserve the final name: an exclusive mkdir fails if the name exists in any form (empty directory included),
#    so no existing destination is ever replaced.
mkdir "$PKG_DIR" 2>/dev/null || fail "package name already in use (nothing replaced): $PKG_DIR"
FINAL_RESERVED=1
fault_point after-final-reservation
if [ "${HIRFAH_PACKAGE_TEST_FAULT:-}" = simulate-foreign-write ]; then   # test only: another party writes into it
    echo "written by another party" > "$PKG_DIR/FOREIGN"
fi
# 4. Publish: one rename onto our own empty reservation. If the reservation is no longer empty, the rename fails
#    and nothing is replaced.
mv -T "$PARTIAL" "$PKG_DIR" 2>/dev/null || fail "cannot publish: $PKG_DIR is no longer our empty reservation (nothing replaced)"
PARTIAL_OWNED=0
FINAL_RESERVED=0
[ ! -e "$PARTIAL" ] || fail "publication left $PARTIAL behind"
# 5. Check the published directory.
[ "$(ls -A "$PKG_DIR" | LC_ALL=C sort | tr '\n' ' ')" = "$ARCHIVE_NAME $ARCHIVE_NAME.sha256 " ] \
    || fail "published directory does not hold exactly the package files: $PKG_DIR"
( cd "$PKG_DIR" && sha256sum -c --quiet "$ARCHIVE_NAME.sha256" ) || fail "checksum verification failed in $PKG_DIR"

info "Package:  $PKG_DIR/$ARCHIVE_NAME"
info "Size:     $(wc -c < "$PKG_DIR/$ARCHIVE_NAME") bytes"
info "SHA-256:  $(cut -d' ' -f1 "$PKG_DIR/$ARCHIVE_NAME.sha256")"
info "Source:   $SHA (tree $TREE), $BRANCH_REF"
info "Builder:  node $NODE_VERSION, npm $NPM_VERSION, vite $VITE_VERSION"
