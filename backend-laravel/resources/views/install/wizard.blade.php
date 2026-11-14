<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PLAUD Companion — 1-Click Installer</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .shell {
            width: 100%;
            max-width: 780px;
            background: var(--card);
            border: 1px solid var(--navy-border);
            border-radius: 16px;
            box-shadow: 0 24px 60px rgba(0, 0, 0, 0.45);
            overflow: hidden;
        }
        .header {
            background: var(--navy);
            padding: 24px 28px;
            border-bottom: 1px solid var(--navy-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .brand-badge {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--emerald);
            color: #07131E;
            font-weight: 800;
            display: grid;
            place-items: center;
            font-size: 18px;
        }
        .brand h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }
        .brand p {
            margin: 2px 0 0;
            font-size: 12px;
            color: var(--muted);
        }
        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            background: var(--navy-light);
            border-bottom: 1px solid var(--navy-border);
        }
        .step-tab {
            padding: 12px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-align: center;
            border-bottom: 2px solid transparent;
        }
        .step-tab.active {
            color: var(--text);
            border-bottom-color: var(--emerald);
            background: rgba(24, 168, 121, 0.08);
        }
        .step-tab.done {
            color: var(--emerald);
        }
        .body { padding: 28px; }
        .panel { display: none; }
        .panel.active { display: block; }
        h2 { margin: 0 0 6px; font-size: 18px; }
        .sub { margin: 0 0 20px; font-size: 13px; color: var(--muted); }
        .check-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        .check-item {
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 10px;
            padding: 10px 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 13px;
        }
        .pill {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
        }
        .pill.ok { background: rgba(24, 168, 121, 0.16); color: var(--emerald); }
        .pill.fail { background: rgba(239, 68, 68, 0.16); color: var(--danger); }
        .pill.warn { background: rgba(245, 158, 11, 0.16); color: var(--warn); }
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        label { font-size: 12px; font-weight: 600; color: var(--muted); }
        input, select {
            background: var(--navy);
            border: 1px solid var(--navy-border);
            border-radius: 8px;
            padding: 10px 12px;
            color: var(--text);
            font-size: 14px;
            outline: none;
        }
        input:focus, select:focus { border-color: var(--emerald); }
        .actions {
            margin-top: 22px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }
        .btn {
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .btn-primary { background: var(--emerald); color: #07131E; }
        .btn-primary:hover:not(:disabled) { background: var(--emerald-hover); }
        .btn-secondary {
            background: var(--navy-light);
            color: var(--text);
            border: 1px solid var(--navy-border);
        }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .alert {
            padding: 12px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
            display: none;
        }
        .alert.show { display: block; }
        .alert.error { background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #FCA5A5; }
        .alert.success { background: rgba(24, 168, 121, 0.15); border: 1px solid rgba(24, 168, 121, 0.4); color: #6EE7B7; }
        @media (max-width: 640px) {
            .form-grid { grid-template-columns: 1fr; }
            .steps { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
<div class="shell">
    <div class="header">
        <div class="brand">
            <div class="brand-badge">P</div>
            <div>
                <h1>PLAUD Companion</h1>
                <p>1-Click Server Setup Wizard</p>
            </div>
        </div>
        <span class="pill ok">PHP {{ $preflight['php_version'] }}</span>
    </div>

    <div class="steps">
        <div class="step-tab active" data-step-tab="1">1. Preflight</div>
        <div class="step-tab" data-step-tab="2">2. Database &amp; .env</div>
        <div class="step-tab" data-step-tab="3">3. Super Admin</div>
        <div class="step-tab" data-step-tab="4">4. Complete</div>
    </div>

    <div class="body">
        <div id="alert" class="alert"></div>

        <!-- Step 1: Preflight -->
        <div class="panel active" data-step="1">
            <h2>Server Preflight Checks</h2>
            <p class="sub">Verifying PHP version, required extensions, writable paths, and process capabilities.</p>

            <div id="preflight-grid" class="check-grid">
                <div class="check-item">
                    <span>PHP &gt;= 8.3 ({{ $preflight['php_version'] }})</span>
                    <span class="pill {{ $preflight['php_ok'] ? 'ok' : 'fail' }}">{{ $preflight['php_ok'] ? 'OK' : 'FAIL' }}</span>
                </div>
                @foreach ($preflight['extensions'] as $ext => $loaded)
                    <div class="check-item">
                        <span>ext-{{ $ext }}</span>
                        <span class="pill {{ $loaded ? 'ok' : 'fail' }}">{{ $loaded ? 'OK' : 'MISSING' }}</span>
                    </div>
                @endforeach
                @foreach ($preflight['writable'] as $dir => $isWritable)
                    <div class="check-item">
                        <span>{{ $dir }}/</span>
                        <span class="pill {{ $isWritable ? 'ok' : 'fail' }}">{{ $isWritable ? 'WRITABLE' : 'LOCKED' }}</span>
                    </div>
                @endforeach
                <div class="check-item">
                    <span>proc_open()</span>
                    <span class="pill {{ $preflight['proc_open_enabled'] ? 'ok' : 'warn' }}">
                        {{ $preflight['proc_open_enabled'] ? 'ENABLED' : 'FALLBACK' }}
                    </span>
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="refreshPreflight()">Re-check Server</button>
                <button type="button" id="btn-step1-next" class="btn btn-primary" onclick="goStep(2)" {{ $preflight['all_ok'] ? '' : 'disabled' }}>
                    Continue to Database
                </button>
            </div>
        </div>

        <!-- Step 2: Database & .env -->
        <div class="panel" data-step="2">
            <h2>Database &amp; Environment</h2>
            <p class="sub">Configure your database connection. Saving writes <code>.env</code> and auto-generates <code>APP_KEY</code> &amp; <code>JWT_SECRET_KEY</code>.</p>

            <div class="form-grid">
                <div class="field">
                    <label for="app_name">Application Name</label>
                    <input id="app_name" type="text" value="{{ $defaults['app_name'] }}">
                </div>
                <div class="field">
                    <label for="app_url">Application URL</label>
                    <input id="app_url" type="url" value="{{ $defaults['app_url'] }}">
                </div>
                <div class="field">
                    <label for="db_connection">Database Driver</label>
                    <select id="db_connection" onchange="toggleDbFields()">
                        <option value="mysql" selected>MySQL / MariaDB</option>
                        <option value="sqlite">SQLite</option>
                    </select>
                </div>
                <div class="field">
                    <label for="db_database">Database Name / SQLite Path</label>
                    <input id="db_database" type="text" value="{{ $defaults['db_database'] }}">
                </div>
                <div class="field mysql-only">
                    <label for="db_host">DB Host</label>
                    <input id="db_host" type="text" value="{{ $defaults['db_host'] }}">
                </div>
                <div class="field mysql-only">
                    <label for="db_port">DB Port</label>
                    <input id="db_port" type="number" value="{{ $defaults['db_port'] }}">
                </div>
                <div class="field mysql-only">
                    <label for="db_username">DB Username</label>
                    <input id="db_username" type="text" value="{{ $defaults['db_username'] }}">
                </div>
                <div class="field mysql-only">
                    <label for="db_password">DB Password</label>
                    <input id="db_password" type="password" value="">
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="goStep(1)">Back</button>
                <div style="display:flex; gap:10px;">
                    <button type="button" class="btn btn-secondary" onclick="testDb()">Test Connection</button>
                    <button type="button" class="btn btn-primary" onclick="saveEnv()">Save .env &amp; Continue</button>
                </div>
            </div>
        </div>

        <!-- Step 3: Super-Admin Owner & Migrations -->
        <div class="panel" data-step="3">
            <h2>Create Super-Admin Owner</h2>
            <p class="sub">Runs database migrations, creates your initial <code>is_super_admin</code> owner and default workspace, and locks <code>/install</code>.</p>

            <div class="form-grid">
                <div class="field">
                    <label for="display_name">Owner Name</label>
                    <input id="display_name" type="text" placeholder="Ada Lovelace">
                </div>
                <div class="field">
                    <label for="email">Owner Email</label>
                    <input id="email" type="email" placeholder="owner@example.com">
                </div>
                <div class="field">
                    <label for="password">Password (min 10 chars)</label>
                    <input id="password" type="password" placeholder="••••••••••••">
                </div>
                <div class="field">
                    <label for="workspace_name">Default Workspace Name</label>
                    <input id="workspace_name" type="text" value="Default Workspace">
                </div>
            </div>

            <div class="actions">
                <button type="button" class="btn btn-secondary" onclick="goStep(2)">Back</button>
                <button type="button" id="btn-install" class="btn btn-primary" onclick="runInstall()">Run Migrations &amp; Install</button>
            </div>
        </div>

        <!-- Step 4: Complete -->
        <div class="panel" data-step="4">
            <h2>Installation Complete</h2>
            <p class="sub"><code>storage/app/installed.lock</code> has been written. All <code>/install</code> endpoints are now permanently locked (HTTP 404).</p>
            <div id="install-summary" class="check-grid"></div>
            <div class="actions">
                <span></span>
                <a href="/settings/updates" class="btn btn-primary" style="text-decoration:none;">Open Update &amp; Backup Panel</a>
            </div>
        </div>
    </div>

    <footer style="margin-top:20px; padding:16px 20px; border-top:1px solid var(--navy-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; font-size:12px; color:var(--muted);">
        <div><strong>PLAUD Companion</strong> &bull; Self-Hosted Setup Engine</div>
        <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
            <span>Developed by <a href="https://www.iqbalmahmud.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Md. Iqbal Mahmud</a></span>
            <span style="opacity:0.4;">&bull;</span>
            <span>Technical Advisor: <a href="https://www.dawncsimmons.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Dawn C Simmons</a></span>
            <span style="opacity:0.4;">&bull;</span>
            <span>Brought to you by <a href="https://www.aiinfos.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">AI Infos</a></span>
        </div>
    </footer>
</div>

<script>
let csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function showAlert(msg, type = 'error') {
    const el = document.getElementById('alert');
    el.textContent = msg;
    el.className = 'alert show ' + type;
}

function clearAlert() {
    const el = document.getElementById('alert');
    el.className = 'alert';
}

function goStep(step) {
    clearAlert();
    document.querySelectorAll('.panel').forEach(p => {
        p.classList.toggle('active', Number(p.dataset.step) === step);
    });
    document.querySelectorAll('.step-tab').forEach(t => {
        const n = Number(t.dataset.stepTab);
        t.classList.toggle('active', n === step);
        t.classList.toggle('done', n < step);
    });
}

function toggleDbFields() {
    const isMysql = document.getElementById('db_connection').value === 'mysql';
    document.querySelectorAll('.mysql-only').forEach(el => {
        el.style.display = isMysql ? 'flex' : 'none';
    });
}

async function apiPost(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify(payload)
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || data.message || ('HTTP ' + res.status));
    }
    return data;
}

async function refreshPreflight() {
    clearAlert();
    const res = await fetch('/install/preflight', { headers: { 'Accept': 'application/json' } });
    const data = await res.json();
    document.getElementById('btn-step1-next').disabled = !data.all_ok;
    showAlert(data.all_ok ? 'All required preflight checks passed.' : 'One or more preflight checks failed.', data.all_ok ? 'success' : 'error');
}

function dbPayload() {
    return {
        app_name: document.getElementById('app_name').value,
        app_url: document.getElementById('app_url').value,
        db_connection: document.getElementById('db_connection').value,
        db_host: document.getElementById('db_host').value,
        db_port: Number(document.getElementById('db_port').value || 3306),
        db_database: document.getElementById('db_database').value,
        db_username: document.getElementById('db_username').value,
        db_password: document.getElementById('db_password').value
    };
}

async function testDb() {
    clearAlert();
    try {
        const res = await apiPost('/install/test-db', dbPayload());
        showAlert('Database connection succeeded (server ' + (res.server_version || 'ok') + ').', 'success');
    } catch (e) {
        showAlert(e.message, 'error');
    }
}

async function saveEnv() {
    clearAlert();
    try {
        const res = await apiPost('/install/configure', dbPayload());
        showAlert(res.message || '.env saved.', 'success');
        setTimeout(() => goStep(3), 400);
    } catch (e) {
        showAlert(e.message, 'error');
    }
}

async function runInstall() {
    clearAlert();
    const btn = document.getElementById('btn-install');
    btn.disabled = true;
    btn.textContent = 'Installing...';
    try {
        const res = await apiPost('/install/run', {
            display_name: document.getElementById('display_name').value,
            email: document.getElementById('email').value,
            password: document.getElementById('password').value,
            workspace_name: document.getElementById('workspace_name').value
        });
        const summary = document.getElementById('install-summary');
        summary.innerHTML = `
            <div class="check-item"><span>Super-Admin ID</span><span class="pill ok">${res.user_id}</span></div>
            <div class="check-item"><span>Workspace ID</span><span class="pill ok">${res.workspace_id}</span></div>
        `;
        goStep(4);
    } catch (e) {
        showAlert(e.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Run Migrations & Install';
    }
}
</script>
</body>
</html>
