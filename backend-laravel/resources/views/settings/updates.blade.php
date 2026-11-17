@extends('layouts.shell')

@section('title', 'Updates & Backups')
@section('active', 'updates')

@push('head')
<style>
:root {
    --navy: var(--pc-surface);
    --navy-light: var(--pc-surface-alt);
    --navy-border: var(--pc-border);
    --emerald: var(--pc-emerald);
    --emerald-hover: var(--pc-emerald-hover);
    --card: var(--pc-surface);
    --text: var(--pc-text);
    --muted: var(--pc-muted);
    --danger: var(--pc-danger);
    --warn: var(--pc-amber);
}
.grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
@media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
.sub { margin: 0 0 16px; font-size: 13px; color: var(--muted); }
.kv { display: grid; gap: 8px; font-size: 13px; }
.kv-row { display: flex; justify-content: space-between; gap: 12px; padding: 8px 10px; background: var(--navy); border: 1px solid var(--navy-border); border-radius: 8px; }
.kv-row span:first-child { color: var(--muted); }
.pill { font-size: 11px; font-weight: 700; padding: 3px 9px; border-radius: 999px; }
.pill.ok { background: rgba(24, 168, 121, 0.16); color: var(--emerald); }
.pill.fail { background: rgba(239, 68, 68, 0.16); color: var(--danger); }
.pill.warn { background: rgba(245, 158, 11, 0.16); color: var(--warn); }
.btn-primary { background: var(--emerald); color: #07131E; border: none; border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-primary:hover:not(:disabled) { background: var(--emerald-hover); }
.btn-secondary { background: var(--navy-light); color: var(--text); border: 1px solid var(--navy-border); border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-danger { background: rgba(239, 68, 68, 0.2); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.45); border-radius: 8px; padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer; }
button:disabled { opacity: 0.45; cursor: not-allowed; }
input[type="text"], input[type="file"], input[type="password"] {
    width: 100%;
    background: var(--navy);
    border: 1px solid var(--navy-border);
    border-radius: 8px;
    padding: 10px 12px;
    color: var(--text);
    font-size: 13px;
    box-sizing: border-box;
}
input[type="text"]:focus, input[type="password"]:focus { border-color: var(--emerald); outline: none; }
.row-actions { display: flex; gap: 10px; align-items: center; margin-top: 12px; flex-wrap: wrap; }
.progress { width: 100%; height: 8px; background: var(--navy); border-radius: 999px; overflow: hidden; margin-top: 10px; display: none; }
.progress-bar { height: 100%; width: 0%; background: var(--emerald); transition: width 0.2s ease; }
.upd-alert { padding: 12px 14px; border-radius: 8px; font-size: 13px; display: none; margin-bottom: 20px; }
.upd-alert.show { display: block; }
.upd-alert.error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #FCA5A5; }
.upd-alert.success { background: rgba(24, 168, 121, 0.15); border: 1px solid rgba(24, 168, 121, 0.4); color: #6EE7B7; }
</style>
@endpush

@section('content')
<div id="alert" class="upd-alert"></div>

<div class="grid-2">
    <!-- Status & PHP Limits -->
    <div class="card">
        <h2 style="margin-top:0;">Live Updater Status</h2>
        <p class="sub">Polls <code>/settings/updates/api/status</code> every 2 seconds.</p>
        <div class="kv">
            <div class="kv-row"><span>State</span><strong id="st-state">—</strong></div>
            <div class="kv-row"><span>Step</span><span id="st-step">—</span></div>
            <div class="kv-row"><span>Target Version</span><span id="st-target">—</span></div>
            <div class="kv-row"><span>Last Error</span><span id="st-error">None</span></div>
        </div>
        <h2 style="margin-top:18px;">PHP Upload Limits</h2>
        <div class="kv" style="margin-top:8px;">
            <div class="kv-row"><span>upload_max_filesize</span><span id="lim-upload">{{ $phpLimits['upload_max_filesize'] }}</span></div>
            <div class="kv-row"><span>post_max_size</span><span id="lim-post">{{ $phpLimits['post_max_size'] }}</span></div>
            <div class="kv-row"><span>Max Release Zip</span><span>{{ $maxZipMb }} MB</span></div>
        </div>
        <div style="margin-top:14px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
            <span id="updater-badge" class="pill {{ $updaterEnabled ? 'ok' : 'warn' }}">
                {{ $updaterEnabled ? 'UPDATER ENABLED' : 'KEY NOT CONFIGURED (503)' }}
            </span>
            <span id="version-badge" class="pill ok">v—</span>
        </div>
    </div>

    <!-- Backup & Rollback -->
    <div class="card">
        <h2 style="margin-top:0;">Backup &amp; Manual Rollback</h2>
        <p class="sub">Create a database + state snapshot before applying updates, or roll back to the previous release.</p>
        <div class="kv">
            <div class="kv-row"><span>Latest Backup</span><span id="bk-name">None</span></div>
            <div class="kv-row"><span>Previous Release</span><span id="prev-version">None</span></div>
        </div>
        <div class="row-actions">
            <button type="button" class="btn-primary" onclick="createBackup()">Create Backup Now</button>
        </div>

        <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--navy-border);">
            <label for="rollback-confirm" style="display:block; font-size:12px; color:var(--muted); margin-bottom:6px;">
                Type <code>ROLLBACK</code> to restore previous release:
            </label>
            <input id="rollback-confirm" type="text" placeholder="ROLLBACK">
            <div class="row-actions">
                <button type="button" id="btn-rollback" class="btn-danger" onclick="triggerRollback()" disabled>
                    Rollback to Previous Version
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Encrypted client-data backup -->
<div class="card" style="margin-top:0;">
    <h2 style="margin-top:0;">🔒 Encrypted Client-Data Backup</h2>
    <p class="sub">A password-protected copy of all client data — documents, transcripts, files and settings. The file is unreadable without its passphrase, so it is safe to keep off-site. You can restore it here or on a new server.</p>

    <div style="display:grid; gap:10px; max-width:460px;">
        <label for="bk-pass" style="font-size:12px; color:var(--muted);">Backup passphrase (at least 10 characters — write it down; it cannot be recovered)</label>
        <input id="bk-pass" type="password" placeholder="Choose a passphrase" autocomplete="new-password">
        <div class="row-actions">
            <button type="button" class="btn-primary" onclick="createDataBackup()">Create &amp; Download Backup</button>
        </div>
    </div>

    <div id="data-backups-list" style="margin-top:16px;"></div>

    <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--navy-border);">
        <h3 style="margin:0 0 8px; font-size:14px;">Restore from a backup file</h3>
        <p class="sub" style="margin-top:0;">This replaces all current data with the backup's contents. Everyone will be signed out.</p>
        <div style="display:grid; gap:10px; max-width:460px;">
            <input id="rs-file" type="file" accept=".plaudbackup">
            <input id="rs-pass" type="password" placeholder="The backup's passphrase" autocomplete="off">
            <label style="font-size:12px; color:var(--muted); display:flex; gap:8px; align-items:flex-start;">
                <input id="rs-appkey" type="checkbox" style="margin-top:3px; width:auto;">
                <span>This backup is from a <b>different server</b>. Also restore its security key (needed to read connected Plaud sign-ins).</span>
            </label>
            <label for="rs-confirm" style="font-size:12px; color:var(--muted);">Type <code>RESTORE</code> to confirm:</label>
            <input id="rs-confirm" type="text" placeholder="RESTORE">
            <div class="row-actions">
                <button type="button" id="btn-restore" class="btn-danger" onclick="restoreDataBackup()">Restore Backup</button>
            </div>
        </div>
    </div>
