# Development and Deployment Guide

## 1. Prerequisites

- Git.
- Flutter SDK and Android toolchain.
- Xcode on macOS for iOS builds/signing.
- Python version selected by the project and pinned in tooling.
- Node.js version selected and pinned for Next.js.
- Docker and Docker Compose.
- PostgreSQL with pgvector extension if semantic search is enabled.
- Redis.
- S3-compatible private object storage or local development equivalent.

Pin versions in project configuration after validating compatibility. Do not assume the latest version is compatible with every dependency.

## 2. Environment configuration

Create `.env` from `.env.example`. Keep real credentials out of Git.

Expected categories:
- Application environment and public base URLs.
- Database connection.
- Redis connection.
- Authentication/session secrets.
- Object storage endpoint, bucket and credentials.
- OpenAI secret reference/configuration.
- Gemini secret reference/configuration.
- Email provider configuration.
- Upload limits and retention settings.
- Logging and monitoring configuration.

Never place private backend secrets in Flutter build-time variables or Next.js public environment variables.

## 3. Local startup

The repository should provide documented commands to:
1. Start PostgreSQL, Redis and local object storage.
2. Apply database migrations.
3. Start FastAPI.
4. Start background workers.
5. Start Next.js admin.
6. Run Flutter on an emulator/device.
7. Run tests.

Add health checks and readiness checks. Provide synthetic seed data only in development.

## 4. Staging

- Use isolated database, storage bucket and provider credentials.
- Use test accounts and synthetic transcripts.
- Require authentication.
- Verify HTTPS and CORS.
- Run migrations and smoke tests.
- Test upload, AI and export end-to-end.
- Check logs for accidental sensitive data.

## 5. Production

Use managed infrastructure where practical:
- Managed PostgreSQL with backups.
- Private object storage with lifecycle policy.
- Redis/queue service.
- Managed secret storage.
- TLS termination and secure ingress.
- Worker scaling.
- Monitoring, alerting and error reporting.
- Database migration process.
- Backup restoration drills.

## 6. Mobile release

### Android
- Configure application ID, signing and release build.
- Remove debug endpoints and test credentials.
- Verify permissions and privacy disclosures.
- Test on supported Android versions and device sizes.
- Prepare store listing and release notes.

### iOS
- Build and sign on macOS with Xcode.
- Configure bundle identifier, signing and provisioning.
- Verify privacy disclosures and required permission descriptions.
- Test on supported iOS versions and device sizes.
- Prepare App Store Connect metadata and review notes.

## 7. Release and rollback

- Tag release in Git.
- Back up database before risky migrations.
- Deploy backward-compatible schema changes where possible.
- Deploy backend and workers.
- Verify health and core journeys.
- Release mobile binaries.
- Monitor errors and job failures.
- Document rollback steps for application and database changes.

## 8. Operational runbooks

Document:
- Database restore.
- Object storage recovery.
- Secret rotation.
- Queue backlog handling.
- Failed import/export recovery.
- Provider outage response.
- Account/session revocation.
- Data deletion request handling.
