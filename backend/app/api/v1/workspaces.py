from typing import List, Optional
from fastapi import APIRouter, Depends, Query, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import get_db
from app.api.deps import get_current_user
from app.models.entities import User
from app.schemas.workspace import (
    WorkspaceCreateRequest,
    WorkspaceResponse,
    WorkspaceMemberResponse,
)
from app.schemas.transcript import (
    TranscriptCreateRequest,
    TranscriptResponse,
    TranscriptDetailResponse,
)
from app.services.workspace_service import WorkspaceService
from app.services.transcript_service import TranscriptService

router = APIRouter(prefix="/workspaces", tags=["Workspaces"])

@router.get("", response_model=List[WorkspaceResponse])
async def list_workspaces(
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """List all workspaces the authenticated user is an active member of."""
    service = WorkspaceService(db)
    return await service.list_user_workspaces(current_user.id)

@router.post("", response_model=WorkspaceResponse, status_code=status.HTTP_201_CREATED)
async def create_workspace(
    request: WorkspaceCreateRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Create a new team workspace and designate the current user as Owner."""
    service = WorkspaceService(db)
    return await service.create_workspace(current_user.id, request)

@router.get("/{workspace_id}", response_model=WorkspaceResponse)
async def get_workspace(
    workspace_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Get details of a specific workspace if the user is an authorized member."""
    service = WorkspaceService(db)
    return await service.get_workspace(workspace_id, current_user.id)

@router.get("/{workspace_id}/members", response_model=List[WorkspaceMemberResponse])
async def list_workspace_members(
    workspace_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """List members of a workspace."""
    service = WorkspaceService(db)
    return await service.list_members(workspace_id, current_user.id)

@router.get("/{workspace_id}/transcripts", response_model=List[TranscriptResponse])
async def list_workspace_transcripts(
    workspace_id: str,
    project_id: Optional[str] = Query(None),
    q: Optional[str] = Query(None, description="Search query across title and text"),
    limit: int = Query(50, ge=1, le=100),
    offset: int = Query(0, ge=0),
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """List and search transcripts within a specific workspace."""
    service = TranscriptService(db)
    return await service.list_transcripts(
        workspace_id=workspace_id,
        user_id=current_user.id,
        project_id=project_id,
        query=q,
        limit=limit,
        offset=offset,
    )

@router.post(
    "/{workspace_id}/transcripts",
    response_model=TranscriptDetailResponse,
    status_code=status.HTTP_201_CREATED,
)
async def create_workspace_transcript(
    workspace_id: str,
    request: TranscriptCreateRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Create a new transcript in a workspace."""
    service = TranscriptService(db)
    return await service.create_transcript(
        workspace_id=workspace_id,
        user_id=current_user.id,
        request=request,
    )
