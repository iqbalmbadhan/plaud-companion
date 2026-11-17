@extends('layouts.shell')

@section('title', 'My Profile')
@section('active', 'profile')

@push('head')
<style>
:root {
            --bg: var(--pc-bg);
            --navy: var(--pc-surface);
            --navy-light: var(--pc-surface-alt);
            --navy-border: var(--pc-border);
            --card: var(--pc-surface);
            --card-sub: var(--pc-surface-alt);
            --border: var(--pc-border);
            --text: var(--pc-text);
            --muted: var(--pc-muted);
            --emerald: var(--pc-emerald);
            --emerald-hover: var(--pc-emerald-hover);
            --emerald-soft: var(--pc-emerald-soft);
            --danger: var(--pc-danger);
            --danger-soft: var(--pc-danger-soft);
        }
.alert-success {
            background: var(--emerald-soft);
            border: 1px solid var(--emerald);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            color: #6ee7b7;
            font-size: 13.5px;
            line-height: 1.4;
        }
.alert-error {
            background: var(--danger-soft);
            border: 1px solid var(--danger);
            border-radius: 12px;
            padding: 14px 18px;
            margin-bottom: 24px;
            color: #fca5a5;
            font-size: 13.5px;
            line-height: 1.4;
        }
.grid-layout {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 24px;
        }
@media (max-width: 860px) {
            .grid-layout {
                grid-template-columns: 1fr;
            }
        }
.card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
        }
