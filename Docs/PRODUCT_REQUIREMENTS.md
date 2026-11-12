# Product Requirements Document

## 1. Product summary

**Name:** PLAUD Companion  
**Platforms:** Android, iOS, web administration  
**Primary users:** Individuals, professionals, teams and organizations that work with recorded-meeting transcripts.  
**Core value:** Turn imported transcripts into searchable knowledge, useful AI outputs, collaborative records and portable Word documents.

## 2. Goals

- Make transcript imports simple and reliable.
- Keep transcripts organized across projects and workspaces.
- Help users understand transcripts with AI summaries, Q&A and action extraction.
- Support both OpenAI and Google Gemini without coupling the product to either provider.
- Make bulk Word export dependable.
- Protect private and organizational information.
- Provide a path to official PLAUD integration if authorized.

## 3. Non-goals for the initial release

- Recording audio or controlling PLAUD hardware.
- Bypassing PLAUD authentication or using undocumented/private APIs.
- Replacing a full enterprise document-management system.
- Training AI models on customer transcripts.
- Real-time meeting transcription unless separately scoped.
- Billing/subscriptions unless explicitly approved as a later milestone.

## 4. User types

### Individual user
Owns a personal workspace, imports transcripts, uses AI, creates projects and exports documents.

### Workspace owner
Creates a team workspace, manages settings, members and ownership.

### Workspace admin
Manages members, roles, projects, provider availability and workspace content within granted permissions.

### Editor
Imports, edits metadata, organizes, analyzes and exports authorized transcripts.

### Viewer
Reads authorized transcripts and permitted AI results; cannot modify content.

### Platform operator
Operates the service and infrastructure. Platform-level access must be tightly controlled and audited; it does not imply routine access to transcript contents.

## 5. Core user journeys

1. User registers and verifies their email.
2. A personal workspace is created.
3. User imports one or more transcript files.
4. The system validates and extracts text, then creates transcript records.
5. User searches, filters, tags and assigns transcripts to projects.
6. User requests an AI summary or asks a question.
7. The AI response cites transcript sources and indicates uncertainty.
8. User selects multiple transcripts and starts a Word export.
9. Background processing generates one DOCX per transcript and packages successful documents in a ZIP.
10. User downloads the archive through an authorized, expiring link.
11. A workspace owner invites colleagues and assigns roles.
12. Members collaborate without crossing workspace boundaries.

## 6. Functional requirements

### Authentication and account
- Registration, login, logout, email verification and password reset.
- Secure session lifecycle and revocation.
- Profile and preferences.
- Account data export and deletion request.
- Optional SSO/social login only when configured and tested.

### Workspace and collaboration
- Personal workspace per user.
- Create and manage team workspaces.
- Invite by email with expiring, single-use invitation tokens.
- Roles: Owner, Admin, Editor, Viewer.
- Membership removal and role changes.
- Workspace switching.
- Auditable ownership transfer and workspace deletion.

### Transcript import
Initial formats: TXT, DOCX, PDF and SRT. CSV requires an explicit mapping specification. Audio import is a later capability and must have a defined transcription provider, supported formats, limits, consent and cost controls.

- Multi-file upload.
- File validation and size limits.
- Text extraction and encoding detection.
- Duplicate detection.
- Preserve original files where retention policy allows.
- Import job status and actionable errors.
- Metadata: title, source, recording date, speakers and duration when available.
- User review/edit of extracted metadata.

### Transcript library
- List and grid views.
- Search title and transcript text.
- Filters by workspace, project, date, source, tags and processing status.
- Sort by title, newest and oldest.
- Tags, projects, favorites and archive.
- Detail view with readable transcript segments and speaker labels.
- Edit metadata and delete.
- Pagination.

### AI
- Select an enabled provider and approved model.
- Summaries, key points, meeting minutes, action items, decisions and follow-ups.
- Q&A over one transcript or selected transcripts.
- Conversation history.
- Source references with transcript title and timestamp/segment where available.
- Clear “insufficient evidence” responses.
- Usage and error reporting where provider data supports it.
- Explicit consent before sending content to external AI providers.

### Search and retrieval
- Keyword search in the initial release.
- Semantic and hybrid search when embeddings are configured.
- Workspace authorization applied before retrieval.
- Source citations for AI answers.
- Re-index when transcript content changes or is deleted.

### Bulk export
- Select one, many, project transcripts or filtered results.
- Generate one DOCX per transcript.
- Templates: clean transcript, meeting minutes, executive summary, transcript plus action items.
- Optional title, date, speaker information, logo, header/footer and page numbers.
- ZIP archive with manifest and per-item status.
- Retry failed items and cancel queued jobs.
- Export history and expiring secure downloads.

### Projects
- Create, rename, archive and delete.
- Assign/unassign transcripts.
- Project-level summaries and exports.
- Permissions inherited from workspace unless a more granular model is explicitly designed.

### Notifications
- In-app notifications for import/export completion, failures and invitations.
- Read/unread state.
- Optional email notifications only with provider configuration and user consent.

### Administration
- User/workspace/membership management.
- Provider configuration and availability.
- Job monitoring.
- Usage and storage metrics.
- Audit events.
- Retention and upload limits.

## 7. Non-functional requirements

- Responsive, accessible user experience.
- Reliable processing of large transcripts within configured limits.
- Background execution for imports, AI tasks and exports.
- Observability without transcript-content leakage.
- Secure multi-tenant isolation.
- Database backups and documented recovery.
- Clear privacy, retention and deletion behavior.
- API versioning and migration discipline.
- Maintainable automated tests.

## 8. MVP acceptance criteria

The MVP is acceptable when a user can:
- Register and sign in.
- Import supported transcript files.
- View, search and organize transcripts.
- Generate an AI summary using a configured provider.
- Ask a question about a transcript and see source evidence.
- Select multiple transcripts.
- Generate actual DOCX documents.
- Download a verified ZIP containing the generated documents.
- Create a team workspace and invite a member.
- Demonstrate that a user cannot access another workspace's private records.

## 9. Product analytics

Collect minimal operational events such as import success/failure, AI job status, export completion and feature usage. Do not collect transcript text or sensitive content in analytics. Disclose analytics in the privacy notice and provide controls where appropriate.
