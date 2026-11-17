@extends('layouts.shell')

@section('title', 'Developer Console')
@section('active', 'developer')

@push('head')
<style>
:root {
    --navy: var(--pc-surface);
    --navy-light: var(--pc-surface-alt);
    --navy-border: var(--pc-border);
    --emerald: var(--pc-emerald);
    --card: var(--pc-surface);
    --text: var(--pc-text);
    --muted: var(--pc-muted);
    --danger: var(--pc-danger);
    --purple: #C084FC;
    --purple-bg: rgba(168,85,247,.16);
    --purple-border: rgba(168,85,247,.35);
}
.metrics-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
@media(max-width:768px){ .metrics-grid { grid-template-columns:repeat(2,1fr); } }
.metric-card { background:var(--card); border:1px solid var(--navy-border); border-radius:12px; padding:16px 18px; }
.metric-card span { font-size:12px; color:var(--muted); display:block; margin-bottom:4px; }
.metric-card strong { font-size:26px; font-weight:800; }

.tab-bar { display:flex; gap:4px; border-bottom:1px solid var(--navy-border); margin-bottom:20px; }
.tab-btn { background:none; border:none; color:var(--muted); font:600 13px/1 var(--pc-font,system-ui); padding:10px 16px; cursor:pointer; border-bottom:2px solid transparent; margin-bottom:-1px; }
.tab-btn.active { color:var(--purple); border-bottom-color:var(--purple); }
.tab-pane { display:none; }
.tab-pane.active { display:block; }