</div>

<!-- Upload & Apply -->
<div class="card">
    <h2 style="margin-top:0;">Upload &amp; Apply Signed Release</h2>
    <p class="sub">Uploads automatically use chunked transfer when larger than PHP's single-request limit.</p>

    <input id="pkg-file" type="file" accept=".zip">
    <div class="progress" id="upload-progress"><div class="progress-bar" id="upload-bar"></div></div>

    <div class="row-actions">
        <button type="button" class="btn-primary" onclick="uploadPackage()">Upload Signed Package</button>
        <button type="button" id="btn-delete-staged" class="btn-secondary" onclick="deleteStaged()" disabled>
            Delete Staged Package
        </button>
    </div>

    <div id="staged-info" class="kv" style="margin-top:14px; display:none;">
        <div class="kv-row"><span>Staged Version</span><strong id="staged-version">—</strong></div>
        <div class="kv-row"><span>Kind / Min Version</span><span id="staged-kind">—</span></div>
        <div class="kv-row"><span>SHA-256</span><span id="staged-sha" style="font-family:monospace; font-size:11px;">—</span></div>
    </div>

    <div style="margin-top:18px; padding-top:14px; border-top:1px solid var(--navy-border);">
        <label for="apply-confirm" style="display:block; font-size:12px; color:var(--muted); margin-bottom:6px;">
            Type <code>APPLY UPDATE</code> to enter maintenance mode, swap files, and run migrations:
        </label>
        <input id="apply-confirm" type="text" placeholder="APPLY UPDATE">
        <div class="row-actions">
            <label style="display:flex; align-items:center; gap:6px; font-size:12px; color:var(--muted);">
                <input type="checkbox" id="allow-downgrade" style="width:auto;"> Allow downgrade / same-version re-apply
            </label>
            <button type="button" id="btn-apply" class="btn-primary" onclick="applyUpdate()" disabled>
                Apply Staged Update
            </button>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const UPDATES_API_BASE = '{{ url('/settings/updates/api') }}';
