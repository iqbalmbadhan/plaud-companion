from typing import List, Optional
from fastapi import APIRouter, Depends, UploadFile, File, Form, status, HTTPException
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import get_db
from app.api.deps import get_current_user
from app.models.entities import User
from app.services.import_service import ImportService
from app.schemas.import_job import BatchImportResponse

router = APIRouter(tags=["Imports"])

@router.post(
    "/workspaces/{workspace_id}/imports",
    response_model=BatchImportResponse,
    status_code=status.HTTP_200_OK,
)
async def upload_transcript_files(
    workspace_id: str,
    files: List[UploadFile] = File(...),
    project_id: Optional[str] = Form(None),
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Import one or more transcript files (TXT, DOCX, PDF, SRT).
    Extracts text, segments, speaker labels, timestamps, validates size/format,
    deduplicates against the workspace, and persists records.
    """
    if not files:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="No files provided for upload.",
        )

    file_payloads = []
    for upload in files:
        filename = upload.filename or "uploaded_transcript.txt"
        data = await upload.read()
        file_payloads.append((filename, data))

    service = ImportService(db)
    return await service.import_batch(
        workspace_id=workspace_id,
        user_id=current_user.id,
        files=file_payloads,
        project_id=project_id,
    )
