<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PLAUD Companion — Sign In</title>
    <style>
        :root {
            --bg: #0B132B;
            --card: #14243A;
            --card-sub: #101B33;
            --border: #23354E;
            --text: #F8FAFC;
            --muted: #94A3B8;
            --emerald: #18A879;
            --emerald-hover: #11855E;
            --emerald-soft: rgba(24, 168, 121, 0.15);
            --blue: #3B82F6;
            --blue-soft: rgba(59, 130, 246, 0.15);
            --purple: #8B5CF6;
            --purple-soft: rgba(139, 92, 246, 0.15);
            --danger: #EF4444;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg) 0%, #101B33 100%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 440px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 16px 36px rgba(0, 0, 0, 0.35);
        }
        .brand-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .brand-logo {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--emerald) 0%, #37C89A 100%);
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 16px;
            color: #fff;
            box-shadow: 0 8px 20px rgba(24, 168, 121, 0.35);
        }
        h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .sub { margin: 3px 0 0; color: var(--muted); font-size: 13px; }
        label { display: block; font-size: 12.5px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        input {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: #0B132B;
            color: var(--text);
            font-size: 14px;
            margin-bottom: 16px;
            outline: none;
            transition: 0.15s ease;
        }
        input:focus {
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px rgba(24, 168, 121, 0.2);
        }
        button.btn-primary {
            width: 100%;
            padding: 12px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--emerald);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: 0.15s ease;
        }
        button.btn-primary:hover { background: var(--emerald-hover); }
        .error {
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fecaca;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 16px;
        }
        .demo-box {
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px 20px;
        }
        .demo-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        .demo-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--muted);
        }
        .demo-items {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .demo-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 11px;
            padding: 10px 12px;
            cursor: pointer;
            transition: 0.15s ease;
        }
        .demo-row:hover {
            border-color: var(--emerald);
            background: rgba(24, 168, 121, 0.08);
            transform: translateY(-1px);
        }
        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
        }
        .badge.green { background: var(--emerald-soft); color: #6EE7B7; }
        .badge.blue { background: var(--blue-soft); color: #93C5FD; }
        .badge.purple { background: var(--purple-soft); color: #C4B5FD; }
        .demo-meta { font-size: 11px; color: var(--muted); margin-top: 2px; }
        .fill-text { font-size: 12px; font-weight: 700; color: var(--emerald); }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="brand-header">
            <div class="brand-logo">PC</div>
            <div>
                <h1>PLAUD Companion</h1>
                <p class="sub">Transcript &amp; AI Productivity Workspace</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login.post') }}" id="loginForm">
            @csrf
            <label for="email">Email Address</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="admin@dawncsimmons.com" required autofocus>

            <label for="password">Password</label>
            <input id="password" type="password" name="password" placeholder="••••••••" required>

            <button type="submit" class="btn-primary">Sign In</button>
        </form>
    </div>

    <div class="demo-box">
        <div class="demo-header">
            <span class="demo-title">⚡ 1-Click Demo Accounts</span>
            <a href="{{ url('/demo') }}" style="color:var(--emerald); font-size:12px; text-decoration:none; font-weight:600;">Details &rarr;</a>
        </div>
        <div class="demo-items">
            <div class="demo-row" onclick="fillAndSubmit('admin@dawncsimmons.com', 'AdminPass123!')" title="Click to fill and sign in">
                <div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span class="badge green">Super-Admin</span>
                        <strong style="font-size:13px;">admin@dawncsimmons.com</strong>
                    </div>
                    <div class="demo-meta">Full Workspace + System Updates &amp; Backups</div>
                </div>
                <span class="fill-text">Sign In &rarr;</span>
            </div>

            <div class="demo-row" onclick="fillAndSubmit('sarah@dawncsimmons.com', 'EditorPass123!')" title="Click to fill and sign in">
                <div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span class="badge blue">Editor</span>
                        <strong style="font-size:13px;">sarah@dawncsimmons.com</strong>
                    </div>
                    <div class="demo-meta">Uploads, AI Prompts &amp; Bulk Word/PDF Export</div>
                </div>
                <span class="fill-text" style="color:var(--blue);">Sign In &rarr;</span>
            </div>

            <div class="demo-row" onclick="fillAndSubmit('alex@dawncsimmons.com', 'ViewerPass123!')" title="Click to fill and sign in">
                <div>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <span class="badge purple">Reviewer</span>
                        <strong style="font-size:13px;">alex@dawncsimmons.com</strong>
                    </div>
                    <div class="demo-meta">Read-Only Workspace &amp; Speaker Diarization</div>
                </div>
                <span class="fill-text" style="color:var(--purple);">Sign In &rarr;</span>
            </div>
        </div>
    </div>
</div>

<script>
function fillAndSubmit(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
    document.getElementById('loginForm').submit();
}
</script>
</body>
</html>
