# Architecture Decisions and Open Questions

Record decisions here with date, status, context, options, decision and consequences. Do not silently convert assumptions into facts.

## Confirmed product direction

- Product name: PLAUD Companion.
- Platforms: Android and iOS, plus web administration.
- User scope: personal and team workspaces.
- AI providers: OpenAI and Google Gemini.
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
