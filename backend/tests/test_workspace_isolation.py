import pytest
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_workspace_isolation_and_permissions(client: AsyncClient):
    # 1. Register User A
    user_a_reg = await client.post(
        "/api/v1/auth/register",
        json={
            "email": "user.a@example.com",
            "password": "Password123!",
            "display_name": "User A",
        },
    )
    assert user_a_reg.status_code == 201
    token_a = user_a_reg.json()["access_token"]
    headers_a = {"Authorization": f"Bearer {token_a}"}

    # Verify User A has a default personal workspace
    ws_a_list = await client.get("/api/v1/workspaces", headers=headers_a)
    assert ws_a_list.status_code == 200
    workspaces_a = ws_a_list.json()
    assert len(workspaces_a) == 1
    workspace_a_id = workspaces_a[0]["id"]
    assert workspaces_a[0]["role"] == "owner"

    # 2. Register User B
    user_b_reg = await client.post(
        "/api/v1/auth/register",
        json={
            "email": "user.b@example.com",
            "password": "Password123!",
            "display_name": "User B",
        },
    )
    assert user_b_reg.status_code == 201
    token_b = user_b_reg.json()["access_token"]
    headers_b = {"Authorization": f"Bearer {token_b}"}

    ws_b_list = await client.get("/api/v1/workspaces", headers=headers_b)
    workspace_b_id = ws_b_list.json()[0]["id"]

    # 3. User A creates a transcript in Workspace A
    t_payload = {
        "title": "Confidential Q4 Strategy Meeting",
        "description": "Financial planning and executive decisions",
        "content_text": "Speaker 1: Revenue projected to increase by 25 percent. Priority is privacy.",
        "segments": [
            {
                "sequence_number": 1,
                "speaker_label": "Speaker 1",
                "start_ms": 0,
                "end_ms": 4200,
                "text": "Revenue projected to increase by 25 percent. Priority is privacy.",
            }
        ],
    }
    t_create = await client.post(
        f"/api/v1/workspaces/{workspace_a_id}/transcripts",
        json=t_payload,
        headers=headers_a,
    )
    assert t_create.status_code == 201
    transcript_a_id = t_create.json()["id"]

    # 4. User B attempts to access Workspace A details -> MUST BE FORBIDDEN (403)
    ws_leak_attempt = await client.get(
        f"/api/v1/workspaces/{workspace_a_id}",
        headers=headers_b,
    )
    assert ws_leak_attempt.status_code == 403

    # 5. User B attempts to list transcripts in Workspace A -> MUST BE FORBIDDEN (403)
    list_leak_attempt = await client.get(
        f"/api/v1/workspaces/{workspace_a_id}/transcripts",
        headers=headers_b,
    )
    assert list_leak_attempt.status_code == 403

    # 6. User B attempts to directly fetch Transcript A -> MUST BE FORBIDDEN (403)
    direct_fetch_leak = await client.get(
        f"/api/v1/transcripts/{transcript_a_id}",
        headers=headers_b,
    )
    assert direct_fetch_leak.status_code == 403

    # 7. User B attempts to delete Transcript A -> MUST BE FORBIDDEN (403)
    delete_leak = await client.delete(
        f"/api/v1/transcripts/{transcript_a_id}",
        headers=headers_b,
    )
    assert delete_leak.status_code == 403

    # 8. User A can retrieve Transcript A with full segments
    t_get = await client.get(
        f"/api/v1/transcripts/{transcript_a_id}",
        headers=headers_a,
    )
    assert t_get.status_code == 200
    t_data = t_get.json()
    assert t_data["title"] == "Confidential Q4 Strategy Meeting"
    assert len(t_data["segments"]) == 1
    assert t_data["segments"][0]["speaker_label"] == "Speaker 1"

    # 9. Duplicate detection within Workspace A should return 409 Conflict
    dup_create = await client.post(
        f"/api/v1/workspaces/{workspace_a_id}/transcripts",
        json=t_payload,
        headers=headers_a,
    )
    assert dup_create.status_code == 409

    # 10. The same content uploaded to Workspace B (different tenant) should SUCCEED
    # and not leak duplicate state across workspaces
    user_b_same_content = await client.post(
        f"/api/v1/workspaces/{workspace_b_id}/transcripts",
        json=t_payload,
        headers=headers_b,
    )
    assert user_b_same_content.status_code == 201

    # 11. User A deletes Transcript A -> 204 No Content
    t_del = await client.delete(
        f"/api/v1/transcripts/{transcript_a_id}",
        headers=headers_a,
    )
    assert t_del.status_code == 204
