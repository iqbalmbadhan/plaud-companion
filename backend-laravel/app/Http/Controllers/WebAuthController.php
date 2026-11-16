<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\TokenService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebAuthController extends Controller
{
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (!is_file(storage_path('app/installed.lock'))) {
            return redirect()->route('install.show');
        }

        $user = $request->user();
        if ($user !== null) {
            if ($user->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('suspended', 'Your account has been suspended by an administrator. Please contact support.');
            }

            return redirect()->route('app.dashboard');
        }

        return view('auth.login');
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        if (!is_file(storage_path('app/installed.lock'))) {
            return redirect()->route('install.show');
        }

        $user = $request->user();
        if ($user !== null) {
            if ($user->status !== 'active') {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('suspended', 'Your account has been suspended by an administrator. Please contact support.');
            }

            return redirect()->to('/app');
        }

        return view('auth.register');
    }

    public function showRegisterForm(Request $request): View|RedirectResponse
    {
        return $this->showRegister($request);
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));

        $user = DB::transaction(function () use ($email, $validated): User {
            $pwdHash = defined('PASSWORD_ARGON2ID')
                ? password_hash($validated['password'], PASSWORD_ARGON2ID)
                : password_hash($validated['password'], PASSWORD_BCRYPT);

            $u = User::create([
                'id' => (string) Str::uuid(),
                'email' => $email,
                'display_name' => trim($validated['display_name']),
                'password_hash' => $pwdHash,
                'status' => 'active',
                'is_super_admin' => false,
                'email_verified_at' => now(),
            ]);

            $ws = Workspace::create([
                'id' => (string) Str::uuid(),
                'name' => mb_substr(trim($validated['display_name']) . "'s Workspace", 0, 100),
                'type' => 'personal',
                'owner_user_id' => $u->id,
            ]);

            WorkspaceMember::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $ws->id,
                'user_id' => $u->id,
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            AuditEvent::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $ws->id,
                'actor_user_id' => $u->id,
                'action' => 'user.registered',
                'resource_type' => 'user',
                'resource_id' => $u->id,
                'metadata' => [
                    'email' => $u->email,
                    'display_name' => $u->display_name,
                ],
                'created_at' => now(),
            ]);

            AuditEvent::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $ws->id,
                'actor_user_id' => $u->id,
                'action' => 'workspace.created',
                'resource_type' => 'workspace',
                'resource_id' => $ws->id,
                'metadata' => [
                    'name' => $ws->name,
                    'type' => $ws->type,
                ],
                'created_at' => now(),
            ]);

            return $u;
        });

        Auth::login($user);
        $request->session()->regenerate();
        app(TokenService::class)->markSessionAuthenticated($request);

        return redirect()->to('/app');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($credentials['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user === null || !password_verify($credentials['password'], $user->password_hash)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid email or password.']);
        }

        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $suspendedNotice = 'Your account has been suspended by an administrator. Please contact support.';

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => $suspendedNotice])
                ->with('suspended', $suspendedNotice);
        }

        Auth::login($user, (bool) $request->boolean('remember', false));
        $request->session()->regenerate();
        app(TokenService::class)->markSessionAuthenticated($request);

        return redirect()->intended(route('app.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function showForgotPassword(Request $request): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to('/app');
        }

        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user !== null && $user->status !== 'active') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account has been suspended by an administrator. Password reset is disabled.']);
        }

        if ($user !== null) {
            $plainToken = Str::random(64);
            $hashedToken = hash('sha256', $plainToken);

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            DB::table('password_reset_tokens')->insert([
                'email' => $email,
                'token' => $hashedToken,
                'created_at' => now(),
            ]);

            $resetUrl = route('password.reset', ['token' => $plainToken, 'email' => $email]);

            $mailer = strtolower((string) (config('mail.default') ?? env('MAIL_MAILER', 'log')));
            $isDevOrLocal = !app()->environment('production');
            $isLogOrArray = in_array($mailer, ['log', 'array'], true);

            if ($isDevOrLocal && $isLogOrArray) {
                return back()->with([
                    'status' => 'A password reset link has been generated. Use the link below to set a new password.',
                    'dev_reset_url' => $resetUrl,
                    'reset_url' => $resetUrl,
                ]);
            }

            try {
                Mail::raw("You recently requested to reset your password for PLAUD Companion. Click the link below to set a new password:\n\n{$resetUrl}\n\nThis password reset link will expire in 60 minutes.\nIf you did not request a password reset, no further action is required.", function ($message) use ($user): void {
                    $message->to($user->email, $user->display_name ?: $user->email)
                        ->subject('Reset Your PLAUD Companion Password');
                });
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('status', 'If that email address exists in our system, a password reset link has been dispatched.');
    }

    public function showResetPassword(Request $request, string $token): View|RedirectResponse
    {
        if ($request->user() !== null) {
            return redirect()->to('/app');
        }

        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'We could not find an active account with that email address.']);
        }

        if ($user->status !== 'active') {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This account has been suspended by an administrator. Password reset is disabled.']);
        }

        $hashedToken = hash('sha256', $validated['token']);
        $record = DB::table('password_reset_tokens')
            ->where('email', $email)
            ->where('token', $hashedToken)
            ->first();

        if (!$record) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link is invalid or has already been used.']);
        }

        if ($record->created_at && Carbon::parse($record->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => 'This password reset link has expired. Please request a new one.']);
        }

        $user->password_hash = app(TokenService::class)->hashPassword($validated['password']);
        $user->save();
        // A reset may follow a compromise: end every session and API token.
        app(TokenService::class)->revokeAllSessions($user);

        DB::table('password_reset_tokens')->where('email', $email)->delete();

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $user->id,
            'action' => 'user.password_reset',
            'resource_type' => 'User',
            'resource_id' => $user->id,
            'metadata' => [
                'email' => $user->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            'created_at' => now(),
        ]);

        return redirect()->route('login')->with('status', 'Your password has been reset successfully. Please sign in with your new credentials.');
    }
}
