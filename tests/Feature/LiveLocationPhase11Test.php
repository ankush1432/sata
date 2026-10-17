<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Enums\TrustedUserStatus;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\LiveLocationSession;
use App\Models\LocationAccessAuditLog;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveLocationPhase11Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected UserDevice $device;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'phone' => '+15551234567',
            'phone_number' => '+15551234567',
        ]);

        $this->device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_uuid' => 'dev-uuid-test-1111',
            'device_name' => 'Pixel 9 Pro',
            'status' => DeviceStatus::ACTIVE,
            'is_protection_enabled' => true,
        ]);
    }

    /**
     * Test 1: Location update endpoint (POST /api/v1/location/update)
     */
    public function test_location_update_success(): void
    {
        // Start an active live session to also verify it gets updated
        $session = LiveLocationSession::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'status' => 'ACTIVE',
            'is_active' => true,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(60),
        ]);

        $payload = [
            'device_uuid' => $this->device->device_uuid,
            'latitude' => 37.7749295,
            'longitude' => -122.4194155,
            'accuracy' => 4.5,
            'altitude' => 15.2,
            'speed' => 1.2,
            'heading' => 180.0,
            'recorded_at' => now()->toIso8601String(),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/location/update', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Location updated successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'location' => [
                        'latitude',
                        'longitude',
                        'accuracy',
                        'altitude',
                        'speed',
                        'heading',
                        'device_id',
                    ],
                    'map_link',
                ],
            ]);

        // Verify device location in database
        $this->device->refresh();
        $this->assertEquals(37.7749295, (float) $this->device->last_latitude);
        $this->assertEquals(-122.4194155, (float) $this->device->last_longitude);
        $this->assertEquals(4.5, (float) $this->device->last_accuracy);
        $this->assertNotNull($this->device->location_updated_at);

        // Verify active session was also updated
        $session->refresh();
        $this->assertEquals(37.7749295, (float) $session->last_latitude);
        $this->assertEquals(-122.4194155, (float) $session->last_longitude);
    }

    /**
     * Test 2: Wrong device ownership rejection
     */
    public function test_wrong_device_ownership_is_rejected(): void
    {
        $otherUser = User::factory()->create([
            'phone' => '+15559876543',
            'phone_number' => '+15559876543',
        ]);

        $otherDevice = UserDevice::create([
            'user_id' => $otherUser->id,
            'device_uuid' => 'dev-uuid-other-user',
            'device_name' => 'Galaxy S24',
            'status' => DeviceStatus::ACTIVE,
        ]);

        // User attempts to update using otherUser's device
        $payload = [
            'device_uuid' => $otherDevice->device_uuid,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/location/update', $payload);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'DEVICE_OWNERSHIP_INVALID',
            ]);
    }

    /**
     * Test 3: Unauthorized location access
     */
    public function test_unauthorized_location_access(): void
    {
        // Unauthenticated access
        $unauthResponse = $this->getJson('/api/v1/location/current');
        $unauthResponse->assertStatus(401);

        // Unrelated user attempting to view user's location
        $stranger = User::factory()->create([
            'phone' => '+15550001111',
            'phone_number' => '+15550001111',
        ]);

        $response = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/location/user/{$this->user->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHORIZED_LOCATION_ACCESS',
            ]);
    }

    /**
     * Test 4: Expired session rejection
     */
    public function test_expired_session_is_rejected(): void
    {
        // Create an expired live location session
        $session = LiveLocationSession::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'status' => 'ACTIVE',
            'is_active' => true,
            'started_at' => now()->subHours(3),
            'expires_at' => now()->subHour(), // Expired 1 hour ago
            'last_latitude' => 37.7749,
            'last_longitude' => -122.4194,
        ]);

        // 1. Attempt to get expired session
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/location/live-session/{$session->session_id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SESSION_EXPIRED',
            ]);

        // 2. Attempt to stream updates to expired session
        $updateResponse = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/location/live-session/{$session->session_id}", [
                'latitude' => 37.7750,
                'longitude' => -122.4195,
            ]);

        $updateResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SESSION_EXPIRED',
            ]);

        // 3. Attempt to access via short reference URL
        $refResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/location/ref/{$session->short_token}");

        $refResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SESSION_EXPIRED',
            ]);
    }

    /**
     * Test 5: Short URL access control and privacy
     * - Never put raw coordinates in public URL paths
     * - Short URLs must not directly expose sensitive data without authorization
     * - Live location is not permanently public
     */
    public function test_short_url_access_control_and_privacy(): void
    {
        $session = LiveLocationSession::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'status' => 'ACTIVE',
            'is_active' => true,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'last_latitude' => 34.052235,
            'last_longitude' => -118.243683,
            'last_accuracy' => 5.0,
        ]);

        $shortUrl = $session->short_location_reference_url;

        // Privacy rule: URL must NOT expose raw coordinates in path
        $this->assertStringNotContainsString('34.052235', $shortUrl);
        $this->assertStringNotContainsString('-118.243683', $shortUrl);
        $this->assertStringContainsString('/api/v1/location/ref/', $shortUrl);

        // Access rule: Unauthenticated access is blocked
        $unauthResponse = $this->getJson("/api/v1/location/ref/{$session->short_token}");
        $this->assertTrue(in_array($unauthResponse->status(), [401, 403]));

        // Access rule: Unrelated user is blocked
        $stranger = User::factory()->create([
            'phone' => '+15558889999',
            'phone_number' => '+15558889999',
        ]);
        $strangerResponse = $this->actingAs($stranger, 'sanctum')
            ->getJson("/api/v1/location/ref/{$session->short_token}");
        $strangerResponse->assertStatus(403);

        // Access rule: Owner can access
        $ownerResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/location/ref/{$session->short_token}");
        $ownerResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->session_id,
                    'latitude' => 34.052235,
                    'longitude' => -118.243683,
                ],
            ]);

        // When session is stopped, access is rejected
        $session->stop();
        $stoppedResponse = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/location/ref/{$session->short_token}");
        $stoppedResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SESSION_STOPPED',
            ]);
    }

    /**
     * Test 6: Admin access audit and session lifecycle
     */
    public function test_admin_access_and_audit_logging(): void
    {
        $admin = Admin::create([
            'name' => 'SecOps Admin',
            'email' => 'admin-location@secops.test',
            'password' => bcrypt('AdminPassword123!'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->device->update([
            'last_latitude' => 51.5074,
            'last_longitude' => -0.1278,
            'last_accuracy' => 3.0,
            'location_updated_at' => now(),
        ]);

        // 1. Admin gets user location
        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$this->user->id}/location");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->user->id,
                    'current_latitude' => 51.5074,
                    'current_longitude' => -0.1278,
                    'accuracy' => 3.0,
                ],
            ]);

        // Verify LocationAccessAuditLog logged the sensitive access
        $this->assertDatabaseHas('location_access_audit_logs', [
            'target_user_id' => $this->user->id,
            'admin_id' => $admin->id,
            'accessor_type' => 'ADMIN',
            'action' => 'ADMIN_LOCATION_VIEWED',
        ]);

        // Verify AdminAuditLog logged the action
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'ADMIN_LOCATION_VIEWED',
            'module' => 'LOCATION',
        ]);

        // 2. Admin initiates a live location session
        $startSessionResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/users/{$this->user->id}/location/session", [
                'duration_minutes' => 45,
            ]);

        $startSessionResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Live location session initiated.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'session_id',
                    'status',
                    'short_token',
                    'short_url',
                    'short_location_reference_url',
                    'map_link',
                ],
            ]);

        $sessionId = $startSessionResponse->json('data.session_id');

        $this->assertDatabaseHas('live_location_sessions', [
            'session_id' => $sessionId,
            'user_id' => $this->user->id,
            'status' => 'ACTIVE',
        ]);

        // 3. Admin stops the live session
        $stopResponse = $this->actingAs($admin, 'sanctum')
            ->postJson("/admin/location/sessions/{$sessionId}/stop");

        $stopResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $sessionId,
                    'status' => 'STOPPED',
                ],
            ]);

        $this->assertDatabaseHas('live_location_sessions', [
            'session_id' => $sessionId,
            'status' => 'STOPPED',
            'is_active' => false,
        ]);

        // 4. Admin user detail endpoint displays required fields
        $userDetailResponse = $this->actingAs($admin, 'sanctum')
            ->getJson("/admin/users/{$this->user->id}");

        $userDetailResponse->assertStatus(200);
        $userData = $userDetailResponse->json('data.user');

        $this->assertArrayHasKey('current_latitude', $userData);
        $this->assertArrayHasKey('current_longitude', $userData);
        $this->assertArrayHasKey('accuracy', $userData);
        $this->assertArrayHasKey('last_updated', $userData);
        $this->assertArrayHasKey('map_link', $userData);
        $this->assertArrayHasKey('short_location_reference_url', $userData);

        $this->assertEquals(51.5074, (float) $userData['current_latitude']);
        $this->assertEquals(-0.1278, (float) $userData['current_longitude']);
        $this->assertEquals(3.0, (float) $userData['accuracy']);
        $this->assertStringContainsString('maps.google.com', $userData['map_link']);
    }

    /**
     * Test 7: Trusted user location access
     * - Verified trusted user can view linked main user location
     * - Unverified/pending trusted user cannot view location
     * - Trusted user cannot view unrelated users' location
     */
    public function test_trusted_user_location_access(): void
    {
        $trustedUserAccount = User::factory()->create([
            'phone' => '+15557778888',
            'phone_number' => '+15557778888',
        ]);

        // Link as trusted user to main user (in PENDING status first)
        $trustedUserLink = TrustedUser::create([
            'user_id' => $this->user->id,
            'name' => 'Trusted Contact',
            'phone' => '+15557778888',
            'phone_number' => '+15557778888',
            'relationship' => 'Spouse',
            'status' => TrustedUserStatus::PENDING,
            'is_active' => false,
        ]);

        $this->device->update([
            'last_latitude' => 48.8566,
            'last_longitude' => 2.3522,
            'last_accuracy' => 10.0,
            'location_updated_at' => now(),
        ]);

        // 1. When PENDING: Access is rejected
        $pendingResponse = $this->actingAs($trustedUserAccount, 'sanctum')
            ->getJson("/api/v1/location/user/{$this->user->id}");

        $pendingResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHORIZED_LOCATION_ACCESS',
            ]);

        // 2. When VERIFIED: Access is granted
        $trustedUserLink->verify();

        $verifiedResponse = $this->actingAs($trustedUserAccount, 'sanctum')
            ->getJson("/api/v1/location/user/{$this->user->id}");

        $verifiedResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_id' => $this->user->id,
                    'current_latitude' => 48.8566,
                    'current_longitude' => 2.3522,
                    'accuracy' => 10.0,
                ],
            ]);

        // 3. Trusted user can also view active session via short URL
        $session = LiveLocationSession::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'status' => 'ACTIVE',
            'is_active' => true,
            'started_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'last_latitude' => 48.8566,
            'last_longitude' => 2.3522,
        ]);

        $shortUrlResponse = $this->actingAs($trustedUserAccount, 'sanctum')
            ->getJson("/api/v1/location/ref/{$session->short_token}");

        $shortUrlResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'session_id' => $session->session_id,
                    'latitude' => 48.8566,
                    'longitude' => 2.3522,
                ],
            ]);

        // 4. Trusted user cannot access location of an unrelated user
        $unrelatedUser = User::factory()->create([
            'phone' => '+15553334444',
            'phone_number' => '+15553334444',
        ]);

        $unrelatedResponse = $this->actingAs($trustedUserAccount, 'sanctum')
            ->getJson("/api/v1/location/user/{$unrelatedUser->id}");

        $unrelatedResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'UNAUTHORIZED_LOCATION_ACCESS',
            ]);
    }
}
