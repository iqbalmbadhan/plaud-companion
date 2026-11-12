import '../models/demo_models.dart';

abstract final class DemoData {
  static const List<DemoWorkspace> workspaces = [
    DemoWorkspace(
      id: 'ws-team-1',
      name: 'Product & Engineering',
      isPersonal: false,
      role: WorkspaceRole.owner,
      memberCount: 8,
      transcriptCount: 128,
    ),
    DemoWorkspace(
      id: 'ws-personal-1',
      name: 'Personal Notes',
      isPersonal: true,
      role: WorkspaceRole.owner,
      memberCount: 1,
      transcriptCount: 34,
    ),
    DemoWorkspace(
      id: 'ws-team-2',
      name: 'Client Discovery (Read-Only)',
      isPersonal: false,
      role: WorkspaceRole.reviewer,
      memberCount: 5,
      transcriptCount: 19,
    ),
  ];

  static const List<DemoProject> projects = [
    DemoProject(
      id: 'prj-1',
      name: 'Q4 Mobile Launch',
      transcriptCount: 42,
      colorHex: 0xFF18A879,
    ),
    DemoProject(
      id: 'prj-2',
      name: 'Customer Interviews',
      transcriptCount: 51,
      colorHex: 0xFF2563EB,
    ),
    DemoProject(
      id: 'prj-3',
      name: 'Architecture Reviews',
      transcriptCount: 35,
      colorHex: 0xFFD97706,
    ),
  ];

  static const List<DemoTranscript> transcripts = [
    DemoTranscript(
      id: 'tr-101',
      title: 'Q4 Mobile Architecture & Plaud SDK Sync',
      project: 'Q4 Mobile Launch',
      sourceFormat: 'SRT',
      durationLabel: '42m 18s',
      recordedAt: 'Nov 12, 2026',
      status: 'Ready',
      hasAiSummary: true,
      summary:
          'Team aligned on Phase 1 Flutter + Next.js design system rollout, deferring live BLE hardware sync until manual TXT/DOCX/PDF/SRT imports are verified.',
      actionItems: [
        'Finalize Flutter design tokens and 5-tab navigation shell.',
        'Keep OpenAI and Gemini keys strictly on FastAPI backend.',
        'Prepare Phase 2 Alembic migrations for workspaces and transcripts.',
      ],
      keyDecisions: [
        'Use Deep Navy (#101B33) and Emerald (#18A879) as primary tokens.',
        'Require explicit user consent before sending transcripts to AI providers.',
      ],
      segments: [
        TranscriptSegment(
          id: 'seg-1',
          speaker: 'Speaker 1 (Lead)',
          timestamp: '00:00:14',
          text:
              'Let us lock Phase 1 around the Flutter mobile shell and Next.js admin layout using clearly labeled demo data.',
        ),
        TranscriptSegment(
          id: 'seg-2',
          speaker: 'Speaker 2 (Mobile)',
          timestamp: '00:02:41',
          text:
              'Sounds good. We will include a preview card for Plaud Device Sync in the import flow so the BLE path is ready for later.',
        ),
        TranscriptSegment(
          id: 'seg-3',
          speaker: 'Speaker 3 (Security)',
          timestamp: '00:06:09',
          text:
              'Confirmed: zero provider API keys in Flutter or browser bundles, and all errors must mask internal traces.',
        ),
      ],
    ),
    DemoTranscript(
      id: 'tr-102',
      title: 'Enterprise Legal Team Discovery Call',
      project: 'Customer Interviews',
      sourceFormat: 'DOCX',
      durationLabel: '31m 05s',
      recordedAt: 'Nov 11, 2026',
      status: 'Ready',
      hasAiSummary: true,
      summary:
          'Legal ops requested bulk .docx export inside a single .zip archive with an index manifest and strict workspace isolation.',
      actionItems: [
        'Support multi-select export queue with manifest CSV/JSON.',
        'Enforce short-lived signed download URLs in Phase 6.',
      ],
      keyDecisions: [
        'Read-only reviewers cannot trigger bulk exports or delete transcripts.',
      ],
      segments: [
        TranscriptSegment(
          id: 'seg-4',
          speaker: 'Client Counsel',
          timestamp: '00:01:12',
          text:
              'We need to select 20 client interviews at once and export clean Word files in one zip.',
        ),
      ],
    ),
    DemoTranscript(
      id: 'tr-103',
      title: 'Weekly Engineering Standup',
      project: 'Architecture Reviews',
      sourceFormat: 'TXT',
      durationLabel: '18m 44s',
      recordedAt: 'Nov 10, 2026',
      status: 'Processing',
      hasAiSummary: false,
      summary: 'AI summary pending user consent or background worker run.',
      actionItems: ['Verify pgvector chunking sizes in Phase 5.'],
      keyDecisions: ['Standardize on /api/v1 REST endpoints.'],
      segments: [
        TranscriptSegment(
          id: 'seg-5',
          speaker: 'Backend Lead',
          timestamp: '00:00:30',
          text:
              'Alembic migrations and Redis ARQ workers will land right after Phase 1 sign-off.',
        ),
      ],
    ),
  ];

  static const List<DemoChatMessage> initialChat = [
    DemoChatMessage(
      id: 'msg-1',
      isUser: true,
      content: 'What were the main security decisions from our recent meetings?',
    ),
    DemoChatMessage(
      id: 'msg-2',
      isUser: false,
      content:
          'Based on your workspace transcripts, the team agreed to keep OpenAI and Gemini keys strictly on the FastAPI backend, require user consent before AI transmission, and restrict read-only reviewers from bulk exports.',
      citations: ['tr-101 · 00:06:09', 'tr-102 · 00:01:12'],
    ),
  ];

  static const List<DemoExportJob> exportJobs = [
    DemoExportJob(
      id: 'exp-1',
      title: 'Q4 Launch Bundle (12 transcripts)',
      format: 'DOCX + ZIP',
      itemCount: 12,
      status: 'Completed',
      createdAt: 'Today, 10:15 AM',
    ),
    DemoExportJob(
      id: 'exp-2',
      title: 'Legal Discovery Pack (5 transcripts)',
      format: 'DOCX + ZIP',
      itemCount: 5,
      status: 'Queued',
      createdAt: 'Today, 2:40 PM',
    ),
  ];

  static const List<DemoMember> members = [
    DemoMember(
      id: 'm-1',
      name: 'Iqbal M. Badhan',
      email: 'owner@example.com',
      role: WorkspaceRole.owner,
      status: 'Active',
    ),
    DemoMember(
      id: 'm-2',
      name: 'Nadia Rahman',
      email: 'admin@example.com',
      role: WorkspaceRole.admin,
      status: 'Active',
    ),
    DemoMember(
      id: 'm-3',
      name: 'Tariq Hasan',
      email: 'member@example.com',
      role: WorkspaceRole.member,
      status: 'Active',
    ),
    DemoMember(
      id: 'm-4',
      name: 'External Auditor',
      email: 'reviewer@example.com',
      role: WorkspaceRole.reviewer,
      status: 'Invited',
    ),
  ];

  static const List<DemoNotificationItem> notifications = [
    DemoNotificationItem(
      id: 'n-1',
      title: 'Bulk export ready (Demo)',
      subtitle: 'Q4 Launch Bundle.zip generated with 12 .docx files.',
      timeAgo: '14m ago',
      unread: true,
    ),
    DemoNotificationItem(
      id: 'n-2',
      title: 'Transcript imported',
      subtitle: 'Q4 Mobile Architecture & Plaud SDK Sync (SRT) parsed.',
      timeAgo: '2h ago',
      unread: false,
    ),
  ];
}
