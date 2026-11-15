<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return redirect()->intended(route('app.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
