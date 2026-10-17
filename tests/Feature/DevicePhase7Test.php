<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Models\Admin;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DevicePhase7Test extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::factory()->create([
            'phone_number' => '+1111111111',
            'email' => 'user1@example.com',
            'name' => 'Alice Smith',
        ]);

        $this->user2 = User::factory()->create([
            'phone_number' => '+2222222222',
            'email' => 'user2@example.com',
            'name' => 'Bob Jones',
        ]);

        // Admin setup
        $this->admin = Admin::create([
            'name' => 'Device Admin',
            'email' => 'admin@device-test.com',
            'password' => Hash::make('password123'),
            'role' => AdminRole::ADMIN,
            'is_active' => true,
        ]);
    }

    protected function authenticateAs(User|Admin $entity): static
    {
        $this->flushHeaders();
        app('auth')->forgetGuards();

        $token = $entity->createToken('test_token')->plainTextToken;

        return $this->withHeader('Authorization', 'Bearer ' . $token);
    }

    /**
     * Test 1: Device Registration succeeds with required device fields.
     * Checks platform, model, manufacturer, os_version, app_version, status, last_seen_at.
     */
    public function test_device_registration_success(): void
    {
        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'device-uuid-12345',
                'platform' => 'android',
                'model' => 'Pixel 8 Pro',
                'manufacturer' => 'Google',
                'os_version' => '14.0',
                'app_version' => '2.1.0',
                'fcm_token' => 'secret_fcm_token_xyz',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'device' => [
                        'device_id' => 'device-uuid-12345',
                        'user_id' => $this->user1->id,
                        'platform' => 'android',
                        'model' => 'Pixel 8 Pro',
                        'manufacturer' => 'Google',
                        'os_version' => '14.0',
                        'app_version' => '2.1.0',
                        'status' => DeviceStatus::ACTIVE->value,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('user_devices', [
            'device_uuid' => 'device-uuid-12345',
            'user_id' => $this->user1->id,
            'model' => 'Pixel 8 Pro',
            'manufacturer' => 'Google',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE->value,
        ]);
    }

    /**
     * Test 2: Rule - user_id must come from authenticated user.
     * Never trust user_id supplied by Flutter.
     */
    public function test_registration_never_trusts_user_id_supplied_by_client(): void
    {
        // Attacker attempts to register a device claiming user_id of user2
        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'spoofed-user-id-device',
                'user_id' => $this->user2->id,
                'platform' => 'android',
                'model' => 'Galaxy S23',
                'manufacturer' => 'Samsung',
            ]);

        $response->assertStatus(201);
        $deviceData = $response->json('data.device');

        // Must belong to user1 (the authenticated user), NEVER user2
        $this->assertEquals($this->user1->id, $deviceData['user_id']);
        $this->assertNotEquals($this->user2->id, $deviceData['user_id']);

        $this->assertDatabaseHas('user_devices', [
            'device_uuid' => 'spoofed-user-id-device',
            'user_id' => $this->user1->id,
        ]);
        $this->assertDatabaseMissing('user_devices', [
            'device_uuid' => 'spoofed-user-id-device',
            'user_id' => $this->user2->id,
        ]);
    }

    /**
     * Test 3: Rule - FCM tokens must not be exposed unnecessarily in serialization.
     */
    public function test_fcm_tokens_are_not_exposed_unnecessarily(): void
    {
        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'device-fcm-hidden-test',
                'platform' => 'ios',
                'model' => 'iPhone 15 Pro',
                'fcm_token' => 'ultra_confidential_fcm_token_string',
            ]);

        $response->assertStatus(201);

        // In response, fcm_token should NOT be leaked in the device payload
        $deviceJson = $response->json('data.device');
        $this->assertArrayNotHasKey('fcm_token', $deviceJson);

        // In database, it is saved securely
        $this->assertDatabaseHas('user_devices', [
            'device_uuid' => 'device-fcm-hidden-test',
            'fcm_token' => 'ultra_confidential_fcm_token_string',
        ]);
    }

    /**
     * Test 4: Duplicate device registration by same user updates existing record without duplicate row.
     */
    public function test_duplicate_device_registration_updates_existing_device(): void
    {
        // First registration
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'device-dup-test-1',
                'platform' => 'android',
                'model' => 'OnePlus 11',
                'manufacturer' => 'OnePlus',
                'app_version' => '1.0.0',
            ])->assertStatus(201);

        $this->assertEquals(1, UserDevice::where('device_uuid', 'device-dup-test-1')->count());

        // Re-registration / duplicate registration by same user with updated app_version
        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'device-dup-test-1',
                'platform' => 'android',
                'model' => 'OnePlus 11',
                'manufacturer' => 'OnePlus',
                'app_version' => '1.1.0',
            ]);

        $response->assertStatus(200);
        $this->assertEquals(1, UserDevice::where('device_uuid', 'device-dup-test-1')->count());

        $this->assertDatabaseHas('user_devices', [
            'device_uuid' => 'device-dup-test-1',
            'user_id' => $this->user1->id,
            'app_version' => '1.1.0',
        ]);
    }

    /**
     * Test 5: Wrong ownership validation - another user cannot register or steal an existing device.
     */
    public function test_wrong_ownership_rejected_on_registration(): void
    {
        // User 1 registers device
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'device-alice-unique',
                'platform' => 'android',
                'model' => 'Pixel 7',
            ])->assertStatus(201);

        // User 2 tries to register Alice's device
        $response = $this->authenticateAs($this->user2)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'device-alice-unique',
                'platform' => 'android',
                'model' => 'Pixel 7',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Device is already registered to another user.',
                'code' => 'DEVICE_OWNERSHIP_INVALID',
            ]);
    }

    /**
     * Test 6: Configurable max_devices_per_user limit enforced.
     */
    public function test_configurable_max_devices_per_user_limit(): void
    {
        Config::set('devices.max_devices_per_user', 2);

        // Device 1: Allowed
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'max-device-1',
                'platform' => 'android',
            ])->assertStatus(201);

        // Device 2: Allowed
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'max-device-2',
                'platform' => 'android',
            ])->assertStatus(201);

        // Device 3: Limit exceeded
        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'max-device-3',
                'platform' => 'android',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'MAX_DEVICES_EXCEEDED',
            ]);
    }

    /**
     * Test 7: Device Heartbeat updates last_seen_at and active status.
     */
    public function test_device_heartbeat_updates_last_seen(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'heartbeat-device-1',
            'platform' => 'android',
            'model' => 'Moto G',
            'status' => DeviceStatus::INACTIVE,
            'last_seen_at' => now()->subDays(2),
        ]);

        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/heartbeat', [
                'device_id' => 'heartbeat-device-1',
                'app_version' => '2.5.0',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => DeviceStatus::ACTIVE->value,
                ],
            ]);

        $device->refresh();
        $this->assertEquals(DeviceStatus::ACTIVE, $device->status);
        $this->assertEquals('2.5.0', $device->app_version);
        $this->assertTrue($device->last_seen_at->greaterThan(now()->subMinute()));
    }

    /**
     * Test 8: Heartbeat fails when wrong user attempts heartbeat on another user's device.
     */
    public function test_heartbeat_wrong_ownership_rejected(): void
    {
        UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'alice-heartbeat-dev',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
        ]);

        $response = $this->authenticateAs($this->user2)
            ->postJson('/api/v1/devices/heartbeat', [
                'device_id' => 'alice-heartbeat-dev',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_OWNERSHIP_INVALID',
            ]);
    }

    /**
     * Test 9: FCM Token endpoint POST /api/v1/devices/fcm-token updates token securely.
     */
    public function test_fcm_token_update_endpoint(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'fcm-device-test',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
        ]);

        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/fcm-token', [
                'device_id' => 'fcm-device-test',
                'fcm_token' => 'fresh_new_firebase_cloud_messaging_token_123',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'device_id' => 'fcm-device-test',
                    'fcm_token_updated' => true,
                ],
            ]);

        // Response does not leak the raw token
        $this->assertArrayNotHasKey('fcm_token', $response->json('data'));

        $device->refresh();
        $this->assertEquals('fresh_new_firebase_cloud_messaging_token_123', $device->fcm_token);
    }

    /**
     * Test 10: FCM token update rejected for wrong user ownership.
     */
    public function test_fcm_token_update_wrong_ownership_rejected(): void
    {
        UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'alice-fcm-dev',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
        ]);

        $response = $this->authenticateAs($this->user2)
            ->postJson('/api/v1/devices/fcm-token', [
                'device_id' => 'alice-fcm-dev',
                'fcm_token' => 'attacker_token',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_OWNERSHIP_INVALID',
            ]);
    }

    /**
     * Test 11: Revoked device cannot perform heartbeat or FCM token update.
     */
    public function test_revoked_device_cannot_perform_operations(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'revoked-device-1',
            'platform' => 'android',
            'status' => DeviceStatus::REVOKED,
            'last_seen_at' => now(),
        ]);

        // Heartbeat on revoked device -> 403
        $heartbeatResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/heartbeat', [
                'device_id' => 'revoked-device-1',
            ]);
        $heartbeatResp->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_REVOKED',
            ]);

        // FCM token update on revoked device -> 403
        $fcmResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/fcm-token', [
                'device_id' => 'revoked-device-1',
                'fcm_token' => 'new-token',
            ]);
        $fcmResp->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_REVOKED',
            ]);

        // Registration attempt on revoked device -> 403
        $regResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/register', [
                'device_id' => 'revoked-device-1',
                'platform' => 'android',
            ]);
        $regResp->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_REVOKED',
            ]);
    }

    /**
     * Test 12: Delete device DELETE /api/v1/devices/{id} deletes device for owner.
     */
    public function test_user_can_delete_own_device(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'delete-target-device',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
        ]);

        $this->user1->update(['current_device_id' => $device->id]);

        // Wrong user cannot delete
        $this->authenticateAs($this->user2)
            ->deleteJson('/api/v1/devices/delete-target-device')
            ->assertStatus(403);

        // Owner deletes
        $response = $this->authenticateAs($this->user1)
            ->deleteJson('/api/v1/devices/delete-target-device');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'device_id' => 'delete-target-device',
                    'deleted' => true,
                ],
            ]);

        $this->assertDatabaseMissing('user_devices', [
            'device_uuid' => 'delete-target-device',
        ]);
        $this->assertNull($this->user1->fresh()->current_device_id);
    }

    /**
     * Test 13: Admin can search devices by device info, platform, and user.
     */
    public function test_admin_search_and_filter_devices(): void
    {
        UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'pixel-uuid-999',
            'device_name' => 'Alice Pixel',
            'model' => 'Pixel 8',
            'manufacturer' => 'Google',
            'platform' => 'android',
            'app_version' => '3.0.0',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
        ]);

        UserDevice::create([
            'user_id' => $this->user2->id,
            'device_uuid' => 'iphone-uuid-888',
            'device_name' => 'Bob iPhone',
            'model' => 'iPhone 15',
            'manufacturer' => 'Apple',
            'platform' => 'ios',
            'app_version' => '2.0.0',
            'status' => DeviceStatus::INACTIVE,
            'last_seen_at' => now()->subDays(5),
        ]);

        // Search by manufacturer
        $searchResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/devices?search=Google');
        $searchResp->assertStatus(200);
        $data = $searchResp->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('pixel-uuid-999', $data[0]['device_uuid']);

        // Search by user name
        $userSearchResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/devices?search=Alice');
        $userSearchResp->assertStatus(200);
        $this->assertCount(1, $userSearchResp->json('data.data'));

        // Filter by platform
        $platformResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/devices?platform=ios');
        $platformResp->assertStatus(200);
        $iosData = $platformResp->json('data.data');
        $this->assertCount(1, $iosData);
        $this->assertEquals('iphone-uuid-888', $iosData[0]['device_uuid']);

        // Filter by status
        $statusResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/devices?status=ACTIVE');
        $statusResp->assertStatus(200);
        $activeData = $statusResp->json('data.data');
        $this->assertCount(1, $activeData);
        $this->assertEquals('pixel-uuid-999', $activeData[0]['device_uuid']);
    }

    /**
     * Test 14: Admin can view device details (platform, app, device info, last seen).
     */
    public function test_admin_view_device_details(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'view-device-detail-uuid',
            'device_name' => 'Alice Galaxy',
            'model' => 'Galaxy S24',
            'manufacturer' => 'Samsung',
            'platform' => 'android',
            'os_version' => '14.1',
            'app_version' => '4.0.0',
            'fcm_token' => 'fcm-secret-12345',
            'status' => DeviceStatus::ACTIVE,
            'last_seen_at' => now(),
        ]);

        $response = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/devices/' . $device->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'device' => [
                        'id' => $device->id,
                        'device_uuid' => 'view-device-detail-uuid',
                        'platform' => 'android',
                        'model' => 'Galaxy S24',
                        'manufacturer' => 'Samsung',
                        'os_version' => '14.1',
                        'app_version' => '4.0.0',
                        'status' => DeviceStatus::ACTIVE->value,
                        'has_fcm_token' => true,
                    ],
                ],
            ]);

        // Verify fcm_token is not exposed in serialization
        $this->assertArrayNotHasKey('fcm_token', $response->json('data.device'));
    }

    /**
     * Test 15: Admin can revoke device; sets status to REVOKED, logs audit, and blocks device immediately.
     */
    public function test_admin_revoke_device_and_audit_logging(): void
    {
        $device = UserDevice::create([
            'user_id' => $this->user1->id,
            'device_uuid' => 'admin-revoke-target-uuid',
            'platform' => 'android',
            'model' => 'Pixel 7',
            'status' => DeviceStatus::ACTIVE,
            'is_protection_enabled' => true,
            'last_seen_at' => now(),
        ]);

        $response = $this->authenticateAs($this->admin)
            ->postJson('/api/v1/admin/devices/' . $device->id . '/revoke');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Device access revoked successfully.',
            ]);

        $device->refresh();
        $this->assertEquals(DeviceStatus::REVOKED, $device->status);
        $this->assertFalse($device->is_protection_enabled);
        $this->assertNotNull($device->revoked_at);

        // Audit log created in admin_audit_logs
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'DEVICE_REVOKED',
            'target_id' => $device->id,
        ]);

        // Revoked device cannot perform heartbeat now
        $hbResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/devices/heartbeat', [
                'device_id' => 'admin-revoke-target-uuid',
            ]);
        $hbResp->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_REVOKED',
            ]);
    }
}
