import io
import json
import zipfile
from datetime import datetime, timezone
from typing import List, Tuple, Dict, Any

def create_export_zip(
    documents: List[Tuple[str, bytes]],
    manifest_data: Dict[str, Any],
) -> bytes:
    """
    Package generated DOCX files and manifest.json into a ZIP archive.
    Runs integrity checks (testzip) before returning bytes.
    """
    buffer = io.BytesIO()

    with zipfile.ZipFile(buffer, "w", zipfile.ZIP_DEFLATED) as z:
        # 1. Add each document
        for filename, data in documents:
            # Clean filename to avoid path traversal
            safe_name = filename.replace("\\", "_").replace("/", "_").strip()
            z.writestr(safe_name, data)

        # 2. Add manifest.json
        manifest_json = json.dumps(manifest_data, indent=2)
        z.writestr("manifest.json", manifest_json)

    # 3. Verify integrity
    buffer.seek(0)
    with zipfile.ZipFile(buffer, "r") as test_z:
        bad_file = test_z.testzip()
        if bad_file:
            raise ValueError(f"ZIP archive corrupted at entry '{bad_file}'.")

    return buffer.getvalue()
