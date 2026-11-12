from fastapi import APIRouter, Depends, status
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import get_db
from app.api.deps import get_current_user
from app.models.entities import User
from app.schemas.transcript import TranscriptDetailResponse
from app.services.transcript_service import TranscriptService

router = APIRouter(prefix="/transcripts", tags=["Transcripts"])

@router.get("/{transcript_id}", response_model=TranscriptDetailResponse)
async def get_transcript_details(
    transcript_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Retrieve full transcript text, speakers, and timestamped segments."""
    service = TranscriptService(db)
    return await service.get_transcript(transcript_id, current_user.id)

@router.delete("/{transcript_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_transcript(
    transcript_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Delete a transcript and its segments."""
    service = TranscriptService(db)
    await service.delete_transcript(transcript_id, current_user.id)
    return None
