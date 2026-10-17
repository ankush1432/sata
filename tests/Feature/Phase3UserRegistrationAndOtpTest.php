<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\Otp\MockOtpProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class Phase3UserRegistrationAndOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MockOtpProvider::clear();
        RateLimiter::clear('register:127.0.0.1');
    }

    /**
     * Test 1: Registration creates user with PENDING status, stores OTP hash only, never logs raw OTP.
     */
    public function test_registration_creates_pending_user_and_dispatches_otp(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Walker',
            'mobile' => '+15551112233',
            'email' => 'alice.walker@example.com',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Alice Walker',
                        'mobile' => '+15551112233',
                        'email' => 'alice.walker@example.com',
                        'status' => UserStatus::PENDING->value,
                    ],
                ],
            ]);

        // Assert user created in PENDING status with hashed password
        $user = User::where('phone_number', '+15551112233')->first();
        $this->assertNotNull($user);
        $this->assertEquals(UserStatus::PENDING, $user->status);
        $this->assertNull($user->mobile_verified_at);
        $this->assertTrue(Hash::check('SecurePass123!', $user->password));

        // Assert OTP verification record: only hash stored, plaintext code is null
        $otpRecord = OtpVerification::where('phone_number', '+15551112233')->latest('id')->first();
        $this->assertNotNull($otpRecord);
        $this->assertNull($otpRecord->otp_code, 'Raw plaintext OTP must NEVER be stored in database');
        $this->assertNotNull($otpRecord->code_hash, 'Only OTP hash must be stored');
        $this->assertEquals(0, $otpRecord->attempts);
        $this->assertFalse($otpRecord->is_verified);
        $this->assertTrue($otpRecord->expires_at->isFuture());

        // Assert configured provider received the dispatched OTP
        $dispatchedCode = MockOtpProvider::getLastOtpFor('+15551112233');
        $this->assertNotNull($dispatchedCode);
        $this->assertEquals(6, strlen($dispatchedCode));
        $this->assertTrue(Hash::check($dispatchedCode, $otpRecord->code_hash));
    }

    /**
     * Test: OTP is never returned in production API responses.
     */
    public function test_otp_is_never_returned_in_production_environment(): void
    {
        Config::set('app.env', 'production');

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Prod User',
            'mobile' => '+15558889900',
        ]);

        $response->assertStatus(201);
        $this->assertArrayNotHasKey('dev_otp', $response->json('data'));
    }

    /**
     * Test 2: OTP verification transitions user to AWAITING_ACTIVATION and sets mobile_verified_at.
     */
    public function test_otp_verification_transitions_user_to_awaiting_activation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Bob Smith',
            'mobile' => '+15553334455',
        ]);

        $otp = MockOtpProvider::getLastOtpFor('+15553334455');

        $verifyResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => '+15553334455',
            'otp' => $otp,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'mobile' => '+15553334455',
                        'status' => UserStatus::AWAITING_ACTIVATION->value,
                    ],
                ],
            ]);

        $this->assertNotEmpty($verifyResponse->json('data.token'));

        // Verify DB persistence
        $user = User::where('phone_number', '+15553334455')->first();
        $this->assertEquals(UserStatus::AWAITING_ACTIVATION, $user->status);
        $this->assertNotNull($user->mobile_verified_at);
        $this->assertNotNull($user->phone_verified_at);

        // Verify OTP marked as verified
        $otpRecord = OtpVerification::where('phone_number', '+15553334455')->latest('id')->first();
        $this->assertTrue($otpRecord->is_verified);
        $this->assertNotNull($otpRecord->verified_at);
    }

    /**
     * Test 3: Invalid OTP is rejected and increments attempts counter.
     */
    public function test_invalid_otp_fails_and_increments_attempts(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Charlie Day',
            'mobile' => '+15554445566',
        ]);

        $verifyResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => '+15554445566',
            'otp' => '999999', // Incorrect OTP
        ]);

        $verifyResponse->assertStatus(401)
            ->assertJson(['success' => false]);

        $user = User::where('phone_number', '+15554445566')->first();
        $this->assertEquals(UserStatus::PENDING, $user->status);
        $this->assertNull($user->mobile_verified_at);

        $otpRecord = OtpVerification::where('phone_number', '+15554445566')->latest('id')->first();
        $this->assertEquals(1, $otpRecord->attempts);
        $this->assertFalse($otpRecord->is_verified);
    }

    /**
     * Test 4: Expired OTP is rejected.
     */
    public function test_expired_otp_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Diana Prince',
            'mobile' => '+15555556677',
        ]);

        $otp = MockOtpProvider::getLastOtpFor('+15555556677');

        // Manually expire the OTP
        OtpVerification::where('phone_number', '+15555556677')
            ->update(['expires_at' => now()->subMinutes(5)]);

        $verifyResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => '+15555556677',
            'otp' => $otp,
        ]);

        $verifyResponse->assertStatus(422)
            ->assertJson(['success' => false]);

        $user = User::where('phone_number', '+15555556677')->first();
        $this->assertEquals(UserStatus::PENDING, $user->status);
        $this->assertNull($user->mobile_verified_at);
    }

    /**
     * Test 5: Maximum attempts reached invalidates OTP and locks verification.
     */
    public function test_maximum_attempts_exceeded_invalidates_otp(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Evan Wright',
            'mobile' => '+15556667788',
        ]);

        $realOtp = MockOtpProvider::getLastOtpFor('+15556667788');

        // Submit 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/v1/auth/verify-otp', [
                'mobile' => '+15556667788',
                'otp' => '000000',
            ]);
            $response->assertJson(['success' => false]);
        }

        // Even with correct OTP, subsequent verification must fail
        $subsequentResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => '+15556667788',
            'otp' => $realOtp,
        ]);

        $subsequentResponse->assertStatus(422)
            ->assertJson(['success' => false]);

        $user = User::where('phone_number', '+15556667788')->first();
        $this->assertEquals(UserStatus::PENDING, $user->status);
    }

    /**
     * Test 6: Resend throttling and invalidation of previous active OTP.
     */
    public function test_resend_throttling_and_invalidates_previous_otp(): void
    {
        $mobile = '+15557778899';

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Fiona Gallagher',
            'mobile' => $mobile,
        ]);

        $firstOtp = MockOtpProvider::getLastOtpFor($mobile);

        // First resend request succeeds
        RateLimiter::clear('resend-otp-mobile:' . $mobile);
        $resendResponse1 = $this->postJson('/api/v1/auth/resend-otp', [
            'mobile' => $mobile,
        ]);

        $resendResponse1->assertStatus(200)
            ->assertJson(['success' => true]);

        $secondOtp = MockOtpProvider::getLastOtpFor($mobile);
        $this->assertNotEquals($firstOtp, $secondOtp);

        // Immediate subsequent resend within cooldown is throttled with 429
        $resendResponse2 = $this->postJson('/api/v1/auth/resend-otp', [
            'mobile' => $mobile,
        ]);

        $resendResponse2->assertStatus(429)
            ->assertJson(['success' => false]);

        // Attempting to verify the old first OTP must fail because it was invalidated by resend
        $oldOtpVerify = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => $mobile,
            'otp' => $firstOtp,
        ]);
        $oldOtpVerify->assertJson(['success' => false]);

        // Verifying with the new second OTP succeeds
        $newOtpVerify = $this->postJson('/api/v1/auth/verify-otp', [
            'mobile' => $mobile,
            'otp' => $secondOtp,
        ]);
        $newOtpVerify->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * Test 7: Duplicate mobile registration is rejected with 422.
     */
    public function test_duplicate_mobile_registration_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'User One',
            'mobile' => '+15559990011',
        ])->assertStatus(201);

        $duplicateResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'User Two',
            'mobile' => '+15559990011',
        ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['mobile'],
            ]);
    }

    /**
     * Test 8: Duplicate email registration is rejected with 422.
     */
    public function test_duplicate_email_registration_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Unique One',
            'mobile' => '+15550001122',
            'email' => 'shared@example.com',
        ])->assertStatus(201);

        $duplicateResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'Unique Two',
            'mobile' => '+15550001133',
            'email' => 'shared@example.com',
        ]);

        $duplicateResponse->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
            ]);
    }

    /**
     * Test 9: Login with password returns Bearer token.
     */
    public function test_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Grace Hopper',
            'phone_number' => '+15552223344',
            'email' => 'grace@navy.mil',
            'password' => Hash::make('Compiler1952!'),
            'status' => UserStatus::AWAITING_ACTIVATION,
            'mobile_verified_at' => now(),
        ]);

        // Successful login with mobile + password
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'mobile' => '+15552223344',
            'password' => 'Compiler1952!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'mobile' => '+15552223344',
                        'email' => 'grace@navy.mil',
                    ],
                ],
            ]);

        $this->assertNotEmpty($loginResponse->json('data.token'));

        // Invalid password returns 401
        $badPasswordResponse = $this->postJson('/api/v1/auth/login', [
            'mobile' => '+15552223344',
            'password' => 'WrongPassword!',
        ]);

        $badPasswordResponse->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /**
     * Test 10: Logout revokes bearer token and prevents subsequent access.
     */
    public function test_logout_revokes_bearer_token(): void
    {
        $user = User::create([
            'name' => 'Hank Pym',
            'phone_number' => '+15553337788',
            'status' => UserStatus::ACTIVE,
            'mobile_verified_at' => now(),
        ]);

        $token = $user->createToken('test_session')->plainTextToken;

        // Verify authenticated me endpoint works
        $meResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'Hank Pym',
                        'mobile' => '+15553337788',
                    ],
                ],
            ]);

        // Call logout endpoint
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Token is deleted from personal_access_tokens table
        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Clear in-memory auth guard state in test runner
        $this->app['auth']->forgetGuards();

        // Subsequent access with revoked token is rejected with 401
        $subsequentMeResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $subsequentMeResponse->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    /**
     * Test 11: Unauthorized API access returns 401.
     */
    public function test_unauthorized_api_access_returns_401(): void
    {
        // Unauthenticated access to /api/v1/auth/me
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

        // Unauthenticated access to /api/v1/auth/logout
        $this->postJson('/api/v1/auth/logout')
            ->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthenticated.',
            ]);

        // Invalid bearer token
        $this->withHeader('Authorization', 'Bearer invalid_bogus_token_123')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
