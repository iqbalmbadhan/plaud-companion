import pytest
from httpx import AsyncClient

@pytest.mark.asyncio
async def test_register_and_login_flow(client: AsyncClient):
    # 1. Register a new user
    reg_payload = {
        "email": "jordan@example.com",
        "password": "SecurePassword123!",
        "display_name": "Jordan Doe",
    }
    resp = await client.post("/api/v1/auth/register", json=reg_payload)
    assert resp.status_code == 201
    data = resp.json()
    assert "access_token" in data
    assert "refresh_token" in data
    assert data["token_type"] == "bearer"

    token = data["access_token"]

    # 2. Duplicate registration should return 409 Conflict
    dup_resp = await client.post("/api/v1/auth/register", json=reg_payload)
    assert dup_resp.status_code == 409

    # 3. Fetch profile with Bearer token
    me_resp = await client.get(
        "/api/v1/auth/me",
        headers={"Authorization": f"Bearer {token}"},
    )
    assert me_resp.status_code == 200
    me_data = me_resp.json()
    assert me_data["email"] == "jordan@example.com"
    assert me_data["display_name"] == "Jordan Doe"

    # 4. Login with correct credentials
    login_resp = await client.post(
        "/api/v1/auth/login",
        json={"email": "jordan@example.com", "password": "SecurePassword123!"},
    )
    assert login_resp.status_code == 200
    assert "access_token" in login_resp.json()

    # 5. Login with invalid password returns 401
    bad_login = await client.post(
        "/api/v1/auth/login",
        json={"email": "jordan@example.com", "password": "WrongPassword!"},
    )
    assert bad_login.status_code == 401
