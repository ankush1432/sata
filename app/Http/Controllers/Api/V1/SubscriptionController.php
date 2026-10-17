<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * Get the authoritative current subscription for the authenticated user.
     * GET /api/v1/subscription/current
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $details = $this->subscriptionService->getAuthoritativeSubscriptionDetails($user);

        if (! $details['subscription']) {
            return $this->successResponse([
                'subscription' => null,
                'has_active_subscription' => false,
                'state' => 'INACTIVE',
                'protection_status' => 'INACTIVE',
                'is_protection_enabled' => false,
                'expiry_date' => null,
                'formatted_expiry_date' => null,
                'permissions' => $details['permissions'],
            ], 'No active subscription found.');
        }

        $subscription = $details['subscription'];

        return $this->successResponse([
            'subscription' => [
                'id' => $subscription->id,
                'user_id' => $subscription->user_id,
                'plan_id' => $subscription->plan_id ?? $subscription->activation_plan_id,
                'activation_code_id' => $subscription->activation_code_id,
                'device_id' => $subscription->device_id,
                'status' => $subscription->status->value,
                'state' => $details['state'],
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'activated_at' => $subscription->activated_at?->toIso8601String(),
                'expires_at' => $subscription->expires_at?->toIso8601String(),
                'expiry_date' => $details['expiry_date'],
                'formatted_expiry_date' => $details['formatted_expiry_date'],
                'days_remaining' => $subscription->daysRemaining(),
                'is_active' => $details['has_active_subscription'],
                'is_expiring' => $details['state'] === 'EXPIRING',
                'is_expired' => $details['state'] === 'EXPIRED',
                'is_disabled' => $subscription->isDisabled(),
                'plan' => $subscription->plan ? [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'code' => $subscription->plan->code,
                    'duration_days' => $subscription->plan->duration_days,
                    'price' => $subscription->plan->price,
                ] : null,
                'device' => $subscription->device ? [
                    'id' => $subscription->device->id,
                    'device_id' => $subscription->device->device_id,
                    'device_name' => $subscription->device->device_name,
                    'model' => $subscription->device->model,
                    'platform' => $subscription->device->platform,
                    'status' => $subscription->device->status->value ?? $subscription->device->status,
                    'is_protection_enabled' => (bool) $subscription->device->is_protection_enabled,
                ] : null,
            ],
            'has_active_subscription' => $details['has_active_subscription'],
            'state' => $details['state'],
            'protection_status' => $details['protection_status'],
            'is_protection_enabled' => $details['is_protection_enabled'],
            'expiry_date' => $details['expiry_date'],
            'formatted_expiry_date' => $details['formatted_expiry_date'],
            'permissions' => $details['permissions'],
        ], $details['message']);
    }

    /**
     * Get full subscription history for the authenticated user.
     * GET /api/v1/subscription/history
     */
    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 15);

        $paginator = $this->subscriptionService->getSubscriptionHistory($user, $perPage);

        $items = collect($paginator->items())->map(function ($sub) {
            return [
                'id' => $sub->id,
                'user_id' => $sub->user_id,
                'plan_id' => $sub->plan_id ?? $sub->activation_plan_id,
                'activation_code_id' => $sub->activation_code_id,
                'device_id' => $sub->device_id,
                'status' => $sub->status->value,
                'starts_at' => $sub->starts_at?->toIso8601String(),
                'activated_at' => $sub->activated_at?->toIso8601String(),
                'expires_at' => $sub->expires_at?->toIso8601String(),
                'disabled_at' => $sub->disabled_at?->toIso8601String(),
                'disable_reason' => $sub->disable_reason,
                'days_remaining' => $sub->daysRemaining(),
                'is_active' => $sub->isActive(),
                'plan' => $sub->plan ? [
                    'id' => $sub->plan->id,
                    'name' => $sub->plan->name,
                    'code' => $sub->plan->code,
                    'duration_days' => $sub->plan->duration_days,
                ] : null,
                'created_at' => $sub->created_at?->toIso8601String(),
            ];
        });

        return $this->successResponse([
            'subscriptions' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Subscription history retrieved successfully.');
    }
}
