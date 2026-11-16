<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Services\PlaudPartnerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PlaudController extends Controller
{
    public function __construct(private readonly PlaudPartnerService $plaud)
    {
    }

    /** Short-lived PLAUD user token for the mobile SDK (device binding + BLE sync). */
    public function userToken(Request $request): JsonResponse
    {
        if (!$this->plaud->configured()) {
            return response()->json([
                'error' => 'plaud_not_configured',
                'message' => 'PLAUD device connection is not enabled on this server yet.',
            ], 503);
        }

        try {
            $issued = $this->plaud->issueUserToken($request->user());
        } catch (RuntimeException $e) {
            report($e);

            return response()->json([
                'error' => 'plaud_unavailable',
                'message' => 'Could not reach PLAUD right now. Please try again shortly.',
            ], 502);
        }

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $request->user()->id,
            'action' => 'plaud.user_token_issued',
            'resource_type' => 'user',
            'resource_id' => $request->user()->id,
            'metadata' => ['expires_in' => $issued['expires_in']],
            'created_at' => now(),
        ]);

        return response()->json($issued)->header('Cache-Control', 'no-store');
    }
}
