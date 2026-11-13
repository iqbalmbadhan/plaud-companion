<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'],
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $email = strtolower(trim($validated['email']));
        if (User::query()->where('email', $email)->exists()) {
            return response()->json(['detail' => 'A user with this email address already exists.'], 409);
        }

        $user = DB::transaction(function () use ($email, $validated): User {
            $u = User::create([
                'id' => (string) Str::uuid(),
                'email' => $email,
                'display_name' => trim($validated['display_name']),
                'password_hash' => $this->tokens->hashPassword($validated['password']),
                'status' => 'active',
                'is_super_admin' => false,
            ]);

            $ws = Workspace::create([
                'id' => (string) Str::uuid(),
                'name' => $u->display_name."'s Workspace",
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

            return $u;
        });

        return response()->json($this->tokens->issueTokenPair($user), 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($validated['email']));
        $user = User::query()->where('email', $email)->first();

        if (!$user || !$this->tokens->verifyPassword($validated['password'], $user->password_hash)) {
            return response()->json(['detail' => 'Invalid email or password.'], 401);
        }

        if ($user->status !== 'active') {
            return response()->json(['detail' => 'Account is suspended or pending verification.'], 403);
        }

        return response()->json($this->tokens->issueTokenPair($user));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'display_name' => $user->display_name,
            'status' => $user->status,
            'is_super_admin' => (bool) $user->is_super_admin,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ]);
    }
}
