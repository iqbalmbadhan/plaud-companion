<?php
declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Services\PlaudSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PlaudSyncController extends Controller
{
    public function __construct(private readonly PlaudSyncService $plaudSync)
    {
    }

    public function status(Request $request, string $workspaceId): JsonResponse
    {
        return response()->json($this->plaudSync->getStatus($workspaceId, $request->user()->id));
    }

    public function connect(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => ['required', 'string', 'min:5'],
            'server_domain' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($this->plaudSync->connectCloud(
            $workspaceId,
            $request->user()->id,
            $validated['api_key'],
            $validated['server_domain'] ?? null
        ));
    }

    public function pairBle(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        return response()->json($this->plaudSync->registerBleDevice(
            $workspaceId,
            $request->user()->id,
            $validated
        ));
    }

    public function sync(Request $request, string $workspaceId): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'content_text' => ['required', 'string', 'min:1'],
            'project_id' => ['nullable', 'string'],
            'source_type' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
            'language' => ['nullable', 'string', 'max:20'],
            'duration_ms' => ['nullable', 'integer'],
            'segments' => ['nullable', 'array'],
        ]);

        return response()->json($this->plaudSync->ingestDeviceSync(
            $workspaceId,
            $request->user()->id,
            $validated
        ), 201);
    }
}
