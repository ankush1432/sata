<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Enums\EvidenceType;
use App\Enums\SecurityEventType;
use App\Enums\SubscriptionStatus;
use App\Enums\TrustedUserStatus;
use App\Enums\UserStatus;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Models\Subscription;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserDetailControlCenterPhase14Test extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(string $password = 'Secret123!'): Admin
    {
        return Admin::create([
            'name' => 'Commander Sovereign',
            'email' => 'admin-' . uniqid() . '@sata.security',
            'password' => Hash::make($password),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    protected function createSupportAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Support Agent',
            'email' => 'support-' . uniqid() . '@sata.security',
            'password' => Hash::make('Support123!'),
            'role' => AdminRole::SUPPORT_AGENT,
            'is_active' => true,
        ]);
    }

    protected function createFullControlCenterFixtures(): array
    {
        // 1. Target User
        $user = User::create([
            'name' => 'Valerie Sterling',
            'email' => 'valerie.sterling@enterprise.net',
            'mobile' => '+15559876543',
            'phone' => '+15559876543',
            'password' => Hash::make('TargetUserSecret99!'),
            'status' => UserStatus::ACTIVE,
        ]);

        // 2. Subscription with Activation Plan
        $plan = ActivationPlan::create([
            'name' => 'Apex Sentinel Shield',
            'code' => 'APEX-SENTINEL',
            'duration_days' => 365,
            'price' => 149.99,
            'max_devices' => 5,
            'max_trusted_users' => 2,
            'status' => 'ACTIVE',
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Titan Ultra S24',
            'model' => 'Samsung Galaxy S24 Ultra',
            'device_model' => 'SM-S928B',
            'platform' => 'android',
            'os_version' => 'Android 14 (OneUI 6.1)',
            'app_version' => 'v2.4.1 (Build 204)',
            'device_management_mode' => 'DEVICE_OWNER',
            'status' => DeviceStatus::ACTIVE,
            'is_protection_enabled' => true,
            'last_latitude' => 37.774929,
            'last_longitude' => -122.419416,
            'last_accuracy' => 4.2,
            'last_seen_at' => now()->subMinutes(2),
            'location_updated_at' => now()->subMinutes(2),
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'activation_plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now()->subDays(30),
            'expires_at' => now()->addDays(335),
        ]);

        // 3. Trusted Users (Max 2)
        $trustedUser1 = TrustedUser::create([
            'user_id' => $user->id,
            'name' => 'Arthur Sterling',
            'phone' => '+15551112233',
            'relationship' => 'Spouse',
            'status' => TrustedUserStatus::VERIFIED,
            'is_active' => true,
            'verified_at' => now()->subDays(10),
        ]);

        $trustedUser2 = TrustedUser::create([
            'user_id' => $user->id,
            'name' => 'Elena Drake',
            'phone' => '+15554445566',
            'relationship' => 'Legal Counsel',
            'status' => TrustedUserStatus::PENDING,
            'is_active' => true,
        ]);

        // 4. Latest Intruder Security Event & Evidence
        $intruderEvent = SecurityEvent::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_uuid' => (string) Str::uuid(),
            'event_type' => SecurityEventType::INTRUDER_PHOTO,
            'severity' => 'HIGH',
            'status' => 'REPORTED',
            'occurred_at' => now()->subHours(1),
            'triggered_at' => now()->subHours(1),
            'description' => 'Multiple incorrect lockscreen PIN attempts detected on hardware.',
        ]);

        $evidence = SecurityEvidence::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'security_event_id' => $intruderEvent->id,
            'evidence_type' => EvidenceType::FRONT_PHOTO,
            'storage_disk' => 'private_evidence',
            'storage_path' => 'evidence/intruder_captured_001.jpg',
            'file_name' => 'intruder_captured_001.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 204850,
            'captured_at' => now()->subHours(1),
        ]);

        // 5. Additional Security Events for Pagination Test
        for ($i = 1; $i <= 5; $i++) {
            SecurityEvent::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'event_uuid' => (string) Str::uuid(),
                'event_type' => SecurityEventType::WRONG_PIN_ATTEMPTS,
                'severity' => 'MEDIUM',
                'status' => 'PENDING',
                'occurred_at' => now()->subHours($i + 1),
            ]);
        }

        // 6. Notifications for User
        for ($j = 1; $j <= 4; $j++) {
            Notification::create([
                'user_id' => $user->id,
                'device_id' => $device->id,
                'title' => "Security Alert #{$j}",
                'body' => "Suspicious activity detected near registered perimeter.",
                'type' => 'SECURITY_ALERT',
                'status' => 'DELIVERED',
                'is_read' => false,
                'sent_at' => now()->subHours($j),
            ]);
        }

        return [$user, $device, $subscription, $intruderEvent, $evidence];
    }

    /**
     * TEST 1: Server-Side Authorization
     * Unauthenticated or unauthorized callers cannot access /admin/users/{user}
     */
    public function test_server_side_authorization_is_strictly_enforced(): void
    {
        [$user] = $this->createFullControlCenterFixtures();

        // 1. Unauthenticated request -> 401
        $unauthRes = $this->getJson("/admin/users/{$user->id}");
        $unauthRes->assertStatus(401);
    }

    /**
     * TEST 2: Main Control Center Route /admin/users/{user} returns all 9 sections
     */
    public function test_admin_user_detail_control_center_returns_all_nine_sections(): void
    {
        $admin = $this->createSuperAdmin();
        [$user, $device, $subscription, $intruderEvent] = $this->createFullControlCenterFixtures();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}");

        $response->assertStatus(200);

        $data = $response->json('data.control_center');
        $this->assertNotNull($data, 'control_center key must be returned in response');

        // SECTION 1: USER
        $this->assertEquals($user->name, $data['user']['name']);
        $this->assertEquals('+15559876543', $data['user']['phone']);
        $this->assertEquals($user->email, $data['user']['email']);
        $this->assertEquals('ACTIVE', $data['user']['status']);

        // SECTION 2: SUBSCRIPTION
        $this->assertEquals('Apex Sentinel Shield', $data['subscription']['plan']);
        $this->assertEquals('ACTIVE', $data['subscription']['status']);
        $this->assertNotNull($data['subscription']['activated_at']);
        $this->assertNotNull($data['subscription']['expires_at']);

        // SECTION 3: DEVICE
        $this->assertEquals($device->device_uuid, $data['device']['device_id']);
        $this->assertNotEmpty($data['device']['model']);
        $this->assertEquals('ANDROID', $data['device']['platform']);
        $this->assertEquals('Android 14 (OneUI 6.1)', $data['device']['os_version']);
        $this->assertEquals('v2.4.1 (Build 204)', $data['device']['app_version']);
        $this->assertEquals('ACTIVE', $data['device']['status']);
        $this->assertNotNull($data['device']['last_seen']);

        // SECTION 4: LIVE LOCATION
        $this->assertEquals(37.774929, $data['live_location']['latitude']);
        $this->assertEquals(-122.419416, $data['live_location']['longitude']);
        $this->assertEquals(4.2, $data['live_location']['accuracy']);
        $this->assertNotNull($data['live_location']['last_updated']);
        $this->assertNotNull($data['live_location']['map_preview']);
        $this->assertNotNull($data['live_location']['open_map_button']);
        $this->assertNotNull($data['live_location']['short_location_url']);
        $this->assertNotNull($data['live_location']['copy_short_url']);

        // SECTION 5: LATEST INTRUDER
        $this->assertNotNull($data['latest_intruder']);
        $this->assertNotNull($data['latest_intruder']['event_time']);
        $this->assertNotNull($data['latest_intruder']['photo_preview']);
        $this->assertNotNull($data['latest_intruder']['location']);
        $this->assertNotNull($data['latest_intruder']['view_photo']);
        $this->assertNotNull($data['latest_intruder']['open_location']);

        // SECTION 6: TRUSTED USERS (Maximum 2)
        $this->assertCount(2, $data['trusted_users']);
        $this->assertEquals('Arthur Sterling', $data['trusted_users'][0]['name']);
        $this->assertEquals('+15551112233', $data['trusted_users'][0]['phone']);
        $this->assertEquals('VERIFIED', $data['trusted_users'][0]['verification_status']);

        // SECTION 7: SECURITY COMMANDS
        $this->assertArrayHasKey('available_commands', $data['security_commands']);
        $this->assertArrayHasKey('FACTORY_RESET', $data['security_commands']['available_commands']);
        $this->assertArrayHasKey('KIOSK_LOCK', $data['security_commands']['available_commands']);
        $this->assertArrayHasKey('KIOSK_UNLOCK', $data['security_commands']['available_commands']);
        $this->assertArrayHasKey('WIPE_DATA', $data['security_commands']['available_commands']);

        // SECTION 8: SECURITY EVENTS (Server-side paginated structure)
        $this->assertArrayHasKey('data', $data['security_events']);
        $firstEvent = $data['security_events']['data'][0];
        $this->assertArrayHasKey('event_type', $firstEvent);
        $this->assertArrayHasKey('severity', $firstEvent);
        $this->assertArrayHasKey('time', $firstEvent);
        $this->assertArrayHasKey('device', $firstEvent);
        $this->assertArrayHasKey('location', $firstEvent);
        $this->assertArrayHasKey('evidence', $firstEvent);
        $this->assertArrayHasKey('status', $firstEvent);
        $this->assertArrayHasKey('reviewed_state', $firstEvent);

        // SECTION 9: NOTIFICATIONS
        $this->assertArrayHasKey('data', $data['notifications']);
        $firstNotif = $data['notifications']['data'][0];
        $this->assertArrayHasKey('type', $firstNotif);
        $this->assertArrayHasKey('sent', $firstNotif);
        $this->assertArrayHasKey('delivery_status', $firstNotif);
    }

    /**
     * TEST 3: Server-Side Pagination for Security Events
     */
    public function test_server_side_pagination_for_security_events(): void
    {
        $admin = $this->createSuperAdmin();
        [$user] = $this->createFullControlCenterFixtures();

        $page1Res = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}/security-events?page=1&per_page=2");

        $page1Res->assertStatus(200);
        $page1Data = $page1Res->json('data');

        $this->assertEquals(1, $page1Data['current_page']);
        $this->assertEquals(2, count($page1Data['data']));
        $this->assertGreaterThanOrEqual(6, $page1Data['total']);

        // Fetch Page 2
        $page2Res = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}/security-events?page=2&per_page=2");

        $page2Res->assertStatus(200);
        $page2Data = $page2Res->json('data');

        $this->assertEquals(2, $page2Data['current_page']);
        $this->assertEquals(2, count($page2Data['data']));
        // Ensure Page 1 and Page 2 contain different events
        $this->assertNotEquals($page1Data['data'][0]['id'], $page2Data['data'][0]['id']);
    }

    /**
     * TEST 4: Server-Side Pagination for Notifications
     */
    public function test_server_side_pagination_for_notifications(): void
    {
        $admin = $this->createSuperAdmin();
        [$user] = $this->createFullControlCenterFixtures();

        $notifRes = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}/notifications?page=1&per_page=2");

        $notifRes->assertStatus(200);
        $data = $notifRes->json('data');

        $this->assertEquals(1, $data['current_page']);
        $this->assertCount(2, $data['data']);
        $this->assertArrayHasKey('type', $data['data'][0]);
        $this->assertArrayHasKey('sent', $data['data'][0]);
        $this->assertArrayHasKey('delivery_status', $data['data'][0]);
    }

    /**
     * TEST 5: Sensitive Action Audit Logging
     * Viewing the user control center creates an audit log record
     */
    public function test_sensitive_action_audit_trail_is_created_on_control_center_access(): void
    {
        $admin = $this->createSuperAdmin();
        [$user] = $this->createFullControlCenterFixtures();

        $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}");

        $audit = AdminAuditLog::where('admin_id', $admin->id)
            ->where('action', 'USER_SECURITY_CONTROL_CENTER_VIEWED')
            ->latest()
            ->first();

        $this->assertNotNull($audit);
        $this->assertEquals($user->id, $audit->target_id);
    }

    /**
     * TEST 6: Secrets are NEVER exposed in Control Center responses
     */
    public function test_secrets_passwords_tokens_are_never_exposed(): void
    {
        $admin = $this->createSuperAdmin();
        [$user] = $this->createFullControlCenterFixtures();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$user->id}");

        $responseContent = $response->getContent();

        $this->assertStringNotContainsString('TargetUserSecret99!', $responseContent);
        $this->assertStringNotContainsString('password_hash', $responseContent);
        $this->assertStringNotContainsString('fcm_token', $responseContent);
        $this->assertStringNotContainsString('remember_token', $responseContent);
    }
}
