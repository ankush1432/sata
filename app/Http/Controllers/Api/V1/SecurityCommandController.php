<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SecurityCommandType;
use App\Http\Controllers\Controller;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use App\Models\UserDevice;
use App\Services\SecurityCommandService;
use App\Support\SecuritySanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SecurityCommandController extends Controller
{
    public function __construct(
        protected SecurityCommandService $commandService
    ) {}

    /**
     * Get authoritative Platform Capability Matrix.
     */
    public function capabilities(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Security command platform capability matrix retrieved.',
            'data' => [
                'capability_matrix' => $this->commandService->getCapabilityMatrix(),
                'rules' => [
                    'Never claim commands are supported unless the device/platform actually supports them.',
                    'FCM acts as delivery/wake-up mechanism only, never the sole authorization mechanism.',
                    'Never execute a command directly from an untrusted notification payload.',
                    'Never fake Factory Reset, Kiosk Lock, Kiosk Unlock, or Wipe Data.',
                    'Unsupported commands return status UNSUPPORTED.',
                ],
            ],
        ]);
    }

    /**
     * List pending commands for authenticated device.
     */
    public function pending(Request $request): JsonResponse
    {
        $user = $request->user();
        $device = $this->resolveDevice($request);

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'No active device identified for this user.',
            ], 404);
        }

        $commands = SecurityCommand::where('device_id', $device->id)
            ->whereIn('status', [CommandStatus::PENDING, CommandStatus::SENT])
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'device_id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'count' => $commands->count(),
                'commands' => $commands,
            ],
        ]);
    }

    /**
     * Fetch command by UUID for device (authenticated pull following FCM wake-up).
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $command = SecurityCommand::where('command_uuid', $uuid)->first();

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Security command not found.',
                'error_code' => 'COMMAND_NOT_FOUND',
            ], 404);
        }

        // Authorize: Command must belong to the user's device
        $device = UserDevice::find($command->device_id);
        if (!$device || (int)$device->user_id !== (int)$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: You do not own the target device for this command.',
                'error_code' => 'FORBIDDEN_DEVICE_OWNERSHIP',
            ], 403);
        }

        // Expiration check on pull
        if ($command->isExpired() && $command->status !== CommandStatus::EXPIRED && !$command->isTerminal()) {
            $command->update([
                'status' => CommandStatus::EXPIRED,
                'failure_reason' => 'Command expired before pull/execution.',
            ]);

            SecurityCommandLog::create([
                'security_command_id' => $command->id,
                'status_before' => $command->status->value,
                'status_after' => CommandStatus::EXPIRED->value,
                'status_from' => $command->status->value,
                'status_to' => CommandStatus::EXPIRED->value,
                'message' => 'Command expired before pull/execution.',
                'recorded_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'command' => [
                    'id' => $command->id,
                    'command_uuid' => $command->command_uuid,
                    'nonce' => $command->nonce,
                    'user_id' => $command->user_id,
                    'device_id' => $command->device_id,
                    'command_type' => $command->command_type->value,
                    'requested_by_admin_id' => $command->requested_by_admin_id,
                    'issued_at' => $command->issued_at?->toIso8601String(),
                    'expires_at' => $command->expires_at?->toIso8601String(),
                    'status' => $command->status->value,
                    'acknowledged_at' => $command->acknowledged_at?->toIso8601String(),
                    'executed_at' => $command->executed_at?->toIso8601String(),
                    'device_management_mode' => $command->device_management_mode,
                    'payload' => $command->payload,
                    'failure_reason' => $command->failure_reason,
                    'response_payload' => $command->response_payload,
                ],
                'capability' => $this->commandService->getCommandCapabilityDetails(
                    $device->platform ?? 'ANDROID',
                    $request->input('device_management_mode', 'STANDARD_APP'),
                    $command->command_type
                ),
            ],
        ]);
    }

    /**
     * Step 9: Acknowledge receipt of command.
     */
    public function acknowledge(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $command = SecurityCommand::where('command_uuid', $uuid)->first();

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Security command not found.',
                'error_code' => 'COMMAND_NOT_FOUND',
            ], 404);
        }

        $device = $this->resolveDevice($request);
        if (!$device || (int)$command->device_id !== (int)$device->id || (int)$device->user_id !== (int)$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Device ID mismatch.',
                'error_code' => 'DEVICE_MISMATCH',
            ], 403);
        }

        // Check if device is revoked
        if ($device->isRevoked() || $device->status === DeviceStatus::REVOKED) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Device has been revoked and cannot acknowledge commands.',
                'error_code' => 'DEVICE_REVOKED',
            ], 403);
        }

        // Check expiration
        if ($command->isExpired() || ($command->expires_at && now()->greaterThan($command->expires_at))) {
            $command->update([
                'status' => CommandStatus::EXPIRED,
                'failure_reason' => 'Command expired before acknowledgement at ' . $command->expires_at?->toIso8601String(),
            ]);

            SecurityCommandLog::create([
                'security_command_id' => $command->id,
                'status_before' => $command->status->value,
                'status_after' => CommandStatus::EXPIRED->value,
                'status_from' => $command->status->value,
                'status_to' => CommandStatus::EXPIRED->value,
                'message' => 'Command expired before acknowledgement.',
                'recorded_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Command has expired and cannot be acknowledged.',
                'error_code' => 'COMMAND_EXPIRED',
            ], 422);
        }

        // Idempotency: Already acknowledged
        if ($command->status === CommandStatus::ACKNOWLEDGED) {
            return response()->json([
                'success' => true,
                'message' => 'Command already acknowledged (idempotent).',
                'data' => [
                    'command_uuid' => $command->command_uuid,
                    'nonce' => $command->nonce,
                    'user_id' => $command->user_id,
                    'device_id' => $command->device_id,
                    'command_type' => $command->command_type->value,
                    'requested_by_admin_id' => $command->requested_by_admin_id,
                    'issued_at' => $command->issued_at?->toIso8601String(),
                    'expires_at' => $command->expires_at?->toIso8601String(),
                    'status' => $command->status->value,
                    'acknowledged_at' => $command->acknowledged_at?->toIso8601String(),
                ],
            ]);
        }

        // One-time execution & replay check: If in terminal state, reject replay
        if ($command->isTerminal()) {
            return response()->json([
                'success' => false,
                'message' => "Replay protection: Command has already reached terminal status {$command->status->value}. Replay rejected.",
                'error_code' => 'REPLAY_ATTEMPT_REJECTED',
            ], 422);
        }

        // Validate lifecycle transition
        if (!$command->canTransitionTo(CommandStatus::ACKNOWLEDGED)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from {$command->status->value} to ACKNOWLEDGED.",
                'error_code' => 'INVALID_STATUS_TRANSITION',
            ], 422);
        }

        $oldStatus = $command->status;
        $command->acknowledged_at = now();
        $command->status = CommandStatus::ACKNOWLEDGED;
        $command->save();

        SecurityCommandLog::create([
            'security_command_id' => $command->id,
            'status_before' => $oldStatus->value,
            'status_after' => CommandStatus::ACKNOWLEDGED->value,
            'status_from' => $oldStatus->value,
            'status_to' => CommandStatus::ACKNOWLEDGED->value,
            'message' => "Command {$command->command_uuid} acknowledged by device {$device->device_uuid}.",
            'recorded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Command acknowledged.',
            'data' => [
                'command_uuid' => $command->command_uuid,
                'nonce' => $command->nonce,
                'user_id' => $command->user_id,
                'device_id' => $command->device_id,
                'command_type' => $command->command_type->value,
                'requested_by_admin_id' => $command->requested_by_admin_id,
                'issued_at' => $command->issued_at?->toIso8601String(),
                'expires_at' => $command->expires_at?->toIso8601String(),
                'status' => $command->status->value,
                'acknowledged_at' => $command->acknowledged_at->toIso8601String(),
            ],
        ]);
    }

    /**
     * Execute 10-Step Secure Command Receiver Pipeline.
     * Evaluates platform capability, never fakes unsupported commands, returns PASS/FAIL.
     */
    public function process(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        // 1. Authenticate command & resolve device
        $command = SecurityCommand::where('command_uuid', $uuid)->first();
        if (!$command) {
            return response()->json([
                'result' => 'FAIL',
                'status' => CommandStatus::FAILED->value,
                'step_failed' => 3,
                'message' => 'Command UUID not found.',
                'capability_matrix' => $this->commandService->getCapabilityMatrix(),
            ], 404);
        }

        $device = $this->resolveDevice($request, $command->device_id);
        if (!$device || (int)$device->user_id !== (int)$user->id) {
            return response()->json([
                'result' => 'FAIL',
                'status' => CommandStatus::FAILED->value,
                'step_failed' => 2,
                'message' => 'Forbidden: Requesting device does not match authorized owner.',
                'capability_matrix' => $this->commandService->getCapabilityMatrix(),
            ], 403);
        }

        if ($device->isRevoked() || $device->status === DeviceStatus::REVOKED) {
            return response()->json([
                'result' => 'FAIL',
                'status' => CommandStatus::FAILED->value,
                'step_failed' => 6,
                'message' => 'Forbidden: Device has been revoked and cannot process commands.',
                'capability_matrix' => $this->commandService->getCapabilityMatrix(),
            ], 403);
        }

        // Gather client context (platform, management mode, nonce)
        $clientContext = [
            'platform' => $request->input('platform', $device->platform ?? 'ANDROID'),
            'device_management_mode' => $request->input('device_management_mode', $request->input('mode', 'STANDARD_APP')),
            'nonce' => $request->input('nonce'),
        ];

        $pipelineResult = $this->commandService->validateAndProcessCommand($device, $uuid, $clientContext);

        $httpStatus = 200;
        if ($pipelineResult['result'] === 'FAIL') {
            if ($pipelineResult['status'] === CommandStatus::UNSUPPORTED->value) {
                // Return 200 with result: FAIL, status: UNSUPPORTED
                $httpStatus = 200;
            } elseif ($pipelineResult['step_failed'] === 2 || $pipelineResult['step_failed'] === 6) {
                $httpStatus = 403;
            } elseif ($pipelineResult['step_failed'] === 3) {
                $httpStatus = 404;
            } elseif ($pipelineResult['step_failed'] === 4 || $pipelineResult['step_failed'] === 5) {
                $httpStatus = 422;
            }
        }

        return response()->json($pipelineResult, $httpStatus);
    }

    /**
     * Step 10: Report execution result from client.
     */
    public function reportResult(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in([
                CommandStatus::COMPLETED->value,
                CommandStatus::FAILED->value,
                CommandStatus::UNSUPPORTED->value,
            ])],
            'response_payload' => ['nullable', 'array'],
            'failure_reason' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $command = SecurityCommand::where('command_uuid', $uuid)->first();
        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Command not found.',
                'error_code' => 'COMMAND_NOT_FOUND',
            ], 404);
        }

        $device = $this->resolveDevice($request, $command->device_id);
        if (!$device || (int)$command->device_id !== (int)$device->id || (int)$device->user_id !== (int)$user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Device mismatch.',
                'error_code' => 'DEVICE_MISMATCH',
            ], 403);
        }

        if ($device->isRevoked() || $device->status === DeviceStatus::REVOKED) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Device has been revoked.',
                'error_code' => 'DEVICE_REVOKED',
            ], 403);
        }

        // Check expiration
        if ($command->isExpired() || ($command->expires_at && now()->greaterThan($command->expires_at))) {
            $command->update([
                'status' => CommandStatus::EXPIRED,
                'failure_reason' => 'Command expired before execution report at ' . $command->expires_at?->toIso8601String(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Command has expired.',
                'error_code' => 'COMMAND_EXPIRED',
            ], 422);
        }

        // One-time execution: If already in terminal state, reject replay
        if ($command->isTerminal()) {
            return response()->json([
                'success' => false,
                'message' => "Replay protection: Command has already reached terminal status {$command->status->value}. One-time execution enforced.",
                'error_code' => 'REPLAY_ATTEMPT_REJECTED',
            ], 422);
        }

        $targetStatus = CommandStatus::from($request->input('status'));

        // Validate lifecycle transition
        if (!$command->canTransitionTo($targetStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid status transition from {$command->status->value} to {$targetStatus->value}.",
                'error_code' => 'INVALID_STATUS_TRANSITION',
            ], 422);
        }

        $oldStatus = $command->status;
        $sanitizedPayload = SecuritySanitizer::sanitize($request->input('response_payload', []));

        $command->status = $targetStatus;
        $command->executed_at = now();
        $command->response_payload = $sanitizedPayload;
        $command->failure_reason = $request->input('failure_reason');
        $command->save();

        SecurityCommandLog::create([
            'security_command_id' => $command->id,
            'status_before' => $oldStatus->value,
            'status_after' => $targetStatus->value,
            'status_from' => $oldStatus->value,
            'status_to' => $targetStatus->value,
            'message' => "Command execution result reported: {$targetStatus->value}.",
            'metadata' => SecuritySanitizer::sanitize([
                'failure_reason' => $command->failure_reason,
            ]),
            'recorded_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Command status updated to {$targetStatus->value}.",
            'data' => [
                'command_uuid' => $command->command_uuid,
                'nonce' => $command->nonce,
                'user_id' => $command->user_id,
                'device_id' => $command->device_id,
                'command_type' => $command->command_type->value,
                'requested_by_admin_id' => $command->requested_by_admin_id,
                'issued_at' => $command->issued_at?->toIso8601String(),
                'expires_at' => $command->expires_at?->toIso8601String(),
                'status' => $command->status->value,
                'executed_at' => $command->executed_at->toIso8601String(),
                'failure_reason' => $command->failure_reason,
            ],
        ]);
    }

    /**
     * Resolve target UserDevice from request or user.
     */
    protected function resolveDevice(Request $request, ?int $preferredDeviceId = null): ?UserDevice
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($preferredDeviceId) {
            return UserDevice::where('id', $preferredDeviceId)->where('user_id', $user->id)->first();
        }

        $requestedDeviceId = $request->input('device_id')
            ?? $request->header('X-Device-Id')
            ?? $request->header('X-Device-UUID');

        if ($requestedDeviceId) {
            if (is_numeric($requestedDeviceId)) {
                $dev = UserDevice::where('id', (int)$requestedDeviceId)->where('user_id', $user->id)->first();
            } else {
                $dev = UserDevice::where('device_uuid', $requestedDeviceId)->where('user_id', $user->id)->first();
            }
            if ($dev) {
                return $dev;
            }
        }

        return $user->activeDevice ?? $user->devices()->first();
    }
}
