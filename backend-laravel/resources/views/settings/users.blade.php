<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json">
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
    </style>
</head>
<body>
<div class="container">
    <div class="topbar">
        <div class="brand">
            <div class="badge">PC</div>
            <div>
                <h1>User &amp; Workspace Access Control</h1>
                <p>Super-Admin User Management Console &bull; Instant Suspension Controls</p>
            </div>
        </div>
        <div class="nav-tabs">
            <a href="{{ route('settings.users') }}" class="nav-tab active">Users</a>
            <a href="{{ route('settings.updates') }}" class="nav-tab">System Updates</a>
            <a href="{{ route('app.home') }}" class="nav-tab">← Workspace</a>
        </div>
    </div>

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

<div id="toast" class="toast"></div>

<script>
const CSRF = document.querySelector('meta[name="csrf-token"]').content;
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
    const url = `/settings/users/api/list?q=${encodeURIComponent(q)}&status=${encodeURIComponent(status)}`;

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
    if (!users.length) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--muted);">No registered users match your criteria.</td></tr>';
        return;
    }

    tbody.innerHTML = users.map(u => {
        const initials = (u.display_name || u.email || 'U').substring(0, 2).toUpperCase();
        const rolePill = u.is_super_admin ? '<span class="pill admin">Super Admin</span>' : '<span class="pill user">User</span>';
        const statusPill = u.status === 'active' ? '<span class="pill active">Active</span>' : '<span class="pill suspended">Suspended</span>';
        
        let actionBtns = '';
        if (u.is_current_user) {
            actionBtns = '<span style="font-size:12px; color:var(--muted); font-style:italic;">Your Account</span>';
        } else {
            const toggleStatusBtn = u.status === 'active'
                ? `<button class="btn btn-suspend" onclick="toggleStatus('${u.id}', '${u.display_name}')">Suspend</button>`
                : `<button class="btn btn-activate" onclick="toggleStatus('${u.id}', '${u.display_name}')">Reactivate</button>`;
            
            const adminLabel = u.is_super_admin ? 'Demote' : 'Make Admin';
            const toggleAdminBtn = `<button class="btn btn-promote" onclick="toggleAdmin('${u.id}', '${u.display_name}')">${adminLabel}</button>`;
            
            actionBtns = `<div style="display:flex; gap:8px; justify-content:flex-end;">${toggleStatusBtn} ${toggleAdminBtn}</div>`;
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
                <td>${u.workspaces_count} workspace(s)</td>
                <td style="color:var(--muted); font-size:12.5px;">${u.created_at}</td>
                <td style="text-align:right;">${actionBtns}</td>
            </tr>
        `;
    }).join('');
}

async function toggleStatus(userId, name) {
    if (!confirm(`Are you sure you want to change the access status for ${name}?`)) return;

    try {
        const res = await fetch(`/settings/users/api/${userId}/toggle-status`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Action failed');
        showToast(data.message);
        loadUsers();
    } catch (err) {
        showToast(err.message, true);
    }
}

async function toggleAdmin(userId, name) {
    if (!confirm(`Are you sure you want to change super-admin privileges for ${name}?`)) return;

    try {
        const res = await fetch(`/settings/users/api/${userId}/toggle-admin`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json'
            }
        });
        const data = await res.json();
        if (!res.ok) throw new Error(data.message || 'Action failed');
        showToast(data.message);
        loadUsers();
    } catch (err) {
        showToast(err.message, true);
    }
}

function escapeHtml(str) {
    return (str || '').replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m]);
}

document.addEventListener('DOMContentLoaded', loadUsers);
</script>
</body>
</html>
