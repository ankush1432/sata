<?php

use App\Enums\AdminPermission;
use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBatchController;
use App\Http\Controllers\Admin\AdminCodeController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDeviceController;
use App\Http\Controllers\Admin\AdminLocationController;
use App\Http\Controllers\Admin\AdminManagementController;
use App\Http\Controllers\Admin\AdminNotificationController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminSecurityCommandController;
use App\Http\Controllers\Admin\AdminSecurityEventController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\Admin\AdminTrustedUserController;
use App\Http\Controllers\Admin\AdminUserController;
use Illuminate\Support\Facades\Route;

// Public Admin Auth Routes
Route::post('/login', [AdminAuthController::class, 'login']);

// Protected Admin Routes (Active Admin & Sanctum Authenticated)
Route::middleware(['auth:sanctum,admin', 'active.admin'])->group(function () {
    // Current Admin Identity & Lifecycle
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::get('/profile', [AdminAuthController::class, 'profile']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::post('/password/change', [AdminAuthController::class, 'changePassword']);

    // Dashboard (Phase 15: Production-ready Executive Dashboard)
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
    Route::get('/dashboard/charts', [AdminDashboardController::class, 'charts']);
    Route::get('/dashboard/recent-activity', [AdminDashboardController::class, 'recentActivity']);

    // Users Management & Location Tracking
    Route::middleware('permission:' . AdminPermission::USERS_VIEW->value)->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::get('/users/{id}', [AdminUserController::class, 'show']);
        Route::get('/users/{id}/security-events', [AdminUserController::class, 'securityEvents']);
        Route::get('/users/{id}/notifications', [AdminUserController::class, 'notifications']);
        Route::get('/users/{id}/location', [AdminLocationController::class, 'getUserLocation']);
        Route::post('/users/{id}/location/session', [AdminLocationController::class, 'startUserSession']);
        Route::post('/location/sessions/{id}/stop', [AdminLocationController::class, 'stopSession']);
    });
    Route::post('/users', [AdminUserController::class, 'store'])
        ->middleware('permission:' . AdminPermission::USERS_MANAGE->value);
    Route::put('/users/{id}', [AdminUserController::class, 'update'])
        ->middleware('permission:' . AdminPermission::USERS_MANAGE->value);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy'])
        ->middleware('permission:' . AdminPermission::USERS_MANAGE->value);
    Route::put('/users/{id}/status', [AdminUserController::class, 'updateStatus']);
    Route::post('/users/{id}/disable', [AdminUserController::class, 'disable'])
        ->middleware('permission:' . AdminPermission::USERS_DISABLE->value);

    // Activation Plans
    Route::middleware('permission:' . AdminPermission::ACTIVATION_PLANS_VIEW->value)->group(function () {
        Route::get('/plans', [AdminPlanController::class, 'index']);
        Route::get('/plans/{id}', [AdminPlanController::class, 'show']);
    });
    Route::middleware('permission:' . AdminPermission::ACTIVATION_PLANS_MANAGE->value)->group(function () {
        Route::post('/plans', [AdminPlanController::class, 'store']);
        Route::put('/plans/{id}', [AdminPlanController::class, 'update']);
        Route::patch('/plans/{id}', [AdminPlanController::class, 'update']);
        Route::post('/plans/{id}/enable', [AdminPlanController::class, 'enable']);
        Route::put('/plans/{id}/enable', [AdminPlanController::class, 'enable']);
        Route::post('/plans/{id}/disable', [AdminPlanController::class, 'disable']);
        Route::put('/plans/{id}/disable', [AdminPlanController::class, 'disable']);
        Route::delete('/plans/{id}', [AdminPlanController::class, 'destroy']);
    });

    // Activation Codes & Batches
    Route::middleware('permission:' . AdminPermission::ACTIVATION_CODES_VIEW->value)->group(function () {
        Route::get('/batches', [AdminBatchController::class, 'index']);
        Route::get('/batches/{id}', [AdminBatchController::class, 'show']);
        Route::get('/codes', [AdminCodeController::class, 'index']);
        Route::get('/codes/export', [AdminCodeController::class, 'export']);
        Route::get('/codes/print', [AdminCodeController::class, 'printCards']);
        Route::get('/codes/{id}/print', [AdminCodeController::class, 'printCode']);
        Route::get('/codes/{id}', [AdminCodeController::class, 'show']);
    });
    Route::middleware('permission:' . AdminPermission::ACTIVATION_CODES_GENERATE->value)->group(function () {
        Route::post('/batches', [AdminBatchController::class, 'store']);
        Route::post('/codes/generate-one', [AdminCodeController::class, 'generateOne']);
        Route::post('/codes/generate', [AdminCodeController::class, 'generateOne']);
        Route::post('/codes/bulk-generate', [AdminCodeController::class, 'bulkGenerate']);
        Route::post('/codes/bulk', [AdminCodeController::class, 'bulkGenerate']);
    });
    Route::middleware('permission:' . AdminPermission::ACTIVATION_CODES_DISABLE->value)->group(function () {
        Route::post('/codes/{id}/disable', [AdminCodeController::class, 'disable']);
        Route::put('/codes/{id}/disable', [AdminCodeController::class, 'disable']);
        Route::delete('/codes/{id}', [AdminCodeController::class, 'destroy']);
        Route::post('/batches/{id}/disable', [AdminCodeController::class, 'disableBatch']);
    });

    // Subscriptions
    Route::middleware('permission:' . AdminPermission::SUBSCRIPTIONS_VIEW->value)->group(function () {
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'index']);
        Route::get('/subscriptions/{id}', [AdminSubscriptionController::class, 'show']);
    });
    Route::middleware('permission:' . AdminPermission::SUBSCRIPTIONS_MANAGE->value)->group(function () {
        Route::post('/subscriptions/{id}/cancel', [AdminSubscriptionController::class, 'cancel']);
        Route::post('/subscriptions/{id}/extend', [AdminSubscriptionController::class, 'extend']);
        Route::delete('/subscriptions/{id}', [AdminSubscriptionController::class, 'destroy']);
    });

    // Devices
    Route::middleware('permission:' . AdminPermission::DEVICES_VIEW->value)->group(function () {
        Route::get('/devices', [AdminDeviceController::class, 'index']);
        Route::get('/devices/{id}', [AdminDeviceController::class, 'show']);
    });
    Route::match(['post', 'put'], '/devices/{id}/revoke', [AdminDeviceController::class, 'revoke'])
        ->middleware('permission:' . AdminPermission::DEVICES_REVOKE->value);

    // Trusted Users
    Route::middleware('permission:' . AdminPermission::TRUSTED_USERS_VIEW->value)->group(function () {
        Route::get('/trusted-users', [AdminTrustedUserController::class, 'index']);
        Route::get('/trusted-users/{id}', [AdminTrustedUserController::class, 'show']);
        Route::match(['post', 'put'], '/trusted-users/{id}/disable', [AdminTrustedUserController::class, 'disable']);
        Route::match(['post', 'put'], '/trusted-users/{id}/verify', [AdminTrustedUserController::class, 'verify']);
        Route::match(['post', 'put'], '/trusted-users/{id}/status', [AdminTrustedUserController::class, 'updateStatus']);
    });

    // Security Events & Evidence
    Route::middleware('permission:' . AdminPermission::SECURITY_EVENTS_VIEW->value)->group(function () {
        Route::get('/security-events', [AdminSecurityEventController::class, 'events']);
        Route::get('/security-events/{id}', [AdminSecurityEventController::class, 'showEvent']);
        Route::match(['post', 'put'], '/security-events/{id}/review', [AdminSecurityEventController::class, 'markReviewed']);
        Route::match(['post', 'put'], '/security-events/{id}/mark-reviewed', [AdminSecurityEventController::class, 'markReviewed']);
        Route::delete('/security-events/{id}', [AdminSecurityEventController::class, 'destroy']);
    });
    Route::middleware('permission:' . AdminPermission::SECURITY_EVIDENCE_VIEW->value)->group(function () {
        Route::get('/evidence', [AdminSecurityEventController::class, 'evidence']);
        Route::get('/evidence/{id}', [AdminSecurityEventController::class, 'showEvidence']);
        Route::get('/evidence/{id}/stream', [AdminSecurityEventController::class, 'streamEvidence']);
        Route::get('/evidence/{id}/download', [AdminSecurityEventController::class, 'streamEvidence']);
    });

    // Security Commands (Phase 12: Admin Security Commands)
    Route::middleware('permission:' . AdminPermission::SECURITY_COMMANDS_VIEW->value)->group(function () {
        Route::get('/security-commands', [AdminSecurityCommandController::class, 'index']);
        Route::get('/security-commands/{command}', [AdminSecurityCommandController::class, 'show']);
        Route::get('/users/{user}/devices/{device}/commands', [AdminSecurityCommandController::class, 'deviceCommands']);
    });
    Route::post('/users/{user}/devices/{device}/commands', [AdminSecurityCommandController::class, 'storeForDevice'])
        ->middleware('permission:' . AdminPermission::SECURITY_COMMANDS_EXECUTE->value);
    Route::post('/security-commands/{command}/transition', [AdminSecurityCommandController::class, 'transitionStatus'])
        ->middleware('permission:' . AdminPermission::SECURITY_COMMANDS_EXECUTE->value);
    Route::post('/security-commands', [AdminSecurityCommandController::class, 'execute'])
        ->middleware('permission:' . AdminPermission::SECURITY_COMMANDS_EXECUTE->value);

    // Notifications
    Route::middleware('permission:' . AdminPermission::NOTIFICATIONS_VIEW->value)->group(function () {
        Route::get('/notifications', [AdminNotificationController::class, 'index']);
        Route::get('/notifications/{id}', [AdminNotificationController::class, 'show']);
    });

    // Admins Management
    Route::middleware('permission:' . AdminPermission::ADMINS_VIEW->value)->group(function () {
        Route::get('/admins', [AdminManagementController::class, 'index']);
        Route::get('/admins/{id}', [AdminManagementController::class, 'show']);
    });
    Route::middleware('permission:' . AdminPermission::ADMINS_MANAGE->value)->group(function () {
        Route::post('/admins', [AdminManagementController::class, 'store']);
        Route::put('/admins/{id}', [AdminManagementController::class, 'update']);
        Route::post('/admins/{id}/toggle-status', [AdminManagementController::class, 'toggleStatus']);
    });

    // Audit Logs
    Route::middleware('permission:' . AdminPermission::AUDIT_LOGS_VIEW->value)->group(function () {
        Route::get('/audit-logs', [AdminAuditLogController::class, 'index']);
        Route::get('/audit-logs/{id}', [AdminAuditLogController::class, 'show']);
    });

    // Scheduled Automation & Jobs (Phase 16)
    Route::get('/scheduled-jobs', [\App\Http\Controllers\Admin\AdminScheduledJobController::class, 'index']);
    Route::post('/scheduled-jobs/run', [\App\Http\Controllers\Admin\AdminScheduledJobController::class, 'run']);
});
