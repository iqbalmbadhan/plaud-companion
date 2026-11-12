from typing import Dict, List, Optional
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.entities import ProviderConnection, WorkspaceRole
from app.schemas.ai import ConnectorInfo, ConnectorTestResponse
from app.services.ai.base_connector import AIConnector
from app.services.ai.openrouter_connector import OpenRouterConnector
from app.services.ai.gemini_connector import GeminiConnector
from app.services.ai.anthropic_connector import AnthropicConnector
from app.services.ai.openai_connector import OpenAIConnector
from app.services.workspace_service import WorkspaceService

class ConnectorRegistry:
    def __init__(self, db: AsyncSession):
        self.db = db
        self.workspace_service = WorkspaceService(db)
        self._connectors: Dict[str, AIConnector] = {
            "openrouter": OpenRouterConnector(),
            "gemini": GeminiConnector(),
            "anthropic": AnthropicConnector(),
            "openai": OpenAIConnector(),
        }

    def get_connector(self, provider_id: str) -> Optional[AIConnector]:
        return self._connectors.get(provider_id.lower().strip())

    async def list_workspace_connectors(
        self, workspace_id: str, user_id: str
    ) -> List[ConnectorInfo]:
        await self.workspace_service.require_membership(workspace_id, user_id)

        stmt = select(ProviderConnection).where(
            ProviderConnection.workspace_id == workspace_id,
            ProviderConnection.enabled == True,
        )
        res = await self.db.execute(stmt)
        active_connections = {c.provider: c for c in res.scalars().all()}

        info_list: List[ConnectorInfo] = []
        for pid, connector in self._connectors.items():
            conn = active_connections.get(pid)
            is_configured = conn is not None and bool(conn.api_key_encrypted)
            is_active = conn is not None and conn.enabled

            # Default to OpenRouter or Gemini as active out of the box
            if not active_connections and pid == "openrouter":
                is_active = True

            info = connector.get_info(
                is_configured=is_configured,
                is_active=is_active,
            )
            if conn and conn.default_model:
                info.default_model = conn.default_model
            info_list.append(info)

        return info_list

    async def connect_provider(
        self,
        workspace_id: str,
        user_id: str,
        provider_id: str,
        api_key: Optional[str] = None,
        default_model: Optional[str] = None,
    ) -> ConnectorTestResponse:
        await self.workspace_service.require_membership(
            workspace_id,
            user_id,
            allowed_roles=[WorkspaceRole.OWNER, WorkspaceRole.ADMIN, WorkspaceRole.EDITOR],
        )

        connector = self.get_connector(provider_id)
        if not connector:
            return ConnectorTestResponse(
                success=False,
                provider_id=provider_id,
                message=f"Unknown provider '{provider_id}'.",
            )

        # 1. Test connection first
        test_result = await connector.test_connection(api_key=api_key)
        if not test_result.success and api_key:
            return test_result

        # 2. Upsert connection record
        stmt = select(ProviderConnection).where(
            ProviderConnection.workspace_id == workspace_id,
            ProviderConnection.provider == provider_id.lower().strip(),
        )
        res = await self.db.execute(stmt)
        conn = res.scalars().first()

        if not conn:
            conn = ProviderConnection(
                workspace_id=workspace_id,
                provider=provider_id.lower().strip(),
                api_key_encrypted=api_key,
                auth_mode="api_key" if api_key else "oauth",
                enabled=True,
                default_model=default_model or connector.default_model,
                created_by_user_id=user_id,
            )
            self.db.add(conn)
        else:
            if api_key:
                conn.api_key_encrypted = api_key
            if default_model:
                conn.default_model = default_model
            conn.enabled = True

        await self.db.commit()
        return ConnectorTestResponse(
            success=True,
            provider_id=provider_id,
            message=f"{connector.name} is now connected and active for your workspace!",
        )

    async def get_active_connector(
        self, workspace_id: str, provider_id: Optional[str] = None
    ) -> tuple[AIConnector, Optional[str], Optional[str]]:
        """Returns (connector, api_key, default_model)."""
        if provider_id:
            connector = self.get_connector(provider_id)
            if connector:
                stmt = select(ProviderConnection).where(
                    ProviderConnection.workspace_id == workspace_id,
                    ProviderConnection.provider == provider_id.lower().strip(),
                )
                res = await self.db.execute(stmt)
                conn = res.scalars().first()
                key = conn.api_key_encrypted if conn else None
                model = conn.default_model if conn else None
                return connector, key, model

        # Otherwise find first enabled provider
        stmt = select(ProviderConnection).where(
            ProviderConnection.workspace_id == workspace_id,
            ProviderConnection.enabled == True,
        ).order_by(ProviderConnection.created_at.desc())
        res = await self.db.execute(stmt)
        active_conn = res.scalars().first()

        if active_conn:
            connector = self.get_connector(active_conn.provider)
            if connector:
                return connector, active_conn.api_key_encrypted, active_conn.default_model

        # Default fallback to OpenRouter or Gemini
        default_conn = self.get_connector("openrouter") or self.get_connector("gemini")
        return default_conn, None, None
