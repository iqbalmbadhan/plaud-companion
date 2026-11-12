import io
import zipfile
import pytest
from httpx import AsyncClient
from app.services.extractors import extract_document, ExtractionError

def create_mock_docx(text_paragraphs: list[str]) -> bytes:
    buffer = io.BytesIO()
    with zipfile.ZipFile(buffer, "w") as z:
        paragraphs_xml = "".join(
            f"<w:p><w:r><w:t>{p}</w:t></w:r></w:p>" for p in text_paragraphs
        )
        doc_xml = f"""<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:body>{paragraphs_xml}</w:body>
        </w:document>"""
        z.writestr("word/document.xml", doc_xml)
    return buffer.getvalue()

def test_txt_extractor_valid_and_empty():
    txt_content = (
        "[00:01:15] Jordan: Welcome everyone to the product review.\n"
        "[00:01:45] Sarah: Thanks Jordan. I have the telemetry numbers ready."
    ).encode("utf-8")

    doc = extract_document(txt_content, "product_review.txt")
    assert doc.title == "product review"
    assert len(doc.segments) == 2
    assert doc.segments[0].speaker_label == "Jordan"
    assert doc.segments[0].start_ms == 75000  # 1 min 15 sec
    assert doc.segments[1].speaker_label == "Sarah"

    # Empty file
    with pytest.raises(ExtractionError) as exc:
        extract_document(b"", "empty.txt")
    assert exc.value.code == "EMPTY_FILE"

def test_srt_extractor_valid():
    srt_content = (
        "1\n"
        "00:00:01,000 --> 00:00:04,500\n"
        "Speaker 1: Testing our transcript synchronization.\n\n"
        "2\n"
        "00:00:05,000 --> 00:00:08,200\n"
        "Speaker 2: Confirmed, timestamps are accurate."
    ).encode("utf-8")

    doc = extract_document(srt_content, "sync_test.srt")
    assert doc.title == "sync test"
    assert len(doc.segments) == 2
    assert doc.segments[0].speaker_label == "Speaker 1"
    assert doc.segments[0].start_ms == 1000
    assert doc.segments[0].end_ms == 4500
    assert doc.segments[1].speaker_label == "Speaker 2"

def test_docx_extractor_valid():
    docx_bytes = create_mock_docx([
        "Speaker 1: Strategic roadmap for Q4.",
        "Speaker 2: All milestones are on track.",
    ])
    doc = extract_document(docx_bytes, "quarterly_roadmap.docx")
    assert doc.title == "quarterly roadmap"
    assert len(doc.segments) == 2
    assert doc.segments[0].speaker_label == "Speaker 1"

def test_unsupported_extension_rejected():
    with pytest.raises(ExtractionError) as exc:
        extract_document(b"sample data", "unsupported.csv")
    assert exc.value.code == "UNSUPPORTED_FORMAT"

@pytest.mark.asyncio
async def test_batch_file_import_api(client: AsyncClient):
    # 1. Register User
    reg = await client.post(
        "/api/v1/auth/register",
        json={
            "email": "importer@example.com",
            "password": "Password123!",
            "display_name": "Importer User",
        },
    )
    assert reg.status_code == 201
    token = reg.json()["access_token"]
    headers = {"Authorization": f"Bearer {token}"}

    # Fetch personal workspace
    ws_list = await client.get("/api/v1/workspaces", headers=headers)
    ws_id = ws_list.json()[0]["id"]

    # 2. Create Project
    proj_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/projects",
        json={"name": "Executive Syncs", "description": "High level discussions"},
        headers=headers,
    )
    assert proj_resp.status_code == 201
    proj_id = proj_resp.json()["id"]

    # 3. Upload batch files via multipart/form-data
    txt_file = (
        "transcript_one.txt",
        b"[00:00:10] Alice: Launch date confirmed for next Monday.\n[00:00:25] Bob: Marketing is prepared.",
        "text/plain",
    )
    srt_file = (
        "transcript_two.srt",
        b"1\n00:00:00,500 --> 00:00:02,000\nCarol: Deployment verified.\n",
        "application/x-subrip",
    )

    import_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/imports",
        headers=headers,
        data={"project_id": proj_id},
        files=[
            ("files", txt_file),
            ("files", srt_file),
        ],
    )
    assert import_resp.status_code == 200
    batch_data = import_resp.json()
    assert batch_data["total_files"] == 2
    assert batch_data["successful_count"] == 2
    assert batch_data["duplicate_count"] == 0

    # 4. Uploading the same txt file again should be flagged as duplicate
    dup_resp = await client.post(
        f"/api/v1/workspaces/{ws_id}/imports",
        headers=headers,
        files=[("files", txt_file)],
    )
    assert dup_resp.status_code == 200
    dup_data = dup_resp.json()
    assert dup_data["successful_count"] == 0
    assert dup_data["duplicate_count"] == 1
    assert dup_data["results"][0]["status"] == "duplicate"

    # 5. Verify transcripts are visible in library with search
    list_resp = await client.get(
        f"/api/v1/workspaces/{ws_id}/transcripts?q=launch",
        headers=headers,
    )
    assert list_resp.status_code == 200
    matches = list_resp.json()
    assert len(matches) == 1
    assert matches[0]["title"] == "transcript one"

    # 6. Verify project transcript count
    proj_get = await client.get(f"/api/v1/projects/{proj_id}", headers=headers)
    assert proj_get.status_code == 200
    assert proj_get.json()["transcript_count"] == 2
