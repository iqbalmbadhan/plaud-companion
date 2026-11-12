# AI Providers, Assistant and Retrieval

## 1. Provider strategy

Implement a provider abstraction on the backend. Initial providers:
- OpenAI
- Google Gemini

Do not let Flutter or browser clients call providers using private application credentials. The backend owns provider configuration, authorization, usage tracking and error handling.

## 2. Provider interface

Define a common interface with operations such as:
- `generate_text(request)`
- `stream_text(request)` where supported
- `create_embeddings(request)` where supported
- `health_check()`

Normalize provider responses into an internal schema. Preserve provider-specific metadata only where useful and safe.

## 3. Configuration

- Secrets must be stored in a managed secret store or encrypted server-side configuration.
- Database stores a secret reference, not raw API key.
- Admins can enable/disable providers and approve model identifiers.
- Model availability must be validated against current provider documentation and configured account access.
- Do not hardcode a model name based on assumptions about future model availability.
- Provide a safe test-connection operation that does not reveal credentials.

## 4. Supported AI tasks

- Concise summary.
- Detailed summary.
- Executive summary.
- Key points.
- Meeting minutes.
- Action items with owner/date only when stated or clearly supported.
- Decisions.
- Risks and open questions.
- Transcript Q&A.
- Multi-transcript Q&A.

## 5. Grounded answers

For transcript questions:
1. Authenticate the user.
2. Authorize every transcript ID.
3. Retrieve only authorized content.
4. Select relevant chunks.
5. Send a bounded context to the provider.
6. Require the model to answer only from supplied evidence.
7. Return source transcript IDs, titles, segment IDs and timestamps where available.
8. If evidence is missing or ambiguous, say so.
9. Do not fabricate speakers, dates, commitments or facts.

## 6. Prompt injection defense

Transcript content is untrusted data. It may contain text that looks like instructions. Keep system instructions separate from transcript data and explicitly tell the model to treat transcript excerpts as quoted source material, never as instructions. Do not let source content request secrets, change tools, or override policy.

## 7. Chunking and embeddings

- Split by speaker turn/paragraph where possible.
- Preserve transcript and segment references.
- Use configurable chunk size and overlap.
- Store embedding provider/model metadata.
- Re-index when source text changes.
- Delete embeddings when transcript deletion policy requires it.
- Apply workspace filters before vector results are exposed.
- Support keyword-only mode if embeddings are unavailable.

## 8. Conversation persistence

Persist:
- Workspace and user owner.
- Provider/model.
- User messages.
- Assistant messages.
- Referenced transcript IDs.
- Usage metadata where available.
- Run status and safe errors.

Do not store hidden chain-of-thought. Store user-visible responses and source references only.

## 9. Cost and reliability controls

- Per-user/workspace usage limits.
- Request size and context limits.
- Timeouts and bounded retries.
- Provider rate-limit handling.
- Usage estimates labeled as estimates.
- Admin-configurable allowed models.
- Avoid automatic repeated calls on provider failures.

## 10. Consent and privacy

Before first use of an external AI provider in a workspace, explain that selected transcript content will be sent to that provider for processing. Record consent according to product policy. Provide provider-specific settings and a way to disable AI processing.