let phpLimits = @json($phpLimits);

function showAlert(msg, type = 'error') {
    const el = document.getElementById('alert');
    el.textContent = msg;
    el.className = 'upd-alert show ' + type;
}

async function pollStatus() {
    try {
        const res = await fetch(`${UPDATES_API_BASE}/status`, {
            headers: { 'Accept': 'application/json' },
            credentials: 'same-origin'
        });
        if (!res.ok) return;
        const data = await res.json();

        if (data.php_limits) phpLimits = data.php_limits;
        document.getElementById('version-badge').textContent = 'v' + (data.current_version || '0.0.0.0');

        const ub = document.getElementById('updater-badge');
        ub.textContent = data.enabled ? 'UPDATER ENABLED' : 'KEY NOT CONFIGURED (503)';
        ub.className = 'pill ' + (data.enabled ? 'ok' : 'warn');

        const st = data.status || {};
        document.getElementById('st-state').textContent = st.state || 'idle';
        document.getElementById('st-step').textContent = st.step || '—';
        document.getElementById('st-target').textContent = st.version || '—';
        document.getElementById('st-error').textContent = st.error || 'None';

        document.getElementById('bk-name').textContent = data.last_backup
            ? `${data.last_backup.filename} (${Math.round(data.last_backup.size_bytes / 1024)} KB)`
            : 'None';

        const prev = data.previous;
        document.getElementById('prev-version').textContent = prev && prev.version ? prev.version : (prev || 'None');
        document.getElementById('btn-rollback').disabled = !prev;

        const staged = data.staged_upload;
        const stagedBox = document.getElementById('staged-info');
        const hasStaged = Boolean(staged && staged.exists);
        stagedBox.style.display = hasStaged ? 'grid' : 'none';
        document.getElementById('btn-delete-staged').disabled = !hasStaged;
        document.getElementById('btn-apply').disabled = !hasStaged || !data.enabled;

        if (hasStaged) {
            document.getElementById('staged-version').textContent = staged.version || (staged.error || 'Unverified');
            document.getElementById('staged-kind').textContent = (staged.kind || '—') + (staged.min_version ? ` (min ${staged.min_version})` : '');
            document.getElementById('staged-sha').textContent = staged.sha256 || '—';
        }
    } catch (_) {}
}

