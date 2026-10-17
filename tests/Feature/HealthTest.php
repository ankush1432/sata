<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_success_and_healthy_services(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'SATA Backend API is healthy',
                'data' => [
                    'status' => 'healthy',
                    'services' => [
                        'database' => 'connected',
                        'storage' => 'accessible',
                        'cache' => 'operational',
                    ],
                    'version' => '1.0.0',
                ],
            ]);
    }
}
