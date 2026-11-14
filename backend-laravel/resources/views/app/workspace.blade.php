<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>PLAUD Companion — Transcript &amp; AI Productivity Workspace</title>
  <style>
    :root {
      --bg: #F6F8FB;
      --surface: #FFFFFF;
      --surface-alt: #EDF2F8;
      --navy: #101B33;
      --navy-2: #14243A;
      --slate: #2D3F57;
      --muted: #64748B;
      --line: #DCE3EE;
      --emerald: #18A879;
      --emerald-dark: #11855E;
      --emerald-soft: #E8F8F2;
      --blue: #2563EB;
      --blue-soft: #EAF1FF;
      --amber: #D97706;
      --amber-soft: #FFF7ED;
      --danger: #DC2626;
      --danger-soft: #FEF2F2;
      --shadow: 0 12px 30px rgba(16, 27, 51, 0.08);
      --radius: 18px;
      --radius-sm: 12px;
      --font: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    * { box-sizing: border-box; }
    html, body {
      margin: 0;
      padding: 0;
      background: var(--bg);
      color: var(--navy);
      font-family: var(--font);
      min-height: 100vh;
    }

    .app {
      display: grid;
      grid-template-columns: 270px 1fr;
      min-height: 100vh;
    }

    .sidebar {
      background: linear-gradient(180deg, var(--navy) 0%, var(--navy-2) 100%);
      color: #fff;
      padding: 22px 18px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      border-right: 1px solid rgba(255,255,255,0.08);
      position: sticky;
      top: 0;
      height: 100vh;
    }

    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 10px 18px;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 16px;
    }

    .brand-logo {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: linear-gradient(135deg, var(--emerald) 0%, #37C89A 100%);
      display: grid;
      place-items: center;
      font-weight: 800;
      letter-spacing: -0.04em;
      box-shadow: 0 10px 24px rgba(24, 168, 121, 0.35);
      flex-shrink: 0;
    }

    .brand h1 {
      font-size: 16px;
      margin: 0;
      line-height: 1.2;
    }

    .brand p {
      margin: 3px 0 0;
      font-size: 12px;
      color: rgba(255,255,255,0.68);
    }

    .ws-switcher {
      margin-bottom: 16px;
      padding: 10px;
      border-radius: 14px;
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
    }

    .ws-switcher label {
      display: block;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.06em;
      color: rgba(255,255,255,0.62);
      margin-bottom: 6px;
      font-weight: 700;
    }

    .ws-select-row {
      display: flex;
      gap: 8px;
      align-items: center;
      width: 100%;
      min-width: 0;
    }

    .ws-select {
      flex: 1 1 0;
      min-width: 0;
      background: rgba(16, 27, 51, 0.75);
      color: #fff;
      border: 1px solid rgba(255,255,255,0.18);
      border-radius: 10px;
      padding: 8px 10px;
      font-size: 13px;
      font-weight: 600;
      outline: none;
      text-overflow: ellipsis;
      white-space: nowrap;
      overflow: hidden;
    }

    .ws-add-btn {
      flex: 0 0 32px;
      width: 32px;
      height: 32px;
      background: rgba(24, 168, 121, 0.22);
      color: #6EE7B7;
      border: 1px solid rgba(24, 168, 121, 0.4);
      border-radius: 10px;
      display: grid;
      place-items: center;
      cursor: pointer;
      font-weight: 700;
      font-size: 16px;
      line-height: 1;
      padding: 0;
    }

    .nav {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .nav button {
      all: unset;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 11px 14px;
      border-radius: 13px;
      color: rgba(255,255,255,0.78);
      font-size: 14px;
      font-weight: 600;
      transition: 0.18s ease;
    }

    .nav button:hover {
      background: rgba(255,255,255,0.07);
      color: #fff;
    }

    .nav button.active {
      background: rgba(24, 168, 121, 0.18);
      color: #fff;
      border: 1px solid rgba(24, 168, 121, 0.42);
    }

    .badge {
      font-size: 11px;
      padding: 3px 8px;
      border-radius: 999px;
      background: rgba(255,255,255,0.1);
      color: #fff;
    }

    .sidebar-card {
      background: rgba(255,255,255,0.06);
      border: 1px solid rgba(255,255,255,0.1);
      padding: 14px;
      border-radius: 16px;
    }

    .sidebar-card h3 {
      margin: 0 0 6px;
      font-size: 13px;
    }

    .sidebar-card p {
      margin: 0 0 10px;
      font-size: 12px;
      color: rgba(255,255,255,0.72);
      line-height: 1.45;
    }

    .status-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 12px;
      color: #B9F6E2;
      font-weight: 600;
    }

    .dot {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: var(--emerald);
      box-shadow: 0 0 0 4px rgba(24, 168, 121, 0.22);
    }

    .main {
      display: flex;
      flex-direction: column;
      min-width: 0;
    }

    .topbar {
      background: rgba(255,255,255,0.88);
      backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--line);
      padding: 14px 26px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      position: sticky;
      top: 0;
      z-index: 10;
    }

    .search-wrap {
      flex: 1;
      max-width: 520px;
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--surface-alt);
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 9px 14px;
    }

    .search-wrap input {
      border: 0;
      outline: 0;
      background: transparent;
      width: 100%;
      font-size: 14px;
      color: var(--navy);
    }

    .top-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }

    .btn {
      border: 1px solid var(--line);
      background: var(--surface);
      color: var(--navy);
      padding: 9px 14px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: 0.18s ease;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      text-decoration: none;
    }

    .btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(16,27,51,0.06); }
    .btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }

    .btn.primary {
      background: var(--emerald);
      color: #fff;
      border-color: var(--emerald);
      box-shadow: 0 8px 18px rgba(24, 168, 121, 0.22);
    }

    .btn.primary:hover { background: var(--emerald-dark); }

    .btn.secondary {
      background: var(--navy);
      color: #fff;
      border-color: var(--navy);
    }

    .btn.danger {
      background: var(--danger-soft);
      color: var(--danger);
      border-color: #FECACA;
    }

    .btn.sm {
      padding: 6px 10px;
      font-size: 12px;
      border-radius: 9px;
    }

    .avatar {
      width: 36px;
      height: 36px;
      border-radius: 11px;
      background: var(--navy);
      color: #fff;
      display: grid;
      place-items: center;
      font-weight: 700;
      font-size: 13px;
    }

    .content {
      padding: 24px;
      display: none;
      gap: 20px;
      flex-direction: column;
    }

    .content.active { display: flex; }

    .hero {
      background: linear-gradient(135deg, var(--navy) 0%, #17345A 60%, #146A5B 100%);
      color: #fff;
      border-radius: 22px;
      padding: 24px;
      display: grid;
      grid-template-columns: 1.5fr 1fr;
      gap: 18px;
      box-shadow: var(--shadow);
    }

    .hero h2 {
      margin: 0 0 8px;
      font-size: 24px;
      letter-spacing: -0.02em;
    }

    .hero p {
      margin: 0 0 16px;
      color: rgba(255,255,255,0.82);
      line-height: 1.55;
      font-size: 14px;
      max-width: 62ch;
    }

    .hero-tags {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .pill {
      padding: 6px 11px;
      border-radius: 999px;
      background: rgba(255,255,255,0.12);
      font-size: 12px;
      font-weight: 600;
    }

    .hero-box {
      background: rgba(255,255,255,0.1);
      border: 1px solid rgba(255,255,255,0.16);
      border-radius: 18px;
      padding: 16px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .grid-4 {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 16px;
    }

    .grid-3 {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 16px;
    }

    .grid-2 {
      display: grid;
      grid-template-columns: 1.35fr 1fr;
      gap: 18px;
    }

    .card {
      background: var(--surface);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      padding: 18px;
      box-shadow: var(--shadow);
    }

    .stat-label {
      color: var(--muted);
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 26px;
      font-weight: 800;
      letter-spacing: -0.03em;
      margin-bottom: 6px;
    }

    .stat-sub {
      font-size: 12px;
      color: var(--emerald-dark);
      font-weight: 700;
    }

    .section-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 14px;
      flex-wrap: wrap;
    }

    .section-head h3 {
      margin: 0;
      font-size: 16px;
      letter-spacing: -0.01em;
    }

    .section-head span {
      color: var(--muted);
      font-size: 12px;
    }

    .list {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .item {
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 13px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      background: #fff;
      cursor: pointer;
      transition: 0.16s ease;
    }

    .item:hover {
      border-color: var(--emerald);
      transform: translateY(-1px);
    }

    .item-left {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      min-width: 0;
    }

    .icon-box {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: grid;
      place-items: center;
      font-weight: 800;
      font-size: 13px;
      background: var(--emerald-soft);
      color: var(--emerald-dark);
      flex-shrink: 0;
    }

    .item-title {
      font-weight: 700;
      font-size: 14px;
      margin: 0 0 4px;
    }

    .item-meta {
      font-size: 12px;
      color: var(--muted);
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
    }

    .tag {
      font-size: 11px;
      font-weight: 700;
      padding: 4px 9px;
      border-radius: 999px;
      background: var(--surface-alt);
      color: var(--slate);
      white-space: nowrap;
    }

    .tag.green { background: var(--emerald-soft); color: var(--emerald-dark); }
    .tag.blue { background: var(--blue-soft); color: var(--blue); }
    .tag.amber { background: var(--amber-soft); color: var(--amber); }

    .quick-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .quick {
      border: 1px solid var(--line);
      border-radius: 15px;
      padding: 14px;
      background: linear-gradient(180deg, #fff 0%, #FAFBFD 100%);
      cursor: pointer;
      transition: 0.18s ease;
    }

    .quick:hover {
      border-color: var(--navy);
      transform: translateY(-1px);
    }

    .quick strong {
      display: block;
      margin-bottom: 5px;
      font-size: 14px;
    }

    .quick small {
      color: var(--muted);
      line-height: 1.45;
      display: block;
      font-size: 12px;
    }

    .upload-zone {
      border: 2px dashed #B9C7DA;
      background: #F9FBFE;
      border-radius: 18px;
      padding: 24px;
      text-align: center;
      transition: 0.2s ease;
      cursor: pointer;
    }

    .upload-zone:hover, .upload-zone.dragover {
      border-color: var(--emerald);
      background: var(--emerald-soft);
    }

    .upload-zone h4 {
      margin: 8px 0 6px;
      font-size: 16px;
    }

    .upload-zone p {
      margin: 0 0 14px;
      color: var(--muted);
      font-size: 13px;
    }

    .filters {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
    }

    .chip-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .chip {
      border: 1px solid var(--line);
      background: #fff;
      border-radius: 999px;
      padding: 7px 12px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
    }

    .chip.active {
      background: var(--navy);
      color: #fff;
      border-color: var(--navy);
    }

    .table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .table th, .table td {
      padding: 12px 10px;
      border-bottom: 1px solid var(--line);
      text-align: left;
      vertical-align: middle;
    }

    .table th {
      color: var(--muted);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.04em;
    }

    .split-detail {
      display: grid;
      grid-template-columns: 1.25fr 0.95fr;
      gap: 18px;
    }

    .transcript-box {
      max-height: 540px;
      overflow: auto;
      padding-right: 6px;
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .utterance {
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 12px 14px;
      background: #fff;
    }

    .utterance-head {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
      font-size: 12px;
      font-weight: 700;
      color: var(--emerald-dark);
    }

    .utterance p {
      margin: 0;
      font-size: 13.5px;
      line-height: 1.55;
      color: var(--navy);
    }

    .chat-shell {
      display: grid;
      grid-template-columns: 280px 1fr;
      gap: 18px;
      min-height: 560px;
    }

    .prompt-list {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .prompt-btn {
      text-align: left;
      border: 1px solid var(--line);
      background: var(--surface-alt);
      padding: 11px 12px;
      border-radius: 12px;
      cursor: pointer;
      font-size: 12.5px;
      font-weight: 600;
      color: var(--navy);
    }

    .prompt-btn:hover {
      border-color: var(--emerald);
      background: var(--emerald-soft);
    }

    .chat-box {
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      min-height: 540px;
    }

    .messages {
      display: flex;
      flex-direction: column;
      gap: 12px;
      max-height: 400px;
      overflow-y: auto;
      padding-right: 6px;
    }

    .msg {
      max-width: 85%;
      padding: 13px 15px;
      border-radius: 16px;
      font-size: 13.5px;
      line-height: 1.55;
      white-space: pre-wrap;
    }

    .msg.user {
      align-self: flex-end;
      background: var(--navy);
      color: #fff;
      border-bottom-right-radius: 4px;
    }

    .msg.ai {
      align-self: flex-start;
      background: var(--surface-alt);
      color: var(--navy);
      border: 1px solid var(--line);
      border-bottom-left-radius: 4px;
    }

    .chat-input {
      display: flex;
      gap: 10px;
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--line);
    }

    .chat-input input {
      flex: 1;
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 12px 14px;
      font-size: 13.5px;
      outline: none;
    }

    .export-options {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .option-card {
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 14px;
      background: #fff;
      cursor: pointer;
    }

    .option-card.selected {
      border-color: var(--emerald);
      background: var(--emerald-soft);
    }

    .check-row {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 0;
      border-bottom: 1px solid var(--line);
      font-size: 13px;
    }

    .form-input, .form-select, .form-textarea {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 11px;
      padding: 10px 12px;
      font-size: 13px;
      font-family: inherit;
      color: var(--navy);
      background: #fff;
      outline: none;
    }

    .form-input:focus, .form-select:focus, .form-textarea:focus {
      border-color: var(--emerald);
      box-shadow: 0 0 0 3px rgba(24, 168, 121, 0.14);
    }

    .modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(16, 27, 51, 0.55);
      backdrop-filter: blur(4px);
      display: none;
      place-items: center;
      z-index: 50;
      padding: 18px;
    }

    .modal-backdrop.open { display: grid; }

    .modal {
      background: #fff;
      border-radius: 20px;
      width: 100%;
      max-width: 560px;
      padding: 22px;
      box-shadow: 0 24px 48px rgba(16, 27, 51, 0.24);
      border: 1px solid var(--line);
    }

    .modal h3 { margin: 0 0 14px; font-size: 18px; }

    .toast-wrap {
      position: fixed;
      bottom: 22px;
      right: 22px;
      z-index: 80;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }

    .toast {
      background: var(--navy);
      color: #fff;
      padding: 12px 16px;
      border-radius: 12px;
      font-size: 13px;
      font-weight: 600;
      box-shadow: 0 12px 28px rgba(16,27,51,0.22);
      border-left: 4px solid var(--emerald);
    }

    .toast.error { border-left-color: var(--danger); }

    .mobile-bar {
      display: none;
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: var(--navy);
      color: #fff;
      padding: 8px 10px;
      justify-content: space-around;
      z-index: 20;
    }

    .mobile-bar button {
      all: unset;
      cursor: pointer;
      font-size: 11px;
      text-align: center;
      color: rgba(255,255,255,0.75);
      padding: 6px 8px;
      border-radius: 10px;
      font-weight: 600;
    }

    .mobile-bar button.active {
      color: #fff;
      background: rgba(24, 168, 121, 0.24);
    }

    @media (max-width: 1080px) {
      .grid-4 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .grid-3, .grid-2, .split-detail, .hero, .chat-shell {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 780px) {
      .app { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .mobile-bar { display: flex; }
      .content { padding: 16px 16px 82px; }
      .topbar { padding: 12px 16px; }
      .grid-4, .export-options, .quick-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
  <div class="app">
    <aside class="sidebar">
      <div>
        <div class="brand">
          <div class="brand-logo">PC</div>
          <div>
            <h1>PLAUD Companion</h1>
            <p>Transcript &amp; AI Workspace</p>
          </div>
        </div>

        <div class="ws-switcher">
          <label for="workspaceSelect">Active Workspace</label>
          <div class="ws-select-row">
            <select id="workspaceSelect" class="ws-select" onchange="switchWorkspace(this.value)"></select>
            <button class="ws-add-btn" title="Create New Workspace" onclick="openModal('workspaceModal')">+</button>
          </div>
        </div>

        <nav class="nav" id="sideNav">
          <button class="active" data-view="dashboard"><span>Dashboard</span><span class="badge">Live</span></button>
          <button data-view="transcripts"><span>Transcripts</span><span class="badge" id="navTranscriptCount">0</span></button>
          <button data-view="detail"><span>Transcript Detail</span><span class="badge">AI</span></button>
          <button data-view="assistant"><span>AI Assistant</span><span class="badge">RAG</span></button>
          <button data-view="export"><span>Bulk Export</span><span class="badge">DOCX/PDF</span></button>
          <button data-view="projects"><span>Projects &amp; Team</span><span class="badge" id="navProjectCount">0</span></button>
          <button data-view="settings">
            <span>Settings</span>
            @if($user['is_super_admin'])
              <span class="badge" style="background:rgba(24,168,121,0.3);color:#A7F3D0;">Admin</span>
            @else
              <span class="badge">Config</span>
            @endif
          </button>
        </nav>
      </div>

      <div class="sidebar-card">
        <h3>PLAUD Integration Bridge</h3>
        <p>Import TXT, SRT, DOCX, or PDF recordings now. Ready for direct PLAUD Note / NotePin sync.</p>
        <div class="status-row">
          <span class="dot"></span>
          <span id="sidebarVersionLabel">v{{ $currentVersion }} • Ready</span>
        </div>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div class="search-wrap">
          <span>🔎</span>
          <input id="globalSearch" type="text" placeholder="Search transcripts, speakers, action items, or projects..." oninput="handleGlobalSearch(this.value)" />
        </div>
        <div class="top-actions">
          <button class="btn" onclick="switchView('transcripts'); document.getElementById('fileUploadInput').click();">+ Import Files</button>
          <button class="btn" onclick="openModal('newTranscriptModal')">+ Paste Transcript</button>
          <button class="btn primary" onclick="switchView('assistant')">✨ Ask AI</button>
          <div class="avatar" title="{{ $user['display_name'] }} ({{ $user['email'] }})">{{ $user['initials'] }}</div>
          <form method="POST" action="{{ url('/logout') }}" style="margin:0;">
            @csrf
            <button type="submit" class="btn sm" title="Sign Out">Sign Out</button>
          </form>
        </div>
      </header>

      <!-- VIEW 1: DASHBOARD -->
      <section class="content active" id="dashboard">
        <div class="hero">
          <div>
            <h2>Welcome back, {{ $user['display_name'] }}</h2>
            <p>
              Organize PLAUD recordings and transcripts, run multi-file AI analysis across meetings,
              extract grounded action items, and generate formatted Word (DOCX) and PDF packages in one click.
            </p>
            <div class="hero-tags">
              <span class="pill">Multi-File Import (TXT/SRT/DOCX/PDF)</span>
              <span class="pill">OpenRouter • Gemini • Claude • OpenAI</span>
              <span class="pill">Bulk DOCX + PDF + ZIP Export</span>
              <span class="pill">Team Workspace RBAC</span>
            </div>
          </div>
          <div class="hero-box">
            <div>
              <div style="font-size:12px; opacity:0.8; margin-bottom:6px;">Active Workspace</div>
              <div style="font-size:19px; font-weight:800; margin-bottom:6px;" id="heroWorkspaceName">Loading...</div>
              <div style="font-size:12.5px; opacity:0.88; line-height:1.45;" id="heroWorkspaceMeta">
                Synced with real database persistence, AI summary engine, and document exporter.
              </div>
            </div>
            <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
              <button class="btn primary" onclick="switchView('detail')">Open Latest Transcript</button>
              <button class="btn" style="background:rgba(255,255,255,0.14);color:#fff;border-color:rgba(255,255,255,0.22);" onclick="seedSampleWorkspaceData()">Load Sample Data</button>
            </div>
          </div>
        </div>

        <div class="grid-4">
          <div class="card">
            <div class="stat-label">Total Transcripts</div>
            <div class="stat-value" id="statTranscriptCount">0</div>
            <div class="stat-sub" id="statTranscriptSub">In active workspace</div>
          </div>
          <div class="card">
            <div class="stat-label">Speaker Segments</div>
            <div class="stat-value" id="statSegmentCount">0</div>
            <div class="stat-sub">Diarized &amp; searchable</div>
          </div>
          <div class="card">
            <div class="stat-label">Active Projects</div>
            <div class="stat-value" id="statProjectCount">0</div>
            <div class="stat-sub">Organized folders</div>
          </div>
          <div class="card">
            <div class="stat-label">Export Packages</div>
            <div class="stat-value" id="statExportCount">0</div>
            <div class="stat-sub">DOCX, PDF &amp; ZIP ready</div>
          </div>
        </div>

        <div class="grid-2">
          <div class="card">
            <div class="section-head">
              <div>
                <h3>Recent Transcripts</h3>
                <span>Click any recording to view speaker segments, AI summaries, and action items</span>
              </div>
              <button class="btn sm" onclick="switchView('transcripts')">View All</button>
            </div>
            <div class="list" id="dashboardRecentList"></div>
          </div>

          <div style="display:flex; flex-direction:column; gap:18px;">
            <div class="card">
              <div class="section-head">
                <div>
                  <h3>Quick Actions</h3>
                  <span>Core PLAUD Companion workflows</span>
                </div>
              </div>
              <div class="quick-grid">
                <div class="quick" onclick="switchView('transcripts'); document.getElementById('fileUploadInput').click();">
                  <strong>📥 Import Recordings</strong>
                  <small>Upload TXT, SRT, DOCX, or PDF transcripts from PLAUD app exports.</small>
                </div>
                <div class="quick" onclick="switchView('assistant')">
                  <strong>🤖 Ask AI Across Files</strong>
                  <small>Query decisions, risks, and follow-ups across your entire workspace.</small>
                </div>
                <div class="quick" onclick="switchView('export')">
                  <strong>📦 Bulk Export Word &amp; PDF</strong>
                  <small>Generate formatted DOCX/PDF reports bundled in a single ZIP archive.</small>
                </div>
                <div class="quick" onclick="switchView('projects')">
                  <strong>📁 Manage Projects &amp; Team</strong>
                  <small>Group recordings by client/initiative and invite workspace collaborators.</small>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="section-head">
                <div>
                  <h3>PLAUD Hardware &amp; AI Readiness</h3>
                  <span>System architecture status</span>
                </div>
                <span class="tag green">Connected</span>
              </div>
              <p style="margin:0; font-size:13px; color:var(--muted); line-height:1.6;">
                Transcript parser, speaker segmenter, grounded AI assistant, and OOXML/PDF document generator are active. Connect your preferred AI provider key in <strong>AI Assistant</strong> or <strong>Settings</strong> for live LLM synthesis.
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- VIEW 2: TRANSCRIPTS -->
      <section class="content" id="transcripts">
        <div class="card">
          <input type="file" id="fileUploadInput" multiple accept=".txt,.srt,.docx,.pdf,.md" style="display:none;" onchange="handleFileUpload(this.files)" />
          <div class="upload-zone" id="uploadDropZone" onclick="document.getElementById('fileUploadInput').click()">
            <div style="font-size:28px;">📄</div>
            <h4>Drop PLAUD transcript files here or click to browse</h4>
            <p>Supports multi-file upload: <strong>.TXT, .SRT, .DOCX, .PDF</strong> — automatically parses speaker labels and timestamps</p>
            <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap;" onclick="event.stopPropagation();">
              <button class="btn primary" onclick="document.getElementById('fileUploadInput').click()">Select Transcript Files</button>
              <button class="btn" onclick="openModal('newTranscriptModal')">+ Paste Raw Transcript Text</button>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="section-head">
            <div>
              <h3>Transcript Library</h3>
              <span>Select transcripts for bulk export or click Open to inspect AI insights</span>
            </div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
              <select id="transcriptProjectFilter" class="form-select" style="width:auto; min-width:170px;" onchange="renderTranscriptsTable()">
                <option value="all">All Projects</option>
              </select>
              <button class="btn primary sm" onclick="switchView('export')">Export Selected</button>
            </div>
          </div>

          <div style="overflow-x:auto;">
            <table class="table">
              <thead>
                <tr>
                  <th><input type="checkbox" id="selectAllTranscripts" onchange="toggleSelectAllTranscripts(this.checked)" /></th>
                  <th>Title</th>
                  <th>Project</th>
                  <th>Source</th>
                  <th>Segments</th>
                  <th>Status</th>
                  <th>Updated</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="transcriptTableBody"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- VIEW 3: TRANSCRIPT DETAIL -->
      <section class="content" id="detail">
        <div class="card">
          <div class="section-head" style="margin-bottom:0;">
            <div>
              <span class="tag green" id="detailProjectBadge">Workspace Transcript</span>
              <h2 id="detailTitle" style="margin:8px 0 4px; font-size:22px;">Select a transcript</h2>
              <span id="detailMeta" style="font-size:12.5px; color:var(--muted);">Choose a transcript from Dashboard or Transcripts Library</span>
            </div>
            <div style="display:flex; gap:10px; flex-wrap:wrap;">
              <select id="detailTranscriptSelect" class="form-select" style="width:auto; min-width:220px;" onchange="openTranscriptDetail(this.value)"></select>
              <button class="btn" onclick="switchView('assistant')">Ask AI About This</button>
              <button class="btn primary" onclick="quickExportCurrentTranscript()">Export DOCX + PDF</button>
            </div>
          </div>
        </div>

        <div class="split-detail">
          <div class="card">
            <div class="section-head">
              <div>
                <h3>Speaker-Diarized Transcript</h3>
                <span id="detailSegmentCountLabel">0 segments</span>
              </div>
            </div>
            <div class="transcript-box" id="detailSegmentsBox"></div>
          </div>

          <div style="display:flex; flex-direction:column; gap:18px;">
            <div class="card">
              <div class="section-head">
                <div>
                  <h3>Executive AI Summary</h3>
                  <span>Grounded synthesis of key points &amp; decisions</span>
                </div>
                <div style="display:flex; gap:6px; align-items:center;">
                  <select id="summaryTypeSelect" class="form-select" style="width:auto; padding:6px 10px; font-size:12px;">
                    <option value="executive">Executive</option>
                    <option value="concise">Concise</option>
                    <option value="detailed">Detailed</option>
                    <option value="meeting_minutes">Meeting Minutes</option>
                  </select>
                  <button class="btn primary sm" id="btnGenerateSummary" onclick="generateTranscriptSummary()">✨ Generate</button>
                </div>
              </div>
              <div id="detailSummaryBox" style="font-size:13.5px; color:var(--slate); line-height:1.65; white-space:pre-wrap;">
                Click "✨ Generate" to create a structured AI summary for this transcript.
              </div>
            </div>

            <div class="card">
              <div class="section-head">
                <div>
                  <h3>Extracted Action Items</h3>
                  <span>Owners, tasks, and follow-up commitments</span>
                </div>
                <button class="btn sm" id="btnExtractActions" onclick="generateTranscriptActions()">⚡ Extract Actions</button>
              </div>
              <div id="detailActionsBox">
                <p style="font-size:13px; color:var(--muted); margin:0;">Click "⚡ Extract Actions" to detect tasks and owners from this transcript.</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- VIEW 4: AI ASSISTANT & CONNECTORS -->
      <section class="content" id="assistant">
        <div class="card">
          <div class="section-head" style="margin-bottom:0;">
            <div>
              <h3>1-Click AI Provider Connectors</h3>
              <span>Connect OpenRouter (recommended free/multi-model), Google Gemini, Anthropic Claude, or OpenAI</span>
            </div>
            <div style="display:flex; gap:8px; align-items:center;">
              <label style="font-size:12px; font-weight:700; color:var(--muted);">Active Provider:</label>
              <select id="aiProviderSelect" class="form-select" style="width:auto;">
                <option value="auto">Auto (Best Connected / Built-in Fallback)</option>
                <option value="openrouter">OpenRouter</option>
                <option value="gemini">Google Gemini</option>
                <option value="claude">Anthropic Claude</option>
                <option value="openai">OpenAI</option>
              </select>
            </div>
          </div>
          <div class="grid-4" id="connectorsGrid" style="margin-top:14px;"></div>
        </div>

        <div class="chat-shell">
          <div class="card">
            <div class="section-head">
              <div>
                <h3>One-Click Prompts</h3>
                <span>Run across active workspace transcripts</span>
              </div>
            </div>
            <div class="prompt-list">
              <button class="prompt-btn" onclick="sendQuickPrompt('Summarize the key decisions and outcomes across our recent transcripts.')">Summarize key decisions across meetings</button>
              <button class="prompt-btn" onclick="sendQuickPrompt('List every action item, owner, and deadline mentioned in these transcripts.')">Extract all action items &amp; owners</button>
              <button class="prompt-btn" onclick="sendQuickPrompt('What are the main client concerns, blockers, or risks discussed?')">Identify client concerns &amp; risks</button>
              <button class="prompt-btn" onclick="sendQuickPrompt('Draft a professional follow-up email summarizing next steps from our latest call.')">Draft follow-up email with next steps</button>
              <button class="prompt-btn" onclick="sendQuickPrompt('Compare product priorities and timeline commitments across our sessions.')">Compare priorities &amp; timeline commitments</button>
            </div>
          </div>

          <div class="card chat-box">
            <div>
              <div class="section-head">
                <div>
                  <h3>Workspace Grounded AI Assistant</h3>
                  <span>Answers are grounded in your workspace transcripts with source citations</span>
                </div>
                <span class="tag green" id="chatContextBadge">Workspace Context Active</span>
              </div>

              <div class="messages" id="chatMessages">
                <div class="msg ai">Hello {{ $user['display_name'] }}! I have indexed your workspace transcripts. Ask me anything about meeting decisions, action items, speaker quotes, or project updates.</div>
              </div>
            </div>

            <div class="chat-input">
              <input id="chatInput" type="text" placeholder="Ask a question across your workspace transcripts..." onkeydown="if(event.key==='Enter') sendChat()" />
              <button class="btn primary" id="btnSendChat" onclick="sendChat()">Send</button>
            </div>
          </div>
        </div>
      </section>

      <!-- VIEW 5: BULK EXPORT -->
      <section class="content" id="export">
        <div class="grid-2">
          <div class="card">
            <div class="section-head">
              <div>
                <h3>Bulk Export Builder</h3>
                <span>Generate formatted Microsoft Word (.DOCX), PDF, and ZIP packages</span>
              </div>
              <span class="tag green">Real OOXML &amp; PDF Engine</span>
            </div>

            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:6px;">1. Select Output Format</label>
              <div class="export-options" id="exportFormatOptions">
                <div class="option-card selected" data-format="both" onclick="selectExportFormat('both', this)">
                  <strong>DOCX + PDF Bundle (.ZIP)</strong>
                  <p style="margin:6px 0 0; font-size:12px; color:var(--muted);">Generates both editable Word files and print-ready PDFs inside a ZIP archive.</p>
                </div>
                <div class="option-card" data-format="docx" onclick="selectExportFormat('docx', this)">
                  <strong>Microsoft Word (.DOCX)</strong>
                  <p style="margin:6px 0 0; font-size:12px; color:var(--muted);">Formatted headings, metadata table, speaker turns, and AI notes.</p>
                </div>
                <div class="option-card" data-format="pdf" onclick="selectExportFormat('pdf', this)">
                  <strong>Portable Document (.PDF)</strong>
                  <p style="margin:6px 0 0; font-size:12px; color:var(--muted);">Clean executive layout ready for client distribution.</p>
                </div>
              </div>
            </div>

            <div style="margin-bottom:14px;">
              <label style="display:block; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:6px;">2. Document Template</label>
              <select id="exportTemplateSelect" class="form-select">
                <option value="meeting_minutes">Meeting Minutes (Metadata + Summary + Actions + Full Transcript)</option>
                <option value="executive_summary">Executive Summary Brief</option>
                <option value="transcript_with_actions">Transcript with Action Items</option>
                <option value="clean_transcript">Clean Verbatim Speaker Transcript</option>
              </select>
            </div>

            <div style="margin-bottom:16px;">
              <div class="check-row"><input type="checkbox" id="expIncludeSummary" checked /> <span>Include AI Executive Summary section</span></div>
              <div class="check-row"><input type="checkbox" id="expIncludeActions" checked /> <span>Include Extracted Action Items checklist</span></div>
            </div>

            <div>
              <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                <label style="font-size:12px; font-weight:700; color:var(--muted);">3. Transcripts to Include</label>
                <button class="btn sm" onclick="toggleSelectAllExportList()">Select / Deselect All</button>
              </div>
              <div id="exportTranscriptChecklist" style="max-height:220px; overflow-y:auto; border:1px solid var(--line); border-radius:12px; padding:8px 12px;"></div>
            </div>

            <div style="margin-top:16px; display:flex; gap:10px;">
              <button class="btn primary" id="btnRunExport" onclick="runBulkExport()">📦 Generate Export Package</button>
            </div>
          </div>

          <div class="card">
            <div class="section-head">
              <div>
                <h3>Generated Export Packages</h3>
                <span>Download your completed ZIP archives containing DOCX and PDF files</span>
              </div>
              <button class="btn sm" onclick="loadWorkspaceExports()">↻ Refresh</button>
            </div>
            <div class="list" id="exportJobsList"></div>
          </div>
        </div>
      </section>

      <!-- VIEW 6: PROJECTS & TEAM -->
      <section class="content" id="projects">
        <div class="card">
          <div class="section-head">
            <div>
              <h3>Projects &amp; Recording Folders</h3>
              <span>Organize transcripts by client, department, or research stream</span>
            </div>
            <button class="btn primary" onclick="openModal('newProjectModal')">+ New Project</button>
          </div>
          <div class="grid-3" id="projectsGrid"></div>
        </div>

        <div class="grid-2">
          <div class="card">
            <div class="section-head">
              <div>
                <h3>Workspace Members &amp; Roles</h3>
                <span>Role-based access control (Owner, Admin, Editor, Viewer)</span>
              </div>
            </div>
            <div class="list" id="membersList"></div>
          </div>

          <div class="card">
            <div class="section-head">
              <div>
                <h3>Invite Team Member</h3>
                <span>Add collaborators to the active workspace</span>
              </div>
            </div>
            <div style="display:flex; flex-direction:column; gap:12px;">
              <div>
                <label style="display:block; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:4px;">Collaborator Email</label>
                <input type="email" id="inviteEmailInput" class="form-input" placeholder="colleague@company.com" />
              </div>
              <div>
                <label style="display:block; font-size:12px; font-weight:700; color:var(--muted); margin-bottom:4px;">Workspace Role</label>
                <select id="inviteRoleSelect" class="form-select">
                  <option value="editor">Editor (Import, edit, run AI &amp; export)</option>
                  <option value="admin">Admin (Manage projects, members &amp; connectors)</option>
                  <option value="viewer">Viewer (Read &amp; search transcripts)</option>
                </select>
              </div>
              <button class="btn primary" onclick="inviteWorkspaceMember()">Send Workspace Invitation</button>
            </div>
          </div>
        </div>
      </section>

      <!-- VIEW 7: SETTINGS (WITH SUPER-ADMIN ONLY SYSTEM UPDATES & BACKUPS) -->
      <section class="content" id="settings">
        <div class="grid-2">
          <div class="card">
            <div class="section-head">
              <div>
                <h3>Account &amp; Workspace Profile</h3>
                <span>Authenticated session details</span>
              </div>
              @if($user['is_super_admin'])
                <span class="tag green">Super-Admin</span>
              @else
                <span class="tag blue">Standard User</span>
              @endif
            </div>
            <div class="check-row"><strong>Display Name:</strong> <span>{{ $user['display_name'] }}</span></div>
            <div class="check-row"><strong>Email Address:</strong> <span>{{ $user['email'] }}</span></div>
            <div class="check-row"><strong>Application Version:</strong> <span>v{{ $currentVersion }}</span></div>
            <div class="check-row"><strong>Export Templates:</strong> <span>DOCX (OOXML) + PDF + ZIP</span></div>
          </div>

          <div class="card">
            <div class="section-head">
              <div>
                <h3>PLAUD Sync &amp; Storage Policy</h3>
                <span>Security &amp; data handling configuration</span>
              </div>
              <span class="tag green">Enforced</span>
            </div>
            <div class="check-row"><strong>Private Storage Isolation:</strong> <span>Workspace-scoped RBAC checks active</span></div>
            <div class="check-row"><strong>Prompt Injection Guard:</strong> <span>Transcripts treated as untrusted data</span></div>
            <div class="check-row"><strong>Supported Imports:</strong> <span>.TXT, .SRT, .DOCX, .PDF (up to 50 MB)</span></div>
            <div class="check-row"><strong>Direct PLAUD Cloud Sync:</strong> <span>Adapter interface ready for partner API</span></div>
          </div>
        </div>

        @if($user['is_super_admin'])
        <!-- SUPER-ADMIN ONLY: INSTALLATION, UPDATES & BACKUPS -->
        <div class="card" style="border: 2px solid rgba(24, 168, 121, 0.35);">
          <div class="section-head">
            <div>
              <div style="display:flex; align-items:center; gap:8px;">
                <h3>System Installation, Signed Updates &amp; Backups</h3>
                <span class="tag green">Super-Admin Only</span>
              </div>
              <span>Manage full server backups, verify Ed25519-signed release ZIPs, apply atomic updates, or roll back</span>
            </div>
            <div style="display:flex; gap:8px;">
              <button class="btn sm" onclick="loadAdminUpdaterStatus()">↻ Refresh Status</button>
              <a href="{{ url('/settings/updates') }}" class="btn sm">Open Full Updater Console ↗</a>
            </div>
          </div>

          <div class="grid-3" style="margin-bottom:18px;">
            <div style="border:1px solid var(--line); border-radius:14px; padding:14px; background:var(--surface-alt);">
              <div class="stat-label">Installed Version</div>
              <div style="font-size:20px; font-weight:800;" id="admCurrentVersion">v{{ $currentVersion }}</div>
              <div style="font-size:12px; color:var(--muted); margin-top:4px;" id="admLockState">Lock: Validating...</div>
            </div>
            <div style="border:1px solid var(--line); border-radius:14px; padding:14px; background:var(--surface-alt);">
              <div class="stat-label">1. Pre-Update Full Backup</div>
              <p style="font-size:12px; color:var(--muted); margin:4px 0 10px;">Snapshots SQL tables + .env + app code into a downloadable ZIP.</p>
              <button class="btn primary sm" id="btnAdminBackup" onclick="createAdminBackup()">Create &amp; Download Backup</button>
            </div>
            <div style="border:1px solid var(--line); border-radius:14px; padding:14px; background:var(--surface-alt);">
              <div class="stat-label">2. Upload Signed Release ZIP</div>
              <p style="font-size:12px; color:var(--muted); margin:4px 0 10px;">Upload <code>update-full.zip</code> or <code>update-patch.zip</code> to verify &amp; apply.</p>
              <input type="file" id="adminReleaseZipInput" accept=".zip" style="display:none;" onchange="uploadAdminReleaseZip(this.files[0])" />
              <button class="btn secondary sm" onclick="document.getElementById('adminReleaseZipInput').click()">Upload Signed Release ZIP</button>
            </div>
          </div>

          <div id="adminStagedReleaseBox" style="display:none; border:1px solid var(--emerald); background:var(--emerald-soft); border-radius:14px; padding:14px; margin-bottom:16px;"></div>

          <div class="grid-2">
            <div>
              <h4 style="margin:0 0 10px; font-size:14px;">Available Server Backups</h4>
              <div class="list" id="adminBackupsList"></div>
            </div>
            <div>
              <h4 style="margin:0 0 10px; font-size:14px;">Update History</h4>
              <div class="list" id="adminUpdateRunsList"></div>
            </div>
          </div>
        </div>
        @endif
      </section>
    </main>
  </div>

  <!-- MOBILE BOTTOM BAR -->
  <div class="mobile-bar" id="mobileNav">
    <button class="active" data-view="dashboard">Home</button>
    <button data-view="transcripts">Files</button>
    <button data-view="detail">Detail</button>
    <button data-view="assistant">AI</button>
    <button data-view="export">Export</button>
    <button data-view="settings">Settings</button>
  </div>

  <!-- MODAL: NEW WORKSPACE -->
  <div class="modal-backdrop" id="workspaceModal">
    <div class="modal">
      <h3>Create New Workspace</h3>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Workspace Name</label>
          <input type="text" id="newWorkspaceName" class="form-input" placeholder="e.g., Executive Strategy Team" />
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
          <button class="btn" onclick="closeModal('workspaceModal')">Cancel</button>
          <button class="btn primary" onclick="createWorkspace()">Create Workspace</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: PASTE / NEW TRANSCRIPT -->
  <div class="modal-backdrop" id="newTranscriptModal">
    <div class="modal">
      <h3>Create / Paste Transcript</h3>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Transcript Title</label>
          <input type="text" id="pasteTranscriptTitle" class="form-input" placeholder="e.g., Q4 Board Strategy Sync" />
        </div>
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Assign to Project (Optional)</label>
          <select id="pasteTranscriptProject" class="form-select">
            <option value="">No Project</option>
          </select>
        </div>
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Transcript Content (Speaker: Text format supported)</label>
          <textarea id="pasteTranscriptBody" class="form-textarea" rows="7" placeholder="[00:00] Speaker 1: Welcome everyone to the meeting.&#10;[00:18] Speaker 2: Let's review the roadmap priorities and action items."></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
          <button class="btn" onclick="closeModal('newTranscriptModal')">Cancel</button>
          <button class="btn primary" onclick="createPastedTranscript()">Save Transcript</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: NEW PROJECT -->
  <div class="modal-backdrop" id="newProjectModal">
    <div class="modal">
      <h3>Create Project Folder</h3>
      <div style="display:flex; flex-direction:column; gap:12px;">
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Project Name</label>
          <input type="text" id="newProjectName" class="form-input" placeholder="e.g., Enterprise Client Discovery" />
        </div>
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Description</label>
          <textarea id="newProjectDesc" class="form-textarea" rows="3" placeholder="Brief summary of recordings in this project..."></textarea>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
          <button class="btn" onclick="closeModal('newProjectModal')">Cancel</button>
          <button class="btn primary" onclick="createProject()">Create Project</button>
        </div>
      </div>
    </div>
  </div>

  <!-- MODAL: CONNECT AI PROVIDER -->
  <div class="modal-backdrop" id="connectAiModal">
    <div class="modal">
      <h3 id="connectAiTitle">Connect AI Provider</h3>
      <input type="hidden" id="connectAiProviderId" />
      <div style="display:flex; flex-direction:column; gap:12px;">
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">API Key</label>
          <input type="password" id="connectAiKeyInput" class="form-input" placeholder="Paste provider API key..." />
        </div>
        <div>
          <label style="display:block; font-size:12px; font-weight:700; margin-bottom:4px;">Preferred Model</label>
          <select id="connectAiModelSelect" class="form-select"></select>
        </div>
        <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:8px;">
          <button class="btn" onclick="closeModal('connectAiModal')">Cancel</button>
          <button class="btn primary" onclick="saveAiConnector()">Save &amp; Connect</button>
        </div>
      </div>
    </div>
  </div>

  <div class="toast-wrap" id="toastWrap"></div>

  <script>
    const INITIAL_WORKSPACES = @json($workspaces);
    const INITIAL_ACTIVE_WS_ID = @json($activeWorkspaceId);
    const IS_SUPER_ADMIN = @json($user['is_super_admin']);
    const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    let state = {
      workspaces: INITIAL_WORKSPACES || [],
      activeWorkspaceId: INITIAL_ACTIVE_WS_ID,
      projects: [],
      transcripts: [],
      selectedTranscriptIds: new Set(),
      currentDetailTranscript: null,
      connectors: [],
      exports: [],
      members: [],
      exportFormat: 'both',
      searchQuery: '',
      stagedUploadId: null
    };

    function showToast(msg, isError = false) {
      const wrap = document.getElementById('toastWrap');
      const el = document.createElement('div');
      el.className = 'toast' + (isError ? ' error' : '');
      el.textContent = msg;
      wrap.appendChild(el);
      setTimeout(() => el.remove(), 4200);
    }

    async function apiFetch(url, options = {}) {
      const headers = Object.assign({
        'Accept': 'application/json',
        'X-CSRF-TOKEN': CSRF_TOKEN
      }, options.headers || {});

      if (options.body && !(options.body instanceof FormData) && typeof options.body === 'object') {
        headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(options.body);
      }

      const res = await fetch(url, Object.assign({}, options, {
        headers,
        credentials: 'same-origin'
      }));

      const contentType = res.headers.get('content-type') || '';
      const data = contentType.includes('application/json') ? await res.json() : await res.text();
      if (!res.ok) {
        const errMsg = (data && (data.detail || data.message || data.error)) || `Request failed (${res.status})`;
        throw new Error(errMsg);
      }
      return data;
    }

    function openModal(id) { document.getElementById(id).classList.add('open'); }
    function closeModal(id) { document.getElementById(id).classList.remove('open'); }

    function switchView(viewId) {
      document.querySelectorAll('.content').forEach(s => s.classList.remove('active'));
      const target = document.getElementById(viewId);
      if (target) target.classList.add('active');

      document.querySelectorAll('#sideNav button, #mobileNav button').forEach(b => {
        b.classList.toggle('active', b.dataset.view === viewId);
      });

      if (viewId === 'settings' && IS_SUPER_ADMIN) {
        loadAdminUpdaterStatus();
      }
    }

    document.querySelectorAll('#sideNav button, #mobileNav button').forEach(btn => {
      btn.addEventListener('click', () => switchView(btn.dataset.view));
    });

    function renderWorkspaceSelector() {
      const sel = document.getElementById('workspaceSelect');
      sel.innerHTML = state.workspaces.map(w =>
        `<option value="${w.id}" ${w.id === state.activeWorkspaceId ? 'selected' : ''}>${escapeHtml(w.name)}</option>`
      ).join('');

      const activeWs = state.workspaces.find(w => w.id === state.activeWorkspaceId);
      if (activeWs) {
        document.getElementById('heroWorkspaceName').textContent = activeWs.name;
      }
    }

    async function switchWorkspace(wsId) {
      state.activeWorkspaceId = wsId;
      state.selectedTranscriptIds.clear();
      renderWorkspaceSelector();
      await loadWorkspaceData();
    }

    async function createWorkspace() {
      const name = document.getElementById('newWorkspaceName').value.trim();
      if (!name) return showToast('Please enter a workspace name', true);
      try {
        const ws = await apiFetch('/app/api/workspaces', {
          method: 'POST',
          body: { name }
        });
        state.workspaces.unshift(ws);
        state.activeWorkspaceId = ws.id;
        document.getElementById('newWorkspaceName').value = '';
        closeModal('workspaceModal');
        renderWorkspaceSelector();
        await loadWorkspaceData();
        showToast(`Workspace "${ws.name}" created`);
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function loadWorkspaceData() {
      if (!state.activeWorkspaceId) return;
      try {
        const [projects, transcripts, connectors, exportsList, members] = await Promise.all([
          apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/projects`),
          apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/transcripts`),
          apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/ai/connectors`),
          apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/exports`),
          apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/members`)
        ]);

        state.projects = projects || [];
        state.transcripts = transcripts || [];
        state.connectors = (connectors && connectors.providers) ? connectors.providers : [];
        state.exports = exportsList || [];
        state.members = members || [];

        if (state.selectedTranscriptIds.size === 0 && state.transcripts.length > 0) {
          state.transcripts.forEach(t => state.selectedTranscriptIds.add(t.id));
        }

        renderDashboard();
        renderProjectSelectors();
        renderTranscriptsTable();
        renderProjectsAndMembers();
        renderConnectors();
        renderExportView();

        if (state.transcripts.length > 0) {
          const currentId = state.currentDetailTranscript?.id;
          const stillExists = state.transcripts.find(t => t.id === currentId);
          await openTranscriptDetail((stillExists || state.transcripts[0]).id, false);
        } else {
          renderEmptyDetail();
        }
      } catch (e) {
        showToast('Failed loading workspace data: ' + e.message, true);
      }
    }

    function renderDashboard() {
      const totalTranscripts = state.transcripts.length;
      const totalSegments = state.transcripts.reduce((acc, t) => acc + (t.segment_count || 0), 0);

      document.getElementById('navTranscriptCount').textContent = totalTranscripts;
      document.getElementById('navProjectCount').textContent = state.projects.length;
      document.getElementById('statTranscriptCount').textContent = totalTranscripts;
      document.getElementById('statSegmentCount').textContent = totalSegments;
      document.getElementById('statProjectCount').textContent = state.projects.length;
      document.getElementById('statExportCount').textContent = state.exports.length;

      const recentEl = document.getElementById('dashboardRecentList');
      if (state.transcripts.length === 0) {
        recentEl.innerHTML = `<div style="padding:18px; text-align:center; color:var(--muted); font-size:13px;">No transcripts yet. Click <strong>Load Sample Data</strong> or <strong>+ Import Files</strong> to get started.</div>`;
        return;
      }

      const projectMap = Object.fromEntries(state.projects.map(p => [p.id, p.name]));
      recentEl.innerHTML = state.transcripts.slice(0, 5).map(t => {
        const projName = t.project_id && projectMap[t.project_id] ? projectMap[t.project_id] : 'General';
        return `
          <div class="item" onclick="openTranscriptDetail('${t.id}', true)">
            <div class="item-left">
              <div class="icon-box">${escapeHtml((t.source_type || 'TXT').slice(0, 3).toUpperCase())}</div>
              <div>
                <div class="item-title">${escapeHtml(t.title)}</div>
                <div class="item-meta">
                  <span>Folder: ${escapeHtml(projName)}</span>
                  <span>•</span>
                  <span>${t.segment_count || 0} segments</span>
                  <span>•</span>
                  <span>${formatDate(t.created_at)}</span>
                </div>
              </div>
            </div>
            <span class="tag green">${escapeHtml(t.status || 'ready')}</span>
          </div>
        `;
      }).join('');
    }

    function renderProjectSelectors() {
      const filterSel = document.getElementById('transcriptProjectFilter');
      const pasteSel = document.getElementById('pasteTranscriptProject');
      const currentFilter = filterSel.value || 'all';

      filterSel.innerHTML = `<option value="all">All Projects (${state.transcripts.length})</option>` +
        state.projects.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
      filterSel.value = currentFilter;

      pasteSel.innerHTML = `<option value="">No Project</option>` +
        state.projects.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');

      const detailSel = document.getElementById('detailTranscriptSelect');
      detailSel.innerHTML = state.transcripts.map(t =>
        `<option value="${t.id}">${escapeHtml(t.title)}</option>`
      ).join('');
    }

    function renderTranscriptsTable() {
      const tbody = document.getElementById('transcriptTableBody');
      const projFilter = document.getElementById('transcriptProjectFilter').value;
      const q = state.searchQuery.toLowerCase();
      const projectMap = Object.fromEntries(state.projects.map(p => [p.id, p.name]));

      const filtered = state.transcripts.filter(t => {
        if (projFilter !== 'all' && t.project_id !== projFilter) return false;
        if (q && !t.title.toLowerCase().includes(q) && !(t.raw_text || '').toLowerCase().includes(q)) return false;
        return true;
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:24px; color:var(--muted);">No matching transcripts found.</td></tr>`;
        return;
      }

      tbody.innerHTML = filtered.map(t => {
        const checked = state.selectedTranscriptIds.has(t.id) ? 'checked' : '';
        const projName = t.project_id && projectMap[t.project_id] ? projectMap[t.project_id] : 'Unassigned';
        return `
          <tr>
            <td><input type="checkbox" ${checked} onchange="toggleTranscriptSelection('${t.id}', this.checked)" /></td>
            <td><strong style="cursor:pointer; color:var(--navy);" onclick="openTranscriptDetail('${t.id}', true)">${escapeHtml(t.title)}</strong></td>
            <td><span class="tag">${escapeHtml(projName)}</span></td>
            <td>${escapeHtml((t.source_type || 'txt').toUpperCase())}</td>
            <td>${t.segment_count || 0}</td>
            <td><span class="tag green">${escapeHtml(t.status || 'ready')}</span></td>
            <td>${formatDate(t.updated_at || t.created_at)}</td>
            <td>
              <div style="display:flex; gap:6px;">
                <button class="btn sm" onclick="openTranscriptDetail('${t.id}', true)">Open</button>
                <button class="btn danger sm" onclick="deleteTranscript('${t.id}')">Delete</button>
              </div>
            </td>
          </tr>
        `;
      }).join('');
    }

    function toggleTranscriptSelection(id, checked) {
      if (checked) state.selectedTranscriptIds.add(id);
      else state.selectedTranscriptIds.delete(id);
      renderExportView();
    }

    function toggleSelectAllTranscripts(checked) {
      if (checked) state.transcripts.forEach(t => state.selectedTranscriptIds.add(t.id));
      else state.selectedTranscriptIds.clear();
      renderTranscriptsTable();
      renderExportView();
    }

    async function handleFileUpload(fileList) {
      if (!fileList || fileList.length === 0) return;
      for (const file of Array.from(fileList)) {
        const fd = new FormData();
        fd.append('file', file);
        try {
          showToast(`Importing ${file.name}...`);
          await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/imports`, {
            method: 'POST',
            body: fd
          });
          showToast(`Imported ${file.name} successfully`);
        } catch (e) {
          showToast(`Import failed (${file.name}): ${e.message}`, true);
        }
      }
      document.getElementById('fileUploadInput').value = '';
      await loadWorkspaceData();
    }

    async function createPastedTranscript() {
      const title = document.getElementById('pasteTranscriptTitle').value.trim();
      const content_text = document.getElementById('pasteTranscriptBody').value.trim();
      const project_id = document.getElementById('pasteTranscriptProject').value || null;

      if (!title || !content_text) return showToast('Please provide both title and transcript text', true);
      try {
        const created = await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/transcripts`, {
          method: 'POST',
          body: { title, content_text, raw_text: content_text, project_id, source_type: 'manual_paste' }
        });
        document.getElementById('pasteTranscriptTitle').value = '';
        document.getElementById('pasteTranscriptBody').value = '';
        closeModal('newTranscriptModal');
        await loadWorkspaceData();
        await openTranscriptDetail(created.id, true);
        showToast('Transcript saved and segmented');
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function deleteTranscript(id) {
      if (!confirm('Delete this transcript from the workspace?')) return;
      try {
        await apiFetch(`/app/api/transcripts/${id}`, { method: 'DELETE' });
        state.selectedTranscriptIds.delete(id);
        showToast('Transcript deleted');
        await loadWorkspaceData();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function openTranscriptDetail(transcriptId, navigate = false) {
      if (!transcriptId) return;
      try {
        const detail = await apiFetch(`/app/api/transcripts/${transcriptId}`);
        state.currentDetailTranscript = detail;

        const detailSel = document.getElementById('detailTranscriptSelect');
        if (detailSel) detailSel.value = detail.id;

        const projectMap = Object.fromEntries(state.projects.map(p => [p.id, p.name]));
        document.getElementById('detailProjectBadge').textContent =
          (detail.project_id && projectMap[detail.project_id]) ? projectMap[detail.project_id] : 'Workspace Transcript';
        document.getElementById('detailTitle').textContent = detail.title;
        document.getElementById('detailMeta').textContent =
          `Source: ${(detail.source_type || 'txt').toUpperCase()} • ${detail.segment_count || 0} segments • Updated ${formatDate(detail.updated_at || detail.created_at)}`;
        document.getElementById('detailSegmentCountLabel').textContent = `${(detail.segments || []).length} speaker turns`;

        const segBox = document.getElementById('detailSegmentsBox');
        const segments = detail.segments || [];
        if (segments.length === 0) {
          segBox.innerHTML = `<div class="utterance"><p>${escapeHtml(detail.raw_text || 'No text content.')}</p></div>`;
        } else {
          segBox.innerHTML = segments.map(s => `
            <div class="utterance">
              <div class="utterance-head">
                <span>${escapeHtml(s.speaker_label || 'Speaker')}</span>
                <span>${formatMs(s.start_ms)}</span>
              </div>
              <p>${escapeHtml(s.text)}</p>
            </div>
          `).join('');
        }

        document.getElementById('detailSummaryBox').textContent =
          'Click "✨ Generate" to synthesize an executive AI summary for "' + detail.title + '".';
        document.getElementById('detailActionsBox').innerHTML =
          '<p style="font-size:13px; color:var(--muted); margin:0;">Click "⚡ Extract Actions" to extract tasks and owners from "' + escapeHtml(detail.title) + '".</p>';

        if (navigate) switchView('detail');
      } catch (e) {
        showToast('Could not load transcript detail: ' + e.message, true);
      }
    }

    function renderEmptyDetail() {
      document.getElementById('detailTitle').textContent = 'No transcript selected';
      document.getElementById('detailMeta').textContent = 'Import or create a transcript to inspect speaker segments';
      document.getElementById('detailSegmentsBox').innerHTML = '';
    }

    async function generateTranscriptSummary() {
      if (!state.currentDetailTranscript) return showToast('Select a transcript first', true);
      const btn = document.getElementById('btnGenerateSummary');
      const summaryType = document.getElementById('summaryTypeSelect').value;
      const provider = document.getElementById('aiProviderSelect').value;
      btn.disabled = true;
      btn.textContent = 'Generating...';
      try {
        const res = await apiFetch(`/app/api/transcripts/${state.currentDetailTranscript.id}/summary`, {
          method: 'POST',
          body: { summary_type: summaryType, provider }
        });
        document.getElementById('detailSummaryBox').textContent = res.summary_markdown || 'No summary returned.';
        showToast(`AI summary generated (${res.provider}/${res.model})`);
      } catch (e) {
        showToast(e.message, true);
      } finally {
        btn.disabled = false;
        btn.textContent = '✨ Generate';
      }
    }

    async function generateTranscriptActions() {
      if (!state.currentDetailTranscript) return showToast('Select a transcript first', true);
      const btn = document.getElementById('btnExtractActions');
      const provider = document.getElementById('aiProviderSelect').value;
      btn.disabled = true;
      btn.textContent = 'Extracting...';
      try {
        const items = await apiFetch(`/app/api/transcripts/${state.currentDetailTranscript.id}/action-items`, {
          method: 'POST',
          body: { provider }
        });
        const box = document.getElementById('detailActionsBox');
        if (!items || items.length === 0) {
          box.innerHTML = '<p style="font-size:13px; color:var(--muted);">No explicit action items detected.</p>';
        } else {
          box.innerHTML = items.map(it => `
            <div class="check-row">
              <input type="checkbox" />
              <div>
                <strong>${escapeHtml(it.owner || 'Team')}:</strong> ${escapeHtml(it.task)}
                ${it.due_date ? `<span class="tag amber" style="margin-left:6px;">Due: ${escapeHtml(it.due_date)}</span>` : ''}
              </div>
            </div>
          `).join('');
        }
        showToast(`Extracted ${items.length} action items`);
      } catch (e) {
        showToast(e.message, true);
      } finally {
        btn.disabled = false;
        btn.textContent = '⚡ Extract Actions';
      }
    }

    function renderConnectors() {
      const grid = document.getElementById('connectorsGrid');
      if (!state.connectors || state.connectors.length === 0) {
        grid.innerHTML = '';
        return;
      }
      grid.innerHTML = state.connectors.map(c => `
        <div style="border:1px solid var(--line); border-radius:14px; padding:14px; background:#fff; display:flex; flex-direction:column; justify-content:space-between; gap:10px;">
          <div>
            <div style="display:flex; justify-content:space-between; align-items:center; gap:6px; margin-bottom:6px;">
              <strong style="font-size:13.5px;">${escapeHtml(c.name)}</strong>
              <span class="tag ${c.connected ? 'green' : ''}">${c.connected ? 'Connected' : 'Optional'}</span>
            </div>
            <div style="font-size:11.5px; color:var(--muted);">Model: <code>${escapeHtml(c.active_model || c.default_model)}</code></div>
            ${c.masked_key ? `<div style="font-size:11px; color:var(--emerald-dark); margin-top:4px;">Key: ${escapeHtml(c.masked_key)}</div>` : ''}
          </div>
          <div style="display:flex; gap:6px; flex-wrap:wrap;">
            <button class="btn sm primary" onclick="openConnectAiModal('${c.provider}')">${c.connected ? 'Update Key' : '1-Click Connect'}</button>
            <button class="btn sm" onclick="testAiConnector('${c.provider}')">Test</button>
          </div>
        </div>
      `).join('');
    }

    function openConnectAiModal(providerId) {
      const c = state.connectors.find(x => x.provider === providerId);
      if (!c) return;
      document.getElementById('connectAiProviderId').value = c.provider;
      document.getElementById('connectAiTitle').textContent = `Connect ${c.name}`;
      document.getElementById('connectAiKeyInput').value = '';
      const sel = document.getElementById('connectAiModelSelect');
      sel.innerHTML = (c.models || [c.default_model]).map(m =>
        `<option value="${m}" ${m === c.active_model ? 'selected' : ''}>${m}</option>`
      ).join('');
      openModal('connectAiModal');
    }

    async function saveAiConnector() {
      const provider = document.getElementById('connectAiProviderId').value;
      const api_key = document.getElementById('connectAiKeyInput').value.trim();
      const model = document.getElementById('connectAiModelSelect').value;
      if (!api_key) return showToast('Please enter an API key', true);
      try {
        await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/ai/connectors/${provider}/connect`, {
          method: 'POST',
          body: { api_key, model }
        });
        closeModal('connectAiModal');
        showToast(`${provider.toUpperCase()} connected successfully`);
        await loadWorkspaceData();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function testAiConnector(provider) {
      try {
        showToast(`Testing ${provider}...`);
        const res = await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/ai/connectors/${provider}/test`, {
          method: 'POST'
        });
        showToast(`${provider}: ${res.status} (${res.model || ''})`);
      } catch (e) {
        showToast(e.message, true);
      }
    }

    function appendChatMessage(role, text) {
      const wrap = document.getElementById('chatMessages');
      const div = document.createElement('div');
      div.className = 'msg ' + role;
      div.textContent = text;
      wrap.appendChild(div);
      wrap.scrollTop = wrap.scrollHeight;
    }

    function sendQuickPrompt(promptText) {
      document.getElementById('chatInput').value = promptText;
      sendChat();
    }

    async function sendChat() {
      const input = document.getElementById('chatInput');
      const question = input.value.trim();
      if (!question) return;
      input.value = '';
      appendChatMessage('user', question);

      const btn = document.getElementById('btnSendChat');
      btn.disabled = true;
      const provider = document.getElementById('aiProviderSelect').value;
      try {
        const res = await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/ai/ask`, {
          method: 'POST',
          body: {
            question,
            provider,
            transcript_ids: Array.from(state.selectedTranscriptIds)
          }
        });
        let answerText = res.answer || 'No answer returned.';
        if (res.citations && res.citations.length > 0) {
          const cites = res.citations.slice(0, 3).map(c => `[${c.speaker_label || 'Speaker'}] "${c.quote}"`).join('\n');
          answerText += `\n\nGrounded Sources (${res.provider}/${res.model}):\n${cites}`;
        }
        appendChatMessage('ai', answerText);
      } catch (e) {
        appendChatMessage('ai', 'Error: ' + e.message);
      } finally {
        btn.disabled = false;
      }
    }

    function selectExportFormat(fmt, el) {
      state.exportFormat = fmt;
      document.querySelectorAll('#exportFormatOptions .option-card').forEach(c => c.classList.remove('selected'));
      el.classList.add('selected');
    }

    function renderExportView() {
      const checklist = document.getElementById('exportTranscriptChecklist');
      if (state.transcripts.length === 0) {
        checklist.innerHTML = `<div style="padding:12px; color:var(--muted); font-size:12.5px;">No transcripts available to export.</div>`;
      } else {
        checklist.innerHTML = state.transcripts.map(t => {
          const checked = state.selectedTranscriptIds.has(t.id) ? 'checked' : '';
          return `
            <div class="check-row">
              <input type="checkbox" ${checked} onchange="toggleTranscriptSelection('${t.id}', this.checked)" />
              <span><strong>${escapeHtml(t.title)}</strong> <small style="color:var(--muted);">(${t.segment_count || 0} segments)</small></span>
            </div>
          `;
        }).join('');
      }
      renderExportJobsList();
    }

    function toggleSelectAllExportList() {
      const allSelected = state.selectedTranscriptIds.size === state.transcripts.length;
      toggleSelectAllTranscripts(!allSelected);
    }

    function renderExportJobsList() {
      const list = document.getElementById('exportJobsList');
      if (!state.exports || state.exports.length === 0) {
        list.innerHTML = `<div style="padding:18px; text-align:center; color:var(--muted); font-size:13px;">No export packages generated yet.</div>`;
        return;
      }
      list.innerHTML = state.exports.map(job => `
        <div class="item" style="cursor:default;">
          <div class="item-left">
            <div class="icon-box">ZIP</div>
            <div>
              <div class="item-title">${escapeHtml((job.format || 'both').toUpperCase())} • ${escapeHtml(job.template || 'meeting_minutes')}</div>
              <div class="item-meta">
                <span>${(job.transcript_ids || []).length} transcripts</span>
                <span>•</span>
                <span>${formatDate(job.created_at)}</span>
              </div>
            </div>
          </div>
          <a class="btn primary sm" href="/app/api/exports/${job.id}/download">⬇ Download ZIP</a>
        </div>
      `).join('');
    }

    async function loadWorkspaceExports() {
      try {
        state.exports = await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/exports`);
        renderExportJobsList();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function runBulkExport() {
      const ids = Array.from(state.selectedTranscriptIds);
      if (ids.length === 0) return showToast('Select at least one transcript to export', true);
      const btn = document.getElementById('btnRunExport');
      btn.disabled = true;
      btn.textContent = 'Generating DOCX/PDF ZIP...';
      try {
        const job = await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/exports`, {
          method: 'POST',
          body: {
            transcript_ids: ids,
            format: state.exportFormat,
            template: document.getElementById('exportTemplateSelect').value,
            include_ai_summary: document.getElementById('expIncludeSummary').checked,
            include_action_items: document.getElementById('expIncludeActions').checked
          }
        });
        showToast('Export package ready! Starting download...');
        await loadWorkspaceExports();
        document.getElementById('statExportCount').textContent = state.exports.length;
        window.location.href = `/app/api/exports/${job.id}/download`;
      } catch (e) {
        showToast(e.message, true);
      } finally {
        btn.disabled = false;
        btn.textContent = '📦 Generate Export Package';
      }
    }

    async function quickExportCurrentTranscript() {
      if (!state.currentDetailTranscript) return;
      state.selectedTranscriptIds.add(state.currentDetailTranscript.id);
      switchView('export');
      await runBulkExport();
    }

    function renderProjectsAndMembers() {
      const pGrid = document.getElementById('projectsGrid');
      if (state.projects.length === 0) {
        pGrid.innerHTML = `<div style="color:var(--muted); font-size:13px;">No projects yet. Click <strong>+ New Project</strong> to create one.</div>`;
      } else {
        pGrid.innerHTML = state.projects.map(p => {
          const count = state.transcripts.filter(t => t.project_id === p.id).length;
          return `
            <div class="card" style="box-shadow:none; cursor:pointer;" onclick="filterByProject('${p.id}')">
              <span class="tag green">${count} Transcripts</span>
              <h3 style="margin:12px 0 6px;">${escapeHtml(p.name)}</h3>
              <p style="margin:0; color:var(--muted); font-size:13px; line-height:1.5;">${escapeHtml(p.description || 'Project recording folder')}</p>
            </div>
          `;
        }).join('');
      }

      const mList = document.getElementById('membersList');
      mList.innerHTML = state.members.map(m => `
        <div class="item" style="cursor:default;">
          <div class="item-left">
            <div class="icon-box">${escapeHtml((m.display_name || m.email || 'U').slice(0, 2).toUpperCase())}</div>
            <div>
              <div class="item-title">${escapeHtml(m.display_name || m.email)}</div>
              <div class="item-meta"><span>${escapeHtml(m.email)}</span></div>
            </div>
          </div>
          <span class="tag blue">${escapeHtml((m.role || 'member').toUpperCase())}</span>
        </div>
      `).join('');
    }

    function filterByProject(projectId) {
      document.getElementById('transcriptProjectFilter').value = projectId;
      renderTranscriptsTable();
      switchView('transcripts');
    }

    async function createProject() {
      const name = document.getElementById('newProjectName').value.trim();
      const description = document.getElementById('newProjectDesc').value.trim();
      if (!name) return showToast('Enter a project name', true);
      try {
        await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/projects`, {
          method: 'POST',
          body: { name, description }
        });
        document.getElementById('newProjectName').value = '';
        document.getElementById('newProjectDesc').value = '';
        closeModal('newProjectModal');
        showToast('Project created');
        await loadWorkspaceData();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function inviteWorkspaceMember() {
      const email = document.getElementById('inviteEmailInput').value.trim();
      const role = document.getElementById('inviteRoleSelect').value;
      if (!email) return showToast('Enter collaborator email', true);
      try {
        await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/invitations`, {
          method: 'POST',
          body: { email, role }
        });
        document.getElementById('inviteEmailInput').value = '';
        showToast(`Invitation created for ${email} (${role})`);
        await loadWorkspaceData();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    async function seedSampleWorkspaceData() {
      try {
        const res = await apiFetch(`/app/api/workspaces/${state.activeWorkspaceId}/seed-sample`, {
          method: 'POST'
        });
        showToast(res.created_transcripts > 0
          ? `Added ${res.created_transcripts} sample transcripts`
          : 'Sample transcripts are already present in this workspace');
        await loadWorkspaceData();
      } catch (e) {
        showToast(e.message, true);
      }
    }

    function handleGlobalSearch(val) {
      state.searchQuery = val.trim();
      renderTranscriptsTable();
      if (state.searchQuery.length > 1) {
        switchView('transcripts');
      }
    }

    // SUPER-ADMIN ONLY: UPDATER & BACKUP FUNCTIONS
    async function loadAdminUpdaterStatus() {
      if (!IS_SUPER_ADMIN) return;
      try {
        const st = await apiFetch('/settings/updates/api/status');
        document.getElementById('admCurrentVersion').textContent = 'v' + (st.current_version || '0.0.0');
        document.getElementById('admLockState').textContent = st.lock?.active ? 'Lock: Active update in progress' : 'Lock: Idle & Ready';

        const bList = document.getElementById('adminBackupsList');
        const backups = st.backups || [];
        bList.innerHTML = backups.length === 0
          ? `<div style="font-size:12.5px; color:var(--muted);">No backups created yet.</div>`
          : backups.map(b => `
              <div class="item" style="cursor:default; padding:10px 12px;">
                <div>
                  <div style="font-weight:700; font-size:12.5px;">${escapeHtml(b.filename)}</div>
                  <div style="font-size:11.5px; color:var(--muted);">${formatBytes(b.size_bytes)} • ${formatDate(b.created_at)}</div>
                </div>
                <div style="display:flex; gap:6px;">
                  <a class="btn sm" href="/settings/updates/api/backups/${encodeURIComponent(b.filename)}">⬇ ZIP</a>
                  <button class="btn danger sm" onclick="rollbackToAdminBackup('${escapeHtml(b.filename)}')">Rollback</button>
                </div>
              </div>
            `).join('');

        const rList = document.getElementById('adminUpdateRunsList');
        const runs = st.recent_runs || [];
        rList.innerHTML = runs.length === 0
          ? `<div style="font-size:12.5px; color:var(--muted);">No update runs recorded yet.</div>`
          : runs.map(r => `
              <div class="item" style="cursor:default; padding:10px 12px;">
                <div>
                  <div style="font-weight:700; font-size:12.5px;">v${escapeHtml(r.from_version || '?')} → v${escapeHtml(r.to_version || '?')} (${escapeHtml(r.mode || 'full')})</div>
                  <div style="font-size:11.5px; color:var(--muted);">${escapeHtml(r.state)} • ${formatDate(r.started_at)}</div>
                </div>
                <span class="tag ${r.state === 'SUCCEEDED' ? 'green' : 'amber'}">${escapeHtml(r.state)}</span>
              </div>
            `).join('');
      } catch (e) {
        showToast('Updater status error: ' + e.message, true);
      }
    }

    async function createAdminBackup() {
      if (!IS_SUPER_ADMIN) return;
      const btn = document.getElementById('btnAdminBackup');
      btn.disabled = true;
      btn.textContent = 'Creating Backup...';
      try {
        const res = await apiFetch('/settings/updates/api/backup', { method: 'POST' });
        showToast(`Backup created: ${res.filename}`);
        await loadAdminUpdaterStatus();
        if (res.download_url) window.location.href = res.download_url;
      } catch (e) {
        showToast(e.message, true);
      } finally {
        btn.disabled = false;
        btn.textContent = 'Create & Download Backup';
      }
    }

    async function uploadAdminReleaseZip(file) {
      if (!IS_SUPER_ADMIN || !file) return;
      const fd = new FormData();
      fd.append('release_zip', file);
      try {
        showToast('Uploading & verifying Ed25519 signature...');
        const res = await apiFetch('/settings/updates/api/upload', {
          method: 'POST',
          body: fd
        });
        state.stagedUploadId = res.upload_id;
        const box = document.getElementById('adminStagedReleaseBox');
        box.style.display = 'block';
        box.innerHTML = `
          <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
            <div>
              <strong>✅ Verified Signed Release v${escapeHtml(res.manifest?.version || '')} (${escapeHtml(res.manifest?.mode || 'full')})</strong>
              <div style="font-size:12px; color:var(--slate); margin-top:4px;">Ed25519 signature and SHA-256 manifest verified. Ready to apply.</div>
            </div>
            <button class="btn primary sm" onclick="applyAdminStagedUpdate('${escapeHtml(res.upload_id)}')">Apply Update Now</button>
          </div>
        `;
        showToast('Release signature verified!');
      } catch (e) {
        showToast(e.message, true);
      } finally {
        document.getElementById('adminReleaseZipInput').value = '';
      }
    }

    async function applyAdminStagedUpdate(uploadId) {
      if (!confirm('Create automatic backup and apply this signed update now?')) return;
      try {
        showToast('Applying update (creating backup, syncing files, running migrations)...');
        const res = await apiFetch('/settings/updates/api/apply', {
          method: 'POST',
          body: { upload_id: uploadId }
        });
        showToast(`Updated to v${res.to_version}! Reloading...`);
        setTimeout(() => window.location.reload(), 1500);
      } catch (e) {
        showToast('Update failed: ' + e.message, true);
        await loadAdminUpdaterStatus();
      }
    }

    async function rollbackToAdminBackup(filename) {
      if (!confirm(`Roll back database and application files to backup "${filename}"?`)) return;
      try {
        showToast(`Rolling back to ${filename}...`);
        await apiFetch('/settings/updates/api/rollback', {
          method: 'POST',
          body: { backup_filename: filename }
        });
        showToast('Rollback completed! Reloading...');
        setTimeout(() => window.location.reload(), 1500);
      } catch (e) {
        showToast('Rollback error: ' + e.message, true);
      }
    }

    function escapeHtml(str) {
      return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function formatDate(iso) {
      if (!iso) return 'Just now';
      try {
        const d = new Date(iso);
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
      } catch {
        return iso;
      }
    }

    function formatMs(ms) {
      const totalSec = Math.floor((ms || 0) / 1000);
      const m = String(Math.floor(totalSec / 60)).padStart(2, '0');
      const s = String(totalSec % 60).padStart(2, '0');
      return `${m}:${s}`;
    }

    function formatBytes(bytes) {
      if (!bytes) return '0 B';
      const mb = bytes / (1024 * 1024);
      return mb >= 1 ? `${mb.toFixed(2)} MB` : `${(bytes / 1024).toFixed(1)} KB`;
    }

    // Drag & drop upload support
    const dropZone = document.getElementById('uploadDropZone');
    if (dropZone) {
      ['dragenter', 'dragover'].forEach(ev => dropZone.addEventListener(ev, e => {
        e.preventDefault();
        dropZone.classList.add('dragover');
      }));
      ['dragleave', 'drop'].forEach(ev => dropZone.addEventListener(ev, e => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
      }));
      dropZone.addEventListener('drop', e => {
        if (e.dataTransfer?.files?.length) handleFileUpload(e.dataTransfer.files);
      });
    }

    // Initialize workspace on page load
    renderWorkspaceSelector();
    loadWorkspaceData();
  </script>
</body>
</html>
