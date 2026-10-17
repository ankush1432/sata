<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtpVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'mobile',
        'phone_number',
        'otp_code',
        'code_hash',
        'purpose',
        'attempts',
        'is_verified',
        'verified_at',
        'expires_at',
        'ip_address',
    ];

    public function setMobileAttribute($value): void
    {
        $this->attributes['phone_number'] = $value;
    }

    public function getMobileAttribute(): ?string
    {
        return $this->attributes['phone_number'] ?? null;
    }

    protected static function booted(): void
    {
        static::creating(function (OtpVerification $otp) {
            if (empty($otp->phone_number) && !empty($otp->phone)) {
                $otp->phone_number = $otp->phone;
            } elseif (empty($otp->phone) && !empty($otp->phone_number)) {
                $otp->phone = $otp->phone_number;
            }
            if (empty($otp->code_hash) && !empty($otp->otp_code)) {
                $otp->code_hash = hash('sha256', (string) $otp->otp_code);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'attempts' => 'integer',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        return ! $this->is_verified && $this->expires_at->isFuture() && $this->attempts < 5;
    }

    public function markAsVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }
}
