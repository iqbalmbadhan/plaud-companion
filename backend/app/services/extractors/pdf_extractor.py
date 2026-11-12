import io
import re
from typing import List, Optional
from app.services.extractors.base import ExtractedDocument, SegmentData, ExtractionError

SPEAKER_REGEX = re.compile(r"^([^:\n\r]{1,40}):\s*(.*)$")

def extract_pdf(file_bytes: bytes, filename: str) -> ExtractedDocument:
    if not file_bytes or len(file_bytes.strip()) == 0:
        raise ExtractionError("File is empty (0 bytes).", code="EMPTY_FILE")

    # Verify PDF magic bytes '%PDF'
    if not file_bytes.startswith(b"%PDF"):
        raise ExtractionError("File is not a valid PDF document header.", code="INVALID_PDF")

    extracted_pages: List[str] = []
    has_images = False

    # 1. Attempt extraction using PyMuPDF (fitz)
    try:
        import fitz  # PyMuPDF
        doc = fitz.open(stream=file_bytes, filetype="pdf")
        for page in doc:
            page_text = page.get_text().strip()
            if page_text:
                extracted_pages.append(page_text)
            if page.get_images():
                has_images = True
        doc.close()
    except ImportError:
        # Fallback to pypdf
        try:
            import pypdf
            reader = pypdf.PdfReader(io.BytesIO(file_bytes))
            for page in reader.pages:
                text = page.extract_text()
                if text and text.strip():
                    extracted_pages.append(text.strip())
        except Exception as e:
            raise ExtractionError(f"Failed to parse PDF document: {str(e)}", code="PDF_READ_ERROR")
    except Exception as e:
        raise ExtractionError(f"Failed to read PDF document: {str(e)}", code="PDF_READ_ERROR")

    # Combine text
    combined_text = "\n\n".join(extracted_pages).strip()

    if not combined_text:
        if has_images:
            raise ExtractionError(
                "Scanned or image-only PDF detected with no selectable text. Please provide an export containing text or an SRT/DOCX/TXT file.",
                code="IMAGE_ONLY_PDF",
            )
        raise ExtractionError("PDF document contains no text.", code="EMPTY_CONTENT")

    # Segment lines and speaker labels
    lines = [l.strip() for l in combined_text.split("\n") if l.strip()]
    segments: List[SegmentData] = []
    seq = 1

    for line in lines:
        match = SPEAKER_REGEX.match(line)
        if match:
            spk, body = match.groups()
            segments.append(
                SegmentData(
                    sequence_number=seq,
                    speaker_label=spk.strip(),
                    text=body.strip() if body else line,
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

    title = filename.rsplit(".", 1)[0].replace("_", " ").replace("-", " ").strip()
    return ExtractedDocument(
        title=title or "PDF Document Transcript",
        content_text=combined_text,
        segments=segments,
        file_type="pdf",
    )
