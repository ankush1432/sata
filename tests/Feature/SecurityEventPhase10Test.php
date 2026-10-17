<?php

namespace Tests\Feature;

use App\Enums\AdminPermission;
use App\Enums\DeviceStatus;
use App\Enums\EvidenceType;
use App\Enums\NotificationType;
use App\Enums\SecurityEventType;
use App\Enums\TrustedUserStatus;
use App\Enums\UserStatus;
use App\Jobs\SendFcmNotificationJob;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\EvidenceAuditLog;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\SecurityEventLocation;
use App\Models\SecurityEvidence;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityEventPhase10Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private_evidence');
    }

    /**
     * Test 1: Event Creation with all required fields, location attachment, and photo evidence.
     */
    public function test_security_event_creation_with_all_fields_location_and_evidence(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone_number' => '+15551110001',
            'email' => 'john@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'dev-uuid-101',
            'device_name' => 'Pixel 9',
            'status' => DeviceStatus::ACTIVE,
        ]);

        Sanctum::actingAs($user);

        $eventUuid = (string) Str::uuid();
        $fakePhoto = UploadedFile::fake()->create('front_intruder.jpg', 200, 'image/jpeg');

        $response = $this->post('/api/v1/security-events', [
            'event_uuid' => $eventUuid,
            'device_uuid' => 'dev-uuid-101',
            'type' => SecurityEventType::INTRUDER_DETECTED->value,
            'severity' => 'CRITICAL',
            'description' => 'Unauthorized biometric attempt detected.',
            'occurred_at' => now()->toIso8601String(),
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'accuracy' => 5.5,
            'altitude' => 15.0,
            'metadata' => [
                'attempts' => 3,
                'camera' => 'front',
            ],
            'file' => $fakePhoto,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_uuid' => $eventUuid,
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'type' => SecurityEventType::INTRUDER_DETECTED->value,
                    'severity' => 'CRITICAL',
                    'status' => 'REPORTED',
                    'latitude' => 37.7749,
                    'longitude' => -122.4194,
                    'accuracy' => 5.5,
                    'metadata' => [
                        'attempts' => 3,
                        'camera' => 'front',
                    ],
                ],
            ]);

        // Verify Database
        $this->assertDatabaseHas('security_events', [
            'event_uuid' => $eventUuid,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED->value,
            'severity' => 'CRITICAL',
            'status' => 'REPORTED',
        ]);

        $this->assertDatabaseHas('security_event_locations', [
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'accuracy' => 5.5,
        ]);

        $this->assertDatabaseHas('security_evidence', [
            'user_id' => $user->id,
            'evidence_type' => EvidenceType::FRONT_PHOTO->value,
            'mime_type' => 'image/jpeg',
            'storage_disk' => 'private_evidence',
        ]);

        // Verify GET /api/v1/security-events and GET /api/v1/security-events/{id}
        $listResp = $this->getJson('/api/v1/security-events');
        $listResp->assertStatus(200)
            ->assertJsonPath('data.total', 1);

        $getResp = $this->getJson("/api/v1/security-events/{$eventUuid}");
        $getResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_uuid' => $eventUuid,
                    'type' => SecurityEventType::INTRUDER_DETECTED->value,
                    'status' => 'REPORTED',
                ],
            ]);

        // Verify all 6 supported event types can be reported
        $supportedTypes = [
            SecurityEventType::INTRUDER_DETECTED,
            SecurityEventType::SIM_CHANGED,
            SecurityEventType::MOTION_DETECTED,
            SecurityEventType::REMOTE_COMMAND,
            SecurityEventType::PROTECTION_ENABLED,
            SecurityEventType::PROTECTION_DISABLED,
        ];

        foreach ($supportedTypes as $type) {
            $subResp = $this->postJson('/api/v1/security-events', [
                'type' => $type->value,
                'device_uuid' => 'dev-uuid-101',
            ]);
            $subResp->assertStatus(201)
                ->assertJsonPath('data.type', $type->value);
        }
    }

    /**
     * Test 2: Duplicate event_uuid enforces uniqueness and provides idempotent behavior.
     */
    public function test_duplicate_event_uuid_idempotent_behavior_and_conflict_rejection(): void
    {
        $user1 = User::create([
            'name' => 'User One',
            'phone_number' => '+15551110002',
            'status' => UserStatus::ACTIVE,
        ]);

        $user2 = User::create([
            'name' => 'User Two',
            'phone_number' => '+15551110003',
            'status' => UserStatus::ACTIVE,
        ]);

        $device1 = UserDevice::create([
            'user_id' => $user1->id,
            'device_uuid' => 'dev-uuid-201',
            'status' => DeviceStatus::ACTIVE,
        ]);

        $device2 = UserDevice::create([
            'user_id' => $user2->id,
            'device_uuid' => 'dev-uuid-202',
            'status' => DeviceStatus::ACTIVE,
        ]);

        $fixedUuid = (string) Str::uuid();

        // User 1 creates event with fixedUuid
        Sanctum::actingAs($user1);
        $res1 = $this->postJson('/api/v1/security-events', [
            'event_uuid' => $fixedUuid,
            'device_uuid' => 'dev-uuid-201',
            'type' => SecurityEventType::SIM_CHANGED->value,
            'severity' => 'HIGH',
            'description' => 'SIM tray ejection detected.',
        ]);
        $res1->assertStatus(201);
        $this->assertEquals(1, SecurityEvent::where('event_uuid', $fixedUuid)->count());

        // User 1 retries with SAME event_uuid (Idempotent submission)
        $resRetry = $this->postJson('/api/v1/security-events', [
            'event_uuid' => $fixedUuid,
            'device_uuid' => 'dev-uuid-201',
            'type' => SecurityEventType::SIM_CHANGED->value,
            'severity' => 'HIGH',
        ]);
        $resRetry->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_uuid' => $fixedUuid,
                    'idempotent' => true,
                ],
            ]);
        // Confirm count is still 1
        $this->assertEquals(1, SecurityEvent::where('event_uuid', $fixedUuid)->count());

        // User 2 tries to use User 1's event_uuid -> Must be rejected (Conflict / Unprocessable)
        Sanctum::actingAs($user2);
        $resConflict = $this->postJson('/api/v1/security-events', [
            'event_uuid' => $fixedUuid,
            'device_uuid' => 'dev-uuid-202',
            'type' => SecurityEventType::MOTION_DETECTED->value,
        ]);
        $resConflict->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'EVENT_UUID_CONFLICT',
            ]);
    }

    /**
     * Test 3: Wrong device ownership rejection.
     */
    public function test_wrong_device_ownership_is_rejected(): void
    {
        $owner = User::create([
            'name' => 'Owner',
            'phone_number' => '+15551110004',
            'status' => UserStatus::ACTIVE,
        ]);

        $attacker = User::create([
            'name' => 'Attacker',
            'phone_number' => '+15551110005',
            'status' => UserStatus::ACTIVE,
        ]);

        $victimDevice = UserDevice::create([
            'user_id' => $owner->id,
            'device_uuid' => 'victim-device-uuid',
            'status' => DeviceStatus::ACTIVE,
        ]);

        Sanctum::actingAs($attacker);

        // Attacker attempts to report event pretending to be victim's device_uuid
        $respUuid = $this->postJson('/api/v1/security-events', [
            'device_uuid' => 'victim-device-uuid',
            'type' => SecurityEventType::INTRUDER_DETECTED->value,
        ]);
        $respUuid->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_OWNERSHIP_INVALID',
            ]);

        // Attacker attempts to report event specifying victim's device_id
        $respId = $this->postJson('/api/v1/security-events', [
            'device_id' => $victimDevice->id,
            'type' => SecurityEventType::INTRUDER_DETECTED->value,
        ]);
        $respId->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'DEVICE_OWNERSHIP_INVALID',
            ]);
    }

    /**
     * Test 4: Invalid upload validations (MIME, size, dangerous extension, safe filename).
     */
    public function test_invalid_evidence_upload_validations(): void
    {
        $user = User::create([
            'name' => 'Upload Tester',
            'phone_number' => '+15551110006',
            'status' => UserStatus::ACTIVE,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'upload-dev-1',
            'status' => DeviceStatus::ACTIVE,
        ]);

        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);

        // 1. Dangerous PHP extension
        $phpFile = UploadedFile::fake()->create('malicious.php', 10, 'application/x-php');
        $respPhp = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $phpFile,
        ]);
        $respPhp->assertStatus(422);

        // 2. Dangerous Shell script extension
        $shFile = UploadedFile::fake()->create('exploit.sh', 10, 'application/x-sh');
        $respSh = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $shFile,
        ]);
        $respSh->assertStatus(422);

        // 3. Dangerous Executable (.exe)
        $exeFile = UploadedFile::fake()->create('trojan.exe', 50, 'application/x-msdownload');
        $respExe = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $exeFile,
        ]);
        $respExe->assertStatus(422);

        // 4. Oversized file (>20MB)
        $oversizedFile = UploadedFile::fake()->create('huge_video.mp4', 25000, 'video/mp4');
        $respOver = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $oversizedFile,
        ]);
        $respOver->assertStatus(422);

        // 5. Valid file: verify safe server-side filename (UUID), private storage, not public
        $validFile = UploadedFile::fake()->create('client_dangerous_name_attempt.jpg', 150, 'image/jpeg');
        $respValid = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $validFile,
        ]);
        $respValid->assertStatus(201);

        $storagePath = $respValid->json('data.storage_path');
        // Must NOT contain the raw client filename in storage path
        $this->assertStringNotContainsString('client_dangerous_name_attempt', $storagePath);
        // Must exist in private storage disk
        Storage::disk('private_evidence')->assertExists($storagePath);
    }

    /**
     * Test 5: Unauthorized evidence access is strictly forbidden.
     */
    public function test_unauthorized_evidence_access_is_forbidden(): void
    {
        $owner = User::create([
            'name' => 'Evidence Owner',
            'phone_number' => '+15551110007',
            'status' => UserStatus::ACTIVE,
        ]);

        $intruderUser = User::create([
            'name' => 'Intruder User',
            'phone_number' => '+15551110008',
            'status' => UserStatus::ACTIVE,
        ]);

        $event = SecurityEvent::create([
            'user_id' => $owner->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($owner);
        $file = UploadedFile::fake()->create('front_photo.jpg', 100, 'image/jpeg');
        $uploadResp = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $file,
        ]);
        $uploadResp->assertStatus(201);
        $evidenceId = $uploadResp->json('data.id');

        // Unauthenticated request -> Must fail (401 or 403)
        Sanctum::actingAs(new User()); // clear acting as
        $unauthResp = $this->get("/api/v1/evidence/{$evidenceId}");
        $this->assertTrue(in_array($unauthResp->status(), [401, 403], true));

        // Authenticated as another user (intruderUser) -> Must return 403 Forbidden
        Sanctum::actingAs($intruderUser);
        $forbiddenResp = $this->get("/api/v1/evidence/{$evidenceId}");
        $forbiddenResp->assertStatus(403);

        // Authenticated as owner -> Success (200)
        Sanctum::actingAs($owner);
        $ownerResp = $this->get("/api/v1/evidence/{$evidenceId}");
        $ownerResp->assertStatus(200);
        $this->assertStringContainsString('no-store', $ownerResp->headers->get('cache-control'));
    }

    /**
     * Test 6: Evidence signed URL generation, valid streaming, and signed URL expiry.
     */
    public function test_evidence_signed_url_generation_and_expiry(): void
    {
        $user = User::create([
            'name' => 'Signed URL User',
            'phone_number' => '+15551110009',
            'status' => UserStatus::ACTIVE,
        ]);

        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);
        $file = UploadedFile::fake()->create('signed_target.jpg', 120, 'image/jpeg');
        $uploadResp = $this->post('/api/v1/evidence/upload', [
            'security_event_id' => $event->id,
            'file' => $file,
        ]);
        $evidenceId = $uploadResp->json('data.id');
        $evidenceUuid = $uploadResp->json('data.evidence_uuid');

        // Generate Signed URL
        $signedResp = $this->postJson("/api/v1/evidence/{$evidenceId}/signed-url", [
            'expires_in' => 15,
        ]);
        $signedResp->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['signed_url', 'evidence_uuid', 'expires_in_minutes'],
            ]);

        $signedUrl = $signedResp->json('data.signed_url');
        $this->assertNotEmpty($signedUrl);

        // Access via valid signed URL without Sanctum token
        Sanctum::actingAs(new User()); // logout
        $validStreamResp = $this->get($signedUrl);
        $validStreamResp->assertStatus(200);

        // Verify Audit Log was written for signed access
        $this->assertDatabaseHas('evidence_audit_logs', [
            'security_evidence_id' => $evidenceId,
            'action' => 'SIGNED_URL_ACCESSED',
        ]);

        // Tamper with signed URL signature -> Must be rejected (403)
        $tamperedUrl = $signedUrl . 'invalid';
        $tamperedResp = $this->get($tamperedUrl);
        $tamperedResp->assertStatus(403);

        // Expired signed URL test: generate a URL that expired 1 minute ago
        $expiredUrl = URL::temporarySignedRoute(
            'evidence.signed-download',
            now()->subMinutes(1),
            ['id' => $evidenceUuid]
        );

        $expiredResp = $this->get($expiredUrl);
        $expiredResp->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Signed URL has expired or is invalid.',
            ]);
    }

    /**
     * Test 7: Intruder Event Workflow:
     * 1. Device submits event
     * 2. Backend validates device ownership
     * 3. Creates event
     * 4. Stores photo evidence
     * 5. Attaches location
     * 6. Queues notification
     * 7. Notifies main user + verified trusted users (and skips unverified trusted users).
     */
    public function test_intruder_event_workflow_notifies_main_user_and_verified_trusted_users(): void
    {
        Queue::fake();

        $mainUser = User::create([
            'name' => 'Main Protected User',
            'phone_number' => '+15559990001',
            'status' => UserStatus::ACTIVE,
        ]);

        $mainDevice = UserDevice::create([
            'user_id' => $mainUser->id,
            'device_uuid' => 'main-device-fcm-1',
            'device_name' => 'Main Pixel',
            'fcm_token' => 'fcm_token_main_user_xyz',
            'status' => DeviceStatus::ACTIVE,
        ]);

        // Trusted User 1: Verified
        $trusted1 = TrustedUser::create([
            'user_id' => $mainUser->id,
            'name' => 'Trusted Alice',
            'phone' => '+15559990002',
            'status' => TrustedUserStatus::VERIFIED,
            'verified_at' => now(),
            'fcm_token' => 'fcm_token_alice_123',
            'priority' => 1,
            'is_active' => true,
        ]);

        // Trusted User 2: Pending / Unverified -> Must NOT receive emergency alerts
        $unverifiedTrusted = TrustedUser::create([
            'user_id' => $mainUser->id,
            'name' => 'Unverified Bob',
            'phone' => '+15559990003',
            'status' => TrustedUserStatus::PENDING,
            'verified_at' => null,
            'fcm_token' => 'fcm_token_bob_456',
            'priority' => 2,
            'is_active' => true,
        ]);

        Sanctum::actingAs($mainUser);

        $fakeIntruderPhoto = UploadedFile::fake()->create('intruder_mugshot.jpg', 300, 'image/jpeg');

        // Intruder event submission
        $response = $this->post('/api/v1/security-events', [
            'device_uuid' => 'main-device-fcm-1',
            'type' => SecurityEventType::INTRUDER_DETECTED->value,
            'severity' => 'CRITICAL',
            'description' => '3 wrong passcode attempts with front camera snap.',
            'latitude' => 37.7891,
            'longitude' => -122.4014,
            'accuracy' => 3.2,
            'photo' => $fakeIntruderPhoto,
        ]);

        $response->assertStatus(201);
        $eventId = $response->json('data.event.id');

        // Verify Event, Location, and Evidence
        $this->assertDatabaseHas('security_events', [
            'id' => $eventId,
            'event_type' => SecurityEventType::INTRUDER_DETECTED->value,
            'user_id' => $mainUser->id,
        ]);

        $this->assertDatabaseHas('security_event_locations', [
            'security_event_id' => $eventId,
            'latitude' => 37.7891,
            'longitude' => -122.4014,
        ]);

        $this->assertDatabaseHas('security_evidence', [
            'security_event_id' => $eventId,
            'user_id' => $mainUser->id,
            'evidence_type' => EvidenceType::FRONT_PHOTO->value,
        ]);

        // Verify FCM Jobs Queued
        // Should queue for:
        // 1. Main user device token
        // 2. Verified Trusted User 1 (Alice)
        // And NOT for unverified Bob
        Queue::assertPushed(SendFcmNotificationJob::class, function ($job) use ($mainDevice, $trusted1) {
            return in_array($job->fcmToken, [$mainDevice->fcm_token, $trusted1->fcm_token], true);
        });

        Queue::assertNotPushed(SendFcmNotificationJob::class, function ($job) use ($unverifiedTrusted) {
            return $job->fcmToken === $unverifiedTrusted->fcm_token;
        });

        // Verify DB Notifications were created
        $this->assertDatabaseHas('notifications', [
            'security_event_id' => $eventId,
            'user_id' => $mainUser->id,
            'device_id' => $mainDevice->id,
        ]);
    }

    /**
     * Test 8: Admin Security Events and Evidence management (list, filter, search, detail, review, stream).
     */
    public function test_admin_security_events_and_evidence_management(): void
    {
        $admin = Admin::create([
            'name' => 'Security Admin',
            'email' => 'sec_admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'SUPER_ADMIN',
            'is_active' => true,
            'custom_permissions' => [
                AdminPermission::SECURITY_EVENTS_VIEW->value,
                AdminPermission::SECURITY_EVIDENCE_VIEW->value,
            ],
        ]);

        $user = User::create([
            'name' => 'Case User',
            'phone_number' => '+15558880001',
            'email' => 'case_user@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $event = SecurityEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'severity' => 'CRITICAL',
            'status' => 'REPORTED',
            'description' => 'Multiple failed biometric attempts.',
            'occurred_at' => now(),
        ]);

        SecurityEventLocation::create([
            'security_event_id' => $event->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
        ]);

        $evidence = SecurityEvidence::create([
            'security_event_id' => $event->id,
            'user_id' => $user->id,
            'evidence_type' => EvidenceType::FRONT_PHOTO,
            'file_name' => 'mugshot.jpg',
            'mime_type' => 'image/jpeg',
            'file_path' => "{$user->id}/{$event->id}/mugshot.jpg",
            'storage_path' => "{$user->id}/{$event->id}/mugshot.jpg",
            'file_size' => 1024,
            'storage_disk' => 'private_evidence',
        ]);
        Storage::disk('private_evidence')->put("{$user->id}/{$event->id}/mugshot.jpg", 'dummy image content');

        Sanctum::actingAs($admin);

        // 1. Admin Event List with Filters & Search
        $listResp = $this->getJson('/admin/security-events?event_type=INTRUDER_DETECTED&status=REPORTED&search=biometric');
        $listResp->assertStatus(200)
            ->assertJsonPath('data.total', 1);

        // 2. Admin Event Detail with Location and Evidence
        $detailResp = $this->getJson("/admin/security-events/{$event->id}");
        $detailResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'event_type' => SecurityEventType::INTRUDER_DETECTED->value,
                    ],
                ],
            ]);

        // 3. Admin Mark Reviewed
        $reviewResp = $this->postJson("/admin/security-events/{$event->id}/review", [
            'status' => 'REVIEWED',
            'notes' => 'Investigated and verified false positive trigger by owner child.',
        ]);
        $reviewResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event' => [
                        'status' => 'REVIEWED',
                        'review_notes' => 'Investigated and verified false positive trigger by owner child.',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('security_events', [
            'id' => $event->id,
            'status' => 'REVIEWED',
            'reviewed_by' => $admin->id,
        ]);

        // Verify AdminAuditLog recorded
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'SECURITY_EVENT_REVIEWED',
            'target_id' => $event->id,
        ]);

        // 4. Admin Evidence List & Stream
        $evListResp = $this->getJson('/admin/evidence');
        $evListResp->assertStatus(200)
            ->assertJsonPath('data.total', 1);

        $streamResp = $this->get("/admin/evidence/{$evidence->id}/stream");
        $streamResp->assertStatus(200);

        // Verify evidence access audit log
        $this->assertDatabaseHas('evidence_audit_logs', [
            'security_evidence_id' => $evidence->id,
            'admin_id' => $admin->id,
            'action' => 'STREAM_ADMIN',
        ]);
    }
}
