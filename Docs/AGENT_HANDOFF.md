# PLAUD Companion — Agent Handoff Notes
_Updated: 2026-11-17 | v2026.11.17.0416_

This document is for new AI agents (Claude Code or similar) picking up work on this repository. Read it **before** touching code.

---

## 1. What This Is

**PLAUD Companion** is a Laravel 12 / PHP 8.5 web application that manages transcripts from PLAUD voice recorders, runs AI summarisation and Q&A, and exports DOCX/ZIP packages. There is a companion Flutter mobile app under `mobile/`.

There is **no Python backend, no Next.js frontend, no Docker** — those were removed. The entire server-side is `backend-laravel/`.

---

## 2. Repository Layout

```
plaud-companion/
├── backend-laravel/      ← Laravel 12 app (the only backend)
│   ├── app/
│   │   ├── Http/Controllers/
│   │   │   ├── Api/V1/           ← JSON API controllers
│   │   │   ├── AiSettingsController.php  ← OpenRouter OAuth callback
│   │   │   ├── DashboardController.php
│   │   │   └── ProfileController.php
│   │   └── Services/
│   │       ├── AiService.php     ← all AI provider logic
│   │       ├── WorkspaceService.php
│   │       ├── Updater.php       ← signed self-update system
│   │       └── ErrorLogger.php
│   ├── config/
│   │   └── updater.php           ← MUST be safe to load outside full app bootstrap
│   ├── public/                   ← document root; contains sw.js, manifest.json, icons/
│   ├── resources/views/
│   │   ├── app/workspace.blade.php   ← main single-page workspace UI (~3300 lines)
│   │   ├── layouts/shell.blade.php   ← profile/settings/users pages shell
│   │   └── partials/
│   │       ├── theme.blade.php   ← CSS design tokens; __pcToggleTheme() global
│   │       └── pwa.blade.php     ← service worker registration
│   ├── routes/web.php
│   └── tests/
│       ├── Feature/              ← run with phpunit --testsuite Feature
│       └── Unit/                 ← run with phpunit (all)
├── mobile/               ← Flutter (Android + iOS)
├── scripts/
│   ├── make-all.ps1              ← builds signed release ZIPs
│   └── lib/
│       └── release-signing.php  ← signs release packages (used by scripts + tests)
└── dist/                 ← signed release ZIPs (gitignored except last-build.txt)
```

---

## 3. Local Development

| Environment | Path | Notes |
|---|---|---|
| Localhost (XAMPP) | `C:\xampp\htdocs\plaud-companion` | PHP 8.5; synced via robocopy |
| Source | `D:\development-important\plaud-companion\backend-laravel` | Edit here |
| Production | `https://test.dawncsimmons.com` | Deploys from GitHub `main` |

**Sync to localhost:**
```powershell
robocopy "d:\development-important\plaud-companion\backend-laravel" "C:\xampp\htdocs\plaud-companion" /MIR /XD ".git" "node_modules" "vendor" /XF ".env" /NFL /NDL /NJH /NJS /NC /NS
```
Exit code 1 (some files copied) or 3 (files + extras) = success; 0 = nothing changed; ≥8 = error.

**Run tests (from source):**
```powershell
cd d:\development-important\plaud-companion\backend-laravel
php vendor/bin/phpunit --testdox
```
Expected: 245 tests, 0 failures, 0 errors (as of this handoff).

**Build release ZIP:**
```powershell
cd d:\development-important\plaud-companion
powershell -ExecutionPolicy Bypass -File scripts\make-all.ps1
```
Produces `dist/plaud-companion-install-fresh.zip` and `dist/plaud-companion-update-*.zip`, both Ed25519-signed.

---

## 4. Key Standing Rules (from user, non-negotiable)

- **Ask before pushing to GitHub.** The user authorises each push explicitly.
- **Show each change before making it** (or confirm before destructive edits).
- **After each task:** test → deploy localhost → build zip → ask before GitHub push.
- Never put API keys, credentials, or secrets in code. `.env` only, server-side only.
- Never fake successful AI calls, imports, or exports.

---

## 5. Architecture Quick-Reference

### AI Providers (`app/Services/AiService.php`)
Providers: `openrouter`, `gemini`, `groq`, `anthropic`, `openai`, `builtin`.
- Keys stored in `ProviderConnection` model (workspace-scoped, DB-encrypted field).
- `fetchProviderModels($workspaceId, $userId, $providerId, $apiKey)` — calls live provider API; falls back to static list.
- `connectProvider(...)` — saves key, pings to verify.
- `complete(...)` — dispatches to provider-specific caller.
- Gemini default model: `gemini-1.5-flash`; fallback auto-retries on 404.
- OpenAI-compatible callers (OpenAI, Groq, OpenRouter): `callOpenAiCompatible()`.

