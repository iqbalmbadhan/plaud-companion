from typing import List
from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy import select
from sqlalchemy.orm import selectinload
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import get_db
from app.api.deps import get_current_user
from app.models.entities import User, Transcript, TranscriptSegment
from app.schemas.ai import (
    ConnectorInfo,
    ConnectorConnectRequest,
    ConnectorTestResponse,
    SummaryRequest,
    SummaryResponse,
    ActionItemsResponse,
    AskQuestionRequest,
    AskQuestionResponse,
)
from app.services.ai.connector_registry import ConnectorRegistry
from app.services.workspace_service import WorkspaceService

router = APIRouter(tags=["AI Connectors & Productivity"])

@router.get("/workspaces/{workspace_id}/ai/connectors", response_model=List[ConnectorInfo])
async def list_ai_connectors(
    workspace_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    List all AI connectors (OpenRouter, Google Gemini, Anthropic Claude, OpenAI)
    with 1-click connect status and setup links for non-technical users.
    """
    registry = ConnectorRegistry(db)
    return await registry.list_workspace_connectors(workspace_id, current_user.id)

@router.post(
    "/workspaces/{workspace_id}/ai/connectors/{provider_id}/connect",
    response_model=ConnectorTestResponse,
)
async def connect_ai_provider(
    workspace_id: str,
    provider_id: str,
    request: ConnectorConnectRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Connect an AI provider with 1-click (or paste token/key) and verify immediately.
    """
    registry = ConnectorRegistry(db)
    return await registry.connect_provider(
        workspace_id=workspace_id,
        user_id=current_user.id,
        provider_id=provider_id,
        api_key=request.api_key,
        default_model=request.model,
    )

@router.post(
    "/workspaces/{workspace_id}/ai/connectors/{provider_id}/test",
    response_model=ConnectorTestResponse,
)
async def test_ai_provider_connection(
    workspace_id: str,
    provider_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Test existing AI connector health in the workspace.
    """
    registry = ConnectorRegistry(db)
    connector, key, _ = await registry.get_active_connector(workspace_id, provider_id)
    return await connector.test_connection(api_key=key)

@router.post("/transcripts/{transcript_id}/summary", response_model=SummaryResponse)
async def generate_transcript_summary(
    transcript_id: str,
    request: SummaryRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Generate an AI summary (concise, detailed, executive, or meeting minutes).
    """
    stmt = select(Transcript).where(Transcript.id == transcript_id, Transcript.deleted_at.is_(None))
    res = await db.execute(stmt)
    transcript = res.scalars().first()
    if not transcript:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Transcript not found.")

    ws_service = WorkspaceService(db)
    await ws_service.require_membership(transcript.workspace_id, current_user.id)

    registry = ConnectorRegistry(db)
    connector, key, default_model = await registry.get_active_connector(
        transcript.workspace_id, request.provider_id
    )

    return await connector.generate_summary(
        transcript_title=transcript.title,
        content_text=transcript.content_text,
        summary_type=request.summary_type,
        model=request.model or default_model,
        api_key=key,
    )

@router.post("/transcripts/{transcript_id}/action-items", response_model=ActionItemsResponse)
async def extract_transcript_action_items(
    transcript_id: str,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Extract action items, assignees, and target dates from transcript.
    """
    stmt = select(Transcript).where(Transcript.id == transcript_id, Transcript.deleted_at.is_(None))
    res = await db.execute(stmt)
    transcript = res.scalars().first()
    if not transcript:
        raise HTTPException(status_code=status.HTTP_404_NOT_FOUND, detail="Transcript not found.")

    ws_service = WorkspaceService(db)
    await ws_service.require_membership(transcript.workspace_id, current_user.id)

    registry = ConnectorRegistry(db)
    connector, key, default_model = await registry.get_active_connector(transcript.workspace_id)

    return await connector.extract_action_items(
        transcript_title=transcript.title,
        content_text=transcript.content_text,
        model=default_model,
        api_key=key,
    )

@router.post("/workspaces/{workspace_id}/ai/ask", response_model=AskQuestionResponse)
async def ask_question_across_transcripts(
    workspace_id: str,
    request: AskQuestionRequest,
    current_user: User = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """
    Ask AI a question grounded strictly across selected transcripts with source citations.
    """
    ws_service = WorkspaceService(db)
    await ws_service.require_membership(workspace_id, current_user.id)

    # Fetch and authorize every selected transcript in workspace
    stmt = (
        select(Transcript)
        .options(selectinload(Transcript.segments))
        .where(
            Transcript.id.in_(request.transcript_ids),
            Transcript.workspace_id == workspace_id,
            Transcript.deleted_at.is_(None),
        )
    )
    res = await db.execute(stmt)
    transcripts = res.scalars().all()

    if not transcripts:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="None of the specified transcripts were found in this workspace.",
        )

    t_data = [
        {
            "id": t.id,
            "title": t.title,
            "text": t.content_text,
            "speaker": t.segments[0].speaker_label if t.segments else "Speaker",
        }
        for t in transcripts
    ]

    registry = ConnectorRegistry(db)
    connector, key, default_model = await registry.get_active_connector(
        workspace_id, request.provider_id
    )

    return await connector.answer_question(
        question=request.question,
        transcripts=t_data,
        model=request.model or default_model,
        api_key=key,
    )
