<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\CommandStatus;
use App\Enums\DeviceStatus;
use App\Enums\NotificationType;
use App\Enums\SecurityCommandType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\ActivationCode;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Models\Subscription;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ScheduledJobsPhase16Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Freeze time to a deterministic point: 2026-06-01 12:00:00 UTC
        $this->travelTo(Carbon::parse('2026-06-01 12:00:00'));
    }

    private function createAdmin(AdminRole $role = AdminRole::SUPER_ADMIN, array $permissions = []): Admin
    {
        return Admin::create([
            'name' => 'Security Admin',
            'email' => 'admin-' . Str::random(8) . '@sata.security',
            'password' => Hash::make('AdminPass123!'),
            'role' => $role,
            'is_active' => true,
            'permissions' => $permissions ?: AdminPermission::values(),
        ]);
    }

    private function createUser(string $phone = '+15551001001'): User
    {
        return User::create([
            'name' => 'Protected User',
            'email' => 'user-' . Str::random(6) . '@example.com',
            'phone_number' => $phone,
            'status' => UserStatus::ACTIVE,
            'mobile_verified_at' => now(),
        ]);
    }

    private function createDevice(User $user, string $uuid = 'device-phase16-01'): UserDevice
    {
        return UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => $uuid,
            'device_name' => 'Pixel 9 Pro',
            'platform' => 'ANDROID',
            'device_management_mode' => 'DEVICE_OWNER',
            'status' => DeviceStatus::ACTIVE,
            'is_protection_enabled' => true,
            'last_seen_at' => now(),
        ]);
    }

    private function createSubscription(User $user, UserDevice $device, Carbon $expiresAt, SubscriptionStatus $status = SubscriptionStatus::ACTIVE): Subscription
    {
        $plan = ActivationPlan::create([
            'name' => 'Standard Protection',
            'code' => 'PLAN_' . Str::random(6),
            'duration_days' => 30,
            'price' => 29.99,
            'is_active' => true,
        ]);

        $code = ActivationCode::create([
            'activation_plan_id' => $plan->id,
            'code' => 'SATA-' . strtoupper(Str::random(12)),
            'is_used' => true,
            'status' => 'USED',
            'used_by_user_id' => $user->id,
            'used_at' => now()->subDays(10),
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'activation_plan_id' => $plan->id,
            'activation_code_id' => $code->id,
            'status' => $status,
            'starts_at' => now()->subDays(10),
            'expires_at' => $expiresAt,
            'activated_at' => now()->subDays(10),
            'reminder_stages_sent' => [],
        ]);
    }

    /**
     * Test 1: Subscriptions process-expiry transitions ACTIVE to EXPIRING when approaching expiry.
     */
    public function test_subscriptions_process_expiry_transitions_active_to_expiring(): void
    {
        $user1 = $this->createUser('+15551001001');
        $device1 = $this->createDevice($user1, 'device-far');
        // Far expiry: 15 days in future -> stays ACTIVE
        $sub1 = $this->createSubscription($user1, $device1, now()->addDays(15), SubscriptionStatus::ACTIVE);

        $user2 = $this->createUser('+15551001002');
        $device2 = $this->createDevice($user2, 'device-near');
        // Within default threshold (7 days): 5 days in future -> transitions to EXPIRING
        $sub2 = $this->createSubscription($user2, $device2, now()->addDays(5), SubscriptionStatus::ACTIVE);

        Artisan::call('subscriptions:process-expiry');

        $this->assertEquals(SubscriptionStatus::ACTIVE, $sub1->fresh()->status);
        $this->assertEquals(SubscriptionStatus::EXPIRING, $sub2->fresh()->status);
        $this->assertTrue($device2->fresh()->is_protection_enabled);
    }

    /**
     * Test 2: Subscriptions process-expiry transitions ACTIVE and EXPIRING to EXPIRED at expiry.
     */
    public function test_subscriptions_process_expiry_transitions_to_expired_at_expiry(): void
    {
        $user = $this->createUser('+15551001003');
        $device = $this->createDevice($user, 'device-expire');
        // Subscription expires in 10 days
        $sub = $this->createSubscription($user, $device, now()->addDays(10), SubscriptionStatus::ACTIVE);

        // Advance time by 11 days (past expiry)
        $this->travel(11)->days();

        Artisan::call('subscriptions:process-expiry');

        $freshSub = $sub->fresh();
        $this->assertEquals(SubscriptionStatus::EXPIRED, $freshSub->status);
        // Protection deactivated on device
        $this->assertFalse($device->fresh()->is_protection_enabled);

        // Verification of expired notification
        $notification = Notification::where('user_id', $user->id)
            ->where('type', NotificationType::SUBSCRIPTION_EXPIRED->value)
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('Expired', $notification->title);
    }

    /**
     * Test 3: Processing expiry is idempotent and never deletes user, devices, events, evidence, or trusted users.
     */
    public function test_process_expiry_is_idempotent_and_preserves_all_related_records(): void
    {
        $user = $this->createUser('+15551001004');
        $device = $this->createDevice($user, 'device-preserve');
        $sub = $this->createSubscription($user, $device, now()->addDays(1), SubscriptionStatus::ACTIVE);

        // Attach security event, evidence, and trusted user
        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => 'SIM_CHANGED',
            'severity' => 'CRITICAL',
            'status' => 'PENDING',
            'metadata' => ['old_imsi' => '12345', 'new_imsi' => '67890'],
            'occurred_at' => now(),
        ]);

        $evidence = SecurityEvidence::create([
            'security_event_id' => $event->id,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'evidence_type' => 'FRONT_PHOTO',
            'file_path' => 'evidence/test.jpg',
            'file_size' => 1024,
            'mime_type' => 'image/jpeg',
            'captured_at' => now(),
        ]);

        $trustedUser = TrustedUser::create([
            'user_id' => $user->id,
            'name' => 'Trusted Contact',
            'phone_number' => '+15559998888',
            'relationship' => 'SPOUSE',
            'is_verified' => true,
            'status' => 'VERIFIED',
        ]);

        // Travel past expiry
        $this->travel(2)->days();

        // Run command twice
        Artisan::call('subscriptions:process-expiry');
        Artisan::call('subscriptions:process-expiry');

        // Assert all data is preserved intact
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('user_devices', ['id' => $device->id]);
        $this->assertDatabaseHas('security_events', ['id' => $event->id]);
        $this->assertDatabaseHas('security_evidence', ['id' => $evidence->id]);
        $this->assertDatabaseHas('trusted_users', ['id' => $trustedUser->id]);
        $this->assertEquals(SubscriptionStatus::EXPIRED, $sub->fresh()->status);

        // Exactly 1 expired notification generated (idempotent)
        $count = Notification::where('user_id', $user->id)
            ->where('type', NotificationType::SUBSCRIPTION_EXPIRED->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    /**
     * Test 4: Notification reminders are dispatched at 7, 3, and 1 days before expiry, strictly once per stage.
     */
    public function test_notification_reminders_multi_stage_time_travel_idempotency(): void
    {
        $user = $this->createUser('+15551001005');
        $device = $this->createDevice($user, 'device-stages');
        // Initial setup: expires in exactly 10 days
        $sub = $this->createSubscription($user, $device, now()->addDays(10), SubscriptionStatus::ACTIVE);

        // Day 10 before expiry: No reminders should fire
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(0, Notification::where('user_id', $user->id)->count());

        // Travel 3 days forward -> 7 days remaining
        $this->travel(3)->days();
        Artisan::call('notifications:process-reminders');

        $this->assertEquals(1, Notification::where('user_id', $user->id)->count());
        $freshSub = $sub->fresh();
        $this->assertTrue($freshSub->hasReminderBeenSentForStage(7));
        $this->assertContains(7, $freshSub->reminder_stages_sent);

        // Re-run on same day (idempotent check)
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(1, Notification::where('user_id', $user->id)->count());

        // Travel 2 days forward -> 5 days remaining (stage 7 sent, stage 3 not reached)
        $this->travel(2)->days();
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(1, Notification::where('user_id', $user->id)->count());

        // Travel 2 days forward -> 3 days remaining (stage 3 trigger)
        $this->travel(2)->days();
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(2, Notification::where('user_id', $user->id)->count());
        $freshSub = $sub->fresh();
        $this->assertTrue($freshSub->hasReminderBeenSentForStage(3));
        $this->assertContains(3, $freshSub->reminder_stages_sent);

        // Re-run on same day
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(2, Notification::where('user_id', $user->id)->count());

        // Travel 2 days forward -> 1 day remaining (stage 1 trigger)
        $this->travel(2)->days();
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(3, Notification::where('user_id', $user->id)->count());
        $freshSub = $sub->fresh();
        $this->assertTrue($freshSub->hasReminderBeenSentForStage(1));
        $this->assertContains(1, $freshSub->reminder_stages_sent);

        // Re-run on same day
        Artisan::call('notifications:process-reminders');
        $this->assertEquals(3, Notification::where('user_id', $user->id)->count());
    }

    /**
     * Test 5: Configurable reminder stages via CLI option.
     */
    public function test_notification_reminders_supports_custom_stages_option(): void
    {
        $user = $this->createUser('+15551001006');
        $device = $this->createDevice($user, 'device-custom-stages');
        // Expires in 14 days
        $sub = $this->createSubscription($user, $device, now()->addDays(14), SubscriptionStatus::ACTIVE);

        // Pass custom stages: 14, 5 days
        Artisan::call('notifications:process-reminders', ['--stages' => '14,5']);

        $this->assertEquals(1, Notification::where('user_id', $user->id)->count());
        $this->assertTrue($sub->fresh()->hasReminderBeenSentForStage(14));
    }

    /**
     * Test 6: Devices mark-offline marks stale devices INACTIVE after inactivity threshold without deleting records.
     */
    public function test_devices_mark_offline_transitions_stale_devices_and_preserves_records(): void
    {
        $user = $this->createUser('+15551001007');
        // Device 1: Active and seen 2 hours ago -> should remain ACTIVE
        $recentDevice = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'dev-recent',
            'device_name' => 'Recent Device',
            'platform' => 'ANDROID',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now()->subHours(2),
        ]);

        // Device 2: Active and seen 25 hours ago -> should transition to INACTIVE
        $staleDevice = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'dev-stale',
            'device_name' => 'Stale Device',
            'platform' => 'ANDROID',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now()->subHours(25),
        ]);

        // Device 3: Active with NULL last_seen_at created 30 hours ago -> should transition to INACTIVE
        $nullSeenDevice = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'dev-null-seen',
            'device_name' => 'Null Seen Device',
            'platform' => 'ANDROID',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => null,
        ]);
        \Illuminate\Support\Facades\DB::table('user_devices')
            ->where('id', $nullSeenDevice->id)
            ->update(['created_at' => now()->subHours(30)]);

        Artisan::call('devices:mark-offline');

        $this->assertEquals(DeviceStatus::ACTIVE, $recentDevice->fresh()->status);
        $this->assertEquals(DeviceStatus::INACTIVE, $staleDevice->fresh()->status);
        $this->assertEquals(DeviceStatus::INACTIVE, $nullSeenDevice->fresh()->status);

        // Re-run: Idempotent
        Artisan::call('devices:mark-offline');
        $this->assertEquals(DeviceStatus::INACTIVE, $staleDevice->fresh()->status);
    }

    /**
     * Test 7: Security commands expire sweeps expired pending commands and creates audit log.
     */
    public function test_security_commands_expire_sweeps_past_ttl_commands_idempotently(): void
    {
        $user = $this->createUser('+15551001008');
        $device = $this->createDevice($user, 'dev-cmd-expire');

        // Command 1: expires in 2 hours -> remains PENDING
        $cmdActive = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'command_type' => SecurityCommandType::KIOSK_LOCK,
            'status' => CommandStatus::PENDING,
            'expires_at' => now()->addHours(2),
            'issued_at' => now()->subHours(22),
        ]);

        // Command 2: expired 1 hour ago -> transitions to EXPIRED
        $cmdExpired = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'command_type' => SecurityCommandType::FACTORY_RESET,
            'status' => CommandStatus::PENDING,
            'expires_at' => now()->subHour(),
            'issued_at' => now()->subHours(25),
        ]);

        // Command 3: Already COMPLETED -> remains COMPLETED even if past expires_at
        $cmdCompleted = SecurityCommand::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'command_uuid' => (string) Str::uuid(),
            'command_type' => SecurityCommandType::WIPE_DATA,
            'status' => CommandStatus::COMPLETED,
            'expires_at' => now()->subHour(),
            'issued_at' => now()->subHours(25),
        ]);

        Artisan::call('security-commands:expire');

        $this->assertEquals(CommandStatus::PENDING, $cmdActive->fresh()->status);
        $this->assertEquals(CommandStatus::EXPIRED, $cmdExpired->fresh()->status);
        $this->assertEquals(CommandStatus::COMPLETED, $cmdCompleted->fresh()->status);

        // Verify audit log created
        $log = SecurityCommandLog::where('security_command_id', $cmdExpired->id)
            ->where('status_to', CommandStatus::EXPIRED->value)
            ->first();
        $this->assertNotNull($log);

        // Re-run command: Idempotent
        Artisan::call('security-commands:expire');
        $this->assertEquals(CommandStatus::EXPIRED, $cmdExpired->fresh()->status);
    }

    /**
     * Test 8: After expiry, sensitive protection operations are rejected and new commands blocked,
     * while existing history remains accessible.
     */
    public function test_post_expiry_rejects_sensitive_operations_and_preserves_history_access(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createUser('+15551001009');
        $device = $this->createDevice($user, 'device-post-expiry');
        $sub = $this->createSubscription($user, $device, now()->addDays(2), SubscriptionStatus::ACTIVE);

        // Travel 3 days forward -> expired
        $this->travel(3)->days();
        Artisan::call('subscriptions:process-expiry');

        $this->assertEquals(SubscriptionStatus::EXPIRED, $sub->fresh()->status);

        // 1. Attempt sensitive protection operation: Start live location tracking session -> REJECTED (403)
        $responseLocation = $this->actingAs($admin, 'sanctum')->postJson("/admin/users/{$user->id}/location/session", [
            'duration_minutes' => 60,
        ]);
        $responseLocation->assertStatus(403);
        $responseLocation->assertJsonPath('error_code', 'PROTECTION_INACTIVE');

        // 2. Attempt dispatching new security command -> BLOCKED (403)
        $responseCommand = $this->actingAs($admin, 'sanctum')->postJson("/admin/users/{$user->id}/devices/{$device->id}/commands", [
            'command_type' => SecurityCommandType::FACTORY_RESET->value,
            'reason' => 'Emergency reset',
            'admin_password' => 'AdminPass123!',
        ]);
        $responseCommand->assertStatus(403);
        $responseCommand->assertJsonPath('error_code', 'SUBSCRIPTION_EXPIRED');

        // 3. Existing history remains accessible to authorized callers
        // View user details
        $responseUser = $this->actingAs($admin, 'sanctum')->getJson("/admin/users/{$user->id}");
        $responseUser->assertStatus(200);

        // View subscription list
        $responseSub = $this->actingAs($admin, 'sanctum')->getJson('/admin/subscriptions');
        $responseSub->assertStatus(200);

        // View devices list
        $responseDev = $this->actingAs($admin, 'sanctum')->getJson('/admin/devices');
        $responseDev->assertStatus(200);
    }

    /**
     * Test 9: Admin scheduled-jobs metadata and status endpoint.
     */
    public function test_admin_can_view_scheduled_jobs_status_and_metrics(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')->getJson('/admin/scheduled-jobs');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'config' => [
                    'reminder_stages',
                    'expiring_threshold_days',
                    'device_offline_threshold_hours',
                    'command_expiration_hours',
                ],
                'stats' => [
                    'subscriptions',
                    'devices',
                    'security_commands',
                ],
                'jobs',
                'timestamp',
            ],
        ]);
    }

    /**
     * Test 10: Admin can manually trigger scheduled automation on demand.
     */
    public function test_admin_can_manually_trigger_scheduled_jobs(): void
    {
        $admin = $this->createAdmin();

        $response = $this->actingAs($admin, 'sanctum')->postJson('/admin/scheduled-jobs/run', [
            'job' => 'all',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'job',
                'outputs' => [
                    'subscriptions:process-expiry',
                    'notifications:process-reminders',
                    'devices:mark-offline',
                    'security-commands:expire',
                ],
                'executed_at',
            ],
        ]);
    }
}
