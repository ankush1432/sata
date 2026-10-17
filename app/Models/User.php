<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'mobile',
        'phone_number',
        'email',
        'password',
        'status',
        'current_subscription_id',
        'active_subscription_id',
        'current_device_id',
        'phone_verified_at',
        'mobile_verified_at',
        'email_verified_at',
        'last_active_at',
    ];

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone_number'] = $value;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->attributes['phone_number'] ?? null;
    }

    public function setMobileAttribute($value): void
    {
        $this->attributes['phone_number'] = $value;
    }

    public function getMobileAttribute(): ?string
    {
        return $this->attributes['phone_number'] ?? null;
    }

    public function setMobileVerifiedAtAttribute($value): void
    {
        $this->attributes['mobile_verified_at'] = $value;
        $this->attributes['phone_verified_at'] = $value;
    }

    public function setPhoneVerifiedAtAttribute($value): void
    {
        $this->attributes['phone_verified_at'] = $value;
        $this->attributes['mobile_verified_at'] = $value;
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'phone_verified_at' => 'datetime',
            'mobile_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    public function currentSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'current_subscription_id')
            ->withDefault(function () {
                return $this->activeSubscription;
            });
    }

    public function activeSubscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'active_subscription_id');
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function activeDevice(): HasOne
    {
        return $this->hasOne(UserDevice::class, 'user_id')->where('status', DeviceStatus::ACTIVE);
    }

    public function currentDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'current_device_id');
    }

    public function otpVerifications(): HasMany
    {
        return $this->hasMany(OtpVerification::class, 'user_id');
    }

    public function trustedUsers(): HasMany
    {
        return $this->hasMany(TrustedUser::class, 'user_id');
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class, 'user_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function securityCommands(): HasMany
    {
        return $this->hasMany(SecurityCommand::class, 'user_id');
    }

    public function liveLocationSessions(): HasMany
    {
        return $this->hasMany(LiveLocationSession::class, 'user_id');
    }

    public function redeemedActivationCodes(): HasMany
    {
        return $this->hasMany(ActivationCode::class, 'redeemed_by_user_id');
    }

    public function latestLocationDevice(): ?UserDevice
    {
        return $this->devices()
            ->whereNotNull('last_latitude')
            ->whereNotNull('last_longitude')
            ->latest('location_updated_at')
            ->first() ?? $this->currentDevice ?? $this->devices()->first();
    }

    public function activeLiveLocationSession(): ?LiveLocationSession
    {
        return $this->liveLocationSessions()
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->whereNull('stopped_at')
            ->latest('started_at')
            ->first();
    }

    public function getCurrentLatitudeAttribute(): ?float
    {
        $session = $this->activeLiveLocationSession();
        if ($session && $session->last_latitude !== null) {
            return (float) $session->last_latitude;
        }

        $device = $this->latestLocationDevice();
        return $device && $device->last_latitude !== null ? (float) $device->last_latitude : null;
    }

    public function getCurrentLongitudeAttribute(): ?float
    {
        $session = $this->activeLiveLocationSession();
        if ($session && $session->last_longitude !== null) {
            return (float) $session->last_longitude;
        }

        $device = $this->latestLocationDevice();
        return $device && $device->last_longitude !== null ? (float) $device->last_longitude : null;
    }

    public function getAccuracyAttribute(): ?float
    {
        $session = $this->activeLiveLocationSession();
        if ($session && $session->last_accuracy !== null) {
            return (float) $session->last_accuracy;
        }

        $device = $this->latestLocationDevice();
        return $device && $device->last_accuracy !== null ? (float) $device->last_accuracy : null;
    }

    public function getLastUpdatedAttribute(): ?\Carbon\Carbon
    {
        $session = $this->activeLiveLocationSession();
        if ($session && $session->last_updated_at !== null) {
            return $session->last_updated_at;
        }

        $device = $this->latestLocationDevice();
        return $device?->location_updated_at ?? $device?->last_seen_at ?? null;
    }

    public function getMapLinkAttribute(): ?string
    {
        $lat = $this->current_latitude;
        $lng = $this->current_longitude;

        if ($lat !== null && $lng !== null) {
            return "https://maps.google.com/?q={$lat},{$lng}";
        }

        return null;
    }

    public function getShortLocationReferenceUrlAttribute(): ?string
    {
        $session = $this->activeLiveLocationSession();
        if ($session) {
            return $session->short_location_reference_url;
        }

        // Return deterministic user short ref token if no active session
        $token = substr(hash('sha256', "user-location-ref-{$this->id}-" . config('app.key')), 0, 16);
        return url("/api/v1/location/ref/{$token}");
    }
}
