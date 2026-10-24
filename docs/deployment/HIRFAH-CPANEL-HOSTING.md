# HIRFAH — cPanel Hosting Runbook

| Field | Value |
|---|---|
| Document | CPANEL-HOSTING-DOC-01 |
| Status | Documentation only. Records a production incident and its verified fix; no deployment script, Laravel file or server setting is changed by this document |
| Scope | Laravel layout on the cPanel account, and Apache access to uploaded files under `/storage/*` |
| Project | HIRFAH (Laravel 12) |
| Date | 2026-10-24 |
| Related | `.cpanel.yml`, `deploy/cpanel-deploy.sh`, `config/filesystems.php`, `config/media-library.php`, `docs/deployment/HIRFAH-SITE-AVAILABILITY-DESIGN.md` |

> Every command in §4 to §6 is read-only. The only write commands in this document are the fix in §3.2 and the `.env` mode change recorded in §3.2b, both already applied in production, and the rollback in §3.4, which is **not** part of any normal procedure.
>
> **Confirmed** marks facts observed on the production server during the incident. Statements derived from the repository code are cited by file. Everything in §6 is diagnostic: possible causes to check, not observed facts.

---

## 1. Deployment layout

| Item | Path |
|---|---|
| Application (Git work tree, `.env`, `storage/`) | `/home/hirfahpal/hirfah-app` |
| Document root served by Apache | `/home/hirfahpal/public_html` |
| Uploaded files (disk `public`) | `/home/hirfahpal/hirfah-app/storage/app/public` |
| Storage link | `/home/hirfahpal/public_html/storage` -> `/home/hirfahpal/hirfah-app/storage/app/public` |
| Deployment log | `/home/hirfahpal/hirfah-app/storage/deploy/preflight.log` |

