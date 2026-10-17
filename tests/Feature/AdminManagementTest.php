<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\PlanDuration;
use App\Models\ActivationPlan;
use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_receive_token(): void
    {
        $admin = Admin::create([
            'name' => 'Test Admin',
            'email' => 'admin@sata.security',
            'password' => Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@sata.security',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                    'admin' => [
                        'email' => 'admin@sata.security',
                        'role' => AdminRole::SUPER_ADMIN->value,
                    ],
                ],
            ]);
    }

    public function test_admin_can_generate_batch_of_activation_codes(): void
    {
        $admin = Admin::create([
            'name' => 'Batch Creator Admin',
            'email' => 'creator@sata.security',
            'password' => Hash::make('secret'),
            'role' => AdminRole::ADMIN,
            'is_active' => true,
        ]);

        $plan = ActivationPlan::create([
            'name' => 'Quarterly Sentinel',
            'code' => 'QUARTERLY',
            'duration_days' => PlanDuration::QUARTERLY->days(),
            'price' => 24.99,
            'is_active' => true,
        ]);

        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/admin/batches', [
                'activation_plan_id' => $plan->id,
                'quantity' => 10,
                'notes' => 'Q1 Partner Distribution',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'batch' => [
                        'quantity' => 10,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('activation_code_batches', [
            'activation_plan_id' => $plan->id,
            'quantity' => 10,
        ]);

        $this->assertDatabaseCount('activation_codes', 10);

        // Verify audit log entry was created
        $this->assertDatabaseHas('admin_audit_logs', [
            'admin_id' => $admin->id,
            'action' => 'BATCH_GENERATED',
            'module' => 'ACTIVATION',
        ]);
    }
}
