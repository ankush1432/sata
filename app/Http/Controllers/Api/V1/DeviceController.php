<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DeviceController extends Controller
{
    /**
     * Register or update a device for the authenticated user.
     * POST /api/v1/devices/register
     * POST /api/v1/devices
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => ['required_without:device_uuid', 'nullable', 'string', 'max:100'],
            'device_uuid' => ['required_without:device_id', 'nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'platform' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:50'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'fcm_token' => ['nullable', 'string'],
            'sim_serial' => ['nullable', 'string', 'max:100'],
            'phone_number' => ['nullable', 'string', 'max:30'],
            'imei' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        // Rule: user_id must come from authenticated user. Never trust user_id supplied by Flutter.
        $user = $request->user();
        $deviceId = $request->input('device_id') ?? $request->input('device_uuid');

        $existingDevice = UserDevice::where('device_uuid', $deviceId)->first();

        // Rule: Validate device ownership
        if ($existingDevice && $existingDevice->user_id !== $user->id) {
            return $this->errorResponse(
                'Device is already registered to another user.',
                null,
                403,
                'DEVICE_OWNERSHIP_INVALID'
            );
        }

        // Rule: Revoked devices cannot perform sensitive operations
        if ($existingDevice && $existingDevice->status === DeviceStatus::REVOKED) {
            return $this->errorResponse(
                'Device has been revoked. Registration is not permitted.',
                null,
                403,
                'DEVICE_REVOKED'
            );
        }

        $model = $request->input('model') ?? $request->input('device_model') ?? $existingDevice?->model;
        $manufacturer = $request->input('manufacturer') ?? $existingDevice?->manufacturer;
        $platform = strtolower($request->input('platform', $existingDevice?->platform ?? 'android'));
        $deviceName = $request->input('device_name')
            ?? ($manufacturer || $model ? trim("{$manufacturer} {$model}") : null)
            ?? $existingDevice?->device_name
            ?? 'Device ' . substr($deviceId, 0, 8);

        // If updating an existing device owned by this user
        if ($existingDevice) {
            $updatePayload = [
                'device_name' => $deviceName,
                'model' => $model,
                'device_model' => $model,
                'manufacturer' => $manufacturer,
                'platform' => $platform,
                'os_version' => $request->input('os_version') ?? $existingDevice->os_version,
                'app_version' => $request->input('app_version') ?? $existingDevice->app_version,
                'status' => DeviceStatus::ACTIVE,
                'last_seen_at' => now(),
            ];

            if ($request->filled('fcm_token')) {
                $updatePayload['fcm_token'] = $request->input('fcm_token');
            }
            if ($request->filled('sim_serial')) {
                $updatePayload['sim_serial'] = $request->input('sim_serial');
            }
            if ($request->filled('phone_number')) {
                $updatePayload['phone_number'] = $request->input('phone_number');
            }
            if ($request->filled('imei')) {
                $updatePayload['imei'] = $request->input('imei');
            }

            $existingDevice->update($updatePayload);
            $user->update(['current_device_id' => $existingDevice->id]);

            return $this->successResponse([
                'device' => $existingDevice->fresh(),
            ], 'Device registration updated successfully.', 200);
        }

        // Rule: Support configurable max_devices_per_user for new registrations
        $maxDevices = (int) config('devices.max_devices_per_user', 5);
        $activeDevicesCount = $user->devices()
            ->where('status', '!=', DeviceStatus::REVOKED->value)
            ->count();

        if ($activeDevicesCount >= $maxDevices) {
            return $this->errorResponse(
                "Maximum limit of {$maxDevices} devices reached.",
                null,
                422,
                'MAX_DEVICES_EXCEEDED'
            );
        }

        // Create new device
        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => $deviceId,
            'device_name' => $deviceName,
            'model' => $model,
            'device_model' => $model,
            'manufacturer' => $manufacturer,
            'platform' => $platform,
            'os_version' => $request->input('os_version'),
            'app_version' => $request->input('app_version'),
            'fcm_token' => $request->input('fcm_token'),
            'sim_serial' => $request->input('sim_serial'),
            'phone_number' => $request->input('phone_number'),
            'imei' => $request->input('imei'),
            'status' => DeviceStatus::ACTIVE,
            'is_protection_enabled' => true,
            'last_seen_at' => now(),
        ]);

        $user->update(['current_device_id' => $device->id]);

        return $this->successResponse([
            'device' => $device,
        ], 'Device registered successfully.', 201);
    }

    /**
     * Backward-compatible alias for register.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        return $this->register($request);
    }

    /**
     * Device heartbeat endpoint.
     * POST /api/v1/devices/heartbeat
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => ['required_without:device_uuid', 'nullable', 'string', 'max:100'],
            'device_uuid' => ['required_without:device_id', 'nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'os_version' => ['nullable', 'string', 'max:50'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $deviceId = $request->input('device_id') ?? $request->input('device_uuid');

        $device = UserDevice::where('device_uuid', $deviceId)
            ->orWhere('id', is_numeric($deviceId) ? (int) $deviceId : 0)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found.', null, 404);
        }

        // Rule: Validate device ownership
        if ($device->user_id !== $user->id) {
            return $this->errorResponse(
                'Device does not belong to the authenticated user.',
                null,
                403,
                'DEVICE_OWNERSHIP_INVALID'
            );
        }

        // Rule: Revoked devices cannot perform sensitive operations
        if ($device->status === DeviceStatus::REVOKED) {
            return $this->errorResponse(
                'Device has been revoked. Operation not permitted.',
                null,
                403,
                'DEVICE_REVOKED'
            );
        }

        $updates = [
            'last_seen_at' => now(),
        ];

        if ($device->status === DeviceStatus::INACTIVE) {
            $updates['status'] = DeviceStatus::ACTIVE;
        }
        if ($request->filled('app_version')) {
            $updates['app_version'] = $request->input('app_version');
        }
        if ($request->filled('os_version')) {
            $updates['os_version'] = $request->input('os_version');
        }

        $device->update($updates);

        return $this->successResponse([
            'device' => $device->fresh(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'status' => $device->status->value,
        ], 'Heartbeat received successfully.');
    }

    /**
     * Update FCM push notification token for a device via request body.
     * POST /api/v1/devices/fcm-token
     */
    public function updateFcmTokenFromBody(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => ['required_without:device_uuid', 'nullable', 'string', 'max:100'],
            'device_uuid' => ['required_without:device_id', 'nullable', 'string', 'max:100'],
            'fcm_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $deviceId = $request->input('device_id') ?? $request->input('device_uuid');

        $device = UserDevice::where('device_uuid', $deviceId)
            ->orWhere('id', is_numeric($deviceId) ? (int) $deviceId : 0)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found.', null, 404);
        }

        // Rule: Validate device ownership
        if ($device->user_id !== $user->id) {
            return $this->errorResponse(
                'Device does not belong to the authenticated user.',
                null,
                403,
                'DEVICE_OWNERSHIP_INVALID'
            );
        }

        // Rule: Revoked devices cannot perform sensitive operations
        if ($device->status === DeviceStatus::REVOKED) {
            return $this->errorResponse(
                'Device has been revoked. Operation not permitted.',
                null,
                403,
                'DEVICE_REVOKED'
            );
        }

        $device->update([
            'fcm_token' => $request->input('fcm_token'),
            'last_seen_at' => now(),
        ]);

        // Rule: FCM tokens must not be exposed unnecessarily
        return $this->successResponse([
            'device_id' => $device->device_uuid,
            'fcm_token_updated' => true,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ], 'FCM token updated successfully.');
    }

    /**
     * Update FCM push notification token via route parameter.
     * POST /api/v1/devices/{uuid}/fcm-token
     */
    public function updateFcmToken(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $device = UserDevice::where('device_uuid', $uuid)
            ->orWhere('id', is_numeric($uuid) ? (int) $uuid : 0)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found', null, 404);
        }

        if ($device->user_id !== $user->id) {
            return $this->errorResponse(
                'Device does not belong to the authenticated user.',
                null,
                403,
                'DEVICE_OWNERSHIP_INVALID'
            );
        }

        if ($device->status === DeviceStatus::REVOKED) {
            return $this->errorResponse(
                'Device has been revoked. Operation not permitted.',
                null,
                403,
                'DEVICE_REVOKED'
            );
        }

        $device->update([
            'fcm_token' => $request->input('fcm_token'),
            'last_seen_at' => now(),
        ]);

        return $this->successResponse([
            'device_id' => $device->device_uuid,
            'fcm_token_updated' => true,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ], 'FCM token updated successfully.');
    }

    /**
     * Delete/Unregister a device.
     * DELETE /api/v1/devices/{id}
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $device = UserDevice::where('device_uuid', $id)
            ->orWhere('id', is_numeric($id) ? (int) $id : 0)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found.', null, 404);
        }

        // Rule: Validate device ownership
        if ($device->user_id !== $user->id) {
            return $this->errorResponse(
                'Device does not belong to the authenticated user.',
                null,
                403,
                'DEVICE_OWNERSHIP_INVALID'
            );
        }

        if ($user->current_device_id === $device->id) {
            $user->update(['current_device_id' => null]);
        }

        $deletedDeviceId = $device->device_uuid;
        $device->delete();

        return $this->successResponse([
            'device_id' => $deletedDeviceId,
            'deleted' => true,
        ], 'Device deleted successfully.');
    }

    /**
     * List all devices associated with the authenticated user.
     * GET /api/v1/devices
     */
    public function listDevices(Request $request): JsonResponse
    {
        $devices = $request->user()->devices()->latest('last_seen_at')->get();

        return $this->successResponse([
            'devices' => $devices,
            'total' => $devices->count(),
            'max_allowed' => (int) config('devices.max_devices_per_user', 5),
        ]);
    }

    /**
     * View details of a specific device.
     * GET /api/v1/devices/{id}
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();

        $device = UserDevice::where('device_uuid', $id)
            ->orWhere('id', is_numeric($id) ? (int) $id : 0)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found.', null, 404);
        }

        if ($device->user_id !== $user->id) {
            return $this->errorResponse(
                'Device does not belong to the authenticated user.',
                null,
                403,
                'DEVICE_OWNERSHIP_INVALID'
            );
        }

        return $this->successResponse([
            'device' => $device,
        ]);
    }

    /**
     * Toggle protection mode on the device.
     * POST /api/v1/devices/{uuid}/protection
     */
    public function toggleProtection(Request $request, string $uuid): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'is_protection_enabled' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $device = UserDevice::where('device_uuid', $uuid)
            ->orWhere('id', is_numeric($uuid) ? (int) $uuid : 0)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found', null, 404);
        }

        if ($device->user_id !== $user->id) {
            return $this->errorResponse('Device does not belong to the authenticated user.', null, 403);
        }

        if ($device->status === DeviceStatus::REVOKED) {
            return $this->errorResponse('Device has been revoked.', null, 403);
        }

        $wantsProtection = $request->boolean('is_protection_enabled');
        if ($wantsProtection) {
            /** @var \App\Services\SubscriptionService $subscriptionService */
            $subscriptionService = app(\App\Services\SubscriptionService::class);
            $authDetails = $subscriptionService->getAuthoritativeSubscriptionDetails($user);

            if (in_array($authDetails['state'], ['EXPIRED', 'INACTIVE'], true) && $authDetails['subscription'] !== null) {
                return $this->errorResponse(
                    'Cannot start protection service: Your protection plan has expired. Please activate or renew your plan.',
                    [
                        'state' => $authDetails['state'],
                        'formatted_expiry_date' => $authDetails['formatted_expiry_date'],
                        'allow_activation' => true,
                    ],
                    403,
                    'PROTECTION_INACTIVE'
                );
            }
        }

        $device->update([
            'is_protection_enabled' => $wantsProtection,
            'last_seen_at' => now(),
        ]);

        return $this->successResponse([
            'device' => $device->fresh(),
        ], 'Device protection status updated.');
    }
}