.profile-overview {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
.avatar-circle {
            width: 72px;
            height: 72px;
            border-radius: 20px;
            background: linear-gradient(135deg, #2563EB 0%, #18A879 100%);
            display: grid;
            place-items: center;
            font-size: 26px;
            font-weight: 800;
            color: #fff;
            margin: 0 auto 16px;
            box-shadow: 0 8px 24px rgba(24, 168, 121, 0.25);
        }
.profile-name {
            font-size: 19px;
            font-weight: 700;
            margin: 0;
        }
.profile-email {
            font-size: 13px;
            color: var(--muted);
            margin: 4px 0 12px;
        }
.badge-row {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 8px;
        }
.badge {
            font-size: 11px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
.badge.active {
            background: var(--emerald-soft);
            color: var(--emerald);
            border: 1px solid rgba(24, 168, 121, 0.4);
        }
.badge.admin {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
            border: 1px solid rgba(139, 92, 246, 0.4);
        }
.badge.member {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.4);
        }
.member-since {
            font-size: 12px;
            color: var(--muted);
            margin-top: 6px;
        }
.stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin: 20px 0;
        }
.stat-item {
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 8px;
            text-align: center;
        }
.stat-num {
            font-size: 18px;
            font-weight: 800;
            color: var(--emerald);
        }
.stat-label {
            font-size: 11px;
            color: var(--muted);
            margin-top: 4px;
        }
.section-title {
            font-size: 14px;
            font-weight: 700;
            margin: 0 0 14px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }
.workspace-item {
            background: var(--card-sub);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 12px 14px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
.ws-name {
            font-size: 13.5px;
            font-weight: 600;
        }
.ws-role {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 6px;
            background: var(--navy-light);
            color: var(--muted);
            text-transform: capitalize;
        }
.form-group {
            margin-bottom: 18px;
        }
label {
            display: block;
            font-size: 12.5px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 6px;
        }
input[type="text"], input[type="email"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: var(--card-sub);
            color: var(--text);
            font-size: 14px;
            outline: none;
            transition: 0.15s ease;
        }
input:focus {
            border-color: var(--emerald);
            box-shadow: 0 0 0 3px rgba(24, 168, 121, 0.22);
        }
input[disabled], input[readonly] {
            opacity: 0.65;
            cursor: not-allowed;
            background: rgba(15, 26, 48, 0.4);
        }
.help-text {
            font-size: 11.5px;
            color: var(--muted);
            margin-top: 4px;
        }
.btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 11px 18px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            cursor: pointer;
            border: 0;
            transition: all 0.15s ease;
            text-decoration: none;
        }
.btn-primary {
            background: var(--emerald);
            color: #fff;
        }
.btn-primary:hover {
            background: var(--emerald-hover);
            transform: translateY(-1px);
        }
footer.attribution-footer {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: var(--muted);
            background: var(--navy);
        }
</style>
@endpush

@section('content')
<h1 class="page-heading" style="margin:0 0 20px; font-size:20px; font-weight:800;">My Profile &amp; Security</h1>

@if (session('status'))
        <div class="alert-success" role="alert">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert-error" role="alert">
            <strong>Action could not be completed:</strong>
            <ul style="margin:6px 0 0; padding-left:20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid-layout">
        <!-- Left: Overview & Affiliations -->
        <div>
            <div class="card">
                <div class="profile-overview">
                    <div class="avatar-circle">
                        {{ $initials }}
                    </div>
                    <h2 class="profile-name">{{ $user->display_name ?: 'Unnamed User' }}</h2>
                    <p class="profile-email">{{ $user->email }}</p>

                    <div class="badge-row">
                        <span class="badge active">{{ ucfirst($user->status) }}</span>
                        @if ($user->is_super_admin)
                            <span class="badge admin">Super Admin</span>
                        @else
                            <span class="badge member">Member</span>
                        @endif
                    </div>

                    <div class="member-since">
                        Member since {{ $user->created_at ? $user->created_at->format('M d, Y') : 'N/A' }}
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-num">{{ count($workspaces) }}</div>
                        <div class="stat-label">Workspaces</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">{{ $transcriptsCount }}</div>
                        <div class="stat-label">Transcripts</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-num">{{ $exportsCount }}</div>
                        <div class="stat-label">Exports</div>
                    </div>
                </div>

                <div style="margin-top:20px;">
                    <div class="section-title">
                        <span>🏢 Workspace Affiliations</span>
                    </div>
                    @forelse ($workspaces as $item)
                        <div class="workspace-item">
                            <div>
                                <div class="ws-name">{{ $item->workspace?->name ?? 'Workspace' }}</div>
                                <div style="font-size:11px; color:var(--muted); margin-top:2px;">{{ $item->workspace?->type ?? 'personal' }}</div>
                            </div>
                            <span class="ws-role">{{ $item->role }}</span>
                        </div>
                    @empty
                        <div style="font-size:12.5px; color:var(--muted); text-align:center; padding:12px;">No workspace memberships found.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right: Forms -->
        <div>
            <!-- Update Display Name -->
            <div class="card">
                <div class="section-title">
                    <span>✏️ Edit Profile Details</span>
                </div>
                <form method="POST" action="{{ route('profile.update') }}">
                    @csrf
                    <div class="form-group">
                        <label for="display_name">Full Name or Display Name</label>
                        <input id="display_name" type="text" name="display_name" value="{{ old('display_name', $user->display_name) }}" minlength="2" maxlength="100" required>
                        <div class="help-text">This name appears on transcripts, export cover pages, and team logs.</div>
                    </div>

                    <div class="form-group">
                        <label for="email_static">Email Address (Account Identifier)</label>
                        <input id="email_static" type="email" value="{{ $user->email }}" disabled readonly>
                        <div class="help-text">Email address is your unique system identity and cannot be edited self-service.</div>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Profile Changes</button>
                </form>
            </div>

            @include('partials.plaud-account-card')

            <!-- Change Password -->
            <div class="card">
                <div class="section-title">
                    <span>🔐 Change Password</span>
                </div>
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <input id="current_password" type="password" name="current_password" placeholder="Enter your current password" required autocomplete="current-password">
                        <div class="help-text">Verification of your existing password is required for security.</div>
                    </div>

                    <div class="form-group">
                        <label for="password">New Password (min. 8 characters)</label>
                        <input id="password" type="password" name="password" placeholder="Enter new strong password" required minlength="8" autocomplete="new-password">
                    </div>

                    <div class="form-group">
                        <label for="password_confirmation">Confirm New Password</label>
                        <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat new password" required minlength="8" autocomplete="new-password">
                    </div>

                    <button type="submit" class="btn btn-primary">Update Password</button>
                </form>
            </div>
        </div>
    </div>
@endsection
