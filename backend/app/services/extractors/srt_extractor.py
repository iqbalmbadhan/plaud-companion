import re
from typing import List, Optional
from app.services.extractors.base import ExtractedDocument, SegmentData, ExtractionError

TIMESTAMP_REGEX = re.compile(
    r"(\d{2}):(\d{2}):(\d{2})[,.](\d{3})\s*-->\s*(\d{2}):(\d{2}):(\d{2})[,.](\d{3})"
)
SPEAKER_TAG_REGEX = re.compile(r"^(?:<v\s+([^>]+)>|\[([^\]]+)\]|([A-Za-z0-9\s]{1,30}):)\s*(.*)$")

def _srt_time_to_ms(hours: int, minutes: int, seconds: int, millis: int) -> int:
    return (hours * 3600 + minutes * 60 + seconds) * 1000 + millis

def extract_srt(file_bytes: bytes, filename: str) -> ExtractedDocument:
    if not file_bytes or len(file_bytes.strip()) == 0:
        raise ExtractionError("File is empty (0 bytes).", code="EMPTY_FILE")

    try:
        text = file_bytes.decode("utf-8-sig")
    except UnicodeDecodeError:
        try:
            text = file_bytes.decode("latin-1")
        except Exception:
            raise ExtractionError("Unable to decode SRT file.", code="UNDECODABLE_CONTENT")

    text = text.replace("\r\n", "\n").replace("\r", "\n").strip()
    if not text:
        raise ExtractionError("SRT file is blank.", code="EMPTY_FILE")

    # Split by blank lines to find blocks
    blocks = re.split(r"\n\s*\n", text)
    segments: List[SegmentData] = []
    full_paragraphs: List[str] = []
    seq = 1

    for block in blocks:
        lines = [line.strip() for line in block.split("\n") if line.strip()]
        if not lines:
            continue

        time_line_idx = -1
        start_ms: Optional[int] = None
        end_ms: Optional[int] = None

        for idx, line in enumerate(lines):
            match = TIMESTAMP_REGEX.search(line)
            if match:
                time_line_idx = idx
                h1, m1, s1, ms1, h2, m2, s2, ms2 = map(int, match.groups())
                start_ms = _srt_time_to_ms(h1, m1, s1, ms1)
                end_ms = _srt_time_to_ms(h2, m2, s2, ms2)
                break

        if time_line_idx != -1 and time_line_idx + 1 < len(lines):
            raw_content = " ".join(lines[time_line_idx + 1 :])
            # Strip HTML-style tags e.g. <i>, </i>, <font>
            clean_content = re.sub(r"<[^>]+>", "", raw_content).strip()

            speaker: Optional[str] = None
            spk_match = SPEAKER_TAG_REGEX.match(clean_content)
            if spk_match:
                v_tag, br_tag, col_tag, rest = spk_match.groups()
                speaker = (v_tag or br_tag or col_tag or "").strip()
                clean_content = rest.strip() if rest else clean_content

            if clean_content:
                segments.append(
                    SegmentData(
                        sequence_number=seq,
                        speaker_label=speaker,
                        start_ms=start_ms,
                        end_ms=end_ms,
                        text=clean_content,
                    )
                )
                if speaker:
                    full_paragraphs.append(f"{speaker}: {clean_content}")
                else:
                    full_paragraphs.append(clean_content)
                seq += 1

    if not segments:
        raise ExtractionError("No valid SRT subtitle cues found.", code="MALFORMED_SRT")

    title = filename.rsplit(".", 1)[0].replace("_", " ").replace("-", " ").strip()
    return ExtractedDocument(
        title=title or "SRT Transcript",
        content_text="\n\n".join(full_paragraphs),
        segments=segments,
        file_type="srt",
    )
