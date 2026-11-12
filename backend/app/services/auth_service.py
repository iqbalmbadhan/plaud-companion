import hashlib
from typing import Optional, Tuple
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi import HTTPException, status
from app.core.security import hash_password, verify_password, create_access_token, create_refresh_token
from app.core.config import settings
from app.models.entities import User, UserStatus, Workspace, WorkspaceType, WorkspaceMember, WorkspaceRole
from app.schemas.auth import UserRegisterRequest, UserLoginRequest, TokenResponse

class AuthService:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def get_by_email(self, email: str) -> Optional[User]:
        normalized = email.strip().lower()
        stmt = select(User).where(User.email == normalized)
        result = await self.db.execute(stmt)
        return result.scalars().first()

    async def get_by_id(self, user_id: str) -> Optional[User]:
        stmt = select(User).where(User.id == user_id)
        result = await self.db.execute(stmt)
        return result.scalars().first()

    async def register(self, request: UserRegisterRequest) -> Tuple[User, TokenResponse]:
        normalized_email = request.email.strip().lower()
        existing = await self.get_by_email(normalized_email)
        if existing:
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail="A user with this email address already exists.",
            )

        hashed = hash_password(request.password)
        user = User(
            email=normalized_email,
            display_name=request.display_name.strip(),
            password_hash=hashed,
            status=UserStatus.ACTIVE,
        )
        self.db.add(user)
        await self.db.flush()

        # Automatically provision Personal Workspace for the new user
        personal_ws = Workspace(
            name=f"{user.display_name}'s Workspace",
            type=WorkspaceType.PERSONAL,
            owner_user_id=user.id,
        )
        self.db.add(personal_ws)
        await self.db.flush()

        membership = WorkspaceMember(
            workspace_id=personal_ws.id,
            user_id=user.id,
            role=WorkspaceRole.OWNER,
            status="active",
        )
        self.db.add(membership)
        await self.db.commit()
        await self.db.refresh(user)

        tokens = TokenResponse(
            access_token=create_access_token(user.id),
            refresh_token=create_refresh_token(user.id),
            expires_in=settings.ACCESS_TOKEN_EXPIRE_MINUTES * 60,
        )
        return user, tokens

    async def login(self, request: UserLoginRequest) -> Tuple[User, TokenResponse]:
        normalized_email = request.email.strip().lower()
        user = await self.get_by_email(normalized_email)
        if not user or not verify_password(request.password, user.password_hash):
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid email or password.",
            )

        if user.status != UserStatus.ACTIVE:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Account is suspended or pending verification.",
            )

        tokens = TokenResponse(
            access_token=create_access_token(user.id),
            refresh_token=create_refresh_token(user.id),
            expires_in=settings.ACCESS_TOKEN_EXPIRE_MINUTES * 60,
        )
        return user, tokens
