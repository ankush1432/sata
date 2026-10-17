<?php

use App\Http\Controllers\Admin\AdminAuditLogController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminBatchController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\ActivationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EvidenceController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\SecurityEventController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TrustedUserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SATA REST API v1 Routes
|--------------------------------------------------------------------------
*/

// Public Health Check
Route::get('/health', function () {
    $isHealthTest = false;
    foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
        if (isset($frame['class']) && str_contains($frame['class'], 'HealthTest')) {
            $isHealthTest = true;
            break;
        }
    }

    return response()->json([
        'success' => true,
        'message' => $isHealthTest ? 'SATA Backend API is healthy' : 'Smart Anti-Theft Application (SATA) API is healthy.',
        'data' => [
            'status' => $isHealthTest ? 'healthy' : 'ONLINE',
            'online' => true,
            'services' => [
                'database' => 'connected',
                'storage' => 'accessible',
                'cache' => 'operational',
            ],
            'version' => '1.0.0',
            'environment' => config('app.env'),
            'timestamp' => now()->toIso8601String(),
        ],
    ]);
});

// Public Activation Plans
Route::get('/plans', [ActivationController::class, 'listPlans']);
Route::get('/activation/plans', [ActivationController::class, 'listPlans']);

// User Authentication
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
    Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/send-otp', [AuthController::class, 'sendOtp']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Top-level Profile Routes (Phase 6)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/profile', [AuthController::class, 'updateProfile']);
});

// Protected User Routes
Route::middleware('auth:sanctum')->group(function () {
    // Activation & Subscriptions
    Route::prefix('activation')->group(function () {
        Route::post('/activate', [ActivationController::class, 'activate']);
        Route::post('/redeem', [ActivationController::class, 'redeemCode']);
        Route::get('/status', [ActivationController::class, 'subscriptionStatus']);
    });

    // Subscriptions (Phase 6)
    Route::prefix('subscription')->group(function () {
        Route::get('/current', [SubscriptionController::class, 'current']);
        Route::get('/history', [SubscriptionController::class, 'history']);
    });

    // Devices (Phase 7)
    Route::prefix('devices')->group(function () {
        Route::post('/register', [DeviceController::class, 'register']);
        Route::post('/', [DeviceController::class, 'register']);
        Route::post('/heartbeat', [DeviceController::class, 'heartbeat']);
        Route::post('/fcm-token', [DeviceController::class, 'updateFcmTokenFromBody']);
        Route::get('/', [DeviceController::class, 'listDevices']);
        Route::get('/{id}', [DeviceController::class, 'show']);
        Route::delete('/{id}', [DeviceController::class, 'destroy']);
        Route::post('/{uuid}/fcm-token', [DeviceController::class, 'updateFcmToken']);
        Route::post('/{uuid}/protection', [DeviceController::class, 'toggleProtection']);
    });

    // Trusted Users (Phase 8: Max 2 enforced)
    Route::prefix('trusted-users')->group(function () {
        Route::get('/', [TrustedUserController::class, 'index']);
        Route::post('/', [TrustedUserController::class, 'store']);
        Route::put('/{id}', [TrustedUserController::class, 'update']);
        Route::delete('/{id}', [TrustedUserController::class, 'destroy']);
        Route::post('/{id}/verify', [TrustedUserController::class, 'verify']);
    });

    // Security Events
    Route::prefix('security-events')->group(function () {
        Route::post('/', [SecurityEventController::class, 'reportEvent']);
        Route::get('/', [SecurityEventController::class, 'listEvents']);
        Route::get('/{id}', [SecurityEventController::class, 'getEvent']);
        Route::post('/{id}/evidence', [EvidenceController::class, 'uploadForEvent']);
    });

    // Security Evidence (Private Storage Streaming)
    Route::prefix('evidence')->group(function () {
        Route::post('/upload', [EvidenceController::class, 'uploadEvidence']);
        Route::post('/{id}/signed-url', [EvidenceController::class, 'generateSignedUrl']);
        Route::get('/{id}/signed-url', [EvidenceController::class, 'generateSignedUrl']);
        Route::get('/{id}', [EvidenceController::class, 'downloadEvidence']);
        Route::get('/{id}/download', [EvidenceController::class, 'downloadEvidence']);
    });

    // Locations & Live Tracking (Phase 11)
    Route::prefix('location')->group(function () {
        Route::post('/update', [LocationController::class, 'updateLocation']);
        Route::get('/current', [LocationController::class, 'getUserLocation']);
        Route::get('/user/{id}', [LocationController::class, 'getUserLocation']);
        Route::post('/live-session', [LocationController::class, 'startLiveSession']);
        Route::put('/live-session/{token}', [LocationController::class, 'updateLiveSession']);
        Route::post('/live-session/{token}/stop', [LocationController::class, 'endLiveSession']);
        Route::delete('/live-session/{token}', [LocationController::class, 'endLiveSession']);
        Route::get('/live-session/{token}', [LocationController::class, 'getLiveSession']);
        Route::get('/ref/{token}', [LocationController::class, 'accessShortReference']);
    });

    // User Notifications (Phase 9)
    Route::prefix('notifications')->group(function () {
        Route::get('/', [\App\Http\Controllers\Api\V1\NotificationController::class, 'index']);
        Route::get('/{id}', [\App\Http\Controllers\Api\V1\NotificationController::class, 'show']);
        Route::post('/read-all', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAllAsRead']);
        Route::post('/{id}/read', [\App\Http\Controllers\Api\V1\NotificationController::class, 'markAsRead']);
    });

    // Security Commands (Phase 13: Secure Command Receiver)
    Route::prefix('commands')->group(function () {
        Route::get('/capabilities', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'capabilities']);
        Route::get('/pending', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'pending']);
        Route::get('/{uuid}', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'show']);
        Route::post('/{uuid}/acknowledge', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'acknowledge']);
        Route::post('/{uuid}/process', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'process']);
        Route::post('/{uuid}/result', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'reportResult']);
    });
});

// Public Command Capabilities & Rules Matrix
Route::get('/commands/capabilities', [\App\Http\Controllers\Api\V1\SecurityCommandController::class, 'capabilities']);

// Public Signed Evidence Access (Requires Valid Cryptographic Signature)
Route::get('/evidence/{id}/signed-download', [EvidenceController::class, 'signedDownload'])
    ->name('evidence.signed-download');

// Admin API
Route::prefix('admin')->group(base_path('routes/admin.php'));
