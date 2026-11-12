from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy import select, func
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import get_db
from app.api.deps import get_current_user
from app.models.entities import User, Project, Transcript, WorkspaceRole
from app.schemas.project import ProjectCreateRequest, ProjectUpdateRequest, ProjectResponse
from app.services.workspace_service import WorkspaceService

router = APIRouter(tags=["Projects"])

@router.get("/workspaces/{workspace_id}/projects", response_model=List[ProjectResponse])
async def list_workspace_projects(
    workspace_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """List all projects in an authorized workspace."""
    ws_service = WorkspaceService(db)
    await ws_service.require_membership(workspace_id, current_user.id)

    stmt = (
        select(Project, func.count(Transcript.id).label("transcript_count"))
        .outerjoin(Transcript, Transcript.project_id == Project.id)
        .where(Project.workspace_id == workspace_id, Project.deleted_at.is_(None))
        .group_by(Project.id)
        .order_by(Project.created_at.desc())
    )
    res = await db.execute(stmt)
    projects = []
    for p, count in res.all():
        projects.append(
            ProjectResponse(
                id=p.id,
                workspace_id=p.workspace_id,
                name=p.name,
                description=p.description,
                status=p.status,
                created_at=p.created_at,
                transcript_count=count,
            )
        )
    return projects

@router.post("/workspaces/{workspace_id}/projects", response_model=ProjectResponse, status_code=status.HTTP_201_CREATED)
async def create_workspace_project(
    workspace_id: str,
    request: ProjectCreateRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Create a new project in a workspace."""
    ws_service = WorkspaceService(db)
    await ws_service.require_membership(
        workspace_id,
        current_user.id,
        allowed_roles=[WorkspaceRole.OWNER, WorkspaceRole.ADMIN, WorkspaceRole.EDITOR],
    )

    project = Project(
        workspace_id=workspace_id,
        name=request.name.strip(),
        description=request.description.strip() if request.description else None,
        created_by_user_id=current_user.id,
    )
    db.add(project)
    await db.commit()
    await db.refresh(project)

    return ProjectResponse(
        id=project.id,
        workspace_id=project.workspace_id,
        name=project.name,
        description=project.description,
        status=project.status,
        created_at=project.created_at,
        transcript_count=0,
    )

@router.get("/projects/{project_id}", response_model=ProjectResponse)
async def get_project(
    project_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Get project details if authorized."""
    stmt = select(Project).where(Project.id == project_id, Project.deleted_at.is_(None))
    res = await db.execute(stmt)
    project = res.scalars().first()
    if not project:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Project not found.")

    ws_service = WorkspaceService(db)
    await ws_service.require_membership(project.workspace_id, current_user.id)

    count_stmt = select(func.count(Transcript.id)).where(Transcript.project_id == project.id)
    count_res = await db.execute(count_stmt)
    count = count_res.scalar() or 0

    return ProjectResponse(
        id=project.id,
        workspace_id=project.workspace_id,
        name=project.name,
        description=project.description,
        status=project.status,
        created_at=project.created_at,
        transcript_count=count,
    )
