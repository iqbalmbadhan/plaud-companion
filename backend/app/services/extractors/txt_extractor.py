import re
from typing import List, Optional, Tuple
from app.services.extractors.base import ExtractedDocument, SegmentData, ExtractionError

SPEAKER_TIME_REGEX = re.compile(
    r"^(?:\[(\d{1,2}:\d{2}(?::\d{2})?)\]\s*)?(?:([^:\n\r]{1,40}):\s*)(.*)$"
)

def _parse_timestamp_ms(time_str: Optional[str]) -> Optional[int]:
    if not time_str:
        return None
    parts = time_str.split(":")
    try:
        if len(parts) == 2:
            minutes, seconds = int(parts[0]), int(parts[1])
            return (minutes * 60 + seconds) * 1000
        elif len(parts) == 3:
            hours, minutes, seconds = int(parts[0]), int(parts[1]), int(parts[2])
            return (hours * 3600 + minutes * 60 + seconds) * 1000
    except ValueError:
        return None
    return None

def extract_txt(file_bytes: bytes, filename: str) -> ExtractedDocument:
    if not file_bytes or len(file_bytes.strip()) == 0:
        raise ExtractionError("File is empty (0 bytes).", code="EMPTY_FILE")

    # Safe encoding detection
    decoded_text: Optional[str] = None
    for encoding in ("utf-8-sig", "utf-8", "utf-16", "utf-16-le", "utf-16-be", "latin-1"):
        try:
            decoded_text = file_bytes.decode(encoding)
            break
        except (UnicodeDecodeError, LookupError):
            continue

    if decoded_text is None:
        raise ExtractionError(
            "Unable to decode file text using standard UTF-8 or Latin-1 encodings.",
            code="UNDECODABLE_CONTENT",
        )

    clean_text = decoded_text.replace("\r\n", "\n").replace("\r", "\n").strip()
    if not clean_text:
        raise ExtractionError("File contains only whitespace or blank characters.", code="EMPTY_FILE")

    lines = [line.strip() for line in clean_text.split("\n") if line.strip()]
    segments: List[SegmentData] = []
    seq = 1

    for line in lines:
        match = SPEAKER_TIME_REGEX.match(line)
        if match:
            time_part, speaker_part, body = match.groups()
            speaker = speaker_part.strip() if speaker_part else None
            start_ms = _parse_timestamp_ms(time_part)
            text_body = body.strip() if body else line
            segments.append(
                SegmentData(
                    sequence_number=seq,
                    text=text_body,
                    speaker_label=speaker,
                    start_ms=start_ms,
                )
            )
        else:
            segments.append(
                SegmentData(
                    sequence_number=seq,
                    text=line,
                )
            )
        seq += 1

    # Extract clean title from filename
    title = filename.rsplit(".", 1)[0].replace("_", " ").replace("-", " ").strip()
    if not title:
        title = "Untitled Transcript"

    return ExtractedDocument(
        title=title,
        content_text=clean_text,
        segments=segments,
        file_type="txt",
    )
