# PLAUD Companion (Laravel 12) — Install, Packaging & Signed Updates Guide

This guide covers building signed releases, deploying on CloudPanel (Ubuntu 24.04/24.6, PHP 8.3/8.4, Nginx, MySQL/MariaDB), running the 1-click `/install` wizard, and performing super-admin-only updates and backups.

---

## 1. Developer 5-Line Cheat Sheet

```powershell
# 1. Generate ed25519 keypair OUTSIDE the repository (never commit the private key)
php scripts/updater-keys.php C:\keys\plaud.updater-key

# 2. Paste the printed base64 public key into backend-laravel/config/updater-public.key

# 3. Build unsigned fresh-install zip + signed full-update zip + dist/last-build.txt baseline
pwsh -File scripts/make-release.ps1 -PrivateKeyFile C:\keys\plaud.updater-key

# 4. Build signed partial patch zip against dist/last-build.txt (only changed/added/removed files)
pwsh -File scripts/make-partial.ps1 -PrivateKeyFile C:\keys\plaud.updater-key

# 5. Or build all three artifacts (install-fresh, update-full, update-patch) in one pass
pwsh -File scripts/make-all.ps1 -PrivateKeyFile C:\keys\plaud.updater-key
```

### Release Artifacts Produced in `dist/`

| Artifact | Signed? | Purpose |
| --- | --- | --- |
| `dist/plaud-companion-install-fresh.zip` | No | Initial server deployment before `/install` (includes `public/index.php` & `.env.example`, excludes `.env`, `storage/`, and private keys). |
| `dist/plaud-companion-update-full.zip` | Yes (`ed25519`) | Full signed update package verified by `Package::open()` (`code_dirs`, `root_files`, `public/assets`, `public/build`, `config/updater-public.key`). |
| `dist/plaud-companion-update-patch.zip` | Yes (`ed25519`) | Partial signed diff against `dist/last-build.txt` with `min_version` and `removed` file list. |
| `dist/last-build.txt` | N/A | SHA-256 baseline manifest of the last full build used by `make-partial.ps1`. |

> [!CAUTION]
> All release scripts enforce `Set-StrictMode -Version Latest`, build a clean `--no-dev --optimize-autoloader` `vendor/` in temporary staging, and **fail** if `.env`, `storage/`, or any private key is present, or if `backend-laravel/config/updater-public.key` is missing/empty.

---

## 2. CloudPanel Deployment (Ubuntu 24.6, PHP 8.3/8.4, MySQL/MariaDB)

1. In CloudPanel, create a **PHP 8.3** or **PHP 8.4** site and a **MySQL / MariaDB** database.
2. Upload and extract `plaud-companion-install-fresh.zip` into `/home/<site_user>/htdocs/<domain>/backend-laravel` (or clone the repository).
3. Apply `deploy/cloudpanel/nginx-cloudpanel-laravel.conf` in **CloudPanel → Sites → Vhost** (web root points to `backend-laravel/public`, `client_max_body_size 200M`, `fastcgi_read_timeout 300s`).
4. Run the deployment script:
   ```bash
   chmod +x deploy/cloudpanel/deploy-cloudpanel-laravel.sh
   sudo DOMAIN_NAME=plaud.yourdomain.com SITE_USER=plaud-companion ./deploy/cloudpanel/deploy-cloudpanel-laravel.sh
   ```

---

## 3. Operator Guide: 1-Click `/install` Wizard

Once deployed on a fresh server (`storage/app/installed.lock` does not exist):

1. Open `https://<your-domain>/install` in a browser.
2. **Step 1 — Preflight Checks**: Verifies PHP `>= 8.3`, extensions (`sodium`, `zip`, `pdo_mysql`, `mbstring`, `openssl`), writable `storage/` and `bootstrap/cache/`, and `proc_open` availability.
3. **Step 2 — Database & Environment**: Enter MySQL/MariaDB host, port, database name, username, and password. The installer tests the connection and writes `.env` with auto-generated `APP_KEY` and `JWT_SECRET_KEY`.
4. **Step 3 — Super-Admin & Default Workspace**: Create the initial `is_super_admin = true` owner account and default workspace, and run all additive database migrations.
5. **Step 4 — Lock**: Writes `storage/app/installed.lock`. All `/install` routes immediately return `404 Not Found` (`EnsureNotInstalled` middleware).

---

## 4. Operator Guide: Super-Admin Updates & Backups

Updates and backups are restricted strictly to authenticated users with `is_super_admin === true` (`EnsureSuperAdmin` middleware; workspace admins, editors, reviewers, and members receive `403 Forbidden`).

### Web UI (`/settings/updates`)

