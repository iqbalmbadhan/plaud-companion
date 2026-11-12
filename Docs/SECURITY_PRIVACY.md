# Security and Privacy Requirements

## 1. Security principles

- Least privilege.
- Defense in depth.
- Secure defaults.
- Tenant isolation.
- Minimize collected data.
- Keep secrets out of clients and logs.
- Make sensitive processing transparent to users.

## 2. Authentication

Use a reputable identity provider or secure password authentication. If local passwords are implemented, use a modern password hashing algorithm such as Argon2id with appropriate parameters. Support session revocation, refresh-token rotation and rate limiting. Do not store plaintext passwords or long-lived bearer tokens in insecure mobile storage.

## 3. Authorization

- Check workspace membership and role on every protected operation.
- Check transcript/project ownership and workspace association.
- Re-check permissions in background workers.
- Protect search, vector retrieval, downloads, exports and admin routes.
- Avoid insecure direct object references.
- Test cross-tenant access attempts.

## 4. File security

- Enforce size and type allowlists.
- Validate file signatures where possible.
- Never execute uploads.
- Use patched parsing libraries and isolated workers.
- Use malware scanning where available.
- Store objects privately.
- Prevent path traversal and unsafe archive extraction.
- Clean temporary files.

## 5. Secrets

- Use managed secrets or encrypted server-side secret storage.
- Never ship provider keys in Flutter, JavaScript bundles, source maps or app configuration.
- Do not log secrets, tokens or signed URLs.
- Rotate compromised credentials.
- Use separate credentials for development, staging and production.

## 6. Network and application protections

- HTTPS/TLS everywhere.
- Secure headers for web.
- CORS allowlist.
- CSRF protection when cookie-based auth is used.
- Input validation and output encoding.
- Parameterized database queries.
- Rate limits for login, uploads, AI and exports.
- Dependency scanning and timely updates.
- Safe error responses.

## 7. AI-specific protections

- Obtain consent before sending transcript content to external providers.
- Treat transcript text and model output as untrusted.
- Defend against prompt injection.
- Restrict AI tools and actions.
- Do not let AI initiate external actions without user confirmation.
- Do not expose other workspace data through retrieval.
- Do not store hidden chain-of-thought.
- Explain that AI can make mistakes and provide source references.

## 8. Privacy controls

Document:
- What data is collected.
- Why it is collected.
- Which providers process it.
- Storage location and retention.
- Whether analytics are used.
- How users export/delete their data.
- What remains in backups and for how long.

Do not claim compliance with a regulation without a formal assessment. Obtain legal/privacy review for the target markets and customer contracts.

## 9. Audit events

Log security-relevant events such as sign-in, invitation, role change, export creation/download, provider configuration and deletion. Avoid transcript bodies and sensitive AI prompts in audit metadata.

## 10. Incident response

Document credential rotation, account/session revocation, storage access shutdown, backup restoration, user notification assessment and incident evidence preservation.
