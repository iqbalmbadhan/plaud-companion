<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TranscriptController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Health probes
    Route::get('/health/live', [HealthController::class, 'live']);
    Route::get('/health/ready', [HealthController::class, 'ready']);

    // Public authentication
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Authenticated API routes
    Route::middleware('auth.token')->group(function (): void {
        Route::get('/auth/me', [AuthController::class, 'me']);

        // Workspaces & RBAC
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::post('/workspaces', [WorkspaceController::class, 'store']);
        Route::get('/workspaces/{workspaceId}', [WorkspaceController::class, 'show']);
        Route::get('/workspaces/{workspaceId}/members', [WorkspaceController::class, 'members']);
        Route::post('/workspaces/{workspaceId}/invitations', [WorkspaceController::class, 'invite']);

        // Projects
        Route::get('/workspaces/{workspaceId}/projects', [ProjectController::class, 'index']);
        Route::post('/workspaces/{workspaceId}/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{projectId}', [ProjectController::class, 'show']);

        // Transcripts & Imports
        Route::get('/workspaces/{workspaceId}/transcripts', [TranscriptController::class, 'index']);
        Route::post('/workspaces/{workspaceId}/transcripts', [TranscriptController::class, 'store']);
        Route::get('/transcripts/{transcriptId}', [TranscriptController::class, 'show']);
        Route::delete('/transcripts/{transcriptId}', [TranscriptController::class, 'destroy']);
        Route::post('/workspaces/{workspaceId}/imports', [ImportController::class, 'store']);

        // AI Connectors & Productivity
        Route::get('/workspaces/{workspaceId}/ai/connectors', [AiController::class, 'connectors']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/connect', [AiController::class, 'connect']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/test', [AiController::class, 'test']);
        Route::post('/transcripts/{transcriptId}/summary', [AiController::class, 'summary']);
        Route::post('/transcripts/{transcriptId}/action-items', [AiController::class, 'actionItems']);
        Route::post('/workspaces/{workspaceId}/ai/ask', [AiController::class, 'ask']);

        // Exports
        Route::get('/workspaces/{workspaceId}/exports', [ExportController::class, 'index']);
        Route::post('/workspaces/{workspaceId}/exports', [ExportController::class, 'store']);
        Route::post('/workspaces/{workspaceId}/exports/demo', [ExportController::class, 'demoPackage']);
        Route::get('/exports/{exportId}', [ExportController::class, 'show']);
        Route::get('/exports/{exportId}/download', [ExportController::class, 'download']);

        // PLAUD Cloud Open API & BLE Hardware Bridge
        Route::get('/workspaces/{workspaceId}/integrations/plaud/status', [\App\Http\Controllers\Api\V1\PlaudSyncController::class, 'status']);
        Route::post('/workspaces/{workspaceId}/integrations/plaud/connect', [\App\Http\Controllers\Api\V1\PlaudSyncController::class, 'connect']);
        Route::post('/workspaces/{workspaceId}/integrations/plaud/pair-ble', [\App\Http\Controllers\Api\V1\PlaudSyncController::class, 'pairBle']);
        Route::post('/workspaces/{workspaceId}/integrations/plaud/sync', [\App\Http\Controllers\Api\V1\PlaudSyncController::class, 'sync']);

        // Signed Updater & Backup API (super_admin only)
        Route::prefix('updates')->middleware('super_admin')->group(function (): void {
            Route::get('/status', [UpdateController::class, 'status']);
            Route::post('/upload', [UpdateController::class, 'upload']);
            Route::post('/apply', [UpdateController::class, 'apply']);
            Route::post('/rollback', [UpdateController::class, 'rollback']);
            Route::post('/backup', [UpdateController::class, 'backup']);
            Route::delete('/upload', [UpdateController::class, 'deleteUpload']);
        });
    });
});
