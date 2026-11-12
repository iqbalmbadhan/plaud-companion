from datetime import datetime
from typing import Optional
from pydantic import BaseModel, Field

class ProjectCreateRequest(BaseModel):
    name: str = Field(min_length=1, max_length=100)
    description: Optional[str] = None

class ProjectUpdateRequest(BaseModel):
    name: Optional[str] = Field(None, min_length=1, max_length=100)
    description: Optional[str] = None

class ProjectResponse(BaseModel):
    id: str
    workspace_id: str
    name: str
    description: Optional[str] = None
    status: str
    created_at: datetime
    transcript_count: Optional[int] = 0

    class Config:
        from_attributes = True
