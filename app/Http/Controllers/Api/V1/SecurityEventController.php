<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Enums\EvidenceType;
use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use App\Models\SecurityEventLocation;
use App\Models\UserDevice;
use App\Services\EvidenceService;
use App\Services\NotificationService;
use App\Services\SecurityAlertDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SecurityEventController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService,
        protected SecurityAlertDeliveryService $alertDeliveryService,
        protected EvidenceService $evidenceService
    ) {}

    /**
     * Report a new security event from a device.
     */
    public function reportEvent(Request $request): JsonResponse
    {
        // Support 'type' or 'event_type'
        if (! $request->has('event_type') && $request->has('type')) {
            $request->merge(['event_type' => $request->input('type')]);
        }

        $validator = Validator::make($request->all(), [
            'event_uuid' => ['nullable', 'string', 'max:64'],
            'device_uuid' => ['nullable', 'string'],
            'device_id' => ['nullable', 'integer'],
            'event_type' => ['required', 'string', Rule::in(SecurityEventType::values())],
            'severity' => ['nullable', 'string', 'in:LOW,MEDIUM,HIGH,CRITICAL'],
            'status' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'occurred_at' => ['nullable', 'date'],
            'triggered_at' => ['nullable', 'date'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'address' => ['nullable', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:20480'],
            'photo' => ['nullable', 'file', 'max:20480'],
            'evidence' => ['nullable', 'file', 'max:20480'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();

        // 1. Device Ownership Validation
        $device = null;
        if ($request->filled('device_id')) {
            $deviceId = $request->input('device_id');
            $device = UserDevice::find($deviceId);

            if ($device && $device->user_id !== $user->id) {
                return $this->errorResponse('Device does not belong to the authenticated user.', null, 403, 'DEVICE_OWNERSHIP_INVALID');
            }

            if ($device && $device->status === DeviceStatus::REVOKED) {
                return $this->errorResponse('Device has been revoked. Security event reporting is not permitted.', null, 403, 'DEVICE_REVOKED');
            }
        } elseif ($request->filled('device_uuid')) {
            $deviceUuid = $request->input('device_uuid');
            $existingGlobalDevice = UserDevice::where('device_uuid', $deviceUuid)->first();

            if ($existingGlobalDevice && $existingGlobalDevice->user_id !== $user->id) {
                return $this->errorResponse('Device does not belong to the authenticated user.', null, 403, 'DEVICE_OWNERSHIP_INVALID');
            }

            if ($existingGlobalDevice && $existingGlobalDevice->status === DeviceStatus::REVOKED) {
                return $this->errorResponse('Device has been revoked. Security event reporting is not permitted.', null, 403, 'DEVICE_REVOKED');
            }

            $device = $user->devices()->firstOrCreate(
                ['device_uuid' => $deviceUuid],
                ['device_name' => 'Device ' . substr($deviceUuid, 0, 8), 'status' => 'ACTIVE']
            );
        } else {
            $device = $user->currentDevice ?? $user->devices()->first();
            if ($device && $device->status === DeviceStatus::REVOKED) {
                return $this->errorResponse('Device has been revoked. Security event reporting is not permitted.', null, 403, 'DEVICE_REVOKED');
            }
        }

        // 2. Event UUID Idempotency Check
        $eventUuid = $request->input('event_uuid');
        if (! empty($eventUuid)) {
            $existingEvent = SecurityEvent::where('event_uuid', $eventUuid)->first();
            if ($existingEvent) {
                if ($existingEvent->user_id !== $user->id) {
                    return $this->errorResponse('Event UUID already exists for a different user.', null, 409, 'EVENT_UUID_CONFLICT');
                }

                // Idempotent return: return the existing recorded event
                $locationRecord = $existingEvent->locations()->first();
                return $this->successResponse([
                    'event' => $existingEvent->load(['locations', 'evidence', 'device']),
                    'event_type' => $existingEvent->event_type->value ?? (string) $existingEvent->event_type,
                    'type' => $existingEvent->event_type->value ?? (string) $existingEvent->event_type,
                    'event_uuid' => $existingEvent->event_uuid,
                    'user_id' => $existingEvent->user_id,
                    'device_id' => $existingEvent->device_id,
                    'severity' => $existingEvent->severity,
                    'status' => $existingEvent->status,
                    'occurred_at' => $existingEvent->occurred_at?->toIso8601String(),
                    'latitude' => $locationRecord ? (float) $locationRecord->latitude : null,
                    'longitude' => $locationRecord ? (float) $locationRecord->longitude : null,
                    'accuracy' => $locationRecord ? (float) $locationRecord->accuracy : null,
                    'metadata' => $existingEvent->metadata,
                    'location' => $locationRecord ? [
                        'latitude' => (float) $locationRecord->latitude,
                        'longitude' => (float) $locationRecord->longitude,
                    ] : null,
                    'idempotent' => true,
                ], 'Security event already recorded.', 200);
            }
        }

        // 3. Pre-validate evidence file if uploaded with event
        $uploadedFile = $request->file('file') ?? $request->file('photo') ?? $request->file('evidence');
        if ($uploadedFile) {
            try {
                $this->evidenceService->validateEvidenceFile($uploadedFile);
            } catch (ValidationException $e) {
                return $this->errorResponse('Evidence file validation failed.', $e->errors(), 422);
            }
        }

        // 4. Create Security Event + Location + Evidence atomically
        $occurredAt = $request->input('occurred_at') ?? $request->input('triggered_at') ?? now();

        $event = DB::transaction(function () use ($user, $device, $request, $eventUuid, $occurredAt, $uploadedFile) {
            $event = SecurityEvent::create([
                'event_uuid' => $eventUuid ?: (string) Str::uuid(),
                'user_id' => $user->id,
                'device_id' => $device?->id,
                'event_type' => $request->input('event_type'),
                'severity' => $request->input('severity', 'HIGH'),
                'status' => $request->input('status', 'REPORTED'),
                'description' => $request->input('description'),
                'metadata' => $request->input('metadata'),
                'occurred_at' => $occurredAt,
                'triggered_at' => $occurredAt,
            ]);

            // If location was provided, attach security_event_locations record
            if ($request->filled(['latitude', 'longitude'])) {
                SecurityEventLocation::create([
                    'security_event_id' => $event->id,
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'accuracy' => $request->input('accuracy'),
                    'altitude' => $request->input('altitude'),
                    'speed' => $request->input('speed'),
                    'heading' => $request->input('heading'),
                    'address' => $request->input('address'),
                    'recorded_at' => $occurredAt,
                    'captured_at' => $occurredAt,
                ]);
            }

            // If photo/file was provided, store evidence artifact
            if ($uploadedFile) {
                $this->evidenceService->storeEvidence(
                    $event,
                    $user,
                    $uploadedFile,
                    EvidenceType::FRONT_PHOTO,
                    $request->input('metadata')
                );
            }

            return $event;
        });

        $locationRecord = $event->locations()->first();

        // 5. Queue Notification to Main User + Verified Trusted Users
        try {
            $this->notificationService->notifySecurityEvent($event);
        } catch (\Throwable $e) {
            Log::error("NotificationService failed for security event #{$event->id}: " . $e->getMessage());
        }

        try {
            $alertDelivery = $this->alertDeliveryService->deliverAlert($event);
        } catch (\Throwable $e) {
            Log::error("SecurityAlertDeliveryService failed for security event #{$event->id}: " . $e->getMessage());
            $alertDelivery = ['delivered' => false, 'error' => $e->getMessage()];
        }

        $event->load(['locations', 'evidence', 'device']);

        return $this->successResponse([
            'event' => $event,
            'event_uuid' => $event->event_uuid,
            'user_id' => $event->user_id,
            'device_id' => $event->device_id,
            'event_type' => $event->event_type->value ?? (string) $event->event_type,
            'type' => $event->event_type->value ?? (string) $event->event_type,
            'severity' => $event->severity,
            'status' => $event->status,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'latitude' => $locationRecord ? (float) $locationRecord->latitude : null,
            'longitude' => $locationRecord ? (float) $locationRecord->longitude : null,
            'accuracy' => $locationRecord ? (float) $locationRecord->accuracy : null,
            'metadata' => $event->metadata,
            'location' => $locationRecord ? [
                'latitude' => (float) $locationRecord->latitude,
                'longitude' => (float) $locationRecord->longitude,
            ] : null,
            'alert_delivery' => $alertDelivery,
        ], 'Security event recorded successfully.', 201);
    }

    /**
     * List security events for the authenticated user.
     */
    public function listEvents(Request $request): JsonResponse
    {
        $query = $request->user()
            ->securityEvents()
            ->with(['locations', 'evidence', 'device']);

        // Filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        } elseif ($request->filled('type')) {
            $query->where('event_type', $request->input('type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        if ($request->filled('from_date')) {
            $query->where('occurred_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('occurred_at', '<=', $request->input('to_date'));
        }

        // Search
        if ($request->filled('search') || $request->filled('q')) {
            $search = $request->input('search') ?? $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('event_uuid', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $events = $query->latest('occurred_at')->paginate($request->input('per_page', 20));

        return $this->successResponse($events);
    }

    /**
     * Get specific security event details.
     */
    public function getEvent(Request $request, int|string $id): JsonResponse
    {
        $user = $request->user();

        $event = SecurityEvent::with(['locations', 'evidence', 'device', 'user:id,name,phone_number'])
            ->where(function ($q) use ($id) {
                $q->where('id', $id)->orWhere('event_uuid', $id);
            })
            ->first();

        if (! $event) {
            return $this->errorResponse('Security event not found.', null, 404);
        }

        // Authorization: Owner, Admin, or Verified Trusted User
        $isAdmin = $user instanceof \App\Models\Admin;
        $isOwner = $user && $event->user_id === $user->id;
        $isVerifiedTrusted = false;

        if (! $isOwner && ! $isAdmin && $user instanceof \App\Models\User) {
            $phone = $user->phone_number ?: $user->phone;
            if (! empty($phone)) {
                $isVerifiedTrusted = \App\Models\TrustedUser::where('user_id', $event->user_id)
                    ->where(function ($q) use ($phone) {
                        $q->where('phone', $phone)->orWhere('phone_number', $phone);
                    })
                    ->where('status', \App\Enums\TrustedUserStatus::VERIFIED)
                    ->whereNotNull('verified_at')
                    ->exists();
            }
        }

        if (! $isOwner && ! $isAdmin && ! $isVerifiedTrusted) {
            return $this->errorResponse('Unauthorized to view this security event.', null, 403, 'UNAUTHORIZED_EVENT_ACCESS');
        }

        $locationRecord = $event->locations()->first();

        return $this->successResponse([
            'event' => $event,
            'id' => $event->id,
            'event_id' => $event->id,
            'event_uuid' => $event->event_uuid,
            'user_id' => $event->user_id,
            'device_id' => $event->device_id,
            'device' => $event->device,
            'event_type' => $event->event_type->value ?? (string) $event->event_type,
            'type' => $event->event_type->value ?? (string) $event->event_type,
            'severity' => $event->severity,
            'status' => $event->status,
            'description' => $event->description,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'latitude' => $locationRecord ? (float) $locationRecord->latitude : null,
            'longitude' => $locationRecord ? (float) $locationRecord->longitude : null,
            'accuracy' => $locationRecord ? (float) $locationRecord->accuracy : null,
            'altitude' => $locationRecord ? (float) $locationRecord->altitude : null,
            'speed' => $locationRecord ? (float) $locationRecord->speed : null,
            'heading' => $locationRecord ? (float) $locationRecord->heading : null,
            'address' => $locationRecord?->address,
            'location' => $locationRecord ? [
                'latitude' => (float) $locationRecord->latitude,
                'longitude' => (float) $locationRecord->longitude,
                'accuracy' => (float) $locationRecord->accuracy,
                'altitude' => $locationRecord->altitude ? (float) $locationRecord->altitude : null,
                'speed' => $locationRecord->speed ? (float) $locationRecord->speed : null,
                'heading' => $locationRecord->heading ? (float) $locationRecord->heading : null,
                'address' => $locationRecord->address,
            ] : null,
            'evidence' => $event->evidence,
            'metadata' => $event->metadata,
        ]);
    }
}
