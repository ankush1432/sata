<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'notification_uuid',
        'user_id',
        'device_id',
        'security_event_id',
        'title',
        'body',
        'type',
        'payload',
        'is_read',
        'read_at',
        'fcm_message_id',
        'status',
        'sent_at',
        'failure_reason',
    ];

    protected static function booted(): void
    {
        static::creating(function (Notification $n) {
            if (empty($n->notification_uuid)) {
                $n->notification_uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'is_read' => 'boolean',
            'read_at' => 'datetime',
            'sent_at' => 'datetime',
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

    public function securityEvent(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'security_event_id');
    }
}
