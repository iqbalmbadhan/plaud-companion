# Architecture Decisions and Open Questions

Record decisions here with date, status, context, options, decision and consequences. Do not silently convert assumptions into facts.

## Confirmed product direction

- Product name: PLAUD Companion.
- Platforms: Android and iOS, plus web administration.
- User scope: personal and team workspaces.
- AI providers: OpenAI, OpenRouter, Anthropic and Google Gemini.
- Core workflow: transcript import, organization, AI analysis, bulk DOCX generation and ZIP download.
- Initial PLAUD path: user-authorized manual file import.
- Proposed stack: Flutter, FastAPI, PostgreSQL/pgvector, Redis workers, private object storage, Next.js admin.

## Decisions requiring confirmation

### Identity provider
Options: managed identity provider or local email/password authentication.
Questions:
- Which provider is preferred?
- Is enterprise SSO required for the first release?
- Which email service will send verification and invitation messages?

### Storage and hosting
Questions:
- Which cloud/provider is approved?
- Which data residency requirements apply?
- What backup and retention period is required?
- Is an S3-compatible service available?

### AI provider ownership
Questions:
- Will users supply their own provider keys, or will the organization manage provider accounts?
- Which models are approved?
- What usage limits and budget controls are required?
- Which transcript categories may be sent to each provider?

### Transcript formats
Questions:
- Which exact PLAUD export formats are available from representative devices/accounts?
- Are speaker labels and timestamps present?
- What maximum file size and transcript duration should be supported?
- Is audio transcription in scope?

### Team permissions
Questions:
- Can transcripts be shared individually, or only through projects/workspaces?
- Can an Editor invite members?
- Can Viewer export?
- Who can configure AI providers?

### Export policy
Questions:
- Which DOCX templates are mandatory?
- Is branding/logo customization required in MVP?
- How long should ZIP files remain downloadable?
- Should partial exports be downloadable?

### Legal and privacy
Questions:
- Which countries/markets will be served?
- What consent language is required?
- What deletion and backup retention policy applies?
- Is a formal privacy/security assessment required before launch?

## Decision log template

### DEC-XXX — Short title
- Date:
- Status: proposed / accepted / superseded
- Context:
- Options:
- Decision:
- Consequences:
- Owner:


---

## Logged Decisions

### DEC-001 — CI/CD Pipeline and Remote Build via GitHub Actions
- **Date:** 2026-11-13
- **Status:** Accepted
- **Context:** Local developer host machines may have differing toolchains (e.g. Flutter SDK or Docker configurations). Continuous integration, automated test runs, static analysis, and packaging of mobile APKs, web builds, and backend test suites must execute reliably on standard runners.
- **Options:**
  1. Rely exclusively on developer local machines for builds and tests.
  2. Implement GitHub Actions workflows (`.github/workflows/ci-pipeline.yml`) to orchestrate test suites, linting, and release artifacts across Ubuntu and macOS runners.
- **Decision:** Option 2. Standardize all build, lint, and test validation on GitHub Actions runners.
- **Consequences:** All pull requests and commits are verified continuously. Developers do not require full Android/iOS build toolchains locally to contribute code and documentation.
- **Owner:** Engineering Team

### DEC-002 — Official Plaud Embedded SDK Integration Architecture
- **Date:** 2026-11-13
- **Status:** Accepted
- **Context:** Plaud provides the official open-source repository `https://github.com/Plaud-AI/plaud-sdk-public` containing precompiled binaries for Android (`plaud-sdk.aar`) and iOS (`PlaudBleSDK`, `PlaudWiFiSDK`, `PlaudDeviceBasicSDK.xcframework`). The app needs to support both physical hardware device synchronization and manual transcript file import (TXT, DOCX, PDF, SRT).
- **Options:**
  1. Only support manual file upload and drop hardware support.
  2. Directly bind native Plaud SDK libraries via Flutter Platform Channels (`MethodChannel` and `EventChannel`) backed by an abstract `IPlaudSdkService` with a deterministic `MockPlaudSdkService` fallback for emulators, CI, and testing.
- **Decision:** Option 2. Scaffolding is created in `mobile/android` and `mobile/ios` to accept the official SDK binaries. The Flutter application uses the `IPlaudSdkService` abstraction, enabling full testability in headless CI while allowing live BLE sync on supported physical devices.
- **Consequences:** Users can sync recordings directly from their Plaud Note/NotePin hardware or upload manual exports. Automated widget and unit tests run cleanly in headless CI environments without physical Bluetooth devices.
- **Owner:** Mobile Engineering & Architecture


### DEC-003 — 1-Click AI Connectors for Non-Technical Users (OpenRouter, Anthropic, Gemini, OpenAI)
- **Date:** 2026-11-13
- **Status:** Accepted
- **Context:** Non-technical users find configuring environment variables, API secret keys, and raw JSON configurations intimidating. They need a simple, intuitive way to connect AI models where clicking a provider name redirects them directly to login or connect.
- **Options:**
  1. Require users to edit `.env` or input raw API keys manually into settings.
  2. Implement an `AIConnector` registry with 1-click OpenRouter OAuth/connect integration (unlocking Claude 3.5 Sonnet, GPT-4o, and Gemini in one click), direct Anthropic, Google Gemini, and OpenAI adapters with 1-click test connections and high-fidelity local fallback.
- **Decision:** Option 2. Implement the unified `ConnectorRegistry` exposing `easy_connect_url` and instant verification.
- **Consequences:** Non-technical users can click "Connect" and immediately start generating summaries and action items without technical friction.
- **Owner:** AI Platform Team


### DEC-003 — 1-Click AI Connectors for Non-Technical Users (OpenRouter, Anthropic, Gemini, OpenAI)
- **Date:** 2026-11-13
- **Status:** Accepted
- **Context:** Non-technical users find configuring environment variables, API secret keys, and raw JSON configurations intimidating. They need a simple, intuitive way to connect AI models where clicking a provider name redirects them directly to login or connect.
- **Options:**
  1. Require users to edit `.env` or input raw API keys manually into settings.
  2. Implement an `AIConnector` registry with 1-click OpenRouter OAuth/connect integration (unlocking Claude 3.5 Sonnet, GPT-4o, and Gemini in one click), direct Anthropic, Google Gemini, and OpenAI adapters with 1-click test connections and high-fidelity local fallback.
- **Decision:** Option 2. Implement the unified `ConnectorRegistry` exposing `easy_connect_url` and instant verification.
- **Consequences:** Non-technical users can click "Connect" and immediately start generating summaries and action items without technical friction.
- **Owner:** AI Platform Team
