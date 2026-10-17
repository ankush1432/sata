<?php

namespace App\Services;

use App\Enums\AdminPermission;
use App\Enums\DeviceStatus;
use App\Enums\TrustedUserStatus;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\LiveLocationSession;
use App\Models\LocationAccessAuditLog;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LocationService
{
    /**
     * Update device location from device telemetry.
     */
    public function updateDeviceLocation(UserDevice $device, array $data): array
    {
        $now = now();
        $recordedAt = ! empty($data['recorded_at'])
            ? Carbon::parse($data['recorded_at'])
            : $now;

        $updatePayload = [
            'last_latitude' => $data['latitude'],
            'last_longitude' => $data['longitude'],
            'last_accuracy' => $data['accuracy'] ?? null,
            'location_updated_at' => $recordedAt,
            'last_seen_at' => $now,
        ];

        if (array_key_exists('altitude', $data)) {
            $updatePayload['last_altitude'] = $data['altitude'];
        }
        if (array_key_exists('speed', $data)) {
            $updatePayload['last_speed'] = $data['speed'];
        }
        if (array_key_exists('heading', $data)) {
            $updatePayload['last_heading'] = $data['heading'];
        }

        $device->update($updatePayload);

        // Update any active live location sessions for this device or user
        $activeSessions = LiveLocationSession::where('device_id', $device->id)
            ->where('is_active', true)
            ->whereNull('stopped_at')
            ->get();

        foreach ($activeSessions as $session) {
            if ($session->isExpired()) {
                $session->update([
                    'is_active' => false,
                    'status' => 'EXPIRED',
                ]);
            } else {
                $session->update([
                    'last_latitude' => $data['latitude'],
                    'last_longitude' => $data['longitude'],
                    'last_accuracy' => $data['accuracy'] ?? $session->last_accuracy,
                    'last_altitude' => $data['altitude'] ?? $session->last_altitude,
                    'last_speed' => $data['speed'] ?? $session->last_speed,
                    'last_heading' => $data['heading'] ?? $session->last_heading,
                    'last_updated_at' => $recordedAt,
                ]);
            }
        }

        return [
            'latitude' => (float) $device->last_latitude,
            'longitude' => (float) $device->last_longitude,
            'accuracy' => $device->last_accuracy ? (float) $device->last_accuracy : null,
            'altitude' => $device->last_altitude ? (float) $device->last_altitude : null,
            'speed' => $device->last_speed ? (float) $device->last_speed : null,
            'heading' => $device->last_heading ? (float) $device->last_heading : null,
            'recorded_at' => $recordedAt->toIso8601String(),
            'updated_at' => $device->location_updated_at?->toIso8601String(),
            'device_id' => $device->device_uuid,
        ];
    }

    /**
     * Start a time-limited live location session.
     */
    public function startLiveSession(User $user, ?UserDevice $device = null, int $durationMinutes = 60, ?array $initialCoords = null): LiveLocationSession
    {
        $device = $device ?? $user->latestLocationDevice() ?? $user->devices()->where('status', DeviceStatus::ACTIVE)->first();

        if (! $device) {
            // Create a default device for the user if none exists
            $device = $user->devices()->create([
                'device_uuid' => (string) Str::uuid(),
                'device_name' => $user->name . "'s Primary Device",
                'status' => DeviceStatus::ACTIVE,
            ]);
        }

        // Stop prior active sessions for this device
        LiveLocationSession::where('device_id', $device->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'status' => 'STOPPED',
                'stopped_at' => now(),
                'ended_at' => now(),
            ]);

        $startedAt = now();
        $expiresAt = $durationMinutes > 0 ? $startedAt->copy()->addMinutes($durationMinutes) : null;
        $sessionId = (string) Str::uuid();
        $shortToken = Str::random(16);

        $session = LiveLocationSession::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'session_id' => $sessionId,
            'session_token' => $sessionId,
            'short_token' => $shortToken,
            'status' => 'ACTIVE',
            'is_active' => true,
            'started_at' => $startedAt,
            'expires_at' => $expiresAt,
            'last_latitude' => $initialCoords['latitude'] ?? $device->last_latitude,
            'last_longitude' => $initialCoords['longitude'] ?? $device->last_longitude,
            'last_accuracy' => $initialCoords['accuracy'] ?? $device->last_accuracy,
            'last_altitude' => $initialCoords['altitude'] ?? $device->last_altitude,
            'last_speed' => $initialCoords['speed'] ?? $device->last_speed,
            'last_heading' => $initialCoords['heading'] ?? $device->last_heading,
            'last_updated_at' => $startedAt,
        ]);

        return $session;
    }

    /**
     * Stop an active live location session.
     */
    public function stopLiveSession(LiveLocationSession $session): void
    {
        $session->stop();
    }

    /**
     * Check if an accessor can view the target user's location.
     */
    public function canAccessUserLocation(mixed $accessor, User $targetUser): bool
    {
        if (! $accessor) {
            return false;
        }

        // 1. Admin with view permissions can access
        if ($accessor instanceof Admin) {
            return true;
        }

        // 2. Owner can access their own location
        if ($accessor instanceof User && $accessor->id === $targetUser->id) {
            return true;
        }

        // 3. Verified trusted user linked to targetUser can access
        if ($accessor instanceof User) {
            $phone = $accessor->phone_number ?: $accessor->phone;
            if (! empty($phone)) {
                $isVerified = TrustedUser::where('user_id', $targetUser->id)
                    ->where(function ($q) use ($phone) {
                        $q->where('phone', $phone)
                            ->orWhere('phone_number', $phone);
                    })
                    ->where('status', TrustedUserStatus::VERIFIED)
                    ->whereNotNull('verified_at')
                    ->exists();

                if ($isVerified) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Log sensitive location access to audit trails.
     */
    public function logAccess(
        User $targetUser,
        mixed $accessor,
        string $action,
        ?UserDevice $device = null,
        ?string $sessionIdentifier = null,
        ?Request $request = null,
        array $metadata = []
    ): LocationAccessAuditLog {
        $accessorType = 'PUBLIC';
        $accessorId = null;
        $adminId = null;
        $userId = null;

        if ($accessor instanceof Admin) {
            $accessorType = 'ADMIN';
            $accessorId = $accessor->id;
            $adminId = $accessor->id;
        } elseif ($accessor instanceof User) {
            if ($accessor->id === $targetUser->id) {
                $accessorType = 'USER';
            } else {
                $accessorType = 'TRUSTED_USER';
            }
            $accessorId = $accessor->id;
            $userId = $accessor->id;
        }

        $log = LocationAccessAuditLog::create([
            'target_user_id' => $targetUser->id,
            'target_device_id' => $device?->id,
            'session_identifier' => $sessionIdentifier,
            'admin_id' => $adminId,
            'user_id' => $userId,
            'accessor_type' => $accessorType,
            'accessor_id' => $accessorId,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            'metadata' => $metadata,
        ]);

        // If admin accessed, also create AdminAuditLog
        if ($accessor instanceof Admin) {
            AdminAuditLog::create([
                'admin_id' => $accessor->id,
                'action' => $action,
                'module' => 'LOCATION',
                'target_type' => User::class,
                'target_id' => $targetUser->id,
                'after_payload' => array_merge([
                    'target_user_id' => $targetUser->id,
                    'action' => $action,
                ], $metadata),
                'ip_address' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
            ]);
        }

        return $log;
    }

    /**
     * Format full user location details for response.
     */
    public function formatUserLocation(User $user): array
    {
        $session = $user->activeLiveLocationSession();
        $device = $user->latestLocationDevice();

        $latitude = $session?->last_latitude ?? $device?->last_latitude ?? null;
        $longitude = $session?->last_longitude ?? $device?->last_longitude ?? null;
        $accuracy = $session?->last_accuracy ?? $device?->last_accuracy ?? null;
        $lastUpdated = $session?->last_updated_at ?? $device?->location_updated_at ?? $device?->last_seen_at ?? null;

        $mapLink = ($latitude !== null && $longitude !== null)
            ? "https://maps.google.com/?q={$latitude},{$longitude}"
            : null;

        $shortUrl = $session
            ? $session->short_location_reference_url
            : $user->short_location_reference_url;

        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'current_latitude' => $latitude ? (float) $latitude : null,
            'current_longitude' => $longitude ? (float) $longitude : null,
            'latitude' => $latitude ? (float) $latitude : null,
            'longitude' => $longitude ? (float) $longitude : null,
            'accuracy' => $accuracy ? (float) $accuracy : null,
            'last_updated' => $lastUpdated ? Carbon::parse($lastUpdated)->toIso8601String() : null,
            'map_link' => $mapLink,
            'short_location_reference_url' => $shortUrl,
            'short_url' => $shortUrl,
            'device' => $device ? [
                'id' => $device->id,
                'device_uuid' => $device->device_uuid,
                'device_name' => $device->device_name,
                'status' => $device->status->value ?? (string) $device->status,
            ] : null,
            'active_session' => $session ? [
                'session_id' => $session->session_id,
                'status' => $session->status,
                'started_at' => $session->started_at?->toIso8601String(),
                'expires_at' => $session->expires_at?->toIso8601String(),
                'short_url' => $session->short_location_reference_url,
            ] : null,
        ];
    }
}
