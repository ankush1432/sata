<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SecurityCommandType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\AuditLogService;
use App\Services\SecurityCommandService;
use App\Services\SubscriptionService;
use App\Support\SecuritySanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminSecurityCommandController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected SecurityCommandService $commandService,
        protected SubscriptionService $subscriptionService
    ) {}

    /**
     * List all security commands with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        $query = SecurityCommand::with([
            'user:id,name,email',
            'device:id,device_uuid,device_name,device_model,platform,status,device_management_mode',
            'requestedByAdmin:id,name,email,role'
        ]);

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('command_type')) {
            $query->where('command_type', $request->input('command_type'));
        }

        $commands = $query->latest()->paginate($request->input('per_page', 25));

        return $this->successResponse($commands);
    }

    /**
     * Show a single security command by ID or UUID.
     * Enforces command expiration.
     */
    public function show(Request $request, string|int $command): JsonResponse
    {
        $cmd = is_numeric($command)
            ? SecurityCommand::with(['user', 'device', 'requestedByAdmin', 'commandLogs', 'logs'])->find((int) $command)
            : SecurityCommand::with(['user', 'device', 'requestedByAdmin', 'commandLogs', 'logs'])->where('command_uuid', $command)->first();

        if (! $cmd) {
            return response()->json([
                'success' => false,
                'message' => 'Security command not found.',
                'error_code' => 'COMMAND_NOT_FOUND',
            ], 404);
        }

        // Enforce expiration check on read
        if ($cmd->expires_at && now()->greaterThan($cmd->expires_at)) {
            $terminalStates = [
                CommandStatus::COMPLETED,
                CommandStatus::FAILED,
                CommandStatus::EXPIRED,
                CommandStatus::UNSUPPORTED,
            ];

            if (! in_array($cmd->status, $terminalStates, true)) {
                $oldStatus = $cmd->status;
                $cmd->update([
                    'status' => CommandStatus::EXPIRED,
                    'failure_reason' => 'Command expired before completion at ' . $cmd->expires_at->toIso8601String(),
                ]);

                SecurityCommandLog::create([
                    'security_command_id' => $cmd->id,
                    'status_before' => $oldStatus->value,
                    'status_after' => CommandStatus::EXPIRED->value,
                    'status_from' => $oldStatus->value,
                    'status_to' => CommandStatus::EXPIRED->value,
                    'message' => 'Command marked EXPIRED upon lifecycle verification.',
                    'recorded_at' => now(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'command' => $cmd->fresh(['user', 'device', 'requestedByAdmin', 'commandLogs']),
            ],
            'command' => $cmd->fresh(['user', 'device', 'requestedByAdmin', 'commandLogs']),
        ]);
    }

    /**
     * List all security commands for a specific user and device.
     * GET /admin/users/{user}/devices/{device}/commands
     */
    public function deviceCommands(Request $request, string|int $user, string|int $device): JsonResponse
    {
        $resolvedUser = is_numeric($user)
            ? User::find((int) $user)
            : User::where('id', $user)->orWhere('uuid', $user)->first();

        if (! $resolvedUser) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
                'error_code' => 'USER_NOT_FOUND',
            ], 404);
        }

        $resolvedDevice = is_numeric($device)
            ? UserDevice::find((int) $device)
            : UserDevice::where('device_uuid', $device)->orWhere('id', $device)->first();

        if (! $resolvedDevice) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found.',
                'error_code' => 'DEVICE_NOT_FOUND',
            ], 404);
        }

        // Ownership verification
        if ((int) $resolvedDevice->user_id !== (int) $resolvedUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Device ownership mismatch: Target device does not belong to the specified user.',
                'error_code' => 'DEVICE_OWNERSHIP_MISMATCH',
            ], 403);
        }

        $commands = SecurityCommand::with(['requestedByAdmin:id,name,email,role'])
            ->where('device_id', $resolvedDevice->id)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $resolvedUser->id,
                    'name' => $resolvedUser->name,
                    'email' => $resolvedUser->email,
                ],
                'device' => [
                    'id' => $resolvedDevice->id,
                    'device_uuid' => $resolvedDevice->device_uuid,
                    'device_name' => $resolvedDevice->device_name,
                    'platform' => $resolvedDevice->platform,
                    'device_management_mode' => $resolvedDevice->device_management_mode ?? 'STANDARD_APP',
                    'status' => $resolvedDevice->status->value,
                ],
                'count' => $commands->count(),
                'commands' => $commands,
            ],
        ]);
    }

    /**
     * Dispatch a security command to a specific device.
     * POST /admin/users/{user}/devices/{device}/commands
     *
     * Exactly three categories supported:
     * 1. FACTORY_RESET
     * 2. KIOSK_LOCK / KIOSK_UNLOCK
     * 3. WIPE_DATA
     */
    public function storeForDevice(Request $request, string|int $user, string|int $device): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        // 1. Validation of request body
        $validator = Validator::make($request->all(), [
            'command_type' => [
                'required',
                'string',
                Rule::in([
                    SecurityCommandType::FACTORY_RESET->value,
                    SecurityCommandType::KIOSK_LOCK->value,
                    SecurityCommandType::KIOSK_UNLOCK->value,
                    SecurityCommandType::WIPE_DATA->value,
                ]),
            ],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'password' => ['nullable', 'string'],
            'admin_password' => ['nullable', 'string'],
            'reauth_password' => ['nullable', 'string'],
            'payload' => ['nullable', 'array'],
            'device_management_mode' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . $validator->errors()->first(),
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }

        $commandType = strtoupper(trim($request->input('command_type')));
        $reason = trim($request->input('reason'));

        // 2. Admin authorization check
        if (! $admin->canExecuteSecurityCommand($commandType)) {
            $this->auditLogService->log(
                $admin,
                'SECURITY_COMMAND_DENIED',
                'SECURITY_COMMANDS',
                null,
                null,
                [
                    'command_type' => $commandType,
                    'target_user' => $user,
                    'target_device' => $device,
                    'reason' => 'unauthorized_role_for_command',
                ],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Your admin role does not have authorization to execute this command.',
                'error_code' => 'COMMAND_EXECUTION_FORBIDDEN',
            ], 403);
        }

        // 3. Resolve User
        $resolvedUser = is_numeric($user)
            ? User::find((int) $user)
            : User::where('id', $user)->orWhere('uuid', $user)->first();

        if (! $resolvedUser) {
            return response()->json([
                'success' => false,
                'message' => 'Target user not found.',
                'error_code' => 'USER_NOT_FOUND',
            ], 404);
        }

        // 4. Resolve Device
        $resolvedDevice = is_numeric($device)
            ? UserDevice::find((int) $device)
            : UserDevice::where('device_uuid', $device)->orWhere('id', $device)->first();

        if (! $resolvedDevice) {
            return response()->json([
                'success' => false,
                'message' => 'Target device not found.',
                'error_code' => 'DEVICE_NOT_FOUND',
            ], 404);
        }

        // 5. Verify User / Device Ownership
        // IMPORTANT: Backend strictly derives user_id and device_id. Never trust frontend input.
        if ((int) $resolvedDevice->user_id !== (int) $resolvedUser->id) {
            $this->auditLogService->log(
                $admin,
                'SECURITY_COMMAND_OWNERSHIP_MISMATCH',
                'SECURITY_COMMANDS',
                null,
                null,
                [
                    'requested_user_id' => $resolvedUser->id,
                    'actual_device_user_id' => $resolvedDevice->user_id,
                    'device_id' => $resolvedDevice->id,
                    'command_type' => $commandType,
                ],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json([
                'success' => false,
                'message' => 'Device ownership mismatch: The specified device does not belong to the target user.',
                'error_code' => 'DEVICE_OWNERSHIP_MISMATCH',
            ], 403);
        }

        // 6. Verify Device Status (Revocation guard)
        if ($resolvedDevice->isRevoked() || $resolvedDevice->status === DeviceStatus::REVOKED) {
            return response()->json([
                'success' => false,
                'message' => 'Device has been revoked. Security commands cannot be dispatched to a revoked device.',
                'error_code' => 'DEVICE_REVOKED',
            ], 403);
        }

        // 7. Verify User Subscription Status
        $subDetails = $this->subscriptionService->getAuthoritativeSubscriptionDetails($resolvedUser);
        if (! ($subDetails['has_active_subscription'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Operation rejected: Target user subscription has expired or is inactive. Command dispatch blocked.',
                'error_code' => 'SUBSCRIPTION_EXPIRED',
                'subscription_state' => $subDetails['state'] ?? 'EXPIRED',
            ], 403);
        }

        // 8. Destructive command handling: Require re-authentication
        $isDestructive = in_array($commandType, [
            SecurityCommandType::FACTORY_RESET->value,
            SecurityCommandType::WIPE_DATA->value,
        ], true);

        if ($isDestructive) {
            $reauthPassword = $request->input('password')
                ?? $request->input('admin_password')
                ?? $request->input('reauth_password');

            if (empty($reauthPassword)) {
                return response()->json([
                    'success' => false,
                    'message' => "Re-authentication password is required before executing destructive command {$commandType}.",
                    'error_code' => 'REAUTHENTICATION_REQUIRED',
                ], 401);
            }

            if (! Hash::check($reauthPassword, $admin->password)) {
                $this->auditLogService->log(
                    $admin,
                    'SECURITY_COMMAND_REAUTH_FAILED',
                    'SECURITY_COMMANDS',
                    null,
                    null,
                    [
                        'command_type' => $commandType,
                        'user_id' => $resolvedUser->id,
                        'device_id' => $resolvedDevice->id,
                        'reason' => 'invalid_password',
                    ],
                    $request->ip(),
                    $request->userAgent()
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Re-authentication failed: Invalid admin password credentials.',
                    'error_code' => 'INVALID_REAUTHENTICATION_CREDENTIALS',
                ], 401);
            }

            $this->auditLogService->log(
                $admin,
                'SECURITY_COMMAND_REAUTH_VERIFIED',
                'SECURITY_COMMANDS',
                null,
                null,
                [
                    'command_type' => $commandType,
                    'user_id' => $resolvedUser->id,
                    'device_id' => $resolvedDevice->id,
                ],
                $request->ip(),
                $request->userAgent()
            );
        }

        // 9. Concurrency Protection & Duplicate in-flight check
        return DB::transaction(function () use (
            $request,
            $admin,
            $resolvedUser,
            $resolvedDevice,
            $commandType,
            $reason
        ) {
            // Lock device row to serialize concurrent command dispatch requests
            UserDevice::where('id', $resolvedDevice->id)->lockForUpdate()->first();

            $activeCommand = SecurityCommand::where('device_id', $resolvedDevice->id)
                ->where('command_type', $commandType)
                ->whereIn('status', [
                    CommandStatus::PENDING,
                    CommandStatus::SENT,
                    CommandStatus::ACKNOWLEDGED,
                    CommandStatus::EXECUTING,
                ])
                ->where(function ($q) {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->first();

            if ($activeCommand) {
                return response()->json([
                    'success' => false,
                    'message' => "Duplicate request: A {$commandType} command is already active and in progress for this device.",
                    'error_code' => 'DUPLICATE_COMMAND_REQUEST',
                    'active_command' => [
                        'command_uuid' => $activeCommand->command_uuid,
                        'status' => $activeCommand->status->value,
                        'sent_at' => $activeCommand->sent_at?->toIso8601String(),
                    ],
                ], 409);
            }

            // 10. Check Platform & Device Capability
            // Rule: Do not assume standard 3rd party app can perform factory reset or kiosk management.
            // Return UNSUPPORTED if device/platform does not support it. Never fake execution.
            $platform = strtoupper(trim($resolvedDevice->platform ?: 'ANDROID'));
            $mode = strtoupper(trim(
                $request->input('device_management_mode')
                ?: $resolvedDevice->device_management_mode
                ?: 'STANDARD_APP'
            ));

            $isSupported = $this->commandService->isCommandSupported($platform, $mode, $commandType);
            $capDetails = $this->commandService->getCommandCapabilityDetails($platform, $mode, $commandType);

            // Derive IDs exclusively from verified server models
            $commandUuid = (string) Str::uuid();
            $nonce = Str::random(32);
            $initialStatus = $isSupported ? CommandStatus::SENT : CommandStatus::UNSUPPORTED;

            $sanitizedPayload = SecuritySanitizer::sanitize(array_merge($request->input('payload', []), [
                'reason' => $reason,
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'target_user_email' => $resolvedUser->email,
                'target_device_uuid' => $resolvedDevice->device_uuid,
            ]));

            $command = SecurityCommand::create([
                'user_id' => $resolvedUser->id,
                'device_id' => $resolvedDevice->id,
                'command_uuid' => $commandUuid,
                'nonce' => $nonce,
                'command_type' => $commandType,
                'status' => $initialStatus,
                'device_management_mode' => $mode,
                'payload' => $sanitizedPayload,
                'requested_by_admin_id' => $admin->id,
                'requested_by_type' => 'ADMIN',
                'requested_by_id' => $admin->id,
                'issued_at' => now(),
                'sent_at' => now(),
                'expires_at' => now()->addHours(24),
                'failure_reason' => $isSupported ? null : "Command {$commandType} is UNSUPPORTED on platform {$platform} with mode {$mode}. " . ($capDetails['notes'] ?? ''),
                'response_payload' => $isSupported ? null : [
                    'supported' => false,
                    'platform' => $platform,
                    'mode' => $mode,
                    'capability_details' => $capDetails,
                    'never_faked' => true,
                ],
            ]);

            // Record status log
            SecurityCommandLog::create([
                'security_command_id' => $command->id,
                'status_before' => null,
                'status_after' => $initialStatus->value,
                'status_from' => null,
                'status_to' => $initialStatus->value,
                'message' => $isSupported
                    ? "Security command {$commandType} created by admin {$admin->email} and dispatched to secure channel."
                    : "Security command {$commandType} created by admin {$admin->email} and evaluated as UNSUPPORTED on platform {$platform} ({$mode}).",
                'metadata' => SecuritySanitizer::sanitize([
                    'command_type' => $commandType,
                    'reason' => $reason,
                    'platform' => $platform,
                    'mode' => $mode,
                    'supported' => $isSupported,
                ]),
                'recorded_at' => now(),
            ]);

            $auditSummary = "Admin {$admin->name} ({$admin->id}) → {$commandType} → User {$resolvedUser->name} ({$resolvedUser->id}) → Device {$resolvedDevice->device_uuid} ({$resolvedDevice->id}) → " . ($isSupported ? 'SUCCESS' : 'UNSUPPORTED') . " → " . now()->toIso8601String();

            // Audit log
            $this->auditLogService->log(
                $admin,
                $isSupported ? 'SECURITY_COMMAND_DISPATCHED' : 'SECURITY_COMMAND_UNSUPPORTED',
                'SECURITY_COMMANDS',
                $command,
                null,
                [
                    'audit_summary' => $auditSummary,
                    'command_uuid' => $command->command_uuid,
                    'command_type' => $commandType,
                    'user_id' => $resolvedUser->id,
                    'device_id' => $resolvedDevice->id,
                    'admin_id' => $admin->id,
                    'admin_name' => $admin->name,
                    'user_name' => $resolvedUser->name,
                    'device_uuid' => $resolvedDevice->device_uuid,
                    'result' => $isSupported ? 'SUCCESS' : 'UNSUPPORTED',
                    'reason' => $reason,
                    'status' => $initialStatus->value,
                    'platform' => $platform,
                    'mode' => $mode,
                    'supported' => $isSupported,
                    'timestamp' => now()->toIso8601String(),
                ],
                $request->ip(),
                $request->userAgent()
            );

            $httpCode = 201;

            return response()->json([
                'success' => true,
                'status' => $initialStatus->value,
                'supported' => $isSupported,
                'message' => $isSupported
                    ? "Security command {$commandType} created and queued for delivery through secure channel."
                    : "Command {$commandType} is UNSUPPORTED on device platform {$platform} with management mode {$mode}. Status recorded as UNSUPPORTED. Never faked.",
                'data' => [
                    'command' => $command->fresh(),
                    'target_user' => [
                        'id' => $resolvedUser->id,
                        'name' => $resolvedUser->name,
                        'email' => $resolvedUser->email,
                    ],
                    'target_device' => [
                        'id' => $resolvedDevice->id,
                        'device_uuid' => $resolvedDevice->device_uuid,
                        'device_name' => $resolvedDevice->device_name,
                        'platform' => $resolvedDevice->platform,
                        'device_management_mode' => $mode,
                    ],
                    'capability_details' => $capDetails,
                ],
                'command' => $command->fresh(),
            ], $httpCode);
        });
    }

    /**
     * Transition command status through authoritative lifecycle.
     * POST /admin/security-commands/{command}/transition
     *
     * Valid statuses:
     * PENDING -> SENT -> ACKNOWLEDGED -> EXECUTING -> COMPLETED | FAILED | EXPIRED | UNSUPPORTED
     */
    public function transitionStatus(Request $request, string|int $command): JsonResponse
    {
        /** @var Admin $admin */
        $admin = $request->user();

        $cmd = is_numeric($command)
            ? SecurityCommand::find((int) $command)
            : SecurityCommand::where('command_uuid', $command)->first();

        if (! $cmd) {
            return response()->json([
                'success' => false,
                'message' => 'Security command not found.',
                'error_code' => 'COMMAND_NOT_FOUND',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'string', Rule::in(CommandStatus::values())],
            'message' => ['nullable', 'string'],
            'failure_reason' => ['nullable', 'string'],
            'response_payload' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
                'error_code' => 'VALIDATION_FAILED',
            ], 422);
        }

        $targetStatus = CommandStatus::from($request->input('status'));
        $currentStatus = $cmd->status;

        // Terminal statuses cannot transition further
        $terminalStates = [
            CommandStatus::COMPLETED,
            CommandStatus::FAILED,
            CommandStatus::EXPIRED,
            CommandStatus::UNSUPPORTED,
        ];

        if (in_array($currentStatus, $terminalStates, true)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid transition: Command has already reached terminal status {$currentStatus->value} and cannot be transitioned.",
                'error_code' => 'COMMAND_ALREADY_TERMINATED',
                'current_status' => $currentStatus->value,
            ], 422);
        }

        // Validate lifecycle transition rules
        if (!$cmd->canTransitionTo($targetStatus)) {
            return response()->json([
                'success' => false,
                'message' => "Invalid transition: Transition from {$currentStatus->value} to {$targetStatus->value} is not permitted by lifecycle rules.",
                'error_code' => 'INVALID_STATUS_TRANSITION',
                'current_status' => $currentStatus->value,
                'target_status' => $targetStatus->value,
            ], 422);
        }

        // Check expiration
        if ($cmd->expires_at && now()->greaterThan($cmd->expires_at) && $targetStatus !== CommandStatus::EXPIRED) {
            $cmd->update([
                'status' => CommandStatus::EXPIRED,
                'failure_reason' => 'Command expired before status transition at ' . $cmd->expires_at->toIso8601String(),
            ]);

            SecurityCommandLog::create([
                'security_command_id' => $cmd->id,
                'status_before' => $currentStatus->value,
                'status_after' => CommandStatus::EXPIRED->value,
                'status_from' => $currentStatus->value,
                'status_to' => CommandStatus::EXPIRED->value,
                'message' => 'Command transitioned to EXPIRED due to exceeded expiry threshold.',
                'recorded_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Command has expired and cannot be processed further.',
                'error_code' => 'COMMAND_EXPIRED',
                'command' => $cmd->fresh(),
            ], 422);
        }

        $updates = [
            'status' => $targetStatus,
        ];

        if ($targetStatus === CommandStatus::ACKNOWLEDGED && empty($cmd->acknowledged_at)) {
            $updates['acknowledged_at'] = now();
        }

        if (in_array($targetStatus, [CommandStatus::COMPLETED, CommandStatus::FAILED], true)) {
            $updates['executed_at'] = now();
        }

        if ($request->filled('failure_reason')) {
            $updates['failure_reason'] = $request->input('failure_reason');
        }

        if ($request->filled('response_payload')) {
            $updates['response_payload'] = SecuritySanitizer::sanitize($request->input('response_payload'));
        }

        $cmd->update($updates);

        $logMessage = $request->input('message')
            ?: "Command {$cmd->command_uuid} status transitioned from {$currentStatus->value} to {$targetStatus->value}.";

        SecurityCommandLog::create([
            'security_command_id' => $cmd->id,
            'status_before' => $currentStatus->value,
            'status_after' => $targetStatus->value,
            'status_from' => $currentStatus->value,
            'status_to' => $targetStatus->value,
            'message' => $logMessage,
            'metadata' => SecuritySanitizer::sanitize([
                'transitioned_by_admin_id' => $admin->id,
                'requested_status' => $targetStatus->value,
            ]),
            'recorded_at' => now(),
        ]);

        $auditSummary = "Admin {$admin->name} ({$admin->id}) → {$cmd->command_type->value} → User {$cmd->user?->name} ({$cmd->user_id}) → Device {$cmd->device?->device_uuid} ({$cmd->device_id}) → " . ($targetStatus === CommandStatus::COMPLETED ? 'SUCCESS' : $targetStatus->value) . " → " . now()->toIso8601String();

        $this->auditLogService->log(
            $admin,
            'SECURITY_COMMAND_STATUS_TRANSITIONED',
            'SECURITY_COMMANDS',
            $cmd,
            null,
            [
                'audit_summary' => $auditSummary,
                'command_uuid' => $cmd->command_uuid,
                'from_status' => $currentStatus->value,
                'to_status' => $targetStatus->value,
                'admin_id' => $admin->id,
                'admin_name' => $admin->name,
                'user_id' => $cmd->user_id,
                'device_id' => $cmd->device_id,
                'command_type' => $cmd->command_type->value,
                'result' => $targetStatus === CommandStatus::COMPLETED ? 'SUCCESS' : $targetStatus->value,
                'timestamp' => now()->toIso8601String(),
            ],
            $request->ip(),
            $request->userAgent()
        );

        return response()->json([
            'success' => true,
            'message' => "Security command status updated to {$targetStatus->value}.",
            'command' => $cmd->fresh(['commandLogs']),
            'data' => [
                'command' => $cmd->fresh(['commandLogs']),
            ],
        ]);
    }

    /**
     * Backwards-compatible execution endpoint.
     * POST /admin/security-commands
     */
    public function execute(Request $request): JsonResponse
    {
        $deviceId = $request->input('device_id');
        $device = is_numeric($deviceId)
            ? UserDevice::find((int) $deviceId)
            : UserDevice::where('device_uuid', $deviceId)->first();

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found.',
                'error_code' => 'DEVICE_NOT_FOUND',
            ], 404);
        }

        $admin = $request->user();
        $commandType = strtoupper(trim((string) $request->input('command_type')));

        if (! $admin->canExecuteSecurityCommand($commandType)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Your admin role does not have authorization to execute this command.',
                'error_code' => 'COMMAND_EXECUTION_FORBIDDEN',
            ], 403);
        }

        $command = $this->commandService->dispatchCommand(
            $device,
            SecurityCommandType::from($commandType),
            $request->input('payload', []) ?: [],
            $admin
        );

        return response()->json([
            'success' => true,
            'message' => "Security command {$commandType} dispatched successfully.",
            'data' => [
                'command_uuid' => $command->command_uuid,
                'command_id' => $command->id,
                'status' => $command->status->value,
                'command_type' => $command->command_type->value,
                'command' => [
                    'id' => $command->id,
                    'command_uuid' => $command->command_uuid,
                    'command_type' => $command->command_type->value,
                    'device_id' => $device->id,
                    'status' => $command->status->value,
                ],
            ],
        ], 201);
    }
}
