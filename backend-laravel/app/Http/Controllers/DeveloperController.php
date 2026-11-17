<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\ErrorLog;
use App\Models\Transcript;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\ErrorLogger;
use App\Services\TokenService;
use App\Support\Impersonation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

/**
 * Developer-only tools: impersonation, workspace document lists (no content), debug log,
 * deleting users and granting the developer role.
 */
class DeveloperController extends Controller
{
    public function __construct(private readonly TokenService $tokens)
    {
    }

    public function index(Request $request): \Illuminate\View\View
    {
        $totalUsers      = User::query()->count();
        $totalWorkspaces = Workspace::query()->count();
        $developerCount  = User::query()->where('is_developer', true)->count();
        $pendingErrors   = \App\Models\ErrorLog::query()->count();

        return view('settings.developer', compact('totalUsers', 'totalWorkspaces', 'developerCount', 'pendingErrors'));
    }

    public function impersonate(Request $request, string $userId): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:200'],
            'password' => ['required', 'string'],
        ]);

        /** @var User $developer */
        $developer = $request->user();
        if (!$this->tokens->verifyPassword($validated['password'], $developer->password_hash)) {
            return response()->json(['error' => 'invalid_password', 'message' => 'Your password is incorrect.'], 422);
        }

        $target = User::query()->find($userId);
        if ($target === null) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }
        if ($target->id === $developer->id || $target->isDeveloper()) {
            return response()->json(['error' => 'forbidden', 'message' => 'You cannot impersonate yourself or another developer.'], 403);
        }
        if ($target->status !== 'active') {
            return response()->json(['error' => 'forbidden', 'message' => 'Suspended accounts cannot be impersonated.'], 403);
        }

        Impersonation::start($request, $developer, $target, trim($validated['reason']));

        return response()->json([
            'success' => true,
            'message' => "You are now working as {$target->display_name} for " . Impersonation::MINUTES . ' minutes.',
            'redirect' => route('app.dashboard'),
        ]);
    }

    /** Available to the impersonated session (it is not a developer session while active). */
    public function stopImpersonation(Request $request): JsonResponse|RedirectResponse
    {
        if (!Impersonation::active()) {
            return $request->expectsJson()
                ? response()->json(['error' => 'not_impersonating', 'message' => 'No impersonation session is active.'], 400)
                : redirect()->route('app.dashboard');
        }

        $restored = Impersonation::stop($request, 'ended_by_developer');
        $target = $restored ? route('settings.users') : route('login');

        return $request->expectsJson()
            ? response()->json(['success' => true, 'redirect' => $target])
            : redirect($target)->with('status', 'Impersonation ended. You are back in your developer account.');
    }

    /** Workspaces with their document lists: titles, sizes and dates only, never transcript text. */
    public function workspaces(): JsonResponse
    {
        $workspaces = Workspace::query()->with('owner')->orderBy('created_at')->get();
        $memberCounts = WorkspaceMember::query()->selectRaw('workspace_id, COUNT(*) as c')->groupBy('workspace_id')->pluck('c', 'workspace_id');

        $docs = Transcript::query()
            ->select(['id', 'workspace_id', 'project_id', 'title', 'source_type', 'status', 'language', 'created_by_user_id', 'created_at'])
            ->withCount('segments')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('workspace_id');

        return response()->json(['workspaces' => $workspaces->map(function (Workspace $w) use ($memberCounts, $docs): array {
            $list = $docs->get($w->id, collect());

            return [
                'id' => $w->id,
                'name' => $w->name,
                'type' => $w->type,
                'owner' => $w->owner ? ['id' => $w->owner->id, 'name' => $w->owner->display_name, 'email' => $w->owner->email] : null,
                'members_count' => (int) ($memberCounts[$w->id] ?? 0),
                'documents_count' => $list->count(),
                'created_at' => $w->created_at?->toIso8601String(),
                'documents' => $list->take(200)->map(static fn (Transcript $t): array => [
                    'id' => $t->id,
                    'title' => $t->title,
                    'source_type' => $t->source_type,
                    'status' => $t->status,
                    'language' => $t->language,
                    'segments_count' => (int) $t->segments_count,
                    'created_at' => $t->created_at?->toIso8601String(),
                ])->values()->all(),
            ];
        })->values()->all()]);
    }

    public function errors(Request $request): JsonResponse
    {
        $query = ErrorLog::query()->orderByDesc('created_at');
        $source = $request->query('source');
        if (in_array($source, ['server', 'browser'], true)) {
            $query->where('source', $source);
        }
        $rows = $query->limit(200)->get();
        $names = User::withTrashed()->whereIn('id', $rows->pluck('user_id')->merge($rows->pluck('impersonator_user_id'))->filter()->unique())
            ->pluck('display_name', 'id');

        return response()->json([
            'errors' => $rows->map(static fn (ErrorLog $e): array => [
                'reference' => $e->reference,
                'source' => $e->source,
                'exception_class' => $e->exception_class,
                'message' => $e->message,
                'location' => $e->location,
                'method' => $e->method,
                'path' => $e->path,
                'status_code' => $e->status_code,
                'user' => $e->user_id ? ($names[$e->user_id] ?? $e->user_id) : null,
                'impersonated_by' => $e->impersonator_user_id ? ($names[$e->impersonator_user_id] ?? $e->impersonator_user_id) : null,
                'user_agent' => $e->user_agent,
                'context' => $e->context,
                'created_at' => $e->created_at?->toIso8601String(),
            ])->all(),
            'retention_days' => ErrorLogger::RETENTION_DAYS,
        ]);
    }

    public function clearErrors(Request $request): JsonResponse
    {
        $count = ErrorLog::query()->delete();
        $this->audit($request, 'debug.log_cleared', null, ['deleted' => $count]);

        return response()->json(['success' => true, 'deleted' => $count]);
    }

    /** Any signed-in page posts uncaught browser errors here (sanitized, rate-limited). */
    public function reportClientError(Request $request, ErrorLogger $logger): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:1000'],
            'url' => ['nullable', 'string', 'max:500'],
            'line' => ['nullable', 'integer', 'min:0'],
            'column' => ['nullable', 'integer', 'min:0'],
            'stack' => ['nullable', 'string', 'max:4000'],
        ]);

        return response()->json(['reference' => $logger->recordBrowser($validated, $request)], 201);
    }

    public function deleteUser(Request $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }
        if ($user->id === $actor->id) {
            return response()->json(['error' => 'forbidden', 'message' => 'You cannot delete your own account.'], 400);
        }
        if ($user->isDeveloper() && $this->activeDeveloperCount() <= 1) {
            return response()->json(['error' => 'forbidden', 'message' => 'You cannot delete the last developer.'], 400);
        }

        $this->tokens->revokeAllSessions($user);
        $user->status = 'suspended';
        $user->save();
        $user->delete(); // soft delete: audit history and owned workspaces stay intact
        $this->audit($request, 'user.deleted', $user, ['target_email' => $user->email]);

        return response()->json(['success' => true, 'message' => "{$user->display_name} was deleted and signed out everywhere."]);
    }

    public function toggleDeveloper(Request $request, string $userId): JsonResponse
    {
        $actor = $request->user();
        $user = User::query()->find($userId);
        if ($user === null) {
            return response()->json(['error' => 'not_found', 'message' => 'User not found.'], 404);
        }
        if ($user->id === $actor->id) {
            return response()->json(['error' => 'forbidden', 'message' => 'You cannot change your own developer role.'], 400);
        }
        if (!$user->isDeveloper() && $user->status !== 'active') {
            return response()->json(['error' => 'forbidden', 'message' => 'Reactivate the account before making it a developer.'], 400);
        }

        $user->is_developer = !$user->isDeveloper();
        if ($user->is_developer) {
            $user->is_super_admin = true;
        }
        $user->save();
        $this->audit($request, $user->is_developer ? 'user.developer_granted' : 'user.developer_revoked', $user, ['target_email' => $user->email]);

        return response()->json([
            'success' => true,
            'is_developer' => $user->is_developer,
            'message' => $user->is_developer ? "{$user->display_name} is now a developer." : "{$user->display_name} is no longer a developer.",
        ]);
    }

    /**
     * Loads Plaud-format sample recordings into the developer's own first workspace, so the
     * "Connect my Plaud account" result can be demonstrated without a device or live account.
     * Clearly labelled sample data; skips any already loaded so it is safe to click twice.
     */
    public function loadPlaudSamples(Request $request, \App\Services\PlaudAccountService $plaud): JsonResponse
    {
        if (Impersonation::active()) {
            return response()->json(['error' => 'forbidden', 'message' => 'Not available while working as another user.'], 403);
        }

        $user = $request->user();
        $workspaceId = WorkspaceMember::query()
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->value('workspace_id');
        if ($workspaceId === null) {
            return response()->json(['error' => 'no_workspace', 'message' => 'You have no workspace to load samples into.'], 404);
        }

        $imported = 0;
        foreach (\App\Support\PlaudSampleRecordings::all() as $sample) {
            $exists = Transcript::query()
                ->where('workspace_id', $workspaceId)
                ->where('external_source', 'plaud')
                ->where('external_id', $sample['file']['id'])
                ->exists();
            if ($exists) {
                continue;
            }
            if ($plaud->importRecording($workspaceId, $user->id, $sample['file'], $sample['segments'], $sample['summary'])) {
                $imported++;
            }
        }

        $this->audit($request, 'developer.plaud_samples_loaded', null, ['workspace_id' => $workspaceId, 'imported' => $imported]);

        return response()->json([
            'success' => true,
            'imported' => $imported,
            'message' => $imported > 0
                ? "{$imported} Plaud sample recording(s) loaded into your workspace."
                : 'Plaud sample recordings are already loaded.',
        ]);
    }

    private function activeDeveloperCount(): int
    {
        return User::query()->where('is_developer', true)->where('status', 'active')->count();
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, string $action, ?User $target, array $metadata = []): void
    {
        AuditEvent::create([
            'id' => (string) Str::uuid(),
            'workspace_id' => null,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'resource_type' => $target ? 'user' : 'system',
            'resource_id' => $target?->id,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
