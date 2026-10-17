<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivationCodeBatch;
use App\Models\ActivationPlan;
use App\Services\ActivationService;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminBatchController extends Controller
{
    public function __construct(
        protected ActivationService $activationService,
        protected AuditLogService $auditLogService
    ) {}

    public function index(): JsonResponse
    {
        $batches = ActivationCodeBatch::with('plan')
            ->withCount([
                'codes',
                'codes as redeemed_codes_count' => function ($query) {
                    $query->where('is_redeemed', true);
                },
            ])
            ->latest()
            ->paginate(15);

        return $this->successResponse($batches);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'activation_plan_id' => ['required', 'integer', 'exists:activation_plans,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:5000'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $plan = ActivationPlan::findOrFail($request->input('activation_plan_id'));
        $batch = $this->activationService->generateBatch(
            $plan,
            $request->input('quantity'),
            $request->user(),
            $request->input('notes')
        );

        $this->auditLogService->log(
            $request->user(),
            'BATCH_GENERATED',
            'ACTIVATION',
            $batch,
            null,
            ['batch_number' => $batch->batch_number, 'quantity' => $batch->quantity]
        );

        return $this->successResponse([
            'batch' => $batch->load('codes'),
        ], 'Batch generated successfully.', 201);
    }

    public function show(int $id): JsonResponse
    {
        $batch = ActivationCodeBatch::with(['plan', 'admin:id,name,email', 'codes'])->find($id);

        if (! $batch) {
            return $this->errorResponse('Batch not found.', null, 404);
        }

        return $this->successResponse(['batch' => $batch]);
    }
}
