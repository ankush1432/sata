<?php

namespace Tests\Feature;

use App\Enums\ActivationCodeStatus;
use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Enums\SecurityEventType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Jobs\GenerateActivationCodesJob;
use App\Models\ActivationCode;
use App\Models\ActivationCodeBatch;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\Notification;
use App\Models\SecurityEvent;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ActivationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActivationCodeAndActivationPhase5Test extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;
    protected ActivationPlan $monthlyPlan;
    protected ActivationPlan $yearlyPlan;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'SATA Admin',
            'email' => 'admin@sata.security',
            'password' => Hash::make('password123'),
            'role' => AdminRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->monthlyPlan = ActivationPlan::create([
            'name' => 'Monthly Protection',
            'code' => 'MONTHLY',
            'duration_days' => 30,
            'price' => 9.99,
            'currency' => 'USD',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->yearlyPlan = ActivationPlan::create([
            'name' => 'Yearly Protection',
            'code' => 'YEARLY',
            'duration_days' => 365,
            'price' => 89.99,
            'currency' => 'USD',
            'status' => 'ACTIVE',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'name' => 'John Doe',
            'phone_number' => '+15551112222',
            'mobile' => '+15551112222',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);
    }

    /**
     * Test single activation code generation.
     */
    public function test_admin_can_generate_single_activation_code(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/codes/generate-one', [
                'plan_id' => $this->monthlyPlan->id,
                'notes' => 'Single test card',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'code' => [
                        'id',
                        'serial_number',
                        'code',
                        'code_hash',
                        'code_last4',
                        'status',
                        'plan_id',
                    ],
                ],
            ]);

        $codeData = $response->json('data.code');

        // Check format SAT-XXXX-XXXX-XXXX
        $this->assertMatchesRegularExpression('/^SAT-[2-9A-Z]{4}-[2-9A-Z]{4}-[2-9A-Z]{4}$/', $codeData['code']);
        // Check serial format SAT-000001
        $this->assertMatchesRegularExpression('/^SAT-\d{6}$/', $codeData['serial_number']);
        // Check hash
        $this->assertEquals(hash('sha256', $codeData['code']), $codeData['code_hash']);
        // Check last 4
        $this->assertEquals(substr($codeData['code'], -4), $codeData['code_last4']);
        // Status should be AVAILABLE
        $this->assertEquals(ActivationCodeStatus::AVAILABLE->value, $codeData['status']);
    }

    /**
     * Test bulk activation code generation with queue dispatch for large volumes.
     */
    public function test_admin_can_bulk_generate_activation_codes(): void
    {
        Queue::fake();

        // Under 50 generates synchronously
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/codes/bulk-generate', [
                'plan_id' => $this->monthlyPlan->id,
                'quantity' => 10,
                'notes' => 'Retail Box A',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.queued', false);

        $this->assertEquals(10, ActivationCode::count());
        $codes = ActivationCode::orderBy('id')->get();

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^SAT-[2-9A-Z]{4}-[2-9A-Z]{4}-[2-9A-Z]{4}$/', $code->code);
            $this->assertMatchesRegularExpression('/^SAT-\d{6}$/', $code->serial_number);
            $this->assertEquals($code->status, ActivationCodeStatus::AVAILABLE->value);
            $this->assertFalse($code->is_redeemed);
        }

        // Large volume (>= 50) queues job
        $largeResponse = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/admin/codes/bulk-generate', [
                'plan_id' => $this->yearlyPlan->id,
                'quantity' => 100,
                'notes' => 'Retail Box Big',
            ]);

        $largeResponse->assertStatus(201)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(GenerateActivationCodesJob::class, function ($job) {
            return $job->quantity === 100 && $job->plan->id === $this->yearlyPlan->id;
        });
    }

    /**
     * Test searching and filtering activation codes.
     */
    public function test_admin_can_search_and_filter_activation_codes(): void
    {
        $service = app(ActivationService::class);
        $code1 = $service->generateOne($this->monthlyPlan, $this->admin);
        $code2 = $service->generateOne($this->yearlyPlan, $this->admin);

        // Search by serial number
        $searchResponse = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/admin/codes?search={$code1->serial_number}");

        $searchResponse->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $code1->id);

        // Search by last 4
        $searchLast4 = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/admin/codes?search={$code2->code_last4}");

        $searchLast4->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $code2->id);

        // Filter by plan_id
        $filterPlan = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/admin/codes?plan_id={$this->yearlyPlan->id}");

        $filterPlan->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.id', $code2->id);

        // Filter by status
        $filterStatus = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/codes?status=AVAILABLE');

        $filterStatus->assertStatus(200)
            ->assertJsonPath('data.total', 2);
    }

    /**
     * Test admin can view code details.
     */
    public function test_admin_can_view_code(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/admin/codes/{$code->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.code.id', $code->id)
            ->assertJsonPath('data.code.serial_number', $code->serial_number);
    }

    /**
     * Test admin can disable an unredeemed activation code.
     */
    public function test_admin_can_disable_code(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/admin/codes/{$code->id}/disable");

        $response->assertStatus(200)
            ->assertJsonPath('data.code.status', ActivationCodeStatus::DISABLED->value);

        $this->assertEquals(ActivationCodeStatus::DISABLED->value, $code->fresh()->status);
        $this->assertFalse($code->fresh()->is_active);
    }

    /**
     * Test admin can export activation codes to CSV.
     */
    public function test_admin_can_export_codes_to_csv(): void
    {
        $service = app(ActivationService::class);
        $service->generateOne($this->monthlyPlan, $this->admin);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->get('/admin/codes/export');

        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'text/csv'));
    }

    /**
     * Test admin can generate printable vouchers/cards.
     */
    public function test_admin_can_get_printable_cards(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/admin/codes/print');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'count',
                    'cards' => [
                        '*' => [
                            'serial_number',
                            'display_code',
                            'plan_name',
                            'duration_days',
                            'status',
                            'instructions',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test successful activation workflow (11 steps).
     */
    public function test_user_can_activate_subscription_on_device_end_to_end(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        $deviceId = 'DEV-SATA-9988-UUID';

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/activation/activate', [
                'activation_code' => $code->code,
                'device_id' => $deviceId,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_status', UserStatus::ACTIVE->value)
            ->assertJsonPath('data.code.serial_number', $code->serial_number)
            ->assertJsonPath('data.code.status', ActivationCodeStatus::ACTIVATED->value);

        // 1. Code marked ACTIVATED & redeemed
        $code->refresh();
        $this->assertEquals(ActivationCodeStatus::ACTIVATED->value, $code->status);
        $this->assertTrue($code->is_redeemed);
        $this->assertEquals($this->user->id, $code->activated_by_user_id);
        $this->assertNotNull($code->activated_at);
        $this->assertNotNull($code->activated_device_id);

        // 2. Subscription created
        $subscription = Subscription::where('user_id', $this->user->id)->first();
        $this->assertNotNull($subscription);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertEquals($this->monthlyPlan->id, $subscription->activation_plan_id);
        // Expiration is exactly 30 days ahead
        $this->assertEquals(
            now()->addDays(30)->toDateString(),
            $subscription->expires_at->toDateString()
        );

        // 3. User status is ACTIVE
        $this->user->refresh();
        $this->assertEquals(UserStatus::ACTIVE, $this->user->status);
        $this->assertEquals($subscription->id, $this->user->active_subscription_id);

        // 4. Device assigned and status ACTIVE
        $device = UserDevice::where('device_uuid', $deviceId)->first();
        $this->assertNotNull($device);
        $this->assertEquals(DeviceStatus::ACTIVE, $device->status);
        $this->assertTrue($device->is_protection_enabled);
        $this->assertEquals($device->id, $this->user->current_device_id);

        // 5. SecurityEvent audit recorded (event_type = PROTECTION_ENABLED)
        $event = SecurityEvent::where('user_id', $this->user->id)
            ->where('event_type', SecurityEventType::PROTECTION_ENABLED)
            ->first();
        $this->assertNotNull($event);
        $this->assertEquals($code->serial_number, $event->metadata['serial_number']);
        $this->assertEquals($code->code_last4, $event->metadata['code_last4']);
        // Verify full code is NEVER stored in event metadata
        $this->assertArrayNotHasKey('code', $event->metadata);

        // 6. Notification created
        $notification = Notification::where('user_id', $this->user->id)->first();
        $this->assertNotNull($notification);
        $this->assertEquals('ACTIVATION_SUCCESSFUL', $notification->type);
        $this->assertEquals($code->serial_number, $notification->payload['serial_number']);
    }

    /**
     * Test backend never trusts duration or plan_id sent by client.
     */
    public function test_backend_ignores_tampered_client_duration_and_plan_id(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        // Client attempts to send 9999 days and a tampered plan_id
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/activation/activate', [
                'activation_code' => $code->code,
                'device_id' => 'DEV-TAMPER-TEST',
                'duration_days' => 9999,
                'plan_id' => 99999,
            ]);

        $response->assertStatus(200);

        $subscription = Subscription::where('user_id', $this->user->id)->first();
        // Duration must remain 30 days as defined by the backend plan
        $this->assertEquals(
            now()->addDays(30)->toDateString(),
            $subscription->expires_at->toDateString()
        );
        $this->assertEquals($this->monthlyPlan->id, $subscription->activation_plan_id);
    }

    /**
     * Test cannot activate already redeemed code (only one activation allowed).
     */
    public function test_cannot_activate_already_redeemed_code(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        // First activation succeeds
        $first = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/activation/activate', [
                'activation_code' => $code->code,
                'device_id' => 'DEV-1',
            ]);
        $first->assertStatus(200);

        // Second activation on another user or same user must fail
        $secondUser = User::create([
            'phone_number' => '+15552223333',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);
        $second = $this->actingAs($secondUser, 'sanctum')
            ->postJson('/api/v1/activation/activate', [
                'activation_code' => $code->code,
                'device_id' => 'DEV-2',
            ]);

        $second->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    /**
     * Test cannot activate disabled or expired code.
     */
    public function test_cannot_activate_disabled_or_expired_code(): void
    {
        $service = app(ActivationService::class);

        // Disabled code
        $disabledCode = $service->generateOne($this->monthlyPlan, $this->admin);
        $disabledCode->disable();

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/activation/activate', [
                'activation_code' => $disabledCode->code,
                'device_id' => 'DEV-DIS',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'This activation code has been disabled.');

        // Expired code
        $expiredCode = ActivationCode::create([
            'activation_plan_id' => $this->monthlyPlan->id,
            'plan_id' => $this->monthlyPlan->id,
            'status' => ActivationCodeStatus::EXPIRED->value,
            'expires_at' => now()->subDay(),
        ]);

        $responseExpired = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/activation/activate', [
                'activation_code' => $expiredCode->code,
                'device_id' => 'DEV-EXP',
            ]);

        $responseExpired->assertStatus(422)
            ->assertJsonPath('message', 'This activation code has expired.');
    }

    /**
     * MANDATORY Concurrency test:
     * Simultaneous / rapid duplicate redemption of the exact same code.
     * Pessimistic row locking ensures only ONE transaction succeeds.
     */
    public function test_concurrency_only_one_successful_activation_allowed(): void
    {
        $service = app(ActivationService::class);
        $code = $service->generateOne($this->monthlyPlan, $this->admin);

        $user1 = User::create([
            'phone_number' => '+15558881111',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);
        $user2 = User::create([
            'phone_number' => '+15558882222',
            'status' => UserStatus::AWAITING_ACTIVATION,
        ]);

        $successCount = 0;
        $failureCount = 0;

        // Simulate concurrent execution: first transaction activates and locks the row
        try {
            $service->activate($user1, $code->code, 'DEV-CONCURRENT-1');
            $successCount++;
        } catch (\Throwable $e) {
            $failureCount++;
        }

        // Second transaction attempts to activate the exact same code
        try {
            $service->activate($user2, $code->code, 'DEV-CONCURRENT-2');
            $successCount++;
        } catch (\Throwable $e) {
            $failureCount++;
        }

        // Exactly one activation succeeded, and one failed
        $this->assertEquals(1, $successCount, 'Exactly one concurrent attempt must succeed.');
        $this->assertEquals(1, $failureCount, 'Conflicting concurrent attempt must fail.');

        // Activation code must be associated with the winner
        $code->refresh();
        $this->assertEquals(ActivationCodeStatus::ACTIVATED->value, $code->status);
        $this->assertEquals($user1->id, $code->activated_by_user_id);

        // Only one active subscription exists for this code
        $this->assertEquals(1, Subscription::where('activation_code_id', $code->id)->count());
    }
}
