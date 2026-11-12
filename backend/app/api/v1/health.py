from fastapi import APIRouter, status
from pydantic import BaseModel

router = APIRouter(prefix="/health", tags=["Operations"])

class HealthResponse(BaseModel):
    status: str
    version: str

@router.get("/live", response_model=HealthResponse, status_code=status.HTTP_200_OK)
async def liveness():
    """Liveness probe for load balancers and orchestrators."""
    return HealthResponse(status="alive", version="0.1.0")

@router.get("/ready", response_model=HealthResponse, status_code=status.HTTP_200_OK)
async def readiness():
    """Readiness probe indicating application is ready to receive traffic."""
    return HealthResponse(status="ready", version="0.1.0")
