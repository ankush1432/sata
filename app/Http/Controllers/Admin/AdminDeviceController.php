<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeviceStatus;
use App\Http\Controllers\Controller;
use App\Models\UserDevice;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminDeviceController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Search and filter all registered devices.
     * GET /api/v1/admin/devices
     */
    public function index(Request $request): JsonResponse
    {
        $query = UserDevice::with('user:id,name,email,phone_number');

        // Search across device attributes and user details
        $searchTerm = $request->input('search') ?? $request->input('q');
        if (! empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $q->where('device_uuid', 'like', "%{$searchTerm}%")
                    ->orWhere('device_name', 'like', "%{$searchTerm}%")
                    ->orWhere('model', 'like', "%{$searchTerm}%")
                    ->orWhere('manufacturer', 'like', "%{$searchTerm}%")
                    ->orWhere('platform', 'like', "%{$searchTerm}%")
                    ->orWhere('app_version', 'like', "%{$searchTerm}%")
                    ->orWhere('os_version', 'like', "%{$searchTerm}%")
                    ->orWhereHas('user', function ($uq) use ($searchTerm) {
                        $uq->where('name', 'like', "%{$searchTerm}%")
                            ->orWhere('email', 'like', "%{$searchTerm}%")
                            ->orWhere('phone_number', 'like', "%{$searchTerm}%");
                    });
            });
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by status (ACTIVE, INACTIVE, REVOKED)
        if ($request->filled('status')) {
            $status = strtoupper((string) $request->input('status'));
            $query->where('status', $status);
        }

        // Filter by platform (android, ios)
        if ($request->filled('platform')) {
            $query->where('platform', strtolower((string) $request->input('platform')));
        }

        // Filter by manufacturer
        if ($request->filled('manufacturer')) {
            $query->where('manufacturer', 'like', '%' . $request->input('manufacturer') . '%');
        }

        // Filter by model
        if ($request->filled('model')) {
            $query->where('model', 'like', '%' . $request->input('model') . '%');
        }

        // Filter by app_version
        if ($request->filled('app_version')) {
            $query->where('app_version', $request->input('app_version'));
        }

        // Filter by is_active (boolean)
        if ($request->has('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN);
            if ($isActive) {
                $query->where('status', DeviceStatus::ACTIVE->value);
            } else {
                $query->where('status', '!=', DeviceStatus::ACTIVE->value);
            }
        }

        $perPage = (int) $request->input('per_page', 20);
        $devices = $query->orderByDesc('last_seen_at')->paginate($perPage);

        return $this->successResponse($devices);
    }

    /**
     * View detailed device information.
     * GET /api/v1/admin/devices/{id}
     */
    public function show(string|int $id): JsonResponse
    {
        $device = UserDevice::with([
            'user:id,name,email,phone_number,status',
            'securityEvents' => fn ($q) => $q->latest()->limit(10),
        ])
            ->where('id', is_numeric($id) ? (int) $id : 0)
            ->orWhere('device_uuid', (string) $id)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found.', null, 404);
        }

        $deviceData = $device->toArray();
        $deviceData['has_fcm_token'] = ! empty($device->fcm_token);

        return $this->successResponse(['device' => $deviceData]);
    }

    /**
     * Revoke access for a specific device.
     * POST /api/v1/admin/devices/{id}/revoke
     * PUT /api/v1/admin/devices/{id}/revoke
     */
    public function revoke(Request $request, string|int $id): JsonResponse
    {
        $device = UserDevice::where('id', is_numeric($id) ? (int) $id : 0)
            ->orWhere('device_uuid', (string) $id)
            ->first();

        if (! $device) {
            return $this->errorResponse('Device not found.', null, 404);
        }

        $before = [
            'status' => $device->status?->value ?? (string) $device->status,
            'is_protection_enabled' => $device->is_protection_enabled,
            'revoked_at' => $device->revoked_at?->toIso8601String(),
        ];

        $device->update([
            'status' => DeviceStatus::REVOKED,
            'is_protection_enabled' => false,
            'revoked_at' => now(),
        ]);

        $this->auditLogService->log(
            $request->user(),
            'DEVICE_REVOKED',
            'DEVICES',
            $device,
            $before,
            [
                'status' => DeviceStatus::REVOKED->value,
                'is_protection_enabled' => false,
                'revoked_at' => now()->toIso8601String(),
            ]
        );

        return $this->successResponse([
            'device' => $device->fresh(),
        ], 'Device access revoked successfully.');
    }
}
