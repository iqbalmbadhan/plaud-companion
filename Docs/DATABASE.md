# Database Model

Use PostgreSQL with UUID primary keys, UTC timestamps, foreign keys, appropriate indexes and migrations. Names below are logical guidance; refine types and constraints during implementation.

## 1. Core tables

### users
- `id`
- `email` (unique, normalized)
- `display_name`
- `password_hash` only if local password auth is used
- `status`
- `email_verified_at`
- `created_at`, `updated_at`, `deleted_at`

### workspaces
- `id`
- `name`
- `type` (`personal`, `team`)
- `owner_user_id`
- `created_at`, `updated_at`, `deleted_at`

### workspace_members
- `id`
- `workspace_id`
- `user_id`
- `role` (`owner`, `admin`, `editor`, `viewer`)
- `status`
- `joined_at`
- Unique constraint on `(workspace_id, user_id)`.

### workspace_invitations
- `id`
- `workspace_id`
- `email`
- `role`
- `token_hash`
- `expires_at`
- `accepted_at`
- `created_by_user_id`
- `created_at`

### projects
- `id`
- `workspace_id`
- `name`
- `description`
- `status`
- `created_by_user_id`
- timestamps and soft-delete field if needed.

### transcripts
- `id`
- `workspace_id`
- `project_id` nullable
- `created_by_user_id`
- `title`
- `description`
- `source_type`
- `recorded_at` nullable
- `language` nullable
- `status`
- `content_text` or normalized segment relation
- `content_hash`
- `created_at`, `updated_at`, `deleted_at`

### transcript_segments
- `id`
- `transcript_id`
- `sequence_number`
- `speaker_label` nullable
- `start_ms` nullable
- `end_ms` nullable
- `text`
- Unique `(transcript_id, sequence_number)`.

### transcript_files
- `id`
- `transcript_id`
- `workspace_id`
- `storage_key`
- `original_filename`
- `mime_type`
- `size_bytes`
- `sha256`
- `file_role` (`original`, `derived`)
- `created_at`, `deleted_at`

### tags and transcript_tags
Tags are workspace-scoped. Use a join table with unique `(transcript_id, tag_id)`.

### provider_connections
- `id`
- `workspace_id` nullable for platform-level configuration
- `provider` (`openai`, `gemini`)
- `secret_reference` (reference to managed secret, never raw key)
- `enabled`
- `allowed_models` JSON or normalized model table
- `created_by_user_id`
- timestamps

### ai_conversations
- `id`
- `workspace_id`
- `created_by_user_id`
- `title`
- `provider`
- `model`
- timestamps

### ai_messages
- `id`
- `conversation_id`
- `role`
- `content`
- `usage_metadata` JSON nullable
- `created_at`

### ai_runs
- `id`
- `workspace_id`
- `user_id`
- `provider`
- `model`
- `task_type`
- `status`
- `input_reference` (IDs, not sensitive payload copies)
- `usage_metadata`
- `error_code`
- timestamps

### transcript_chunks
- `id`
- `workspace_id`
- `transcript_id`
- `chunk_index`
- `text`
- `embedding` vector, if pgvector enabled
- `embedding_model`
- `created_at`
- Index workspace/transcript and vector index as appropriate.

### export_jobs
- `id`
- `workspace_id`
- `requested_by_user_id`
- `status`
- `template`
- `settings` JSON
- `total_items`
- `completed_items`
- `failed_items`
- `archive_storage_key` nullable
- `manifest_storage_key` nullable
- `expires_at`
- timestamps

### export_job_items
- `id`
- `export_job_id`
- `transcript_id`
- `status`
- `output_storage_key` nullable
- `error_code` nullable
- `attempt_count`
- timestamps

### notifications
- `id`
- `user_id`
- `workspace_id` nullable
- `type`
- `payload` JSON with minimal non-sensitive data
- `read_at`
- `created_at`

### audit_events
- `id`
- `workspace_id` nullable
- `actor_user_id` nullable
- `action`
- `resource_type`
- `resource_id`
- `metadata` JSON with safe metadata only
- `created_at`

## 2. Authorization and tenancy

Every workspace-owned table must have a `workspace_id` directly or be reachable through a parent whose workspace is checked. Every query must be scoped to a workspace the authenticated user belongs to with the required role.

Consider PostgreSQL row-level security as defense in depth, but do not rely on RLS alone. Set and clear tenant context safely per transaction/connection.

Vector retrieval must filter by authorized workspace IDs before returning chunks. File download and export workers must re-check membership and resource authorization.

## 3. Deletion

Define and document:
- Soft deletion vs hard deletion.
- Retention period for originals and exports.
- Cascading deletion behavior.
- Embedding deletion.
- Backup expiration behavior.
- Legal hold exceptions, if applicable.

A user-facing deletion confirmation must explain what is deleted and what may remain temporarily in backups according to policy.

## 4. Indexes and constraints

Add indexes for:
- Normalized user email.
- Workspace membership lookup.
- Workspace + created date on transcripts.
- Transcript title/search fields.
- Project membership.
- Job status + created date.
- Export item lookup.
- Foreign keys used in authorization joins.

Use full-text indexes for keyword search where appropriate. Add uniqueness constraints for invitation tokens and idempotency keys.

## 5. Migration policy

- All schema changes use versioned migrations.
- Migrations must be reviewed and tested against a representative database.
- Avoid destructive migrations without backup and explicit migration plan.
- Seed only synthetic development/demo data.
