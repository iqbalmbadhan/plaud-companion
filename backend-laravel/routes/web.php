<?php

use App\Http\Controllers\Api\V1\AiController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ImportController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TranscriptController;
use App\Http\Controllers\Api\V1\WorkspaceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UpdateController;
use App\Http\Controllers\WebAuthController;
use Illuminate\Support\Facades\Route;

// Main PLAUD Companion Web Application (redirects to /install if not installed, or /login if unauthenticated)
Route::middleware(['web.auth'])->group(function (): void {
    // Connect my Plaud account (OAuth + import)
    Route::get('/integrations/plaud/connect', [\App\Http\Controllers\PlaudAccountController::class, 'connect'])->name('plaud.account.connect');
    Route::get('/integrations/plaud/callback', [\App\Http\Controllers\PlaudAccountController::class, 'callback'])->name('plaud.account.callback');
    Route::get('/integrations/plaud/status', [\App\Http\Controllers\PlaudAccountController::class, 'status'])->name('plaud.account.status');
    Route::post('/integrations/plaud/sync', [\App\Http\Controllers\PlaudAccountController::class, 'sync'])->middleware('throttle:12,1')->name('plaud.account.sync');
    Route::post('/integrations/plaud/disconnect', [\App\Http\Controllers\PlaudAccountController::class, 'disconnect'])->name('plaud.account.disconnect');
    Route::get('/', [DashboardController::class, 'index'])->name('app.home');
    Route::get('/app', [DashboardController::class, 'index'])->name('app.dashboard');
    // OpenRouter "Easy Connect" OAuth callback
    Route::get('/settings/ai/callback', [AiSettingsController::class, 'oauthCallback'])->name('settings.ai.callback');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'updateProfile'])->name('profile.update');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Session-authenticated JSON endpoints for the Web Workspace UI
    Route::prefix('app/api')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'apiShow'])->name('app.api.profile');
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
        Route::get('/transcripts/{transcriptId}/audio', [TranscriptController::class, 'audio'])->name('app.api.transcripts.audio');
        Route::get('/transcripts/{transcriptId}/audio/{fileId}', [TranscriptController::class, 'streamAudio'])->name('app.api.transcripts.audio.stream');
        Route::delete('/transcripts/{transcriptId}', [TranscriptController::class, 'destroy']);
        Route::post('/workspaces/{workspaceId}/imports', [ImportController::class, 'store']);

        Route::get('/workspaces/{workspaceId}/ai/connectors', [AiController::class, 'connectors']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/connect', [AiController::class, 'connect']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/test', [AiController::class, 'test']);
        Route::post('/workspaces/{workspaceId}/ai/connectors/{providerId}/fetch-models', [AiController::class, 'fetchModels']);
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
Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
Route::post('/register', [WebAuthController::class, 'register'])->middleware('throttle:10,1')->name('register.post');
Route::get('/forgot-password', [WebAuthController::class, 'showForgotPassword'])->name('password.request');
Route::post('/forgot-password', [WebAuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
Route::get('/reset-password/{token}', [WebAuthController::class, 'showResetPassword'])->name('password.reset');
Route::post('/reset-password', [WebAuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('password.update');
Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
Route::match(['GET', 'POST'], '/auth/logout', [WebAuthController::class, 'logout'])->name('auth.logout');
Route::get('/demo', static function () {
    // Disabled by default: the page exposes demo credentials (see config/app.php).
    abort_unless((bool) config('app.demo_login_enabled'), 404);

    return view('auth.demo');
})->name('demo');

// 1-Click Installer routes (blocked with 404 once storage/app/installed.lock exists)
Route::prefix('install')->middleware('not_installed')->group(function (): void {
    Route::get('/', [InstallController::class, 'show'])->name('install.show');
    Route::get('/preflight', [InstallController::class, 'preflight'])->name('install.preflight');
    Route::post('/test-db', [InstallController::class, 'testDatabase'])->name('install.test-db');
    Route::post('/configure', [InstallController::class, 'configureEnvironment'])->name('install.configure');
    Route::post('/run', [InstallController::class, 'install'])->name('install.run');
});

// Super-admin-only User & Access Management Panel
Route::middleware(['web.auth', 'super_admin'])->prefix('settings/users')->group(function (): void {
    Route::get('/', [\App\Http\Controllers\UserManagementController::class, 'index'])->name('settings.users');
    Route::get('/api/{userId}/profile', [\App\Http\Controllers\UserManagementController::class, 'apiProfile'])->name('settings.users.profile');
    Route::prefix('api')->group(function (): void {
        Route::get('/list', [\App\Http\Controllers\UserManagementController::class, 'apiList']);
        Route::post('/{userId}/toggle-status', [\App\Http\Controllers\UserManagementController::class, 'toggleStatus']);
        Route::post('/{userId}/toggle-admin', [\App\Http\Controllers\UserManagementController::class, 'toggleAdmin']);
        Route::post('/{userId}/sign-out-everywhere', [\App\Http\Controllers\UserManagementController::class, 'signOutEverywhere']);
        Route::post('/{userId}/password-reset', [\App\Http\Controllers\UserManagementController::class, 'sendPasswordReset'])->middleware('throttle:10,1');
    });
});

// Developer console page + API
Route::middleware(['web.auth', 'developer'])->group(function (): void {
    Route::get('/settings/developer', [\App\Http\Controllers\DeveloperController::class, 'index'])->name('settings.developer');
});
Route::middleware(['web.auth', 'developer'])->prefix('settings/developer/api')->group(function (): void {
    Route::post('/users/{userId}/impersonate', [\App\Http\Controllers\DeveloperController::class, 'impersonate'])->middleware('throttle:10,1');
    Route::post('/users/{userId}/toggle-developer', [\App\Http\Controllers\DeveloperController::class, 'toggleDeveloper']);
    Route::delete('/users/{userId}', [\App\Http\Controllers\DeveloperController::class, 'deleteUser']);
    Route::get('/workspaces', [\App\Http\Controllers\DeveloperController::class, 'workspaces']);
    Route::get('/errors', [\App\Http\Controllers\DeveloperController::class, 'errors']);
    Route::delete('/errors', [\App\Http\Controllers\DeveloperController::class, 'clearErrors']);
    Route::post('/plaud-samples', [\App\Http\Controllers\DeveloperController::class, 'loadPlaudSamples'])->middleware('throttle:6,1');
});

// Ending impersonation is done from the impersonated session itself.
Route::post('/impersonation/stop', [\App\Http\Controllers\DeveloperController::class, 'stopImpersonation'])->middleware('web.auth')->name('impersonation.stop');

// Browser-side errors from any signed-in page, for the developer debug log.
Route::post('/app/api/client-errors', [\App\Http\Controllers\DeveloperController::class, 'reportClientError'])->middleware(['web.auth', 'throttle:30,1'])->name('client-errors.store');

// Super-admin-only Settings Update & Backup Panel
Route::middleware(['web.auth', 'super_admin'])->prefix('settings/updates')->group(function (): void {
    Route::get('/', [UpdateController::class, 'show'])->name('settings.updates');
    Route::prefix('api')->group(function (): void {
        Route::get('/status', [UpdateController::class, 'status']);
        Route::post('/upload', [UpdateController::class, 'upload']);
        Route::post('/apply', [UpdateController::class, 'apply']);
        Route::post('/rollback', [UpdateController::class, 'rollback']);
        Route::post('/backup', [UpdateController::class, 'backup']);
        Route::delete('/upload', [UpdateController::class, 'deleteUpload']);

        // Encrypted client-data backups (passphrase-protected; portable across installs).
        Route::get('/data-backups', [\App\Http\Controllers\DataBackupController::class, 'index']);
        Route::post('/data-backups', [\App\Http\Controllers\DataBackupController::class, 'create'])->middleware('throttle:6,1');
        Route::get('/data-backups/{filename}/download', [\App\Http\Controllers\DataBackupController::class, 'download']);
        Route::post('/data-backups/restore', [\App\Http\Controllers\DataBackupController::class, 'restore'])->middleware('throttle:6,1');
        Route::delete('/data-backups/{filename}', [\App\Http\Controllers\DataBackupController::class, 'destroy']);
    });
});
