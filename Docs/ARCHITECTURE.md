# System Architecture

## 1. High-level design

Use a modular monorepo with independently deployable mobile, backend and web-admin applications.

```text
Flutter Android/iOS ───────┐
                           ├── HTTPS ── FastAPI /api/v1
Next.js Admin Web ─────────┘                  |
                                              +── PostgreSQL
                                              |     + pgvector (optional)
                                              |
                                              +── Redis / background workers
                                              |
                                              +── Private object storage
                                              |
                                              +── OpenAI adapter
                                              +── Gemini adapter
                                              +── Email provider (optional)
```

## 2. Components

### Flutter mobile app
Responsible for presentation, local navigation, secure token storage, upload/download interaction and user-facing job status. It must not contain provider secrets or privileged authorization logic.

### Next.js admin web
Responsible for administrative workflows and responsive web access where scoped. Use server-side authorization and do not expose secrets to browser bundles.

### FastAPI backend
Owns business rules, authentication integration, authorization, validation, provider calls, file processing orchestration, export orchestration and audit events.

### PostgreSQL
Stores users, workspaces, memberships, transcript metadata/text or normalized segments, projects, conversations, jobs and audit events.

### pgvector
Stores embeddings when semantic search is enabled. It is not a replacement for authorization filtering.

### Redis and workers
Use a queue for imports, embeddings, AI processing and exports. Workers must re-check authorization and resource ownership at execution time.

### Object storage
Private bucket/container for original uploads and generated exports. Use encryption at rest, lifecycle policies and short-lived signed URLs.

## 3. Backend module boundaries

Suggested modules:
- `auth`
- `users`
- `workspaces`
- `projects`
- `transcripts`
- `imports`
- `search`
- `ai`
- `providers`
- `exports`
- `notifications`
- `audit`
- `admin`

Keep route handlers thin. Put business logic in services and persistence logic in repositories.

## 4. Integration boundaries

Define interfaces for:
- `AIProvider`: generate, stream (optional), embed (optional), health_check.
- `ObjectStorage`: put, get, delete, create_signed_download_url.
- `TranscriptExtractor`: extract text and metadata from supported formats.
- `EmailSender`: send invitation and account emails.
- `PlaudConnector`: optional future adapter, disabled until officially supported and authorized.

## 5. Background jobs

Jobs should include:
- `process_transcript_import`
- `generate_transcript_embeddings`
- `run_ai_task`
- `generate_export`
- `cleanup_expired_files`
- `send_invitation_email`

Each job needs an ID, status, attempts, timestamps, error code, safe error message and idempotency key. Do not put secrets or full transcript text in queue metadata.

## 6. Reliability patterns

- Use database transactions for state changes.
- Use an outbox pattern if reliable event publication is needed.
- Make jobs idempotent.
- Use bounded retries with exponential backoff.
- Route permanently failing jobs to a dead-letter mechanism.
- Expose health/readiness checks.
- Add timeouts to external services.
- Do not report completion before output verification.

## 7. Scalability

Start with a modular monolith backend and separate worker processes. Avoid microservices until measurable scale or team ownership justifies them. Use pagination, database indexes, object storage and asynchronous jobs from the beginning.

## 8. Environments

- Local: Docker Compose, local database, local Redis and local/test object storage.
- Staging: production-like isolated infrastructure and test provider credentials.
- Production: managed database, private object storage, managed secrets, backups, monitoring and alerting.

## 9. Architectural constraints

- No direct database access from mobile or browser.
- No AI provider calls containing secrets from the client.
- No cross-workspace vector retrieval.
- No public object-storage buckets for private transcripts.
- No undocumented PLAUD API access.
