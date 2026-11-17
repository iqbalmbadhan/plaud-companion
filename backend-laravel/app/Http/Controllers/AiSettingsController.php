<?php

namespace App\Http\Controllers;

use App\Services\AiService;
use App\Services\WorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiSettingsController extends Controller
{
    public function __construct(
        private readonly AiService $ai,
        private readonly WorkspaceService $workspaces,
    ) {
    }

    /**
     * Handle the OpenRouter "Easy Connect" OAuth callback.
     * OpenRouter redirects here with ?code=... after the user authorises.
     * We exchange the code for a live API key and save it to the workspace.
     */
    public function oauthCallback(Request $request): RedirectResponse
    {
        $code = (string) $request->query('code', '');
        $codeVerifier = (string) $request->query('cv', '');

        if ($code === '') {
            return redirect()->route('app.dashboard')
                ->with('error', 'OpenRouter authorisation was cancelled or the link expired. Please try again.');
        }

        // Exchange the short-lived code for a permanent API key.
        // Include the PKCE code_verifier when present (required by OpenRouter).
        $payload = ['code' => $code];
        if ($codeVerifier !== '') {
            $payload['code_verifier'] = $codeVerifier;
        }

        $response = Http::timeout(15)
            ->withHeader('Content-Type', 'application/json')
            ->post('https://openrouter.ai/api/v1/auth/keys', $payload);

        if (!$response->successful()) {
            return redirect()->route('app.dashboard')
                ->with('error', 'Could not retrieve your OpenRouter API key. The link may have already been used — please try Easy Connect again.');
        }

        $apiKey = $response->json('key', '');

        if (empty($apiKey)) {
            return redirect()->route('app.dashboard')
                ->with('error', 'OpenRouter returned an empty key. Please try Easy Connect again.');
        }

        $user = $request->user();
        $workspaceId = $this->workspaces->listUserWorkspaces($user->id)[0]['id'] ?? null;

        if ($workspaceId === null) {
            return redirect()->route('app.dashboard')
                ->with('error', 'No workspace found for your account. Create a workspace first, then connect OpenRouter.');
        }

        $result = $this->ai->connectProvider($workspaceId, $user->id, 'openrouter', $apiKey);

        if (!($result['success'] ?? false)) {
            $msg = $result['message'] ?? 'Could not verify the OpenRouter API key.';

            return redirect()->route('app.dashboard')
                ->with('error', "OpenRouter key saved but could not be verified: {$msg} You can re-enter it manually in Settings → AI.");
        }

        return redirect()->route('app.dashboard')
            ->with('success', 'OpenRouter connected successfully. AI features are ready to use.');
    }
}
