<?php

namespace Tests\Feature;

use App\Models\Transcript;
use App\Models\TranscriptFile;
use App\Models\User;
use App\Services\DataBackupService;
use App\Services\WorkspaceService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class DataBackupTest extends TestCase
{
    private function seedTranscriptWithFile(string $userId): array
    {
        $ws = app(WorkspaceService::class)->createWorkspace($userId, 'Backup WS', 'personal');
        $t = Transcript::create([
            'id' => (string) Str::uuid(), 'workspace_id' => $ws['id'], 'created_by_user_id' => $userId,
            'title' => 'Important meeting', 'source_type' => 'docx_import', 'status' => 'processed',
            'content_text' => 'secret content', 'content_hash' => hash('sha256', 'secret content'),
        ]);
        $key = "workspaces/{$ws['id']}/imports/original.txt";
        Storage::disk('local')->put($key, 'FILE-BODY-123');
        TranscriptFile::create([
            'id' => (string) Str::uuid(), 'transcript_id' => $t->id, 'workspace_id' => $ws['id'],
            'storage_key' => $key, 'original_filename' => 'original.txt', 'mime_type' => 'text/plain',
            'size_bytes' => 13, 'sha256' => hash('sha256', 'FILE-BODY-123'), 'file_role' => 'original', 'created_at' => now(),
        ]);

        return [$ws['id'], $t->id, $key];
    }

    public function test_backup_file_is_encrypted_and_round_trips(): void
    {
        $admin = $this->createSuperAdmin();
        [$wsId, $transcriptId, $key] = $this->seedTranscriptWithFile($admin->id);
        $service = app(DataBackupService::class);

        $backup = $service->create('correct horse battery');
        $raw = file_get_contents($backup['path']);
        $this->assertStringNotContainsString('secret content', $raw, 'Transcript text must not appear in plaintext.');
        $this->assertStringNotContainsString('FILE-BODY-123', $raw, 'File bodies must not appear in plaintext.');
        $this->assertGreaterThanOrEqual(1, $backup['files']);

        // Destroy the live data and the file.
        Transcript::query()->delete();
        User::query()->where('id', $admin->id)->delete();
        Storage::disk('local')->delete($key);
        $this->assertSame(0, Transcript::count());

        $result = $service->restore($backup['path'], 'correct horse battery');

        $this->assertGreaterThan(0, $result['rows']);
        $this->assertTrue($result['app_key_matched']);
        $this->assertSame(1, Transcript::query()->where('id', $transcriptId)->count());
        $this->assertSame('secret content', Transcript::find($transcriptId)->content_text);
        $this->assertNotNull(User::find($admin->id));
        $this->assertSame('FILE-BODY-123', Storage::disk('local')->get($key));
    }

    public function test_wrong_passphrase_is_rejected(): void
    {
        $this->createSuperAdmin();
        $service = app(DataBackupService::class);
        $backup = $service->create('the-right-passphrase');

        $this->expectException(\App\Services\BackupPassphraseException::class);
        $service->restore($backup['path'], 'the-wrong-passphrase');
    }

    public function test_short_passphrase_rejected(): void
    {
        $this->createSuperAdmin();
        $this->expectExceptionMessage('at least 10 characters');
        app(DataBackupService::class)->create('short');
    }

    public function test_endpoints_require_super_admin(): void
    {
        $this->actingAsWebUser($this->createUser())->getJson('/settings/updates/api/data-backups')->assertStatus(403);
    }

    public function test_create_and_list_and_download_via_http(): void
    {
        $admin = $this->createSuperAdmin();
        $this->seedTranscriptWithFile($admin->id);

        $this->actingAsWebUser($admin)
            ->postJson('/settings/updates/api/data-backups', ['passphrase' => 'a-strong-passphrase'])
            ->assertOk()->assertJson(['success' => true]);

        $list = $this->actingAsWebUser($admin)->getJson('/settings/updates/api/data-backups')->assertOk()->json('backups');
        $this->assertCount(1, $list);
        $filename = $list[0]['filename'];

        $this->actingAsWebUser($admin)
            ->get("/settings/updates/api/data-backups/{$filename}/download")
            ->assertOk()->assertHeader('content-disposition', "attachment; filename={$filename}");

        $this->actingAsWebUser($admin)->delete("/settings/updates/api/data-backups/{$filename}")->assertOk();
        $this->assertCount(0, $this->actingAsWebUser($admin)->getJson('/settings/updates/api/data-backups')->json('backups'));
    }

    public function test_download_rejects_path_traversal(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAsWebUser($admin)->get('/settings/updates/api/data-backups/..%2F..%2F.env/download')->assertStatus(404);
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/private/backups/*.plaudbackup') ?: [] ) as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }
}
