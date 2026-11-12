from datetime import datetime
from typing import List, Optional
from pydantic import BaseModel, Field

class ExportCreateRequest(BaseModel):
    transcript_ids: List[str] = Field(min_length=1, description="List of transcript IDs to export")
    format: str = Field(
        default="both",
        pattern="^(docx|pdf|both)$",
        description="Target document format: 'docx', 'pdf', or 'both'",
    )
    template: str = Field(
        default="clean_transcript",
        pattern="^(clean_transcript|meeting_minutes|executive_summary|transcript_with_actions)$",
    )
    title: Optional[str] = None
    organization_name: Optional[str] = None
    include_metadata: bool = True
    include_ai_summary: bool = True
    include_action_items: bool = True

class ExportItemResponse(BaseModel):
    id: str
    transcript_id: str
    transcript_title: str
    status: str
    output_filename: Optional[str] = None
    format: Optional[str] = "docx"
    error_code: Optional[str] = None

class ExportJobResponse(BaseModel):
    id: str
    workspace_id: str
    status: str
    template: str
    format: str = "both"
    total_items: int
    completed_items: int
    failed_items: int
    download_url: Optional[str] = None
    created_at: datetime
    items: List[ExportItemResponse] = []

    class Config:
        from_attributes = True
