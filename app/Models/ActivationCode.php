<?php

namespace App\Models;

use App\Enums\ActivationCodeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'serial_number',
        'code',
        'code_hash',
        'code_last4',
        'activation_plan_id',
        'plan_id',
        'batch_id',
        'status',
        'is_redeemed',
        'is_active',
        'generated_by_admin_id',
        'created_by_admin_id',
        'activated_by_user_id',
        'redeemed_by_user_id',
        'activated_device_id',
        'activated_at',
        'redeemed_at',
        'expires_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (ActivationCode $code) {
            // Plan ID sync
            if (empty($code->activation_plan_id) && !empty($code->plan_id)) {
                $code->activation_plan_id = $code->plan_id;
            } elseif (empty($code->plan_id) && !empty($code->activation_plan_id)) {
                $code->plan_id = $code->activation_plan_id;
            }

            // Admin ID sync
            if (empty($code->generated_by_admin_id) && !empty($code->created_by_admin_id)) {
                $code->generated_by_admin_id = $code->created_by_admin_id;
            } elseif (empty($code->created_by_admin_id) && !empty($code->generated_by_admin_id)) {
                $code->created_by_admin_id = $code->generated_by_admin_id;
            }

            // Generate secure code if omitted
            if (empty($code->code)) {
                $code->code = self::generateSecureCode();
            }

            // Generate hash and last 4
            $normalized = self::normalizeCode($code->code);
            if (empty($code->code_hash)) {
                $code->code_hash = hash('sha256', $normalized);
            }
            if (empty($code->code_last4)) {
                $code->code_last4 = substr($normalized, -4);
            }

            // Generate serial number if omitted
            if (empty($code->serial_number)) {
                $seq = self::getNextSerialSequence();
                $code->serial_number = sprintf('SAT-%06d', $seq);
            }

            // Default status
            if (empty($code->status)) {
                $code->status = ActivationCodeStatus::AVAILABLE->value;
            }
        });

        static::saving(function (ActivationCode $code) {
            // Synchronize status with flags
            if ($code->status === ActivationCodeStatus::ACTIVATED->value) {
                $code->is_redeemed = true;
            } elseif ($code->status === ActivationCodeStatus::AVAILABLE->value) {
                $code->is_redeemed = false;
            }

            // Synchronize user & timestamp
            if (!empty($code->activated_by_user_id) && empty($code->redeemed_by_user_id)) {
                $code->redeemed_by_user_id = $code->activated_by_user_id;
            } elseif (!empty($code->redeemed_by_user_id) && empty($code->activated_by_user_id)) {
                $code->activated_by_user_id = $code->redeemed_by_user_id;
            }

            if (!empty($code->activated_at) && empty($code->redeemed_at)) {
                $code->redeemed_at = $code->activated_at;
            } elseif (!empty($code->redeemed_at) && empty($code->activated_at)) {
                $code->activated_at = $code->redeemed_at;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_redeemed' => 'boolean',
            'is_active' => 'boolean',
            'activated_at' => 'datetime',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public static function generateSecureCode(): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $block = function () use ($chars) {
            $str = '';
            $max = strlen($chars) - 1;
            for ($i = 0; $i < 4; $i++) {
                $str .= $chars[random_int(0, $max)];
            }
            return $str;
        };

        return sprintf('SAT-%s-%s-%s', $block(), $block(), $block());
    }

    public static function normalizeCode(string $raw): string
    {
        $clean = strtoupper(trim($raw));
        $alphanumeric = preg_replace('/[^A-Z0-9]/', '', $clean);

        if (str_starts_with($alphanumeric, 'SAT') && strlen($alphanumeric) === 15) {
            $p1 = substr($alphanumeric, 3, 4);
            $p2 = substr($alphanumeric, 7, 4);
            $p3 = substr($alphanumeric, 11, 4);
            return "SAT-{$p1}-{$p2}-{$p3}";
        }

        if (strlen($alphanumeric) === 12) {
            $p1 = substr($alphanumeric, 0, 4);
            $p2 = substr($alphanumeric, 4, 4);
            $p3 = substr($alphanumeric, 8, 4);
            return "SAT-{$p1}-{$p2}-{$p3}";
        }

        return $clean;
    }

    public static function getNextSerialSequence(): int
    {
        $maxSerial = DB::table('activation_codes')->max('serial_number');
        $maxFromSerial = 0;
        if ($maxSerial && preg_match('/SAT-(\d+)/', $maxSerial, $matches)) {
            $maxFromSerial = (int) $matches[1];
        }
        $maxId = (int) (DB::table('activation_codes')->max('id') ?? 0);
        return max($maxFromSerial, $maxId) + 1;
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

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ActivationCodeBatch::class, 'batch_id');
    }

    public function generatedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'generated_by_admin_id')
            ->withDefault(function () {
                if ($this->created_by_admin_id) {
                    return Admin::find($this->created_by_admin_id);
                }
                return null;
            });
    }

    public function admin(): BelongsTo
    {
        return $this->generatedByAdmin();
    }

    public function activatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by_user_id')
            ->withDefault(function () {
                if ($this->redeemed_by_user_id) {
                    return User::find($this->redeemed_by_user_id);
                }
                return null;
            });
    }

    public function redeemedByUser(): BelongsTo
    {
        return $this->activatedByUser();
    }

    public function activatedDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'activated_device_id');
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class, 'activation_code_id');
    }

    public function isAvailable(): bool
    {
        $status = $this->status ?? ($this->is_redeemed ? 'ACTIVATED' : 'AVAILABLE');
        if ($status !== ActivationCodeStatus::AVAILABLE->value) {
            return false;
        }

        if ($this->is_redeemed) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    public function isValidForRedemption(): bool
    {
        return $this->isAvailable();
    }

    public function disable(): self
    {
        $this->status = ActivationCodeStatus::DISABLED->value;
        $this->is_active = false;
        $this->save();
        return $this;
    }
}
