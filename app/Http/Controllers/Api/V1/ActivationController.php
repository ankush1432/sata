<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivationPlan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ActivationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class ActivationController extends Controller
{
    public function __construct(
        protected ActivationService $activationService
    ) {}

    /**
     * List all active activation plans.
     */
    public function listPlans(): JsonResponse
    {
        $plans = ActivationPlan::active()
            ->orderBy('duration_days', 'asc')
            ->get();

        return $this->successResponse([
            'plans' => $plans,
        ], 'Activation plans retrieved successfully.');
    }

    /**
     * Activate a subscription using an activation code and device ID.
     * POST /api/v1/activation/activate
     */
    public function activate(Request $request): JsonResponse
    {
        $user = $request->user();
        $ip = $request->ip() ?? '127.0.0.1';
        $throttleKey = 'activation:' . $ip . ':' . ($user?->id ?? 'guest');

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->errorResponse(
                "Too many activation attempts. Please wait {$seconds} seconds.",
                null,
                429
            );
        }

        $validator = Validator::make($request->all(), [
            'activation_code' => ['required_without:code', 'nullable', 'string', 'max:64'],
            'code' => ['required_without:activation_code', 'nullable', 'string', 'max:64'],
            'device_id' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($throttleKey, 60);
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $code = $request->input('activation_code') ?? $request->input('code');
        $deviceId = $request->input('device_id');

        try {
            $data = $this->activationService->activate(
                $request->user(),
                $code,
                $deviceId
            );

            RateLimiter::clear($throttleKey);

            $freshUser = $request->user()->fresh();
            $statusVal = $freshUser->status instanceof \BackedEnum
                ? $freshUser->status->value
                : (string) ($freshUser->status ?? 'ACTIVE');

            return $this->successResponse([
                'subscription' => $data['subscription'],
                'device' => $data['device'],
                'plan' => $data['plan'],
                'code' => $data['code'],
                'user_status' => $statusVal,
            ], 'Activation successful. Device protection initialized.', 200);
        } catch (DomainException $e) {
            RateLimiter::hit($throttleKey, 60);
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Redeem an activation code for the authenticated user.
     */
    public function redeemCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $ip = $request->ip() ?? '127.0.0.1';
        $throttleKey = 'activation:' . $ip . ':' . ($user?->id ?? 'guest');

        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return $this->errorResponse(
                "Too many activation attempts. Please wait {$seconds} seconds.",
                null,
                429
            );
        }

        $validator = Validator::make($request->all(), [
            'code' => ['required_without:activation_code', 'nullable', 'string', 'max:64'],
            'activation_code' => ['required_without:code', 'nullable', 'string', 'max:64'],
        ]);

        if ($validator->fails()) {
            RateLimiter::hit($throttleKey, 60);
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $code = $request->input('activation_code') ?? $request->input('code');
        $deviceId = $request->input('device_id', $request->user()->current_device_id ?? 'DEFAULT-DEVICE');

        try {
            $data = $this->activationService->activate(
                $request->user(),
                $code,
                $deviceId
            );

            RateLimiter::clear($throttleKey);

            $freshUser = $request->user()->fresh();
            $statusVal = $freshUser->status instanceof \BackedEnum
                ? $freshUser->status->value
                : (string) ($freshUser->status ?? 'ACTIVE');

            return $this->successResponse([
                'subscription' => $data['subscription'],
                'user_status' => $statusVal,
            ], 'Activation code redeemed successfully. Your account is now active.');
        } catch (DomainException $e) {
            RateLimiter::hit($throttleKey, 60);
            return $this->errorResponse($e->getMessage(), null, 422);
        }
    }

    /**
     * Get the authenticated user's current subscription status.
     */
    public function subscriptionStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            $user = User::find($user->id);
        }

        $subscription = null;
        if ($user) {
            $subscription = $user->activeSubscription?->load('plan')
                ?? Subscription::where('user_id', $user->id)->latest()->first()?->load('plan');
        }

        $statusValue = ($user && $user->status instanceof \BackedEnum)
            ? $user->status->value
            : (string) ($user?->status ?? 'ACTIVE');

        return $this->successResponse([
            'user_status' => $statusValue,
            'is_active' => $subscription ? $subscription->isActive() : false,
            'subscription' => $subscription,
        ]);
    }
}
