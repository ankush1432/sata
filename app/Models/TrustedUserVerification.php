<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrustedUserVerification extends Model
{
    use HasFactory;

    protected $fillable = [
        'trusted_user_id',
        'token',
        'verification_code',
        'is_verified',
        'verified_at',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (TrustedUserVerification $model) {
            if (empty($model->token) && !empty($model->verification_code)) {
                $model->token = (string) $model->verification_code;
            } elseif (empty($model->verification_code) && !empty($model->token)) {
                $model->verification_code = (string) $model->token;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function trustedUser(): BelongsTo
    {
        return $this->belongsTo(TrustedUser::class, 'trusted_user_id');
    }
}
