<?php

namespace App\Services;

use App\Enums\DeviceStatus;
use App\Enums\NotificationType;
use App\Enums\SubscriptionStatus;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    /**
     * Get the current active or expiring subscription for a user.
     */
    public function getCurrentSubscription(User $user): ?Subscription
    {
        // First reconcile any pending expired subscriptions
        $this->reconcileUserStatus($user);

        return Subscription::with(['plan', 'device', 'activationCode'])
            ->where('user_id', $user->id)
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING])
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Reconcile subscription status for a specific user.
     * Guarantees:
     * - User account/data is NEVER deleted.
     * - Trusted users, devices, security events, evidence history, profile preserved.
     * - Only protection services become inactive on expiry.
     */
    public function reconcileUserStatus(User $user): ?Subscription
    {
        $now = now();
        $thresholdDays = config('subscription.expiring_threshold_days', 7);
        $thresholdDate = $now->copy()->addDays($thresholdDays);

        $activeSubs = Subscription::with(['device'])
            ->where('user_id', $user->id)
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING])
            ->get();

        foreach ($activeSubs as $sub) {
            if ($sub->expires_at && $sub->expires_at->lessThanOrEqualTo($now)) {
                $sub->update([
                    'status' => SubscriptionStatus::EXPIRED,
                ]);

                if ($sub->device_id && $sub->device) {
                    $sub->device->update([
                        'is_protection_enabled' => false,
                    ]);
                }

                if ($sub->expired_reminder_sent_at === null) {
                    $this->triggerExpiredReminder($sub);
                    $sub->update(['expired_reminder_sent_at' => $now]);
                }
            } elseif ($sub->expires_at && $sub->status === SubscriptionStatus::ACTIVE && $sub->expires_at->greaterThan($now) && $sub->expires_at->lessThanOrEqualTo($thresholdDate)) {
                $sub->update([
                    'status' => SubscriptionStatus::EXPIRING,
                ]);
            }
        }

        return Subscription::with(['plan', 'device', 'activationCode'])
            ->where('user_id', $user->id)
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Authoritative Subscription & Protection State for Phase 14.
     * Evaluates states: ACTIVE | EXPIRING | EXPIRED | INACTIVE
     */
    public function getAuthoritativeSubscriptionDetails(User $user): array
    {
        $latest = $this->reconcileUserStatus($user);

        if (! $latest) {
            return [
                'subscription' => null,
                'state' => 'INACTIVE',
                'has_active_subscription' => false,
                'protection_status' => 'INACTIVE',
                'is_protection_enabled' => false,
                'expiry_date' => null,
                'formatted_expiry_date' => null,
                'permissions' => [
                    'allow_login' => true,
                    'allow_account_access' => true,
                    'allow_subscription_history' => true,
                    'allow_activation' => true,
                    'allow_existing_data_access' => true,
                    'allow_new_protection_services' => false,
                    'allow_sensitive_security_operations' => false,
                ],
                'message' => 'No subscription found. Protection is inactive.',
            ];
        }

        $now = now();
        $isPastExpiry = $latest->expires_at && $latest->expires_at->lessThanOrEqualTo($now);

        if ($latest->status === SubscriptionStatus::EXPIRED || $isPastExpiry) {
            $state = 'EXPIRED';
            $hasActive = false;
            $protectionStatus = 'INACTIVE';
        } elseif ($latest->status === SubscriptionStatus::DISABLED || $latest->status === SubscriptionStatus::INACTIVE) {
            $state = 'INACTIVE';
            $hasActive = false;
            $protectionStatus = 'INACTIVE';
        } elseif ($latest->status === SubscriptionStatus::EXPIRING || ($latest->expires_at && $latest->isExpiring())) {
            $state = 'EXPIRING';
            $hasActive = true;
            $protectionStatus = 'ACTIVE';
        } else {
            $state = 'ACTIVE';
            $hasActive = true;
            $protectionStatus = 'ACTIVE';
        }

        $deviceProtectionEnabled = $latest->device ? (bool) $latest->device->is_protection_enabled : false;

        $formattedExpiry = $latest->expires_at ? $latest->expires_at->format('d M Y') : null;

        return [
            'subscription' => $latest,
            'state' => $state,
            'has_active_subscription' => $hasActive,
            'protection_status' => $protectionStatus,
            'is_protection_enabled' => $hasActive && $deviceProtectionEnabled,
            'expiry_date' => $formattedExpiry,
            'formatted_expiry_date' => $formattedExpiry,
            'permissions' => [
                'allow_login' => true,
                'allow_account_access' => true,
                'allow_subscription_history' => true,
                'allow_activation' => true,
                'allow_existing_data_access' => true,
                'allow_new_protection_services' => $hasActive,
                'allow_sensitive_security_operations' => $hasActive,
            ],
            'message' => $state === 'EXPIRED'
                ? 'Your protection plan has expired.'
                : ($state === 'EXPIRING'
                    ? 'Your protection plan is expiring soon.'
                    : ($state === 'ACTIVE'
                        ? 'Protection plan is active.'
                        : 'Protection plan is inactive.')),
        ];
    }

    /**
     * Check if user is authorized to run protection functionality.
     */
    public function hasActiveProtection(User $user): bool
    {
        $details = $this->getAuthoritativeSubscriptionDetails($user);
        return $details['has_active_subscription'] === true;
    }

    /**
     * Get full subscription history for a user, paginated.
     * Historical subscriptions are preserved and never deleted.
     */
    public function getSubscriptionHistory(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return Subscription::with(['plan', 'device', 'activationCode'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Process subscriptions approaching expiry.
     * Marks ACTIVE subscriptions within the threshold as EXPIRING idempotently.
     *
     * Rules:
     * - ACTIVE → EXPIRING when appropriate.
     * - Idempotent: repeated runs do not cause duplicate transitions or side-effects.
     * - Preserves user, device history, security events, evidence, trusted users.
     */
    public function processExpiring(?int $daysThreshold = null): array
    {
        $now = now();
        $days = $daysThreshold ?? config('subscription.expiring_threshold_days', 7);
        $thresholdDate = $now->copy()->addDays($days);

        $processed = 0;

        // Find subscriptions that are ACTIVE and will expire within thresholdDate
        $expiringSubscriptions = Subscription::with(['user', 'device'])
            ->where('status', SubscriptionStatus::ACTIVE)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $thresholdDate)
            ->where('expires_at', '>', $now)
            ->get();

        foreach ($expiringSubscriptions as $subscription) {
            DB::transaction(function () use ($subscription, &$processed) {
                // Double check row with lock for idempotency
                $lockedSub = Subscription::lockForUpdate()->find($subscription->id);
                if (! $lockedSub || $lockedSub->status !== SubscriptionStatus::ACTIVE) {
                    return;
                }

                $lockedSub->update([
                    'status' => SubscriptionStatus::EXPIRING,
                ]);

                if ($lockedSub->expiring_reminder_sent_at === null) {
                    $this->triggerExpiringReminder($lockedSub);
                    $lockedSub->update([
                        'expiring_reminder_sent_at' => now(),
                    ]);
                }

                $processed++;
            });
        }

        return [
            'detected' => $processed,
            'transitioned' => $processed,
        ];
    }

    /**
     * Process multi-stage subscription reminder notifications.
     *
     * Subscription reminders:
     * - 7 days before expiry.
     * - 3 days before expiry.
     * - 1 day before expiry.
     * (Stages are configurable via config('subscription.reminder_stages')).
     *
     * Rules:
     * - Each reminder must be sent only once.
     * - Jobs must be idempotent.
     * - Preserves user, device history, security events, evidence, trusted users.
     */
    public function processReminders(?array $customStages = null): array
    {
        $now = now();
        $stages = $customStages ?? config('subscription.reminder_stages', [7, 3, 1]);
        // Sort descending so highest threshold is checked first
        rsort($stages, SORT_NUMERIC);

        $remindersSent = 0;
        $subscriptionsChecked = 0;

        // Subscriptions currently active or expiring with a valid expiry date in future
        $subscriptions = Subscription::with(['user', 'device'])
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING])
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', $now)
            ->get();

        foreach ($subscriptions as $sub) {
            $subscriptionsChecked++;
            $daysRemaining = $sub->daysRemaining();

            // Check each configured stage
            foreach ($stages as $stage) {
                $stageInt = (int) $stage;

                // If remaining days has entered or is within this stage
                if ($daysRemaining <= $stageInt) {
                    if (! $sub->hasReminderBeenSentForStage($stageInt)) {
                        DB::transaction(function () use ($sub, $stageInt, $daysRemaining, &$remindersSent) {
                            $lockedSub = Subscription::lockForUpdate()->find($sub->id);
                            if (! $lockedSub || $lockedSub->hasReminderBeenSentForStage($stageInt)) {
                                return;
                            }

                            $this->triggerExpiringReminderForStage($lockedSub, $stageInt, $daysRemaining);
                            $lockedSub->recordReminderSentForStage($stageInt);
                            $remindersSent++;
                        });
                    }
                }
            }
        }

        // Also process any expired subscriptions missing the final expired reminder
        $expiredSubscriptions = Subscription::with(['user', 'device'])
            ->where('status', SubscriptionStatus::EXPIRED)
            ->whereNull('expired_reminder_sent_at')
            ->get();

        foreach ($expiredSubscriptions as $expSub) {
            DB::transaction(function () use ($expSub, &$remindersSent) {
                $lockedSub = Subscription::lockForUpdate()->find($expSub->id);
                if (! $lockedSub || $lockedSub->expired_reminder_sent_at !== null) {
                    return;
                }

                $this->triggerExpiredReminder($lockedSub);
                $lockedSub->update([
                    'expired_reminder_sent_at' => now(),
                ]);
                $remindersSent++;
            });
        }

        return [
            'stages' => $stages,
            'subscriptions_checked' => $subscriptionsChecked,
            'reminders_sent' => $remindersSent,
        ];
    }

    /**
     * Process expired subscriptions.
     *
     * Transitions ACTIVE/EXPIRING → EXPIRED at expiry.
     * Guarantees:
     * - Do NOT delete user.
     * - Do NOT delete device history.
     * - Do NOT delete security events.
     * - Do NOT delete evidence.
     * - Do NOT delete trusted users.
     * - Sets linked device is_protection_enabled = false.
     * - Idempotent execution.
     */
    public function processExpired(): array
    {
        $now = now();
        $processed = 0;
        $remindersSent = 0;

        $expiredSubscriptions = Subscription::with(['user', 'device'])
            ->whereIn('status', [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        foreach ($expiredSubscriptions as $subscription) {
            DB::transaction(function () use ($subscription, &$processed, &$remindersSent, $now) {
                $lockedSub = Subscription::lockForUpdate()->find($subscription->id);
                if (! $lockedSub || ! in_array($lockedSub->status, [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING], true)) {
                    return;
                }

                // Transition status to EXPIRED
                $lockedSub->update([
                    'status' => SubscriptionStatus::EXPIRED,
                ]);
                $processed++;

                // Deactivate protection on device without deleting device or device history
                if ($lockedSub->device_id && $lockedSub->device) {
                    $lockedSub->device->update([
                        'is_protection_enabled' => false,
                    ]);
                }

                // Send expired reminder hook idempotently (only once)
                if ($lockedSub->expired_reminder_sent_at === null) {
                    $this->triggerExpiredReminder($lockedSub);
                    $lockedSub->update([
                        'expired_reminder_sent_at' => $now,
                    ]);
                    $remindersSent++;
                }
            });
        }

        return [
            'expired' => $processed,
            'reminders_sent' => $remindersSent,
        ];
    }

    /**
     * Disable a subscription manually/by admin.
     */
    public function disableSubscription(Subscription $subscription, string $reason = 'Administrative action'): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason) {
            $subscription->update([
                'status' => SubscriptionStatus::DISABLED,
                'disabled_at' => now(),
                'disable_reason' => $reason,
            ]);

            if ($subscription->device) {
                $subscription->device->update([
                    'is_protection_enabled' => false,
                ]);
            }

            return $subscription->fresh();
        });
    }

    /**
     * Trigger reminder notification for a specific stage (e.g. 7, 3, or 1 day).
     */
    public function triggerExpiringReminderForStage(Subscription $subscription, int $stage, int $daysRemaining): void
    {
        $dayLabel = $daysRemaining === 1 ? '1 day' : "{$daysRemaining} days";
        $expiresAtFormatted = $subscription->expires_at?->toFormattedDateString() ?? 'soon';

        Notification::create([
            'user_id' => $subscription->user_id,
            'device_id' => $subscription->device_id,
            'title' => "Subscription Expiring: {$dayLabel} Remaining",
            'body' => "Your security subscription expires on {$expiresAtFormatted}. You have {$dayLabel} of active protection remaining. Renew your plan now to prevent interruption of security monitoring.",
            'type' => NotificationType::SUBSCRIPTION_EXPIRING->value,
            'payload' => [
                'subscription_id' => $subscription->id,
                'stage_days' => $stage,
                'days_remaining' => $daysRemaining,
                'expires_at' => $subscription->expires_at?->toIso8601String(),
            ],
            'is_read' => false,
            'status' => 'PENDING',
        ]);

        Log::info("Triggered stage-{$stage} expiring reminder for subscription #{$subscription->id}, user #{$subscription->user_id} ({$daysRemaining} days remaining)");
    }

    /**
     * Trigger reminder notification when subscription is expiring (generic fallback).
     */
    protected function triggerExpiringReminder(Subscription $subscription): void
    {
        $daysRemaining = $subscription->daysRemaining();
        $this->triggerExpiringReminderForStage($subscription, $daysRemaining > 0 ? $daysRemaining : 7, $daysRemaining);
    }

    /**
     * Trigger notification when subscription has expired.
     */
    protected function triggerExpiredReminder(Subscription $subscription): void
    {
        Notification::create([
            'user_id' => $subscription->user_id,
            'device_id' => $subscription->device_id,
            'title' => 'Subscription Expired',
            'body' => 'Your security subscription has expired. Device security protection is now deactivated. Renew now to restore full protection.',
            'type' => NotificationType::SUBSCRIPTION_EXPIRED->value,
            'payload' => [
                'subscription_id' => $subscription->id,
                'expired_at' => $subscription->expires_at?->toIso8601String(),
            ],
            'is_read' => false,
            'status' => 'PENDING',
        ]);

        Log::info("Triggered expired subscription notification for subscription #{$subscription->id}, user #{$subscription->user_id}");
    }
}
