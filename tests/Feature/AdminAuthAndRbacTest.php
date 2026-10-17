<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\SecurityCommandType;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(AdminRole $role = AdminRole::ADMIN, bool $isActive = true, ?array $customPerms = null): Admin
    {
        return Admin::create([
            'name' => 'Test ' . $role->value,
            'email' => strtolower($role->value) . '@sata.security',
            'password' => Hash::make('password123'),
            'role' => $role,
            'is_active' => $isActive,
            'custom_permissions' => $customPerms,
        ]);
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = $this->createAdmin(AdminRole::SUPER_ADMIN);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'admin' => [
                        'email' => $admin->email,
                        'role' => AdminRole::SUPER_ADMIN->value,
                    ],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'));
        $this->assertNotEmpty($response->json('data.permissions'));

        // Audit log created for login
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'ADMIN_LOGIN',
            'module' => 'AUTH',
        ]);
    }

    public function test_inactive_admin_cannot_login(): void
    {
        $admin = $this->createAdmin(AdminRole::ADMIN, false);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'ADMIN_ACCOUNT_SUSPENDED',
            ]);
    }

    public function test_admin_login_fails_with_invalid_credentials(): void
    {
        $admin = $this->createAdmin(AdminRole::ADMIN);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => $admin->email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_admin_can_fetch_own_identity_at_admin_me(): void
    {
        $admin = $this->createAdmin(AdminRole::ADMIN);
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'email' => $admin->email,
                        'role' => AdminRole::ADMIN->value,
                    ],
                ],
            ]);

        $this->assertContains(AdminPermission::ACTIVATION_CODES_GENERATE->value, $response->json('data.admin.permissions'));
    }

    public function test_admin_can_change_password_and_old_tokens_revoked(): void
    {
        $admin = $this->createAdmin(AdminRole::ADMIN);
        $token1 = $admin->createToken('token_1')->plainTextToken;
        $token2 = $admin->createToken('token_2')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/v1/admin/password/change', [
                'current_password' => 'password123',
                'new_password' => 'newSecurePass456!',
                'new_password_confirmation' => 'newSecurePass456!',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Old token 2 must now be revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $admin->id,
            'name' => 'token_2',
        ]);

        // Audit log recorded
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'ADMIN_PASSWORD_CHANGED',
            'module' => 'AUTH',
        ]);
    }

    public function test_suspended_admin_is_blocked_by_active_admin_middleware(): void
    {
        $admin = $this->createAdmin(AdminRole::ADMIN, true);
        $token = $admin->createToken('admin_token')->plainTextToken;

        // Suspend admin
        $admin->update(['is_active' => false]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/me');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'ACCOUNT_SUSPENDED',
            ]);
    }

    public function test_super_admin_has_full_access(): void
    {
        $superAdmin = $this->createAdmin(AdminRole::SUPER_ADMIN);
        $token = $superAdmin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertTrue($superAdmin->hasPermission(AdminPermission::ADMINS_MANAGE));
        $this->assertTrue($superAdmin->hasPermission(AdminPermission::SECURITY_COMMANDS_EXECUTE));
        $this->assertTrue($superAdmin->hasPermission(AdminPermission::USERS_DISABLE));
    }

    public function test_rbac_permission_check_blocks_unauthorized_role(): void
    {
        // Support role does NOT have audit_logs.view permission
        $support = $this->createAdmin(AdminRole::SUPPORT);
        $token = $support->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'FORBIDDEN',
            ]);
    }

    public function test_custom_permission_grants_access_override(): void
    {
        // Support role with custom permission to view audit logs
        $support = $this->createAdmin(AdminRole::SUPPORT, true, [
            AdminPermission::AUDIT_LOGS_VIEW->value,
        ]);
        $token = $support->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/admin/audit-logs');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_support_role_cannot_execute_destructive_commands_without_permission(): void
    {
        $support = $this->createAdmin(AdminRole::SUPPORT);
        $token = $support->createToken('admin_token')->plainTextToken;

        $user = User::create([
            'name' => 'Target User',
            'phone_number' => '+15559876543',
            'status' => UserStatus::ACTIVE,
            'is_active' => true,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'support-test-device-uuid',
            'device_name' => 'Target Phone',
            'device_model' => 'Pixel 9',
            'is_active' => true,
        ]);

        // Attempt destructive command KIOSK_LOCK or FACTORY_RESET
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/security-commands', [
                'device_id' => $device->id,
                'command_type' => SecurityCommandType::FACTORY_RESET->value,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'COMMAND_EXECUTION_FORBIDDEN',
            ]);
    }

    public function test_super_admin_can_execute_destructive_commands(): void
    {
        $superAdmin = $this->createAdmin(AdminRole::SUPER_ADMIN);
        $token = $superAdmin->createToken('admin_token')->plainTextToken;

        $user = User::create([
            'name' => 'Target User',
            'phone_number' => '+15559876544',
            'status' => UserStatus::ACTIVE,
            'is_active' => true,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'super-test-device-uuid',
            'device_name' => 'Super Target Phone',
            'device_model' => 'Samsung S24',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/security-commands', [
                'device_id' => $device->id,
                'command_type' => SecurityCommandType::FACTORY_RESET->value,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'command' => [
                        'command_type' => SecurityCommandType::FACTORY_RESET->value,
                        'device_id' => $device->id,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('security_commands', [
            'device_id' => $device->id,
            'command_type' => SecurityCommandType::FACTORY_RESET->value,
            'requested_by_admin_id' => $superAdmin->id,
        ]);
    }

    public function test_support_with_destructive_permission_can_execute(): void
    {
        $support = $this->createAdmin(AdminRole::SUPPORT, true, [
            'security_commands.destructive',
        ]);
        $token = $support->createToken('admin_token')->plainTextToken;

        $user = User::create([
            'name' => 'Target User',
            'phone_number' => '+15559876545',
            'status' => UserStatus::ACTIVE,
            'is_active' => true,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'support-elevated-device-uuid',
            'device_name' => 'Elevated Phone',
            'device_model' => 'OnePlus 12',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/security-commands', [
                'device_id' => $device->id,
                'command_type' => SecurityCommandType::FACTORY_RESET->value,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);
    }

    public function test_super_admin_can_manage_admins(): void
    {
        $superAdmin = $this->createAdmin(AdminRole::SUPER_ADMIN);
        $token = $superAdmin->createToken('admin_token')->plainTextToken;

        // Create new admin
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/admins', [
                'name' => 'New Support Member',
                'email' => 'newsupport@sata.security',
                'password' => 'supportSecret123',
                'role' => AdminRole::SUPPORT->value,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'email' => 'newsupport@sata.security',
                        'role' => AdminRole::SUPPORT->value,
                    ],
                ],
            ]);

        $newAdminId = $response->json('data.admin.id');

        // Toggle suspend status
        $toggleResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/admin/admins/{$newAdminId}/toggle-status");

        $toggleResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'is_active' => false,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('admins', [
            'id' => $newAdminId,
            'is_active' => false,
        ]);
    }

    public function test_admin_direct_endpoints_login_and_me(): void
    {
        $admin = $this->createAdmin(AdminRole::ADMIN);

        $loginResp = $this->postJson('/admin/login', [
            'email' => $admin->email,
            'password' => 'password123',
        ]);

        $loginResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'email' => $admin->email,
                    ],
                ],
            ]);

        $token = $loginResp->json('data.token');

        $meResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/admin/me');

        $meResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'admin' => [
                        'id' => $admin->id,
                        'email' => $admin->email,
                    ],
                ],
            ]);

        $logoutResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/admin/logout');

        $logoutResp->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}
