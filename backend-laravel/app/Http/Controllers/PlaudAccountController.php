<?php

namespace App\Http\Controllers;

use App\Models\PlaudAccountLink;
use App\Services\PlaudAccountService;
use App\Services\PlaudReconnectRequired;
use App\Services\WorkspaceService;
use App\Support\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PlaudAccountController extends Controller
{
    private const SESSION_KEY = 'plaud_oauth';

    public function __construct(
        private readonly PlaudAccountService $plaud,
        private readonly WorkspaceService $workspaces,
    ) {
    }

    public function connect(Request $request): RedirectResponse
    {
        if (Impersonation::active()) {
            return redirect()->route('profile.show')->with('plaud_error', 'You cannot connect a Plaud account while working as another user.');
        }
        try {
            $auth = $this->plaud->authorizationRequest(route('plaud.account.callback'));
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route('profile.show')->with('plaud_error', 'Plaud is not reachable right now. Please try again in a few minutes.');
        }
        $request->session()->put(self::SESSION_KEY, ['state' => $auth['state'], 'verifier' => $auth['verifier']]);

        return redirect()->away($auth['url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $pending = $request->session()->pull(self::SESSION_KEY);
        if (!is_array($pending) || !hash_equals((string) $pending['state'], (string) $request->query('state'))) {
            return redirect()->route('profile.show')->with('plaud_error', 'The Plaud sign-in could not be confirmed. Please try again.');
        }
        if ($request->filled('error') || !$request->filled('code')) {
            return redirect()->route('profile.show')->with('plaud_error', 'Plaud sign-in was cancelled.');
        }

        $user = $request->user();
        $workspaceId = $this->workspaces->listUserWorkspaces($user->id)[0]['id'] ?? null;
        if ($workspaceId === null) {
            return redirect()->route('profile.show')->with('plaud_error', 'No workspace found for your account.');
        }

        try {
            $link = $this->plaud->completeAuthorization($user, $workspaceId, (string) $request->query('code'), (string) $pending['verifier'], route('plaud.account.callback'));
        } catch (RuntimeException $e) {
            report($e);

            return redirect()->route('profile.show')->with('plaud_error', 'Plaud sign-in failed. Please try again.');
        }

        $message = 'Your Plaud account is connected.';
        try {
            $result = $this->plaud->sync($link);
            $message .= $result['imported'] > 0
                ? " {$result['imported']} recording(s) imported" . ($result['remaining'] ? '; the rest will follow automatically.' : '.')
                : ' New recordings will appear automatically.';
        } catch (RuntimeException $e) {
            report($e);
            $message .= ' Your recordings will be imported shortly.';
        }

        return redirect()->route('profile.show')->with('plaud_status', $message);
    }

    public function sync(Request $request): JsonResponse
    {
        $link = PlaudAccountLink::query()->where('user_id', $request->user()->id)->first();
        if ($link === null) {
            return response()->json(['error' => 'not_connected', 'message' => 'No Plaud account is connected.'], 404);
        }
        $this->workspaces->requireMembership($link->workspace_id, $request->user()->id, ['owner', 'admin', 'editor']);

        try {
            $result = $this->plaud->sync($link);
        } catch (PlaudReconnectRequired $e) {
            return response()->json(['error' => 'reconnect_needed', 'message' => $e->getMessage()], 409);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['error' => 'plaud_unavailable', 'message' => 'Plaud is not reachable right now. We will try again later.'], 502);
        }

        return response()->json($result + ['message' => $result['imported'] > 0
            ? "{$result['imported']} new recording(s) imported from Plaud."
            : 'Everything is up to date.']);
    }

    public function status(Request $request): JsonResponse
    {
        $link = PlaudAccountLink::query()->where('user_id', $request->user()->id)->first();

        return response()->json([
            'connected' => $link !== null,
            'status' => $link?->status,
            'last_synced_at' => $link?->last_synced_at?->toIso8601String(),
            'imported_count' => $link?->imported_count ?? 0,
            'last_error' => $link?->last_error,
        ]);
    }

    public function disconnect(Request $request): RedirectResponse
    {
        if (Impersonation::active()) {
            return redirect()->route('profile.show')->with('plaud_error', 'You cannot disconnect a Plaud account while working as another user.');
        }
        $this->plaud->disconnect($request->user());

        return redirect()->route('profile.show')->with('plaud_status', 'Your Plaud account was disconnected. Recordings already imported stay in your workspace.');
    }
}
