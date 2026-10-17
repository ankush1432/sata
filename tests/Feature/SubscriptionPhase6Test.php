<?php

namespace Tests\Feature;

use App\Enums\DeviceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\ActivationCode;
use App\Models\ActivationPlan;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\ActivationService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SubscriptionPhase6Test extends TestCase
{
    use RefreshDatabase;

    protected ActivationService $activationService;
    protected SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activationService = app(ActivationService::class);
        $this->subscriptionService = app(SubscriptionService::class);
    }

    private function createUser(string $phone = '+15551001001'): User
    {
        return User::create([
            'phone_number' => $phone,
            'status' => UserStatus::AWAITING_ACTIVATION,
            'mobile_verified_at' => now(),
        ]);
    }

    private function createDevice(User $user, string $deviceId = 'device-test-01'): UserDevice
    {
        return UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => $deviceId,
            'device_name' => 'Pixel 9 Pro',
            'model' => 'Pixel 9 Pro',
            'platform' => 'android',
            'status' => DeviceStatus::ACTIVE,
        ]);
    }

    private function createPlanAndCode(int $days, string $codeName = 'TEST_PLAN'): array
    {
        $plan = ActivationPlan::create([
            'name' => "Plan {$days} Days",
            'code' => "{$codeName}_{$days}",
            'duration_days' => $days,
            'price' => 19.99,
            'is_active' => true,
        ]);

        $batch = $this->activationService->generateBatch($plan, 1);
        $code = $batch->codes()->first();

        return [$plan, $code];
    }

    public function test_activation_for_30_days_plan_calculates_exact_expiry(): void
    {
        $user = $this->createUser();
        $device = $this->createDevice($user);
        [$plan, $code] = $this->createPlanAndCode(30, 'PLAN_30');

        $before = now();
        $result = $this->activationService->activate($user, $code->code, $device->device_uuid);
        $after = now();

        /** @var Subscription $subscription */
        $subscription = $result['subscription'];

        $this->assertNotNull($subscription);
        $this->assertEquals($user->id, $subscription->user_id);
        $this->assertEquals($plan->id, $subscription->plan_id);
        $this->assertEquals($code->id, $subscription->activation_code_id);
        $this->assertEquals($device->id, $subscription->device_id);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertTrue($subscription->starts_at->greaterThanOrEqualTo($before->subSecond()));
        $this->assertTrue($subscription->starts_at->lessThanOrEqualTo($after->addSecond()));

        // Check exact 30 days addition
        $expectedExpiry = $subscription->starts_at->copy()->addDays(30);
        $this->assertEquals($expectedExpiry->timestamp, $subscription->expires_at->timestamp);
        $this->assertTrue($subscription->isActive());
        $this->assertEquals(30, $subscription->daysRemaining());
    }

    public function test_activation_for_90_days_plan_calculates_exact_expiry(): void
    {
        $user = $this->createUser('+15551001002');
        $device = $this->createDevice($user, 'device-90');
        [$plan, $code] = $this->createPlanAndCode(90, 'PLAN_90');

        $result = $this->activationService->activate($user, $code->code, $device->device_uuid);
        $subscription = $result['subscription'];

        $expectedExpiry = $subscription->starts_at->copy()->addDays(90);
        $this->assertEquals($expectedExpiry->timestamp, $subscription->expires_at->timestamp);
        $this->assertEquals(90, $subscription->daysRemaining());
    }

    public function test_activation_for_180_days_plan_calculates_exact_expiry(): void
    {
        $user = $this->createUser('+15551001003');
        $device = $this->createDevice($user, 'device-180');
        [$plan, $code] = $this->createPlanAndCode(180, 'PLAN_180');

        $result = $this->activationService->activate($user, $code->code, $device->device_uuid);
        $subscription = $result['subscription'];

        $expectedExpiry = $subscription->starts_at->copy()->addDays(180);
        $this->assertEquals($expectedExpiry->timestamp, $subscription->expires_at->timestamp);
        $this->assertEquals(180, $subscription->daysRemaining());
    }

    public function test_activation_for_365_days_plan_calculates_exact_expiry(): void
    {
        $user = $this->createUser('+15551001004');
        $device = $this->createDevice($user, 'device-365');
        [$plan, $code] = $this->createPlanAndCode(365, 'PLAN_365');

        $result = $this->activationService->activate($user, $code->code, $device->device_uuid);
        $subscription = $result['subscription'];

        $expectedExpiry = $subscription->starts_at->copy()->addDays(365);
        $this->assertEquals($expectedExpiry->timestamp, $subscription->expires_at->timestamp);
        $this->assertEquals(365, $subscription->daysRemaining());
    }

    public function test_time_travel_expiry_detection_and_status_transition(): void
    {
        // Freeze time at Day 0
        Carbon::setTestNow(Carbon::parse('2026-01-01 12:00:00'));

        $user = $this->createUser('+15551001005');
        $device = $this->createDevice($user, 'device-time-travel');
        [$plan, $code] = $this->createPlanAndCode(30, 'PLAN_TT');

        $result = $this->activationService->activate($user, $code->code, $device->device_uuid);
        $subscription = $result['subscription'];

        $this->assertEquals(SubscriptionStatus::ACTIVE, $subscription->status);
        $this->assertEquals(30, $subscription->daysRemaining());

        // Fast-forward to Day 25 (5 days left -> within 7 days expiring threshold)
        Carbon::setTestNow(Carbon::parse('2026-01-26 12:00:00'));

        // Run scheduler command
        $exitCode = Artisan::call('subscriptions:process-expiry', ['--days' => 7]);
        $this->assertEquals(0, $exitCode);

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::EXPIRING, $subscription->status);
        $this->assertTrue($subscription->isExpiring());
        $this->assertNotNull($subscription->expiring_reminder_sent_at);
        $this->assertEquals(5, $subscription->daysRemaining());

        // Verify reminder notification was generated
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'SUBSCRIPTION_EXPIRING',
        ]);

        // Idempotency check: Running command again does not duplicate notifications
        $notificationCountBefore = Notification::count();
        Artisan::call('subscriptions:process-expiry', ['--days' => 7]);
        $this->assertEquals($notificationCountBefore, Notification::count());

        // Fast-forward to Day 31 (1 day after expiration)
        Carbon::setTestNow(Carbon::parse('2026-02-01 12:00:00'));

        Artisan::call('subscriptions:process-expiry');

        $subscription->refresh();
        $this->assertEquals(SubscriptionStatus::EXPIRED, $subscription->status);
        $this->assertTrue($subscription->isExpired());
        $this->assertFalse($subscription->isActive());
        $this->assertEquals(0, $subscription->daysRemaining());
        $this->assertNotNull($subscription->expired_reminder_sent_at);

        // Verify expired notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'SUBSCRIPTION_EXPIRED',
        ]);

        // Reset test time
        Carbon::setTestNow();
    }

    public function test_renewal_creates_new_immutable_record_and_preserves_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-01-01 10:00:00'));

        $user = $this->createUser('+15551001006');
        $device = $this->createDevice($user, 'device-renewal');

        // First subscription (30 days)
        [$plan1, $code1] = $this->createPlanAndCode(30, 'RENEWAL_P1');
        $res1 = $this->activationService->activate($user, $code1->code, $device->device_uuid);
        $sub1 = $res1['subscription'];

        $this->assertEquals(SubscriptionStatus::ACTIVE, $sub1->status);
        $this->assertEquals(1, Subscription::where('user_id', $user->id)->count());

        // Time travel to Day 29
        Carbon::setTestNow(Carbon::parse('2026-01-30 10:00:00'));

        // Renewal with a 90 days plan
        [$plan2, $code2] = $this->createPlanAndCode(90, 'RENEWAL_P2');
        $res2 = $this->activationService->activate($user, $code2->code, $device->device_uuid);
        $sub2 = $res2['subscription'];

        // Subscriptions count must now be 2 - historical subscription is preserved, never deleted
        $this->assertEquals(2, Subscription::where('user_id', $user->id)->count());
        $this->assertNotEquals($sub1->id, $sub2->id);

        $sub1->refresh();
        $sub2->refresh();

        // Old subscription marked INACTIVE, new subscription is ACTIVE
        $this->assertEquals(SubscriptionStatus::INACTIVE, $sub1->status);
        $this->assertEquals(SubscriptionStatus::ACTIVE, $sub2->status);
        $this->assertEquals(90, $sub2->daysRemaining());

        Carbon::setTestNow();
    }

    public function test_disabled_subscription_state_and_helper(): void
    {
        $user = $this->createUser('+15551001007');
        $device = $this->createDevice($user, 'device-disabled');
        [$plan, $code] = $this->createPlanAndCode(30, 'PLAN_DIS');

        $result = $this->activationService->activate($user, $code->code, $device->device_uuid);
        $sub = $result['subscription'];

        $this->subscriptionService->disableSubscription($sub, 'Breach of terms of service');

        $sub->refresh();
        $this->assertEquals(SubscriptionStatus::DISABLED, $sub->status);
        $this->assertTrue($sub->isDisabled());
        $this->assertFalse($sub->isActive());
        $this->assertNotNull($sub->disabled_at);
        $this->assertEquals('Breach of terms of service', $sub->disable_reason);

        // Device protection must be turned off
        $device->refresh();
        $this->assertFalse((bool) $device->is_protection_enabled);
    }

    public function test_current_and_history_api_endpoints(): void
    {
        $user = $this->createUser('+15551001008');
        $device = $this->createDevice($user, 'device-api-test');

        $token = $user->createToken('test_token')->plainTextToken;

        // 1. Initial state: No subscription
        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/subscription/current');

        $resp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_subscription' => false,
                    'subscription' => null,
                ],
            ]);

        // 2. Activate subscription 1
        [$plan1, $code1] = $this->createPlanAndCode(30, 'API_P1');
        $this->activationService->activate($user, $code1->code, $device->device_uuid);

        $resp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/subscription/current');

        $resp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_subscription' => true,
                    'subscription' => [
                        'status' => 'ACTIVE',
                        'is_active' => true,
                        'days_remaining' => 30,
                    ],
                ],
            ]);

        // 3. Renew with subscription 2
        [$plan2, $code2] = $this->createPlanAndCode(90, 'API_P2');
        $this->activationService->activate($user, $code2->code, $device->device_uuid);

        // Current should now reflect the 90 day subscription
        $currentResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/subscription/current');

        $currentResp->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_active_subscription' => true,
                    'subscription' => [
                        'status' => 'ACTIVE',
                        'days_remaining' => 90,
                    ],
                ],
            ]);

        // 4. History endpoint must contain both subscriptions
        $historyResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/subscription/history');

        $historyResp->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $subscriptions = $historyResp->json('data.subscriptions');
        $this->assertCount(2, $subscriptions);
        $this->assertEquals(2, $historyResp->json('data.pagination.total'));
    }
}