async function uploadPackage() {
    const input = document.getElementById('pkg-file');
    if (!input.files || !input.files[0]) { showAlert('Select a signed release .zip file first.', 'error'); return; }
    const file = input.files[0];
    const maxSingle = Math.min(phpLimits.upload_max_bytes || 2097152, phpLimits.post_max_bytes || 2097152);
    const chunkSize = Math.max(512 * 1024, Math.min(2 * 1024 * 1024, Math.floor(maxSingle * 0.75)));
    const progress = document.getElementById('upload-progress');
    const bar = document.getElementById('upload-bar');
    progress.style.display = 'block';
    bar.style.width = '0%';
    try {
        if (file.size <= chunkSize) {
            const fd = new FormData();
            fd.append('file', file);
            const res = await fetch(`${UPDATES_API_BASE}/upload`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                credentials: 'same-origin',
                body: fd
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.detail || data.error || 'Upload failed');
            bar.style.width = '100%';
            showAlert('Package uploaded and verified.', 'success');
        } else {
            const uploadId = 'up_' + Date.now() + '_' + Math.random().toString(36).slice(2, 10);
            const totalChunks = Math.ceil(file.size / chunkSize);
            for (let i = 0; i < totalChunks; i++) {
                const start = i * chunkSize;
                const end = Math.min(file.size, start + chunkSize);
                const fd = new FormData();
                fd.append('upload_id', uploadId);
                fd.append('chunk_index', String(i));
                fd.append('total_chunks', String(totalChunks));
                fd.append('total_size', String(file.size));
                fd.append('chunk', file.slice(start, end), file.name + '.part');
                const res = await fetch(`${UPDATES_API_BASE}/upload`, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    credentials: 'same-origin',
                    body: fd
                });
                const data = await res.json();
                if (!res.ok) throw new Error(data.detail || data.error || 'Chunk upload failed');
                bar.style.width = Math.round(((i + 1) / totalChunks) * 100) + '%';
            }
            showAlert('Chunked package uploaded and verified.', 'success');
        }
        await pollStatus();
    } catch (e) { showAlert(e.message, 'error'); }
}

