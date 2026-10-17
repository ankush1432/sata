<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\LiveLocationSession;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    /**
     * Device location update endpoint.
     * POST /api/v1/location/update
     */
    public function updateLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'recorded_at' => ['nullable', 'date'],
            'device_uuid' => ['nullable', 'string'],
            'device_id' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $device = null;

        if ($request->filled('device_id')) {
            $deviceId = $request->input('device_id');
            // Try by ID or UUID
            $device = UserDevice::where(function ($q) use ($deviceId) {
                $q->where('id', $deviceId)->orWhere('device_uuid', $deviceId);
            })->first();

            if ($device && $device->user_id !== $user->id) {
                return $this->errorResponse('Device does not belong to the authenticated user.', null, 403, 'DEVICE_OWNERSHIP_INVALID');
            }

            if ($device && $device->status === DeviceStatus::REVOKED) {
                return $this->errorResponse('Device has been revoked. Location update not permitted.', null, 403, 'DEVICE_REVOKED');
            }
        } elseif ($request->filled('device_uuid')) {
            $deviceUuid = $request->input('device_uuid');
            $device = UserDevice::where('device_uuid', $deviceUuid)->first();

            if ($device && $device->user_id !== $user->id) {
                return $this->errorResponse('Device does not belong to the authenticated user.', null, 403, 'DEVICE_OWNERSHIP_INVALID');
            }

            if ($device && $device->status === DeviceStatus::REVOKED) {
                return $this->errorResponse('Device has been revoked. Location update not permitted.', null, 403, 'DEVICE_REVOKED');
            }

            if (! $device) {
                $device = $user->devices()->create([
                    'device_uuid' => $deviceUuid,
                    'device_name' => 'Device ' . substr($deviceUuid, 0, 8),
                    'status' => DeviceStatus::ACTIVE,
                ]);
            }
        } else {
            $device = $user->currentDevice ?? $user->devices()->where('status', DeviceStatus::ACTIVE)->first();
            if (! $device) {
                $device = $user->devices()->create([
                    'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
                    'device_name' => 'Primary Device',
                    'status' => DeviceStatus::ACTIVE,
                ]);
            }
        }

        $locationData = $this->locationService->updateDeviceLocation($device, $request->all());

        return $this->successResponse([
            'location' => $locationData,
            'map_link' => "https://maps.google.com/?q={$locationData['latitude']},{$locationData['longitude']}",
        ], 'Location updated successfully.');
    }

    /**
     * Start a new live location tracking session.
     * POST /api/v1/location/live-session
     */
    public function startLiveSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_uuid' => ['nullable', 'string'],
            'device_id' => ['nullable'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $device = null;

        if ($request->filled('device_uuid')) {
            $device = $user->devices()->where('device_uuid', $request->input('device_uuid'))->first();
            if (! $device) {
                return $this->errorResponse('Device not found or does not belong to user.', null, 403, 'DEVICE_OWNERSHIP_INVALID');
            }
        } elseif ($request->filled('device_id')) {
            $deviceId = $request->input('device_id');
            $device = $user->devices()->where(function ($q) use ($deviceId) {
                $q->where('id', $deviceId)->orWhere('device_uuid', $deviceId);
            })->first();

            if (! $device) {
                return $this->errorResponse('Device not found or does not belong to user.', null, 403, 'DEVICE_OWNERSHIP_INVALID');
            }
        }

        if ($device && $device->status === DeviceStatus::REVOKED) {
            return $this->errorResponse('Device has been revoked. Live tracking is not permitted.', null, 403, 'DEVICE_REVOKED');
        }

        /** @var \App\Services\SubscriptionService $subscriptionService */
        $subscriptionService = app(\App\Services\SubscriptionService::class);
        $authDetails = $subscriptionService->getAuthoritativeSubscriptionDetails($user);
        if ($authDetails['state'] === 'EXPIRED' && $authDetails['subscription'] !== null) {
            return $this->errorResponse(
                'Cannot start live location tracking: Protection subscription is expired. Please activate or renew your plan.',
                [
                    'state' => 'EXPIRED',
                    'formatted_expiry_date' => $authDetails['formatted_expiry_date'],
                    'allow_activation' => true,
                ],
                403,
                'PROTECTION_INACTIVE'
            );
        }

        $duration = (int) $request->input('duration_minutes', 60);
        $initialCoords = null;
        if ($request->filled(['latitude', 'longitude'])) {
            $initialCoords = [
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'accuracy' => $request->input('accuracy'),
                'altitude' => $request->input('altitude'),
                'speed' => $request->input('speed'),
                'heading' => $request->input('heading'),
            ];
        }

        $session = $this->locationService->startLiveSession($user, $device, $duration, $initialCoords);

        // Audit logging
        $this->locationService->logAccess(
            $user,
            $user,
            'SESSION_STARTED',
            $device,
            $session->session_id,
            $request,
            ['duration_minutes' => $duration]
        );

        return $this->successResponse([
            'session' => $session,
            'session_id' => $session->session_id,
            'short_token' => $session->short_token,
            'short_url' => $session->short_location_reference_url,
            'short_location_reference_url' => $session->short_location_reference_url,
            'map_link' => $session->map_link,
            'expires_at' => $session->expires_at?->toIso8601String(),
        ], 'Live location session started.', 201);
    }

    /**
     * Stream continuous location update to the active session.
     * PUT /api/v1/location/live-session/{token}
     */
    public function updateLiveSession(Request $request, string $token): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'altitude' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $session = LiveLocationSession::where(function ($q) use ($token) {
            $q->where('session_token', $token)
                ->orWhere('session_id', $token)
                ->orWhere('short_token', $token);
        })->first();

        if (! $session) {
            return $this->errorResponse('Live location session not found.', null, 404);
        }

        $user = $request->user();
        if ($user && $session->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to update this session.', null, 403);
        }

        if ($session->isExpired()) {
            $session->update(['is_active' => false, 'status' => 'EXPIRED']);
            return $this->errorResponse('Live location session has expired.', null, 403, 'SESSION_EXPIRED');
        }

        if ($session->status === 'STOPPED') {
            return $this->errorResponse('Live location session has stopped.', null, 403, 'SESSION_STOPPED');
        }

        $session->update([
            'last_latitude' => $request->input('latitude'),
            'last_longitude' => $request->input('longitude'),
            'last_accuracy' => $request->input('accuracy'),
            'last_altitude' => $request->input('altitude'),
            'last_speed' => $request->input('speed'),
            'last_heading' => $request->input('heading'),
            'last_updated_at' => now(),
        ]);

        // Also update the device's location
        if ($session->device) {
            $session->device->update([
                'last_latitude' => $request->input('latitude'),
                'last_longitude' => $request->input('longitude'),
                'last_accuracy' => $request->input('accuracy'),
                'last_altitude' => $request->input('altitude'),
                'last_speed' => $request->input('speed'),
                'last_heading' => $request->input('heading'),
                'location_updated_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        return $this->successResponse([
            'session' => $session,
            'map_link' => $session->map_link,
        ], 'Location updated.');
    }

    /**
     * End/stop live location tracking session.
     * POST /api/v1/location/live-session/{token}/stop or DELETE /api/v1/location/live-session/{token}
     */
    public function endLiveSession(Request $request, string $token): JsonResponse
    {
        $session = LiveLocationSession::where(function ($q) use ($token) {
            $q->where('session_token', $token)
                ->orWhere('session_id', $token)
                ->orWhere('short_token', $token);
        })->first();

        if (! $session) {
            return $this->errorResponse('Session not found.', null, 404);
        }

        $user = $request->user();
        if ($user && $session->user_id !== $user->id) {
            return $this->errorResponse('Unauthorized to stop this session.', null, 403);
        }

        $this->locationService->stopLiveSession($session);

        $this->locationService->logAccess(
            $session->user,
            $user,
            'SESSION_STOPPED',
            $session->device,
            $session->session_id,
            $request
        );

        return $this->successResponse([
            'session_id' => $session->session_id,
            'status' => 'STOPPED',
            'stopped_at' => $session->stopped_at?->toIso8601String(),
        ], 'Live location session ended.');
    }

    /**
     * Get live session status and coordinates.
     * GET /api/v1/location/live-session/{token}
     */
    public function getLiveSession(Request $request, string $token): JsonResponse
    {
        $session = LiveLocationSession::with(['device', 'user:id,name,phone_number'])
            ->where(function ($q) use ($token) {
                $q->where('session_token', $token)
                    ->orWhere('session_id', $token)
                    ->orWhere('short_token', $token);
            })
            ->first();

        if (! $session) {
            return $this->errorResponse('Session not found.', null, 404);
        }

        $user = $request->user();
        if (! $this->locationService->canAccessUserLocation($user, $session->user)) {
            return $this->errorResponse('Unauthorized to view this location session.', null, 403, 'UNAUTHORIZED_LOCATION_ACCESS');
        }

        if ($session->isExpired()) {
            $session->update(['is_active' => false, 'status' => 'EXPIRED']);
            return $this->errorResponse('Live location session has expired.', null, 403, 'SESSION_EXPIRED');
        }

        $this->locationService->logAccess(
            $session->user,
            $user,
            'LIVE_SESSION_VIEWED',
            $session->device,
            $session->session_id,
            $request
        );

        return $this->successResponse([
            'session' => $session,
            'latitude' => $session->last_latitude,
            'longitude' => $session->last_longitude,
            'accuracy' => $session->last_accuracy,
            'map_link' => $session->map_link,
            'short_url' => $session->short_location_reference_url,
        ]);
    }

    /**
     * Get location for current user or linked target user.
     * GET /api/v1/location/current
     * GET /api/v1/location/user/{id}
     */
    public function getUserLocation(Request $request, int|string|null $id = null): JsonResponse
    {
        $user = $request->user();

        $targetUser = null;
        if ($id === null || $id === 'current' || $id === (string) $user->id) {
            $targetUser = $user;
        } else {
            $targetUser = User::find($id);
        }

        if (! $targetUser) {
            return $this->errorResponse('User not found.', null, 404);
        }

        // Ownership and access check: Trusted users can only view locations for their linked main user
        if (! $this->locationService->canAccessUserLocation($user, $targetUser)) {
            return $this->errorResponse('Unauthorized location access. You can only view locations for your linked main user.', null, 403, 'UNAUTHORIZED_LOCATION_ACCESS');
        }

        $this->locationService->logAccess(
            $targetUser,
            $user,
            'LOCATION_VIEWED',
            $targetUser->latestLocationDevice(),
            null,
            $request
        );

        $locationInfo = $this->locationService->formatUserLocation($targetUser);

        return $this->successResponse($locationInfo);
    }

    /**
     * Short location reference URL endpoint.
     * GET /api/v1/location/ref/{token}
     *
     * Rules:
     * - Short URLs must not directly expose sensitive data.
     * - Never put raw coordinates in public URL paths.
     * - Access requires authorization.
     * - Log sensitive location access.
     */
    public function accessShortReference(Request $request, string $token): JsonResponse
    {
        $session = LiveLocationSession::with(['device', 'user:id,name,phone_number'])
            ->where(function ($q) use ($token) {
                $q->where('short_token', $token)
                    ->orWhere('session_token', $token)
                    ->orWhere('session_id', $token);
            })
            ->first();

        if (! $session) {
            return $this->errorResponse('Location reference not found.', null, 404);
        }

        $user = $request->user();

        // 1. Authorization Check: Live location is not permanently public. Access requires authorization.
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Live location access requires authentication.',
                'error_code' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (! $this->locationService->canAccessUserLocation($user, $session->user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized location access. Trusted users can only view locations for their linked main user.',
                'error_code' => 'UNAUTHORIZED_LOCATION_ACCESS',
            ], 403);
        }

        // 2. Expiration Check
        if ($session->isExpired()) {
            $session->update(['is_active' => false, 'status' => 'EXPIRED']);
            return response()->json([
                'success' => false,
                'message' => 'Live location session has expired.',
                'error_code' => 'SESSION_EXPIRED',
            ], 403);
        }

        // 3. Stopped Check
        if ($session->status === 'STOPPED') {
            return response()->json([
                'success' => false,
                'message' => 'Live location session has stopped.',
                'error_code' => 'SESSION_STOPPED',
            ], 403);
        }

        // 4. Log Access
        $this->locationService->logAccess(
            $session->user,
            $user,
            'SHORT_REF_ACCESSED',
            $session->device,
            $session->session_id,
            $request,
            ['short_token' => $token]
        );

        return $this->successResponse([
            'session_id' => $session->session_id,
            'status' => $session->status,
            'latitude' => $session->last_latitude,
            'longitude' => $session->last_longitude,
            'accuracy' => $session->last_accuracy,
            'map_link' => $session->map_link,
            'last_updated' => $session->last_updated_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
        ]);
    }
}
