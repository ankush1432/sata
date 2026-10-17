<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SecurityCommandType;
use App\Enums\SubscriptionStatus;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\SecurityCommand;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSecurityCommandPhase12Test extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@sata.security',
            'password' => Hash::make('Secret123!'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    protected function createSupportAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Support Staff',
            'email' => 'support@sata.security',
            'password' => Hash::make('Secret123!'),
            'role' => AdminRole::SUPPORT,
            'is_active' => true,
        ]);
    }

    protected function createTestUserWithDevice(
        string $platform = 'android',
        string $mode = 'STANDARD_APP',
        bool $hasActiveSub = true,
        DeviceStatus $deviceStatus = DeviceStatus::ACTIVE
    ): array {
        $user = User::create([
            'name' => 'Security Test User',
            'email' => 'user-' . uniqid() . '@sata.security',
            'mobile' => '+1555' . rand(1000000, 9999999),
            'password' => Hash::make('userpassword123'),
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'device_name' => 'Test ' . ucfirst($platform) . ' Device',
            'platform' => $platform,
            'device_management_mode' => $mode,
            'status' => $deviceStatus,
            'is_protection_enabled' => $deviceStatus === DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
            'revoked_at' => $deviceStatus === DeviceStatus::REVOKED ? now() : null,
        ]);

        if ($hasActiveSub) {
            $plan = ActivationPlan::create([
                'name' => 'Pro Protection Plan',
                'code' => 'PRO-' . uniqid(),
                'duration_days' => 365,
                'price' => 49.99,
                'max_devices' => 3,
                'max_trusted_users' => 2,
                'status' => 'ACTIVE',
            ]);

            Subscription::create([
                'user_id' => $user->id,
                'activation_plan_id' => $plan->id,
                'device_id' => $device->id,
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => now()->subDay(),
                'expires_at' => now()->addDays(364),
            ]);
        }

        return [$user, $device];
    }

    /**
     * TEST 1: Authorization
     * Unauthenticated request is rejected with 401.
     */
    public function test_unauthenticated_request_cannot_issue_commands(): void
    {
        [$user, $device] = $this->createTestUserWithDevice();

        $response = $this->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
            'command_type' => 'KIOSK_LOCK',
            'reason' => 'Emergency theft report',
        ]);

        $response->assertStatus(401);
    }

    /**
     * TEST 2: Wrong user/device ownership
     * Reject if device does not belong to specified user.
     * Backend strictly derives user_id and device_id.
     */
    public function test_wrong_user_device_ownership_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        [$userA, $deviceA] = $this->createTestUserWithDevice();
        [$userB, $deviceB] = $this->createTestUserWithDevice();

        // Attempt to issue command targeting userA but passing deviceB
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$userA->id}/devices/{$deviceB->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Ownership mismatch test',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_OWNERSHIP_MISMATCH',
            ]);
    }

    /**
     * TEST 3: Revoked device
     * Security commands must not be dispatched to revoked devices.
     */
    public function test_revoked_device_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'DEVICE_OWNER',
            hasActiveSub: true,
            deviceStatus: DeviceStatus::REVOKED
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'FACTORY_RESET',
                'reason' => 'Stolen device decommission',
                'password' => 'Secret123!',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_REVOKED',
            ]);
    }

    /**
     * TEST 4: Expired subscription
     * Users without active subscription are rejected.
     */
    public function test_expired_subscription_blocks_security_command(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'DEVICE_OWNER',
            hasActiveSub: false
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Expired sub lock test',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SUBSCRIPTION_EXPIRED',
            ]);
    }

    /**
     * TEST 5: Unsupported capability
     * Normal Android/iOS standard app cannot perform factory reset or kiosk lock.
     * Must return UNSUPPORTED and never fake success.
     */
    public function test_unsupported_capability_recorded_and_never_faked(): void
    {
        $admin = $this->createSuperAdmin();
        // Standard user-space app without Device Owner provisioning
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'STANDARD_APP',
            hasActiveSub: true
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'FACTORY_RESET',
                'reason' => 'Testing capability enforcement',
                'password' => 'Secret123!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'UNSUPPORTED',
                'supported' => false,
            ]);

        $this->assertDatabaseHas('security_commands', [
            'device_id' => $device->id,
            'command_type' => 'FACTORY_RESET',
            'status' => 'UNSUPPORTED',
        ]);
    }

    /**
     * TEST 6: Duplicate request
     * A second command of the same type while another is in-flight is rejected with 409.
     */
    public function test_duplicate_in_flight_command_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'DEVICE_OWNER',
            hasActiveSub: true
        );

        // First dispatch succeeds
        $res1 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Emergency lock 1',
            ]);

        $res1->assertStatus(201)
            ->assertJson([
                'success' => true,
                'status' => 'SENT',
                'supported' => true,
            ]);

        // Immediate duplicate request must be rejected
        $res2 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Emergency lock duplicate',
            ]);

        $res2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'DUPLICATE_COMMAND_REQUEST',
            ]);
    }

    /**
     * TEST 7: Unauthorized admin
     * Support admin cannot execute destructive commands without custom permissions.
     */
    public function test_support_admin_forbidden_from_destructive_commands(): void
    {
        $supportAdmin = $this->createSupportAdmin();
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'DEVICE_OWNER',
            hasActiveSub: true
        );

        $response = $this->actingAs($supportAdmin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'FACTORY_RESET',
                'reason' => 'Support attempting destructive wipe',
                'password' => 'Secret123!',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'COMMAND_EXECUTION_FORBIDDEN',
            ]);
    }

    /**
     * TEST 8: Re-authentication
     * Destructive commands require admin password verification.
     */
    public function test_reauthentication_required_and_verified_for_destructive_commands(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'DEVICE_OWNER',
            hasActiveSub: true
        );

        // 8a: Missing password -> 401 REAUTHENTICATION_REQUIRED
        $noPass = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'WIPE_DATA',
                'reason' => 'Wipe without re-auth',
            ]);

        $noPass->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'REAUTHENTICATION_REQUIRED',
            ]);

        // 8b: Wrong password -> 401 INVALID_REAUTHENTICATION_CREDENTIALS
        $wrongPass = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'WIPE_DATA',
                'reason' => 'Wipe with wrong pass',
                'password' => 'IncorrectPassword!',
            ]);

        $wrongPass->assertStatus(401)
            ->assertJson([
                'success' => false,
                'error_code' => 'INVALID_REAUTHENTICATION_CREDENTIALS',
            ]);

        // 8c: Valid password -> 201 Created and audit logged
        $validPass = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'WIPE_DATA',
                'reason' => 'Wipe with valid pass',
                'password' => 'Secret123!',
            ]);

        $validPass->assertStatus(201)
            ->assertJson([
                'success' => true,
                'supported' => true,
            ]);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'SECURITY_COMMAND_REAUTH_VERIFIED',
        ]);
    }

    /**
     * TEST 9: Command status transitions & lifecycle
     * PENDING -> SENT -> ACKNOWLEDGED -> EXECUTING -> COMPLETED
     * Terminal state protection & expiration.
     */
    public function test_command_status_transitions_and_expiration(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice(
            platform: 'android',
            mode: 'DEVICE_OWNER',
            hasActiveSub: true
        );

        // 1. Dispatch
        $createRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Lifecycle transition test',
            ]);

        $createRes->assertStatus(201);
        $commandUuid = $createRes->json('data.command.command_uuid');

        // 2. Transition to ACKNOWLEDGED
        $ackRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$commandUuid}/transition", [
                'status' => 'ACKNOWLEDGED',
                'message' => 'Device acknowledged push wake-up',
            ]);

        $ackRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'command' => [
                    'status' => 'ACKNOWLEDGED',
                ],
            ]);

        // 3. Transition to EXECUTING
        $execRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$commandUuid}/transition", [
                'status' => 'EXECUTING',
                'message' => 'Device pinning lock-task mode',
            ]);

        $execRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'command' => [
                    'status' => 'EXECUTING',
                ],
            ]);

        // 4. Transition to COMPLETED
        $compRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$commandUuid}/transition", [
                'status' => 'COMPLETED',
                'message' => 'Kiosk lock active and confirmed',
            ]);

        $compRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'command' => [
                    'status' => 'COMPLETED',
                ],
            ]);

        // 5. Terminal state protection: Cannot transition COMPLETED to EXECUTING
        $terminalViolation = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$commandUuid}/transition", [
                'status' => 'EXECUTING',
            ]);

        $terminalViolation->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'COMMAND_ALREADY_TERMINATED',
            ]);

        // 6. Query by command UUID
        $queryRes = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/security-commands/{$commandUuid}");

        $queryRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'command' => [
                        'command_uuid' => $commandUuid,
                        'status' => 'COMPLETED',
                    ],
                ],
            ]);

        // 7. Query device commands list
        $devCmds = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}/devices/{$device->id}/commands");

        $devCmds->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 1,
                ],
            ]);
    }
}