async function deleteStaged() {
    try {
        const res = await fetch(`${UPDATES_API_BASE}/upload`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Delete failed');
        showAlert('Staged package removed.', 'success');
        await pollStatus();
    } catch (e) { showAlert(e.message, 'error'); }
}

async function applyUpdate() {
    const confirmation = document.getElementById('apply-confirm').value;
    const allowDowngrade = document.getElementById('allow-downgrade').checked;
    try {
        const res = await fetch(`${UPDATES_API_BASE}/apply`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({ confirmation, allow_downgrade: allowDowngrade })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || data.error || 'Apply failed');
        document.getElementById('apply-confirm').value = '';
        showAlert('Update applied successfully.', 'success');
        await pollStatus();
    } catch (e) { showAlert(e.message, 'error'); }
}

async function triggerRollback() {
    const confirmation = document.getElementById('rollback-confirm').value;
    try {
        const res = await fetch(`${UPDATES_API_BASE}/rollback`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({ confirmation })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || data.error || 'Rollback failed');
        document.getElementById('rollback-confirm').value = '';
        showAlert('Rollback completed.', 'success');
        await pollStatus();
    } catch (e) { showAlert(e.message, 'error'); }
}

async function createBackup() {
    try {
        const res = await fetch(`${UPDATES_API_BASE}/backup`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'same-origin'
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.detail || 'Backup failed');
        showAlert('Backup created: ' + data.backup.filename, 'success');
        await pollStatus();
    } catch (e) { showAlert(e.message, 'error'); }
}

function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;' }[c])); }

async function loadDataBackups() {
    const box = document.getElementById('data-backups-list');
    try {
        const res = await fetch(`${UPDATES_API_BASE}/data-backups`, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
        const data = await res.json();
        const items = data.backups || [];
        if (items.length === 0) { box.innerHTML = '<p class="sub" style="margin:0;">No encrypted backups yet.</p>'; return; }
        box.innerHTML = '<div class="kv">' + items.map(b => {
            const when = b.created_at ? new Date(b.created_at).toLocaleString() : '';
            const mb = (b.size_bytes / 1048576).toFixed(2);
            return `<div class="kv-row"><span>${esc(b.filename)}<br><small style="color:var(--muted);">${esc(when)} · ${mb} MB · ${esc(b.tables||0)} tables · ${esc(b.files||0)} files</small></span>
                <span style="display:flex; gap:8px;">
                    <a class="btn-secondary" href="${UPDATES_API_BASE}/data-backups/${encodeURIComponent(b.filename)}/download" style="text-decoration:none; padding:6px 10px; border-radius:6px; font-size:12px; font-weight:600;">Download</a>
                    <button type="button" class="btn-danger" style="padding:6px 10px; font-size:12px;" onclick="deleteDataBackup('${esc(b.filename)}')">Delete</button>
                </span></div>`;
        }).join('') + '</div>';
    } catch (e) { box.innerHTML = '<p class="sub">Could not load backups.</p>'; }
}

async function createDataBackup() {
    const passphrase = document.getElementById('bk-pass').value;
    if (passphrase.length < 10) { showAlert('The passphrase must be at least 10 characters.', 'error'); return; }
    try {
        const res = await fetch(`${UPDATES_API_BASE}/data-backups`, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            credentials: 'same-origin',
            body: JSON.stringify({ passphrase })
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Backup failed');
        showAlert(data.message, 'success');
        document.getElementById('bk-pass').value = '';
        await loadDataBackups();
        window.location.href = `${UPDATES_API_BASE}/data-backups/${encodeURIComponent(data.filename)}/download`;
    } catch (e) { showAlert(e.message, 'error'); }
}

async function deleteDataBackup(filename) {
    if (!confirm('Delete this backup file from the server? Keep your downloaded copy.')) return;
    try {
        const res = await fetch(`${UPDATES_API_BASE}/data-backups/${encodeURIComponent(filename)}`, {
            method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, credentials: 'same-origin'
        });
        if (!res.ok) throw new Error('Delete failed');
        await loadDataBackups();
    } catch (e) { showAlert(e.message, 'error'); }
}

async function restoreDataBackup() {
    const file = document.getElementById('rs-file').files[0];
    const passphrase = document.getElementById('rs-pass').value;
    if (document.getElementById('rs-confirm').value !== 'RESTORE') { showAlert('Type RESTORE to confirm.', 'error'); return; }
    if (!file) { showAlert('Choose a backup file.', 'error'); return; }
    if (!confirm('This replaces ALL current data with the backup. Continue?')) return;
    const fd = new FormData();
    fd.append('backup', file);
    fd.append('passphrase', passphrase);
    fd.append('restore_app_key', document.getElementById('rs-appkey').checked ? '1' : '0');
    const btn = document.getElementById('btn-restore');
    btn.disabled = true; btn.textContent = 'Restoring…';
    try {
        const res = await fetch(`${UPDATES_API_BASE}/data-backups/restore`, {
            method: 'POST', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }, credentials: 'same-origin', body: fd
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Restore failed');
        showAlert(data.message, 'success');
    } catch (e) { showAlert(e.message, 'error'); }
    finally { btn.disabled = false; btn.textContent = 'Restore Backup'; }
}

loadDataBackups();
pollStatus();
setInterval(pollStatus, 2000);
</script>
@endpush
