import os
import io
from abc import ABC, abstractmethod
from typing import Optional
from app.core.config import settings

class IObjectStorage(ABC):
    @abstractmethod
    async def put(self, storage_key: str, data: bytes, content_type: str = "application/octet-stream") -> str:
        pass

    @abstractmethod
    async def get(self, storage_key: str) -> bytes:
        pass

    @abstractmethod
    async def delete(self, storage_key: str) -> bool:
        pass

    @abstractmethod
    async def create_signed_download_url(self, storage_key: str, expires_seconds: int = 3600) -> str:
        pass

class LocalObjectStorage(IObjectStorage):
    def __init__(self, base_dir: Optional[str] = None):
        self.base_dir = base_dir or os.path.join(os.getcwd(), ".storage_private")
        os.makedirs(self.base_dir, exist_ok=True)

    def _get_path(self, storage_key: str) -> str:
        # Sanitize against path traversal attacks
        clean_key = os.path.normpath(storage_key).lstrip("/\\")
        if ".." in clean_key:
            raise ValueError("Invalid storage key (path traversal attempt).")
        full_path = os.path.join(self.base_dir, clean_key)
        os.makedirs(os.path.dirname(full_path), exist_ok=True)
        return full_path

    async def put(self, storage_key: str, data: bytes, content_type: str = "application/octet-stream") -> str:
        path = self._get_path(storage_key)
        with open(path, "wb") as f:
            f.write(data)
        return storage_key

    async def get(self, storage_key: str) -> bytes:
        path = self._get_path(storage_key)
        if not os.path.exists(path):
            raise FileNotFoundError(f"Storage key {storage_key} not found.")
        with open(path, "rb") as f:
            return f.read()

    async def delete(self, storage_key: str) -> bool:
        path = self._get_path(storage_key)
        if os.path.exists(path):
            os.remove(path)
            return True
        return False

    async def create_signed_download_url(self, storage_key: str, expires_seconds: int = 3600) -> str:
        # For local development / testing, generate safe relative proxy URL
        return f"/api/v1/storage/download/{storage_key}?expires={expires_seconds}"

_default_storage: Optional[IObjectStorage] = None

def get_storage() -> IObjectStorage:
    global _default_storage
    if _default_storage is None:
        _default_storage = LocalObjectStorage()
    return _default_storage
