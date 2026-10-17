<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlanDuration;
use App\Http\Controllers\Controller;
use App\Models\ActivationPlan;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPlanController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List all activation plans with subscription and code counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivationPlan::withCount(['activationCodes', 'subscriptions']);

        if ($request->filled('status')) {
            $status = strtoupper(trim($request->input('status')));
            $query->where('status', $status);
        } elseif ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search);
            });
        }

        $plans = $query->orderBy('duration_days', 'asc')->get();

        return $this->successResponse(['plans' => $plans], 'Activation plans retrieved successfully.');
    }

    /**
     * View details of a specific activation plan.
     */
    public function show(int $id): JsonResponse
    {
        $plan = ActivationPlan::withCount(['activationCodes', 'subscriptions'])
            ->with(['subscriptions' => function ($q) {
                $q->latest()->limit(10);
            }])
            ->find($id);

        if (! $plan) {
            return $this->errorResponse('Activation plan not found.', null, 404);
        }

        return $this->successResponse(['plan' => $plan]);
    }

    /**
     * Create a new activation plan.
     * Backend strictly owns duration for standard plans (MONTHLY=30, QUARTERLY=90, HALF_YEARLY=180, YEARLY=365).
     */
    public function store(Request $request): JsonResponse
    {
        // Check for invalid duration first if supplied
        if ($request->has('duration_days')) {
            $rawDuration = $request->input('duration_days');
            if (! is_numeric($rawDuration) || (int) $rawDuration <= 0) {
                return $this->errorResponse('Invalid duration. Duration must be a positive integer greater than 0.', [
                    'duration_days' => ['Invalid duration. Must be an integer greater than 0.'],
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', 'unique:activation_plans,code'],
            'price' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,ENABLED,DISABLED'],
            'is_active' => ['nullable', 'boolean'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $validated = $validator->validated();
        $code = strtoupper(trim($validated['code']));
        $validated['code'] = $code;

        // Backend owns duration for standard plans
        $standardDuration = PlanDuration::durationForCode($code);
        if ($standardDuration !== null) {
            if ($request->filled('duration_days') && (int) $request->input('duration_days') !== $standardDuration) {
                return $this->errorResponse("Invalid duration for standard plan code '{$code}'. Backend defines duration as {$standardDuration} days.", [
                    'duration_days' => ["Standard plan '{$code}' duration must be {$standardDuration} days."],
                ], 422);
            }
            $validated['duration_days'] = $standardDuration;
        } else {
            if (! isset($validated['duration_days'])) {
                return $this->errorResponse('Invalid duration. duration_days is required for custom plan codes.', [
                    'duration_days' => ['Duration days must be specified for custom plans.'],
                ], 422);
            }
            $validated['duration_days'] = (int) $validated['duration_days'];
        }

        // Set status and is_active
        if (isset($validated['status'])) {
            $status = strtoupper($validated['status']);
            $isActive = in_array($status, ['ACTIVE', 'ENABLED'], true);
            $validated['status'] = $isActive ? 'ACTIVE' : 'INACTIVE';
            $validated['is_active'] = $isActive;
        } elseif (isset($validated['is_active'])) {
            $isActive = (bool) $validated['is_active'];
            $validated['status'] = $isActive ? 'ACTIVE' : 'INACTIVE';
            $validated['is_active'] = $isActive;
        } else {
            $validated['status'] = 'ACTIVE';
            $validated['is_active'] = true;
        }

        $validated['currency'] = $validated['currency'] ?? 'USD';

        $plan = ActivationPlan::create($validated);

        $this->auditLogService->log($request->user(), 'PLAN_CREATED', 'PLANS', $plan, null, $plan->toArray());

        return $this->successResponse(['plan' => $plan], 'Activation plan created successfully.', 201);
    }

    /**
     * Update an activation plan.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $plan = ActivationPlan::find($id);

        if (! $plan) {
            return $this->errorResponse('Activation plan not found.', null, 404);
        }

        // Check for invalid duration if passed
        if ($request->has('duration_days')) {
            $rawDuration = $request->input('duration_days');
            if (! is_numeric($rawDuration) || (int) $rawDuration <= 0) {
                return $this->errorResponse('Invalid duration. Duration must be a positive integer greater than 0.', [
                    'duration_days' => ['Invalid duration. Must be an integer greater than 0.'],
                ], 422);
            }
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'unique:activation_plans,code,' . $id],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:ACTIVE,INACTIVE,ENABLED,DISABLED'],
            'is_active' => ['nullable', 'boolean'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $validated = $validator->validated();
        $code = isset($validated['code']) ? strtoupper(trim($validated['code'])) : $plan->code;
        if (isset($validated['code'])) {
            $validated['code'] = $code;
        }

        // Backend owns duration verification for standard plans
        $standardDuration = PlanDuration::durationForCode($code);
        if ($standardDuration !== null) {
            if ($request->filled('duration_days') && (int) $request->input('duration_days') !== $standardDuration) {
                return $this->errorResponse("Invalid duration for standard plan code '{$code}'. Backend defines duration as {$standardDuration} days.", [
                    'duration_days' => ["Standard plan '{$code}' duration must be {$standardDuration} days."],
                ], 422);
            }
            if ($request->has('code') && ! $request->filled('duration_days')) {
                $validated['duration_days'] = $standardDuration;
            }
        }

        if (isset($validated['status'])) {
            $status = strtoupper($validated['status']);
            $isActive = in_array($status, ['ACTIVE', 'ENABLED'], true);
            $validated['status'] = $isActive ? 'ACTIVE' : 'INACTIVE';
            $validated['is_active'] = $isActive;
        } elseif (isset($validated['is_active'])) {
            $isActive = (bool) $validated['is_active'];
            $validated['status'] = $isActive ? 'ACTIVE' : 'INACTIVE';
            $validated['is_active'] = $isActive;
        }

        $before = $plan->toArray();
        $plan->update($validated);

        $this->auditLogService->log(
            $request->user(),
            'PLAN_UPDATED',
            'PLANS',
            $plan,
            $before,
            $plan->fresh()->toArray()
        );

        return $this->successResponse(['plan' => $plan->fresh()], 'Activation plan updated successfully.');
    }

    /**
     * Enable an activation plan.
     */
    public function enable(Request $request, int $id): JsonResponse
    {
        $plan = ActivationPlan::find($id);

        if (! $plan) {
            return $this->errorResponse('Activation plan not found.', null, 404);
        }

        $before = $plan->toArray();
        $plan->enable();

        $this->auditLogService->log(
            $request->user(),
            'PLAN_ENABLED',
            'PLANS',
            $plan,
            $before,
            $plan->fresh()->toArray()
        );

        return $this->successResponse(['plan' => $plan->fresh()], 'Activation plan enabled successfully.');
    }

    /**
     * Disable an activation plan.
     * Historical subscription records remain valid even when a plan is disabled.
     */
    public function disable(Request $request, int $id): JsonResponse
    {
        $plan = ActivationPlan::find($id);

        if (! $plan) {
            return $this->errorResponse('Activation plan not found.', null, 404);
        }

        $before = $plan->toArray();
        $plan->disable();

        $this->auditLogService->log(
            $request->user(),
            'PLAN_DISABLED',
            'PLANS',
            $plan,
            $before,
            $plan->fresh()->toArray()
        );

        return $this->successResponse([
            'plan' => $plan->fresh(),
            'note' => 'Historical subscriptions associated with this plan remain active and valid.',
        ], 'Activation plan disabled successfully.');
    }

    /**
     * Delete an activation plan.
     * Rule: Do not delete a plan with historical subscriptions.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $plan = ActivationPlan::withCount('subscriptions')->find($id);

        if (! $plan) {
            return $this->errorResponse('Activation plan not found.', null, 404);
        }

        if ($plan->subscriptions_count > 0 || $plan->hasHistoricalSubscriptions()) {
            return $this->errorResponse(
                'Cannot delete activation plan with historical subscriptions. You can disable the plan instead.',
                ['plan' => ['Plan has historical subscriptions and cannot be deleted.']],
                422
            );
        }

        if ($plan->activationCodes()->where('is_redeemed', true)->exists()) {
            return $this->errorResponse(
                'Cannot delete activation plan with redeemed activation codes. You can disable the plan instead.',
                ['plan' => ['Plan has historical redeemed codes and cannot be deleted.']],
                422
            );
        }

        $before = $plan->toArray();
        $plan->delete();

        $this->auditLogService->log(
            $request->user(),
            'PLAN_DELETED',
            'PLANS',
            null,
            $before,
            null
        );

        return $this->successResponse(null, 'Activation plan deleted successfully.');
    }
}
