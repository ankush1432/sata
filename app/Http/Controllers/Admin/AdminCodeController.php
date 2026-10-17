<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivationCodeStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivationCode;
use App\Models\ActivationCodeBatch;
use App\Models\ActivationPlan;
use App\Services\ActivationService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminCodeController extends Controller
{
    public function __construct(
        protected ActivationService $activationService,
        protected AuditLogService $auditLogService
    ) {}

    /**
     * List activation codes with search, filtering, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivationCode::with([
            'plan',
            'batch',
            'activatedByUser:id,name,email,phone_number',
            'generatedByAdmin:id,name,email',
            'activatedDevice:id,device_uuid,device_name,platform',
        ]);

        // Search by serial number or code
        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('serial_number', 'like', "%{$search}%")
                    ->orWhere('code_last4', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        // Filter by Status
        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->input('status')));
        }

        // Filter by Plan
        if ($request->filled('plan_id')) {
            $planId = $request->input('plan_id');
            $query->where(function ($q) use ($planId) {
                $q->where('plan_id', $planId)
                    ->orWhere('activation_plan_id', $planId);
            });
        } elseif ($request->filled('activation_plan_id')) {
            $planId = $request->input('activation_plan_id');
            $query->where(function ($q) use ($planId) {
                $q->where('plan_id', $planId)
                    ->orWhere('activation_plan_id', $planId);
            });
        }

        // Filter by Batch
        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        // Filter by Redemption status (legacy support)
        if ($request->has('is_redeemed')) {
            $query->where('is_redeemed', filter_var($request->input('is_redeemed'), FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $perPage = (int) $request->input('per_page', 30);
        $codes = $query->latest('id')->paginate($perPage);

        return $this->successResponse($codes);
    }

    /**
     * Generate a single activation code by plan.
     */
    public function generateOne(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => ['required_without:activation_plan_id', 'integer', 'exists:activation_plans,id'],
            'activation_plan_id' => ['required_without:plan_id', 'integer', 'exists:activation_plans,id'],
            'notes' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $planId = $request->input('plan_id') ?? $request->input('activation_plan_id');
        $plan = ActivationPlan::findOrFail($planId);

        $expiresAt = $request->filled('expires_at') ? new \DateTime($request->input('expires_at')) : null;
        $code = $this->activationService->generateOne(
            $plan,
            $request->user(),
            $request->input('notes'),
            $expiresAt
        );

        $this->auditLogService->log(
            $request->user(),
            'ACTIVATION_CODE_GENERATED',
            'ACTIVATION',
            $code,
            null,
            [
                'serial_number' => $code->serial_number,
                'plan_id' => $plan->id,
                'code_last4' => $code->code_last4,
            ]
        );

        return $this->successResponse([
            'code' => $code->load('plan'),
        ], 'Activation code generated successfully.', 201);
    }

    /**
     * Bulk generate activation codes by plan.
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => ['required_without:activation_plan_id', 'integer', 'exists:activation_plans,id'],
            'activation_plan_id' => ['required_without:plan_id', 'integer', 'exists:activation_plans,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
            'async' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $planId = $request->input('plan_id') ?? $request->input('activation_plan_id');
        $plan = ActivationPlan::findOrFail($planId);
        $quantity = (int) $request->input('quantity');
        $forceQueue = $request->boolean('async') || $quantity >= 50;

        $expiresAt = $request->filled('expires_at') ? new \DateTime($request->input('expires_at')) : null;
        $batch = $this->activationService->generateBatch(
            $plan,
            $quantity,
            $request->user(),
            $request->input('notes'),
            $expiresAt,
            $forceQueue
        );

        $this->auditLogService->log(
            $request->user(),
            'BATCH_GENERATED',
            'ACTIVATION',
            $batch,
            null,
            [
                'batch_number' => $batch->batch_number,
                'plan_id' => $plan->id,
                'quantity' => $quantity,
                'queued' => $forceQueue,
            ]
        );

        $message = $forceQueue
            ? 'Batch generation job queued successfully.'
            : 'Batch generated successfully.';

        return $this->successResponse([
            'batch' => $batch->load('codes'),
            'queued' => $forceQueue,
        ], $message, 201);
    }

    /**
     * View activation code details.
     */
    public function show(int $id): JsonResponse
    {
        $code = ActivationCode::with([
            'plan',
            'batch',
            'activatedByUser',
            'generatedByAdmin',
            'activatedDevice',
            'subscription',
        ])->find($id);

        if (! $code) {
            return $this->errorResponse('Activation code not found.', null, 404);
        }

        return $this->successResponse(['code' => $code]);
    }

    /**
     * Disable a single activation code.
     */
    public function disable(Request $request, int $id): JsonResponse
    {
        $code = ActivationCode::findOrFail($id);

        if ($code->is_redeemed || $code->status === ActivationCodeStatus::ACTIVATED->value) {
            return $this->errorResponse('Cannot disable already redeemed activation code.', null, 400);
        }

        $before = [
            'status' => $code->status,
            'is_active' => $code->is_active,
        ];

        $code->update([
            'status' => ActivationCodeStatus::DISABLED->value,
            'is_active' => false,
        ]);

        $this->auditLogService->log(
            $request->user(),
            'ACTIVATION_CODE_DISABLED',
            'ACTIVATION',
            $code,
            $before,
            [
                'status' => ActivationCodeStatus::DISABLED->value,
                'is_active' => false,
            ]
        );

        return $this->successResponse(['code' => $code], 'Activation code disabled.');
    }

    /**
     * Disable all unredeemed codes in a batch.
     */
    public function disableBatch(Request $request, int $id): JsonResponse
    {
        $batch = ActivationCodeBatch::findOrFail($id);

        $affected = ActivationCode::where('batch_id', $batch->id)
            ->where('is_redeemed', false)
            ->where('status', '!=', ActivationCodeStatus::ACTIVATED->value)
            ->update([
                'status' => ActivationCodeStatus::DISABLED->value,
                'is_active' => false,
            ]);

        $this->auditLogService->log(
            $request->user(),
            'BATCH_DISABLED',
            'ACTIVATION',
            $batch,
            null,
            ['batch_id' => $batch->id, 'affected_count' => $affected]
        );

        return $this->successResponse([
            'batch' => $batch,
            'affected_codes' => $affected,
        ], 'All unredeemed codes in batch disabled.');
    }

    /**
     * Export activation codes to CSV format.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        $query = ActivationCode::with(['plan', 'batch']);

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->input('plan_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', strtoupper($request->input('status')));
        }

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="sata-activation-codes-' . date('Y-m-d') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, [
                'Serial Number',
                'Activation Code',
                'Plan Code',
                'Plan Name',
                'Duration (Days)',
                'Status',
                'Created At',
                'Expires At',
                'Activated At',
            ]);

            $query->chunk(500, function ($codes) use ($handle) {
                foreach ($codes as $c) {
                    fputcsv($handle, [
                        $c->serial_number ?? sprintf('SAT-%06d', $c->id),
                        $c->code,
                        $c->plan?->code ?? 'N/A',
                        $c->plan?->name ?? 'N/A',
                        $c->plan?->duration_days ?? 0,
                        $c->status,
                        $c->created_at?->toIso8601String(),
                        $c->expires_at?->toIso8601String(),
                        $c->activated_at?->toIso8601String() ?? $c->redeemed_at?->toIso8601String(),
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Printable card/voucher view/data for codes.
     */
    public function printCards(Request $request): JsonResponse
    {
        $query = ActivationCode::with('plan');

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        if ($request->filled('code_ids')) {
            $ids = is_array($request->input('code_ids')) ? $request->input('code_ids') : explode(',', $request->input('code_ids'));
            $query->whereIn('id', $ids);
        }

        $limit = min((int) $request->input('limit', 100), 500);
        $codes = $query->take($limit)->get();

        $printableCards = $codes->map(function (ActivationCode $code) {
            return [
                'serial_number' => $code->serial_number ?? sprintf('SAT-%06d', $code->id),
                'display_code' => $code->code,
                'plan_name' => $code->plan?->name ?? 'SATA Security Plan',
                'plan_code' => $code->plan?->code ?? 'N/A',
                'duration_days' => $code->plan?->duration_days ?? 30,
                'status' => $code->status,
                'instructions' => 'Open SATA Mobile App -> Activation -> Enter the 16-character code above to protect your device.',
            ];
        });

        return $this->successResponse([
            'count' => $printableCards->count(),
            'cards' => $printableCards,
        ], 'Printable vouchers prepared.');
    }

    /**
     * Print a single activation card by code ID.
     */
    public function printCode(int $id): JsonResponse
    {
        $code = ActivationCode::with('plan')->findOrFail($id);

        return $this->successResponse([
            'card' => [
                'serial_number' => $code->serial_number ?? sprintf('SAT-%06d', $code->id),
                'display_code' => $code->code,
                'plan_name' => $code->plan?->name ?? 'SATA Security Plan',
                'plan_code' => $code->plan?->code ?? 'N/A',
                'duration_days' => $code->plan?->duration_days ?? 30,
                'status' => $code->status,
                'instructions' => 'Open SATA Mobile App -> Activation -> Enter the 16-character code above to protect your device.',
            ],
        ], 'Printable voucher prepared.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $code = ActivationCode::findOrFail($id);
        $before = $code->only(['id', 'code', 'status']);
        $code->delete();

        $this->auditLogService->log(
            $request->user(),
            'ACTIVATION_CODE_DELETED',
            'ACTIVATION_CODES',
            null,
            $before,
            null
        );

        return $this->successResponse(null, 'Activation code deleted successfully.');
    }
}
