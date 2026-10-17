<?php

namespace App\Models;

use App\Enums\TrustedUserStatus;
use DomainException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TrustedUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'phone_number',
        'fcm_token',
        'relationship',
        'status',
        'priority',
        'is_active',
        'verification_token',
        'verification_reference',
        'verified_at',
    ];

    protected $casts = [];

    protected $hidden = [
        'fcm_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => TrustedUserStatus::class,
            'priority' => 'integer',
            'is_active' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function setPhoneAttribute($value): void
    {
        $this->attributes['phone'] = $value;
        $this->attributes['phone_number'] = $value;
    }

    public function setPhoneNumberAttribute($value): void
    {
        $this->attributes['phone_number'] = $value;
        $this->attributes['phone'] = $value;
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->attributes['phone'] ?? $this->attributes['phone_number'] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function (TrustedUser $trustedUser) {
            if (empty($trustedUser->phone_number) && ! empty($trustedUser->phone)) {
                $trustedUser->phone_number = $trustedUser->phone;
            }
            if (empty($trustedUser->phone) && ! empty($trustedUser->phone_number)) {
                $trustedUser->phone = $trustedUser->phone_number;
            }

            if (empty($trustedUser->verification_token)) {
                $trustedUser->verification_token = Str::random(32);
            }

            if (empty($trustedUser->verification_reference)) {
                $trustedUser->verification_reference = 'TRU-' . strtoupper(Str::random(10));
            }

            $count = TrustedUser::where('user_id', $trustedUser->user_id)->count();
            if ($count >= 2) {
                throw new DomainException('Maximum limit of 2 trusted users reached.');
            }
        });
    }

    public function isVerified(): bool
    {
        return $this->status === TrustedUserStatus::VERIFIED && $this->verified_at !== null;
    }

    public function isPending(): bool
    {
        return $this->status === TrustedUserStatus::PENDING;
    }

    public function isDisabled(): bool
    {
        return $this->status === TrustedUserStatus::DISABLED;
    }

    public function verify(): void
    {
        $this->update([
            'status' => TrustedUserStatus::VERIFIED,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        // Also mark any pending verifications as verified
        $this->verifications()->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function disable(): void
    {
        $this->update([
            'status' => TrustedUserStatus::DISABLED,
            'is_active' => false,
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(TrustedUserVerification::class, 'trusted_user_id');
    }
}
