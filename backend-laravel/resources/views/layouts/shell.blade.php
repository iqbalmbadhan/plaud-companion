@php
    $__u = auth()->user();
    $__name = $__u->display_name ?? 'User';
    $__email = $__u->email ?? '';
    $__parts = preg_split('/\s+/', trim($__name)) ?: [];
    $__initials = strtoupper(substr($__parts[0] ?? 'P', 0, 1) . (isset($__parts[1]) ? substr($__parts[1], 0, 1) : ''));
    $__admin = (bool) ($__u->is_super_admin ?? false) || (bool) ($__u->is_developer ?? false);
    $active = trim($__env->yieldContent('active'));
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    @include('partials.theme')
    @include('partials.pwa')
    <title>@yield('title', 'PLAUD Companion') — PLAUD Companion</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--pc-font); background: var(--pc-bg); color: var(--pc-text); }
        a { color: inherit; }
        .app { display: grid; grid-template-columns: 264px 1fr; min-height: 100vh; }

        /* Sidebar — always dark to match the workspace dashboard */
        .sidebar { background: linear-gradient(180deg, var(--pc-text) 0%, var(--pc-ink) 100%); border-right: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; justify-content: space-between; padding: 20px 16px; color: #fff; }
        .brand { display: flex; align-items: center; gap: 12px; padding: 4px 6px 18px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 16px; }
        .brand-logo { width: 42px; height: 42px; border-radius: 14px; background: linear-gradient(135deg, var(--pc-emerald), #37C89A); color: #fff; display: grid; place-items: center; font-weight: 800; font-size: 18px; box-shadow: 0 10px 24px rgba(24,168,121,0.35); flex-shrink: 0; }
        .brand h1 { margin: 0; font-size: 16px; font-weight: 800; color: #fff; }
        .brand p { margin: 3px 0 0; font-size: 12px; color: rgba(255,255,255,0.65); }
        .nav { display: flex; flex-direction: column; gap: 4px; margin-top: 6px; }
        .nav a { display: flex; align-items: center; justify-content: space-between; gap: 10px; padding: 11px 14px; border-radius: 12px; text-decoration: none; color: rgba(255,255,255,0.80); font-weight: 600; font-size: 14px; border: 1px solid transparent; transition: .15s ease; }
        .nav a:hover { background: rgba(255,255,255,0.09); color: #fff; }
        .nav a.active { background: rgba(24,168,121,0.22); color: #6EE7B7; border-color: rgba(24,168,121,0.4); }
        .nav .sec { font-size: 11px; letter-spacing: .08em; text-transform: uppercase; color: rgba(255,255,255,0.42); margin: 14px 10px 4px; font-weight: 700; }
        .nav .pill { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 999px; background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.60); }
        .nav a.active .pill { background: var(--pc-emerald); color: #fff; }
        .side-card { border: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.06); border-radius: 14px; padding: 14px; font-size: 12px; color: rgba(255,255,255,0.60); }
        .side-card strong { color: rgba(255,255,255,0.90); }
        .side-card .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--pc-emerald); display: inline-block; margin-right: 6px; }

        /* Topbar */
        .main { display: flex; flex-direction: column; min-width: 0; }
        .topbar { position: sticky; top: 0; z-index: 20; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 26px; background: var(--pc-surface); border-bottom: 1px solid var(--pc-border); }
        .topbar h2 { margin: 0; font-size: 18px; font-weight: 800; }
        .top-actions { display: flex; align-items: center; gap: 10px; }
        .btn { border: 1px solid var(--pc-border); background: var(--pc-surface); color: var(--pc-text); padding: 9px 14px; border-radius: 12px; font: 700 13px var(--pc-font); cursor: pointer; display: inline-flex; align-items: center; gap: 7px; text-decoration: none; transition: .15s ease; }
        .btn:hover { background: var(--pc-surface-alt); }
        .btn.primary { background: var(--pc-emerald); color: #fff; border-color: var(--pc-emerald); }
        .btn.primary:hover { background: var(--pc-emerald-hover); }

        /* Account dropdown */
        .menu { position: relative; }
        .avatar-btn { border: 1px solid var(--pc-border); background: transparent; padding: 2px; border-radius: 13px; cursor: pointer; }
        .avatar-btn:hover { border-color: var(--pc-emerald); }
        .avatar { width: 36px; height: 36px; border-radius: 11px; background: var(--pc-text); color: var(--pc-surface); display: grid; place-items: center; font-weight: 700; font-size: 13px; }
        .menu-pop { position: absolute; top: calc(100% + 8px); right: 0; min-width: 232px; background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: 14px; box-shadow: 0 16px 40px rgba(16,27,51,0.16); padding: 6px; z-index: 50; opacity: 0; visibility: hidden; transform: translateY(-6px); transition: .16s ease; }
        .menu.open .menu-pop { opacity: 1; visibility: visible; transform: translateY(0); }
        .menu-pop a, .menu-pop button { display: flex; align-items: center; gap: 11px; width: 100%; box-sizing: border-box; padding: 11px 12px; border: 0; background: transparent; border-radius: 10px; font: 600 14px var(--pc-font); color: var(--pc-text); text-align: left; cursor: pointer; text-decoration: none; }
        .menu-pop a:hover, .menu-pop button:hover { background: var(--pc-emerald-soft); color: var(--pc-emerald-hover); }
        .menu-pop form { margin: 0; }
        .menu-head { padding: 10px 12px 12px; border-bottom: 1px solid var(--pc-border); margin-bottom: 6px; display: flex; flex-direction: column; gap: 2px; }
        .menu-head strong { font-size: 14px; } .menu-head span { font-size: 12px; color: var(--pc-muted); overflow: hidden; text-overflow: ellipsis; }
        .menu-sep { height: 1px; background: var(--pc-border); margin: 6px 4px; }

        /* Content + shared card styles so pages match the dashboard */
        .content { padding: 26px; max-width: 1100px; width: 100%; }
        .card { background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: 16px; padding: 22px; margin-bottom: 18px; }
        .card h2, .card h3 { margin-top: 0; }
        .app-footer { background: var(--pc-bg); border-top: 2px solid var(--pc-border); padding: 20px 26px; }
        .app-footer-inner { max-width: 1100px; display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
        .app-footer-brand { display: flex; align-items: center; gap: 10px; }
        .app-footer-logo { width: 28px; height: 28px; border-radius: 8px; background: var(--pc-emerald); color: #fff; font-weight: 800; font-size: 11px; display: grid; place-items: center; letter-spacing: -.5px; flex-shrink: 0; }
        .app-footer-brand-text { font-size: 13px; font-weight: 700; color: var(--pc-text); line-height: 1.2; }
        .app-footer-brand-text span { display: block; font-size: 11px; font-weight: 500; color: var(--pc-muted); }
        .app-footer-credits { font-size: 12px; color: var(--pc-muted); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; justify-content: center; }
        .app-footer-credits a { color: var(--pc-emerald-hover); text-decoration: none; font-weight: 600; }
        .app-footer-credits a:hover { text-decoration: underline; }
        .app-footer-credits .sep { opacity: .4; }
        .app-footer-ver { font-size: 11px; color: var(--pc-muted); background: var(--pc-surface); border: 1px solid var(--pc-border); border-radius: 6px; padding: 3px 9px; font-weight: 600; white-space: nowrap; }

        .hamburger { display: none; }
        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { position: fixed; inset: 0 auto 0 0; width: 264px; transform: translateX(-100%); transition: transform .25s ease; z-index: 60; }
            .sidebar.open { transform: translateX(0); }
            .hamburger { display: inline-flex; }
            .content { padding: 16px; }
        }
    </style>
    @stack('head')
</head>
<body>
@includeIf('partials.impersonation-banner')
<div class="app">
    <aside class="sidebar" id="appSidebar">
        <div>
            <a href="{{ route('app.dashboard') }}" class="brand" style="text-decoration:none;">
                <div class="brand-logo">PC</div>
                <div><h1>PLAUD Companion</h1><p>Transcript &amp; AI Workspace</p></div>
            </a>
            <nav class="nav">
                <a href="{{ route('app.dashboard') }}" class="{{ $active === 'dashboard' ? 'active' : '' }}"><span>Dashboard</span><span class="pill">Live</span></a>
                <a href="{{ route('app.dashboard') }}#transcripts"><span>Transcripts</span></a>
                <a href="{{ route('app.dashboard') }}#assistant"><span>AI Assistant</span><span class="pill">RAG</span></a>
                <a href="{{ route('app.dashboard') }}#export"><span>Bulk Export</span></a>
                <a href="{{ route('app.dashboard') }}#projects"><span>Projects &amp; Team</span></a>

                <div class="sec">Account</div>
                <a href="{{ route('profile.show') }}" class="{{ $active === 'profile' ? 'active' : '' }}"><span>My Profile</span></a>
                @if($__admin)
                    <a href="{{ route('settings.users') }}" class="{{ $active === 'users' ? 'active' : '' }}"><span>Users &amp; Access</span><span class="pill">Admin</span></a>
                    <a href="{{ route('settings.updates') }}" class="{{ $active === 'updates' ? 'active' : '' }}"><span>Updates &amp; Backups</span><span class="pill">Admin</span></a>
                @endif
            </nav>
        </div>
        <div class="side-card">
            <strong style="color:var(--pc-text);">PLAUD Companion</strong><br>
            <span class="dot"></span>v{{ config('app.version', '1.0.0') }} • Ready
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div style="display:flex; align-items:center; gap:12px;">
                <button class="btn hamburger" onclick="document.getElementById('appSidebar').classList.toggle('open')" aria-label="Menu">☰</button>
                <h2>@yield('title', 'PLAUD Companion')</h2>
            </div>
            <div class="top-actions">
                <a href="{{ route('app.dashboard') }}" class="btn">← Dashboard</a>
                <div class="menu" id="acctMenu">
                    <button class="avatar-btn" onclick="toggleAcct()" aria-haspopup="true" aria-expanded="false" title="Account menu"><span class="avatar">{{ $__initials }}</span></button>
                    <div class="menu-pop">
                        <div class="menu-head"><strong>{{ $__name }}</strong><span>{{ $__email }}</span></div>
                        <a href="{{ route('profile.show') }}">👤 My profile &amp; password</a>
                        @if($__admin)
                            <a href="{{ route('settings.users') }}">👥 Users &amp; access</a>
                            <a href="{{ route('settings.updates') }}">⚙️ Updates &amp; backups</a>
                        @endif
                        <div class="menu-sep"></div>
                        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit">↩ Sign out</button></form>
                    </div>
                </div>
            </div>
        </header>

        <div class="content">
            @yield('content')
        </div>

        <footer class="app-footer">
            <div class="app-footer-inner">
                <div class="app-footer-brand">
                    <div class="app-footer-logo">PC</div>
                    <div class="app-footer-brand-text">
                        PLAUD Companion
                        <span>Transcript &amp; AI Workspace</span>
                    </div>
                </div>
                <div class="app-footer-credits">
                    <span>Developed by</span>
                    <a href="https://www.iqbalmahmud.com" target="_blank" rel="noopener noreferrer">Md. Iqbal Mahmud</a>
                    <span class="sep">•</span>
                    <span>Advisor</span>
                    <a href="https://www.dawncsimmons.com" target="_blank" rel="noopener noreferrer">Dawn C Simmons</a>
                    <span class="sep">•</span>
                    <a href="https://www.aiinfos.com" target="_blank" rel="noopener noreferrer">AI Infos</a>
                </div>
                <span class="app-footer-ver">v{{ config('app.version', '1.0.0') }}</span>
            </div>
        </footer>
    </main>
</div>

<script>
    function toggleAcct() {
        var m = document.getElementById('acctMenu');
        var t = m.querySelector('[aria-haspopup]');
        var open = m.classList.toggle('open');
        if (t) t.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    document.addEventListener('click', function (e) { if (!e.target.closest('#acctMenu')) { var m = document.getElementById('acctMenu'); if (m) m.classList.remove('open'); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { var m = document.getElementById('acctMenu'); if (m) m.classList.remove('open'); } });
</script>
@include('partials.password-eye')
@include('partials.error-reporter')
@stack('scripts')
</body>
</html>
