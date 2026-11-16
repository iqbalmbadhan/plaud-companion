# DOCX and ZIP Export Specification

## 1. Goal

Generate one valid Microsoft Word DOCX per selected transcript and package successful documents into a ZIP archive.

## 2. User options

Selection:
- One transcript.
- Selected transcripts.
- All transcripts in a project.
- Current filtered result set (with explicit server-side selection semantics).

Templates:
1. Clean transcript.
2. Meeting minutes.
3. Executive summary.
4. Transcript with action items.

Settings:
- Document title.
- Organization name.
- Optional logo.
- Header/footer.
- Page numbering.
- Font and paragraph spacing.
- Include metadata.
- Include AI-generated summary/action items, only if already generated or explicitly requested.

## 3. Job lifecycle

Statuses:
- `queued`
- `validating`
- `processing`
- `completed`
- `partially_completed`
- `failed`
- `cancel_requested`
- `cancelled`
- `expired`

Create an export job and child item for every transcript. Validate permissions before queuing and again in the worker. Do not trust IDs supplied by the client.

## 4. Document generation

Use `python-docx`.
- Sanitize and normalize text.
- Escape/handle unsupported characters safely.
- Preserve speaker labels and timestamps where available.
- Use readable headings and paragraph styles.
- Avoid overflowing or malformed metadata.
- Use a safe logo processing path and validate image format/size.
- Sanitize filenames and prevent path traversal.
- Do not include hidden internal IDs or secrets in documents.

## 5. ZIP creation

Use Python `zipfile`.
- Include successful DOCX files.
- Include `manifest.csv` or `manifest.json` with filename, transcript title, status and safe failure reason.
- Do not include failed/empty files.
- Ensure archive paths are relative and sanitized.
- Validate ZIP integrity before marking the job complete.
- Consider maximum archive size and storage quotas.

## 6. Partial failures

A job may complete partially. Show:
- Total selected.
- Successful.
- Failed.
- Retryable failed items.
- Downloadable archive of successful items, if at least one succeeded.

Never label a partial export as fully completed.

## 7. Security and retention

- Store archives in private object storage.
- Re-authorize download requests.
- Use short-lived signed URLs.
- Expire archives after configured retention.
- Delete temporary files.
- Do not put permanent public links in notifications or logs.

## 8. Tests

- Open generated DOCX with a parser and verify expected content.
- Verify metadata and speaker formatting.
- Test long text and Unicode.
- Test filename sanitization.
- Test ZIP integrity (`testzip` or equivalent).
- Test manifest accuracy.
- Test partial failures.
- Test unauthorized export and download.
- Test expired link and cleanup.

## Laravel implementation notes (backend-laravel)

- Summary and action-item sections are **opt-in** (`include_ai_summary`, `include_action_items`,
  default `false`) or implied by the `meeting_minutes`, `executive_summary` and
  `transcript_with_actions` templates. They are generated per transcript by the workspace's AI
  provider and shared by the DOCX and PDF outputs.
- If generation fails, the document states that the section is unavailable and `manifest.json`
  lists the failure under `ai_warnings`. Placeholder content is never inserted.
- Known limitations: exports run synchronously in the request, `expires_at` is not yet enforced
  on download, the PDF is a single page truncated to 75 transcript lines with non-ASCII
  characters replaced, and transcripts with identical titles collide inside the ZIP.