### Design System (`resources/views/partials/theme.blade.php`)
- CSS custom properties `--pc-*` define light/dark palette.
- `window.__pcToggleTheme()` toggles dark/light; updates all `[data-pc-theme-btn]` elements.
- Floating toggle button is **suppressed** on pages that have `.topbar` (workspace, shell layout pages).
- Auth pages (login, register, forgot-password) get the floating button.
- Sidebars use hardcoded dark navy gradients (`#101B33→#162944` light; `#13203B→#0F1A30` dark) — **not** CSS vars — to stay consistently dark in both themes.

### Shell Layout vs Workspace
- `shell.blade.php` — wraps Profile, Users, Updates pages; has dark sidebar + topbar.
- `workspace.blade.php` — full SPA dashboard; ~3300 lines; all JS inline; `state` object holds workspace data.

### Routes Pattern
All routes require `web.auth` middleware. JSON API routes live under `/app/api/`.
AI connector routes:
```
GET  /app/api/workspaces/{id}/ai/connectors
POST /app/api/workspaces/{id}/ai/connectors/{provider}/connect
POST /app/api/workspaces/{id}/ai/connectors/{provider}/test
POST /app/api/workspaces/{id}/ai/connectors/{provider}/fetch-models   ← live model list
GET  /settings/ai/callback                                             ← OpenRouter OAuth
```

### Updater / Release System
- `config/updater.php` — must NOT crash when loaded outside a full Laravel bootstrap (unit tests). Uses `is_object(app()) && method_exists(app(), 'basePath')` guard.
- `Updater::apply()` — staged swap + migrate; migrate inner catch must NOT retry via `Artisan::call()` when `MigrationRunner` already threw `UpdateException` (causes facade error in unit tests).
- `ReleaseSigningTest.php` — searches up to 6 directory levels for `scripts/lib/release-signing.php` to work from both the repo root and XAMPP htdocs.

---

## 6. Currently Implemented (verified)

| Feature | Status |
|---|---|
| Auth (login, register, forgot/reset password) | IMPLEMENTED AND VERIFIED |
| Workspace SPA (transcripts, projects, AI assistant, export) | IMPLEMENTED AND VERIFIED |
| AI providers: OpenAI, Groq, Gemini, Anthropic, OpenRouter, Built-in | IMPLEMENTED AND VERIFIED |
| Live model fetch from provider APIs | IMPLEMENTED AND VERIFIED |
| OpenRouter OAuth Easy Connect | IMPLEMENTED AND VERIFIED |
| Dark/light theme toggle in topbar | IMPLEMENTED AND VERIFIED |
| Sidebars consistently dark in both themes | IMPLEMENTED AND VERIFIED |
| Profile, Users, Updates pages (shell layout) | IMPLEMENTED AND VERIFIED |
| Signed self-update system (Updater + Package) | IMPLEMENTED AND VERIFIED |
| All 245 unit + feature tests passing | IMPLEMENTED AND VERIFIED |
| PWA (sw.js, manifest.json, icons) | IMPLEMENTED AND VERIFIED |
| DOCX + ZIP bulk export | IMPLEMENTED BUT NOT VERIFIED (no e2e test) |
| Flutter mobile app | PARTIALLY IMPLEMENTED |
| PLAUD device sync | BLOCKED BY EXTERNAL CONFIGURATION |

---

## 7. Known Issues / Deferred Work

- **Production 404s for `/sw.js`, `/manifest.json`, `/icons/*`**: Static files ARE in git. Likely a server document-root or `.htaccess` configuration issue on the production host (`test.dawncsimmons.com`). Check `docs/CLOUDPANEL_DEPLOYMENT.md` and ensure the server root points to `backend-laravel/public/`.
- **Localhost 500s on `/app/api/workspaces/.../ai/connectors`**: The `MissingAppKeyException` logged is a session cookie termination issue, not a request-handling error. Clearing browser cookies for `localhost` resolves it. The `APP_KEY` in `C:\xampp\htdocs\plaud-companion\.env` is set correctly.
- **Workspace colour in dark mode (non-sidebar elements)**: The workspace uses local CSS vars (`--navy`, `--surface`, etc.) that map to `--pc-*`. They work correctly but some secondary elements may look slightly different across pages — incremental work needed if user reports it.
- **Flutter app**: Basic structure in place; feature parity with web not yet reached.
- **PLAUD device sync**: Marked as future work; partner API access declined. MCP / account-connect path is the planned route.

---

## 8. Memory Files

User preferences and project context are stored in:
`C:\Users\iqbal-pc\.claude\projects\d--development-important-plaud-companion\memory\`

Key memories:
- `product-direction-2026-11.md` — Laravel-only stack, server-encrypted data, developer role, two themes
- `feedback-ship-workflow.md` — test → deploy → build zip → ASK before GitHub push
- `design-system-state.md` — `--pc-*` token system, light/dark toggle placement

---

## 9. Git Workflow

```bash
# Check status
git status

# After changes: sync, test, build, then ask user to push
robocopy ... (see §3)
php vendor/bin/phpunit --testdox
powershell -File scripts\make-all.ps1

# Commit
git add <specific files>
git commit -m "type(scope): description\n\nCo-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>"

# Push — ASK USER FIRST
git push origin main
```

Branches: everything on `main`. No feature branches currently in use.
