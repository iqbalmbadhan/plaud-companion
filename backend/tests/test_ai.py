import pytest
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_ai_connectors_and_productivity_flow(client: AsyncClient):
    # 1. Register User
    reg = await client.post(
        "/api/v1/auth/register",
        json={
            "email": "ai.user@example.com",
            "password": "Password123!",
            "display_name": "AI Professional",
        },
    )
    assert reg.status_code == 201
    token = reg.json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}

    # Fetch personal workspace
    ws_list = await client.get("/api/v1/workspaces", headers=headers)
    ws_id = ws_list.json()[0]["id"]

    # 2. List AI Connectors (verify OpenRouter, Gemini, Anthropic, OpenAI are present)
    connectors_resp = await client.get(
        f"/api/v1/workspaces/{ws_id}/ai/connectors",
        headers=headers,
    )
    assert connectors_resp.status_code == 200
    connectors = connectors_resp.json()
    provider_ids = [c["provider_id"] for c in connectors]
    assert "openrouter" in provider_ids
    assert "gemini" in provider_ids
    assert "anthropic" in provider_ids
    assert "openai" in provider_ids

    # Verify OpenRouter has 1-click easy connect URL for non-technical users
    openrouter_info = next(c for c in connectors if c["provider_id"] == "openrouter")
    assert openrouter_info["easy_connect_url"] is not None
    assert "openrouter.ai/auth" in openrouter_info["easy_connect_url"]

    # 3. Create a Transcript to run AI operations against
    t_create = await client.post(
        f"/api/v1/workspaces/{ws_id}/transcripts",
        json={
            "title": "Q4 Strategic Planning",
            "content_text": (
                "Jordan: The website redesign must launch before Nov 30. Mobile navigation is the highest priority.\n"
                "Sarah: I will deliver the updated design mockups next Wednesday.\n"
                "Alex: Let's also test page speed and mobile accessibility before final sign-off."
            ),
        },
        headers=headers,
    )
    assert t_create.status_code == 201
    transcript_id = t_create.json()["id"]

    # 4. Generate Summary (Concise)
    summary_resp = await client.post(
        f"/api/v1/transcripts/{transcript_id}/summary",
        json={"summary_type": "concise"},
        headers=headers,
    )
    assert summary_resp.status_code == 200
    sum_data = summary_resp.json()
    assert sum_data["summary_type"] == "concise"
    assert len(sum_data["summary_text"]) > 10
    assert len(sum_data["bullet_points"]) >= 2

    # 5. Extract Action Items
    actions_resp = await client.post(
        f"/api/v1/transcripts/{transcript_id}/action-items",
        headers=headers,
    )
    assert actions_resp.status_code == 200
    act_data = actions_resp.json()
    assert act_data["total_count"] >= 1
    assert any("Sarah" in (a.get("assignee") or "") or "Wednesday" in (a.get("due_date") or "") for a in act_data["action_items"])

    # 6. Ask Question Across Transcripts with Source Citations
    ask_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/ai/ask",
        json={
            "question": "What is the deadline for the website redesign?",
            "transcript_ids": [transcript_id],
        },
        headers=headers,
    )
    assert ask_resp.status_code == 200
    ask_data = ask_resp.json()
    assert len(ask_data["answer"]) > 10
    assert len(ask_data["citations"]) == 1
    assert ask_data["citations"][0]["transcript_id"] == transcript_id
    assert ask_data["has_sufficient_evidence"] is True
