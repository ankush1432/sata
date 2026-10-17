<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LiveLocationSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'session_id',
        'session_token',
        'short_token',
        'is_active',
        'status',
        'started_at',
        'expires_at',
        'stopped_at',
        'ended_at',
        'last_latitude',
        'last_longitude',
        'last_accuracy',
        'last_altitude',
        'last_speed',
        'last_heading',
        'last_updated_at',
    ];

    protected $appends = [
        'short_location_reference_url',
        'map_link',
    ];

    protected static function booted(): void
    {
        static::creating(function (LiveLocationSession $session) {
            if (empty($session->session_id)) {
                $session->session_id = (string) Str::uuid();
            }
            if (empty($session->session_token)) {
                $session->session_token = $session->session_id;
            }
            if (empty($session->short_token)) {
                $session->short_token = Str::random(16);
            }
            if (empty($session->started_at)) {
                $session->started_at = now();
            }
            if (empty($session->status)) {
                $session->status = 'ACTIVE';
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_accuracy' => 'float',
            'last_altitude' => 'float',
            'last_speed' => 'float',
            'last_heading' => 'float',
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'stopped_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    /**
     * Determine if the session is currently active.
     */
    public function isActive(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        if ($this->status === 'STOPPED') {
            return false;
        }

        if (! empty($this->stopped_at) || ! empty($this->ended_at)) {
            return false;
        }

        return $this->status === 'ACTIVE' && (bool) $this->is_active;
    }

    /**
     * Determine if session has expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === 'EXPIRED') {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Stop the live location session.
     */
    public function stop(): void
    {
        $now = now();
        $this->update([
            'status' => 'STOPPED',
            'is_active' => false,
            'stopped_at' => $now,
            'ended_at' => $now,
        ]);
    }

    /**
     * Mutator for stopped_at syncs ended_at.
     */
    public function setStoppedAtAttribute($value): void
    {
        $this->attributes['stopped_at'] = $value;
        $this->attributes['ended_at'] = $value;
    }

    /**
     * Mutator for ended_at syncs stopped_at.
     */
    public function setEndedAtAttribute($value): void
    {
        $this->attributes['ended_at'] = $value;
        $this->attributes['stopped_at'] = $value;
    }

    /**
     * Accessor for stopped_at fallback to ended_at.
     */
    public function getStoppedAtAttribute(): ?\Carbon\Carbon
    {
        $val = $this->attributes['stopped_at'] ?? $this->attributes['ended_at'] ?? null;
        return $val ? \Carbon\Carbon::parse($val) : null;
    }

    /**
     * Accessor for short_location_reference_url.
     * Note: NEVER exposes raw coordinates in public URL paths.
     */
    public function getShortLocationReferenceUrlAttribute(): string
    {
        $token = $this->short_token ?: $this->session_token ?: $this->session_id;
        return url("/api/v1/location/ref/{$token}");
    }

    /**
     * Accessor for Google Maps link.
     */
    public function getMapLinkAttribute(): ?string
    {
        if ($this->last_latitude !== null && $this->last_longitude !== null) {
            return "https://maps.google.com/?q={$this->last_latitude},{$this->last_longitude}";
        }

        return null;
    }
}
