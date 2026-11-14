<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class PlaudSyncService
{
    private const DEFAULT_DOMAIN = 'platform-us.plaud.ai';

    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    /**
     * Get the current PLAUD integration status for a workspace.
     *
     * @return array<string, mixed>
     */
    public function getStatus(string $workspaceId, string $userId): array
    {
        $this->workspaces->requireMembership($workspaceId, $userId);

        $conn = DB::table('provider_connections')
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'plaud')
            ->first();

        $bleDevice = DB::table('audit_events')
            ->where('workspace_id', $workspaceId)
            ->where('action', 'device.ble_paired')
            ->orderByDesc('created_at')
            ->first();

        $bleInfo = null;
        if ($bleDevice && !empty($bleDevice->metadata)) {
            $bleInfo = json_decode((string) $bleDevice->metadata, true);
        }

        return [
            'cloud_sync' => [
                'enabled' => (bool) ($conn->enabled ?? false),
                'connected' => !empty($conn->api_key_encrypted),
                'server_domain' => self::DEFAULT_DOMAIN,
                'client_id' => $conn ? 'plaud_app_'.substr((string) $conn->id, 0, 8) : null,
                'last_synced_at' => $conn->updated_at ?? null,
            ],
            'ble_bridge' => [
                'supported' => true,
                'paired' => $bleInfo !== null,
                'device_name' => $bleInfo['device_name'] ?? 'PLAUD Note / NotePin (Ready to Pair)',
                'device_id' => $bleInfo['device_id'] ?? null,
                'battery_level' => $bleInfo['battery_level'] ?? null,
                'service_uuid' => '0000fee7-0000-1000-8000-00805f9b34fb',
            ],
        ];
    }

    /**
     * Connect or update PLAUD Cloud Open API credentials.
     *
     * @return array<string, mixed>
     */
    public function connectCloud(
        string $workspaceId,
        string $userId,
        string $apiKey,
        ?string $serverDomain = null
    ): array {
        $this->workspaces->requireMembership($workspaceId, $userId, ['owner', 'admin']);

        $domain = trim((string) ($serverDomain ?: self::DEFAULT_DOMAIN));
        $now = gmdate('Y-m-d H:i:s');

        $existing = DB::table('provider_connections')
            ->where('workspace_id', $workspaceId)
            ->where('provider', 'plaud')
            ->first();

        if ($existing) {
            DB::table('provider_connections')
                ->where('id', $existing->id)
                ->update([
                    'api_key_encrypted' => $apiKey,
                    'default_model' => $domain,
                    'enabled' => true,
                    'updated_at' => $now,
                ]);
            $connId = $existing->id;
        } else {
            $connId = (string) Str::uuid();
            DB::table('provider_connections')->insert([
                'id' => $connId,
                'workspace_id' => $workspaceId,
                'provider' => 'plaud',
                'api_key_encrypted' => $apiKey,
                'auth_mode' => 'oauth_token',
                'enabled' => true,
                'default_model' => $domain,
                'created_by_user_id' => $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'action' => 'integration.plaud_connected',
            'resource_type' => 'provider_connection',
            'resource_id' => $connId,
            'metadata' => ['domain' => $domain],
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => 'PLAUD Cloud API credentials connected successfully.',
            'server_domain' => $domain,
        ];
    }

    /**
     * Pair a BLE device with the active workspace.
     *
     * @param array<string, mixed> $deviceData
     * @return array<string, mixed>
     */
    public function registerBleDevice(string $workspaceId, string $userId, array $deviceData): array
    {
        $this->workspaces->requireMembership($workspaceId, $userId, ['owner', 'admin', 'editor']);

        $deviceName = trim((string) ($deviceData['device_name'] ?? 'PLAUD Note'));
        $deviceId = trim((string) ($deviceData['device_id'] ?? Str::uuid()->toString()));
        $battery = isset($deviceData['battery_level']) ? (int) $deviceData['battery_level'] : 95;

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'action' => 'device.ble_paired',
            'resource_type' => 'hardware_device',
            'resource_id' => $deviceId,
            'metadata' => [
                'device_name' => $deviceName,
                'device_id' => $deviceId,
                'battery_level' => $battery,
                'protocol' => 'BLE_GATT',
                'service_uuid' => '0000fee7-0000-1000-8000-00805f9b34fb',
                'paired_at' => gmdate('c'),
            ],
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Paired with {$deviceName} via Bluetooth Low Energy.",
            'device' => [
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'battery_level' => $battery,
            ],
        ];
    }

    /**
     * Sync recording metadata and transcripts directly into the workspace.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function ingestDeviceSync(string $workspaceId, string $userId, array $payload): array
    {
        $this->workspaces->requireMembership($workspaceId, $userId, ['owner', 'admin', 'editor']);

        $title = trim((string) ($payload['title'] ?? 'PLAUD Recording ' . date('Y-m-d H:i')));
        $contentText = trim((string) ($payload['content_text'] ?? ''));
        if (empty($contentText)) {
            throw new HttpResponseException(
                response()->json(['detail' => 'Recording transcript content is required.'], 422)
            );
        }

        $sourceType = trim((string) ($payload['source_type'] ?? 'plaud_ble_sync'));
        $contentHash = hash('sha256', $contentText);
        $durationMs = isset($payload['duration_ms']) ? (int) $payload['duration_ms'] : null;

        $transcript = DB::transaction(function () use (
            $workspaceId,
            $userId,
            $title,
            $contentText,
            $contentHash,
            $sourceType,
            $durationMs,
            $payload
        ): Transcript {
            $t = Transcript::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'project_id' => $payload['project_id'] ?? null,
                'created_by_user_id' => $userId,
                'title' => $title,
                'description' => "Synced from {$sourceType}" . ($durationMs ? ' (' . round($durationMs / 60000, 1) . ' min)' : ''),
                'source_type' => $sourceType,
                'recorded_at' => isset($payload['recorded_at']) ? new \DateTime($payload['recorded_at']) : now(),
                'language' => $payload['language'] ?? 'en',
                'status' => 'processed',
                'content_text' => $contentText,
                'content_hash' => $contentHash,
            ]);

            $segments = $payload['segments'] ?? [];
            if (empty($segments)) {
                $lines = preg_split('/\R+/', $contentText) ?: [];
                $seq = 1;
                $cursorMs = 0;
                foreach ($lines as $line) {
                    $l = trim($line);
                    if ($l === '') continue;
                    $speaker = 'Speaker ' . ($seq % 2 === 1 ? '1' : '2');
                    $body = $l;
                    if (preg_match('/^\[?(\d{1,2}):(\d{2})(?::(\d{2}))?\]?\s*([^:]{1,50}):\s*(.+)$/', $l, $m)) {
                        $start = ((int) $m[1] * 60 + (int) $m[2]) * 1000;
                        $speaker = trim($m[4]);
                        $body = trim($m[5]);
                    } else {
                        $start = $cursorMs;
                    }
                    $end = $start + 12000;
                    $cursorMs = $end;

                    TranscriptSegment::create([
                        'id' => (string) Str::uuid(),
                        'transcript_id' => $t->id,
                        'sequence_number' => $seq++,
                        'speaker_label' => $speaker,
                        'start_ms' => $start,
                        'end_ms' => $end,
                        'text' => $body,
                    ]);
                }
            } else {
                foreach ($segments as $idx => $s) {
                    TranscriptSegment::create([
                        'id' => (string) Str::uuid(),
                        'transcript_id' => $t->id,
                        'sequence_number' => $idx + 1,
                        'speaker_label' => $s['speaker_label'] ?? 'Speaker',
                        'start_ms' => $s['start_ms'] ?? 0,
                        'end_ms' => $s['end_ms'] ?? 0,
                        'text' => $s['text'] ?? '',
                    ]);
                }
            }

            return $t->load('segments');
        });

        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => $workspaceId,
            'actor_user_id' => $userId,
            'action' => 'transcript.plaud_synced',
            'resource_type' => 'transcript',
            'resource_id' => $transcript->id,
            'metadata' => [
                'source_type' => $sourceType,
                'segment_count' => $transcript->segments->count(),
            ],
            'created_at' => now(),
        ]);

        return [
            'success' => true,
            'message' => "Recording '{$transcript->title}' successfully synced and segmented.",
            'transcript_id' => $transcript->id,
            'segment_count' => $transcript->segments->count(),
        ];
    }
}
