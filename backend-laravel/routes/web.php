<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TranscriptController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

// Main PLAUD Companion Web Application (redirects to /install if not installed, or /login if unauthenticated)
Route::middleware(['web.auth'])->group(function (): void {
    Route::get('/', [DashboardController::class, 'index'])->name('app.home');
    Route::get('/app', [DashboardController::class, 'index'])->name('app.dashboard');

    // Session-authenticated JSON endpoints for the Web Workspace UI
    Route::prefix('app/api')->group(function (): void {
        Route::get('/workspaces', [WorkspaceController::class, 'index']);
        Route::post('/workspaces', [WorkspaceController::class, 'store']);
        Route::get('/workspaces/{workspaceId}', [WorkspaceController::class, 'show']);
        Route::get('/workspaces/{workspaceId}/members', [WorkspaceController::class, 'members']);
        Route::post('/workspaces/{workspaceId}/invitations', [WorkspaceController::class, 'invite']);
        Route::post('/workspaces/{workspaceId}/seed-sample', [DashboardController::class, 'seedSample']);

        Route::get('/workspaces/{workspaceId}/projects', [ProjectController::class, 'index']);
        Route::post('/workspaces/{workspaceId}/projects', [ProjectController::class, 'store']);
        Route::get('/projects/{projectId}', [ProjectController::class, 'show']);

        Route::get('/workspaces/{workspaceId}/transcripts', [TranscriptController::class, 'index']);
        Route::post('/workspaces/{workspaceId}/transcripts', [TranscriptController::class, 'store']);
        Route::get('/transcripts/{transcriptId}', [TranscriptController::class, 'show']);
        Route::delete('/transcripts/{transcriptId}', [TranscriptController::class, 'destroy']);
        Route::post('/workspaces/{workspaceId}/imports', [ImportController::class, 'store']);

        Route::get('/workspaces/{workspaceId}/ai/connectors', [AiController::class, 'connectors']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/connect', [AiController::class, 'connect']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/test', [AiController::class, 'test']);
        Route::post('/transcripts/{transcriptId}/summary', [AiController::class, 'summary']);
        Route::post('/transcripts/{transcriptId}/action-items', [AiController::class, 'actionItems']);
        Route::post('/workspaces/{workspaceId}/ai/ask', [AiController::class, 'ask']);

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
    });
});

// Web authentication
Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
Route::match(['GET', 'POST'], '/auth/logout', [WebAuthController::class, 'logout'])->name('auth.logout');
Route::get('/demo', static fn () => view('auth.demo'))->name('demo');

// 1-Click Installer routes (blocked with 404 once storage/app/installed.lock exists)
Route::prefix('install')->middleware('not_installed')->group(function (): void {
    Route::get('/', [InstallController::class, 'show'])->name('install.show');
    Route::get('/preflight', [InstallController::class, 'preflight'])->name('install.preflight');
    Route::post('/test-db', [InstallController::class, 'testDatabase'])->name('install.test-db');
    Route::post('/configure', [InstallController::class, 'configureEnvironment'])->name('install.configure');
    Route::post('/run', [InstallController::class, 'install'])->name('install.run');
});

// Super-admin-only Settings Update & Backup Panel
Route::middleware(['super_admin'])->prefix('settings/updates')->group(function (): void {
    Route::get('/', [UpdateController::class, 'show'])->name('settings.updates');
    Route::prefix('api')->group(function (): void {
        Route::get('/status', [UpdateController::class, 'status']);
        Route::post('/upload', [UpdateController::class, 'upload']);
        Route::post('/apply', [UpdateController::class, 'apply']);
        Route::post('/rollback', [UpdateController::class, 'rollback']);
        Route::post('/backup', [UpdateController::class, 'backup']);
        Route::delete('/upload', [UpdateController::class, 'deleteUpload']);
    });
});
