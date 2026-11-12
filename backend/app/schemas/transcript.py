from datetime import datetime
from typing import Optional, List
from pydantic import BaseModel, Field

class SegmentCreateRequest(BaseModel):
    sequence_number: int
    speaker_label: Optional[str] = None
    start_ms: Optional[int] = None
    end_ms: Optional[int] = None
    text: str = Field(min_length=1)

class TranscriptSegmentResponse(BaseModel):
    id: str
    sequence_number: int
    speaker_label: Optional[str] = None
    start_ms: Optional[int] = None
    end_ms: Optional[int] = None
    text: str

    class Config:
        from_attributes = True

class TranscriptCreateRequest(BaseModel):
    title: str = Field(min_length=1, max_length=255)
    description: Optional[str] = None
    project_id: Optional[str] = None
    source_type: str = "manual_upload"
    recorded_at: Optional[datetime] = None
    language: Optional[str] = "en"
    content_text: str = Field(min_length=1)
    segments: Optional[List[SegmentCreateRequest]] = None

class TranscriptUpdateRequest(BaseModel):
    title: Optional[str] = Field(None, min_length=1, max_length=255)
    description: Optional[str] = None
    project_id: Optional[str] = None
    language: Optional[str] = None

class TranscriptResponse(BaseModel):
    id: str
    workspace_id: str
    project_id: Optional[str] = None
    title: str
    description: Optional[str] = None
    source_type: str
    recorded_at: Optional[datetime] = None
    language: Optional[str] = None
    status: str
    content_hash: str
    created_at: datetime
    segment_count: Optional[int] = 0

    class Config:
        from_attributes = True

class TranscriptDetailResponse(TranscriptResponse):
    content_text: str
    segments: List[TranscriptSegmentResponse] = []
