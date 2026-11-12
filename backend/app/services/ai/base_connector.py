import time
from abc import ABC, abstractmethod
from typing import List, Optional
from app.schemas.ai import (
    ConnectorInfo,
    ConnectorTestResponse,
    SummaryResponse,
    ActionItemsResponse,
    AskQuestionResponse,
    SourceCitation,
)

class AIConnector(ABC):
    @property
    @abstractmethod
    def provider_id(self) -> str:
        pass

    @property
    @abstractmethod
    def name(self) -> str:
        pass

    @property
    @abstractmethod
    def description(self) -> str:
        pass

    @property
    @abstractmethod
    def setup_help_url(self) -> str:
        pass

    @property
    @abstractmethod
    def default_model(self) -> str:
        pass

    @property
    @abstractmethod
    def available_models(self) -> List[str]:
        pass

    def get_info(self, is_configured: bool = False, is_active: bool = False) -> ConnectorInfo:
        return ConnectorInfo(
            provider_id=self.provider_id,
            name=self.name,
            description=self.description,
            is_configured=is_configured,
            is_active=is_active,
            default_model=self.default_model,
            available_models=self.available_models,
            setup_help_url=self.setup_help_url,
            easy_connect_supported=True,
        )

    @abstractmethod
    async def test_connection(self, api_key: Optional[str] = None) -> ConnectorTestResponse:
        pass

    @abstractmethod
    async def generate_summary(
        self,
        transcript_title: str,
        content_text: str,
        summary_type: str = "concise",
        model: Optional[str] = None,
        api_key: Optional[str] = None,
    ) -> SummaryResponse:
        pass

    @abstractmethod
    async def extract_action_items(
        self,
        transcript_title: str,
        content_text: str,
        model: Optional[str] = None,
        api_key: Optional[str] = None,
    ) -> ActionItemsResponse:
        pass

    @abstractmethod
    async def answer_question(
        self,
        question: str,
        transcripts: List[dict],  # list of {"id": str, "title": str, "text": str, "segments": list}
        model: Optional[str] = None,
        api_key: Optional[str] = None,
    ) -> AskQuestionResponse:
        pass
