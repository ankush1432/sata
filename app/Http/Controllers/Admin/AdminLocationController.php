<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Http\Controllers\Controller;
use App\Models\LiveLocationSession;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminLocationController extends Controller
{
    public function __construct(
        protected LocationService $locationService
    ) {}

    /**
     * View a user's current location details.
     * GET /admin/users/{user}/location
     */
    public function getUserLocation(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->hasPermission(AdminPermission::USERS_VIEW)) {
            return $this->errorResponse('Permission denied.', null, 403, 'FORBIDDEN');
        }

        $user = User::with(['devices', 'liveLocationSessions'])->find($id);

        if (! $user) {
            return $this->errorResponse('User not found.', null, 404);
        }

        // Log sensitive location access by admin
        $this->locationService->logAccess(
            $user,
            $admin,
            'ADMIN_LOCATION_VIEWED',
            $user->latestLocationDevice(),
            $user->activeLiveLocationSession()?->session_id,
            $request
        );

        $locationData = $this->locationService->formatUserLocation($user);

        return $this->successResponse($locationData);
    }

    /**
     * Start a live location tracking session for a user.
     * POST /admin/users/{user}/location/session
     */
    public function startUserSession(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->hasPermission(AdminPermission::USERS_VIEW) && ! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
            return $this->errorResponse('Permission denied.', null, 403, 'FORBIDDEN');
        }

        $user = User::find($id);

        if (! $user) {
            return $this->errorResponse('User not found.', null, 404);
        }

        /** @var \App\Services\SubscriptionService $subscriptionService */
        $subscriptionService = app(\App\Services\SubscriptionService::class);
        $authDetails = $subscriptionService->getAuthoritativeSubscriptionDetails($user);
        if (($authDetails['state'] ?? null) === 'EXPIRED' || (! empty($authDetails['subscription']) && ! ($authDetails['has_active_subscription'] ?? false))) {
            return $this->errorResponse(
                'Cannot start live location tracking: Target user subscription has expired or is inactive.',
                [
                    'state' => $authDetails['state'] ?? 'EXPIRED',
                    'formatted_expiry_date' => $authDetails['formatted_expiry_date'] ?? null,
                ],
                403,
                'PROTECTION_INACTIVE'
            );
        }

        $device = null;
        if ($request->filled('device_id')) {
            $device = $user->devices()->where('id', $request->input('device_id'))->first();
        } elseif ($request->filled('device_uuid')) {
            $device = $user->devices()->where('device_uuid', $request->input('device_uuid'))->first();
        }

        $durationMinutes = (int) $request->input('duration_minutes', 60);

        $session = $this->locationService->startLiveSession($user, $device, $durationMinutes);

        // Audit log
        $this->locationService->logAccess(
            $user,
            $admin,
            'ADMIN_SESSION_STARTED',
            $session->device,
            $session->session_id,
            $request,
            ['duration_minutes' => $durationMinutes]
        );

        return $this->successResponse([
            'session' => $session,
            'session_id' => $session->session_id,
            'status' => $session->status,
            'short_token' => $session->short_token,
            'short_url' => $session->short_location_reference_url,
            'short_location_reference_url' => $session->short_location_reference_url,
            'map_link' => $session->map_link,
            'started_at' => $session->started_at?->toIso8601String(),
            'expires_at' => $session->expires_at?->toIso8601String(),
        ], 'Live location session initiated.', 201);
    }

    /**
     * Stop a live location tracking session.
     * POST /admin/location/sessions/{session}/stop
     */
    public function stopSession(Request $request, int|string $id): JsonResponse
    {
        $admin = $request->user();

        if (! $admin->hasPermission(AdminPermission::USERS_VIEW) && ! $admin->hasPermission(AdminPermission::USERS_MANAGE)) {
            return $this->errorResponse('Permission denied.', null, 403, 'FORBIDDEN');
        }

        $session = LiveLocationSession::where('session_id', $id)
            ->orWhere('id', $id)
            ->orWhere('short_token', $id)
            ->first();

        if (! $session) {
            return $this->errorResponse('Live location session not found.', null, 404);
        }

        $this->locationService->stopLiveSession($session);

        // Audit log
        $this->locationService->logAccess(
            $session->user,
            $admin,
            'ADMIN_SESSION_STOPPED',
            $session->device,
            $session->session_id,
            $request
        );

        return $this->successResponse([
            'session_id' => $session->session_id,
            'status' => 'STOPPED',
            'stopped_at' => $session->stopped_at?->toIso8601String(),
        ], 'Live location session stopped.');
    }
}
