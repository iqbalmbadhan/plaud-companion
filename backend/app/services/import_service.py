import hashlib
import uuid
from typing import List, Tuple, Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi import HTTPException, status
from app.models.entities import (
    Transcript,
    TranscriptSegment,
    TranscriptFile,
    TranscriptStatus,
    WorkspaceRole,
    AuditEvent,
)
from app.services.extractors import extract_document, ExtractionError
from app.services.storage import get_storage
from app.services.workspace_service import WorkspaceService
from app.schemas.transcript import TranscriptResponse
from app.schemas.import_job import ImportItemResult, BatchImportResponse

class ImportService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.workspace_service = WorkspaceService(db)
        self.storage = get_storage()

    async def import_single_file(
        self,
        workspace_id: str,
        user_id: str,
        filename: str,
        file_bytes: bytes,
        project_id: Optional[str] = None,
    ) -> ImportItemResult:
        # 1. Authorize workspace editor/admin/owner
        await self.workspace_service.require_membership(
            workspace_id,
            user_id,
            allowed_roles=[WorkspaceRole.OWNER, WorkspaceRole.ADMIN, WorkspaceRole.EDITOR],
        )

        # 2. Extract content
        try:
            doc = extract_document(file_bytes, filename)
        except ExtractionError as e:
            return ImportItemResult(
                filename=filename,
                status="error",
                error_code=e.code,
                error_message=e.message,
            )
        except Exception as e:
            return ImportItemResult(
                filename=filename,
                status="error",
                error_code="UNEXPECTED_ERROR",
                error_message=f"Failed to process file: {str(e)}",
            )

        # 3. Calculate SHA-256
        content_hash = hashlib.sha256(doc.content_text.encode("utf-8")).hexdigest()
        file_sha256 = hashlib.sha256(file_bytes).hexdigest()

        # 4. Workspace duplicate check
        dup_stmt = select(Transcript).where(
            Transcript.workspace_id == workspace_id,
            Transcript.content_hash == content_hash,
            Transcript.deleted_at.is_(None),
        )
        dup_res = await self.db.execute(dup_stmt)
        existing_dup = dup_res.scalars().first()
        if existing_dup:
            return ImportItemResult(
                filename=filename,
                status="duplicate",
                transcript_id=existing_dup.id,
                error_code="DUPLICATE_CONTENT",
                error_message="Transcript with identical content already exists in this workspace.",
            )

        # 5. Store file in private storage
        transcript_id = str(uuid.uuid4())
        storage_key = f"workspaces/{workspace_id}/transcripts/{transcript_id}/original_{filename}"
        await self.storage.put(storage_key, file_bytes)

        # 6. Create Transcript & Segment records
        transcript = Transcript(
            id=transcript_id,
            workspace_id=workspace_id,
            project_id=project_id,
            created_by_user_id=user_id,
            title=doc.title,
            source_type=f"file_import_{doc.file_type}",
            recorded_at=doc.recorded_at,
            language=doc.language or "en",
            status=TranscriptStatus.AI_READY,
            content_text=doc.content_text,
            content_hash=content_hash,
        )
        self.db.add(transcript)

        for seg in doc.segments:
            db_seg = TranscriptSegment(
                transcript_id=transcript_id,
                sequence_number=seg.sequence_number,
                speaker_label=seg.speaker_label,
                start_ms=seg.start_ms,
                end_ms=seg.end_ms,
                text=seg.text,
            )
            self.db.add(db_seg)

        # 7. Create TranscriptFile
        db_file = TranscriptFile(
            transcript_id=transcript_id,
            workspace_id=workspace_id,
            storage_key=storage_key,
            original_filename=filename,
            mime_type=f"application/{doc.file_type}",
            size_bytes=len(file_bytes),
            sha256=file_sha256,
            file_role="original",
        )
        self.db.add(db_file)

        # 8. Audit event
        audit = AuditEvent(
            workspace_id=workspace_id,
            actor_user_id=user_id,
            action="transcript.imported",
            resource_type="transcript",
            resource_id=transcript_id,
            event_metadata={"filename": filename, "format": doc.file_type, "size": len(file_bytes)},
        )
        self.db.add(audit)
        await self.db.commit()

        t_resp = TranscriptResponse(
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
            segment_count=len(doc.segments),
        )

        return ImportItemResult(
            filename=filename,
            status="success",
            transcript_id=transcript_id,
            transcript=t_resp,
        )

    async def import_batch(
        self,
        workspace_id: str,
        user_id: str,
        files: List[Tuple[str, bytes]],
        project_id: Optional[str] = None,
    ) -> BatchImportResponse:
        results: List[ImportItemResult] = []
        success_count = 0
        dup_count = 0
        fail_count = 0

        for filename, data in files:
            res = await self.import_single_file(
                workspace_id=workspace_id,
                user_id=user_id,
                filename=filename,
                file_bytes=data,
                project_id=project_id,
            )
            results.append(res)
            if res.status == "success":
                success_count += 1
            elif res.status == "duplicate":
                dup_count += 1
            else:
                fail_count += 1

        return BatchImportResponse(
            workspace_id=workspace_id,
            total_files=len(files),
            successful_count=success_count,
            duplicate_count=dup_count,
            failed_count=fail_count,
            results=results,
        )
