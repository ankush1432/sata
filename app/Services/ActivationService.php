<?php

namespace App\Services;

use App\Enums\ActivationCodeStatus;
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
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ActivationService
{
    /**
     * Generate a single activation code for a given plan.
     */
    public function generateOne(
        ActivationPlan $plan,
        ?Admin $admin = null,
        ?string $notes = null,
        ?\DateTimeInterface $expiresAt = null
    ): ActivationCode {
        return DB::transaction(function () use ($plan, $admin, $notes, $expiresAt) {
            $seq = ActivationCode::getNextSerialSequence();
            $serialNumber = sprintf('SAT-%06d', $seq);
            $plainCode = ActivationCode::generateSecureCode();
            $normalized = ActivationCode::normalizeCode($plainCode);
            $codeHash = hash('sha256', $normalized);
            $codeLast4 = substr($normalized, -4);

            $code = ActivationCode::create([
                'serial_number' => $serialNumber,
                'code' => $plainCode,
                'code_hash' => $codeHash,
                'code_last4' => $codeLast4,
                'activation_plan_id' => $plan->id,
                'plan_id' => $plan->id,
                'status' => ActivationCodeStatus::AVAILABLE->value,
                'is_redeemed' => false,
                'is_active' => true,
                'generated_by_admin_id' => $admin?->id,
                'created_by_admin_id' => $admin?->id,
                'expires_at' => $expiresAt,
            ]);

            return $code;
        });
    }

    /**
     * Generate a batch of activation codes.
     */
    public function generateBatch(
        ActivationPlan $plan,
        int $quantity,
        ?Admin $admin = null,
        ?string $notes = null,
        ?\DateTimeInterface $expiresAt = null,
        bool $forceQueue = false
    ): ActivationCodeBatch {
        $batchNumber = 'BATCH-' . strtoupper(Str::random(8));

        $batch = ActivationCodeBatch::create([
            'batch_number' => $batchNumber,
            'activation_plan_id' => $plan->id,
            'plan_id' => $plan->id,
            'created_by_admin_id' => $admin?->id,
            'quantity' => $quantity,
            'notes' => $notes,
        ]);

        if ($forceQueue || $quantity >= 50) {
            GenerateActivationCodesJob::dispatch($plan, $quantity, $admin, $batch, $notes);
            return $batch;
        }

        return DB::transaction(function () use ($plan, $quantity, $admin, $batch, $expiresAt) {
            $currentSequence = ActivationCode::getNextSerialSequence();
            $codes = [];

            for ($i = 0; $i < $quantity; $i++) {
                $serialNum = sprintf('SAT-%06d', $currentSequence++);
                $plainCode = ActivationCode::generateSecureCode();
                $normalized = ActivationCode::normalizeCode($plainCode);
                $codeHash = hash('sha256', $normalized);
                $codeLast4 = substr($normalized, -4);

                $codes[] = [
                    'serial_number' => $serialNum,
                    'code' => $plainCode,
                    'code_hash' => $codeHash,
                    'code_last4' => $codeLast4,
                    'activation_plan_id' => $plan->id,
                    'plan_id' => $plan->id,
                    'batch_id' => $batch->id,
                    'status' => ActivationCodeStatus::AVAILABLE->value,
                    'is_redeemed' => false,
                    'is_active' => true,
                    'generated_by_admin_id' => $admin?->id,
                    'created_by_admin_id' => $admin?->id,
                    'expires_at' => $expiresAt,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            ActivationCode::insert($codes);

            return $batch;
        });
    }

    /**
     * Activate a subscription using an activation code and assign to a device.
     * Enforces strict backend validation, row locking, and 11-step activation flow.
     */
    public function activate(User $user, string $rawCode, string|int $deviceId): array
    {
        // Rate limiting / Brute force protection: max 10 attempts per minute per user
        $rateLimitKey = "activation_user:{$user->id}";
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            $seconds = RateLimiter::availableIn($rateLimitKey);
            throw new DomainException("Too many activation attempts. Please wait {$seconds} seconds before trying again.");
        }

        $cleanInput = trim($rawCode);
        $normalizedCode = ActivationCode::normalizeCode($cleanInput);
        $codeHash = hash('sha256', $normalizedCode);

        try {
            $result = DB::transaction(function () use ($user, $cleanInput, $normalizedCode, $codeHash, $deviceId) {
                // 1. Validate authenticated user
                if (! $user->exists) {
                    throw new DomainException('User must be registered and authenticated.');
                }

                // 2. Lock activation code row (Pessimistic row locking)
                $code = ActivationCode::where('code_hash', $codeHash)
                    ->orWhere('code', $normalizedCode)
                    ->orWhere('code', strtoupper($cleanInput))
                    ->lockForUpdate()
                    ->first();

                if (! $code) {
                    throw new DomainException('Invalid activation code.');
                }

                // 3. Validate AVAILABLE status
                if ($code->status === ActivationCodeStatus::ACTIVATED->value || $code->is_redeemed) {
                    throw new DomainException('This activation code has already been redeemed.');
                }

                if ($code->status === ActivationCodeStatus::DISABLED->value || ! $code->is_active) {
                    throw new DomainException('This activation code has been disabled.');
                }

                if ($code->status === ActivationCodeStatus::EXPIRED->value || ($code->expires_at && $code->expires_at->isPast())) {
                    $code->update(['status' => ActivationCodeStatus::EXPIRED->value]);
                    throw new DomainException('This activation code has expired.');
                }

                // 4. Validate device
                $device = null;
                if (is_numeric($deviceId)) {
                    $device = UserDevice::where('id', (int) $deviceId)->first();
                }
                if (! $device) {
                    $device = UserDevice::where('device_uuid', (string) $deviceId)->first();
                }

                if ($device) {
                    if ($device->user_id !== $user->id) {
                        throw new DomainException('Device does not belong to the authenticated user.');
                    }
                    if ($device->status === DeviceStatus::REVOKED) {
                        throw new DomainException('Device has been revoked and cannot activate subscriptions.');
                    }

                    $device->update([
                        'status' => DeviceStatus::ACTIVE,
                        'last_seen_at' => now(),
                    ]);
                } else {
                    // Create or register device for user
                    $device = UserDevice::create([
                        'user_id' => $user->id,
                        'device_uuid' => (string) $deviceId,
                        'device_name' => 'Device ' . substr((string) $deviceId, 0, 8),
                        'model' => 'Mobile Device',
                        'platform' => 'android',
                        'status' => DeviceStatus::ACTIVE,
                        'last_seen_at' => now(),
                    ]);
                }

                // 5. Resolve plan (Backend derives plan strictly from activation code)
                $plan = $code->plan;
                if (! $plan || ! $plan->is_active) {
                    throw new DomainException('The activation plan associated with this code is inactive.');
                }

                // 6. Create subscription (Backend owns duration)
                $startsAt = now();
                $expiresAt = $startsAt->copy()->addDays($plan->duration_days);

                // Preserve full history: supersede any prior active/expiring subscription without deleting
                Subscription::where('user_id', $user->id)
                    ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING])
                    ->update(['status' => SubscriptionStatus::INACTIVE]);

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'activation_plan_id' => $plan->id,
                    'plan_id' => $plan->id,
                    'activation_code_id' => $code->id,
                    'device_id' => $device->id,
                    'status' => SubscriptionStatus::ACTIVE,
                    'starts_at' => $startsAt,
                    'activated_at' => $startsAt,
                    'expires_at' => $expiresAt,
                ]);

                // 7. Assign device
                $device->update([
                    'status' => DeviceStatus::ACTIVE,
                    'is_protection_enabled' => true,
                    'last_seen_at' => now(),
                ]);

                // 8. Mark activation code ACTIVATED
                $code->update([
                    'status' => ActivationCodeStatus::ACTIVATED->value,
                    'is_redeemed' => true,
                    'activated_by_user_id' => $user->id,
                    'redeemed_by_user_id' => $user->id,
                    'activated_device_id' => $device->id,
                    'activated_at' => $startsAt,
                    'redeemed_at' => $startsAt,
                ]);

                // 9. Set user ACTIVE
                $user->update([
                    'status' => UserStatus::ACTIVE,
                    'current_device_id' => $device->id,
                    'active_subscription_id' => $subscription->id,
                ]);

                // 10. Create audit/event records (Never log full activation code!)
                SecurityEvent::create([
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'event_type' => SecurityEventType::PROTECTION_ENABLED,
                    'severity' => 'INFO',
                    'description' => "Protection enabled via activation of plan {$plan->name}.",
                    'metadata' => [
                        'serial_number' => $code->serial_number,
                        'code_last4' => $code->code_last4,
                        'plan_code' => $plan->code,
                        'duration_days' => $plan->duration_days,
                        'subscription_id' => $subscription->id,
                    ],
                    'occurred_at' => $startsAt,
                    'triggered_at' => $startsAt,
                ]);

                // 11. Send activation notification
                Notification::create([
                    'user_id' => $user->id,
                    'device_id' => $device->id,
                    'title' => 'Device Protection Activated',
                    'body' => "Your SATA subscription ({$plan->name}) has been activated successfully on your device.",
                    'type' => 'ACTIVATION_SUCCESSFUL',
                    'payload' => [
                        'plan_code' => $plan->code,
                        'duration_days' => $plan->duration_days,
                        'serial_number' => $code->serial_number,
                        'code_last4' => $code->code_last4,
                    ],
                    'status' => 'SENT',
                ]);

                return [
                    'subscription' => $subscription->load('plan'),
                    'device' => $device,
                    'plan' => $plan,
                    'code' => [
                        'serial_number' => $code->serial_number,
                        'code_last4' => $code->code_last4,
                        'status' => $code->status,
                        'activated_at' => $startsAt->toIso8601String(),
                    ],
                ];
            });

            RateLimiter::clear($rateLimitKey);
            return $result;
        } catch (DomainException $e) {
            RateLimiter::hit($rateLimitKey, 60);
            throw $e;
        }
    }

    /**
     * Redeem an activation code (backward-compatibility alias using activate).
     */
    public function redeemCode(string $codeString, User $user, string|int|null $deviceId = null): Subscription
    {
        $deviceId = $deviceId ?? $user->current_device_id ?? ('DEVICE-' . $user->id);
        $result = $this->activate($user, $codeString, $deviceId);
        return $result['subscription'];
    }
}

