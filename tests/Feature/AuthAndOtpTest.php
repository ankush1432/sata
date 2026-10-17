<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndOtpTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_check_returns_online_status(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'ONLINE',
                ],
            ]);
    }

    public function test_send_otp_generates_verification_record(): void
    {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone_number' => '+15551234567',
            'purpose' => 'LOGIN',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'OTP sent successfully.',
            ]);

        $this->assertDatabaseHas('otp_verifications', [
            'phone_number' => '+15551234567',
            'is_verified' => false,
        ]);
    }

    public function test_verify_otp_authenticates_and_creates_user(): void
    {
        $sendResponse = $this->postJson('/api/v1/auth/send-otp', [
            'phone_number' => '+15559876543',
            'purpose' => 'LOGIN',
        ]);

        $otp = $sendResponse->json('data.dev_otp');

        $verifyResponse = $this->postJson('/api/v1/auth/verify-otp', [
            'phone_number' => '+15559876543',
            'otp' => $otp,
            'purpose' => 'LOGIN',
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'user' => [
                        'phone_number' => '+15559876543',
                        'status' => UserStatus::OTP_VERIFIED->value,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone_number' => '+15559876543',
            'status' => UserStatus::OTP_VERIFIED->value,
        ]);
    }

    public function test_user_can_access_profile_with_bearer_token(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'phone_number' => '+15551112233',
            'status' => UserStatus::ACTIVE,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'phone_number' => '+15551112233',
                        'name' => 'John Doe',
                    ],
                ],
            ]);
    }
}
