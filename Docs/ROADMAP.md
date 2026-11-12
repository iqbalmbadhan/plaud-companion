# Implementation Roadmap

This is a dependency-ordered roadmap, not a guaranteed schedule. Estimate each phase after repository inspection and team-capacity review.

## Phase 0 — Discovery and architecture
Deliverables:
- Repository audit.
- PRD and acceptance criteria.
- Architecture and ER diagram.
- API contract.
- Security threat assessment.
- Open decisions and risks.

Exit criteria: architecture reviewed and scope approved.

## Phase 1 — Design system and navigation
Deliverables:
- Flutter design tokens/components.
- Core screen layouts.
- Responsive navigation.
- Next.js admin shell.
- Accessible loading, empty and error states.

Exit criteria: main navigation and screen flows work with clearly labeled demo data.

## Phase 2 — Backend foundation
Deliverables:
- FastAPI project.
- PostgreSQL migrations.
- Authentication.
- Users, workspaces and roles.
- Authorization tests.
- Docker local environment.

Exit criteria: users can authenticate and workspace isolation tests pass.

## Phase 3 — Transcript import and library
Deliverables:
- TXT/DOCX/PDF/SRT extraction.
- Private storage.
- Background import jobs.
- Transcript library, search and projects.

Exit criteria: real files import and persist; errors are handled.

## Phase 4 — AI providers
Deliverables:
- OpenAI and Gemini adapters.
- Server-side configuration.
- Summary and action-item generation.
- Transcript Q&A with source references.
- Consent and usage controls.

Exit criteria: configured providers work in staging; mock tests pass independently.

## Phase 5 — Knowledge retrieval
Deliverables:
- Chunking.
- Embeddings and pgvector.
- Hybrid search.
- Multi-transcript Q&A.
- Permission-filtered retrieval.

Exit criteria: source citations are returned and tenant isolation is tested.

## Phase 6 — DOCX and ZIP exports
Deliverables:
- Templates.
- Bulk selection.
- Background export jobs.
- Verified DOCX and ZIP.
- Manifest, retry and secure download.

Exit criteria: exported files open and archive integrity tests pass.

## Phase 7 — Collaboration
Deliverables:
- Invitations.
- Roles.
- Member management.
- Workspace/project sharing.
- Audit events.

Exit criteria: role matrix and cross-tenant tests pass.

## Phase 8 — Admin and operations
Deliverables:
- Admin dashboard.
- Provider settings.
- Job monitoring.
- Usage metrics.
- Retention controls.

Exit criteria: admin permissions are tested and sensitive information is protected.

## Phase 9 — Production readiness
Deliverables:
- Security review.
- Performance tests.
- Monitoring and alerting.
- Backup/restore.
- Android and iOS release builds.
- Deployment and user documentation.

Exit criteria: release checklist complete and blockers explicitly accepted.

## Weekly review template

- Completed (evidence):
- In progress:
- Blocked:
- Defects:
- Decisions needed:
- Next week's top priorities:
- Roadmap impact:
