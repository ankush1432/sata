<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function __construct(
        protected OtpService $otpService
    ) {}

    /**
     * POST /api/v1/auth/register
     * Register a new user in PENDING status and dispatch cryptographically secure OTP.
     */
    public function register(Request $request): JsonResponse
    {
        $ip = $request->ip() ?? '127.0.0.1';
        $throttleKey = 'register:' . $ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->errorResponse(
                "Too many registration requests from this IP. Please wait {$seconds} seconds.",
                null,
                429
            );
        }

        // Normalize mobile from either 'mobile', 'phone', or 'phone_number'
        $mobile = $request->input('mobile')
            ?? $request->input('phone_number')
            ?? $request->input('phone');

        $dataToValidate = $request->all();
        $dataToValidate['mobile'] = $mobile;

        $validator = Validator::make($dataToValidate, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'mobile' => ['required', 'string', 'min:8', 'max:20', Rule::unique('users', 'phone_number')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ], [
            'mobile.unique' => 'The mobile has already been taken.',
            'email.unique' => 'The email has already been taken.',
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($throttleKey, 60);
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        // Create user in PENDING status
        $user = User::create([
            'name' => $request->input('name'),
            'phone_number' => $mobile,
            'email' => $request->input('email'),
            'password' => $request->filled('password') ? Hash::make($request->input('password')) : null,
            'status' => UserStatus::PENDING,
        ]);

        // Generate and dispatch OTP (only hash stored, never logged raw)
        $code = $this->otpService->generateOtp($mobile, 'REGISTRATION', $ip, $user->id);

        RateLimiter::hit($throttleKey, 60);

        $responseData = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->phone_number,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'status' => $user->status->value,
            ],
            'expires_in_minutes' => OtpService::EXPIRY_MINUTES,
        ];

        // Never return OTP in production
        if (config('app.env') !== 'production') {
            $responseData['dev_otp'] = $code;
        }

        return $this->successResponse(
            $responseData,
            'User registered successfully. Verification code sent to mobile.',
            201
        );
    }

    /**
     * POST /api/v1/auth/verify-otp
     * Validate OTP against stored hash, limit attempts, expire old codes, set mobile_verified_at,
     * and transition user status to AWAITING_ACTIVATION.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $mobile = $request->input('mobile')
            ?? $request->input('phone_number')
            ?? $request->input('phone');

        $dataToValidate = $request->all();
        $dataToValidate['mobile'] = $mobile;

        $validator = Validator::make($dataToValidate, [
            'mobile' => ['required', 'string', 'min:8', 'max:20'],
            'otp' => ['required', 'string', 'size:6'],
            'purpose' => ['nullable', 'string', 'in:LOGIN,REGISTRATION,TRUSTED_USER'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        $otp = (string) $request->input('otp');
        $purpose = $request->input('purpose');
        $ip = $request->ip() ?? '127.0.0.1';
        $throttleKey = "verify-otp:{$ip}:{$mobile}";

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->errorResponse(
                "Too many verification attempts. Please wait {$seconds} seconds.",
                null,
                429
            );
        }

        $result = $this->otpService->verifyOtp($mobile, $otp, $purpose);

        if (! $result['success']) {
            RateLimiter::hit($throttleKey, 60);

            $statusCode = match ($result['status']) {
                'MAX_ATTEMPTS' => 422,
                'EXPIRED' => 422,
                'INVALID' => 401,
                default => 401,
            };

            return $this->errorResponse($result['message'], ['otp' => [$result['message']]], $statusCode);
        }

        RateLimiter::clear($throttleKey);

        /** @var OtpVerification $record */
        $record = $result['record'];

        // Retrieve or create user record
        $user = User::where('phone_number', $mobile)->first();

        if (! $user) {
            $initialStatus = (($record && $record->purpose === 'LOGIN') || $purpose === 'LOGIN')
                ? UserStatus::OTP_VERIFIED
                : UserStatus::AWAITING_ACTIVATION;

            $user = User::create([
                'phone_number' => $mobile,
                'status' => $initialStatus,
                'phone_verified_at' => now(),
                'mobile_verified_at' => now(),
            ]);
        } else {
            $updates = [
                'phone_verified_at' => now(),
                'mobile_verified_at' => now(),
                'last_active_at' => now(),
            ];

            // Transition status: if purpose was LOGIN, keep OTP_VERIFIED for backwards compatibility;
            // otherwise set to AWAITING_ACTIVATION.
            if (($record && $record->purpose === 'LOGIN') || $purpose === 'LOGIN') {
                $updates['status'] = UserStatus::OTP_VERIFIED;
            } else {
                $updates['status'] = UserStatus::AWAITING_ACTIVATION;
            }

            $user->update($updates);
        }

        $deviceName = $request->input('device_name', 'mobile_app');
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->phone_number,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'status' => $user->status->value,
                'mobile_verified_at' => $user->mobile_verified_at?->toIso8601String(),
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'active_subscription' => $user->activeSubscription,
            ],
        ], 'Verification successful.');
    }

    /**
     * POST /api/v1/auth/resend-otp
     * Rate-limited endpoint to invalidate previous active OTP and generate a new secure one.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $mobile = $request->input('mobile')
            ?? $request->input('phone_number')
            ?? $request->input('phone');

        $dataToValidate = $request->all();
        $dataToValidate['mobile'] = $mobile;

        $validator = Validator::make($dataToValidate, [
            'mobile' => ['required', 'string', 'min:8', 'max:20'],
            'purpose' => ['nullable', 'string', 'in:LOGIN,REGISTRATION,TRUSTED_USER'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        $ip = $request->ip() ?? '127.0.0.1';
        $mobileThrottleKey = 'resend-otp-mobile:' . $mobile;
        $ipThrottleKey = 'resend-otp-ip:' . $ip;

        // Rate limit: 1 resend per 60 seconds per mobile number
        if (RateLimiter::tooManyAttempts($mobileThrottleKey, 1)) {
            $seconds = RateLimiter::availableIn($mobileThrottleKey);
            return $this->errorResponse(
                "Too many OTP resend attempts. Please wait {$seconds} seconds before requesting another code.",
                ['mobile' => ["Resend rate limit active. Wait {$seconds}s."]],
                429
            );
        }

        // Rate limit: max 5 resends per 10 minutes per IP
        if (RateLimiter::tooManyAttempts($ipThrottleKey, 5)) {
            $seconds = RateLimiter::availableIn($ipThrottleKey);
            return $this->errorResponse(
                "Too many requests from this network. Please wait {$seconds} seconds.",
                null,
                429
            );
        }

        RateLimiter::hit($mobileThrottleKey, 60);
        RateLimiter::hit($ipThrottleKey, 600);

        $purpose = $request->input('purpose', 'REGISTRATION');
        $user = User::where('phone_number', $mobile)->first();

        // Generate and dispatch new OTP (invalidating previous ones)
        $code = $this->otpService->generateOtp($mobile, $purpose, $ip, $user?->id);

        $responseData = [
            'mobile' => $mobile,
            'purpose' => $purpose,
            'expires_in_minutes' => OtpService::EXPIRY_MINUTES,
        ];

        if (config('app.env') !== 'production') {
            $responseData['dev_otp'] = $code;
        }

        return $this->successResponse(
            $responseData,
            'A new verification code has been dispatched to your mobile number.'
        );
    }

    /**
     * POST /api/v1/auth/login
     * Authenticate via mobile/email with password or initiate OTP login.
     */
    public function login(Request $request): JsonResponse
    {
        $identifier = $request->input('mobile')
            ?? $request->input('phone_number')
            ?? $request->input('phone')
            ?? $request->input('email');

        $ip = $request->ip() ?? '127.0.0.1';
        $throttleKey = "login:{$ip}:{$identifier}";

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->errorResponse(
                "Too many failed login attempts. Please wait {$seconds} seconds.",
                null,
                429
            );
        }

        $dataToValidate = $request->all();
        $dataToValidate['identifier'] = $identifier;

        $validator = Validator::make($dataToValidate, [
            'identifier' => ['required', 'string'],
            'password' => ['nullable', 'string'],
            'otp' => ['nullable', 'string', 'size:6'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        $user = User::where('phone_number', $identifier)
            ->orWhere('email', $identifier)
            ->first();

        if (! $user) {
            RateLimiter::hit($throttleKey, 60);
            return $this->errorResponse('Invalid credentials.', null, 401);
        }

        if ($user->status === UserStatus::DISABLED) {
            return $this->errorResponse('Your account is disabled. Please contact support.', null, 403);
        }

        // Verify password if provided or required
        if ($request->filled('password')) {
            if (! $user->password || ! Hash::check($request->input('password'), $user->password)) {
                RateLimiter::hit($throttleKey, 60);
                return $this->errorResponse('Invalid credentials.', null, 401);
            }
        } elseif ($request->filled('otp')) {
            // Verify via OTP
            $otpResult = $this->otpService->verifyOtp($user->phone_number, $request->input('otp'), 'LOGIN');
            if (! $otpResult['success']) {
                RateLimiter::hit($throttleKey, 60);
                return $this->errorResponse($otpResult['message'], null, 401);
            }
        } else {
            // No password or OTP supplied: dispatch login OTP
            $code = $this->otpService->generateOtp($user->phone_number, 'LOGIN', $ip, $user->id);

            $responseData = [
                'require_otp' => true,
                'mobile' => $user->phone_number,
                'expires_in_minutes' => OtpService::EXPIRY_MINUTES,
            ];

            if (config('app.env') !== 'production') {
                $responseData['dev_otp'] = $code;
            }

            return $this->successResponse($responseData, 'Login verification code sent to mobile.');
        }

        RateLimiter::clear($throttleKey);
        $user->update(['last_active_at' => now()]);

        $deviceName = $request->input('device_name', 'mobile_app');
        $token = $user->createToken($deviceName)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->phone_number,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'status' => $user->status->value,
                'mobile_verified_at' => $user->mobile_verified_at?->toIso8601String(),
                'active_subscription' => $user->activeSubscription,
            ],
        ], 'Login successful.');
    }

    /**
     * GET /api/v1/auth/me
     * Return authenticated user profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['activeSubscription.plan', 'currentDevice', 'trustedUsers']);

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->phone_number,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'status' => $user->status->value,
                'mobile_verified_at' => $user->mobile_verified_at?->toIso8601String(),
                'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
                'last_active_at' => $user->last_active_at?->toIso8601String(),
                'active_subscription' => $user->activeSubscription,
                'current_device' => $user->currentDevice,
                'trusted_users' => $user->trustedUsers,
            ],
        ]);
    }

    /**
     * GET /api/v1/auth/profile
     * Alias for me() for backwards compatibility.
     */
    public function profile(Request $request): JsonResponse
    {
        return $this->me($request);
    }

    /**
     * PUT|POST /api/v1/auth/profile
     * Update authenticated user profile attributes.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        if ($request->has('name')) {
            $user->name = trim((string) $request->input('name'));
        }

        if ($request->has('email')) {
            $user->email = $request->input('email') ? trim((string) $request->input('email')) : null;
        }

        $user->save();

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'mobile' => $user->phone_number,
                'phone_number' => $user->phone_number,
                'email' => $user->email,
                'status' => $user->status->value,
                'mobile_verified_at' => $user->mobile_verified_at?->toIso8601String(),
                'active_subscription' => $user->activeSubscription,
            ],
        ], 'Profile updated successfully.');
    }

    /**
     * POST /api/v1/auth/change-password
     * Update user password with current password verification.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:6', 'max:100', 'different:current_password'],
            'new_password_confirmation' => ['sometimes', 'same:new_password'],
        ], [
            'new_password.different' => 'The new password must be different from current password.',
            'new_password_confirmation.same' => 'Password confirmation does not match.',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        if (! $user->password || ! Hash::check($request->input('current_password'), $user->password)) {
            return $this->errorResponse('The current password provided is incorrect.', [
                'current_password' => ['The current password provided is incorrect.'],
            ], 422);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return $this->successResponse(null, 'Password changed successfully.');
    }

    /**
     * POST /api/v1/auth/logout
     * Revoke active bearer token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully.');
    }

    /**
     * POST /api/v1/auth/send-otp (Backwards compatibility)
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $mobile = $request->input('mobile')
            ?? $request->input('phone_number')
            ?? $request->input('phone');

        $dataToValidate = $request->all();
        $dataToValidate['phone_number'] = $mobile;

        $validator = Validator::make($dataToValidate, [
            'phone_number' => ['required', 'string', 'min:8', 'max:20'],
            'purpose' => ['nullable', 'string', 'in:LOGIN,REGISTRATION,TRUSTED_USER'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed.', $validator->errors(), 422);
        }

        $purpose = $request->input('purpose', 'LOGIN');
        $code = $this->otpService->generateOtp($mobile, $purpose, $request->ip());

        $responseData = [
            'phone_number' => $mobile,
            'mobile' => $mobile,
            'purpose' => $purpose,
            'expires_in_minutes' => OtpService::EXPIRY_MINUTES,
        ];

        if (config('app.env') !== 'production') {
            $responseData['dev_otp'] = $code;
        }

        return $this->successResponse($responseData, 'OTP sent successfully.');
    }
}