1. Sign in as the **Super-Admin** and navigate to `/settings/updates`.
2. **Download Backup**: Click **Backup** (`POST /api/v1/updates/backup`) to generate and download a snapshot before applying an update.
3. **Upload Release Zip**: Upload `plaud-companion-update-full.zip` or `plaud-companion-update-patch.zip` (`POST /api/v1/updates/upload`, supports single-file or chunked upload).
4. **Apply Update**: Enter the typed confirmation and click **Apply Update** (`POST /api/v1/updates/apply`). The UI polls `GET /api/v1/updates/status` every 2 seconds.
   - Lifecycle: `verify` → `safety` → `stage` → `artisan down` → `journaled swap` → `migrate` → `finish` (`bootstrap/cache` clear, `opcache_reset`, `artisan up`, prune old `previous/` keeping `keep_previous = 1`).
   - On any exception after swap, the updater automatically reverts the journal, clears caches, and brings the app back up (`rolled_back = true`).
5. **Manual Rollback**: If needed after a successful update, click **Rollback** (`POST /api/v1/updates/rollback`) to restore the single retained `previous/<from-version>/` snapshot.
6. **Discard Staged Upload**: `DELETE /api/v1/updates/upload` removes an unapplied staged zip.

---

## 5. Error Code Reference

All updater failures throw `App\Support\Update\UpdateException` with a machine-readable `error_code` and HTTP status code.

| Error Code | HTTP | Component | Meaning & Resolution |
| --- | --- | --- | --- |
| `update_disabled` | `503` | `Package` / `UpdateController` | `config/updater-public.key` (or `UPDATER_PUBLIC_KEY`) is missing or invalid. Configure the ed25519 public key before enabling updates. |
| `update_locked` | `409` | `UpdateStatus` | Non-blocking `flock()` failed because another update or rollback is currently running. Wait for it to finish. |
| `update_invalid_state` | `422` | `UpdateStatus` | Internal status transition attempted an invalid state (must be `idle`, `running`, `ok`, or `failed`). |
| `update_bad_zip` | `422` | `Package` / `Updater` | File is missing, empty, unreadable, or not a valid ZIP archive, or an entry failed to read/extract. |
| `update_too_big` | `422` | `Package` | Archive exceeds `max_zip_mb` (150 MB), unpacked size exceeds `max_unpacked_mb` (450 MB), a file exceeds `max_single_file_mb` (50 MB), or compression ratio exceeds `max_compression_ratio` (100x). |
| `update_too_many_entries` | `422` | `Package` | ZIP entry count exceeds `max_entries` (25,000). |
| `update_bad_signature` | `422` | `Package` | Missing `update/manifest.json` or `update/manifest.sig`, malformed base64 signature, or ed25519 detached signature verification failed. |
| `update_bad_manifest` | `422` | `Package` | `manifest.json` is invalid JSON, has bad `version`/`kind`/`min_version` format, payload `sha256` mismatch, duplicate paths, invalid `removed` paths, or missing archive entries. |
| `update_forbidden_path` | `422` | `Package` | Archive contains a symlink, path traversal (`..`), absolute path, forbidden file (`.env*`, `storage/`, `bootstrap/cache/`, `.git/`, `public/index.php`, `*.key`/`*.pem`/`*.sql`/`*.bak`), or path outside the allow-list. |
| `update_unlisted_file` | `422` | `Package` | Archive contains a file not listed in `update/manifest.json`. |
| `update_bad_hash` | `422` | `Package` | Extracted entry size or SHA-256 digest does not match `update/manifest.json`. |
| `update_downgrade_refused` | `422` | `Updater` | Target `version <= current VERSION` and `allowDowngrade` is `false`. |
| `update_min_version` | `422` | `Updater` | Partial patch requires `min_version` newer than the currently installed `VERSION`. Apply a full update instead. |
| `update_low_disk` | `422` | `Updater` | Free disk space is less than `2x` the release ZIP size. Free disk space and retry. |
| `update_not_writable` | `422` | `Updater` / `UpdateStatus` | Target path, parent directory, `work` dir, `previous` dir, or lock/temp file is not writable by the PHP process. Fix filesystem ownership/permissions. |
| `update_io_failed` | `422` | `Updater` / `UpdateStatus` | Filesystem copy, atomic rename, or JSON encode/write failed during staging or status persistence. |
| `update_swap_failed` | `422` | `Updater` | Unexpected failure during journaled directory/file swap. Automatic rollback is triggered. |
| `update_migrate_failed` | `422` | `MigrationRunner` | `php artisan migrate --force` (via `proc_open` or in-process `Artisan::call`) exited non-zero. Automatic rollback is triggered. |
| `update_no_previous` | `404` / `422` | `Updater` | No `previous/<version>/` backup exists, or its rollback journal is missing/corrupted. |
| `update_rollback_failed` | `500` | `Updater` | Rollback itself failed after an update error, or manual rollback failed. Site remains in maintenance mode (`rolled_back = false`) for manual recovery. |
