<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\SecurityEventType;
use App\Enums\TrustedUserStatus;
use App\Jobs\SendFcmNotificationJob;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\TrustedUser;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\FcmService;
use App\Services\NotificationPayloadSecurity;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\TestCase;

class NotificationPhase9Test extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected UserDevice $device;
    protected NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        FcmService::fake();

        $this->user = User::factory()->create([
            'phone_number' => '+15550001111',
            'phone' => '+15550001111',
            'name' => 'Main Security User',
        ]);

        $this->device = UserDevice::create([
            'user_id' => $this->user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Pixel 9 Pro',
            'model' => 'Pixel 9 Pro',
            'status' => DeviceStatus::ACTIVE,
            'fcm_token' => 'fcm_valid_token_device_main_123',
            'is_protection_enabled' => true,
        ]);

        $this->notificationService = app(NotificationService::class);
    }

    protected function tearDown(): void
    {
        FcmService::resetFake();
        parent::tearDown();
    }

    /**
     * Test 1: Main user notification.
     * Verifies notification DB record schema and FCM dispatch for all notification types.
     */
    public function test_main_user_notification(): void
    {
        $types = [
            NotificationType::SUBSCRIPTION_ACTIVATED,
            NotificationType::SUBSCRIPTION_EXPIRING,
            NotificationType::SUBSCRIPTION_EXPIRED,
            NotificationType::INTRUDER_DETECTED,
            NotificationType::SIM_CHANGED,
            NotificationType::MOTION_DETECTED,
            NotificationType::REMOTE_COMMAND,
            NotificationType::SECURITY_ALERT,
        ];

        foreach ($types as $type) {
            $event = SecurityEvent::create([
                'user_id' => $this->user->id,
                'device_id' => $this->device->id,
                'event_type' => SecurityEventType::INTRUDER_DETECTED,
                'severity' => 'HIGH',
                'description' => "Test event for {$type->value}",
                'triggered_at' => now(),
            ]);

            $title = "Notification Title for {$type->value}";
            $body = "Detailed body message for {$type->value}";
            $payload = ['event_uuid' => $event->event_uuid];

            $notifications = $this->notificationService->sendToUser(
                $this->user,
                $type,
                $title,
                $body,
                $payload,
                $event->id
            );

            $this->assertNotEmpty($notifications);
            $notif = $notifications->first();

            // Verify stored notification DB record:
            // user_id, device_id, security_event_id, type, title, body, payload, status, sent_at, read_at
            $this->assertDatabaseHas('notifications', [
                'id' => $notif->id,
                'user_id' => $this->user->id,
                'device_id' => $this->device->id,
                'security_event_id' => $event->id,
                'type' => $type->value,
                'title' => $title,
                'body' => $body,
                'status' => NotificationStatus::SENT->value,
            ]);

            $notif->refresh();
            $this->assertNotNull($notif->sent_at);
            $this->assertNull($notif->read_at);
            $this->assertFalse($notif->is_read);
            $this->assertEquals($event->id, $notif->security_event_id);
            $this->assertNotNull($notif->fcm_message_id);

            // Verify FCM history captured this send
            $history = FcmService::getHistory();
            $lastCall = end($history);
            $this->assertEquals('fcm_valid_token_device_main_123', $lastCall['token']);
            $this->assertEquals($title, $lastCall['title']);
            $this->assertEquals($body, $lastCall['body']);

            // Verify FCM data payload contains references only
            $this->assertEquals($type->value, $lastCall['data']['type']);
            $this->assertEquals((string) $event->id, $lastCall['data']['event_id']);
            $this->assertEquals((string) $this->device->id, $lastCall['data']['device_id']);
            $this->assertEquals((string) $notif->id, $lastCall['data']['notification_id']);
        }
    }

    /**
     * Test 2: Trusted user notifications.
     * Verifies for security event:
     * 1. Main user
     * 2. Trusted User 1 if verified
     * 3. Trusted User 2 if verified
     * Unverified contacts receive nothing.
     */
    public function test_trusted_user_notifications(): void
    {
        // Scenario A: Main user with 2 verified trusted contacts
        $trusted1 = TrustedUser::create([
            'user_id' => $this->user->id,
            'name' => 'Alice Verified',
            'phone' => '+15551112222',
            'phone_number' => '+15551112222',
            'status' => TrustedUserStatus::VERIFIED,
            'priority' => 1,
            'is_active' => true,
            'verified_at' => now(),
            'fcm_token' => 'fcm_token_trusted_1_alice',
        ]);

        $trusted2 = TrustedUser::create([
            'user_id' => $this->user->id,
            'name' => 'Bob Verified',
            'phone' => '+15553334444',
            'phone_number' => '+15553334444',
            'status' => TrustedUserStatus::VERIFIED,
            'priority' => 2,
            'is_active' => true,
            'verified_at' => now(),
            'fcm_token' => 'fcm_token_trusted_2_bob',
        ]);

        $event = SecurityEvent::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'severity' => 'CRITICAL',
            'description' => 'Unauthorized intruder access attempted',
            'triggered_at' => now(),
        ]);

        $result = $this->notificationService->notifySecurityEvent($event);
        $this->assertTrue($result['delivered']);

        // Check notifications in database for this event
        $notifications = Notification::where('security_event_id', $event->id)->get();

        // Exactly 3 notifications: Main User, Trusted 1, Trusted 2
        $this->assertCount(3, $notifications);

        // Check Main User notification
        $mainNotif = $notifications->first(fn($n) => ($n->payload['target_type'] ?? '') === 'MAIN_USER');
        $this->assertNotNull($mainNotif);
        $this->assertEquals($this->user->id, $mainNotif->user_id);
        $this->assertEquals(NotificationType::INTRUDER_DETECTED->value, $mainNotif->type);

        // Check Trusted User 1 notification
        $t1Notif = $notifications->first(fn($n) => ($n->payload['target_type'] ?? '') === 'TRUSTED_USER_1');
        $this->assertNotNull($t1Notif);
        $this->assertEquals($trusted1->id, $t1Notif->payload['trusted_user_id']);

        // Check Trusted User 2 notification
        $t2Notif = $notifications->first(fn($n) => ($n->payload['target_type'] ?? '') === 'TRUSTED_USER_2');
        $this->assertNotNull($t2Notif);
        $this->assertEquals($trusted2->id, $t2Notif->payload['trusted_user_id']);

        // Verify FCM was dispatched for main user, trusted 1, and trusted 2
        $tokensCalled = array_column(FcmService::getHistory(), 'token');
        $this->assertContains('fcm_valid_token_device_main_123', $tokensCalled);
        $this->assertContains('fcm_token_trusted_1_alice', $tokensCalled);
        $this->assertContains('fcm_token_trusted_2_bob', $tokensCalled);

        // Scenario B: User 2 has 1 verified and 1 unverified contact
        $user2 = User::factory()->create([
            'name' => 'User Two',
            'phone_number' => '+15552223333',
            'phone' => '+15552223333',
        ]);
        $user2Device = UserDevice::create([
            'user_id' => $user2->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'User 2 Phone',
            'status' => DeviceStatus::ACTIVE,
            'fcm_token' => 'fcm_user_2_token',
        ]);

        $verifiedContact = TrustedUser::create([
            'user_id' => $user2->id,
            'name' => 'Verified Contact',
            'phone' => '+15557778888',
            'status' => TrustedUserStatus::VERIFIED,
            'priority' => 1,
            'is_active' => true,
            'verified_at' => now(),
            'fcm_token' => 'fcm_verified_contact',
        ]);

        $unverifiedContact = TrustedUser::create([
            'user_id' => $user2->id,
            'name' => 'Unverified Contact',
            'phone' => '+15559990000',
            'status' => TrustedUserStatus::PENDING,
            'priority' => 2,
            'is_active' => true,
            'verified_at' => null,
            'fcm_token' => 'fcm_unverified_contact',
        ]);

        $event2 = SecurityEvent::create([
            'user_id' => $user2->id,
            'device_id' => $user2Device->id,
            'event_type' => SecurityEventType::SIM_CHANGED,
            'severity' => 'HIGH',
            'description' => 'SIM swap on user 2',
            'triggered_at' => now(),
        ]);

        $result2 = $this->notificationService->notifySecurityEvent($event2);
        $this->assertTrue($result2['delivered']);

        $notifications2 = Notification::where('security_event_id', $event2->id)->get();
        // Should only notify Main User and Verified Contact (2 total, unverified excluded)
        $this->assertCount(2, $notifications2);

        $unverifiedNotif = $notifications2->first(fn($n) => ($n->payload['trusted_user_id'] ?? null) === $unverifiedContact->id);
        $this->assertNull($unverifiedNotif);

        $allTokens = array_column(FcmService::getHistory(), 'token');
        $this->assertNotContains('fcm_unverified_contact', $allTokens);
    }

    /**
     * Test 3: Invalid or unregistered FCM token handling.
     * When FCM returns UNREGISTERED / InvalidRegistration, notification becomes FAILED
     * and the dead token is cleared from the device.
     */
    public function test_invalid_fcm_token_handling(): void
    {
        $invalidDevice = UserDevice::create([
            'user_id' => $this->user->id,
            'device_uuid' => (string) Str::uuid(),
            'device_name' => 'Old Phone',
            'model' => 'Old Phone',
            'status' => DeviceStatus::ACTIVE,
            'fcm_token' => 'invalid_fcm_token',
        ]);

        $notification = $this->notificationService->sendToDevice(
            $invalidDevice,
            NotificationType::SECURITY_ALERT,
            'Security Alert Title',
            'Alert Body Message',
            ['key' => 'val']
        );

        $notification->refresh();

        // Notification status must become FAILED
        $this->assertEquals(NotificationStatus::FAILED->value, $notification->status);
        $this->assertNotNull($notification->failure_reason);
        $this->assertStringContainsString('unregistered', strtolower($notification->failure_reason));

        // Invalid token must be stripped from the device
        $invalidDevice->refresh();
        $this->assertNull($invalidDevice->fcm_token);
    }

    /**
     * Test 4: Queue retry on transient failures.
     * Verifies retry where appropriate and exhaustion handling.
     */
    public function test_queue_retry_and_exhaustion(): void
    {
        $attempts = 0;
        FcmService::fake(function (string $token, string $title, string $body, array $data) use (&$attempts) {
            $attempts++;
            if ($attempts < 3) {
                // Return transient failure on attempts 1 and 2
                return [
                    'success' => false,
                    'error' => 'UNAVAILABLE',
                    'is_unregistered' => false,
                    'should_retry' => true,
                    'message' => 'FCM server transient 503',
                ];
            }

            // Succeed on attempt 3
            return [
                'success' => true,
                'message_id' => 'fcm_recovered_msg_777',
                'error' => null,
            ];
        });

        $notif = Notification::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'title' => 'Retry Test',
            'body' => 'Testing transient failure retry',
            'type' => NotificationType::REMOTE_COMMAND->value,
            'status' => NotificationStatus::PENDING->value,
        ]);

        $job = new SendFcmNotificationJob($notif, $this->device->fcm_token);
        $fcmService = app(FcmService::class);

        // Attempt 1 (Transient fail -> should release with backoff)
        $job->handle($fcmService);
        $notif->refresh();
        $this->assertEquals(NotificationStatus::PENDING->value, $notif->status);
        $this->assertStringContainsString('transient', strtolower($notif->failure_reason));

        // Attempt 2 (Transient fail -> should release with backoff)
        $job->handle($fcmService);
        $notif->refresh();
        $this->assertEquals(NotificationStatus::PENDING->value, $notif->status);

        // Attempt 3 (Succeeds)
        $job->handle($fcmService);
        $notif->refresh();
        $this->assertEquals(NotificationStatus::SENT->value, $notif->status);
        $this->assertEquals('fcm_recovered_msg_777', $notif->fcm_message_id);
        $this->assertNotNull($notif->sent_at);
        $this->assertNull($notif->failure_reason);

        // Test Retry Exhaustion: When failures exceed tries
        $exhaustNotif = Notification::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'title' => 'Exhaustion Test',
            'body' => 'Testing retry exhaustion',
            'type' => NotificationType::REMOTE_COMMAND->value,
            'status' => NotificationStatus::PENDING->value,
        ]);

        FcmService::fake(fn() => [
            'success' => false,
            'error' => 'UNAVAILABLE',
            'is_unregistered' => false,
            'should_retry' => true,
            'message' => 'Persistent outage',
        ]);

        $exhaustJob = new class($exhaustNotif, 'exhaust_token') extends SendFcmNotificationJob {
            public int $mockAttempts = 3;
            public function attempts(): int
            {
                return $this->mockAttempts;
            }
        };

        $exhaustJob->handle($fcmService);
        $exhaustNotif->refresh();
        $this->assertEquals(NotificationStatus::FAILED->value, $exhaustNotif->status);
        $this->assertStringContainsString('exhausted', strtolower($exhaustNotif->failure_reason));
    }

    /**
     * Test 5: Event remains successful after FCM failure.
     * Rule: If FCM fails, security event must remain successful, notification becomes FAILED.
     */
    public function test_event_remains_successful_after_fcm_failure(): void
    {
        // Configure FCM to completely fail with non-retryable error
        FcmService::fake(fn() => [
            'success' => false,
            'error' => 'UNREGISTERED',
            'is_unregistered' => true,
            'should_retry' => false,
            'message' => 'Token has been unregistered',
        ]);

        $token = $this->user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/security-events', [
                'device_uuid' => $this->device->device_uuid,
                'event_type' => SecurityEventType::SIM_CHANGED->value,
                'severity' => 'CRITICAL',
                'description' => 'SIM card was removed or changed unexpectedly',
                'latitude' => 37.7749,
                'longitude' => -122.4194,
            ]);

        // 1. Security event API must return 201 Created and be successful
        $response->assertStatus(201);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('message', 'Security event recorded successfully.');

        $eventId = $response->json('data.event.id');
        $this->assertNotNull($eventId);

        // 2. Security event record must remain in database
        $eventInDb = SecurityEvent::find($eventId);
        $this->assertNotNull($eventInDb);
        $this->assertEquals(SecurityEventType::SIM_CHANGED, $eventInDb->event_type);

        // 3. Notification record was created, but status became FAILED due to FCM failure
        $notif = Notification::where('security_event_id', $eventId)->first();
        $this->assertNotNull($notif);
        $this->assertEquals(NotificationStatus::FAILED->value, $notif->status);
        $this->assertStringContainsString('unregistered', strtolower($notif->failure_reason));
    }

    /**
     * Test 6: Payload security.
     * FCM payload must contain references only:
     * - type
     * - event_id
     * - device_id
     * - notification_id
     *
     * Never include:
     * - password
     * - OTP
     * - auth token
     * - activation code
     * - master PIN
     * - private photo binary
     * - private credentials
     */
    public function test_payload_security(): void
    {
        $event = SecurityEvent::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'severity' => 'HIGH',
            'description' => 'Intruder photo taken',
            'triggered_at' => now(),
        ]);

        $notification = Notification::create([
            'user_id' => $this->user->id,
            'device_id' => $this->device->id,
            'security_event_id' => $event->id,
            'title' => 'Intruder Detected',
            'body' => 'An unauthorized intruder was detected on your phone.',
            'type' => NotificationType::INTRUDER_DETECTED->value,
            'payload' => [
                'event_uuid' => $event->event_uuid,
                'target_type' => 'MAIN_USER',
            ],
            'status' => NotificationStatus::PENDING->value,
        ]);

        // 1. Build FCM References-Only payload
        $fcmData = NotificationPayloadSecurity::buildFcmReferencesOnlyPayload($notification);

        // Check reference keys
        $this->assertArrayHasKey('type', $fcmData);
        $this->assertArrayHasKey('event_id', $fcmData);
        $this->assertArrayHasKey('device_id', $fcmData);
        $this->assertArrayHasKey('notification_id', $fcmData);

        // Disallow any other keys
        $allowedKeys = ['type', 'event_id', 'device_id', 'notification_id'];
        $this->assertEqualsCanonicalizing($allowedKeys, array_keys($fcmData));

        // 2. Validate strict check passes for safe payload
        NotificationPayloadSecurity::assertFcmPayloadIsSafe($fcmData);

        // 3. Test that forbidden keys trigger exceptions
        $forbiddenAttempts = [
            ['password' => 'secret123'],
            ['otp' => '948271'],
            ['auth_token' => 'bearer_xyz'],
            ['activation_code' => 'SAT-ABC-123'],
            ['master_pin' => '1234'],
            ['private_photo_binary' => base64_encode('binarydata')],
            ['private_credentials' => 'secret_key'],
        ];

        foreach ($forbiddenAttempts as $forbidden) {
            $tampered = array_merge($fcmData, $forbidden);

            $this->expectException(InvalidArgumentException::class);
            NotificationPayloadSecurity::assertFcmPayloadIsSafe($tampered);
        }

        // 4. Test sanitizeDbPayload strips sensitive keys
        $dirtyPayload = [
            'event_uuid' => 'evt_123',
            'password' => 'myPassword',
            'otp' => '123456',
            'auth_token' => 'token_val',
            'activation_code' => 'SAT-999-888',
            'master_pin' => '4321',
            'photo_binary' => 'blob123',
            'credentials' => ['db_pass' => 'root'],
            'safe_metadata' => 'allowed_info',
        ];

        $clean = NotificationPayloadSecurity::sanitizeDbPayload($dirtyPayload);

        $this->assertArrayHasKey('event_uuid', $clean);
        $this->assertArrayHasKey('safe_metadata', $clean);
        $this->assertArrayNotHasKey('password', $clean);
        $this->assertArrayNotHasKey('otp', $clean);
        $this->assertArrayNotHasKey('auth_token', $clean);
        $this->assertArrayNotHasKey('activation_code', $clean);
        $this->assertArrayNotHasKey('master_pin', $clean);
        $this->assertArrayNotHasKey('photo_binary', $clean);
        $this->assertArrayNotHasKey('credentials', $clean);
    }

    /**
     * Test 7: User Notifications API endpoints.
     * Listing, unread count, mark as read, mark all as read.
     */
    public function test_user_notification_api_endpoints(): void
    {
        $token = $this->user->createToken('test_token')->plainTextToken;

        // Create notifications for this user
        Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Test Notif 1',
            'body' => 'Body 1',
            'type' => NotificationType::SECURITY_ALERT->value,
            'is_read' => false,
            'status' => 'SENT',
        ]);

        $notif2 = Notification::create([
            'user_id' => $this->user->id,
            'title' => 'Test Notif 2',
            'body' => 'Body 2',
            'type' => NotificationType::SUBSCRIPTION_ACTIVATED->value,
            'is_read' => false,
            'status' => 'SENT',
        ]);

        // 1. List notifications
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/notifications');

        $listRes->assertStatus(200);
        $listRes->assertJsonPath('data.unread_count', 2);
        $this->assertCount(2, $listRes->json('data.notifications'));

        // 2. Mark notif 2 as read
        $readRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/notifications/{$notif2->id}/read");

        $readRes->assertStatus(200);
        $readRes->assertJsonPath('data.notification.is_read', true);
        $this->assertNotNull($readRes->json('data.notification.read_at'));

        // 3. Mark all as read
        $readAllRes = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/notifications/read-all');

        $readAllRes->assertStatus(200);
        $this->assertEquals(0, Notification::where('user_id', $this->user->id)->where('is_read', false)->count());
    }
}
