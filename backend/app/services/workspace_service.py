from typing import List, Optional, Tuple
from sqlalchemy import select, func
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi import HTTPException, status
from app.models.entities import (
    Workspace,
    WorkspaceType,
    WorkspaceMember,
    WorkspaceRole,
    Transcript,
    User,
)
from app.schemas.workspace import (
    WorkspaceCreateRequest,
    WorkspaceUpdateRequest,
    WorkspaceResponse,
    WorkspaceMemberResponse,
    WorkspaceInviteRequest,
)

class WorkspaceService:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_membership(
        self, workspace_id: str, user_id: str
    ) -> Optional[WorkspaceMember]:
        stmt = (
            select(WorkspaceMember)
            .where(
                WorkspaceMember.workspace_id == workspace_id,
                WorkspaceMember.user_id == user_id,
                WorkspaceMember.status == "active",
            )
        )
        result = await self.db.execute(stmt)
        return result.scalars().first()

    async def require_membership(
        self,
        workspace_id: str,
        user_id: str,
        allowed_roles: Optional[List[WorkspaceRole]] = None,
    ) -> WorkspaceMember:
        membership = await self.get_membership(workspace_id, user_id)
        if not membership:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Access denied. You are not a member of this workspace.",
            )
        if allowed_roles and membership.role not in allowed_roles:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Insufficient permissions. Required one of: {[r.value for r in allowed_roles]}",
            )
        return membership

    async def list_user_workspaces(self, user_id: str) -> List[WorkspaceResponse]:
        stmt = (
            select(
                Workspace,
                WorkspaceMember.role,
                func.count(Transcript.id).label("transcript_count"),
            )
            .join(WorkspaceMember, WorkspaceMember.workspace_id == Workspace.id)
            .outerjoin(Transcript, Transcript.workspace_id == Workspace.id)
            .where(
                WorkspaceMember.user_id == user_id,
                WorkspaceMember.status == "active",
            )
            .group_by(Workspace.id, WorkspaceMember.role)
            .order_by(Workspace.created_at.desc())
        )
        results = await self.db.execute(stmt)
        workspaces = []
        for ws, role, count in results.all():
            resp = WorkspaceResponse(
                id=ws.id,
                name=ws.name,
                type=ws.type.value,
                owner_user_id=ws.owner_user_id,
                created_at=ws.created_at,
                role=role.value,
                transcript_count=count,
            )
            workspaces.append(resp)
        return workspaces

    async def create_workspace(
        self, user_id: str, request: WorkspaceCreateRequest
    ) -> WorkspaceResponse:
        ws_type = WorkspaceType(request.type)
        ws = Workspace(
            name=request.name.strip(),
            type=ws_type,
            owner_user_id=user_id,
        )
        self.db.add(ws)
        await self.db.flush()

        membership = WorkspaceMember(
            workspace_id=ws.id,
            user_id=user_id,
            role=WorkspaceRole.OWNER,
            status="active",
        )
        self.db.add(membership)
        await self.db.commit()
        await self.db.refresh(ws)

        return WorkspaceResponse(
            id=ws.id,
            name=ws.name,
            type=ws.type.value,
            owner_user_id=ws.owner_user_id,
            created_at=ws.created_at,
            role=WorkspaceRole.OWNER.value,
            transcript_count=0,
        )

    async def get_workspace(self, workspace_id: str, user_id: str) -> WorkspaceResponse:
        membership = await self.require_membership(workspace_id, user_id)
        stmt = (
            select(Workspace, func.count(Transcript.id).label("transcript_count"))
            .outerjoin(Transcript, Transcript.workspace_id == Workspace.id)
            .where(Workspace.id == workspace_id)
            .group_by(Workspace.id)
        )
        res = await self.db.execute(stmt)
        row = res.first()
        if not row:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Workspace not found.",
            )
        ws, count = row
        return WorkspaceResponse(
            id=ws.id,
            name=ws.name,
            type=ws.type.value,
            owner_user_id=ws.owner_user_id,
            created_at=ws.created_at,
            role=membership.role.value,
            transcript_count=count,
        )

    async def list_members(
        self, workspace_id: str, user_id: str
    ) -> List[WorkspaceMemberResponse]:
        await self.require_membership(workspace_id, user_id)
        stmt = (
            select(WorkspaceMember, User.email, User.display_name)
            .join(User, User.id == WorkspaceMember.user_id)
            .where(WorkspaceMember.workspace_id == workspace_id)
            .order_by(WorkspaceMember.joined_at.asc())
        )
        results = await self.db.execute(stmt)
        members = []
        for member, email, display_name in results.all():
            resp = WorkspaceMemberResponse(
                id=member.id,
                user_id=member.user_id,
                workspace_id=member.workspace_id,
                role=member.role.value,
                status=member.status,
                joined_at=member.joined_at,
                email=email,
                display_name=display_name,
            )
            members.append(resp)
        return members
