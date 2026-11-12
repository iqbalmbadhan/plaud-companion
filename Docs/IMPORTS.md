# Transcript Import and Processing

## 1. Initial scope

Support user-authorized uploads of:
- TXT
- DOCX
- PDF
- SRT

CSV is not accepted until a documented column mapping is designed. Audio transcription is a separate feature requiring provider selection, supported codecs, consent and cost controls.

## 2. Upload flow

1. Client requests an upload/import operation.
2. Backend authenticates and authorizes workspace membership.
3. Validate extension, MIME type, file signature where practical, file size and upload quota.
4. Store file in private quarantine/staging storage.
5. Scan using configured malware scanning where available.
6. Create import job and queue processing.
7. Extract text in an isolated worker.
8. Normalize encoding and line endings without losing meaningful content.
9. Extract available title, date, speaker labels and timestamps.
10. Detect duplicates using content hash and workspace scope.
11. Create transcript and segment records.
12. Preserve original file according to retention policy.
13. Mark job complete only after database and storage operations are verified.
14. Notify the user.

## 3. Extraction rules

- TXT: detect encoding safely; reject or report undecodable content.
- DOCX: extract paragraphs and tables; do not execute embedded content.
- PDF: extract text; report scanned/image-only PDFs as unsupported until OCR is deliberately implemented.
- SRT: parse cues and timestamps; preserve cue order.
- Keep original source and normalized transcript representation separately.

## 4. Validation

Reject:
- Empty files.
- Unsupported formats.
- Files over configured limits.
- Malformed documents that cannot be safely parsed.
- Files failing security checks.

Return useful error codes without exposing server paths or internal exception details.

## 5. Duplicate handling

Compute SHA-256 on upload. Duplicate detection should be scoped to a workspace and should not reveal whether another workspace has uploaded the same file. Let users choose whether to skip, keep both, or replace where policy permits.

## 6. Worker safety

- Use resource limits and timeouts.
- Do not execute uploaded files or macros.
- Use patched parsers.
- Avoid archive path traversal.
- Keep temporary files private and clean them up.
- Make retries idempotent.
- Re-check workspace and transcript state during processing.

## 7. PLAUD compatibility

Do not assume a single export format. Collect representative user-provided samples and document supported formats. Start with standard files that users can export themselves. Implement a direct PLAUD connector only after official API documentation and authorization are confirmed.

## 8. Import acceptance tests

- Valid TXT, DOCX, PDF and SRT.
- Empty and corrupt files.
- Unsupported extension and MIME mismatch.
- Oversized file.
- Duplicate within same workspace.
- Same file in separate workspaces.
- Multiple files in one batch.
- Permission revoked before worker execution.
- Worker retry and failure.
