<?php

namespace Tests\Feature;

use App\Enums\PlanDuration;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\ActivationPlan;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivationAndSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_active_activation_plans(): void
    {
        ActivationPlan::create([
            'name' => 'Yearly Fortress',
            'code' => 'YEARLY',
            'duration_days' => PlanDuration::YEARLY->days(),
            'price' => 79.99,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/plans');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'code' => 'YEARLY',
                'duration_days' => 365,
            ]);
    }

    public function test_user_can_redeem_activation_code_and_activate_account(): void
    {
        $plan = ActivationPlan::create([
            'name' => 'Monthly Shield',
            'code' => 'MONTHLY',
            'duration_days' => 30,
            'price' => 9.99,
            'is_active' => true,
        ]);

        $activationService = app(ActivationService::class);
        $batch = $activationService->generateBatch($plan, 1);
        $code = $batch->codes()->first();

        $user = User::create([
            'phone_number' => '+15553334444',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/activation/redeem', [
                'code' => $code->code,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_status' => UserStatus::ACTIVE->value,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'activation_plan_id' => $plan->id,
            'status' => SubscriptionStatus::ACTIVE->value,
        ]);

        $this->assertDatabaseHas('activation_codes', [
            'id' => $code->id,
            'is_redeemed' => true,
            'redeemed_by_user_id' => $user->id,
        ]);
    }

    public function test_cannot_redeem_already_redeemed_code(): void
    {
        $plan = ActivationPlan::create([
            'name' => 'Monthly Shield',
            'code' => 'MONTHLY',
            'duration_days' => 30,
            'price' => 9.99,
            'is_active' => true,
        ]);

        $activationService = app(ActivationService::class);
        $batch = $activationService->generateBatch($plan, 1);
        $code = $batch->codes()->first();

        $user1 = User::create([
            'phone_number' => '+15551110001',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);

        $user2 = User::create([
            'phone_number' => '+15551110002',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);

        $activationService->redeemCode($code->code, $user1);

        $token2 = $user2->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/v1/activation/redeem', [
                'code' => $code->code,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This activation code has already been redeemed.',
            ]);
    }
}