- The document root is **not** `hirfah-app/public`. `public_html` holds its own `index.php` and `.htaccess` (required by `deploy/cpanel-deploy.sh` step 1; these two files are not in the repository and their contents are not described here). The Vite build is installed in both `hirfah-app/public/build` and `public_html/build` by `deploy/vite-package-install.php`.
- Uploads (media library, product images, vendor profile media, settings logo and favicon) are stored on the `public` disk: `storage/app/public/media/{year}/{month}/{generated name}` for the media library. The admin shows the original file name (for example `055.png`) from the `media` table, not from the disk.
- Public URLs are built as `APP_URL` + `/storage/` + the stored path (`config/filesystems.php`, disk `public`).
- Static files, `/storage/*` included, are served directly by Apache, which runs as the user **`nobody`** (confirmed). Laravel is not involved unless Apache cannot find the file. In that case the front-controller rule (`RewriteCond %{REQUEST_FILENAME} !-f` in the repository's `public/.htaccess`) passes the request to `index.php`, and Laravel answers with a normal 404 (`config/site-availability.php` → `unmatched_static`).
- `storage/app/public/*` is ignored by Git. A deployment never adds, removes or changes uploaded files, and files uploaded locally never reach production through Git.
- **Do not run `php artisan storage:link` on this account.** It creates `hirfah-app/public/storage`, not `public_html/storage`, so it neither creates nor repairs the link Apache uses.

## 2. Production incident: uploaded images not shown

### 2.1 Symptom (confirmed)

- Uploaded images were not displayed on the site, while the admin showed the file name (for example `055.png`) read from the `media` table.
- Requests to the image URLs (`/storage/media/...`) returned **HTTP 404**.
- The Apache error log recorded:

  ```
  AH00037: Symbolic link not allowed or link target not accessible
  ```

### 2.2 Facts established on the server (confirmed)

- `/home/hirfahpal/hirfah-app` had mode **`700`** (owner `hirfahpal` only).
- Apache serves static files as **`nobody`**.
- `public_html/storage` is a symbolic link to `hirfah-app/storage/app/public`.
- After the fix in §3.2 the image was displayed, with no other change: the file was on disk and the URL was correct.

### 2.3 Why the response was 404 and not 403 (explanation)

Apache, as `nobody`, could not traverse `hirfah-app` to reach the link target, and logged `AH00037`. From Apache's point of view the requested file therefore did not exist, the `!-f` rewrite condition matched, and the request fell through to Laravel's `index.php`, which answers a missing static path with a 404 (§1). The 404 was a consequence of the permission problem, not of a missing file. This explanation follows from the configuration and is consistent with the logs and with the fix; it was not traced request by request.

## 3. Cause and fix

### 3.1 Confirmed cause

`nobody` had no execute (traverse) permission on `/home/hirfahpal/hirfah-app` (mode `700`). Following a symbolic link requires search permission on every directory of the target path, so Apache could not reach `hirfah-app/storage/app/public`.

### 3.2 Fix applied (verified in production)

```bash
setfacl -m u:nobody:--x /home/hirfahpal/hirfah-app
```

This adds a POSIX ACL entry that gives `nobody` traverse permission only on the application folder. After the fix, `getfacl` showed the entry and the image was displayed (confirmed).

What it grants:

- `nobody` can pass **through** `hirfah-app` to paths below it whose own modes allow it (`storage/app/public` and the upload folders).
- `nobody` **cannot list** `hirfah-app` (no `r`), so it can only open paths it already knows.
- Owner, group and other permissions are unchanged (`group::---`, `other::---`), and nothing below `hirfah-app` is changed. `ls -ld` now shows `drwx--x---+`: with an ACL present, the group bits display the ACL mask (`--x`), not a group permission, and `+` marks the ACL.

### 3.2a What traverse exposes, and the checks that keep it narrow

Traverse is not read access, but it removes the protection that mode `700` gave every file below `hirfah-app`. A file under `hirfah-app` whose own mode lets others read it (for example `644`, the usual mode of files checked out by Git) can now be opened by `nobody` **if its path is known**. Over HTTP, Apache only reaches what the document root links to, which is why the checks below matter:

- `.env` must not be readable by others. Read-only check: `ls -l /home/hirfahpal/hirfah-app/.env` must show no `r` in the last three characters (for example `-rw-------` or `-rw-r-----`). This check shows the mode only; never print the file. The production finding and its fix are recorded in §3.2b.
- `public_html/storage` must remain the only link from the document root into `hirfah-app`. Read-only check: `find /home/hirfahpal/public_html -maxdepth 2 -type l -ls` should list `public_html/storage` and no other link into `hirfah-app`.
- Grant nothing wider than `u:nobody:--x` on `hirfah-app` (no `r`, no `rx`, no default ACL with `-d`, no `-R`), and no `chmod` on the folder (§3.3, §5).

### 3.2b `.env` mode: finding and fix (confirmed)

| Step | Production result |
|---|---|
| Finding | `/home/hirfahpal/hirfah-app/.env` had mode **`644`**: readable by others. With the traverse ACL of §3.2 in place, `nobody` could open it by its known path |
| Fix applied | `chmod 600 /home/hirfahpal/hirfah-app/.env` |
| Verified with `stat` | `600 hirfahpal:hirfahpal /home/hirfahpal/hirfah-app/.env` |
| ACL after the change | `user:nobody:--x` still effective, `mask::--x` (the folder ACL of §3.2 is unchanged; the `chmod` applied to the file, not to `hirfah-app`) |
| Site after the change | Works normally; uploaded images are displayed |

The `chmod` targets the file only. It must never be applied to `hirfah-app` itself, which would rewrite the ACL mask (§5).

Read-only check, same output format as the verification above:

```bash
stat -c '%a %U:%G %n' /home/hirfahpal/hirfah-app/.env
```

Expected: `600 hirfahpal:hirfahpal /home/hirfahpal/hirfah-app/.env`.

Scope of this protection: under standard Unix permissions, mode `600` lets only the owner (`hirfahpal`) read and write the file; the group, other users and `nobody` cannot read it. It does not cover access with higher privileges (for example `root` or the hosting provider's tools), nor any ACL entry placed on the file itself (shown by `getfacl -p /home/hirfahpal/hirfah-app/.env` and by a `+` after the mode in `ls -l`). Those need a separate assessment.

### 3.3 Why an ACL and not `chmod 755`

| Option | Effect | Decision |
|---|---|---|
| `chmod 755 hirfah-app` | **Every** local user and process can list the application root and read any file whose own mode allows it | Rejected: wider than needed |
| `chmod 711 hirfah-app` | Every local user can traverse the folder (no listing) | Rejected: still grants all users, not only Apache |
| `setfacl -m u:nobody:--x hirfah-app` | Only `nobody`, traverse only | **Chosen**: the smallest grant that lets Apache follow the link |

The ACL also leaves the owner, group and other permissions unchanged (§3.2).

### 3.4 Rollback (not part of the normal procedure)

> **Do not run this as part of a fix, deployment or check.** Removing the entry breaks every image under `/storage/*` again (§2.1). Use it only when the access model is deliberately changed (for example the document root is moved and Apache no longer needs the link).

```bash
setfacl -x u:nobody /home/hirfahpal/hirfah-app
```

Confirm with `getfacl -p /home/hirfahpal/hirfah-app`: the `user:nobody:--x` line is gone.

## 4. Verification (read-only)

### 4.1 ACL entry and effective permission

```bash
getfacl -p /home/hirfahpal/hirfah-app
```

Production state after the fix (confirmed), below the `# file:`, `# owner:` and `# group:` header lines:

```
user::rwx
user:nobody:--x
group::---
mask::--x
other::---
```

- `user:nobody:--x` must be present.
- `mask::` must include `x`. If the line reads `user:nobody:--x   #effective:---`, the entry exists but is **not effective** (§5).
- `group::---` and `other::---` must stay as shown; any `r` there widens access to the application folder.

### 4.2 Every directory on the path

```bash
namei -l /home/hirfahpal/public_html/storage/media
```

`namei` shows the link and the mode of each component up to the target. `/home/hirfahpal` and `public_html` must be traversable by Apache (they are, since the site is served). Each directory below `hirfah-app` on the target path (`storage`, `storage/app`, `storage/app/public`, `media`) must give others at least `x` (normally `755`). `namei` does not show ACL entries: `hirfah-app` appears as `drwx--x---`, where the group bits are the ACL mask; use §4.1 for the entry itself.

### 4.3 Link target

```bash
readlink -f /home/hirfahpal/public_html/storage
```

Expected: `/home/hirfahpal/hirfah-app/storage/app/public`.

### 4.4 HTTP test with a real image

Take a real stored path from the media library table (read-only query, for example in phpMyAdmin). Table `media`, columns from `database/migrations/2025_06_11_000000_create_media_table.php`; no table prefix is configured. The original name is not unique, so list every match:

```sql
SELECT id, disk, file_path, file_name, created_at
FROM media
WHERE file_original_name = '055.png'
ORDER BY id DESC;
```

Use a row whose `disk` is `public`, and request its `file_path` (for example `media/2026/10/<generated name>.png`) under `/storage/`:

```bash
curl -sI "https://<domain>/storage/<file_path>"
```

Expected: `HTTP/... 200` and `Content-Type: image/...`. A `404` with `Content-Type: text/html` means the request reached Laravel: Apache could not serve the file (§6). `-I` sends a `HEAD` request and prints headers only.

Uploads outside the media library (product images, vendor profile media, settings logo and favicon) are not in `media`. Their path is stored on the owning record and is tested the same way.

### 4.5 Deployment log

```bash
grep -E "storage link|RESULT" /home/hirfahpal/hirfah-app/storage/deploy/preflight.log | tail -3
```

### 4.6 Limit of the deployment check `storage link OK`

Step 6 of `deploy/cpanel-deploy.sh` checks that `public_html/storage` is a symbolic link resolving to `hirfah-app/storage/app/public`. It runs as the account user `hirfahpal`, who owns `hirfah-app`, so it **passes even when `nobody` cannot traverse `hirfah-app`**. With the folder at mode `700` (§2.2), this check passes while Apache cannot follow the link.

`storage link OK` proves the link exists and points to the right place. It does **not** prove that Apache can follow it. The HTTP test (§4.4) proves that; the ACL check (§4.1) confirms the fix is still in place.

## 5. When the ACL can stop working

| Event | Effect | Recovery |
|---|---|---|
| `chmod` on `hirfah-app` (manual, a script, or a hosting "fix permissions" action) | With an ACL present, the group bits of `chmod` set the ACL **mask**. For example `chmod 700` sets `mask::---`, and `user:nobody:--x` becomes `#effective:---` while still listed | Re-run the fix in §3.2 (`setfacl -m` recalculates the mask), then verify §4.1 |
| `hirfah-app` recreated: fresh clone, copy, or restore from a backup or archive that does not keep ACLs (for example `tar` without `--acls`) | The new directory has no ACL entry | Re-run §3.2, then verify §4 |
| `hirfah-app` moved or renamed | A rename on the same filesystem keeps the ACL, but `public_html/storage` and the deployment paths (`/home/hirfahpal/hirfah-app` in `.cpanel.yml` and `deploy/cpanel-deploy.sh`) no longer match | Restore the documented path; verify §4.3 and §4.1 |
| Account moved to another server or restored by the host | ACLs may not be preserved; the Apache user may differ | Confirm the Apache user, re-run §3.2 if needed, verify §4 |
| Document root changed (for example to `hirfah-app/public`) | Apache needs traverse on `hirfah-app` for every request, not only `/storage/*` | Re-check the whole access model before relying on this document |

A normal deployment (`.cpanel.yml` → `deploy/cpanel-deploy.sh`) does not change the ACL. The script runs no `chmod` or `setfacl` on `hirfah-app`, and the Vite installer changes modes only inside its own staging and transaction folders and the two `build` folders. A cPanel Git update changes files inside the work tree; it does not recreate the `hirfah-app` directory itself.

## 6. Troubleshooting: images still fail with the ACL in place

Diagnostic only: these are possible causes, none of them was observed in the incident of §2. All checks are read-only. Check in this order:

1. **ACL effective?** §4.1: no `#effective:---`, and `mask::` includes `x`.
2. **Directory modes below `hirfah-app`.** §4.2: `storage`, `storage/app`, `storage/app/public` and the `media/{year}/{month}` folders need at least `x` for others. A single closed folder (for example one created with a restrictive umask) blocks only the files below it.
3. **File mode.** `ls -l /home/hirfahpal/hirfah-app/storage/app/public/<file_path>`: the file must be readable by others (normally `644`). Otherwise Apache usually answers 403 and logs `AH00132` (file permissions deny server access) rather than `AH00037`.
4. **Link owner.** If the server only follows links whose owner matches the target's owner (`SymLinksIfOwnerMatch`, a common cPanel setting), `ls -la /home/hirfahpal/public_html/storage` must show owner `hirfahpal`, the same as the target. A link created by another user (for example `root`) is then refused with `AH00037` even with correct permissions.
5. **Link target.** §4.3. A link to any other path gives 404 for every file under `/storage/`.
6. **File actually on disk.** The admin shows the file name from the database, which does not prove the file exists. Check the `file_path` from §4.4 with `ls -l`. Files uploaded on a local machine are never deployed (§1).
7. **Generated URL.** View the page source and compare the `src` of the image with the site address. It is built from `APP_URL` (`config/filesystems.php`). A different host, `http` instead of `https` (blocked as mixed content), or a stale `bootstrap/cache/config.php` gives broken images while CSS and JS still load. Compare only the URL seen in the page; do not print or share `.env`.
8. **Apache error log.** In the cPanel error log view (Metrics → Errors; the exact location depends on the cPanel version), look for `AH00037` (link not allowed or target not accessible), `AH00035` (search permission missing on a path component), `AH00132` (file permissions deny server access), or no entry at all (the request reached Laravel because the file is missing or the URL is wrong).

## 7. Checklist after a deployment or a move

After every deployment:

- [ ] `preflight.log` ends with `RESULT: DEPLOY PASSED` and contains `PASS: storage link OK` (§4.5); this alone is not enough (§4.6).
- [ ] `getfacl -p /home/hirfahpal/hirfah-app` shows `user:nobody:--x` with no `#effective:---` (§4.1).
- [ ] `curl -sI` on one real image returns `200` and `Content-Type: image/...` (§4.4).
- [ ] One page with an image shows it in a private browser window.
- [ ] `stat -c '%a %U:%G %n' /home/hirfahpal/hirfah-app/.env` gives `600 hirfahpal:hirfahpal /home/hirfahpal/hirfah-app/.env` (§3.2b). This also applies after a hosting move: a restored or recreated `.env` may come back as `644`.

Additionally after a re-clone, move, rename, restore, server migration or hosting permission repair:

- [ ] `readlink -f /home/hirfahpal/public_html/storage` gives `/home/hirfahpal/hirfah-app/storage/app/public` (§4.3).
- [ ] `namei -l /home/hirfahpal/public_html/storage/media` shows no closed directory below `hirfah-app` (§4.2).
- [ ] The link is owned by `hirfahpal` (§6, item 4).
- [ ] `public_html/storage` is the only link into `hirfah-app` (§3.2a).
- [ ] If the ACL entry is missing or not effective, apply §3.2 once and repeat the checks above.
