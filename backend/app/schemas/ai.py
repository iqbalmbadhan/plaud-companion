from typing import List, Optional
from pydantic import BaseModel, Field

class ConnectorInfo(BaseModel):
    provider_id: str
    name: str
    description: str
    is_configured: bool
    is_active: bool
    default_model: str
    available_models: List[str]
    setup_help_url: Optional[str] = None
    easy_connect_url: Optional[str] = None
    auth_type: str = "oauth"  # "oauth", "api_key", "instant"
    tag: Optional[str] = None  # "Recommended", "Multi-Model", "Fast"

class ConnectorConnectRequest(BaseModel):
    api_key: Optional[str] = Field(None, description="API key or OAuth token")
    model: Optional[str] = Field(None, description="Preferred default model")

class ConnectorTestResponse(BaseModel):
    success: bool
    message: str
    provider_id: str
    latency_ms: Optional[int] = None

class OAuthExchangeRequest(BaseModel):
    code: str = Field(description="OAuth authorization code returned from provider")
    callback_url: Optional[str] = None

class SummaryRequest(BaseModel):
    summary_type: str = Field(
        default="concise",
        pattern="^(concise|detailed|executive|meeting_minutes)$",
    )
    provider_id: Optional[str] = None
    model: Optional[str] = None

class SummaryResponse(BaseModel):
    summary_type: str
    summary_text: str
    bullet_points: List[str]
    provider_used: str
    model_used: str

class ActionItem(BaseModel):
    task: str
    assignee: Optional[str] = None
    due_date: Optional[str] = None
    status: str = "pending"

class ActionItemsResponse(BaseModel):
    action_items: List[ActionItem]
    total_count: int
    provider_used: str

class SourceCitation(BaseModel):
    transcript_id: str
    transcript_title: str
    sequence_number: Optional[int] = None
    speaker: Optional[str] = None
    timestamp: Optional[str] = None
    excerpt: str

class AskQuestionRequest(BaseModel):
    question: str = Field(min_length=2, max_length=1000)
    transcript_ids: List[str] = Field(min_length=1, max_length=20)
    provider_id: Optional[str] = None
    model: Optional[str] = None

class AskQuestionResponse(BaseModel):
    answer: str
    citations: List[SourceCitation]
    provider_used: str
    model_used: str
    has_sufficient_evidence: bool = True
