<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\DeviceStatus;
use App\Enums\EvidenceType;
use App\Enums\SecurityEventType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\SecurityCommand;
use App\Models\SecurityEvent;
use App\Models\SecurityEventLocation;
use App\Models\SecurityEvidence;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminDashboardPhase15Test extends TestCase
{
    use RefreshDatabase;

    protected function createSuperAdmin(): Admin
    {
        return Admin::create([
            'name' => 'Chief Operations Officer',
            'email' => 'coo-' . uniqid() . '@sata.security',
            'password' => Hash::make('Secret123!'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    protected function createRestrictedAdmin(array $permissions): Admin
    {
        return Admin::create([
            'name' => 'Restricted Analyst',
            'email' => 'analyst-' . uniqid() . '@sata.security',
            'password' => Hash::make('Secret123!'),
            'role' => AdminRole::SUPPORT_AGENT,
            'is_active' => true,
            'custom_permissions' => $permissions,
        ]);
    }

    /**
     * Seed realistic multi-table telemetry fixtures.
     */
    protected function seedDashboardFixtures(): array
    {
        $plan = ActivationPlan::create([
            'name' => 'Titan Sovereign Armor',
            'code' => 'PLAN-SOV-' . rand(100, 999),
            'duration_days' => 365,
            'price' => 199.99,
            'status' => 'ACTIVE',
        ]);

        // Users in distinct lifecycle states
        $activeUser = User::create([
            'name' => 'Elena Rostova',
            'email' => 'elena.rostova@shield.net',
            'mobile' => '+14155550101',
            'status' => UserStatus::ACTIVE,
            'created_at' => Carbon::now()->subDays(5),
        ]);

        $pendingOtpUser = User::create([
            'name' => 'Marcus Vance',
            'email' => 'marcus.vance@shield.net',
            'mobile' => '+14155550102',
            'status' => UserStatus::PENDING,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $awaitingUser = User::create([
            'name' => 'Daria Thorne',
            'email' => 'daria.thorne@shield.net',
            'mobile' => '+14155550103',
            'status' => UserStatus::AWAITING_ACTIVATION,
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $disabledUser = User::create([
            'name' => 'Klaus Richter',
            'email' => 'klaus.richter@shield.net',
            'mobile' => '+14155550104',
            'status' => UserStatus::DISABLED,
            'created_at' => Carbon::now()->subDays(10),
        ]);

        // Subscriptions
        $activeSub = Subscription::create([
            'user_id' => $activeUser->id,
            'activation_plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => Carbon::now()->subDays(10),
            'expires_at' => Carbon::now()->addDays(90),
            'activated_at' => Carbon::now()->subDays(10),
        ]);

        $expiringSub = Subscription::create([
            'user_id' => $awaitingUser->id,
            'activation_plan_id' => $plan->id,
            'status' => SubscriptionStatus::EXPIRING,
            'starts_at' => Carbon::now()->subDays(350),
            'expires_at' => Carbon::now()->addDays(15),
            'activated_at' => Carbon::now()->subDays(350),
        ]);

        $expiredSub = Subscription::create([
            'user_id' => $disabledUser->id,
            'activation_plan_id' => $plan->id,
            'status' => SubscriptionStatus::EXPIRED,
            'starts_at' => Carbon::now()->subDays(400),
            'expires_at' => Carbon::now()->subDays(35),
            'activated_at' => Carbon::now()->subDays(400),
        ]);

        // Devices (Active, Offline, Revoked)
        $activeDevice = UserDevice::create([
            'user_id' => $activeUser->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Titan Ultra S24',
            'model' => 'Samsung Galaxy S24 Ultra',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => Carbon::now()->subMinutes(15),
        ]);

        $offlineDevice = UserDevice::create([
            'user_id' => $pendingOtpUser->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Pixel 8 Pro',
            'model' => 'Google Pixel 8 Pro',
            'platform' => 'android',
            'status' => DeviceStatus::INACTIVE,
            'last_seen_at' => Carbon::now()->subDays(4),
        ]);

        $revokedDevice = UserDevice::create([
            'user_id' => $disabledUser->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Compromised iPhone',
            'model' => 'iPhone 15 Pro Max',
            'platform' => 'ios',
            'status' => DeviceStatus::REVOKED,
            'revoked_at' => Carbon::now()->subDays(2),
        ]);

        // Security Events (Intruder, SIM, Motion)
        $intruderEvent = SecurityEvent::create([
            'user_id' => $activeUser->id,
            'device_id' => $activeDevice->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'severity' => 'CRITICAL',
            'occurred_at' => Carbon::now()->subHours(2),
        ]);

        SecurityEvidence::create([
            'security_event_id' => $intruderEvent->id,
            'evidence_type' => EvidenceType::FRONT_CAMERA,
            'storage_path' => 'evidence/intruder_01.jpg',
            'file_name' => 'intruder_01.jpg',
            'file_size' => 204800,
            'mime_type' => 'image/jpeg',
            'is_processed' => true,
        ]);

        SecurityEventLocation::create([
            'security_event_id' => $intruderEvent->id,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'accuracy' => 4.5,
            'recorded_at' => Carbon::now()->subHours(2),
        ]);

        SecurityEvent::create([
            'user_id' => $activeUser->id,
            'device_id' => $activeDevice->id,
            'event_type' => SecurityEventType::SIM_CHANGED,
            'severity' => 'HIGH',
            'occurred_at' => Carbon::now()->subHours(4),
        ]);

        SecurityEvent::create([
            'user_id' => $activeUser->id,
            'device_id' => $activeDevice->id,
            'event_type' => SecurityEventType::MOTION_DETECTED,
            'severity' => 'MEDIUM',
            'occurred_at' => Carbon::now()->subHours(6),
        ]);

        // Security Commands (Pending & Failed)
        SecurityCommand::create([
            'user_id' => $activeUser->id,
            'device_id' => $activeDevice->id,
            'command_type' => CommandType::LOCATE,
            'status' => CommandStatus::PENDING,
            'created_at' => Carbon::now()->subMinutes(10),
        ]);

        SecurityCommand::create([
            'user_id' => $disabledUser->id,
            'device_id' => $revokedDevice->id,
            'command_type' => CommandType::FACTORY_RESET,
            'status' => CommandStatus::FAILED,
            'error_message' => 'Device unreachable: revoked token',
            'created_at' => Carbon::now()->subHours(12),
        ]);

        return compact('activeUser', 'plan', 'activeDevice');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(401);
    }

    public function test_admin_dashboard_returns_all_required_metric_cards(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*'], 'admin');

        $this->seedDashboardFixtures();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'cards' => [
                        'users' => ['total', 'active', 'pending_otp', 'awaiting_activation', 'disabled'],
                        'subscriptions' => ['active', 'expiring', 'expired'],
                        'devices' => ['active', 'offline', 'revoked'],
                        'security' => ['total_security_events', 'intruder_alerts', 'sim_alerts', 'motion_alerts', 'pending_commands', 'failed_commands'],
                    ],
                    'charts',
                    'recent_activity',
                    'filters',
                    'permissions',
                ],
            ]);

        // Verify Users card counts
        $users = $response->json('data.cards.users');
        $this->assertEquals(4, $users['total']);
        $this->assertEquals(1, $users['active']);
        $this->assertEquals(1, $users['pending_otp']);
        $this->assertEquals(1, $users['awaiting_activation']);
        $this->assertEquals(1, $users['disabled']);

        // Verify Subscriptions card counts
        $subs = $response->json('data.cards.subscriptions');
        $this->assertEquals(1, $subs['active']);
        $this->assertEquals(1, $subs['expiring']);
        $this->assertEquals(1, $subs['expired']);

        // Verify Devices card counts
        $devices = $response->json('data.cards.devices');
        $this->assertEquals(1, $devices['active']);
        $this->assertEquals(1, $devices['offline']);
        $this->assertEquals(1, $devices['revoked']);

        // Verify Security card counts
        $sec = $response->json('data.cards.security');
        $this->assertEquals(3, $sec['total_security_events']);
        $this->assertEquals(1, $sec['intruder_alerts']);
        $this->assertEquals(1, $sec['sim_alerts']);
        $this->assertEquals(1, $sec['motion_alerts']);
        $this->assertEquals(1, $sec['pending_commands']);
        $this->assertEquals(1, $sec['failed_commands']);
    }

    public function test_admin_dashboard_returns_all_four_chart_series(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*'], 'admin');
        $this->seedDashboardFixtures();

        $response = $this->getJson('/api/v1/admin/dashboard?preset=7d');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'charts' => [
                        'user_registrations',
                        'active_subscriptions',
                        'security_events',
                        'device_activity',
                    ],
                ],
            ]);

        $charts = $response->json('data.charts');
        $this->assertNotEmpty($charts['user_registrations']);
        $this->assertNotEmpty($charts['active_subscriptions']);
        $this->assertNotEmpty($charts['security_events']);
        $this->assertNotEmpty($charts['device_activity']);
    }

    public function test_admin_dashboard_returns_all_four_recent_activity_streams(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*'], 'admin');
        $this->seedDashboardFixtures();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'recent_activity' => [
                        'latest_intruder_events',
                        'latest_security_commands',
                        'latest_registrations',
                        'latest_subscription_activations',
                    ],
                ],
            ]);

        $activity = $response->json('data.recent_activity');

        // Check intruder event with eager loaded evidence and location
        $this->assertNotEmpty($activity['latest_intruder_events']);
        $firstIntruder = $activity['latest_intruder_events'][0];
        $this->assertEquals('INTRUDER_DETECTED', $firstIntruder['event_type']);
        $this->assertTrue($firstIntruder['has_evidence']);
        $this->assertNotNull($firstIntruder['location']);

        // Check security commands
        $this->assertNotEmpty($activity['latest_security_commands']);

        // Check registrations
        $this->assertNotEmpty($activity['latest_registrations']);

        // Check activations
        $this->assertNotEmpty($activity['latest_subscription_activations']);
        $this->assertEquals('Titan Sovereign Armor', $activity['latest_subscription_activations'][0]['plan_name']);
    }

    public function test_admin_dashboard_is_permission_aware(): void
    {
        // Admin with ONLY users.view permission
        $restrictedAdmin = $this->createRestrictedAdmin([
            AdminPermission::USERS_VIEW->value,
        ]);
        Sanctum::actingAs($restrictedAdmin, ['*'], 'admin');
        $this->seedDashboardFixtures();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200);

        // Users cards and activity should be present
        $this->assertArrayHasKey('users', $response->json('data.cards'));
        $this->assertArrayHasKey('user_registrations', $response->json('data.charts'));
        $this->assertArrayHasKey('latest_registrations', $response->json('data.recent_activity'));

        // Subscriptions, Devices, and Security should be absent/unauthorized
        $this->assertArrayNotHasKey('subscriptions', $response->json('data.cards'));
        $this->assertArrayNotHasKey('devices', $response->json('data.cards'));
        $this->assertArrayNotHasKey('security', $response->json('data.cards'));
        $this->assertArrayNotHasKey('active_subscriptions', $response->json('data.charts'));
        $this->assertArrayNotHasKey('latest_intruder_events', $response->json('data.recent_activity'));
    }

    public function test_admin_dashboard_respects_date_range_filter(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*'], 'admin');
        $this->seedDashboardFixtures();

        $dateFrom = Carbon::now()->subDays(3)->toDateString();
        $dateTo = Carbon::now()->toDateString();

        $response = $this->getJson("/api/v1/admin/dashboard?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(200)
            ->assertJsonPath('data.filters.preset', 'custom')
            ->assertJsonPath('data.filters.date_from', $dateFrom)
            ->assertJsonPath('data.filters.date_to', $dateTo);
    }

    public function test_admin_dashboard_logs_audit_trail_on_view(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*'], 'admin');

        $response = $this->getJson('/api/v1/admin/dashboard?preset=7d');
        $response->assertStatus(200);

        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'DASHBOARD_VIEWED',
            'module' => 'DASHBOARD',
        ]);
    }

    public function test_standalone_dashboard_sub_endpoints(): void
    {
        $admin = $this->createSuperAdmin();
        Sanctum::actingAs($admin, ['*'], 'admin');
        $this->seedDashboardFixtures();

        $this->getJson('/api/v1/admin/dashboard/stats')->assertStatus(200)->assertJsonStructure(['data' => ['cards']]);
        $this->getJson('/api/v1/admin/dashboard/charts')->assertStatus(200)->assertJsonStructure(['data' => ['charts']]);
        $this->getJson('/api/v1/admin/dashboard/recent-activity')->assertStatus(200)->assertJsonStructure(['data' => ['recent_activity']]);
    }
}
