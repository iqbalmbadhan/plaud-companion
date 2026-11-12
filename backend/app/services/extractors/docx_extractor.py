import io
import re
import zipfile
import xml.etree.ElementTree as ET
from typing import List, Optional
from app.services.extractors.base import ExtractedDocument, SegmentData, ExtractionError

WORD_NAMESPACE = "{http://schemas.openxmlformats.org/wordprocessingml/2006/main}"
PARA_TAG = f"{WORD_NAMESPACE}p"
TEXT_TAG = f"{WORD_NAMESPACE}t"
TABLE_TAG = f"{WORD_NAMESPACE}tbl"

SPEAKER_REGEX = re.compile(r"^([^:\n\r]{1,40}):\s*(.*)$")

def extract_docx(file_bytes: bytes, filename: str) -> ExtractedDocument:
    if not file_bytes or len(file_bytes.strip()) == 0:
        raise ExtractionError("File is empty (0 bytes).", code="EMPTY_FILE")

    try:
        with zipfile.ZipFile(io.BytesIO(file_bytes)) as z:
            if "word/document.xml" not in z.namelist():
                raise ExtractionError(
                    "Malformed DOCX document: word/document.xml not found.",
                    code="MALFORMED_DOCX",
                )
            xml_content = z.read("word/document.xml")
    except zipfile.BadZipFile:
        raise ExtractionError("File is not a valid DOCX or ZIP archive.", code="INVALID_ARCHIVE")
    except ExtractionError:
        raise
    except Exception as e:
        raise ExtractionError(f"Failed to read DOCX archive: {str(e)}", code="DOCX_READ_ERROR")

    try:
        tree = ET.fromstring(xml_content)
    except Exception as e:
        raise ExtractionError(f"Failed to parse DOCX XML structure: {str(e)}", code="XML_PARSE_ERROR")

    paragraphs: List[str] = []
    for p_elem in tree.iter(PARA_TAG):
        texts = [t.text for t in p_elem.iter(TEXT_TAG) if t.text]
        p_text = "".join(texts).strip()
        if p_text:
            paragraphs.append(p_text)

    if not paragraphs:
        raise ExtractionError("DOCX document contains no text paragraphs.", code="EMPTY_CONTENT")

    segments: List[SegmentData] = []
    seq = 1

    for p in paragraphs:
        match = SPEAKER_REGEX.match(p)
        if match:
            spk, body = match.groups()
            segments.append(
                SegmentData(
                    sequence_number=seq,
                    speaker_label=spk.strip(),
                    text=body.strip() if body else p,
                )
            )
        else:
            segments.append(
                SegmentData(
                    sequence_number=seq,
                    text=p,
                )
            )
        seq += 1

    full_text = "\n\n".join(paragraphs)
    title = filename.rsplit(".", 1)[0].replace("_", " ").replace("-", " ").strip()

    return ExtractedDocument(
        title=title or "Word Document Transcript",
        content_text=full_text,
        segments=segments,
        file_type="docx",
    )
