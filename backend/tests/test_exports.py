import io
import json
import zipfile
import pytest
from httpx import AsyncClient
from app.services.exports.docx_generator import generate_docx
from app.services.exports.pdf_generator import generate_pdf
from app.services.exports.zip_packager import create_export_zip

def test_generate_docx_structure():
    docx_bytes = generate_docx(
        title="Weekly Business Meeting",
        content_text="Speaker 1: Launch date confirmed.\nSpeaker 2: Verified.",
        segments=[
            {"speaker_label": "Speaker 1", "text": "Launch date confirmed."},
            {"speaker_label": "Speaker 2", "text": "Verified."},
        ],
        template="meeting_minutes",
        ai_summary="All targets on track for release.",
    )
    assert docx_bytes.startswith(b"PK\x03\x04")

    # Verify interior structure of DOCX
    with zipfile.ZipFile(io.BytesIO(docx_bytes), "r") as z:
        names = z.namelist()
        assert "word/document.xml" in names
        assert "[Content_Types].xml" in names
        assert "word/styles.xml" in names
        doc_xml = z.read("word/document.xml").decode("utf-8")
        assert "Weekly Business Meeting" in doc_xml
        assert "Executive Summary" in doc_xml
        assert "Speaker 1" in doc_xml

def test_generate_pdf_structure():
    pdf_bytes = generate_pdf(
        title="Quarterly Review",
        content_text="Jordan: Overview of performance.\nAlex: Numbers verified.",
        segments=[
            {"speaker_label": "Jordan", "text": "Overview of performance."},
            {"speaker_label": "Alex", "text": "Numbers verified."},
        ],
        template="executive_summary",
        ai_summary="Record engagement across all workspaces.",
    )
    assert pdf_bytes.startswith(b"%PDF")
    assert len(pdf_bytes) > 200

def test_zip_packager_integrity():
    docs = [
        ("meeting_1.docx", b"DOCX_CONTENT_1"),
        ("meeting_1.pdf", b"%PDF-1.4 sample"),
    ]
    manifest = {
        "export_id": "test-export-id",
        "total_items": 1,
        "items": [{"filename": "meeting_1.docx"}, {"filename": "meeting_1.pdf"}],
    }
    zip_bytes = create_export_zip(docs, manifest)
    assert zip_bytes.startswith(b"PK\x03\x04")

    with zipfile.ZipFile(io.BytesIO(zip_bytes), "r") as z:
        assert z.testzip() is None
        names = z.namelist()
        assert "meeting_1.docx" in names
        assert "meeting_1.pdf" in names
        assert "manifest.json" in names
        manifest_read = json.loads(z.read("manifest.json").decode("utf-8"))
        assert manifest_read["total_items"] == 1

@pytest.mark.asyncio
async def test_bulk_export_api_flow(client: AsyncClient):
    # 1. Register User
    reg = await client.post(
        "/api/v1/auth/register",
        json={
            "email": "exporter@example.com",
            "password": "Password123!",
            "display_name": "Export Manager",
        },
    )
    assert reg.status_code == 201
    token = reg.json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}

    # Fetch personal workspace
    ws_list = await client.get("/api/v1/workspaces", headers=headers)
    ws_id = ws_list.json()[0]["id"]

    # 2. Create 2 transcripts
    t1_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/transcripts",
        json={
            "title": "Client Discovery Call",
            "content_text": "Jordan: Discussing scope.\nClient: Everything looks good.",
        },
        headers=headers,
    )
    t2_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/transcripts",
        json={
            "title": "Product Planning Review",
            "content_text": "Sarah: Architecture verified.\nAlex: Ready for export.",
        },
        headers=headers,
    )
    t1_id = t1_resp.json()["id"]
    t2_id = t2_resp.json()["id"]

    # 3. Request Bulk DOCX + PDF Export ("both")
    export_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/exports",
        json={
            "transcript_ids": [t1_id, t2_id],
            "format": "both",
            "template": "meeting_minutes",
            "organization_name": "Enterprise Workspace",
            "include_metadata": True,
        },
        headers=headers,
    )
    assert export_resp.status_code == 201
    job_data = export_resp.json()
    assert job_data["status"] == "completed"
    assert job_data["total_items"] == 2
    assert job_data["completed_items"] == 2
    # 2 transcripts x 2 formats = 4 documents
    assert len(job_data["items"]) == 4
    export_id = job_data["id"]

    # 4. Check status endpoint
    status_resp = await client.get(f"/api/v1/exports/{export_id}", headers=headers)
    assert status_resp.status_code == 200
    assert status_resp.json()["status"] == "completed"

    # 5. Download ZIP archive
    download_resp = await client.get(f"/api/v1/exports/{export_id}/download", headers=headers)
    assert download_resp.status_code == 200
    assert download_resp.headers["content-type"] == "application/zip"
    zip_bytes = download_resp.content

    # Inspect downloaded archive
    with zipfile.ZipFile(io.BytesIO(zip_bytes), "r") as z:
        assert z.testzip() is None
        file_list = z.namelist()
        assert "manifest.json" in file_list
        assert any(f.endswith(".docx") for f in file_list)
        assert any(f.endswith(".pdf") for f in file_list)
