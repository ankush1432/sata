<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminManagementController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Admin::query();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $admins = $query->latest()->paginate($request->input('per_page', 20));

        return $this->successResponse($admins);
    }

    public function show(int $id): JsonResponse
    {
        $admin = Admin::find($id);

        if (! $admin) {
            return $this->errorResponse('Admin not found.', null, 404);
        }

        return $this->successResponse([
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role->value,
                'is_active' => (bool) $admin->is_active,
                'custom_permissions' => $admin->custom_permissions,
                'effective_permissions' => $admin->getPermissions(),
                'last_login_at' => $admin->last_login_at?->toIso8601String(),
                'last_login_ip' => $admin->last_login_ip,
                'created_at' => $admin->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'string', Rule::in(AdminRole::values())],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.*' => ['string', Rule::in(AdminPermission::values())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $plainPassword = $data['password'];
        $data['password'] = Hash::make($plainPassword);
        $data['is_active'] = $data['is_active'] ?? true;

        $newAdmin = Admin::create($data);

        $this->auditLogService->log(
            $request->user(),
            'ADMIN_CREATED',
            'ADMINS',
            $newAdmin,
            null,
            [
                'id' => $newAdmin->id,
                'name' => $newAdmin->name,
                'email' => $newAdmin->email,
                'role' => $newAdmin->role->value,
                'is_active' => $newAdmin->is_active,
            ]
        );

        return $this->successResponse([
            'admin' => [
                'id' => $newAdmin->id,
                'name' => $newAdmin->name,
                'email' => $newAdmin->email,
                'role' => $newAdmin->role->value,
                'is_active' => (bool) $newAdmin->is_active,
                'permissions' => $newAdmin->getPermissions(),
            ],
        ], 'Admin account created successfully.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $targetAdmin = Admin::findOrFail($id);
        $before = [
            'name' => $targetAdmin->name,
            'role' => $targetAdmin->role->value,
            'is_active' => (bool) $targetAdmin->is_active,
            'custom_permissions' => $targetAdmin->custom_permissions,
        ];

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($targetAdmin->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['nullable', 'string', Rule::in(AdminRole::values())],
            'custom_permissions' => ['nullable', 'array'],
            'custom_permissions.*' => ['string', Rule::in(AdminPermission::values())],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $validated = $validator->validated();

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
            // Invalidate existing tokens on password update
            $targetAdmin->tokens()->delete();
        } else {
            unset($validated['password']);
        }

        // Safety: If changing role or is_active on oneself
        $currentAdmin = $request->user();
        if ($targetAdmin->id === $currentAdmin->id && isset($validated['is_active']) && ! $validated['is_active']) {
            return $this->errorResponse('You cannot suspend your own admin account.', null, 400);
        }

        $targetAdmin->update($validated);

        $this->auditLogService->log(
            $currentAdmin,
            'ADMIN_UPDATED',
            'ADMINS',
            $targetAdmin,
            $before,
            [
                'name' => $targetAdmin->name,
                'role' => $targetAdmin->role->value,
                'is_active' => (bool) $targetAdmin->is_active,
                'custom_permissions' => $targetAdmin->custom_permissions,
            ]
        );

        return $this->successResponse([
            'admin' => [
                'id' => $targetAdmin->id,
                'name' => $targetAdmin->name,
                'email' => $targetAdmin->email,
                'role' => $targetAdmin->role->value,
                'is_active' => (bool) $targetAdmin->is_active,
                'permissions' => $targetAdmin->getPermissions(),
            ],
        ], 'Admin account updated successfully.');
    }

    public function toggleStatus(Request $request, int $id): JsonResponse
    {
        $targetAdmin = Admin::findOrFail($id);
        $currentAdmin = $request->user();

        if ($targetAdmin->id === $currentAdmin->id) {
            return $this->errorResponse('You cannot change the status of your own account.', null, 400);
        }

        $newActiveState = ! $targetAdmin->is_active;

        // If disabling a SUPER_ADMIN, check that at least one other active SUPER_ADMIN remains
        if (! $newActiveState && $targetAdmin->isSuperAdmin()) {
            $otherActiveSuperAdmins = Admin::where('role', AdminRole::SUPER_ADMIN)
                ->where('is_active', true)
                ->where('id', '!=', $targetAdmin->id)
                ->count();

            if ($otherActiveSuperAdmins === 0) {
                return $this->errorResponse('Cannot suspend the only remaining active Super Admin.', null, 400);
            }
        }

        $beforeState = (bool) $targetAdmin->is_active;
        $targetAdmin->update(['is_active' => $newActiveState]);

        if (! $newActiveState) {
            // Revoke all tokens immediately
            $targetAdmin->tokens()->delete();
        }

        $this->auditLogService->log(
            $currentAdmin,
            $newActiveState ? 'ADMIN_ACTIVATED' : 'ADMIN_SUSPENDED',
            'ADMINS',
            $targetAdmin,
            ['is_active' => $beforeState],
            ['is_active' => $newActiveState]
        );

        return $this->successResponse([
            'admin' => [
                'id' => $targetAdmin->id,
                'email' => $targetAdmin->email,
                'is_active' => $targetAdmin->is_active,
            ],
        ], $newActiveState ? 'Admin activated.' : 'Admin suspended and tokens revoked.');
    }
}
