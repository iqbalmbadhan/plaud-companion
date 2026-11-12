import uuid
import re
from datetime import datetime, timezone, timedelta
from typing import List, Optional
from sqlalchemy import select
from sqlalchemy.orm import selectinload
from sqlalchemy.ext.asyncio import AsyncSession
from fastapi import HTTPException, status
from app.models.entities import (
    Workspace,
    Transcript,
    ExportJob,
    ExportJobItem,
    ExportStatus,
    WorkspaceRole,
    AuditEvent,
)
from app.schemas.export import ExportCreateRequest, ExportJobResponse, ExportItemResponse
from app.services.exports.docx_generator import generate_docx
from app.services.exports.zip_packager import create_export_zip
from app.services.storage import get_storage
from app.services.workspace_service import WorkspaceService

def _slugify(text: str) -> str:
    s = re.sub(r"[^\w\s-]", "", text).strip().lower()
    return re.sub(r"[-\s]+", "_", s)[:50]

class ExportService:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.workspace_service = WorkspaceService(db)
        self.storage = get_storage()

    async def create_export_job(
        self,
        workspace_id: str,
        user_id: str,
        request: ExportCreateRequest,
    ) -> ExportJobResponse:
        # 1. Authorize user has export permission
        await self.workspace_service.require_membership(
            workspace_id,
            user_id,
            allowed_roles=[WorkspaceRole.OWNER, WorkspaceRole.ADMIN, WorkspaceRole.EDITOR],
        )

        # 2. Fetch requested transcripts strictly within workspace
        stmt = (
            select(Transcript)
            .options(selectinload(Transcript.segments))
            .where(
                Transcript.id.in_(request.transcript_ids),
                Transcript.workspace_id == workspace_id,
                Transcript.deleted_at.is_(None),
            )
        )
        res = await self.db.execute(stmt)
        transcripts = res.scalars().all()

        if not transcripts:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail="None of the specified transcripts were found in this workspace.",
            )

        job_id = str(uuid.uuid4())
        job = ExportJob(
            id=job_id,
            workspace_id=workspace_id,
            requested_by_user_id=user_id,
            status=ExportStatus.PROCESSING,
            template=request.template,
            settings={
                "title": request.title,
                "organization_name": request.organization_name,
                "include_metadata": request.include_metadata,
            },
            total_items=len(transcripts),
            completed_items=0,
            failed_items=0,
            expires_at=datetime.now(timezone.utc) + timedelta(days=7),
        )
        self.db.add(job)
        await self.db.flush()

        docs_to_pack = []
        item_responses: List[ExportItemResponse] = []
        manifest_items = []

        for t in transcripts:
            seg_dicts = [
                {
                    "speaker_label": s.speaker_label,
                    "text": s.text,
                    "time_str": f"{s.start_ms // 1000}s" if s.start_ms else None,
                }
                for s in t.segments
            ]

            doc_title = request.title or t.title
            docx_bytes = generate_docx(
                title=doc_title,
                content_text=t.content_text,
                segments=seg_dicts,
                organization_name=request.organization_name,
                template=request.template,
            )

            filename = f"{_slugify(t.title)}.docx"
            docs_to_pack.append((filename, docx_bytes))

            item_id = str(uuid.uuid4())
            item = ExportJobItem(
                id=item_id,
                export_job_id=job.id,
                transcript_id=t.id,
                status="completed",
                output_storage_key=f"exports/{job.id}/{filename}",
            )
            self.db.add(item)

            item_responses.append(
                ExportItemResponse(
                    id=item_id,
                    transcript_id=t.id,
                    transcript_title=t.title,
                    status="completed",
                    output_filename=filename,
                )
            )

            manifest_items.append({
                "filename": filename,
                "transcript_id": t.id,
                "transcript_title": t.title,
                "status": "completed",
            })

        # Assemble and package ZIP
        manifest_data = {
            "export_id": job.id,
            "workspace_id": workspace_id,
            "template": request.template,
            "created_at": datetime.now(timezone.utc).isoformat(),
            "total_items": len(transcripts),
            "items": manifest_items,
        }
        zip_bytes = create_export_zip(docs_to_pack, manifest_data)

        # Store in private storage
        archive_key = f"workspaces/{workspace_id}/exports/{job.id}/export_archive.zip"
        await self.storage.put(archive_key, zip_bytes, content_type="application/zip")

        job.status = ExportStatus.COMPLETED
        job.completed_items = len(transcripts)
        job.archive_storage_key = archive_key

        # Audit event
        audit = AuditEvent(
            workspace_id=workspace_id,
            actor_user_id=user_id,
            action="export.completed",
            resource_type="export_job",
            resource_id=job.id,
            event_metadata={"total_items": len(transcripts), "template": request.template},
        )
        self.db.add(audit)
        await self.db.commit()

        download_url = await self.storage.create_signed_download_url(archive_key, expires_seconds=86400)

        return ExportJobResponse(
            id=job.id,
            workspace_id=job.workspace_id,
            status=job.status.value,
            template=job.template,
            total_items=job.total_items,
            completed_items=job.completed_items,
            failed_items=job.failed_items,
            download_url=download_url,
            created_at=job.created_at,
            items=item_responses,
        )

    async def get_export_job(
        self, export_id: str, user_id: str
    ) -> ExportJobResponse:
        stmt = (
            select(ExportJob)
            .options(selectinload(ExportJob.items))
            .where(ExportJob.id == export_id)
        )
        res = await self.db.execute(stmt)
        job = res.scalars().first()
        if not job:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Export job not found.")

        await self.workspace_service.require_membership(job.workspace_id, user_id)

        item_responses = []
        for it in job.items:
            item_responses.append(
                ExportItemResponse(
                    id=it.id,
                    transcript_id=it.transcript_id,
                    transcript_title="Transcript Document",
                    status=it.status,
                )
            )

        download_url = None
        if job.archive_storage_key:
            download_url = await self.storage.create_signed_download_url(job.archive_storage_key, expires_seconds=86400)

        return ExportJobResponse(
            id=job.id,
            workspace_id=job.workspace_id,
            status=job.status.value,
            template=job.template,
            total_items=job.total_items,
            completed_items=job.completed_items,
            failed_items=job.failed_items,
            download_url=download_url,
            created_at=job.created_at,
            items=item_responses,
        )

    async def download_export_archive(
        self, export_id: str, user_id: str
    ) -> tuple[bytes, str]:
        stmt = select(ExportJob).where(ExportJob.id == export_id)
        res = await self.db.execute(stmt)
        job = res.scalars().first()
        if not job or not job.archive_storage_key:
            raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Export archive not found.")

        # Re-authorize user
        await self.workspace_service.require_membership(job.workspace_id, user_id)

        data = await self.storage.get(job.archive_storage_key)
        filename = f"export_{job.id[:8]}.zip"
        return data, filename
