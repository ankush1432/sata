<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Admin login with email and password, rate limiting, and audit logging.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $email = strtolower(trim((string) $request->input('email')));
        $throttleKey = 'admin_login:' . sha1($email . '|' . $request->ip());

        // Max 5 attempts per 60 seconds
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->auditLogService->log(
                null,
                'ADMIN_LOGIN_THROTTLED',
                'AUTH',
                null,
                null,
                ['email' => $email, 'retry_after' => $seconds],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'success' => false,
                'message' => "Too many login attempts. Please try again in {$seconds} seconds.",
                'error_code' => 'TOO_MANY_REQUESTS',
                'retry_after' => $seconds,
            ], 429);
        }

        $admin = Admin::where('email', $email)->first();

        if (! $admin || ! Hash::check($request->input('password'), $admin->password)) {
            RateLimiter::hit($throttleKey, 60);

            $this->auditLogService->log(
                $admin,
                'ADMIN_LOGIN_FAILED',
                'AUTH',
                $admin,
                null,
                ['email' => $email, 'reason' => 'invalid_credentials'],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password credentials.',
                'error_code' => 'INVALID_CREDENTIALS',
            ], 401);
        }

        if (! $admin->is_active) {
            RateLimiter::hit($throttleKey, 60);

            $this->auditLogService->log(
                $admin,
                'ADMIN_LOGIN_DISABLED_REJECTED',
                'AUTH',
                $admin,
                null,
                ['email' => $email, 'reason' => 'account_suspended'],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'success' => false,
                'message' => 'Your admin account has been suspended or is inactive.',
                'error_code' => 'ADMIN_ACCOUNT_SUSPENDED',
            ], 403);
        }

        RateLimiter::clear($throttleKey);

        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        $token = $admin->createToken('admin_panel', ['role:' . $admin->role->value])->plainTextToken;

        $this->auditLogService->log(
            $admin,
            'ADMIN_LOGIN',
            'AUTH',
            $admin,
            null,
            ['role' => $admin->role->value],
            $request->ip(),
            $request->userAgent()
        );

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'permissions' => $admin->getPermissions(),
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
                'is_active' => (bool) $admin->is_active,
                'permissions' => $admin->getPermissions(),
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
                'last_login_ip' => $admin->last_login_ip,
            ],
        ], 'Admin login successful.');
    }

    /**
     * Get current authenticated admin (GET /admin/me).
     */
    public function me(Request $request): JsonResponse
    {
        $admin = $request->user();

        return $this->successResponse([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
                'is_active' => (bool) $admin->is_active,
                'permissions' => $admin->getPermissions(),
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
                'last_login_ip' => $admin->last_login_ip,
                'created_at' => $admin->created_at?->toIso8601String(),
            ],
        ], 'Current admin details retrieved.');
    }

    /**
     * Profile alias for backward compatibility.
     */
    public function profile(Request $request): JsonResponse
    {
        return $this->me($request);
    }

    /**
     * Admin password change (POST /admin/password/change).
     */
    public function changePassword(Request $request): JsonResponse
    {
        $admin = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        if (! Hash::check($request->input('current_password'), $admin->password)) {
            $this->auditLogService->log(
                $admin,
                'ADMIN_PASSWORD_CHANGE_FAILED',
                'AUTH',
                $admin,
                null,
                ['reason' => 'current_password_mismatch'],
                $request->ip(),
                $request->userAgent()
            );

            return $this->errorResponse('Current password does not match.', null, 422);
        }

        if (Hash::check($request->input('new_password'), $admin->password)) {
            return $this->errorResponse('New password cannot be the same as the current password.', null, 422);
        }

        // Update password with secure hash
        $admin->password = Hash::make($request->input('new_password'));
        $admin->save();

        // Revoke previous tokens and issue a fresh one
        $admin->tokens()->delete();
        $freshToken = $admin->createToken('admin_panel', ['role:' . $admin->role->value])->plainTextToken;

        $this->auditLogService->log(
            $admin,
            'ADMIN_PASSWORD_CHANGED',
            'AUTH',
            $admin,
            null,
            ['action' => 'password_updated_tokens_revoked'],
            $request->ip(),
            $request->userAgent()
        );

        return $this->successResponse([
            'token' => $freshToken,
            'token_type' => 'Bearer',
        ], 'Password changed successfully.');
    }

    /**
     * Admin logout (POST /admin/logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $admin = $request->user();

        $this->auditLogService->log(
            $admin,
            'ADMIN_LOGOUT',
            'AUTH',
            $admin,
            null,
            ['action' => 'token_revoked'],
            $request->ip(),
            $request->userAgent()
        );

        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Admin logged out successfully.');
    }
}
