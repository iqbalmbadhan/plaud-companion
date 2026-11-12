# API Contract

Base path: `/api/v1`. Use JSON for normal requests and multipart form data for uploads. Return consistent error structures and request IDs.

## 1. Conventions

- Authentication: secure bearer access token or configured session mechanism.
- Refresh tokens: rotate, revoke and store securely.
- Pagination: cursor-based for large collections; document response shape.
- Dates: ISO 8601 UTC.
- IDs: UUID.
- Idempotency: support `Idempotency-Key` for import/export creation where appropriate.
- Errors: `{ "error": { "code": "...", "message": "...", "request_id": "..." } }`.
- Never expose stack traces, secrets, storage keys or provider raw errors.

## 2. Authentication

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/refresh`
- `POST /auth/logout`
- `POST /auth/logout-all`
- `POST /auth/verify-email`
- `POST /auth/password/forgot`
- `POST /auth/password/reset`
- `GET /me`
- `PATCH /me`
- `DELETE /me`

Exact auth flow depends on selected identity provider.

## 3. Workspaces and members

- `GET /workspaces`
- `POST /workspaces`
- `GET /workspaces/{workspace_id}`
- `PATCH /workspaces/{workspace_id}`
- `DELETE /workspaces/{workspace_id}`
- `GET /workspaces/{workspace_id}/members`
- `POST /workspaces/{workspace_id}/invitations`
- `POST /invitations/{token}/accept`
- `PATCH /workspaces/{workspace_id}/members/{member_id}`
- `DELETE /workspaces/{workspace_id}/members/{member_id}`

## 4. Projects

- `GET /workspaces/{workspace_id}/projects`
- `POST /workspaces/{workspace_id}/projects`
- `GET /projects/{project_id}`
- `PATCH /projects/{project_id}`
- `DELETE /projects/{project_id}`
- `POST /projects/{project_id}/transcripts`
- `DELETE /projects/{project_id}/transcripts/{transcript_id}`

## 5. Imports and transcripts

- `POST /workspaces/{workspace_id}/imports` — multipart upload; returns import job.
- `GET /imports/{job_id}`
- `POST /imports/{job_id}/retry` where safe.
- `GET /workspaces/{workspace_id}/transcripts`
- `GET /transcripts/{transcript_id}`
- `PATCH /transcripts/{transcript_id}`
- `DELETE /transcripts/{transcript_id}`
- `POST /transcripts/{transcript_id}/restore` if soft deletion is supported.
- `GET /transcripts/{transcript_id}/download-original`
- `GET /transcripts/{transcript_id}/segments`

## 6. Search

- `GET /workspaces/{workspace_id}/search?q=...`
- `POST /workspaces/{workspace_id}/search/semantic`

Search must enforce workspace membership and permissions. Semantic search should not return raw vector database identifiers or unauthorized snippets.

## 7. AI

- `GET /ai/providers`
- `GET /ai/models`
- `POST /ai/transcripts/{transcript_id}/summary`
- `POST /ai/transcripts/{transcript_id}/action-items`
- `POST /ai/conversations`
- `GET /ai/conversations`
- `GET /ai/conversations/{conversation_id}`
- `POST /ai/conversations/{conversation_id}/messages`
- `POST /ai/ask` — selected transcript IDs plus question.
- `GET /ai/runs/{run_id}`

For long-running tasks, return a job/run ID and provide status. Streaming may be added for supported chat flows.

## 8. Exports

- `POST /workspaces/{workspace_id}/exports`
- `GET /exports/{export_id}`
- `GET /exports/{export_id}/items`
- `POST /exports/{export_id}/retry-failed`
- `POST /exports/{export_id}/cancel`
- `GET /exports/{export_id}/download`
- `GET /workspaces/{workspace_id}/exports`

The create request specifies transcript IDs or a project/filter scope, template, and document settings. Validate that the requester can export every selected transcript.

## 9. Notifications and audit

- `GET /notifications`
- `PATCH /notifications/{notification_id}`
- `GET /workspaces/{workspace_id}/audit-events` (authorized roles only).

## 10. Health and operations

- `GET /health/live`
- `GET /health/ready`

Do not expose internal diagnostics or dependency secrets publicly.

## 11. API implementation checklist

- Generate OpenAPI documentation.
- Validate every path/body/query parameter.
- Apply authorization at service/repository boundary.
- Add rate limits to login, upload, AI and export operations.
- Add request correlation IDs.
- Test 401, 403, 404, 409, 413, 422, 429 and 5xx behavior.
