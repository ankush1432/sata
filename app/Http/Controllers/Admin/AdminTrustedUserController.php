<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TrustedUserStatus;
use App\Http\Controllers\Controller;
use App\Models\TrustedUser;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminTrustedUserController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Search, filter, and list trusted users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = TrustedUser::with(['user:id,name,email,phone_number', 'verifications']);

        // Search by contact name, phone, verification reference, or user name/email/phone
        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('phone_number', 'like', "%{$search}%")
                    ->orWhere('verification_reference', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by user_id
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        // Filter by verification status (PENDING, VERIFIED, DISABLED)
        if ($request->filled('status')) {
            $status = strtoupper((string) $request->input('status'));
            $query->where('status', $status);
        }

        // Filter by is_verified
        if ($request->has('is_verified')) {
            $isVerified = filter_var($request->input('is_verified'), FILTER_VALIDATE_BOOLEAN);
            if ($isVerified) {
                $query->where('status', TrustedUserStatus::VERIFIED->value)->whereNotNull('verified_at');
            } else {
                $query->where(function ($q) {
                    $q->where('status', '!=', TrustedUserStatus::VERIFIED->value)->orWhereNull('verified_at');
                });
            }
        }

        // Filter by is_active
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $trustedUsers = $query->latest('id')->paginate($request->input('per_page', 20));

        return $this->successResponse($trustedUsers);
    }

    /**
     * View trusted user detail and verification status.
     */
    public function show(int $id): JsonResponse
    {
        $trustedUser = TrustedUser::with(['user:id,name,email,phone_number', 'verifications'])->find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404);
        }

        return $this->successResponse([
            'trusted_user' => $trustedUser,
            'is_verified' => $trustedUser->isVerified(),
            'verification_status' => $trustedUser->status->value,
            'verified_at' => $trustedUser->verified_at,
            'verification_reference' => $trustedUser->verification_reference,
        ]);
    }

    /**
     * Disable a trusted user if necessary and audit sensitive change.
     */
    public function disable(Request $request, int $id): JsonResponse
    {
        $trustedUser = TrustedUser::with('user')->find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404);
        }

        $oldStatus = $trustedUser->status->value;
        $trustedUser->disable();

        // Audit sensitive change
        $this->auditLogService->log(
            admin: $request->user(),
            action: 'TRUSTED_USER_DISABLED',
            module: 'TRUSTED_USERS',
            target: $trustedUser,
            before: ['status' => $oldStatus],
            after: [
                'status' => TrustedUserStatus::DISABLED->value,
                'reason' => $request->input('reason', 'Disabled by administrator'),
            ],
            ipAddress: $request->ip()
        );

        return $this->successResponse([
            'trusted_user' => $trustedUser->fresh(),
        ], 'Trusted user disabled successfully.');
    }

    /**
     * Verify a trusted user and audit sensitive change.
     */
    public function verify(Request $request, int $id): JsonResponse
    {
        $trustedUser = TrustedUser::with('user')->find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404);
        }

        $oldStatus = $trustedUser->status->value;
        $trustedUser->verify();

        // Audit sensitive change
        $this->auditLogService->log(
            admin: $request->user(),
            action: 'TRUSTED_USER_VERIFIED',
            module: 'TRUSTED_USERS',
            target: $trustedUser,
            before: ['status' => $oldStatus],
            after: [
                'status' => TrustedUserStatus::VERIFIED->value,
                'verified_by_admin' => $request->user()->name,
            ],
            ipAddress: $request->ip()
        );

        return $this->successResponse([
            'trusted_user' => $trustedUser->fresh(),
        ], 'Trusted user verified successfully.');
    }

    /**
     * Update status (e.g. PENDING, VERIFIED, DISABLED) and audit sensitive change.
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', Rule::in(TrustedUserStatus::values())],
        ]);

        $trustedUser = TrustedUser::with('user')->find($id);

        if (! $trustedUser) {
            return $this->errorResponse('Trusted user not found.', null, 404);
        }

        $oldStatus = $trustedUser->status->value;
        $newStatus = strtoupper((string) $request->input('status'));

        if ($newStatus === TrustedUserStatus::VERIFIED->value) {
            $trustedUser->verify();
        } elseif ($newStatus === TrustedUserStatus::DISABLED->value) {
            $trustedUser->disable();
        } else {
            $trustedUser->update([
                'status' => TrustedUserStatus::PENDING,
                'verified_at' => null,
            ]);
        }

        $this->auditLogService->log(
            admin: $request->user(),
            action: 'TRUSTED_USER_STATUS_UPDATED',
            module: 'TRUSTED_USERS',
            target: $trustedUser,
            before: ['status' => $oldStatus],
            after: ['status' => $newStatus],
            ipAddress: $request->ip()
        );

        return $this->successResponse([
            'trusted_user' => $trustedUser->fresh(),
        ], 'Trusted user status updated successfully.');
    }
}
