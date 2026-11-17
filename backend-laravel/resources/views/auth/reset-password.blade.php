<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="manifest" href="{{ url('/manifest.json') }}">
    @include('partials.pwa')
    <meta name="theme-color" content="#18A879">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>PLAUD Companion — Reset Password</title>
    @include('partials.theme')
    <style>
        :root {
            --bg: var(--pc-bg);
            --card: var(--pc-surface);
            --border: var(--pc-border);
            --text: var(--pc-text);
            --muted: var(--pc-muted);
            --emerald: var(--pc-emerald);
            --emerald-hover: var(--pc-emerald-hover);
            --danger: var(--pc-danger);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: radial-gradient(circle at 50% 20%, var(--pc-glow) 0%, var(--bg) 70%);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 420px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.45);
        }
        .brand-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 24px;
        }
        .brand-logo {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--emerald) 0%, #37C89A 100%);
            display: grid;
            place-items: center;
            font-weight: 800;
            font-size: 18px;
            color: #fff;
            box-shadow: 0 8px 22px rgba(24, 168, 121, 0.35);
        }
        h1 { margin: 0; font-size: 21px; font-weight: 700; letter-spacing: -0.02em; }
        .sub { margin: 4px 0 0; color: var(--muted); font-size: 13px; }
        label { display: block; font-size: 12.5px; font-weight: 600; color: var(--muted); margin-bottom: 6px; }
        input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--surface-alt);
            color: var(--text);
            font-size: 14px;
            margin-bottom: 16px;
            outline: none;
            transition: 0.15s ease;
        }
        input:focus {
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px rgba(24, 168, 121, 0.22);
        }
        button.btn-primary {
            width: 100%;
            padding: 13px 16px;
            border: 0;
            border-radius: 10px;
            background: var(--emerald);
            color: #fff;
            font-weight: 700;
            font-size: 14.5px;
            cursor: pointer;
            margin-top: 6px;
            transition: background 0.15s ease, transform 0.1s ease;
        }
        button.btn-primary:hover {
            background: var(--emerald-hover);
            transform: translateY(-1px);
        }
        .error {
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fecaca;
            padding: 10px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .security-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 11.5px;
            color: rgba(148, 163, 184, 0.7);
            line-height: 1.5;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="brand-header">
            <div class="brand-logo">PC</div>
            <div>
                <h1>Set New Password</h1>
                <p class="sub">Choose a strong credential for your account</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('password.update') }}" id="resetPasswordForm">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">Work Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" placeholder="name@company.com" required autofocus autocomplete="email">

            <label for="password">New Password (min. 8 characters)</label>
            <input id="password" type="password" name="password" placeholder="Enter new secure password" required minlength="8" autocomplete="new-password">

            <label for="password_confirmation">Confirm New Password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm new password" required minlength="8" autocomplete="new-password">

            <button type="submit" class="btn-primary">Reset Password &amp; Continue</button>
        </form>

        <div style="margin-top: 20px; text-align: center; font-size: 13px; color: var(--muted);">
            <a href="{{ route('login') }}" style="color: var(--emerald); text-decoration: none; font-weight: 600;">&larr; Back to Sign In</a>
        </div>
    </div>

    <footer class="security-footer">
        <div>PLAUD Note &amp; NotePin Integration Bridge &bull; On-Device Encryption</div>
        <div style="margin-top: 10px; font-size: 12px; color: var(--muted); display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; align-items: center;">
            <span>Developed by <a href="https://www.iqbalmahmud.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Md. Iqbal Mahmud</a></span>
            <span style="opacity:0.5;">&bull;</span>
            <span>Technical Advisor: <a href="https://www.dawncsimmons.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">Dawn C Simmons</a></span>
            <span style="opacity:0.5;">&bull;</span>
            <span>Brought to you by <a href="https://www.aiinfos.com" target="_blank" rel="noopener noreferrer" style="color:var(--emerald); text-decoration:none; font-weight:600;">AI Infos</a></span>
        </div>
    </footer>
</div>
    @include('partials.password-eye')
</body>
</html>
