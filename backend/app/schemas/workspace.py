from datetime import datetime
from typing import Optional, List
from pydantic import BaseModel, EmailStr, Field

class WorkspaceCreateRequest(BaseModel):
    name: str = Field(min_length=2, max_length=100)
    type: str = Field(default="team", pattern="^(personal|team)$")

class WorkspaceUpdateRequest(BaseModel):
    name: Optional[str] = Field(None, min_length=2, max_length=100)

class WorkspaceMemberResponse(BaseModel):
    id: str
    user_id: str
    workspace_id: str
    role: str
    status: str
    joined_at: datetime
    email: Optional[str] = None
    display_name: Optional[str] = None

    class Config:
        from_attributes = True

class WorkspaceResponse(BaseModel):
    id: str
    name: str
    type: str
    owner_user_id: str
    created_at: datetime
    role: Optional[str] = None
    transcript_count: Optional[int] = 0

    class Config:
        from_attributes = True

class WorkspaceInviteRequest(BaseModel):
    email: EmailStr
    role: str = Field(default="editor", pattern="^(admin|editor|viewer)$")
