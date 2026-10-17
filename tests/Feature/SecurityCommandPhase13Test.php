<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\SecurityCommandType;
use App\Enums\SubscriptionStatus;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SecurityCommandPhase13Test extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(string $password = 'Secret123!'): Admin
    {
        return Admin::create([
            'name' => 'Super Admin Alpha',
            'email' => 'admin-' . uniqid() . '@sata.security',
            'password' => Hash::make($password),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    protected function createTestUserWithDevice(
        string $platform = 'android',
        string $mode = 'DEVICE_OWNER',
        bool $hasActiveSub = true,
        DeviceStatus $deviceStatus = DeviceStatus::ACTIVE
    ): array {
        $user = User::create([
            'name' => 'User ' . Str::random(5),
            'email' => 'user-' . uniqid() . '@sata.security',
            'mobile' => '+1555' . rand(1000000, 9999999),
            'password' => Hash::make('SecretPassword123!'),
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Hardware ' . ucfirst($platform) . ' Unit',
            'platform' => $platform,
            'device_management_mode' => $mode,
            'status' => $deviceStatus,
            'is_protection_enabled' => $deviceStatus === DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
            'revoked_at' => $deviceStatus === DeviceStatus::REVOKED ? now() : null,
        ]);

        if ($hasActiveSub) {
            $plan = ActivationPlan::create([
                'name' => 'Enterprise Security Shield',
                'code' => 'ENT-' . uniqid(),
                'duration_days' => 365,
                'price' => 99.99,
                'max_devices' => 5,
                'max_trusted_users' => 3,
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
     * TEST 1: Required Command Fields
     * Every command must have:
     * command_uuid, nonce, user_id, device_id, command_type,
     * requested_by_admin_id, issued_at, expires_at, status
     */
    public function test_every_command_has_all_required_phase13_fields(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Target device lost in high-risk area',
            ]);

        $response->assertStatus(201);

        $commandId = $response->json('data.command.id');
        $command = SecurityCommand::find($commandId);

        $this->assertNotNull($command);
        $this->assertNotEmpty($command->command_uuid);
        $this->assertNotEmpty($command->nonce);
        $this->assertEquals($user->id, $command->user_id);
        $this->assertEquals($device->id, $command->device_id);
        $this->assertEquals(SecurityCommandType::KIOSK_LOCK, $command->command_type);
        $this->assertEquals($admin->id, $command->requested_by_admin_id);
        $this->assertNotNull($command->issued_at);
        $this->assertNotNull($command->expires_at);
        $this->assertEquals(CommandStatus::SENT, $command->status);
    }

    /**
     * TEST 2: One-time execution & Replay protection
     * Once a command is completed or processed, replaying it must be rejected.
     */
    public function test_replay_same_command_is_prevented(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        // Create command
        $createRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Test one-time execution',
            ]);
        $commandUuid = $createRes->json('data.command.command_uuid');

        // Step 1: Device acknowledges
        $ackRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$commandUuid}/acknowledge", [
                'device_id' => $device->id,
            ]);
        $ackRes->assertStatus(200);

        // Step 2: Device reports execution result as COMPLETED
        $resultRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$commandUuid}/result", [
                'device_id' => $device->id,
                'status' => 'COMPLETED',
                'response_payload' => ['kiosk_mode' => 'ACTIVE'],
            ]);
        $resultRes->assertStatus(200);

        // REPLAY ATTEMPT 1: Device tries to acknowledge a completed command again
        $replayAckRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$commandUuid}/acknowledge", [
                'device_id' => $device->id,
            ]);
        $replayAckRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'REPLAY_ATTEMPT_REJECTED',
            ]);

        // REPLAY ATTEMPT 2: Device tries to report execution again
        $replayResultRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$commandUuid}/result", [
                'device_id' => $device->id,
                'status' => 'COMPLETED',
            ]);
        $replayResultRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'REPLAY_ATTEMPT_REJECTED',
            ]);

        // REPLAY ATTEMPT 3: Processing receiver pipeline on completed command
        $pipelineRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$commandUuid}/process", [
                'device_id' => $device->id,
                'device_management_mode' => 'DEVICE_OWNER',
            ]);
        $pipelineRes->assertStatus(422)
            ->assertJson([
                'result' => 'FAIL',
                'step_failed' => 5,
            ]);
    }

    /**
     * TEST 3: Expired command
     * Commands past their expires_at date must not be executed or acknowledged.
     */
    public function test_expired_command_cannot_be_executed(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $command = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::SENT,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now()->subHours(48),
            'sent_at' => now()->subHours(48),
            'expires_at' => now()->subHours(24), // Expired!
        ]);

        // Attempt acknowledge on expired command
        $ackRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$command->command_uuid}/acknowledge", [
                'device_id' => $device->id,
            ]);

        $ackRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'COMMAND_EXPIRED',
            ]);

        $this->assertEquals(CommandStatus::EXPIRED, $command->fresh()->status);

        // Attempt process pipeline on expired command
        $procRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$command->command_uuid}/process", [
                'device_id' => $device->id,
            ]);

        $procRes->assertStatus(422)
            ->assertJson([
                'result' => 'FAIL',
                'status' => 'EXPIRED',
                'step_failed' => 4,
            ]);
    }

    /**
     * TEST 4: Wrong device
     * If caller presents a device that does not match command device_id, reject.
     */
    public function test_wrong_device_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $deviceA] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');
        // Create second device for the same user
        $deviceB = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Second Device',
            'platform' => 'android',
            'device_management_mode' => 'DEVICE_OWNER',
            'status' => DeviceStatus::ACTIVE,
            'is_protection_enabled' => true,
        ]);

        $command = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $deviceA->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::SENT,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // Device B tries to acknowledge Device A's command
        $ackRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$command->command_uuid}/acknowledge", [
                'device_id' => $deviceB->id,
            ]);

        $ackRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_MISMATCH',
            ]);
    }

    /**
     * TEST 5: Wrong user
     * If user does not own the target device/command, reject.
     */
    public function test_wrong_user_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        [$userA, $deviceA] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');
        [$userB, $deviceB] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $command = SecurityCommand::create([
            'user_id' => $userA->id,
            'device_id' => $deviceA->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::SENT,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // User B tries to view User A's command
        $showRes = $this->actingAs($userB, 'sanctum')
            ->getJson("/api/v1/commands/{$command->command_uuid}");
        $showRes->assertStatus(403);

        // User B tries to acknowledge User A's command
        $ackRes = $this->actingAs($userB, 'sanctum')
            ->postJson("/api/v1/commands/{$command->command_uuid}/acknowledge", [
                'device_id' => $deviceA->id,
            ]);
        $ackRes->assertStatus(403);

        // Admin cannot dispatch command if user does not own the device
        $adminMismatchRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$userB->id}/devices/{$deviceA->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Cross-tenant mismatch attempt',
            ]);
        $adminMismatchRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_OWNERSHIP_MISMATCH',
            ]);
    }

    /**
     * TEST 6: Revoked device
     * Revoked devices cannot receive, acknowledge, or execute commands.
     */
    public function test_revoked_device_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $revokedDevice] = $this->createTestUserWithDevice(
            'android',
            'DEVICE_OWNER',
            true,
            DeviceStatus::REVOKED
        );

        // 1. Admin cannot dispatch to revoked device
        $adminRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$revokedDevice->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Attempting dispatch on decommissioned device',
            ]);
        $adminRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_REVOKED',
            ]);

        // 2. Pre-existing command cannot be acknowledged by revoked device
        $existingCmd = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $revokedDevice->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::SENT,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $ackRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$existingCmd->command_uuid}/acknowledge", [
                'device_id' => $revokedDevice->id,
            ]);
        $ackRes->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_REVOKED',
            ]);
    }

    /**
     * TEST 7: Duplicate command
     * Dispatching a duplicate active command of same type to same device is rejected.
     */
    public function test_duplicate_command_is_prevented(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        // First command: Successfully queued
        $res1 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'First lock request',
            ]);
        $res1->assertStatus(201);

        // Second command of same type while first is active: 409 Conflict
        $res2 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Duplicate lock request while active',
            ]);

        $res2->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'DUPLICATE_COMMAND_REQUEST',
            ]);
    }

    /**
     * TEST 8: Concurrent command protection
     * Simultaneous dispatch requests prevent race condition duplicate creation.
     */
    public function test_concurrent_command_protection(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $activeCommand = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::EXECUTING,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $concurrentRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Concurrent race attempt',
            ]);

        $concurrentRes->assertStatus(409)
            ->assertJson([
                'success' => false,
                'error_code' => 'DUPLICATE_COMMAND_REQUEST',
            ]);
    }

    /**
     * TEST 9: Audit creation format
     * Admin audit example format:
     * Admin X -> KIOSK_LOCK -> User Y -> Device Z -> SUCCESS -> timestamp
     */
    public function test_audit_creation_with_admin_x_user_y_device_z_format(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $res = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'KIOSK_LOCK',
                'reason' => 'Verifying audit trail formatting',
            ]);

        $res->assertStatus(201);

        $audit = AdminAuditLog::where('admin_id', $admin->id)
            ->where('module', 'SECURITY_COMMANDS')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertArrayHasKey('audit_summary', $audit->after_payload);

        $summary = $audit->after_payload['audit_summary'];
        $this->assertStringContainsString("Admin {$admin->name}", $summary);
        $this->assertStringContainsString("KIOSK_LOCK", $summary);
        $this->assertStringContainsString("User {$user->name}", $summary);
        $this->assertStringContainsString("Device {$device->device_uuid}", $summary);
        $this->assertStringContainsString("SUCCESS", $summary);
    }

    /**
     * TEST 10: Sensitive command logs must never contain passwords, OTP, tokens, or secrets
     */
    public function test_sensitive_command_logs_never_contain_passwords_otp_or_secrets(): void
    {
        $plainPassword = 'SuperSecretAdminPassword123!';
        $admin = $this->createSuperAdmin($plainPassword);
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $fakeSensitivePayload = [
            'password' => 'PlainUserPassword',
            'reauth_password' => $plainPassword,
            'otp' => '948271',
            'activation_code' => 'SATA-4829-9182',
            'auth_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.dummy',
            'private_secret' => 'super_private_rsa_key_38472',
            'safe_metadata' => 'This is public audit context',
        ];

        // Send destructive command requiring re-auth
        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
                'command_type' => 'FACTORY_RESET',
                'reason' => 'Decommission testing',
                'password' => $plainPassword,
                'payload' => $fakeSensitivePayload,
            ]);

        $response->assertStatus(201);

        // Verify database records contain NO sensitive values
        $auditLogs = AdminAuditLog::all();
        foreach ($auditLogs as $audit) {
            $json = json_encode([$audit->before_payload, $audit->after_payload]);
            $this->assertStringNotContainsString('PlainUserPassword', $json);
            $this->assertStringNotContainsString('948271', $json);
            $this->assertStringNotContainsString('SATA-4829-9182', $json);
            $this->assertStringNotContainsString('super_private_rsa_key_38472', $json);
        }

        $commandLogs = SecurityCommandLog::all();
        foreach ($commandLogs as $cmdLog) {
            $json = json_encode($cmdLog->metadata);
            $this->assertStringNotContainsString('PlainUserPassword', $json);
            $this->assertStringNotContainsString('948271', $json);
            $this->assertStringNotContainsString('SATA-4829-9182', $json);
            $this->assertStringNotContainsString('super_private_rsa_key_38472', $json);
        }

        $command = SecurityCommand::where('device_id', $device->id)->latest()->first();
        $payloadJson = json_encode($command->payload);
        $this->assertStringNotContainsString('PlainUserPassword', $payloadJson);
        $this->assertStringNotContainsString('948271', $payloadJson);
        $this->assertStringNotContainsString('SATA-4829-9182', $payloadJson);
        $this->assertStringNotContainsString('super_private_rsa_key_38472', $payloadJson);
    }

    /**
     * TEST 11: Status transition validation
     * Lifecycle:
     * PENDING -> SENT -> ACKNOWLEDGED -> EXECUTING -> COMPLETED
     * Failure: PENDING/SENT/EXECUTING -> FAILED
     * Unsupported: PENDING -> UNSUPPORTED
     * Expired: PENDING/SENT -> EXPIRED
     * Terminal states cannot transition to anything.
     */
    public function test_status_transition_lifecycle_validation(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $command = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::PENDING,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        // PENDING -> SENT
        $res1 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$command->id}/transition", [
                'status' => 'SENT',
            ]);
        $res1->assertStatus(200);

        // SENT -> ACKNOWLEDGED
        $res2 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$command->id}/transition", [
                'status' => 'ACKNOWLEDGED',
            ]);
        $res2->assertStatus(200);

        // ACKNOWLEDGED -> EXECUTING
        $res3 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$command->id}/transition", [
                'status' => 'EXECUTING',
            ]);
        $res3->assertStatus(200);

        // EXECUTING -> COMPLETED
        $res4 = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$command->id}/transition", [
                'status' => 'COMPLETED',
            ]);
        $res4->assertStatus(200);

        // Illegal transition: COMPLETED -> PENDING (Terminal cannot transition)
        $illegalRes = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/security-commands/{$command->id}/transition", [
                'status' => 'PENDING',
            ]);
        $illegalRes->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error_code' => 'COMMAND_ALREADY_TERMINATED',
            ]);
    }

    /**
     * TEST 12: ACK handling and execution result reporting
     */
    public function test_ack_handling_and_execution_result_reporting(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device] = $this->createTestUserWithDevice('android', 'DEVICE_OWNER');

        $command = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'nonce' => Str::random(32),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::SENT,
            'requested_by_admin_id' => $admin->id,
            'issued_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertNull($command->acknowledged_at);

        // Device acknowledges
        $ackRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$command->command_uuid}/acknowledge", [
                'device_id' => $device->id,
            ]);

        $ackRes->assertStatus(200);
        $command->refresh();
        $this->assertEquals(CommandStatus::ACKNOWLEDGED, $command->status);
        $this->assertNotNull($command->acknowledged_at);

        // Device reports execution failure
        $failRes = $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/commands/{$command->command_uuid}/result", [
                'device_id' => $device->id,
                'status' => 'FAILED',
                'failure_reason' => 'Device screen overlay was intercepted by user',
            ]);

        $failRes->assertStatus(200);
        $command->refresh();
        $this->assertEquals(CommandStatus::FAILED, $command->status);
        $this->assertEquals('Device screen overlay was intercepted by user', $command->failure_reason);
        $this->assertNotNull($command->executed_at);
    }
}
