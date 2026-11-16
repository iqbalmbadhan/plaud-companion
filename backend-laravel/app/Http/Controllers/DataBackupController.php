<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Services\BackupPassphraseException;
use App\Services\DataBackupService;
use App\Support\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Encrypted client-data backups (super-admin only). The passphrase is required to read a
 * backup and is never stored; audit records never include it.
 */
class DataBackupController extends Controller
{
    public function __construct(private readonly DataBackupService $backups)
    {
    }

    public function index(): JsonResponse
    {
        $dir = $this->backups->backupDir();
        $items = [];
        foreach (glob($dir . DIRECTORY_SEPARATOR . '*.plaudbackup') ?: [] as $path) {
            $info = ['filename' => basename($path), 'size_bytes' => (int) filesize($path)];
            try {
                $info += $this->backups->inspect($path);
            } catch (RuntimeException) {
                continue;
            }
            $items[] = $info;
        }
        usort($items, fn ($a, $b) => strcmp($b['filename'], $a['filename']));

        return response()->json(['backups' => $items]);
    }

    public function create(Request $request): JsonResponse
    {
        $validated = $request->validate(['passphrase' => ['required', 'string', 'min:10', 'max:200']]);

        try {
            $result = $this->backups->create($validated['passphrase']);
        } catch (RuntimeException $e) {
            return response()->json(['error' => 'backup_failed', 'message' => $e->getMessage()], 422);
        }

        $this->audit($request, 'data_backup.created', ['filename' => $result['filename'], 'tables' => $result['tables'], 'files' => $result['files']]);

        return response()->json([
            'success' => true,
            'filename' => $result['filename'],
            'size_bytes' => $result['size_bytes'],
            'message' => 'Encrypted backup created. Download it and keep it, with its passphrase, somewhere safe.',
        ]);
    }

    public function download(Request $request, string $filename): BinaryFileResponse|JsonResponse
    {
        $path = $this->resolve($filename);
        if ($path === null) {
            return response()->json(['error' => 'not_found', 'message' => 'Backup not found.'], 404);
        }
        $this->audit($request, 'data_backup.downloaded', ['filename' => $filename]);

        return response()->download($path, $filename, ['Content-Type' => 'application/octet-stream'])->deleteFileAfterSend(false);
    }

    public function restore(Request $request): JsonResponse
    {
        if (Impersonation::active()) {
            return response()->json(['error' => 'forbidden', 'message' => 'Not available while working as another user.'], 403);
        }
        $validated = $request->validate([
            'backup' => ['required', 'file', 'max:1048576'], // up to 1 GB
            'passphrase' => ['required', 'string'],
            'restore_app_key' => ['sometimes', 'boolean'],
        ]);

        $tmp = $request->file('backup')->getRealPath();

        try {
            $result = $this->backups->restore($tmp, $validated['passphrase'], (bool) ($validated['restore_app_key'] ?? false));
        } catch (BackupPassphraseException $e) {
            return response()->json(['error' => 'bad_passphrase', 'message' => $e->getMessage()], 422);
        } catch (RuntimeException $e) {
            report($e);

            return response()->json(['error' => 'restore_failed', 'message' => $e->getMessage()], 422);
        }

        // This audit row is itself from the restored data; record it after the fact.
        $this->audit($request, 'data_backup.restored', [
            'tables' => $result['tables'], 'rows' => $result['rows'], 'files' => $result['files'],
            'app_key_matched' => $result['app_key_matched'], 'app_key_restored' => $result['app_key_restored'],
        ]);

        $message = "Restore complete: {$result['rows']} record(s) across {$result['tables']} table(s) and {$result['files']} file(s).";
        if (!$result['app_key_matched'] && !$result['app_key_restored']) {
            $message .= ' Warning: this backup came from a different install. Some encrypted data (such as connected Plaud sign-ins) will not be readable unless you restore with the security key option.';
        } elseif ($result['app_key_restored']) {
            $message .= ' The security key from the backup was applied; please sign in again.';
        }

        return response()->json(['success' => true] + $result + ['message' => $message]);
    }

    public function destroy(Request $request, string $filename): JsonResponse
    {
        $path = $this->resolve($filename);
        if ($path === null) {
            return response()->json(['error' => 'not_found', 'message' => 'Backup not found.'], 404);
        }
        @unlink($path);
        $this->audit($request, 'data_backup.deleted', ['filename' => $filename]);

        return response()->json(['success' => true, 'message' => 'Backup deleted.']);
    }

    /** Resolves a user-supplied filename safely to a file inside the backup directory. */
    private function resolve(string $filename): ?string
    {
        if (!str_ends_with($filename, '.plaudbackup') || !preg_match('/^[A-Za-z0-9._-]+$/', $filename)) {
            return null;
        }
        $path = $this->backups->backupDir() . DIRECTORY_SEPARATOR . $filename;

        return is_file($path) ? $path : null;
    }

    private function audit(Request $request, string $action, array $metadata): void
    {
        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'resource_type' => 'system',
            'resource_id' => null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
