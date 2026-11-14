<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PLAUD Companion — Demo Accounts &amp; Architecture</title>
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
            --amber: #F59E0B;
            --amber-soft: rgba(245, 158, 11, 0.15);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg) 0%, #101B33 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
        }
        .container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }
        .hero-card {
            background: linear-gradient(135deg, #101B33 0%, #17345A 60%, #146A5B 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
        }
        .hero-title {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }
        .logo-box {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--emerald) 0%, #37C89A 100%);
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 18px;
            color: #fff;
            box-shadow: 0 10px 24px rgba(24, 168, 121, 0.4);
        }
        h1 { margin: 0; font-size: 24px; font-weight: 800; }
        p { margin: 0; color: rgba(255,255,255,0.85); font-size: 14px; line-height: 1.6; }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 16px;
        }
        .account-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 14px;
            transition: 0.2s ease;
        }
        .account-card:hover {
            border-color: var(--emerald);
            transform: translateY(-2px);
            box-shadow: 0 12px 28px rgba(0,0,0,0.25);
        }
        .badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 9px;
            border-radius: 6px;
            display: inline-block;
            margin-bottom: 10px;
        }
        .badge.green { background: var(--emerald-soft); color: #6EE7B7; }
        .badge.blue { background: var(--blue-soft); color: #93C5FD; }
        .badge.purple { background: var(--purple-soft); color: #C4B5FD; }
        .creds-box {
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            font-size: 12.5px;
            font-family: monospace;
            color: #E2E8F0;
        }
        .creds-box div { margin: 3px 0; }
        .btn {
            display: block;
            text-align: center;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            border: 0;
            transition: 0.15s ease;
        }
        .btn-emerald { background: var(--emerald); color: #fff; }
        .btn-emerald:hover { background: var(--emerald-hover); }
        .btn-blue { background: var(--blue); color: #fff; }
        .btn-blue:hover { background: #2563EB; }
        .btn-purple { background: var(--purple); color: #fff; }
        .btn-purple:hover { background: #7C3AED; }
        .section-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }
        .section-card h3 { margin: 0 0 12px; font-size: 16px; }
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-top: 14px;
        }
        .feature-item {
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 14px;
            font-size: 13px;
        }
        .feature-item strong { display: block; margin-bottom: 4px; color: #fff; }
        .feature-item span { color: var(--muted); font-size: 12px; line-height: 1.45; }
    </style>
</head>
<body>
<div class="container">
    <div class="hero-card">
        <div class="hero-title">
            <div class="logo-box">PC</div>
            <div>
                <h1>PLAUD Companion Demo Environment</h1>
                <p>Interactive testing credentials with seeded projects, transcripts, and export packages.</p>
            </div>
        </div>
        <p style="margin-top:10px;">
            Choose a role below to sign in automatically or test permissions, workspace isolation, and super-admin controls.
        </p>
    </div>

    <div class="grid">
        <!-- Account 1 -->
        <div class="account-card">
            <div>
                <span class="badge green">Role: Super-Admin (Owner)</span>
                <h2 style="margin:0 0 6px; font-size:16px;">Dawn Simmons</h2>
                <p style="color:var(--muted); font-size:12.5px; margin-bottom:12px;">
                    Full workspace administration, signed updates, server backups, and provider settings.
                </p>
                <div class="creds-box">
                    <div><strong>Email:</strong> admin@dawncsimmons.com</div>
                    <div><strong>Password:</strong> AdminPass123!</div>
                </div>
            </div>
            <form method="POST" action="{{ route('login.post') }}" style="margin:0;">
                @csrf
                <input type="hidden" name="email" value="admin@dawncsimmons.com">
                <input type="hidden" name="password" value="AdminPass123!">
                <button type="submit" class="btn btn-emerald">Launch As Super-Admin &rarr;</button>
            </form>
        </div>

        <!-- Account 2 -->
        <div class="account-card">
            <div>
                <span class="badge blue">Role: Team Editor</span>
                <h2 style="margin:0 0 6px; font-size:16px;">Sarah Jenkins</h2>
                <p style="color:var(--muted); font-size:12.5px; margin-bottom:12px;">
                    Import recordings, run grounded AI assistant queries, extract actions, and create bulk exports.
                </p>
                <div class="creds-box">
                    <div><strong>Email:</strong> sarah@dawncsimmons.com</div>
                    <div><strong>Password:</strong> EditorPass123!</div>
                </div>
            </div>
            <form method="POST" action="{{ route('login.post') }}" style="margin:0;">
                @csrf
                <input type="hidden" name="email" value="sarah@dawncsimmons.com">
                <input type="hidden" name="password" value="EditorPass123!">
                <button type="submit" class="btn btn-blue">Launch As Editor &rarr;</button>
            </form>
        </div>

        <!-- Account 3 -->
        <div class="account-card">
            <div>
                <span class="badge purple">Role: Client Reviewer</span>
                <h2 style="margin:0 0 6px; font-size:16px;">Alex Rivera</h2>
                <p style="color:var(--muted); font-size:12.5px; margin-bottom:12px;">
                    Read-only workspace member with access to speaker turns, summaries, and action checklists.
                </p>
                <div class="creds-box">
                    <div><strong>Email:</strong> alex@dawncsimmons.com</div>
                    <div><strong>Password:</strong> ViewerPass123!</div>
                </div>
            </div>
            <form method="POST" action="{{ route('login.post') }}" style="margin:0;">
                @csrf
                <input type="hidden" name="email" value="alex@dawncsimmons.com">
                <input type="hidden" name="password" value="ViewerPass123!">
                <button type="submit" class="btn btn-purple">Launch As Reviewer &rarr;</button>
            </form>
        </div>
    </div>

    <div class="section-card">
        <h3>Seeded Demo Assets in "Executive Strategy &amp; Product" Workspace</h3>
        <p style="color:var(--muted); font-size:13px;">
            The active demo workspace is prepopulated with realistic multi-speaker recordings and generated documents:
        </p>

        <div class="feature-list">
            <div class="feature-item">
                <strong>📁 4 Active Projects</strong>
                <span>Business Meetings, Product Research, Client Discussions, Board &amp; Investor Updates</span>
            </div>
            <div class="feature-item">
                <strong>🎙️ 5 Diarized Transcripts</strong>
                <span>Speaker-labeled transcripts with timestamps (.TXT, .SRT, .DOCX, .PDF &amp; PLAUD Note Sync)</span>
            </div>
            <div class="feature-item">
                <strong>📦 1 Downloadable ZIP Package</strong>
                <span>Real Microsoft Word (.docx) and .pdf document bundle ready to download</span>
            </div>
            <div class="feature-item">
                <strong>🔒 Security Enforcement</strong>
                <span>System Updates &amp; Backups visible only to Super-Admin; blocked for standard users (HTTP 403)</span>
            </div>
        </div>

        <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
            <a href="{{ url('/login') }}" style="color:var(--muted); font-size:13px; text-decoration:none;">&larr; Back to standard login</a>
            <span style="font-size:12px; color:var(--emerald);">Local Environment Ready • MySQL Database Connected</span>
        </div>

        <footer style="margin-top:20px; padding-top:16px; border-top:1px solid var(--border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; font-size:12px; color:var(--muted);">
            <div><strong>PLAUD Companion</strong> &bull; On-Device Cryptography Vault</div>
            <div style="display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                <span>Developed by <a href="https://www.iqbalmahmud.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Md. Iqbal Mahmud</a></span>
                <span style="opacity:0.4;">&bull;</span>
                <span>Technical Advisor: <a href="https://www.dawncsimmons.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Dawn C Simmons</a></span>
                <span style="opacity:0.4;">&bull;</span>
                <span>Brought to you by <a href="https://www.aiinfos.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">AI Infos</a></span>
            </div>
        </footer>
    </div>
</div>
</body>
</html>
