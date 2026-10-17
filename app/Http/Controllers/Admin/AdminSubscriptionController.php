<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with(['user:id,name,email,phone_number', 'plan']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $subscriptions = $query->latest()->paginate($request->input('per_page', 20));

        return $this->successResponse($subscriptions);
    }

    public function show(int $id): JsonResponse
    {
        $subscription = Subscription::with(['user', 'plan'])->find($id);

        if (! $subscription) {
            return $this->errorResponse('Subscription not found.', null, 404);
        }

        return $this->successResponse(['subscription' => $subscription]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $oldStatus = $subscription->status->value;

        $subscription->update([
            'status' => SubscriptionStatus::CANCELLED,
            'auto_renew' => false,
        ]);

        $this->auditLogService->log(
            $request->user(),
            'SUBSCRIPTION_CANCELLED',
            'SUBSCRIPTION',
            $subscription,
            ['status' => $oldStatus],
            ['status' => SubscriptionStatus::CANCELLED->value]
        );

        return $this->successResponse(['subscription' => $subscription], 'Subscription cancelled.');
    }

    public function extend(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:3650'],
        ]);

        $subscription = Subscription::findOrFail($id);
        $beforeExpiry = $subscription->expires_at;

        $currentExpiry = $subscription->expires_at && $subscription->expires_at->isFuture()
            ? $subscription->expires_at
            : now();

        $newExpiry = $currentExpiry->copy()->addDays($request->input('days'));

        $subscription->update([
            'expires_at' => $newExpiry,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        $this->auditLogService->log(
            $request->user(),
            'SUBSCRIPTION_EXTENDED',
            'SUBSCRIPTION',
            $subscription,
            ['expires_at' => $beforeExpiry],
            ['expires_at' => $newExpiry, 'days_added' => $request->input('days')]
        );

        return $this->successResponse(['subscription' => $subscription], 'Subscription extended.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $subscription = Subscription::findOrFail($id);
        $before = $subscription->only(['id', 'user_id', 'plan_id', 'status']);
        $subscription->delete();

        $this->auditLogService->log(
            $request->user(),
            'SUBSCRIPTION_DELETED',
            'SUBSCRIPTION',
            null,
            $before,
            null
        );

        return $this->successResponse(null, 'Subscription deleted successfully.');
    }
}
