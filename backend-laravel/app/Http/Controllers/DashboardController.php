<?php

namespace App\Http\Controllers;

use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Services\WorkspaceService;
use App\Support\Update\Package;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly WorkspaceService $workspaces)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $userWorkspaces = $this->workspaces->listUserWorkspaces($user->id);

        if (empty($userWorkspaces)) {
            $created = $this->workspaces->createWorkspace(
                $user->id,
                $user->display_name . "'s Workspace",
                'personal'
            );
            $userWorkspaces = [$created];
        }

        $activeWorkspace = $userWorkspaces[0];
        $this->ensureStarterDataOnce($activeWorkspace['id'], $user->id);

        return view('app.workspace', [
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'display_name' => $user->display_name,
                'initials' => $this->initials($user->display_name),
                'is_super_admin' => $user->is_super_admin === true,
            ],
            'workspaces' => $userWorkspaces,
            'activeWorkspace' => $activeWorkspace,
            'activeWorkspaceId' => $activeWorkspace['id'],
            'appVersion' => config('app.version', '1.0.0'),
            'currentVersion' => config('app.version', '1.0.0'),
            'updaterEnabled' => $user->is_super_admin === true ? Package::enabled() : false,
            'maxZipMb' => (int) config('updater.max_zip_mb', 150),
        ]);
    }

    public function seedSample(Request $request, string $workspaceId): JsonResponse
    {
        $this->workspaces->requireMembership($workspaceId, $request->user()->id, ['owner', 'admin', 'editor']);
        $created = $this->seedWorkspaceRecords($workspaceId, $request->user()->id);

        return response()->json([
            'ok' => true,
            'created_transcripts' => $created,
        ]);
    }

    private function ensureStarterDataOnce(string $workspaceId, string $userId): void
    {
        $alreadySeeded = AuditEvent::query()
            ->where('workspace_id', $workspaceId)
            ->where('action', 'workspace.starter_seeded')
            ->exists();

        if ($alreadySeeded) {
            return;
        }

        $hasTranscripts = Transcript::query()->where('workspace_id', $workspaceId)->exists();
        if ($hasTranscripts) {
            return;
        }

        $this->seedWorkspaceRecords($workspaceId, $userId);
    }

    private function seedWorkspaceRecords(string $workspaceId, string $userId): int
    {
        return DB::transaction(function () use ($workspaceId, $userId): int {
            $projectMap = [];
            $projectSpecs = [
                'Business Meetings' => 'Team syncs, planning and executive decisions',
                'Product Research' => 'User interviews, roadmap ideas and validation',
                'Client Discussions' => 'Discovery calls, proposals and follow-ups',
            ];

            foreach ($projectSpecs as $name => $desc) {
                $existing = Project::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    $projectMap[$name] = $existing->id;
                } else {
                    $p = Project::create([
                        'id' => (string) Str::uuid(),
                        'workspace_id' => $workspaceId,
                        'name' => $name,
                        'description' => $desc,
                        'status' => 'active',
                        'created_by_user_id' => $userId,
                    ]);
                    $projectMap[$name] = $p->id;
                }
            }

            $samples = [
                [
                    'title' => 'Weekly Business Meeting',
                    'project' => 'Business Meetings',
                    'source_type' => 'docx_import',
                    'description' => '42 min · 3 speakers · Website redesign and mobile navigation priorities',
                    'segments' => [
                        ['speaker' => 'SPEAKER 1', 'start_ms' => 135000, 'end_ms' => 152000, 'text' => 'We need to complete the website redesign before the end of this month. The priority is improving the mobile experience and simplifying the navigation.'],
                        ['speaker' => 'SPEAKER 2', 'start_ms' => 152000, 'end_ms' => 184000, 'text' => "I'll prepare the updated design and share it with the team. I can have the first draft ready by next Wednesday."],
                        ['speaker' => 'SPEAKER 3', 'start_ms' => 184000, 'end_ms' => 208000, 'text' => "Let's also review page speed and accessibility before we approve the final version."],
                        ['speaker' => 'SPEAKER 1', 'start_ms' => 208000, 'end_ms' => 240000, 'text' => "Agreed. We'll schedule a review meeting after the first draft is shared."],
                    ],
                ],
                [
                    'title' => 'Product Planning Session',
                    'project' => 'Product Research',
                    'source_type' => 'srt_import',
                    'description' => '58 min · 4 speakers · Q4 roadmap, 1-Click AI connectors and bulk ZIP export',
                    'segments' => [
                        ['speaker' => 'PRODUCT LEAD', 'start_ms' => 60000, 'end_ms' => 95000, 'text' => 'Our top customer request this quarter is 1-click AI provider setup for OpenRouter, Claude, Gemini, and OpenAI without manual key management on mobile.'],
                        ['speaker' => 'BACKEND ENG', 'start_ms' => 95000, 'end_ms' => 140000, 'text' => 'We have isolated all provider credentials inside the encrypted backend vault and added DOCX, PDF, and validated bulk ZIP export jobs.'],
                        ['speaker' => 'DESIGN LEAD', 'start_ms' => 140000, 'end_ms' => 180000, 'text' => 'The 5-tab mobile navigation and web workspace now share the Deep Navy and Emerald design tokens.'],
                    ],
                ],
                [
                    'title' => 'Client Discovery Call',
                    'project' => 'Client Discussions',
                    'source_type' => 'pdf_import',
                    'description' => '31 min · 2 speakers · Enterprise workspace RBAC and CloudPanel deployment requirements',
                    'segments' => [
                        ['speaker' => 'CLIENT VP', 'start_ms' => 45000, 'end_ms' => 90000, 'text' => 'We need strict workspace role isolation so reviewers can inspect transcripts while only owners and admins manage team settings.'],
                        ['speaker' => 'SOLUTIONS ARCH', 'start_ms' => 90000, 'end_ms' => 135000, 'text' => 'Workspace RBAC is enforced on every query, and system updates and backups under Settings are locked exclusively to the super-admin account.'],
                    ],
                ],
                [
                    'title' => 'Website Review & Feedback',
                    'project' => 'Business Meetings',
                    'source_type' => 'txt_import',
                    'description' => '26 min · 3 speakers · Performance audit, mobile responsiveness and export templates',
                    'segments' => [
                        ['speaker' => 'SPEAKER 1', 'start_ms' => 30000, 'end_ms' => 70000, 'text' => 'Initial feedback on the new dashboard is very positive. Users love being able to filter transcripts by project and export to Word in one click.'],
                        ['speaker' => 'SPEAKER 2', 'start_ms' => 70000, 'end_ms' => 115000, 'text' => 'Action item for this week: finalize the executive summary template and verify bulk ZIP downloads across mobile and desktop browsers.'],
                    ],
                ],
            ];

            $createdCount = 0;
            foreach ($samples as $sample) {
                $bodyLines = array_map(
                    static fn (array $s): string => "{$s['speaker']}: {$s['text']}",
                    $sample['segments']
                );
                $contentText = implode("\n", $bodyLines);
                $hash = hash('sha256', $contentText);

                $exists = Transcript::query()
                    ->where('workspace_id', $workspaceId)
                    ->where('content_hash', $hash)
                    ->exists();
                if ($exists) {
                    continue;
                }

                $transcript = Transcript::create([
                    'id' => (string) Str::uuid(),
                    'workspace_id' => $workspaceId,
                    'project_id' => $projectMap[$sample['project']] ?? null,
                    'created_by_user_id' => $userId,
                    'title' => $sample['title'],
                    'description' => $sample['description'],
                    'source_type' => $sample['source_type'],
                    'recorded_at' => now()->subHours(count($samples) - $createdCount),
                    'language' => 'en',
                    'status' => 'processed',
                    'content_text' => $contentText,
                    'content_hash' => $hash,
                ]);

                foreach ($sample['segments'] as $idx => $seg) {
                    TranscriptSegment::create([
                        'id' => (string) Str::uuid(),
                        'transcript_id' => $transcript->id,
                        'sequence_number' => $idx + 1,
                        'speaker_label' => $seg['speaker'],
                        'start_ms' => $seg['start_ms'],
                        'end_ms' => $seg['end_ms'],
                        'text' => $seg['text'],
                    ]);
                }

                $createdCount++;
            }

            AuditEvent::create([
                'id' => (string) Str::uuid(),
                'workspace_id' => $workspaceId,
                'actor_user_id' => $userId,
                'action' => 'workspace.starter_seeded',
                'resource_type' => 'workspace',
                'resource_id' => $workspaceId,
                'metadata' => ['created_transcripts' => $createdCount],
                'created_at' => now(),
            ]);

            return $createdCount;
        });
    }

    private function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = isset($parts[0][0]) ? strtoupper($parts[0][0]) : 'P';
        $second = isset($parts[1][0]) ? strtoupper($parts[1][0]) : '';

        return $first . $second;
    }
}
