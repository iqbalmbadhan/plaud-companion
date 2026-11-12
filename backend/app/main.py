import uuid
from fastapi import FastAPI, Request, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from fastapi.exceptions import RequestValidationError
from starlette.exceptions import HTTPException as StarletteHTTPException
from app.core.config import settings
from app.api.v1.auth import router as auth_router
from app.api.v1.workspaces import router as workspaces_router
from app.api.v1.transcripts import router as transcripts_router
from app.api.v1.imports import router as imports_router
from app.api.v1.projects import router as projects_router
from app.api.v1.ai import router as ai_router
from app.api.v1.health import router as health_router

app = FastAPI(
    title=settings.APP_NAME,
    version=settings.APP_VERSION,
    description="Cross-platform transcript management and AI productivity platform.",
    docs_url="/docs",
    redoc_url="/redoc",
)

# CORS Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.CORS_ORIGINS if settings.CORS_ORIGINS else ["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Consistent Error Handling per Docs/API.md convention
@app.exception_handler(StarletteHTTPException)
async def http_exception_handler(request: Request, exc: StarletteHTTPException):
    request_id = request.headers.get("x-request-id", str(uuid.uuid4()))
    return JSONResponse(
        status_code=exc.status_code,
        content={
            "error": {
                "code": f"HTTP_{exc.status_code}",
                "message": str(exc.detail),
                "request_id": request_id,
            }
        },
    )

@app.exception_handler(RequestValidationError)
async def validation_exception_handler(request: Request, exc: RequestValidationError):
    request_id = request.headers.get("x-request-id", str(uuid.uuid4()))
    errors = exc.errors()
    msg = errors[0].get("msg") if errors else "Validation error"
    return JSONResponse(
        status_code=status.HTTP_422_UNPROCESSABLE_ENTITY,
        content={
            "error": {
                "code": "VALIDATION_ERROR",
                "message": msg,
                "request_id": request_id,
                "details": errors,
            }
        },
    )

# Include Routers under /api/v1
api_prefix = settings.API_V1_PREFIX
app.include_router(auth_router, prefix=api_prefix)
app.include_router(workspaces_router, prefix=api_prefix)
app.include_router(transcripts_router, prefix=api_prefix)
app.include_router(imports_router, prefix=api_prefix)
app.include_router(projects_router, prefix=api_prefix)
app.include_router(ai_router, prefix=api_prefix)
app.include_router(health_router, prefix=api_prefix)

@app.get("/")
async def root():
    return {
        "app": settings.APP_NAME,
        "version": settings.APP_VERSION,
        "status": "running",
        "docs": "/docs",
    }
