import os
from typing import Dict, Callable
from app.services.extractors.base import ExtractedDocument, ExtractionError
from app.services.extractors.txt_extractor import extract_txt
from app.services.extractors.docx_extractor import extract_docx
from app.services.extractors.pdf_extractor import extract_pdf
from app.services.extractors.srt_extractor import extract_srt

EXTRACTOR_REGISTRY: Dict[str, Callable[[bytes, str], ExtractedDocument]] = {
    "txt": extract_txt,
    "docx": extract_docx,
    "pdf": extract_pdf,
    "srt": extract_srt,
}

MAX_FILE_SIZE_BYTES = 25 * 1024 * 1024  # 25 MB

def get_file_extension(filename: str) -> str:
    parts = filename.rsplit(".", 1)
    if len(parts) > 1:
        return parts[1].lower().strip()
    return ""

def extract_document(file_bytes: bytes, filename: str) -> ExtractedDocument:
    if len(file_bytes) > MAX_FILE_SIZE_BYTES:
        raise ExtractionError(
            f"File exceeds maximum allowed size of {MAX_FILE_SIZE_BYTES // (1024*1024)}MB.",
            code="FILE_TOO_LARGE",
        )

    ext = get_file_extension(filename)
    if not ext or ext not in EXTRACTOR_REGISTRY:
        supported = ", ".join(f".{k.upper()}" for k in EXTRACTOR_REGISTRY.keys())
        raise ExtractionError(
            f"Unsupported file format '.{ext}'. Supported formats are: {supported}.",
            code="UNSUPPORTED_FORMAT",
        )

    extractor_func = EXTRACTOR_REGISTRY[ext]
    return extractor_func(file_bytes, filename)
