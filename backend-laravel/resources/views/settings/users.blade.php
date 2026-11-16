<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    @include('partials.pwa')
    <meta name="theme-color" content="#18A879">
    <title>PLAUD Companion — User Management</title>
    <style>
        :root {
            --navy: #101B33;
            --navy-light: #182747;
            --navy-border: #253861;
            --emerald: #18A879;
            --emerald-hover: #149168;
            --bg: #0B1324;
            --card: #13203B;
            --text: #F1F5F9;
            --muted: #94A3B8;
            --danger: #EF4444;
            --warn: #F59E0B;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Inter, sans-serif;
            background: linear-gradient(160deg, var(--bg) 0%, var(--navy) 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 28px 20px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            gap: 20px;
        }
        .topbar {
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 14px;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .brand { display: flex; align-items: center; gap: 12px; }
        .badge {
            width: 36px;
            height: 36px;
            border-radius: 9px;
            background: var(--emerald);
            color: #07131E;
            font-weight: 800;
            display: grid;
            place-items: center;
        }
        .topbar h1 { margin: 0; font-size: 18px; }
        .topbar p { margin: 2px 0 0; font-size: 12px; color: var(--muted); }
        .nav-tabs {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .nav-tab {
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            border: 1px solid transparent;
            transition: all 0.2s;
        }
        .nav-tab:hover {
            color: var(--text);
            background: rgba(255, 255, 255, 0.05);
        }
        .nav-tab.active {
            color: #fff;
            background: rgba(24, 168, 121, 0.2);
            border-color: var(--emerald);
        }
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
        }
        @media (max-width: 768px) {
            .metrics-grid { grid-template-columns: repeat(2, 1fr); }
        }
        .metric-card {
            background: var(--card);
            border: 1px solid var(--navy-border);
            border-radius: 12px;
            padding: 16px 18px;
        }
        .metric-card span { font-size: 12px; color: var(--muted); display: block; margin-bottom: 4px; }
        .metric-card strong { font-size: 24px; font-weight: 800; color: #fff; }
        .card {
            background: var(--card);
            border: 1px solid var(--navy-border);
            border-radius: 14px;
            padding: 22px;
        }
        .search-row {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
            flex-wrap: wrap;
        }
        .search-input {
            flex: 1;
            min-width: 240px;
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            font-size: 13.5px;
            outline: none;
        }
        .search-input:focus { border-color: var(--emerald); }
        .filter-select {
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 8px;
            padding: 10px 14px;
            color: var(--text);
            font-size: 13px;
            outline: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13.5px;
        }
        th {
            text-align: left;
            padding: 10px 14px;
            color: var(--muted);
            font-size: 11.5px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-bottom: 1px solid var(--navy-border);
        }
        td {
            padding: 14px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            vertical-align: middle;
        }
        tr:hover td { background: rgba(255, 255, 255, 0.02); }
        .user-info { display: flex; align-items: center; gap: 10px; }
        .user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            background: linear-gradient(135deg, #18A879 0%, #104C39 100%);
            display: grid;
            place-items: center;
            font-weight: 700;
            font-size: 13px;
            color: #fff;
            flex-shrink: 0;
        }
        .pill {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 9px;
            border-radius: 999px;
            display: inline-block;
        }
        .pill.active { background: rgba(24, 168, 121, 0.16); color: var(--emerald); border: 1px solid rgba(24, 168, 121, 0.35); }
        .pill.suspended { background: rgba(239, 68, 68, 0.16); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.35); }
        .pill.admin { background: rgba(168, 85, 247, 0.16); color: #C084FC; border: 1px solid rgba(168, 85, 247, 0.35); }
        .pill.user { background: rgba(148, 163, 184, 0.12); color: var(--muted); }
        .btn {
            border: none;
            border-radius: 7px;
            padding: 7px 12px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-suspend { background: rgba(239, 68, 68, 0.18); color: #FCA5A5; border: 1px solid rgba(239, 68, 68, 0.4); }
        .btn-suspend:hover { background: rgba(239, 68, 68, 0.35); }
        .btn-activate { background: rgba(24, 168, 121, 0.18); color: #6EE7B7; border: 1px solid rgba(24, 168, 121, 0.4); }
        .btn-activate:hover { background: rgba(24, 168, 121, 0.35); }
        .btn-promote { background: var(--navy-light); color: var(--text); border: 1px solid var(--navy-border); }
        .btn-promote:hover { border-color: #A855F7; color: #C084FC; }
        .toast {
            position: fixed;
            bottom: 24px;
            right: 24px;
            padding: 12px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
            display: none;
            z-index: 1000;
        }
        .toast.success { background: #064E3B; border: 1px solid var(--emerald); color: #D1FAE5; }
        .toast.error { background: #7F1D1D; border: 1px solid var(--danger); color: #FEE2E2; }

        /* Profile Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(11, 19, 36, 0.85);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1050;
            padding: 20px;
        }
        .modal-card {
            background: var(--card);
            border: 1px solid var(--navy-border);
            border-radius: 16px;
            width: 100%;
            max-width: 640px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.6);
            display: flex;
            flex-direction: column;
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--navy-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .modal-header h2 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }
        .modal-close-btn {
            background: transparent;
            border: none;
            color: var(--muted);
            font-size: 22px;
            cursor: pointer;
            line-height: 1;
            padding: 4px;
        }
        .modal-close-btn:hover { color: var(--text); }
        .modal-body {
            padding: 22px 24px;
            display: grid;
            gap: 20px;
        }
        .modal-profile-header {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .modal-avatar {
            width: 52px;
            height: 52px;
            border-radius: 12px;
            background: linear-gradient(135deg, #18A879 0%, #104C39 100%);
            display: grid;
            place-items: center;
            font-size: 18px;
            font-weight: 800;
            color: #fff;
            flex-shrink: 0;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
        }
        .stat-box {
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 10px;
            padding: 12px 14px;
            text-align: center;
        }
        .stat-box span { font-size: 11.5px; color: var(--muted); display: block; margin-bottom: 4px; }
        .stat-box strong { font-size: 18px; font-weight: 800; color: #fff; }
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 8px;
        }
        .list-card {
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 12.5px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 6px;
        }
        .list-card:last-child { margin-bottom: 0; }
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="brand">
            <div class="badge">PC</div>
            <div>
                <h1>User Directory &amp; Access Controls</h1>
                <p>Super-Admin User Management Console &bull; Instant Suspension Controls</p>
            </div>
        </div>
        <div class="nav-tabs">
            <a href="{{ route('settings.users') }}" class="nav-tab active">Users</a>
            <a href="{{ route('settings.updates') }}" class="nav-tab">System Updates</a>
            <a href="{{ route('profile.show') }}" class="nav-tab">My Profile</a>
            <a href="{{ route('app.home') }}" class="nav-tab">&larr; Workspace</a>
            <form method="POST" action="{{ route('logout') }}" style="display:inline; margin:0;">
                @csrf
                <button type="submit" class="nav-tab" style="background:none; border:none; cursor:pointer;">Sign Out</button>
            </form>
        </div>
    </div>

    @if (session('status'))
        <div class="card" role="status" style="margin-bottom:16px; border-color:var(--emerald); color:var(--emerald); font-weight:600;">{{ session('status') }}</div>
    @endif

    <!-- METRICS OVERVIEW -->
    <div class="metrics-grid">
        <div class="metric-card">
            <span>Total Accounts</span>
            <strong id="metricTotal">{{ $metrics['total'] }}</strong>
        </div>
        <div class="metric-card">
            <span>Active Accounts</span>
            <strong id="metricActive" style="color:var(--emerald);">{{ $metrics['active'] }}</strong>
        </div>
        <div class="metric-card">
            <span>Suspended Accounts</span>
            <strong id="metricSuspended" style="color:var(--danger);">{{ $metrics['suspended'] }}</strong>
        </div>
        <div class="metric-card">
            <span>Super Administrators</span>
            <strong id="metricAdmins" style="color:#C084FC;">{{ $metrics['super_admins'] }}</strong>
        </div>
    </div>

    <!-- USERS DIRECTORY TABLE -->
    <div class="card">
        <div class="search-row">
            <input id="searchInput" type="text" class="search-input" placeholder="Search users by name, email..." oninput="debounceSearch()">
            <select id="statusFilter" class="filter-select" onchange="loadUsers()">
                <option value="">All Statuses</option>
                <option value="active">Active Only</option>
                <option value="suspended">Suspended Only</option>
            </select>
            <button class="btn btn-promote" onclick="loadUsers()">↻ Refresh</button>
        </div>

        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Workspaces</th>
                        <th>Registered</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="userTableBody">
                    <tr>
                        <td colspan="6" style="text-align:center; padding:32px; color:var(--muted);">Loading users...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    @if ($isDeveloper)
    <!-- DEVELOPER: WORKSPACE DOCUMENT LISTS (no transcript content) -->
    <div class="card" style="margin-top:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0; font-size:16px;">Workspaces &amp; documents</h2>
                <p style="margin:4px 0 0; font-size:12.5px; color:var(--muted);">Titles, sources, sizes and dates only. Transcript text is never shown here; use <b>Work as</b> to open someone's documents.</p>
            </div>
            <button class="btn btn-promote" data-dev="load-workspaces">&#8635; Refresh</button>
        </div>
        <div id="devWorkspaces" style="margin-top:12px; font-size:13px; color:var(--muted);">Loading&hellip;</div>
    </div>

    <!-- DEVELOPER: PLAUD SAMPLE DATA -->
    <div class="card" style="margin-top:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0; font-size:16px;">Plaud sample recordings</h2>
                <p style="margin:4px 0 0; font-size:12.5px; color:var(--muted);">Loads a few <b>sample</b> recordings in Plaud's real format into your own workspace, so you can see exactly what "Connect my Plaud account" produces — no device or Plaud login needed. Sample data, clearly not real.</p>
            </div>
            <button class="btn btn-promote" data-dev="load-plaud-samples">&#127908; Load samples</button>
        </div>
        <div id="devPlaudSamples" style="margin-top:12px; font-size:13px; color:var(--muted);"></div>
    </div>

    <!-- DEVELOPER: DEBUG LOG -->
    <div class="card" style="margin-top:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap;">
            <div>
                <h2 style="margin:0; font-size:16px;">Debug log</h2>
                <p style="margin:4px 0 0; font-size:12.5px; color:var(--muted);">Server and browser errors with the reference shown to the user. Kept 30 days. No transcript text, request bodies or secrets are stored.</p>
            </div>
            <div style="display:flex; gap:8px;">
                <select id="errSource" class="filter-select"><option value="">All</option><option value="server">Server</option><option value="browser">Browser</option></select>
                <button class="btn btn-promote" data-dev="load-errors">&#8635; Refresh</button>
                <button class="btn btn-suspend" data-dev="clear-errors">Clear</button>
            </div>
        </div>
        <div id="devErrors" style="margin-top:12px; font-size:13px; color:var(--muted);">Loading&hellip;</div>
    </div>

    <!-- DEVELOPER: IMPERSONATION CONFIRMATION -->
    <div id="impersonateModal" class="modal-overlay" data-dev="close-imp-overlay">
        <div class="modal-card" style="max-width:480px;">
            <div class="modal-header">
                <h2>Work as <span id="impName"></span></h2>
                <button class="modal-close-btn" data-dev="close-imp">&times;</button>
            </div>
            <form id="impersonateForm" style="padding:18px; display:flex; flex-direction:column; gap:12px;">
                <p style="margin:0; font-size:13px; color:var(--muted);">You will use the app as this person, including their documents, for up to 30 minutes. A banner stays on screen, admin pages are locked, and every action is recorded in the audit log with your name.</p>
                <label style="display:flex; flex-direction:column; gap:4px; font-size:12.5px; font-weight:600;">Reason (saved in the audit log)
                    <input id="impReason" class="search-input" type="text" required minlength="5" maxlength="200" placeholder="e.g. Preparing the board export at Morgan's request">
                </label>
                <label style="display:flex; flex-direction:column; gap:4px; font-size:12.5px; font-weight:600;">Your password
                    <input id="impPassword" class="search-input" type="password" required autocomplete="current-password">
                </label>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn btn-promote" data-dev="close-imp">Cancel</button>
                    <button type="submit" class="btn btn-activate">Start session</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    <footer style="margin-top:16px; padding:16px 20px; border-top:1px solid var(--navy-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; font-size:12px; color:var(--muted);">
        <div><strong>PLAUD Companion</strong> &bull; On-Device Encryption &amp; Access Control</div>
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <span>Developed by <a href="https://www.iqbalmahmud.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Md. Iqbal Mahmud</a></span>
            <span style="opacity:0.4;">&bull;</span>
            <span>Technical Advisor: <a href="https://www.dawncsimmons.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Dawn C Simmons</a></span>
            <span style="opacity:0.4;">&bull;</span>
            <span>Brought to you by <a href="https://www.aiinfos.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">AI Infos</a></span>
        </div>
    </footer>
</div>

<!-- USER PROFILE INSPECTION MODAL -->
<div id="profileModal" class="modal-overlay" onclick="handleModalOverlayClick(event)">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h2>User Profile &amp; Telemetry Inspection</h2>
            <button class="modal-close-btn" onclick="closeProfileModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div class="modal-profile-header">
                <div id="modalAvatar" class="modal-avatar">--</div>
                <div style="flex:1;">
                    <div id="modalDisplayName" style="font-size:16px; font-weight:800; color:#fff;">User Name</div>
                    <div id="modalEmail" style="font-size:13px; color:var(--muted); margin:2px 0 6px;">user@example.com</div>
                    <div style="display:flex; gap:8px; align-items:center;">
                        <span id="modalRoleBadge" class="pill user">User</span>
                        <span id="modalStatusBadge" class="pill active">Active</span>
                        <span id="modalRegisteredDate" style="font-size:11.5px; color:var(--muted); margin-left:auto;"></span>
                    </div>
                </div>
            </div>

            <!-- Profile Summary Metrics -->
            <div class="stat-grid">
                <div class="stat-box">
                    <span>Workspaces</span>
                    <strong id="modalStatWorkspaces">0</strong>
                </div>
                <div class="stat-box">
                    <span>Transcripts</span>
                    <strong id="modalStatTranscripts">0</strong>
                </div>
                <div class="stat-box">
                    <span>Paired BLE Devices</span>
                    <strong id="modalStatDevices">0</strong>
                </div>
            </div>

            <!-- Workspace Affiliations -->
            <div>
                <h3 class="section-title">Owned Workspaces</h3>
                <div id="modalWorkspacesList">
                    <div style="font-size:12.5px; color:var(--muted); font-style:italic;">No workspaces owned.</div>
                </div>
            </div>

            <!-- BLE Hardware Device Activity -->
            <div>
                <h3 class="section-title">BLE Device Activity &amp; Telemetry</h3>
                <div id="modalDevicesList">
                    <div style="font-size:12.5px; color:var(--muted); font-style:italic;">No paired BLE hardware devices recorded.</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
const USERS_API_BASE = '{{ url('/settings/users/api') }}';
const DEV_API_BASE = '{{ url('/settings/developer/api') }}';
const IS_DEVELOPER = @json($isDeveloper);
let usersById = {};
let searchTimer = null;

function showToast(msg, isError = false) {
    const t = document.getElementById('toast');
    t.className = 'toast ' + (isError ? 'error' : 'success');
    t.innerText = msg;
    t.style.display = 'block';
    setTimeout(() => { t.style.display = 'none'; }, 4000);
}

function debounceSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadUsers, 250);
}

async function loadUsers() {
    const q = document.getElementById('searchInput').value.trim();
    const status = document.getElementById('statusFilter').value;
    const url = `${USERS_API_BASE}/list?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}`;

    try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!res.ok) throw new Error('Failed to load user list');
        const data = await res.json();
        renderUsers(data.users || []);
    } catch (err) {
        showToast(err.message, true);
    }
}

function renderUsers(users) {
    const tbody = document.getElementById('userTableBody');
    usersById = {};
    users.forEach(u => { usersById[u.id] = u; });
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--muted);">No registered users match your criteria.</td></tr>';
        return;
    }

    // Buttons carry only data attributes; user-supplied names are never placed inside inline JavaScript.
    const btn = (action, id, cls, label) => `<button class="btn ${cls}" data-action="${action}" data-id="${escapeHtml(id)}">${label}</button>`;
    tbody.innerHTML = users.map(u => {
        const initials = escapeHtml((u.display_name || u.email || 'U').substring(0, 2).toUpperCase());
        const rolePill = u.is_developer ? '<span class="pill admin">Developer</span>'
            : (u.is_super_admin ? '<span class="pill admin">Super Admin</span>' : '<span class="pill user">User</span>');
        const statusPill = u.status === 'active' ? '<span class="pill active">Active</span>' : '<span class="pill suspended">Suspended</span>';
        const buttons = [btn('profile', u.id, 'btn-promote', 'Profile')];

        if (u.is_current_user) {
            buttons.push('<span style="font-size:12px; color:var(--muted); font-style:italic;">Your Account</span>');
        } else if (u.can_manage) {
            if (IS_DEVELOPER && !u.is_developer && u.status === 'active') buttons.push(btn('impersonate', u.id, 'btn-activate', 'Work as'));
            buttons.push(u.status === 'active' ? btn('status', u.id, 'btn-suspend', 'Suspend') : btn('status', u.id, 'btn-activate', 'Reactivate'));
            if (!u.is_developer) buttons.push(btn('admin', u.id, 'btn-promote', u.is_super_admin ? 'Remove Admin' : 'Make Admin'));
            buttons.push(btn('signout', u.id, 'btn-promote', 'Sign out everywhere'));
            if (u.status === 'active') buttons.push(btn('reset', u.id, 'btn-promote', 'Password reset'));
            if (IS_DEVELOPER) {
                buttons.push(btn('developer', u.id, 'btn-promote', u.is_developer ? 'Remove Developer' : 'Make Developer'));
                buttons.push(btn('delete', u.id, 'btn-suspend', 'Delete'));
            }
        } else {
            buttons.push('<span style="font-size:12px; color:var(--muted); font-style:italic;">Developer account</span>');
        }

        return `
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar">${initials}</div>
                        <div>
                            <div style="font-weight:700;">${escapeHtml(u.display_name)}</div>
                            <div style="font-size:12px; color:var(--muted);">${escapeHtml(u.email)}</div>
                        </div>
                    </div>
                </td>
                <td>${rolePill}</td>
                <td>${statusPill}</td>
                <td>${Number(u.workspaces_count) || 0} workspace(s)</td>
                <td style="color:var(--muted); font-size:12.5px;">${escapeHtml(u.created_at)}</td>
                <td style="text-align:right;"><div style="display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap;">${buttons.join(' ')}</div></td>
            </tr>
        `;
    }).join('');
}

async function apiCall(url, method = 'POST', body = null) {
    const res = await fetch(url, {
        method,
        headers: Object.assign({ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }, body ? { 'Content-Type': 'application/json' } : {}),
        body: body ? JSON.stringify(body) : null,
        credentials: 'same-origin'
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* non-JSON response */ }
    if (!res.ok) {
        const firstError = data.errors ? Object.values(data.errors)[0][0] : null;
        throw new Error(data.message || data.detail || firstError || `Request failed (${res.status})`);
    }
    return data;
}

async function runUserAction(action, id) {
    const u = usersById[id];
    if (!u) return;
    if (action === 'profile') return openUserProfile(id);
    if (action === 'impersonate') return openImpersonate(id);
    const name = u.display_name || u.email;
    const confirmText = {
        status: `${u.status === 'active' ? 'Suspend' : 'Reactivate'} ${name}?` + (u.status === 'active' ? ' They will be signed out everywhere.' : ''),
        admin: `${u.is_super_admin ? 'Remove admin rights from' : 'Give admin rights to'} ${name}?`,
        signout: `Sign ${name} out on every browser, phone and app?`,
        reset: `Send a password reset link to ${u.email}?`,
        developer: u.is_developer ? `Remove the developer role from ${name}?` : `Make ${name} a developer?`,
        delete: `Delete ${name}? They are signed out everywhere and can no longer sign in. Their workspaces and history are kept.`
    }[action];
    const urls = {
        status: [`${USERS_API_BASE}/${id}/toggle-status`, 'POST'],
        admin: [`${USERS_API_BASE}/${id}/toggle-admin`, 'POST'],
        signout: [`${USERS_API_BASE}/${id}/sign-out-everywhere`, 'POST'],
        reset: [`${USERS_API_BASE}/${id}/password-reset`, 'POST'],
        developer: [`${DEV_API_BASE}/users/${id}/toggle-developer`, 'POST'],
        delete: [`${DEV_API_BASE}/users/${id}`, 'DELETE']
    }[action];
    if (!urls || !confirm(confirmText)) return;
    try {
        const data = await apiCall(urls[0], urls[1]);
        showToast(data.message || 'Done.');
        if (data.reset_url) window.prompt('Development mode: copy this password reset link (expires in 60 minutes).', data.reset_url);
        loadUsers();
    } catch (err) {
        showToast(err.message, true);
    }
}

let impersonateId = null;
function openImpersonate(id) {
    impersonateId = id;
    document.getElementById('impName').textContent = usersById[id].display_name || usersById[id].email;
    document.getElementById('impReason').value = '';
    document.getElementById('impPassword').value = '';
    document.getElementById('impersonateModal').style.display = 'flex';
    document.getElementById('impReason').focus();
}
function closeImpersonate() {
    document.getElementById('impersonateModal').style.display = 'none';
    document.getElementById('impPassword').value = '';
}

async function loadWorkspaces() {
    const box = document.getElementById('devWorkspaces');
    try {
        const data = await apiCall(`${DEV_API_BASE}/workspaces`, 'GET');
        if (!data.workspaces.length) { box.textContent = 'No workspaces yet.'; return; }
        box.innerHTML = data.workspaces.map(w => `
            <details style="border:1px solid var(--navy-border); border-radius:10px; padding:10px 12px; margin-bottom:8px;">
                <summary style="cursor:pointer; color:var(--text);"><b>${escapeHtml(w.name)}</b>
                    <span style="color:var(--muted);"> &middot; owner ${escapeHtml(w.owner ? w.owner.name : '-')} &middot; ${Number(w.members_count) || 0} member(s) &middot; ${Number(w.documents_count) || 0} document(s)</span></summary>
                <div style="overflow-x:auto; margin-top:8px;"><table>
                    <thead><tr><th>Document</th><th>Source</th><th>Status</th><th>Segments</th><th>Created</th></tr></thead>
                    <tbody>${w.documents.length ? w.documents.map(d => `<tr><td>${escapeHtml(d.title)}</td><td>${escapeHtml(d.source_type)}</td><td>${escapeHtml(d.status)}</td><td>${Number(d.segments_count) || 0}</td><td>${escapeHtml((d.created_at || '').slice(0, 16).replace('T', ' '))}</td></tr>`).join('')
                        : '<tr><td colspan="5" style="color:var(--muted);">No documents.</td></tr>'}</tbody>
                </table></div>
            </details>`).join('');
    } catch (err) { box.textContent = err.message; }
}

async function loadErrors() {
    const box = document.getElementById('devErrors');
    const source = document.getElementById('errSource').value;
    try {
        const data = await apiCall(`${DEV_API_BASE}/errors${source ? '?source=' + encodeURIComponent(source) : ''}`, 'GET');
        if (!data.errors.length) { box.textContent = 'No errors recorded.'; return; }
        box.innerHTML = data.errors.map(e => `
            <details style="border:1px solid var(--navy-border); border-radius:10px; padding:10px 12px; margin-bottom:8px;">
                <summary style="cursor:pointer; color:var(--text);"><span class="pill ${e.source === 'server' ? 'suspended' : 'admin'}">${escapeHtml(e.source)}</span>
                    <code>${escapeHtml(e.reference)}</code> ${escapeHtml(String(e.message).slice(0, 140))}
                    <span style="color:var(--muted);"> &middot; ${escapeHtml((e.created_at || '').slice(0, 19).replace('T', ' '))}</span></summary>
                <div style="margin-top:8px; display:grid; grid-template-columns:140px 1fr; gap:4px 12px; font-size:12.5px;">
                    <span>Where</span><span>${escapeHtml(e.location || '-')}</span>
                    <span>Request</span><span>${escapeHtml((e.method || '') + ' ' + (e.path || ''))}</span>
                    <span>Type</span><span>${escapeHtml(e.exception_class || '-')}</span>
                    <span>User</span><span>${escapeHtml(e.user || '-')}${e.impersonated_by ? ' (developer ' + escapeHtml(e.impersonated_by) + ' acting as this user)' : ''}</span>
                    <span>Browser</span><span>${escapeHtml(e.user_agent || '-')}</span>
                </div>
                ${e.context && e.context.stack ? `<pre style="white-space:pre-wrap; font-size:11.5px; margin:8px 0 0; color:var(--muted);">${escapeHtml(e.context.stack)}</pre>` : ''}
            </details>`).join('');
    } catch (err) { box.textContent = err.message; }
}

document.addEventListener('click', (ev) => {
    const actionBtn = ev.target.closest('[data-action][data-id]');
    if (actionBtn) { runUserAction(actionBtn.dataset.action, actionBtn.dataset.id); return; }
    const dev = ev.target.closest('[data-dev]');
    if (!dev) return;
    const what = dev.dataset.dev;
    if (what === 'load-workspaces') loadWorkspaces();
    else if (what === 'load-plaud-samples') {
        const box = document.getElementById('devPlaudSamples');
        dev.disabled = true; box.textContent = 'Loading sample recordings…';
        apiCall(`${DEV_API_BASE}/plaud-samples`, 'POST')
            .then(d => { box.textContent = d.message; showToast(d.message); loadWorkspaces(); })
            .catch(e => { box.textContent = e.message; showToast(e.message, true); })
            .finally(() => { dev.disabled = false; });
    }
    else if (what === 'load-errors') loadErrors();
    else if (what === 'clear-errors') {
        if (confirm('Delete every entry in the debug log?')) apiCall(`${DEV_API_BASE}/errors`, 'DELETE').then(() => { showToast('Debug log cleared.'); loadErrors(); }).catch(e => showToast(e.message, true));
    } else if (what === 'close-imp' || (what === 'close-imp-overlay' && ev.target === dev)) closeImpersonate();
});

if (IS_DEVELOPER) {
    document.getElementById('errSource').addEventListener('change', loadErrors);
    document.getElementById('impersonateForm').addEventListener('submit', async (ev) => {
        ev.preventDefault();
        try {
            const data = await apiCall(`${DEV_API_BASE}/users/${impersonateId}/impersonate`, 'POST', {
                reason: document.getElementById('impReason').value.trim(),
                password: document.getElementById('impPassword').value
            });
            window.location.href = data.redirect;
        } catch (err) {
            showToast(err.message, true);
        }
    });
    document.addEventListener('DOMContentLoaded', () => { loadWorkspaces(); loadErrors(); });
}

function handleModalOverlayClick(event) {
    if (event.target === document.getElementById('profileModal')) {
        closeProfileModal();
    }
}

function closeProfileModal() {
    const modal = document.getElementById('profileModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

async function openUserProfile(userId) {
    try {
        const res = await fetch(`${USERS_API_BASE}/${userId}/profile`, {
            headers: { 'Accept': 'application/json' }
        });
        if (!res.ok) {
            const errData = await res.json().catch(() => ({}));
            throw new Error(errData.message || 'Failed to load user profile');
        }
        const profile = await res.json();

        // Safe DOM population
        const initials = (profile.display_name || profile.email || 'U').substring(0, 2).toUpperCase();
        document.getElementById('modalAvatar').textContent = initials;
        document.getElementById('modalDisplayName').textContent = profile.display_name || 'N/A';
        document.getElementById('modalEmail').textContent = profile.email || 'N/A';
        document.getElementById('modalRegisteredDate').textContent = profile.created_at ? `Joined ${profile.created_at}` : '';

        // Badges
        const roleBadge = document.getElementById('modalRoleBadge');
        if (profile.is_super_admin) {
            roleBadge.className = 'pill admin';
            roleBadge.textContent = 'Super Admin';
        } else {
            roleBadge.className = 'pill user';
            roleBadge.textContent = 'User';
        }

        const statusBadge = document.getElementById('modalStatusBadge');
        if (profile.status === 'active') {
            statusBadge.className = 'pill active';
            statusBadge.textContent = 'Active';
        } else {
            statusBadge.className = 'pill suspended';
            statusBadge.textContent = 'Suspended';
        }

        // Metrics
        document.getElementById('modalStatWorkspaces').textContent = profile.workspaces_count ?? (profile.workspaces ? profile.workspaces.length : 0);
        document.getElementById('modalStatTranscripts').textContent = profile.transcripts_count ?? 0;
        const devices = profile.device_activity || [];
        document.getElementById('modalStatDevices').textContent = devices.length;

        // Workspaces list
        const wsContainer = document.getElementById('modalWorkspacesList');
        const workspaces = profile.workspaces || [];
        if (workspaces.length === 0) {
            wsContainer.innerHTML = '<div style="font-size:12.5px; color:var(--muted); font-style:italic;">No workspaces owned.</div>';
        } else {
            wsContainer.innerHTML = workspaces.map(w => {
                const wsName = escapeHtml(w.name || 'Untitled Workspace');
                const wsId = escapeHtml(w.id || '');
                return `<div class="list-card"><div><strong>${wsName}</strong><div style="font-size:11px; color:var(--muted); font-family:monospace;">${wsId}</div></div><span class="pill user">Owner</span></div>`;
            }).join('');
        }

        // Devices list
        const devContainer = document.getElementById('modalDevicesList');
        if (devices.length === 0) {
            devContainer.innerHTML = '<div style="font-size:12.5px; color:var(--muted); font-style:italic;">No paired BLE hardware devices recorded.</div>';
        } else {
            devContainer.innerHTML = devices.map(d => {
                const resId = escapeHtml(d.resource_id || 'Hardware Device');
                const devName = escapeHtml((d.metadata && d.metadata.device_name) ? d.metadata.device_name : resId);
                const battery = (d.metadata && d.metadata.battery_level !== undefined) ? `Battery: ${d.metadata.battery_level}%` : '';
                const dateStr = d.created_at ? new Date(d.created_at).toLocaleString() : '';
                return `<div class="list-card"><div><strong>${devName}</strong><div style="font-size:11px; color:var(--muted); font-family:monospace;">ID: ${resId} ${battery ? '&bull; ' + battery : ''}</div></div><span style="font-size:11px; color:var(--muted);">${escapeHtml(dateStr)}</span></div>`;
            }).join('');
        }

        // Display modal
        document.getElementById('profileModal').style.display = 'flex';
    } catch (err) {
        showToast(err.message, true);
    }
}

// Close modal on Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeProfileModal();
});

function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
}

document.addEventListener('DOMContentLoaded', loadUsers);
</script>
@include('partials.error-reporter')
</body>
</html>
