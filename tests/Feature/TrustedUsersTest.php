<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustedUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_up_to_two_trusted_users(): void
    {
        $user = User::create([
            'name' => 'Main User',
            'phone_number' => '+15551239999',
            'status' => UserStatus::ACTIVE,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        // Add 1st trusted user
        $firstResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Trusted Contact 1',
                'phone_number' => '+15551112222',
                'relationship' => 'Spouse',
            ]);

        $firstResp->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'trusted_user' => [
                        'name' => 'Trusted Contact 1',
                        'phone_number' => '+15551112222',
                    ],
                ],
            ]);

        // Add 2nd trusted user
        $secondResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Trusted Contact 2',
                'phone_number' => '+15553334444',
                'relationship' => 'Sibling',
            ]);

        $secondResp->assertStatus(201);

        $this->assertCount(2, $user->trustedUsers()->get());

        // Attempt to add 3rd trusted user -> MUST FAIL (Max 2 rule)
        $thirdResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/trusted-users', [
                'name' => 'Trusted Contact 3',
                'phone_number' => '+15555556666',
                'relationship' => 'Parent',
            ]);

        $thirdResp->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);

        // Still only 2 in DB
        $this->assertCount(2, $user->trustedUsers()->get());
    }

    public function test_user_can_remove_trusted_user(): void
    {
        $user = User::create([
            'phone_number' => '+15554445555',
            'status' => UserStatus::ACTIVE,
        ]);

        $trusted = $user->trustedUsers()->create([
            'name' => 'To Delete',
            'phone_number' => '+15550001111',
            'priority' => 1,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->deleteJson('/api/v1/trusted-users/' . $trusted->id);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('trusted_users', [
            'id' => $trusted->id,
        ]);
    }
}
