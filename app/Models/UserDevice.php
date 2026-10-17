<?php

namespace App\Models;

use App\Enums\DeviceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDevice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_uuid',
        'device_name',
        'model',
        'device_model',
        'manufacturer',
        'platform',
        'device_management_mode',
        'os_version',
        'app_version',
        'fcm_token',
        'sim_serial',
        'phone_number',
        'imei',
        'status',
        'is_protection_enabled',
        'last_seen_at',
        'revoked_at',
        'last_latitude',
        'last_longitude',
        'last_accuracy',
        'last_altitude',
        'last_speed',
        'last_heading',
        'location_updated_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     * FCM tokens must not be exposed unnecessarily.
     */
    protected $hidden = [
        'fcm_token',
    ];

    /**
     * The accessors to append to the model's array form.
     */
    protected $appends = [
        'device_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeviceStatus::class,
            'is_protection_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_accuracy' => 'float',
            'last_altitude' => 'float',
            'last_speed' => 'float',
            'last_heading' => 'float',
            'location_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserDevice $device) {
            if (empty($device->device_uuid)) {
                $device->device_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($device->device_model) && ! empty($device->model)) {
                $device->device_model = $device->model;
            } elseif (empty($device->model) && ! empty($device->device_model)) {
                $device->model = $device->device_model;
            }
        });

        static::updating(function (UserDevice $device) {
            if (! empty($device->model) && empty($device->device_model)) {
                $device->device_model = $device->model;
            } elseif (! empty($device->device_model) && empty($device->model)) {
                $device->model = $device->device_model;
            }
        });
    }

    /**
     * Accessor for device_id (alias for device_uuid).
     */
    public function getDeviceIdAttribute(): ?string
    {
        return $this->attributes['device_uuid'] ?? null;
    }

    /**
     * Mutator for device_id.
     */
    public function setDeviceIdAttribute(?string $value): void
    {
        if ($value !== null) {
            $this->attributes['device_uuid'] = $value;
        }
    }

    /**
     * Accessor for is_active.
     */
    public function getIsActiveAttribute(): bool
    {
        return $this->status === DeviceStatus::ACTIVE;
    }

    /**
     * Mutator for is_active.
     */
    public function setIsActiveAttribute($value): void
    {
        $this->attributes['status'] = filter_var($value, FILTER_VALIDATE_BOOLEAN)
            ? DeviceStatus::ACTIVE->value
            : DeviceStatus::INACTIVE->value;
    }

    /**
     * Mutator for device_model.
     */
    public function setDeviceModelAttribute(?string $value): void
    {
        $this->attributes['device_model'] = $value;
        if (empty($this->attributes['model'])) {
            $this->attributes['model'] = $value;
        }
    }

    public function isRevoked(): bool
    {
        return $this->status === DeviceStatus::REVOKED;
    }

    public function isActive(): bool
    {
        return $this->status === DeviceStatus::ACTIVE;
    }

    public function revoke(): void
    {
        $this->update([
            'status' => DeviceStatus::REVOKED,
            'is_protection_enabled' => false,
            'revoked_at' => now(),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function securityEvents(): HasMany
    {
        return $this->hasMany(SecurityEvent::class, 'device_id');
    }

    public function securityCommands(): HasMany
    {
        return $this->hasMany(SecurityCommand::class, 'device_id');
    }

    public function liveLocationSessions(): HasMany
    {
        return $this->hasMany(LiveLocationSession::class, 'device_id');
    }
}
