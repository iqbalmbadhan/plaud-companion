# PLAUD Companion

A cross-platform transcript management and AI productivity platform for individuals and teams.

## Product goal

PLAUD Companion lets users import transcripts exported from PLAUD devices (and other supported sources), organize and search them, ask AI questions, collaborate in workspaces, and export one or many transcripts as Word documents packaged in a ZIP archive.

> **Integration constraint:** The initial release uses user-authorized manual file import. Do not assume PLAUD provides a public API. Implement direct synchronization only after confirming official documentation, permission, and contractual authorization.

## Documentation map

- [`AGENTS.md`](AGENTS.md) — instructions for AI coding agents.
- [`docs/PRODUCT_REQUIREMENTS.md`](docs/PRODUCT_REQUIREMENTS.md) — product scope and requirements.
- [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) — system architecture and boundaries.
- [`docs/DATABASE.md`](docs/DATABASE.md) — data model and isolation rules.
- [`docs/API.md`](docs/API.md) — API conventions and endpoint contract.
- [`docs/UX.md`](docs/UX.md) — design system and user journeys.
- [`docs/AI.md`](docs/AI.md) — OpenAI/Gemini abstraction, retrieval and safety.
- [`docs/IMPORTS.md`](docs/IMPORTS.md) — file import and transcript processing.
- [`docs/EXPORTS.md`](docs/EXPORTS.md) — DOCX and ZIP export behavior.
- [`docs/SECURITY_PRIVACY.md`](docs/SECURITY_PRIVACY.md) — security and privacy requirements.
- [`docs/TESTING.md`](docs/TESTING.md) — test strategy and acceptance criteria.
- [`docs/ROADMAP.md`](docs/ROADMAP.md) — phased implementation plan.
- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) — local, staging and production deployment.
- [`docs/DECISIONS.md`](docs/DECISIONS.md) — architecture decisions and open questions.

## Suggested repository structure

```text
plaud-companion/
├── AGENTS.md
├── README.md
├── apps/
│   ├── mobile/                 # Flutter Android + iOS
│   └── admin-web/              # Next.js + TypeScript
├── backend/                    # FastAPI
│   ├── app/
│   ├── migrations/
│   └── tests/
├── infrastructure/
├── docs/
├── reference/
│   └── prototype/
├── .env.example
└── docker-compose.yml
```

## Development principles

1. Build in small, reviewable phases.
2. Keep provider credentials on the backend.
3. Enforce workspace authorization in every server-side data access path.
4. Treat uploaded transcript content as untrusted data.
5. Use background jobs for expensive or long-running work.
6. Never mark an operation complete until its output has been verified.
7. Keep demo data and mock adapters clearly separated from production services.
8. Record assumptions and unresolved decisions in `docs/DECISIONS.md`.

## Current status

This documentation pack describes the intended product. It does not mean the application has already been implemented. Track implementation status in issues or a project board and update the documents when decisions change.
