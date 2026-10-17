<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityEventLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'security_event_id',
        'latitude',
        'longitude',
        'accuracy',
        'altitude',
        'speed',
        'heading',
        'address',
        'recorded_at',
        'captured_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (SecurityEventLocation $loc) {
            if (empty($loc->recorded_at) && !empty($loc->captured_at)) {
                $loc->recorded_at = $loc->captured_at;
            } elseif (empty($loc->captured_at) && !empty($loc->recorded_at)) {
                $loc->captured_at = $loc->recorded_at;
            } elseif (empty($loc->recorded_at) && empty($loc->captured_at)) {
                $loc->recorded_at = now();
                $loc->captured_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'altitude' => 'float',
            'speed' => 'float',
            'heading' => 'float',
            'recorded_at' => 'datetime',
            'captured_at' => 'datetime',
        ];
    }

    public function securityEvent(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'security_event_id');
    }
}
