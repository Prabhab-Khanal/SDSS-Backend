<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\FolderController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\BrowseController;
use App\Http\Controllers\AccessRequestController;
use App\Http\Controllers\FileAccessController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\StorageController;
use Illuminate\Support\Facades\Route;

// Public routes — no auth required
Route::post('/register',    [AuthController::class, 'register']);
Route::post('/login',       [AuthController::class, 'login']);
Route::post('/check-email', [AuthController::class, 'checkEmail']);

// Authenticated routes
Route::middleware(['auth:api', 'account.approved'])->group(function () {
    Route::post('/logout',       [AuthController::class, 'logout']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::get('/me',            [AuthController::class, 'me']);

    // Admin routes
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/users',                  [AdminUserController::class, 'index']);
        Route::get('/users/{user}',           [AdminUserController::class, 'show']);
        Route::patch('/users/{user}/approve', [AdminUserController::class, 'approve']);
        Route::patch('/users/{user}/reject',  [AdminUserController::class, 'reject']);
        Route::patch('/users/{user}/suspend', [AdminUserController::class, 'suspend']);

        Route::get('/audit-logs',             [AuditLogController::class, 'index']);
        Route::get('/audit-logs/{auditLog}',  [AuditLogController::class, 'show']);
    });

    // Full storage tree
    Route::get('/my-storage', [StorageController::class, 'index']);

    // Folder management
    Route::get('/folders',              [FolderController::class, 'index']);
    Route::get('/folders/{folder}',     [FolderController::class, 'show']);
    Route::post('/folders',             [FolderController::class, 'store']);
    Route::patch('/folders/{folder}',      [FolderController::class, 'update']);
    Route::patch('/folders/{folder}/move', [FolderController::class, 'move']);
    Route::delete('/folders/{folder}',     [FolderController::class, 'destroy']);

    // File management
    Route::get('/files',                  [FileController::class, 'index']);
    Route::post('/files',                 [FileController::class, 'store']);
    Route::patch('/files/{file}',         [FileController::class, 'update']);
    Route::patch('/files/{file}/move',    [FileController::class, 'move']);
    Route::delete('/files/{file}',        [FileController::class, 'destroy']);
    Route::get('/files/{file}/download',  [FileController::class, 'download']);

    // Browse other users' storage (metadata only)
    Route::prefix('browse')->group(function () {
        Route::get('/users',                              [BrowseController::class, 'users']);
        Route::get('/users/{user}/folders',               [BrowseController::class, 'folders']);
        Route::get('/users/{user}/folders/{folder}',      [BrowseController::class, 'folderContents']);
        Route::get('/users/{user}/files',                 [BrowseController::class, 'files']);
    });

    // Access requests
    Route::post('/files/{file}/access-requests',            [AccessRequestController::class, 'store']);
    Route::get('/access-requests/outgoing',                 [AccessRequestController::class, 'outgoing']);
    Route::get('/access-requests/incoming',                 [AccessRequestController::class, 'incoming']);
    Route::patch('/access-requests/{accessRequest}/approve', [AccessRequestController::class, 'approve']);
    Route::patch('/access-requests/{accessRequest}/reject',  [AccessRequestController::class, 'reject']);

    // File access via authorization token
    Route::get('/files/access/{token}', [FileAccessController::class, 'download']);

    // Notifications
    Route::get('/notifications',                      [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count',          [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',             [NotificationController::class, 'markAllAsRead']);
});
