from dataclasses import dataclass, field
from datetime import datetime
from typing import List, Optional

@dataclass
class SegmentData:
    sequence_number: int
    text: str
    speaker_label: Optional[str] = None
    start_ms: Optional[int] = None
    end_ms: Optional[int] = None

@dataclass
class ExtractedDocument:
    title: str
    content_text: str
    segments: List[SegmentData] = field(default_factory=list)
    language: Optional[str] = "en"
    recorded_at: Optional[datetime] = None
    file_type: str = "txt"

class ExtractionError(Exception):
    def __init__(self, message: str, code: str = "EXTRACTION_FAILED"):
        super().__init__(message)
        self.code = code
        self.message = message
