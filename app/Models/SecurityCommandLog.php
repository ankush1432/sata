<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityCommandLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_command_id',
        'status_before',
        'status_after',
        'status_from',
        'status_to',
        'message',
        'metadata',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SecurityCommandLog $log) {
            if (empty($log->status_before) && !empty($log->status_from)) {
                $log->status_before = $log->status_from;
            } elseif (empty($log->status_from) && !empty($log->status_before)) {
                $log->status_from = $log->status_before;
            }
            if (empty($log->status_after) && !empty($log->status_to)) {
                $log->status_after = $log->status_to;
            } elseif (empty($log->status_to) && !empty($log->status_after)) {
                $log->status_to = $log->status_after;
            }
            if (empty($log->recorded_at)) {
                $log->recorded_at = now();
            }
        });
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(SecurityCommand::class, 'security_command_id');
    }

    public function securityCommand(): BelongsTo
    {
        return $this->belongsTo(SecurityCommand::class, 'security_command_id');
    }
}
