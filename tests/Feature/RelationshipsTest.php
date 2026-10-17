<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Enums\DeviceStatus;
use App\Enums\PlanDuration;
use App\Enums\SecurityEventType;
use App\Enums\SubscriptionStatus;
use App\Enums\UserStatus;
use App\Models\ActivationCode;
use App\Models\ActivationCodeBatch;
use App\Models\ActivationPlan;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\LiveLocationSession;
use App\Models\Notification;
use App\Models\OtpVerification;
use App\Models\SecurityCommand;
use App\Models\SecurityCommandLog;
use App\Models\SecurityEvent;
use App\Models\SecurityEventLocation;
use App\Models\SecurityEvidence;
use App\Models\Subscription;
use App\Models\TrustedUser;
use App\Models\TrustedUserVerification;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_18_models_and_relationships_are_wired_properly(): void
    {
        // 1. Admin
        $admin = Admin::create([
            'name' => 'Root Admin',
            'email' => 'root@sata.security',
            'password' => 'secret',
            'role' => AdminRole::SUPER_ADMIN,
        ]);

        // 2. Admin Audit Log
        $auditLog = AdminAuditLog::create([
            'admin_id' => $admin->id,
            'action' => 'TEST_ACTION',
            'target_type' => Admin::class,
            'target_id' => $admin->id,
        ]);
        $this->assertEquals($admin->id, $auditLog->admin->id);
        $this->assertTrue($admin->auditLogs->contains($auditLog));

        // 3. User
        $user = User::create([
            'name' => 'Alice Test',
            'phone' => '+15556667777',
            'status' => UserStatus::ACTIVE,
        ]);

        // 4. OTP Verification
        $otp = OtpVerification::create([
            'user_id' => $user->id,
            'phone' => $user->phone,
            'otp_code' => '123456',
            'expires_at' => now()->addMinutes(10),
        ]);
        $this->assertEquals($user->id, $otp->user->id);
        $this->assertTrue($user->otpVerifications->contains($otp));

        // 5. Activation Plan
        $plan = ActivationPlan::create([
            'name' => 'Yearly Test Plan',
            'code' => 'PLAN-REL-YEAR',
            'duration_type' => PlanDuration::YEARLY,
            'duration_days' => 365,
            'price' => 49.99,
        ]);

        // 6. Activation Code Batch
        $batch = ActivationCodeBatch::create([
            'batch_number' => 'BATCH-REL-001',
            'plan_id' => $plan->id,
            'quantity' => 1,
            'created_by_admin_id' => $admin->id,
        ]);
        $this->assertEquals($plan->id, $batch->plan->id);
        $this->assertEquals($admin->id, $batch->createdByAdmin->id);

        // 7. Activation Code
        $code = ActivationCode::create([
            'batch_id' => $batch->id,
            'plan_id' => $plan->id,
            'code' => 'SATA-REL-CODE-001',
            'status' => 'UNUSED',
        ]);
        $this->assertEquals($batch->id, $code->batch->id);
        $this->assertEquals($plan->id, $code->plan->id);

        // 8. Subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'activation_code_id' => $code->id,
            'status' => SubscriptionStatus::ACTIVE,
            'starts_at' => now(),
            'expires_at' => now()->addDays(365),
        ]);
        $this->assertEquals($user->id, $subscription->user->id);
        $this->assertEquals($plan->id, $subscription->plan->id);
        $this->assertEquals($code->id, $subscription->activationCode->id);

        $user->update(['current_subscription_id' => $subscription->id]);
        $this->assertEquals($subscription->id, $user->fresh()->currentSubscription->id);

        // 9. User Device
        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'DEV-UUID-001',
            'os_type' => 'ANDROID',
            'status' => DeviceStatus::ACTIVE,
        ]);
        $this->assertEquals($user->id, $device->user->id);
        $this->assertTrue($user->devices->contains($device));
        $this->assertEquals($device->id, $user->activeDevice->id);

        // 10. Trusted User
        $trustedUser = TrustedUser::create([
            'user_id' => $user->id,
            'name' => 'Trusted Contact',
            'phone' => '+15559998888',
        ]);
        $this->assertEquals($user->id, $trustedUser->user->id);

        // 11. Trusted User Verification
        $tuVerification = TrustedUserVerification::create([
            'trusted_user_id' => $trustedUser->id,
            'verification_code' => '998877',
            'expires_at' => now()->addHour(),
        ]);
        $this->assertEquals($trustedUser->id, $tuVerification->trustedUser->id);

        // 12. Security Event
        $event = SecurityEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);
        $this->assertEquals($user->id, $event->user->id);
        $this->assertEquals($device->id, $event->device->id);

        // 13. Security Event Location
        $location = SecurityEventLocation::create([
            'security_event_id' => $event->id,
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'captured_at' => now(),
        ]);
        $this->assertEquals($event->id, $location->securityEvent->id);
        $this->assertEquals($location->id, $event->fresh()->location->id);

        // 14. Security Evidence
        $evidence = SecurityEvidence::create([
            'evidence_uuid' => (string) Str::uuid(),
            'security_event_id' => $event->id,
            'user_id' => $user->id,
            'device_id' => $device->id,
            'storage_path' => 'events/sample.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'captured_at' => now(),
        ]);
        $this->assertEquals($event->id, $evidence->securityEvent->id);
        $this->assertTrue($event->evidences->contains($evidence));

        // 15. Notification
        $notification = Notification::create([
            'notification_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_id' => $device->id,
            'title' => 'Intruder Alert',
            'body' => 'Front camera triggered.',
        ]);
        $this->assertEquals($user->id, $notification->user->id);
        $this->assertEquals($device->id, $notification->device->id);

        // 16. Security Command
        $cmd = SecurityCommand::create([
            'command_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_id' => $device->id,
            'requested_by_admin_id' => $admin->id,
        ]);
        $this->assertEquals($user->id, $cmd->user->id);
        $this->assertEquals($device->id, $cmd->device->id);
        $this->assertEquals($admin->id, $cmd->requestedByAdmin->id);

        // 17. Security Command Log
        $cmdLog = SecurityCommandLog::create([
            'security_command_id' => $cmd->id,
            'status_from' => 'PENDING',
            'status_to' => 'SENT',
        ]);
        $this->assertEquals($cmd->id, $cmdLog->securityCommand->id);
        $this->assertTrue($cmd->logs->contains($cmdLog));

        // 18. Live Location Session
        $session = LiveLocationSession::create([
            'session_id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'device_id' => $device->id,
            'started_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);
        $this->assertEquals($user->id, $session->user->id);
        $this->assertEquals($device->id, $session->device->id);
    }
}
