from fastapi import APIRouter, Depends, Response, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import get_db
from app.api.deps import get_current_user
from app.models.entities import User
from app.schemas.export import ExportCreateRequest, ExportJobResponse
from app.services.exports.export_service import ExportService

router = APIRouter(tags=["Exports"])

@router.post(
    "/workspaces/{workspace_id}/exports",
    response_model=ExportJobResponse,
    status_code=status.HTTP_201_CREATED,
)
async def create_bulk_export(
    workspace_id: str,
    request: ExportCreateRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Generate Word DOCX documents for selected transcripts and package into a verified ZIP archive.
    """
    service = ExportService(db)
    return await service.create_export_job(
        workspace_id=workspace_id,
        user_id=current_user.id,
        request=request,
    )

@router.get("/exports/{export_id}", response_model=ExportJobResponse)
async def get_export_status(
    export_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Get status and items of an export job."""
    service = ExportService(db)
    return await service.get_export_job(export_id, current_user.id)

@router.get("/exports/{export_id}/download")
async def download_export_file(
    export_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Download the verified ZIP archive."""
    service = ExportService(db)
    archive_bytes, filename = await service.download_export_archive(export_id, current_user.id)
    return Response(
        content=archive_bytes,
        media_type="application/zip",
        headers={"Content-Disposition": f'attachment; filename="{filename}"'},
    )