table { width:100%; border-collapse:collapse; font-size:13.5px; }
th { text-align:left; padding:10px 14px; color:var(--muted); font-size:11.5px; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid var(--navy-border); white-space:nowrap; }
td { padding:12px 14px; border-bottom:1px solid rgba(128,128,128,.08); vertical-align:middle; }
tr:hover td { background:rgba(128,128,128,.04); }
.user-info { display:flex; align-items:center; gap:10px; }
.user-avatar { width:34px; height:34px; border-radius:9px; background:linear-gradient(135deg,#18A879 0%,#104C39 100%); display:grid; place-items:center; font-weight:700; font-size:13px; color:#fff; flex-shrink:0; }
.user-avatar.dev { background:linear-gradient(135deg,#9333EA,#6B21A8); }
.pill { font-size:11px; font-weight:700; padding:3px 9px; border-radius:999px; display:inline-block; }
.pill.active { background:rgba(24,168,121,.16); color:var(--emerald); border:1px solid rgba(24,168,121,.35); }
.pill.suspended { background:rgba(239,68,68,.16); color:var(--danger); border:1px solid rgba(239,68,68,.35); }
.pill.dev { background:var(--purple-bg); color:var(--purple); border:1px solid var(--purple-border); }
.pill.admin { background:rgba(168,85,247,.12); color:#C084FC; }
.pill.user { background:rgba(148,163,184,.12); color:var(--muted); }
.pill.server { background:rgba(239,68,68,.14); color:#FCA5A5; border:1px solid rgba(239,68,68,.3); }
.pill.browser { background:rgba(251,191,36,.14); color:#FCD34D; border:1px solid rgba(251,191,36,.3); }
.action-row { display:flex; gap:6px; flex-wrap:wrap; }
.btn-sm { font-size:11.5px; padding:5px 10px; border-radius:7px; border:1px solid var(--navy-border); background:var(--navy-light); color:var(--text); cursor:pointer; white-space:nowrap; }
.btn-sm:hover { border-color:var(--emerald); }
.btn-sm.danger { border-color:rgba(239,68,68,.4); color:#FCA5A5; background:rgba(239,68,68,.1); }
.btn-sm.danger:hover { background:rgba(239,68,68,.22); }
.btn-sm.purple { border-color:var(--purple-border); color:var(--purple); background:var(--purple-bg); }
.btn-sm.purple:hover { background:rgba(168,85,247,.28); }

/* workspace cards */
.ws-card { background:var(--card); border:1px solid var(--navy-border); border-radius:12px; margin-bottom:12px; overflow:hidden; }
.ws-card-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; cursor:pointer; }
.ws-card-head:hover { background:rgba(128,128,128,.04); }
.ws-meta { display:flex; gap:16px; font-size:12px; color:var(--muted); flex-wrap:wrap; }
.ws-docs { display:none; padding:0 18px 14px; }
.ws-docs.open { display:block; }
.ws-docs table { font-size:12.5px; }

/* error log */
.err-filter { display:flex; gap:8px; align-items:center; margin-bottom:14px; flex-wrap:wrap; }
.err-row { font-size:12.5px; }
.err-msg { max-width:380px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.err-ref { font-family:monospace; font-size:11.5px; color:var(--muted); }
.err-expand { cursor:pointer; color:var(--emerald); font-size:11px; }
.err-detail { background:var(--navy-light); border-radius:8px; padding:12px; margin:4px 0 8px; font:12px/1.6 monospace; white-space:pre-wrap; word-break:break-all; display:none; }
.search-input { background:var(--navy); border:1px solid var(--navy-border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; min-width:220px; }
.search-input:focus { border-color:var(--emerald); }
select.filter-select { background:var(--navy); border:1px solid var(--navy-border); border-radius:8px; padding:8px 12px; color:var(--text); font-size:13px; outline:none; }

/* modal */
.overlay { position:fixed; inset:0; background:rgba(11,19,36,.88); backdrop-filter:blur(6px); display:none; align-items:center; justify-content:center; z-index:1050; padding:20px; }
.overlay.open { display:flex; }
.modal-card { background:var(--card); border:1px solid var(--navy-border); border-radius:16px; width:100%; max-width:480px; box-shadow:0 20px 45px rgba(0,0,0,.55); }
.modal-head { padding:18px 24px 14px; border-bottom:1px solid var(--navy-border); display:flex; align-items:center; justify-content:space-between; }
.modal-head h2 { margin:0; font-size:16px; font-weight:700; }
.modal-body { padding:20px 24px; display:grid; gap:14px; }
.modal-foot { padding:14px 24px; display:flex; justify-content:flex-end; gap:8px; border-top:1px solid var(--navy-border); }
.form-label { font-size:12px; font-weight:700; display:block; margin-bottom:5px; color:var(--muted); }
.form-input { width:100%; background:var(--navy); border:1px solid var(--navy-border); border-radius:8px; padding:9px 12px; color:var(--text); font-size:13.5px; outline:none; box-sizing:border-box; }
.form-input:focus { border-color:var(--purple); }
.close-btn { background:none; border:none; color:var(--muted); font-size:22px; cursor:pointer; line-height:1; padding:4px; }
.close-btn:hover { color:var(--text); }

/* toast */
.toast { position:fixed; bottom:24px; right:24px; padding:12px 18px; border-radius:10px; font-size:13.5px; font-weight:600; box-shadow:0 10px 30px rgba(0,0,0,.4); display:none; z-index:9999; min-width:200px; }
.toast.ok { background:#064E3B; border:1px solid var(--emerald); color:#D1FAE5; }
.toast.err { background:#7F1D1D; border:1px solid var(--danger); color:#FEE2E2; }

.spinner { display:inline-block; width:14px; height:14px; border:2px solid rgba(255,255,255,.25); border-top-color:#fff; border-radius:50%; animation:spin .6s linear infinite; }
@keyframes spin { to { transform:rotate(360deg); } }
.empty-state { text-align:center; padding:40px; color:var(--muted); font-size:14px; }
</style>
@endpush

@section('content')
<h1 class="page-heading" style="margin:0 0 20px; font-size:20px; font-weight:800;">Developer Console</h1>

<div class="metrics-grid">
    <div class="metric-card">
        <span>Total Users</span>
        <strong style="color:var(--text);">{{ $totalUsers }}</strong>
    </div>
    <div class="metric-card">
        <span>Workspaces</span>
        <strong style="color:var(--text);">{{ $totalWorkspaces }}</strong>
    </div>
    <div class="metric-card">
        <span>Developers</span>
        <strong style="color:var(--purple);">{{ $developerCount }}</strong>
    </div>
    <div class="metric-card">
        <span>Error Log</span>
        <strong style="color:{{ $pendingErrors > 0 ? 'var(--danger)' : 'var(--text)' }};" id="metricErrors">{{ $pendingErrors }}</strong>
    </div>
</div>

<div class="tab-bar">
    <button class="tab-btn active" onclick="switchTab('users',this)">Users</button>
    <button class="tab-btn" onclick="switchTab('workspaces',this)">Workspaces</button>
    <button class="tab-btn" onclick="switchTab('errors',this)">Error Log</button>
</div>

{{-- ===== USERS TAB ===== --}}
<div id="tab-users" class="tab-pane active">
    <div style="display:flex; gap:10px; align-items:center; margin-bottom:14px; flex-wrap:wrap;">
        <input class="search-input" type="search" id="userSearch" placeholder="Search name or email…" oninput="filterUsers()">
        <select class="filter-select" id="userFilter" onchange="filterUsers()">
            <option value="">All roles</option>
            <option value="dev">Developer</option>
            <option value="admin">Admin</option>
            <option value="user">User</option>
            <option value="suspended">Suspended</option>
        </select>
        <button class="btn-sm purple" onclick="loadPlaudSamples()" id="samplesBtn">Load PLAUD Samples</button>
    </div>
    <div id="usersLoading" style="text-align:center; padding:30px; color:var(--muted);">Loading…</div>
    <div id="usersWrap" style="display:none; overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="usersBody"></tbody>
        </table>
    </div>
</div>

{{-- ===== WORKSPACES TAB ===== --}}
<div id="tab-workspaces" class="tab-pane">
    <div id="wsLoading" style="text-align:center; padding:30px; color:var(--muted);">Loading…</div>
    <div id="wsWrap"></div>
</div>

{{-- ===== ERROR LOG TAB ===== --}}
<div id="tab-errors" class="tab-pane">
    <div class="err-filter">
        <input class="search-input" type="search" id="errSearch" placeholder="Search errors…" oninput="filterErrors()">
        <select class="filter-select" id="errSource" onchange="loadErrors()">
            <option value="">All sources</option>
            <option value="server">Server</option>
            <option value="browser">Browser</option>
        </select>
        <button class="btn-sm danger" id="clearErrBtn" onclick="clearErrors()">Clear All</button>
    </div>
    <div id="errLoading" style="text-align:center; padding:30px; color:var(--muted); display:none;">Loading…</div>
    <div id="errWrap" style="overflow-x:auto;"></div>
</div>

{{-- ===== IMPERSONATION MODAL ===== --}}
<div class="overlay" id="impModal">
    <div class="modal-card">
        <div class="modal-head">
            <h2>Work as <span id="impName">User</span></h2>
            <button class="close-btn" onclick="closeImp()">&#x2715;</button>
        </div>
        <div class="modal-body">
            <div style="font-size:13px; color:var(--muted); background:rgba(168,85,247,.1); border:1px solid var(--purple-border); border-radius:8px; padding:12px;">
                Every action during this session is recorded in the audit log. The session ends automatically in 30 minutes.
            </div>
            <div>
                <label class="form-label" for="impReason">Reason <span style="color:var(--danger);">*</span></label>
                <input class="form-input" id="impReason" type="text" placeholder="e.g. Troubleshooting export issue for user" maxlength="200">
            </div>
            <div>
                <label class="form-label" for="impPassword">Your developer password <span style="color:var(--danger);">*</span></label>
                <input class="form-input" id="impPassword" type="password" placeholder="Enter your password to confirm">
            </div>
        </div>
        <div class="modal-foot">
            <button class="btn-sm" onclick="closeImp()">Cancel</button>
            <button class="btn-sm purple" id="impConfirmBtn" onclick="confirmImpersonate()">Start session</button>
        </div>
    </div>
</div>

{{-- ===== DELETE CONFIRM MODAL ===== --}}
<div class="overlay" id="delModal">
    <div class="modal-card">
        <div class="modal-head">
            <h2>Delete <span id="delName">User</span>?</h2>
            <button class="close-btn" onclick="document.getElementById('delModal').classList.remove('open')">&#x2715;</button>
        </div>
        <div class="modal-body">
            <p style="color:var(--muted); font-size:13.5px; margin:0;">This permanently removes the account and signs them out everywhere. Owned workspaces and audit history are kept. This cannot be undone.</p>
        </div>
        <div class="modal-foot">
            <button class="btn-sm" onclick="document.getElementById('delModal').classList.remove('open')">Cancel</button>
            <button class="btn-sm danger" id="delConfirmBtn" onclick="confirmDelete()">Delete account</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const BASE = '{{ url('/settings/developer/api') }}';
const USERS_LIST_URL = '{{ url('/settings/users/api/list') }}';

// ── Tab switching ──────────────────────────────────────────────────────────
function switchTab(name, btn) {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('tab-' + name).classList.add('active');
    if (name === 'workspaces' && !_wsFetched) fetchWorkspaces();
    if (name === 'errors' && !_errFetched) loadErrors();
}

// ── Toast ──────────────────────────────────────────────────────────────────
function toast(msg, ok = true) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'toast ' + (ok ? 'ok' : 'err');
    t.style.display = 'block';
    clearTimeout(t._t);
    t._t = setTimeout(() => { t.style.display = 'none'; }, 4000);
}

// ── Helpers ────────────────────────────────────────────────────────────────
function initials(name) {
    const p = name.trim().split(/\s+/);
    return ((p[0]?.[0] ?? '') + (p[1]?.[0] ?? '')).toUpperCase();
}
function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
async function api(method, path, body) {
    const opts = { method, headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', Accept: 'application/json' } };
    if (body) opts.body = JSON.stringify(body);
    const r = await fetch(BASE + path, opts);
    const json = await r.json().catch(() => ({}));
    return { ok: r.ok, status: r.status, data: json };
}

// ── Users ──────────────────────────────────────────────────────────────────
let _users = [], _impTarget = null, _delTarget = null;

async function fetchUsers() {
    const r = await fetch(USERS_LIST_URL, { headers: { Accept: 'application/json' } });
    const data = await r.json();
    _users = data.users ?? [];
    renderUsers();
    document.getElementById('usersLoading').style.display = 'none';
    document.getElementById('usersWrap').style.display = '';
}

function renderUsers() {
    const q = document.getElementById('userSearch').value.toLowerCase();
    const f = document.getElementById('userFilter').value;
    const rows = _users.filter(u => {
        if (q && !((u.name || u.display_name || '').toLowerCase().includes(q)) && !(u.email || '').toLowerCase().includes(q)) return false;
        if (f === 'dev' && !u.is_developer) return false;
        if (f === 'admin' && (!u.is_super_admin || u.is_developer)) return false;
        if (f === 'user' && (u.is_super_admin || u.is_developer)) return false;
        if (f === 'suspended' && u.status !== 'suspended') return false;
        return true;
    });

    const myId = '{{ auth()->id() }}';
    const tbody = document.getElementById('usersBody');
    if (!rows.length) { tbody.innerHTML = '<tr><td colspan="5" class="empty-state">No users match.</td></tr>'; return; }

    tbody.innerHTML = rows.map(u => {
        const name = u.display_name || u.name || 'Unknown';
        const ini = initials(name);
        const isDev = !!u.is_developer;
        const isAdmin = !!u.is_super_admin;
        const isSelf = u.id === myId;
        const rolePill = isDev
            ? '<span class="pill dev">Developer</span>'
            : (isAdmin ? '<span class="pill admin">Admin</span>' : '<span class="pill user">User</span>');
        const statusPill = u.status === 'active'
            ? '<span class="pill active">Active</span>'
            : '<span class="pill suspended">Suspended</span>';

        const canImp = !isSelf && !isDev && u.status === 'active';
        const canDev = !isSelf;
        const canDel = !isSelf;

        const impBtn = canImp ? `<button class="btn-sm purple" onclick="openImp('${u.id}','${name.replace(/'/g,"\\'")}')">Impersonate</button>` : '';
        const devBtn = canDev ? `<button class="btn-sm" onclick="toggleDev('${u.id}',this)" data-dev="${isDev}">${isDev ? 'Revoke Dev' : 'Grant Dev'}</button>` : '';
        const delBtn = canDel ? `<button class="btn-sm danger" onclick="openDel('${u.id}','${name.replace(/'/g,"\\'")}')">Delete</button>` : '';

        return `<tr data-id="${u.id}">
            <td><div class="user-info"><div class="user-avatar ${isDev ? 'dev' : ''}">${ini}</div><div><div style="font-weight:600;">${name}</div><div style="font-size:11.5px; color:var(--muted);">${u.email}</div></div></div></td>
            <td>${rolePill}</td>
            <td>${statusPill}</td>
            <td style="color:var(--muted); font-size:12px;">${u.created_at || '—'}</td>
            <td><div class="action-row">${impBtn}${devBtn}${delBtn}</div></td>
        </tr>`;
    }).join('');
}
function filterUsers() { renderUsers(); }

async function toggleDev(userId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';
    const { ok, data } = await api('POST', `/users/${userId}/toggle-developer`);
    if (ok) {
        const isDev = !!data.is_developer;
        const u = _users.find(x => x.id === userId);
        if (u) { u.is_developer = isDev; u.is_super_admin = isDev ? true : u.is_super_admin; }
        toast(data.message);
        renderUsers();
    } else {
        toast(data.message || 'Failed.', false);
        btn.disabled = false;
        btn.innerHTML = btn.dataset.dev === 'true' ? 'Revoke Dev' : 'Grant Dev';
    }
}

// Impersonation
function openImp(userId, name) {
    _impTarget = userId;
    document.getElementById('impName').textContent = name;
    document.getElementById('impReason').value = '';
    document.getElementById('impPassword').value = '';
    document.getElementById('impModal').classList.add('open');
    setTimeout(() => document.getElementById('impReason').focus(), 50);
}
function closeImp() { document.getElementById('impModal').classList.remove('open'); _impTarget = null; }
async function confirmImpersonate() {
    const reason = document.getElementById('impReason').value.trim();
    const password = document.getElementById('impPassword').value;
    if (reason.length < 5) { toast('Please enter a reason (at least 5 characters).', false); return; }
    if (!password) { toast('Please enter your password.', false); return; }
    const btn = document.getElementById('impConfirmBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';
    const { ok, data } = await api('POST', `/users/${_impTarget}/impersonate`, { reason, password });
    if (ok && data.redirect) {
        toast('Session started. Redirecting…');
        setTimeout(() => { window.location.href = data.redirect; }, 800);
    } else {
        toast(data.message || 'Failed.', false);
        btn.disabled = false; btn.textContent = 'Start session';
    }
}

// Delete
function openDel(userId, name) {
    _delTarget = userId;
    document.getElementById('delName').textContent = name;
    document.getElementById('delModal').classList.add('open');
}
async function confirmDelete() {
    const btn = document.getElementById('delConfirmBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';
    const { ok, data } = await api('DELETE', `/users/${_delTarget}`);
    if (ok) {
        _users = _users.filter(u => u.id !== _delTarget);
        document.getElementById('delModal').classList.remove('open');
        toast(data.message);
        renderUsers();
    } else {
        toast(data.message || 'Failed.', false);
    }
    btn.disabled = false; btn.textContent = 'Delete account';
}

// PLAUD samples
async function loadPlaudSamples() {
    const btn = document.getElementById('samplesBtn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner"></span>';
    const { ok, data } = await api('POST', '/plaud-samples');
    toast(data.message || (ok ? 'Done.' : 'Failed.'), ok);
    btn.disabled = false; btn.textContent = 'Load PLAUD Samples';
}

// ── Workspaces ──────────────────────────────────────────────────────────────
let _wsFetched = false;
async function fetchWorkspaces() {
    _wsFetched = true;
    document.getElementById('wsLoading').style.display = '';
    const { ok, data } = await api('GET', '/workspaces');
    document.getElementById('wsLoading').style.display = 'none';
    if (!ok) { document.getElementById('wsWrap').innerHTML = '<p style="color:var(--danger); padding:20px;">Failed to load workspaces.</p>'; return; }
    renderWorkspaces(data.workspaces || []);
}

function renderWorkspaces(list) {
    if (!list.length) {
        document.getElementById('wsWrap').innerHTML = '<div class="empty-state">No workspaces yet.</div>';
        return;
    }
    document.getElementById('wsWrap').innerHTML = list.map((w, i) => {
        const docs = (w.documents || []).map(d =>
            `<tr><td>${d.title || '(untitled)'}</td><td><span class="pill ${d.source_type === 'plaud' ? 'active' : 'user'}">${d.source_type}</span></td><td>${d.status}</td><td style="color:var(--muted);">${d.segments_count} seg</td><td style="color:var(--muted); font-size:12px;">${fmtDate(d.created_at)}</td></tr>`
        ).join('');
        const docsTable = w.documents_count > 0 ? `<div class="ws-docs" id="wsDocs${i}"><table><thead><tr><th>Title</th><th>Source</th><th>Status</th><th>Segments</th><th>Date</th></tr></thead><tbody>${docs}</tbody></table></div>` : '';
        return `<div class="ws-card">
            <div class="ws-card-head" onclick="toggleWsDocs(${i})">
                <div>
                    <div style="font-weight:700; font-size:14px;">${w.name}</div>
                    <div class="ws-meta">
                        <span>Owner: ${w.owner?.name ?? '—'}</span>
                        <span>Type: ${w.type}</span>
                        <span>${w.members_count} member${w.members_count !== 1 ? 's' : ''}</span>
                        <span>${w.documents_count} doc${w.documents_count !== 1 ? 's' : ''}</span>
                        <span style="color:var(--muted);">${fmtDate(w.created_at)}</span>
                    </div>
                </div>
                <span style="color:var(--muted); font-size:18px;" id="wsChev${i}">${w.documents_count > 0 ? '▸' : ''}</span>
            </div>
            ${docsTable}
        </div>`;
    }).join('');
}
function toggleWsDocs(i) {
    const el = document.getElementById('wsDocs' + i);
    if (!el) return;
    const open = el.classList.toggle('open');
    document.getElementById('wsChev' + i).textContent = open ? '▾' : '▸';
}

// ── Error Log ──────────────────────────────────────────────────────────────
let _errFetched = false, _errors = [];
async function loadErrors() {
    _errFetched = true;
    const src = document.getElementById('errSource').value;
    document.getElementById('errLoading').style.display = '';
    document.getElementById('errWrap').innerHTML = '';
    const path = '/errors' + (src ? '?source=' + src : '');
    const { ok, data } = await api('GET', path);
    document.getElementById('errLoading').style.display = 'none';
    if (!ok) { document.getElementById('errWrap').innerHTML = '<p style="color:var(--danger); padding:20px;">Failed to load error log.</p>'; return; }
    _errors = data.errors || [];
    renderErrors();
    document.getElementById('metricErrors').textContent = _errors.length;
}

function renderErrors() {
    const q = document.getElementById('errSearch').value.toLowerCase();
    const rows = _errors.filter(e => !q || (e.message || '').toLowerCase().includes(q) || (e.path || '').toLowerCase().includes(q) || (e.reference || '').toLowerCase().includes(q));
    if (!rows.length) {
        document.getElementById('errWrap').innerHTML = '<div class="empty-state">No errors logged.</div>';
        return;
    }
    document.getElementById('errWrap').innerHTML = `<table>
        <thead><tr><th>Ref</th><th>Source</th><th>Message</th><th>Path</th><th>User</th><th>When</th></tr></thead>
        <tbody>${rows.map((e, i) => {
            const detail = [e.exception_class, e.location, e.user_agent, e.context ? JSON.stringify(e.context, null, 2) : ''].filter(Boolean).join('\n');
            return `<tr class="err-row">
                <td><span class="err-ref">${e.reference || '—'}</span>${detail ? `<br><span class="err-expand" onclick="toggleErr(${i})">▸ details</span><pre class="err-detail" id="errD${i}">${detail.replace(/</g,'&lt;')}</pre>` : ''}</td>
                <td><span class="pill ${e.source}">${e.source}</span></td>
                <td class="err-msg" title="${(e.message || '').replace(/"/g,'&quot;')}">${e.message || '—'}</td>
                <td style="font-size:12px; color:var(--muted);">${(e.method || '') + ' ' + (e.path || '—')}</td>
                <td style="font-size:12px; color:var(--muted);">${e.user || '—'}${e.impersonated_by ? '<br><span style="font-size:11px; color:var(--purple);">via ' + e.impersonated_by + '</span>' : ''}</td>
                <td style="font-size:12px; color:var(--muted); white-space:nowrap;">${fmtDate(e.created_at)}</td>
            </tr>`;
        }).join('')}</tbody>
    </table>`;
}
function filterErrors() { renderErrors(); }
function toggleErr(i) {
    const el = document.getElementById('errD' + i);
    const btn = el?.previousElementSibling;
    if (!el) return;
    const open = el.style.display === 'block';
    el.style.display = open ? 'none' : 'block';
    if (btn) btn.textContent = open ? '▸ details' : '▾ details';
}
async function clearErrors() {
    if (!confirm('Delete all error log entries?')) return;
    const btn = document.getElementById('clearErrBtn');
    btn.disabled = true;
    const { ok, data } = await api('DELETE', '/errors');
    if (ok) { _errors = []; renderErrors(); toast(`Cleared ${data.deleted} entries.`); document.getElementById('metricErrors').textContent = '0'; }
    else { toast(data.message || 'Failed.', false); }
    btn.disabled = false;
}

// ── Boot ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', fetchUsers);
</script>
@endsection
