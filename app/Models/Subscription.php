<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'activation_plan_id',
        'plan_id',
        'activation_code_id',
        'device_id',
        'status',
        'starts_at',
        'expires_at',
        'activated_at',
        'cancelled_at',
        'disabled_at',
        'disable_reason',
        'expiring_reminder_sent_at',
        'expired_reminder_sent_at',
        'reminder_stages_sent',
    ];

    protected static function booted(): void
    {
        static::creating(function (Subscription $sub) {
            if (empty($sub->activation_plan_id) && !empty($sub->plan_id)) {
                $sub->activation_plan_id = $sub->plan_id;
            } elseif (empty($sub->plan_id) && !empty($sub->activation_plan_id)) {
                $sub->plan_id = $sub->activation_plan_id;
            }

            if (empty($sub->activated_at) && !empty($sub->starts_at)) {
                $sub->activated_at = $sub->starts_at;
            } elseif (empty($sub->starts_at) && !empty($sub->activated_at)) {
                $sub->starts_at = $sub->activated_at;
            }

            if ($sub->reminder_stages_sent === null) {
                $sub->reminder_stages_sent = [];
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'expiring_reminder_sent_at' => 'datetime',
            'expired_reminder_sent_at' => 'datetime',
            'reminder_stages_sent' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ActivationPlan::class, 'activation_plan_id')
            ->withDefault(function () {
                if ($this->plan_id) {
                    return ActivationPlan::find($this->plan_id);
                }
                return null;
            });
    }

    public function activationCode(): BelongsTo
    {
        return $this->belongsTo(ActivationCode::class, 'activation_code_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, [SubscriptionStatus::ACTIVE, SubscriptionStatus::EXPIRING], true)
            && ($this->starts_at === null || $this->starts_at->isPast() || $this->starts_at->isCurrentSecond())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isExpiring(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRING;
    }

    public function isExpired(): bool
    {
        return $this->status === SubscriptionStatus::EXPIRED || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isDisabled(): bool
    {
        return $this->status === SubscriptionStatus::DISABLED;
    }

    public function daysRemaining(): int
    {
        if (! $this->expires_at || $this->expires_at->isPast()) {
            return 0;
        }

        return (int) max(0, ceil(now()->diffInSeconds($this->expires_at, false) / 86400));
    }

    /**
     * Check if a reminder for a specific stage (in days) has already been sent.
     * Ensures: "Each reminder must be sent only once."
     */
    public function hasReminderBeenSentForStage(int $stage): bool
    {
        $sentStages = $this->reminder_stages_sent ?? [];
        if (in_array((int) $stage, array_map('intval', (array) $sentStages), true)) {
            return true;
        }

        // Secondary idempotency safeguard: check existing notification records
        return Notification::where('user_id', $this->user_id)
            ->where('type', 'SUBSCRIPTION_EXPIRING')
            ->whereJsonContains('payload->stage_days', (int) $stage)
            ->where(function ($query) {
                if ($this->starts_at) {
                    $query->where('created_at', '>=', $this->starts_at);
                }
            })
            ->exists();
    }

    /**
     * Record that a reminder for a specific stage has been sent.
     */
    public function recordReminderSentForStage(int $stage): void
    {
        $sentStages = $this->reminder_stages_sent ?? [];
        $stageInt = (int) $stage;

        if (! in_array($stageInt, array_map('intval', (array) $sentStages), true)) {
            $sentStages[] = $stageInt;
        }

        $this->update([
            'reminder_stages_sent' => array_values(array_unique($sentStages)),
            'expiring_reminder_sent_at' => now(),
        ]);
    }
}
