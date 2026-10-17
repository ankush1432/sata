<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\SecurityCommand;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\AuditLogService;
use App\Services\LocationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
        protected LocationService $locationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = User::with(['activeSubscription.plan', 'currentDevice'])
            ->withCount(['devices', 'trustedUsers', 'securityEvents'])
            ->latest()
            ->paginate($request->input('per_page', 20));

        return $this->successResponse($users);
    }

    /**
     * Phase 14: Main Admin User Detail / Security Control Center
     * GET /admin/users/{user}
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::with(['subscriptions.plan', 'devices', 'trustedUsers'])->find((int) $id)
            : User::with(['subscriptions.plan', 'devices', 'trustedUsers'])->where('id', $id)->orWhere('uuid', $id)->first();

        if (! $user) {
            return $this->errorResponse('User not found.', null, 404);
        }

        $admin = $request->user();

        // Audit sensitive access to user control center
        if ($admin) {
            $this->auditLogService->log(
                $admin,
                'USER_SECURITY_CONTROL_CENTER_VIEWED',
                'USERS',
                $user,
                null,
                [
                    'target_user_id' => $user->id,
                    'target_user_email' => $user->email,
                    'section' => 'CONTROL_CENTER_DASHBOARD',
                ],
                $request->ip(),
                $request->userAgent()
            );
        }

        // 1. USER
        $userData = [
            'id' => $user->id,
            'uuid' => $user->uuid,
            'name' => $user->name,
            'phone' => $user->phone_number ?? $user->mobile ?? $user->phone ?? 'Not specified',
            'email' => $user->email,
            'status' => $user->status instanceof UserStatus ? $user->status->value : (string) $user->status,
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        // 2. SUBSCRIPTION
        $subscription = $user->activeSubscription ?? $user->subscriptions()->with('plan')->latest()->first();
        $subscriptionData = [
            'plan' => $subscription?->plan?->name ?? 'Free Tier / Inactive',
            'plan_code' => $subscription?->plan?->code ?? 'FREE',
            'status' => $subscription?->status?->value ?? (string) ($subscription?->status ?? 'INACTIVE'),
            'activated_at' => $subscription?->starts_at?->toIso8601String() ?? $subscription?->created_at?->toIso8601String(),
            'expires_at' => $subscription?->expires_at?->toIso8601String(),
            'is_active' => $subscription ? $subscription->isActive() : false,
        ];

        // 3. DEVICE
        $primaryDevice = $user->activeDevice ?? $user->currentDevice ?? $user->devices()->latest()->first();
        $deviceData = $primaryDevice ? [
            'device_id' => $primaryDevice->device_uuid,
            'id' => $primaryDevice->id,
            'model' => $primaryDevice->device_model ?: ($primaryDevice->model ?: ($primaryDevice->device_name ?: 'Generic Device')),
            'device_name' => $primaryDevice->device_name,
            'platform' => strtoupper($primaryDevice->platform ?: 'ANDROID'),
            'os_version' => $primaryDevice->os_version ?: 'Android 14',
            'app_version' => $primaryDevice->app_version ?: 'v2.4.1',
            'device_management_mode' => $primaryDevice->device_management_mode ?: 'STANDARD_APP',
            'status' => $primaryDevice->status instanceof \App\Enums\DeviceStatus ? $primaryDevice->status->value : (string) $primaryDevice->status,
            'last_seen' => $primaryDevice->last_seen_at?->toIso8601String() ?? $primaryDevice->updated_at?->toIso8601String(),
            'is_protection_enabled' => (bool) $primaryDevice->is_protection_enabled,
            'all_devices_count' => $user->devices->count(),
        ] : null;

        // 4. LIVE LOCATION
        $locationInfo = $this->locationService->formatUserLocation($user);
        $shortUrl = $locationInfo['short_url'] ?? $locationInfo['short_location_reference_url'] ?? null;
        $liveLocationData = [
            'latitude' => $locationInfo['latitude'],
            'longitude' => $locationInfo['longitude'],
            'accuracy' => $locationInfo['accuracy'],
            'last_updated' => $locationInfo['last_updated'],
            'map_preview' => $locationInfo['map_link'],
            'map_url' => $locationInfo['map_link'],
            'open_map_button' => $locationInfo['map_link'],
            'short_location_url' => $shortUrl,
            'short_url' => $shortUrl,
            'copy_short_url' => $shortUrl,
            'has_active_session' => ! empty($locationInfo['active_session']),
        ];

        // 5. LATEST INTRUDER
        $latestIntruderEvent = SecurityEvent::where('user_id', $user->id)
            ->where(function ($q) {
                $q->whereIn('event_type', ['INTRUDER_PHOTO', 'INTRUDER_DETECTED', 'UNAUTHORIZED_ACCESS', 'SIM_CHANGED', 'WRONG_PIN_ATTEMPTS'])
                    ->orWhereHas('evidence');
            })
            ->with(['evidence', 'device', 'locations'])
            ->latest('occurred_at')
            ->first();

        $latestIntruderData = null;
        if ($latestIntruderEvent) {
            $evidenceItem = $latestIntruderEvent->evidence->first();
            $eventLoc = $latestIntruderEvent->locations->first();
            $lat = $eventLoc?->latitude ?? $latestIntruderEvent->latitude ?? $locationInfo['latitude'];
            $lng = $eventLoc?->longitude ?? $latestIntruderEvent->longitude ?? $locationInfo['longitude'];
            $mapLink = ($lat && $lng) ? "https://maps.google.com/?q={$lat},{$lng}" : null;

            $latestIntruderData = [
                'event_id' => $latestIntruderEvent->id,
                'event_uuid' => $latestIntruderEvent->event_uuid,
                'event_type' => $latestIntruderEvent->event_type->value ?? (string) $latestIntruderEvent->event_type,
                'event_time' => $latestIntruderEvent->occurred_at?->toIso8601String() ?? $latestIntruderEvent->created_at->toIso8601String(),
                'photo_preview' => $evidenceItem ? "/admin/evidence/{$evidenceItem->id}/stream" : null,
                'location' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'accuracy' => $eventLoc?->accuracy ?? $latestIntruderEvent->accuracy,
                    'address' => $eventLoc?->address ?? ($lat ? "Coordinates: {$lat}, {$lng}" : 'Unknown location'),
                ],
                'view_photo' => $evidenceItem ? "/admin/evidence/{$evidenceItem->id}" : null,
                'open_location' => $mapLink,
                'severity' => $latestIntruderEvent->severity,
                'status' => $latestIntruderEvent->status,
            ];
        }

        // 6. TRUSTED USERS (Maximum 2)
        $trustedUsers = $user->trustedUsers()->orderBy('id', 'asc')->limit(2)->get()->map(function ($tu) {
            return [
                'id' => $tu->id,
                'name' => $tu->name,
                'phone' => $tu->phone ?? $tu->phone_number,
                'verification_status' => $tu->status instanceof \App\Enums\TrustedUserStatus
                    ? $tu->status->value
                    : ($tu->verified_at ? 'VERIFIED' : 'PENDING'),
                'relationship' => $tu->relationship ?? 'Emergency Contact',
                'is_active' => (bool) $tu->is_active,
                'verified_at' => $tu->verified_at?->toIso8601String(),
            ];
        })->values()->all();

        // 7. SECURITY COMMANDS
        $recentCommands = SecurityCommand::where('user_id', $user->id)
            ->with('requestedByAdmin:id,name,email')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(function ($cmd) {
                return [
                    'id' => $cmd->id,
                    'command_uuid' => $cmd->command_uuid,
                    'command_type' => $cmd->command_type->value ?? (string) $cmd->command_type,
                    'status' => $cmd->status->value ?? (string) $cmd->status,
                    'device_id' => $cmd->device_id,
                    'issued_at' => $cmd->issued_at?->toIso8601String() ?? $cmd->sent_at?->toIso8601String(),
                    'expires_at' => $cmd->expires_at?->toIso8601String(),
                    'acknowledged_at' => $cmd->acknowledged_at?->toIso8601String(),
                    'executed_at' => $cmd->executed_at?->toIso8601String(),
                    'failure_reason' => $cmd->failure_reason,
                    'requested_by' => $cmd->requestedByAdmin?->name,
                ];
            });

        $securityCommandsData = [
            'available_commands' => [
                'FACTORY_RESET' => [
                    'label' => 'Factory Reset',
                    'destructive' => true,
                    'requires_reauth' => true,
                    'description' => 'Complete hardware wipe. Device will reboot into initial setup wizard.',
                ],
                'KIOSK_LOCK' => [
                    'label' => 'Kiosk Lock',
                    'destructive' => false,
                    'requires_reauth' => false,
                    'description' => 'Pin app to screen and block user escape.',
                ],
                'KIOSK_UNLOCK' => [
                    'label' => 'Kiosk Unlock',
                    'destructive' => false,
                    'requires_reauth' => false,
                    'description' => 'Release kiosk pinning and restore standard interaction.',
                ],
                'WIPE_DATA' => [
                    'label' => 'Wipe Data',
                    'destructive' => true,
                    'requires_reauth' => true,
                    'description' => 'Erase confidential data, storage, and credentials.',
                ],
            ],
            'target_device' => $deviceData,
            'recent_commands' => $recentCommands,
        ];

        // 8. SECURITY EVENTS (Server-side paginated)
        $eventsPaginated = SecurityEvent::where('user_id', $user->id)
            ->with(['device', 'evidence', 'locations', 'reviewer'])
            ->latest('occurred_at')
            ->paginate($request->input('events_per_page', 10), ['*'], 'events_page');

        $formattedEvents = $eventsPaginated->through(function ($event) use ($locationInfo) {
            $loc = $event->locations->first();
            $ev = $event->evidence->first();
            $lat = $loc?->latitude ?? $event->latitude ?? $locationInfo['latitude'];
            $lng = $loc?->longitude ?? $event->longitude ?? $locationInfo['longitude'];

            return [
                'id' => $event->id,
                'event_uuid' => $event->event_uuid,
                'event_type' => $event->event_type->value ?? (string) $event->event_type,
                'severity' => $event->severity,
                'time' => $event->occurred_at?->toIso8601String() ?? $event->created_at->toIso8601String(),
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'device' => $event->device ? [
                    'id' => $event->device->id,
                    'name' => $event->device->device_name,
                    'uuid' => $event->device->device_uuid,
                ] : null,
                'location' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'accuracy' => $loc?->accuracy ?? $event->accuracy,
                    'address' => $loc?->address,
                    'map_link' => ($lat && $lng) ? "https://maps.google.com/?q={$lat},{$lng}" : null,
                ],
                'evidence' => $event->evidence->map(fn($e) => [
                    'id' => $e->id,
                    'type' => $e->evidence_type->value ?? (string) $e->evidence_type,
                    'preview_url' => "/admin/evidence/{$e->id}/stream",
                    'view_url' => "/admin/evidence/{$e->id}",
                ]),
                'status' => $event->status,
                'reviewed_state' => [
                    'is_reviewed' => ! empty($event->reviewed_at),
                    'reviewed_at' => $event->reviewed_at?->toIso8601String(),
                    'reviewed_by' => $event->reviewer?->name ?? $event->reviewed_by,
                    'review_notes' => $event->review_notes,
                ],
            ];
        });

        // 9. NOTIFICATIONS (Server-side paginated)
        $notificationsPaginated = Notification::where('user_id', $user->id)
            ->latest('created_at')
            ->paginate($request->input('notifications_per_page', 10), ['*'], 'notifications_page');

        $formattedNotifications = $notificationsPaginated->through(function ($n) {
            return [
                'id' => $n->id,
                'notification_uuid' => $n->notification_uuid,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'sent' => $n->sent_at?->toIso8601String() ?? $n->created_at->toIso8601String(),
                'sent_at' => $n->sent_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'delivery_status' => $n->status ?? 'DELIVERED',
                'status' => $n->status ?? 'DELIVERED',
                'is_read' => (bool) $n->is_read,
            ];
        });

        // Consolidated Control Center Object
        $controlCenter = [
            'user' => $userData,
            'subscription' => $subscriptionData,
            'device' => $deviceData,
            'live_location' => $liveLocationData,
            'latest_intruder' => $latestIntruderData,
            'trusted_users' => $trustedUsers,
            'security_commands' => $securityCommandsData,
            'security_events' => $formattedEvents,
            'notifications' => $formattedNotifications,
        ];

        // Backwards-compatible legacy array with control_center
        $legacyData = array_merge($user->toArray(), [
            'current_latitude' => $locationInfo['current_latitude'],
            'current_longitude' => $locationInfo['current_longitude'],
            'latitude' => $locationInfo['current_latitude'],
            'longitude' => $locationInfo['current_longitude'],
            'accuracy' => $locationInfo['accuracy'],
            'last_updated' => $locationInfo['last_updated'],
            'map_link' => $locationInfo['map_link'],
            'short_location_reference_url' => $locationInfo['short_location_reference_url'],
            'short_url' => $locationInfo['short_url'],
            'location' => $locationInfo,
            'control_center' => $controlCenter,
        ]);

        return $this->successResponse([
            'user' => $legacyData,
            'control_center' => $controlCenter,
        ]);
    }

    /**
     * Server-side paginated Security Events for user.
     * GET /admin/users/{user}/security-events
     */
    public function securityEvents(Request $request, string|int $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::find((int) $id)
            : User::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $user) {
            return $this->errorResponse('User not found.', null, 404);
        }

        $query = SecurityEvent::where('user_id', $user->id)
            ->with(['device', 'evidence', 'locations', 'reviewer']);

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $events = $query->latest('occurred_at')
            ->paginate($request->input('per_page', 10));

        $formatted = $events->through(function ($event) {
            $loc = $event->locations->first();
            $lat = $loc?->latitude ?? $event->latitude;
            $lng = $loc?->longitude ?? $event->longitude;

            return [
                'id' => $event->id,
                'event_uuid' => $event->event_uuid,
                'event_type' => $event->event_type->value ?? (string) $event->event_type,
                'severity' => $event->severity,
                'time' => $event->occurred_at?->toIso8601String() ?? $event->created_at->toIso8601String(),
                'occurred_at' => $event->occurred_at?->toIso8601String(),
                'device' => $event->device ? [
                    'id' => $event->device->id,
                    'name' => $event->device->device_name,
                    'uuid' => $event->device->device_uuid,
                ] : null,
                'location' => [
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'accuracy' => $loc?->accuracy ?? $event->accuracy,
                    'address' => $loc?->address,
                    'map_link' => ($lat && $lng) ? "https://maps.google.com/?q={$lat},{$lng}" : null,
                ],
                'evidence' => $event->evidence->map(fn($e) => [
                    'id' => $e->id,
                    'type' => $e->evidence_type->value ?? (string) $e->evidence_type,
                    'preview_url' => "/admin/evidence/{$e->id}/stream",
                    'view_url' => "/admin/evidence/{$e->id}",
                ]),
                'status' => $event->status,
                'reviewed_state' => [
                    'is_reviewed' => ! empty($event->reviewed_at),
                    'reviewed_at' => $event->reviewed_at?->toIso8601String(),
                    'reviewed_by' => $event->reviewer?->name ?? $event->reviewed_by,
                    'review_notes' => $event->review_notes,
                ],
            ];
        });

        return $this->successResponse($formatted);
    }

    /**
     * Server-side paginated Notifications for user.
     * GET /admin/users/{user}/notifications
     */
    public function notifications(Request $request, string|int $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::find((int) $id)
            : User::where('id', $id)->orWhere('uuid', $id)->first();

        if (! $user) {
            return $this->errorResponse('User not found.', null, 404);
        }

        $query = Notification::where('user_id', $user->id);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $notifications = $query->latest('created_at')
            ->paginate($request->input('per_page', 10));

        $formatted = $notifications->through(function ($n) {
            return [
                'id' => $n->id,
                'notification_uuid' => $n->notification_uuid,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'sent' => $n->sent_at?->toIso8601String() ?? $n->created_at->toIso8601String(),
                'sent_at' => $n->sent_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
                'delivery_status' => $n->status ?? 'DELIVERED',
                'status' => $n->status ?? 'DELIVERED',
                'is_read' => (bool) $n->is_read,
            ];
        });

        return $this->successResponse($formatted);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(UserStatus::values())],
        ]);

        $user = User::findOrFail($id);
        $oldStatus = $user->status->value;
        $newStatus = $request->input('status');

        $admin = $request->user();

        // If disabling/suspending user, requires users.disable permission
        if (in_array($newStatus, [UserStatus::SUSPENDED->value], true)) {
            if (! $admin->hasPermission(AdminPermission::USERS_DISABLE)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission denied. Required permission: users.disable',
                    'error_code' => 'FORBIDDEN',
                ], 403);
            }
        } else {
            if (! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Permission denied. Required permission: users.manage',
                    'error_code' => 'FORBIDDEN',
                ], 403);
            }
        }

        $user->update(['status' => $newStatus]);

        $this->auditLogService->log(
            $admin,
            'USER_STATUS_UPDATED',
            'USERS',
            $user,
            ['status' => $oldStatus],
            ['status' => $newStatus]
        );

        return $this->successResponse(['user' => $user], 'User status updated successfully.');
    }

    public function disable(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->hasPermission(AdminPermission::USERS_DISABLE)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Required permission: users.disable',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        $user = User::findOrFail($id);
        $oldStatus = $user->status->value;

        $user->update([
            'status' => UserStatus::SUSPENDED,
            'is_active' => false,
        ]);

        // Revoke user API tokens upon disabling
        $user->tokens()->delete();

        $this->auditLogService->log(
            $admin,
            'USER_DISABLED',
            'USERS',
            $user,
            ['status' => $oldStatus, 'is_active' => true],
            ['status' => UserStatus::SUSPENDED->value, 'is_active' => false]
        );

        return $this->successResponse(['user' => $user], 'User has been disabled and sessions terminated.');
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Required permission: users.manage',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        $user = User::findOrFail($id);
        $before = $user->only(['name', 'email', 'phone_number']);

        $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($request->only(['name', 'email', 'phone_number']));

        $this->auditLogService->log(
            $admin,
            'USER_UPDATED',
            'USERS',
            $user,
            $before,
            $user->only(['name', 'email', 'phone_number'])
        );

        return $this->successResponse(['user' => $user], 'User updated successfully.');
    }

    public function store(Request $request): JsonResponse
    {
        $admin = $request->user();

        if ($admin && ! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Required permission: users.manage',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string'],
        ]);

        $phoneNumber = $validated['phone_number'] ?? $validated['phone'] ?? null;
        if (! $phoneNumber) {
            $phoneNumber = '+1' . rand(200, 999) . rand(100, 999) . rand(1000, 9999);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'phone_number' => $phoneNumber,
            'status' => $validated['status'] ?? UserStatus::ACTIVE->value,
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
        ]);

        if ($admin) {
            $this->auditLogService->log(
                $admin,
                'USER_CREATED',
                'USERS',
                $user,
                null,
                $user->only(['id', 'name', 'email', 'phone_number', 'status'])
            );
        }

        return $this->successResponse(['user' => $user], 'User created successfully.', 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();

        if ($admin && ! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission denied. Required permission: users.manage',
                'error_code' => 'FORBIDDEN',
            ], 403);
        }

        $user = User::findOrFail($id);
        $before = $user->only(['id', 'name', 'email', 'phone_number', 'status']);

        // Delete user's tokens & relations, or delete user
        $user->tokens()->delete();
        $user->subscriptions()->delete();
        $user->devices()->delete();
        $user->trustedUsers()->delete();
        $user->delete();

        if ($admin) {
            $this->auditLogService->log(
                $admin,
                'USER_DELETED',
                'USERS',
                null,
                $before,
                null
            );
        }

        return $this->successResponse(null, 'User deleted successfully.');
    }
}
