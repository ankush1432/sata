<?php

namespace App\Models;

use App\Enums\PlanDuration;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivationPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'duration_days',
        'price',
        'currency',
        'description',
        'status',
        'is_active',
    ];

    protected $appends = [
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActivationPlan $plan) {
            if (! empty($plan->code)) {
                $plan->code = strtoupper(trim($plan->code));
                // Backend owns duration for standard plan codes
                $standardDuration = PlanDuration::durationForCode($plan->code);
                if ($standardDuration !== null && empty($plan->duration_days)) {
                    $plan->duration_days = $standardDuration;
                }
            }

            if (! isset($plan->attributes['status'])) {
                $plan->attributes['status'] = ($plan->is_active ?? true) ? 'ACTIVE' : 'INACTIVE';
            }
        });

        static::saving(function (ActivationPlan $plan) {
            if (isset($plan->attributes['status'])) {
                $plan->attributes['status'] = strtoupper($plan->attributes['status']);
                $plan->attributes['is_active'] = in_array($plan->attributes['status'], ['ACTIVE', 'ENABLED', '1'], true);
            } elseif (isset($plan->attributes['is_active'])) {
                $plan->attributes['status'] = $plan->attributes['is_active'] ? 'ACTIVE' : 'INACTIVE';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function getStatusAttribute(): string
    {
        if (! empty($this->attributes['status'])) {
            return strtoupper($this->attributes['status']);
        }

        return ($this->is_active ?? true) ? 'ACTIVE' : 'INACTIVE';
    }

    public function setStatusAttribute($value): void
    {
        $status = strtoupper(trim((string) $value));
        $this->attributes['status'] = $status;
        $this->attributes['is_active'] = in_array($status, ['ACTIVE', 'ENABLED', '1'], true);
    }

    public function setIsActiveAttribute($value): void
    {
        $bool = (bool) $value;
        $this->attributes['is_active'] = $bool;
        $this->attributes['status'] = $bool ? 'ACTIVE' : 'INACTIVE';
    }

    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_active', true)
                ->orWhere('status', 'ACTIVE');
        });
    }

    public function enable(): self
    {
        $this->update([
            'is_active' => true,
            'status' => 'ACTIVE',
        ]);

        return $this;
    }

    public function disable(): self
    {
        $this->update([
            'is_active' => false,
            'status' => 'INACTIVE',
        ]);

        return $this;
    }

    public function hasHistoricalSubscriptions(): bool
    {
        return $this->subscriptions()->exists();
    }

    public function activationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class, 'activation_plan_id');
    }

    public function batches(): HasMany
    {
        return $this->hasMany(ActivationCodeBatch::class, 'activation_plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'activation_plan_id');
    }
}
