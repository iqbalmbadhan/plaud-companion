# Testing and Quality Strategy

## 1. General rule

A feature is not complete because code compiles. Verify behavior with automated tests and, where appropriate, manual end-to-end tests. Report commands and actual outcomes.

## 2. Backend tests

- Unit tests for services and validation.
- Repository tests against a test database.
- API integration tests.
- Authorization tests for every role.
- Cross-workspace isolation tests.
- Migration tests.
- Background job retry/idempotency tests.
- Provider adapter tests with mocked HTTP.
- Real provider smoke tests only when credentials are configured.

## 3. Import tests

Test each supported format, malformed files, empty files, size limits, encoding, duplicate detection, extraction failures, retry and permission changes.

## 4. AI tests

- Provider selection and fallback behavior.
- Timeout and rate-limit handling.
- Context truncation/chunk retrieval.
- Grounded response and source citation format.
- Insufficient evidence behavior.
- Prompt injection test cases.
- Workspace isolation in retrieval.
- Consent enforcement.
- Usage metadata normalization.

Avoid brittle tests that require exact generative wording. Assert schema, evidence references and safety behavior.

## 5. Export tests

- DOCX opens successfully.
- Content and metadata are correct.
- Unicode and long transcript content are preserved.
- Filename sanitization works.
- ZIP passes integrity check.
- Manifest matches actual files.
- Partial failures are reported accurately.
- Expired files and unauthorized downloads are blocked.

## 6. Flutter tests

- Widget tests for major screens.
- Form validation.
- Navigation.
- Import progress and errors.
- Transcript selection.
- AI provider selection.
- Export progress and retry.
- Role-dependent UI visibility.
- Accessibility semantics where practical.

## 7. End-to-end journeys

1. Register → create personal workspace → import TXT → view transcript.
2. Import DOCX/PDF/SRT → verify extracted content.
3. Generate summary → inspect sources.
4. Ask question across selected transcripts → verify citations.
5. Select multiple transcripts → create DOCX ZIP → download and inspect.
6. Create team → invite member → accept → verify role restrictions.
7. Attempt cross-workspace access → expect denial.
8. Delete transcript → verify associated data follows retention policy.

## 8. Static and security checks

Run formatter, linter, type checker, dependency audit, secret scanning and container/image scanning as configured. Do not ignore high-severity findings without documenting a mitigation and approval.

## 9. Release gate

No release candidate until:
- Critical and high-severity defects are resolved or formally accepted.
- Core user journeys pass.
- Database migrations are tested.
- Backup/restore procedure is documented.
- Android/iOS build results are recorded.
- Production secrets and provider configuration are verified.
- Privacy and retention notices are available.
