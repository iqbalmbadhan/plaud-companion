<?php

use App\Http\Controllers\InstallController;
use App\Http\Controllers\UpdateController;
use Illuminate\Support\Facades\Route;

// 1-Click Installer routes (blocked with 404 once storage/app/installed.lock exists)
Route::prefix('install')->middleware('not_installed')->group(function (): void {
    Route::get('/', [InstallController::class, 'show'])->name('install.show');
    Route::get('/preflight', [InstallController::class, 'preflight'])->name('install.preflight');
    Route::post('/test-db', [InstallController::class, 'testDatabase'])->name('install.test-db');
    Route::post('/configure', [InstallController::class, 'configureEnvironment'])->name('install.configure');
    Route::post('/run', [InstallController::class, 'install'])->name('install.run');
});

// Super-admin-only Settings Update & Backup Panel
Route::middleware(['super_admin'])->group(function (): void {
    Route::get('/settings/updates', [UpdateController::class, 'show'])->name('settings.updates');
});
