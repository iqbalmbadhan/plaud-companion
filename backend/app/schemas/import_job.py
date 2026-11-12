from datetime import datetime
from typing import List, Optional
from pydantic import BaseModel
from app.schemas.transcript import TranscriptResponse

class ImportItemResult(BaseModel):
    filename: str
    status: str  # "success", "duplicate", "error"
    transcript_id: Optional[str] = None
    transcript: Optional[TranscriptResponse] = None
    error_code: Optional[str] = None
    error_message: Optional[str] = None

class BatchImportResponse(BaseModel):
    workspace_id: str
    total_files: int
    successful_count: int
    duplicate_count: int
    failed_count: int
    results: List[ImportItemResult]
