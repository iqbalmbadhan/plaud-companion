import hashlib
from typing import List, Optional
from sqlalchemy import select, func, or_
from sqlalchemy.orm import selectinload
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi import HTTPException, status
from app.models.entities import (
    Transcript,
    TranscriptSegment,
    TranscriptStatus,
    WorkspaceRole,
)
from app.schemas.transcript import (
    TranscriptCreateRequest,
    TranscriptUpdateRequest,
    TranscriptResponse,
    TranscriptDetailResponse,
    TranscriptSegmentResponse,
)
from app.services.workspace_service import WorkspaceService

class TranscriptService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.workspace_service = WorkspaceService(db)

    async def list_transcripts(
        self,
        workspace_id: str,
        user_id: str,
        project_id: Optional[str] = None,
        query: Optional[str] = None,
        limit: int = 50,
        offset: int = 0,
    ) -> List[TranscriptResponse]:
        # Enforce that user is a member of this workspace
        await self.workspace_service.require_membership(workspace_id, user_id)

        stmt = (
            select(
                Transcript,
                func.count(TranscriptSegment.id).label("segment_count"),
            )
            .outerjoin(TranscriptSegment, TranscriptSegment.transcript_id == Transcript.id)
            .where(
                Transcript.workspace_id == workspace_id,
                Transcript.deleted_at.is_(None),
            )
        )

        if project_id:
            stmt = stmt.where(Transcript.project_id == project_id)

        if query:
            clean_q = f"%{query.strip().lower()}%"
            stmt = stmt.where(
                or_(
                    func.lower(Transcript.title).like(clean_q),
                    func.lower(Transcript.content_text).like(clean_q),
                )
            )

        stmt = (
            stmt.group_by(Transcript.id)
            .order_by(Transcript.created_at.desc())
            .limit(limit)
            .offset(offset)
        )

        results = await self.db.execute(stmt)
        transcripts = []
        for t, count in results.all():
            resp = TranscriptResponse(
                id=t.id,
                workspace_id=t.workspace_id,
                project_id=t.project_id,
                title=t.title,
                description=t.description,
                source_type=t.source_type,
                recorded_at=t.recorded_at,
                language=t.language,
                status=t.status.value,
                content_hash=t.content_hash,
                created_at=t.created_at,
                segment_count=count,
            )
            transcripts.append(resp)
        return transcripts

    async def create_transcript(
        self,
        workspace_id: str,
        user_id: str,
        request: TranscriptCreateRequest,
    ) -> TranscriptDetailResponse:
        # Require editor, admin, or owner role
        await self.workspace_service.require_membership(
            workspace_id,
            user_id,
            allowed_roles=[WorkspaceRole.OWNER, WorkspaceRole.ADMIN, WorkspaceRole.EDITOR],
        )

        # Compute SHA-256 content hash
        content_hash = hashlib.sha256(request.content_text.strip().encode("utf-8")).hexdigest()

        # Check workspace-scoped duplicate
        dup_stmt = select(Transcript).where(
            Transcript.workspace_id == workspace_id,
            Transcript.content_hash == content_hash,
            Transcript.deleted_at.is_(None),
        )
        dup_res = await self.db.execute(dup_stmt)
        if dup_res.scalars().first():
            raise HTTPException(
                status_code=status.HTTP_409_CONFLICT,
                detail="A transcript with the exact same content already exists in this workspace.",
            )

        transcript = Transcript(
            workspace_id=workspace_id,
            project_id=request.project_id,
            created_by_user_id=user_id,
            title=request.title.strip(),
            description=request.description.strip() if request.description else None,
            source_type=request.source_type,
            recorded_at=request.recorded_at,
            language=request.language,
            status=TranscriptStatus.AI_READY,
            content_text=request.content_text.strip(),
            content_hash=content_hash,
        )
        self.db.add(transcript)
        await self.db.flush()

        segments_out = []
        if request.segments:
            for seg_req in request.segments:
                seg = TranscriptSegment(
                    transcript_id=transcript.id,
                    sequence_number=seg_req.sequence_number,
                    speaker_label=seg_req.speaker_label,
                    start_ms=seg_req.start_ms,
                    end_ms=seg_req.end_ms,
                    text=seg_req.text.strip(),
                )
                self.db.add(seg)
                segments_out.append(
                    TranscriptSegmentResponse(
                        id=seg.id,
                        sequence_number=seg.sequence_number,
                        speaker_label=seg.speaker_label,
                        start_ms=seg.start_ms,
                        end_ms=seg.end_ms,
                        text=seg.text,
                    )
                )

        await self.db.commit()
        await self.db.refresh(transcript)

        return TranscriptDetailResponse(
            id=transcript.id,
            workspace_id=transcript.workspace_id,
            project_id=transcript.project_id,
            title=transcript.title,
            description=transcript.description,
            source_type=transcript.source_type,
            recorded_at=transcript.recorded_at,
            language=transcript.language,
            status=transcript.status.value,
            content_hash=transcript.content_hash,
            created_at=transcript.created_at,
            segment_count=len(segments_out),
            content_text=transcript.content_text,
            segments=segments_out,
        )

    async def get_transcript(
        self, transcript_id: str, user_id: str
    ) -> TranscriptDetailResponse:
        stmt = (
            select(Transcript)
            .options(selectinload(Transcript.segments))
            .where(
                Transcript.id == transcript_id,
                Transcript.deleted_at.is_(None),
            )
        )
        res = await self.db.execute(stmt)
        transcript = res.scalars().first()
        if not transcript:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Transcript not found.",
            )

        # Enforce that requesting user is authorized in this transcript's workspace
        await self.workspace_service.require_membership(transcript.workspace_id, user_id)

        segments = [
            TranscriptSegmentResponse(
                id=s.id,
                sequence_number=s.sequence_number,
                speaker_label=s.speaker_label,
                start_ms=s.start_ms,
                end_ms=s.end_ms,
                text=s.text,
            )
            for s in transcript.segments
        ]

        return TranscriptDetailResponse(
            id=transcript.id,
            workspace_id=transcript.workspace_id,
            project_id=transcript.project_id,
            title=transcript.title,
            description=transcript.description,
            source_type=transcript.source_type,
            recorded_at=transcript.recorded_at,
            language=transcript.language,
            status=transcript.status.value,
            content_hash=transcript.content_hash,
            created_at=transcript.created_at,
            segment_count=len(segments),
            content_text=transcript.content_text,
            segments=segments,
        )

    async def delete_transcript(self, transcript_id: str, user_id: str) -> bool:
        stmt = select(Transcript).where(
            Transcript.id == transcript_id,
            Transcript.deleted_at.is_(None),
        )
        res = await self.db.execute(stmt)
        transcript = res.scalars().first()
        if not transcript:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="Transcript not found.",
            )

        # Authorization: must be OWNER, ADMIN, or EDITOR of the transcript's workspace
        await self.workspace_service.require_membership(
            transcript.workspace_id,
            user_id,
            allowed_roles=[WorkspaceRole.OWNER, WorkspaceRole.ADMIN, WorkspaceRole.EDITOR],
        )

        await self.db.delete(transcript)
        await self.db.commit()
        return True
