<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\PlanDuration;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\Subscription;
use App\Models\User;
use App\Services\ActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ActivationPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $superAdmin;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = Admin::create([
            'name' => 'SATA Super Admin',
            'email' => 'admin@sata.security',
            'password' => Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->adminToken = $this->superAdmin->createToken('admin_test_token')->plainTextToken;
    }

    protected function adminHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Accept' => 'application/json',
        ];
    }

    /**
     * Test 1: CRUD Operations
     * Tests List, Create, View, Update, Enable, Disable, and Delete of Activation Plans.
     */
    public function test_admin_can_perform_complete_crud_on_activation_plans(): void
    {
        // 1. Create - Standard Plan (Backend owns duration: 30 for MONTHLY)
        $createResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Monthly Shield Tier',
                'code' => 'MONTHLY',
                'duration_days' => 30,
                'price' => 9.99,
                'currency' => 'USD',
                'description' => '30-day baseline anti-theft defense.',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'name' => 'Monthly Shield Tier',
                        'code' => 'MONTHLY',
                        'duration_days' => 30,
                        'price' => '9.99',
                        'currency' => 'USD',
                        'status' => 'ACTIVE',
                        'is_active' => true,
                    ],
                ],
            ]);

        $planId = $createResponse->json('data.plan.id');
        $this->assertNotNull($planId);
        $this->assertDatabaseHas('activation_plans', [
            'id' => $planId,
            'code' => 'MONTHLY',
            'status' => 'ACTIVE',
            'duration_days' => 30,
        ]);

        // 2. List
        $listResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/plans');

        $listResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonFragment([
                'id' => $planId,
                'code' => 'MONTHLY',
            ]);

        // 3. View
        $viewResponse = $this->withHeaders($this->adminHeaders())
            ->getJson('/api/v1/admin/plans/' . $planId);

        $viewResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'id' => $planId,
                        'code' => 'MONTHLY',
                        'name' => 'Monthly Shield Tier',
                        'duration_days' => 30,
                        'status' => 'ACTIVE',
                    ],
                ],
            ]);

        // 4. Update
        $updateResponse = $this->withHeaders($this->adminHeaders())
            ->putJson('/api/v1/admin/plans/' . $planId, [
                'name' => 'Monthly Shield Tier Enhanced',
                'price' => 11.99,
                'description' => 'Updated 30-day defense description.',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'name' => 'Monthly Shield Tier Enhanced',
                        'price' => '11.99',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('activation_plans', [
            'id' => $planId,
            'name' => 'Monthly Shield Tier Enhanced',
            'price' => 11.99,
        ]);

        // 5. Disable
        $disableResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans/' . $planId . '/disable');

        $disableResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'status' => 'INACTIVE',
                        'is_active' => false,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('activation_plans', [
            'id' => $planId,
            'status' => 'INACTIVE',
            'is_active' => false,
        ]);

        // 6. Enable
        $enableResponse = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans/' . $planId . '/enable');

        $enableResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'status' => 'ACTIVE',
                        'is_active' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('activation_plans', [
            'id' => $planId,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // 7. Delete (allowed because plan has no historical subscriptions)
        $deleteResponse = $this->withHeaders($this->adminHeaders())
            ->deleteJson('/api/v1/admin/plans/' . $planId);

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Activation plan deleted successfully.',
            ]);

        $this->assertDatabaseMissing('activation_plans', [
            'id' => $planId,
        ]);
    }

    /**
     * Test 2: Duplicate Code Validation
     */
    public function test_cannot_create_or_update_plan_with_duplicate_code(): void
    {
        // Seed first plan
        ActivationPlan::create([
            'name' => 'Quarterly Sentinel',
            'code' => 'QUARTERLY',
            'duration_days' => 90,
            'price' => 24.99,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Attempt to create duplicate plan with same code QUARTERLY
        $response = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Another Quarterly',
                'code' => 'QUARTERLY',
                'duration_days' => 90,
                'price' => 29.99,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonValidationErrors(['code']);

        // Create second distinct plan
        $plan2 = ActivationPlan::create([
            'name' => 'Yearly Fortress',
            'code' => 'YEARLY',
            'duration_days' => 365,
            'price' => 79.99,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Attempt to update second plan's code to already existing code QUARTERLY
        $updateResponse = $this->withHeaders($this->adminHeaders())
            ->putJson('/api/v1/admin/plans/' . $plan2->id, [
                'code' => 'QUARTERLY',
            ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    /**
     * Test 3: Invalid Duration & Backend Ownership
     */
    public function test_rejects_invalid_duration_and_enforces_backend_duration_ownership(): void
    {
        // 1. Duration <= 0 (e.g. 0)
        $respZero = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Zero Duration Plan',
                'code' => 'ZERO_PLAN',
                'duration_days' => 0,
                'price' => 5.00,
            ]);

        $respZero->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // 2. Negative duration (e.g. -30)
        $respNegative = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Negative Duration Plan',
                'code' => 'NEG_PLAN',
                'duration_days' => -30,
                'price' => 5.00,
            ]);

        $respNegative->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // 3. Non-integer duration (e.g. "invalid_str")
        $respInvalid = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'String Duration Plan',
                'code' => 'STR_PLAN',
                'duration_days' => 'invalid_str',
                'price' => 5.00,
            ]);

        $respInvalid->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // 4. Standard plan with mismatching duration (e.g. MONTHLY should be 30, client attempts 45)
        $respMismatch = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Tampered Monthly',
                'code' => 'MONTHLY',
                'duration_days' => 45, // Invalid for MONTHLY (backend owns 30)
                'price' => 9.99,
            ]);

        $respMismatch->assertStatus(422)
            ->assertJsonFragment(['success' => false]);

        // 5. Backend owns duration when standard code is used without duration_days
        $respAuto = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans', [
                'name' => 'Auto Half Yearly',
                'code' => 'HALF_YEARLY',
                'price' => 44.99,
            ]);

        $respAuto->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'code' => 'HALF_YEARLY',
                        'duration_days' => 180, // Auto-populated by backend ownership rule
                    ],
                ],
            ]);
    }

    /**
     * Test 4: Disable Plan Functionality
     */
    public function test_disable_plan_hides_it_from_public_api_and_can_be_reenabled(): void
    {
        $plan = ActivationPlan::create([
            'name' => 'Standard Yearly',
            'code' => 'YEARLY',
            'duration_days' => 365,
            'price' => 79.99,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        // Initially visible in public API
        $publicResp1 = $this->getJson('/api/v1/activation/plans');
        $publicResp1->assertStatus(200)
            ->assertJsonFragment(['code' => 'YEARLY']);

        // Disable the plan via admin endpoint
        $disableResp = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans/' . $plan->id . '/disable');

        $disableResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'status' => 'INACTIVE',
                        'is_active' => false,
                    ],
                ],
            ]);

        // Now hidden from public API
        $publicResp2 = $this->getJson('/api/v1/activation/plans');
        $publicResp2->assertStatus(200);
        $plansInApi = $publicResp2->json('data.plans');
        $codes = array_column($plansInApi, 'code');
        $this->assertNotContains('YEARLY', $codes);

        // Re-enable the plan via admin endpoint
        $enableResp = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans/' . $plan->id . '/enable');

        $enableResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'status' => 'ACTIVE',
                        'is_active' => true,
                    ],
                ],
            ]);

        // Visible again in public API
        $publicResp3 = $this->getJson('/api/v1/activation/plans');
        $publicResp3->assertStatus(200)
            ->assertJsonFragment(['code' => 'YEARLY']);
    }

    /**
     * Test 5: Historical Subscription Protection & Validity
     * - Cannot delete a plan with historical subscriptions
     * - Historical subscription records remain valid even if a plan is disabled
     */
    public function test_historical_subscription_protection_and_validity(): void
    {
        $plan = ActivationPlan::create([
            'name' => 'Quarterly Aegis',
            'code' => 'QUARTERLY',
            'duration_days' => 90,
            'price' => 24.99,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $user = User::create([
            'phone_number' => '+15557778888',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);

        $activationService = app(ActivationService::class);
        $batch = $activationService->generateBatch($plan, 1);
        $code = $batch->codes()->first();

        // Redeem code to create historical subscription
        $subscription = $activationService->redeemCode($code->code, $user);

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->isActive());
        $this->assertEquals($plan->id, $subscription->activation_plan_id);

        // Rule 1: Attempting to delete a plan with historical subscriptions must be rejected (422)
        $deleteResp = $this->withHeaders($this->adminHeaders())
            ->deleteJson('/api/v1/admin/plans/' . $plan->id);

        $deleteResp->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonFragment([
                'message' => 'Cannot delete activation plan with historical subscriptions. You can disable the plan instead.',
            ]);

        // Verify plan was NOT deleted
        $this->assertDatabaseHas('activation_plans', [
            'id' => $plan->id,
            'code' => 'QUARTERLY',
        ]);

        // Rule 2: Disable the plan
        $disableResp = $this->withHeaders($this->adminHeaders())
            ->postJson('/api/v1/admin/plans/' . $plan->id . '/disable');

        $disableResp->assertStatus(200);
        $this->assertFalse($plan->fresh()->is_active);

        // Verify subscription remains valid even though plan is disabled!
        $freshSubscription = $subscription->fresh();
        $this->assertEquals(SubscriptionStatus::ACTIVE, $freshSubscription->status);
        $this->assertTrue($freshSubscription->isActive());
        $this->assertNotNull($freshSubscription->plan);
        $this->assertEquals('QUARTERLY', $freshSubscription->plan->code);

        // Verify user can check their subscription status via API and it remains ACTIVE
        $userToken = $user->createToken('user_token')->plainTextToken;
        $statusResp = $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->getJson('/api/v1/activation/status');

        $statusResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user_status' => UserStatus::ACTIVE->value,
                    'is_active' => true,
                ],
            ]);
    }

    /**
     * Test 6: API Output & Standards
     * GET /api/v1/activation/plans
     * Fields: name, code, duration_days, price, currency, status, timestamps
     */
    public function test_api_output_schema_and_standard_durations(): void
    {
        // Seed all four standard plans
        $standardPlans = [
            ['name' => 'Monthly Plan', 'code' => 'MONTHLY', 'duration_days' => 30, 'price' => 9.99, 'currency' => 'USD'],
            ['name' => 'Quarterly Plan', 'code' => 'QUARTERLY', 'duration_days' => 90, 'price' => 24.99, 'currency' => 'USD'],
            ['name' => 'Half Yearly Plan', 'code' => 'HALF_YEARLY', 'duration_days' => 180, 'price' => 44.99, 'currency' => 'USD'],
            ['name' => 'Yearly Plan', 'code' => 'YEARLY', 'duration_days' => 365, 'price' => 79.99, 'currency' => 'USD'],
        ];

        foreach ($standardPlans as $p) {
            ActivationPlan::create(array_merge($p, ['status' => 'ACTIVE', 'is_active' => true]));
        }

        // Call the requested endpoint: GET /api/v1/activation/plans
        $response = $this->getJson('/api/v1/activation/plans');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Activation plans retrieved successfully.',
            ]);

        $plans = $response->json('data.plans');
        $this->assertCount(4, $plans);

        $planMap = [];
        foreach ($plans as $item) {
            // Verify all required fields are present
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('code', $item);
            $this->assertArrayHasKey('duration_days', $item);
            $this->assertArrayHasKey('price', $item);
            $this->assertArrayHasKey('currency', $item);
            $this->assertArrayHasKey('status', $item);
            $this->assertArrayHasKey('created_at', $item);
            $this->assertArrayHasKey('updated_at', $item);

            // Verify status is ACTIVE
            $this->assertEquals('ACTIVE', $item['status']);

            $planMap[$item['code']] = $item['duration_days'];
        }

        // Verify standard duration days strictly match requirements
        $this->assertEquals(30, $planMap['MONTHLY']);
        $this->assertEquals(90, $planMap['QUARTERLY']);
        $this->assertEquals(180, $planMap['HALF_YEARLY']);
        $this->assertEquals(365, $planMap['YEARLY']);
    }

    /**
     * Test 7: Flutter Client Must Never Send Trusted Duration
     */
    public function test_flutter_client_cannot_tamper_with_duration_on_redemption(): void
    {
        $plan = ActivationPlan::create([
            'name' => 'Monthly Shield',
            'code' => 'MONTHLY',
            'duration_days' => 30, // Backend owns 30 days
            'price' => 9.99,
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $activationService = app(ActivationService::class);
        $batch = $activationService->generateBatch($plan, 1);
        $code = $batch->codes()->first();

        $user = User::create([
            'phone_number' => '+15559990000',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);
        $token = $user->createToken('flutter_client')->plainTextToken;

        // Attacker attempts to send spoofed duration_days = 9999 or days = 9999 in payload
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/activation/redeem', [
                'code' => $code->code,
                'duration_days' => 9999,
                'days' => 9999,
                'expires_at' => now()->addYears(10)->toIso8601String(),
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $subscription = Subscription::where('user_id', $user->id)->first();
        $this->assertNotNull($subscription);

        // Subscription duration MUST equal 30 days (backend-owned), ignoring the 9999 injected payload
        $diffInDays = round($subscription->starts_at->diffInDays($subscription->expires_at));
        $this->assertEquals(30, $diffInDays);
    }
}
