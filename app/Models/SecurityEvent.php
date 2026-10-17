<?php

namespace App\Models;

use App\Enums\SecurityEventType;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SecurityEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_uuid',
        'user_id',
        'device_id',
        'event_type',
        'severity',
        'status',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'description',
        'metadata',
        'occurred_at',
        'triggered_at',
    ];

    protected $appends = [
        'type',
        'latitude',
        'longitude',
        'accuracy',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => SecurityEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'datetime',
            'triggered_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SecurityEvent $event) {
            if (empty($event->event_uuid)) {
                $event->event_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($event->triggered_at) && !empty($event->occurred_at)) {
                $event->triggered_at = $event->occurred_at;
            } elseif (empty($event->triggered_at)) {
                $event->triggered_at = now();
            }
            if (empty($event->occurred_at) && !empty($event->triggered_at)) {
                $event->occurred_at = $event->triggered_at;
            } elseif (empty($event->occurred_at)) {
                $event->occurred_at = now();
            }
            if (empty($event->status)) {
                $event->status = 'REPORTED';
            }
        });

        static::deleting(function (SecurityEvent $event) {
            throw new DomainException('Security events are immutable audit logs and cannot be deleted.');
        });
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function getTypeAttribute(): ?string
    {
        return $this->event_type?->value ?? (is_string($this->event_type) ? $this->event_type : null);
    }

    public function getLatitudeAttribute(): ?float
    {
        return $this->location?->latitude ?? $this->locations->first()?->latitude;
    }

    public function getLongitudeAttribute(): ?float
    {
        return $this->location?->longitude ?? $this->locations->first()?->longitude;
    }

    public function getAccuracyAttribute(): ?float
    {
        return $this->location?->accuracy ?? $this->locations->first()?->accuracy;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(SecurityEventLocation::class, 'security_event_id');
    }

    public function location(): HasOne
    {
        return $this->hasOne(SecurityEventLocation::class, 'security_event_id')->latestOfMany();
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(SecurityEvidence::class, 'security_event_id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(SecurityEvidence::class, 'security_event_id');
    }
}
