<?php

namespace App\Models;

use App\Enums\CommandStatus;
use App\Enums\SecurityCommandType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecurityCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_id',
        'command_uuid',
        'nonce',
        'command_type',
        'status',
        'device_management_mode',
        'payload',
        'requested_by_admin_id',
        'requested_by_type',
        'requested_by_id',
        'issued_at',
        'sent_at',
        'acknowledged_at',
        'executed_at',
        'expires_at',
        'response_payload',
        'failure_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (SecurityCommand $cmd) {
            if (empty($cmd->command_uuid)) {
                $cmd->command_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($cmd->nonce)) {
                $cmd->nonce = \Illuminate\Support\Str::random(32);
            }
            if (empty($cmd->command_type)) {
                $cmd->command_type = SecurityCommandType::KIOSK_LOCK;
            }
            if (empty($cmd->status)) {
                $cmd->status = CommandStatus::PENDING;
            }
            if (empty($cmd->issued_at)) {
                $cmd->issued_at = now();
            }
            if (!empty($cmd->requested_by_admin_id) && empty($cmd->requested_by_id)) {
                $cmd->requested_by_type = 'ADMIN';
                $cmd->requested_by_id = $cmd->requested_by_admin_id;
            }
            if (empty($cmd->expires_at)) {
                $cmd->expires_at = now()->addHours(24);
            }
        });
    }

    public function setCommandTypeAttribute($value): void
    {
        if ($value instanceof \App\Enums\CommandType) {
            $this->attributes['command_type'] = SecurityCommandType::tryFrom($value->value)?->value ?? $value->value;
        } elseif ($value instanceof SecurityCommandType) {
            $this->attributes['command_type'] = $value->value;
        } else {
            $this->attributes['command_type'] = (string) $value;
        }
    }

    protected function casts(): array
    {
        return [
            'command_type' => SecurityCommandType::class,
            'status' => CommandStatus::class,
            'payload' => 'array',
            'response_payload' => 'array',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'executed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Check if command has exceeded its expiration threshold.
     */
    public function isExpired(): bool
    {
        if ($this->status === CommandStatus::EXPIRED) {
            return true;
        }

        return $this->expires_at !== null && now()->greaterThan($this->expires_at);
    }

    /**
     * Terminal states cannot transition to any other status.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [
            CommandStatus::COMPLETED,
            CommandStatus::FAILED,
            CommandStatus::UNSUPPORTED,
            CommandStatus::EXPIRED,
        ], true);
    }

    /**
     * Validate whether a status transition is permitted by Phase 13 lifecycle rules.
     */
    public function canTransitionTo(CommandStatus|string $target): bool
    {
        $targetStatus = $target instanceof CommandStatus ? $target : CommandStatus::tryFrom($target);
        if (!$targetStatus) {
            return false;
        }

        if ($this->isTerminal()) {
            return false;
        }

        $validTransitions = [
            CommandStatus::PENDING->value => [
                CommandStatus::SENT,
                CommandStatus::ACKNOWLEDGED,
                CommandStatus::UNSUPPORTED,
                CommandStatus::FAILED,
                CommandStatus::EXPIRED,
            ],
            CommandStatus::SENT->value => [
                CommandStatus::ACKNOWLEDGED,
                CommandStatus::FAILED,
                CommandStatus::EXPIRED,
            ],
            CommandStatus::ACKNOWLEDGED->value => [
                CommandStatus::EXECUTING,
                CommandStatus::COMPLETED,
                CommandStatus::FAILED,
            ],
            CommandStatus::EXECUTING->value => [
                CommandStatus::COMPLETED,
                CommandStatus::FAILED,
            ],
        ];

        $allowed = $validTransitions[$this->status->value] ?? [];

        return in_array($targetStatus, $allowed, true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function requestedByAdmin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by_admin_id')
            ->withDefault(function () {
                if ($this->requested_by_type === 'ADMIN' && $this->requested_by_id) {
                    return Admin::find($this->requested_by_id);
                }
                return null;
            });
    }

    public function admin(): BelongsTo
    {
        return $this->requestedByAdmin();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(SecurityCommandLog::class, 'security_command_id');
    }

    public function commandLogs(): HasMany
    {
        return $this->hasMany(SecurityCommandLog::class, 'security_command_id');
    }
}
