<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AdminAuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_id',
        'action',
        'module',
        'target_type',
        'target_id',
        'before_payload',
        'after_payload',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'before_payload' => 'array',
            'after_payload' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AdminAuditLog $log) {
            if (empty($log->module)) {
                $log->module = 'GENERAL';
            }
        });
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
