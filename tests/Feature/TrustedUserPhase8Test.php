<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\SecurityEventType;
use App\Enums\TrustedUserStatus;
use App\Enums\UserStatus;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Models\TrustedUser;
use App\Models\User;
use App\Services\SecurityAlertDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TrustedUserPhase8Test extends TestCase
{
    use RefreshDatabase;

    protected User $user1;
    protected User $user2;
    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user1 = User::create([
            'name' => 'Alice Johnson',
            'phone_number' => '+15551110001',
            'email' => 'alice@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->user2 = User::create([
            'name' => 'Bob Miller',
            'phone_number' => '+15552220002',
            'email' => 'bob@example.com',
            'status' => UserStatus::ACTIVE,
        ]);

        $this->admin = Admin::create([
            'name' => 'Admin Officer',
            'email' => 'admin@trusted-test.com',
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
     * Test 1: Add 1 trusted user.
     * Verifies phone validation, pending status, verification reference generation,
     * linked to main user, and no exposure of private account data.
     */
    public function test_add_first_trusted_user(): void
    {
        $response = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'John Doe',
                'phone' => '+15553334444',
                'relationship' => 'Brother',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trusted_user' => [
                        'name' => 'John Doe',
                        'phone' => '+15553334444',
                        'user_id' => $this->user1->id,
                        'status' => TrustedUserStatus::PENDING->value,
                    ],
                ],
            ]);

        $trustedUser = $response->json('data.trusted_user');
        $this->assertNotEmpty($trustedUser['verification_token']);
        $this->assertNotEmpty($trustedUser['verification_reference']);
        $this->assertNull($trustedUser['verified_at']);

        // Assert no private account data (password, remember_token, user tokens) is leaked
        $this->assertArrayNotHasKey('password', $trustedUser);
        $this->assertArrayNotHasKey('token', $trustedUser);

        $this->assertDatabaseHas('trusted_users', [
            'user_id' => $this->user1->id,
            'name' => 'John Doe',
            'phone' => '+15553334444',
            'status' => TrustedUserStatus::PENDING->value,
        ]);
    }

    /**
     * Test 2: Add 2 trusted users successfully.
     */
    public function test_add_second_trusted_user(): void
    {
        // Add 1st
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Contact One',
                'phone' => '+15551112222',
                'relationship' => 'Spouse',
            ])->assertStatus(201);

        // Add 2nd
        $secondResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Contact Two',
                'phone' => '+15553334444',
                'relationship' => 'Father',
            ]);

        $secondResp->assertStatus(201);
        $this->assertEquals(2, $this->user1->trustedUsers()->count());

        // GET /api/v1/trusted-users returns both
        $listResp = $this->authenticateAs($this->user1)
            ->getJson('/api/v1/trusted-users');

        $listResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'count' => 2,
                    'max_allowed' => 2,
                ],
            ]);
        $this->assertCount(2, $listResp->json('data.trusted_users'));
    }

    /**
     * Test 3: Reject 3rd trusted user (Maximum 2 rule).
     */
    public function test_reject_third_trusted_user(): void
    {
        // Add 1
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Contact 1',
                'phone' => '+15551111111',
            ])->assertStatus(201);

        // Add 2
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Contact 2',
                'phone' => '+15552222222',
            ])->assertStatus(201);

        // Reject 3rd
        $thirdResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Contact 3',
                'phone' => '+15553333333',
            ]);

        $thirdResp->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'MAX_TRUSTED_USERS_EXCEEDED',
            ]);

        // Still only 2 contacts in DB
        $this->assertEquals(2, $this->user1->trustedUsers()->count());
    }

    /**
     * Test 4: Update trusted user details.
     * Updating name updates record; updating phone requires re-verification.
     */
    public function test_update_trusted_user(): void
    {
        $trusted = $this->user1->trustedUsers()->create([
            'name' => 'Old Name',
            'phone' => '+15551112222',
            'status' => TrustedUserStatus::VERIFIED,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Update name only: status remains VERIFIED
        $updateNameResp = $this->authenticateAs($this->user1)
            ->putJson('/api/v1/trusted-users/' . $trusted->id, [
                'name' => 'New Contact Name',
            ]);

        $updateNameResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trusted_user' => [
                        'name' => 'New Contact Name',
                        'status' => TrustedUserStatus::VERIFIED->value,
                    ],
                ],
            ]);

        // Update phone: resets status to PENDING and clears verified_at
        $updatePhoneResp = $this->authenticateAs($this->user1)
            ->putJson('/api/v1/trusted-users/' . $trusted->id, [
                'phone' => '+15559998888',
            ]);

        $updatePhoneResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trusted_user' => [
                        'phone' => '+15559998888',
                        'status' => TrustedUserStatus::PENDING->value,
                    ],
                ],
            ]);

        $trusted->refresh();
        $this->assertEquals('+15559998888', $trusted->phone);
        $this->assertEquals(TrustedUserStatus::PENDING, $trusted->status);
        $this->assertNull($trusted->verified_at);
    }

    /**
     * Test 5: Delete trusted user.
     */
    public function test_delete_trusted_user(): void
    {
        $trusted = $this->user1->trustedUsers()->create([
            'name' => 'Contact To Delete',
            'phone' => '+15558887777',
            'status' => TrustedUserStatus::PENDING,
        ]);

        $deleteResp = $this->authenticateAs($this->user1)
            ->deleteJson('/api/v1/trusted-users/' . $trusted->id);

        $deleteResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'deleted' => true,
                    'id' => $trusted->id,
                ],
            ]);

        $this->assertDatabaseMissing('trusted_users', [
            'id' => $trusted->id,
        ]);
    }

    /**
     * Test 6: Verify trusted user.
     * Sets verified_at, status = VERIFIED, and enables sensitive alerts.
     */
    public function test_verify_trusted_user(): void
    {
        $trusted = $this->user1->trustedUsers()->create([
            'name' => 'Verify Me',
            'phone' => '+15557776666',
            'status' => TrustedUserStatus::PENDING,
            'verification_token' => 'secure_verification_token_456',
            'verification_reference' => 'TRU-XYZ123',
        ]);

        // Wrong token -> 422
        $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users/' . $trusted->id . '/verify', [
                'token' => 'wrong_token',
            ])->assertStatus(422);

        // Correct token -> 200
        $verifyResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users/' . $trusted->id . '/verify', [
                'token' => 'secure_verification_token_456',
            ]);

        $verifyResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trusted_user' => [
                        'status' => TrustedUserStatus::VERIFIED->value,
                    ],
                ],
            ]);

        $trusted->refresh();
        $this->assertEquals(TrustedUserStatus::VERIFIED, $trusted->status);
        $this->assertNotNull($trusted->verified_at);
        $this->assertTrue($trusted->isVerified());
    }

    /**
     * Test 7: Unauthorized access:
     * - User cannot access, update, delete, or verify another user's trusted contact.
     * - Phone validation rejects malformed or duplicate numbers.
     */
    public function test_unauthorized_access_prevented(): void
    {
        // Alice creates a contact
        $aliceContact = $this->user1->trustedUsers()->create([
            'name' => 'Alice Private Contact',
            'phone' => '+15551239999',
            'status' => TrustedUserStatus::PENDING,
        ]);

        // Bob tries to view list -> must NOT see Alice's contact
        $bobList = $this->authenticateAs($this->user2)
            ->getJson('/api/v1/trusted-users');
        $bobList->assertStatus(200);
        $this->assertCount(0, $bobList->json('data.trusted_users'));

        // Bob tries to update Alice's contact -> 403 Forbidden
        $bobUpdate = $this->authenticateAs($this->user2)
            ->putJson('/api/v1/trusted-users/' . $aliceContact->id, [
                'name' => 'Hacked Name',
            ]);
        $bobUpdate->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED_ACCESS',
            ]);

        // Bob tries to verify Alice's contact -> 403 Forbidden
        $bobVerify = $this->authenticateAs($this->user2)
            ->postJson('/api/v1/trusted-users/' . $aliceContact->id . '/verify');
        $bobVerify->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED_ACCESS',
            ]);

        // Bob tries to delete Alice's contact -> 403 Forbidden
        $bobDelete = $this->authenticateAs($this->user2)
            ->deleteJson('/api/v1/trusted-users/' . $aliceContact->id);
        $bobDelete->assertStatus(403)
            ->assertJson([
                'success' => false,
                'code' => 'UNAUTHORIZED_ACCESS',
            ]);

        // Duplicate phone validation for same user -> 422
        $dupResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Duplicate Phone Guy',
                'phone' => '+15551239999',
            ]);
        $dupResp->assertStatus(422)
            ->assertJson([
                'success' => false,
                'code' => 'DUPLICATE_PHONE',
            ]);

        // Malformed phone validation -> 422
        $badPhoneResp = $this->authenticateAs($this->user1)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Bad Phone',
                'phone' => '123', // too short
            ]);
        $badPhoneResp->assertStatus(422);
    }

    /**
     * Test 8: Admin access:
     * - Show trusted users on user detail.
     * - Search and filter.
     * - View verification status.
     * - Disable if necessary.
     * - Audit sensitive changes.
     */
    public function test_admin_trusted_user_management(): void
    {
        $contact = $this->user1->trustedUsers()->create([
            'name' => 'Emergency Contact',
            'phone' => '+15554443322',
            'status' => TrustedUserStatus::VERIFIED,
            'verified_at' => now(),
            'verification_reference' => 'TRU-EMERG99',
            'is_active' => true,
        ]);

        // 1. Show trusted users on user detail
        $userDetailResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/users/' . $this->user1->id);

        $userDetailResp->assertStatus(200);
        $userTrustedContacts = $userDetailResp->json('data.user.trusted_users');
        $this->assertNotEmpty($userTrustedContacts);
        $this->assertEquals('Emergency Contact', $userTrustedContacts[0]['name']);
        $this->assertEquals(TrustedUserStatus::VERIFIED->value, $userTrustedContacts[0]['status']);

        // 2. Search trusted users by name
        $searchResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/trusted-users?search=Emergency');
        $searchResp->assertStatus(200);
        $this->assertCount(1, $searchResp->json('data.data'));

        // 3. Filter by verification status
        $filterResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/trusted-users?status=VERIFIED');
        $filterResp->assertStatus(200);
        $this->assertCount(1, $filterResp->json('data.data'));

        // 4. View single trusted user verification status
        $viewResp = $this->authenticateAs($this->admin)
            ->getJson('/api/v1/admin/trusted-users/' . $contact->id);
        $viewResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_verified' => true,
                    'verification_status' => TrustedUserStatus::VERIFIED->value,
                    'verification_reference' => 'TRU-EMERG99',
                ],
            ]);

        // 5. Disable trusted user
        $disableResp = $this->authenticateAs($this->admin)
            ->postJson('/api/v1/admin/trusted-users/' . $contact->id . '/disable', [
                'reason' => 'Suspected compromised number',
            ]);

        $disableResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Trusted user disabled successfully.',
            ]);

        $contact->refresh();
        $this->assertEquals(TrustedUserStatus::DISABLED, $contact->status);
        $this->assertFalse($contact->is_active);

        // 6. Audit sensitive changes: verify entry in admin_audit_logs
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'TRUSTED_USER_DISABLED',
            'target_id' => $contact->id,
        ]);
    }

    /**
     * Test 9: Security Alert Delivery Targets & Evidence Protection:
     * - Target 1: Main User.
     * - Target 2: Trusted User 1 (verified).
     * - Target 3: Trusted User 2 (verified).
     * - Unverified (PENDING) contact is excluded.
     * - Do not send private evidence directly in notification payload.
     */
    public function test_security_alert_delivery_targets_and_evidence_payload(): void
    {
        // Main user has 2 verified contacts and 1 pending on user2
        $verifiedContact1 = $this->user1->trustedUsers()->create([
            'name' => 'Verified Guardian 1',
            'phone' => '+15550000001',
            'status' => TrustedUserStatus::VERIFIED,
            'verified_at' => now(),
            'priority' => 1,
            'is_active' => true,
        ]);

        $verifiedContact2 = $this->user1->trustedUsers()->create([
            'name' => 'Verified Guardian 2',
            'phone' => '+15550000002',
            'status' => TrustedUserStatus::VERIFIED,
            'verified_at' => now(),
            'priority' => 2,
            'is_active' => true,
        ]);

        // Create a security event with private evidence
        $event = SecurityEvent::create([
            'user_id' => $this->user1->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'severity' => 'CRITICAL',
            'description' => 'Unauthorized device access detected.',
            'triggered_at' => now(),
        ]);

        // Attach private evidence (e.g. photos/audio)
        SecurityEvidence::create([
            'security_event_id' => $event->id,
            'evidence_type' => \App\Enums\EvidenceType::FRONT_PHOTO,
            'file_path' => 'private/evidence/intruder_face_999.jpg',
            'file_size' => 102400,
            'mime_type' => 'image/jpeg',
            'storage_disk' => 'local',
        ]);

        // Deliver alert
        $service = new SecurityAlertDeliveryService();
        $result = $service->deliverAlert($event);

        $this->assertEquals(3, $result['recipients_count']); // Main user + 2 verified trusted users

        // Check notifications created
        $notifications = Notification::where('user_id', $this->user1->id)->latest()->take(3)->get();
        $this->assertCount(3, $notifications);

        // Verify notification targets: MAIN_USER, TRUSTED_USER_1, TRUSTED_USER_2
        $recipientRoles = $notifications->pluck('payload.recipient_role')
            ->filter()
            ->concat($notifications->pluck('payload.target_type')->filter())
            ->unique()
            ->values()
            ->all();

        $this->assertContains('MAIN_USER', $recipientRoles);
        $this->assertContains('TRUSTED_USER_1', $recipientRoles);
        $this->assertContains('TRUSTED_USER_2', $recipientRoles);

        // Crucial check: "Do not send private evidence directly in notification payload."
        foreach ($notifications as $notification) {
            $payload = $notification->payload;
            $this->assertArrayNotHasKey('file_path', $payload);
            $this->assertArrayNotHasKey('evidence_url', $payload);
            $this->assertArrayNotHasKey('photo_url', $payload);
            $this->assertStringNotContainsString('intruder_face_999.jpg', json_encode($payload));
        }

        // Unverified contacts must NOT receive sensitive alerts
        $unverifiedContact = $this->user2->trustedUsers()->create([
            'name' => 'Pending Contact',
            'phone' => '+15559990000',
            'status' => TrustedUserStatus::PENDING,
            'verified_at' => null,
            'is_active' => true,
        ]);

        $event2 = SecurityEvent::create([
            'user_id' => $this->user2->id,
            'event_type' => SecurityEventType::SIM_CHANGED,
            'severity' => 'HIGH',
            'description' => 'SIM card changed.',
            'triggered_at' => now(),
        ]);

        $result2 = $service->deliverAlert($event2);
        // Only main user receives it, unverified contact is excluded
        $this->assertEquals(1, $result2['recipients_count']);
        $this->assertEquals('MAIN_USER', $result2['recipients'][0]['target_type']);
    }
}
