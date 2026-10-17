<?php

namespace App\Models;

use App\Enums\EvidenceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SecurityEvidence extends Model
{
    use HasFactory;

    protected $table = 'security_evidence';

    protected $fillable = [
        'evidence_uuid',
        'security_event_id',
        'user_id',
        'device_id',
        'evidence_type',
        'storage_disk',
        'storage_path',
        'file_path',
        'file_name',
        'mime_type',
        'file_size',
        'file_size_bytes',
        'checksum',
        'sha256_hash',
        'captured_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'evidence_type' => EvidenceType::class,
            'file_size' => 'integer',
            'file_size_bytes' => 'integer',
            'captured_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SecurityEvidence $evidence) {
            if (empty($evidence->evidence_uuid)) {
                $evidence->evidence_uuid = (string) \Illuminate\Support\Str::uuid();
            }
            if (empty($evidence->storage_disk)) {
                $evidence->storage_disk = 'private_evidence';
            }
            if (empty($evidence->evidence_type)) {
                $evidence->evidence_type = EvidenceType::FRONT_PHOTO;
            }
            if (empty($evidence->storage_path) && !empty($evidence->file_path)) {
                $evidence->storage_path = $evidence->file_path;
            } elseif (empty($evidence->file_path) && !empty($evidence->storage_path)) {
                $evidence->file_path = $evidence->storage_path;
            }
            if (empty($evidence->file_size) && !empty($evidence->file_size_bytes)) {
                $evidence->file_size = $evidence->file_size_bytes;
            } elseif (empty($evidence->file_size_bytes) && !empty($evidence->file_size)) {
                $evidence->file_size_bytes = $evidence->file_size;
            }
            if (empty($evidence->checksum) && !empty($evidence->sha256_hash)) {
                $evidence->checksum = $evidence->sha256_hash;
            } elseif (empty($evidence->sha256_hash) && !empty($evidence->checksum)) {
                $evidence->sha256_hash = $evidence->checksum;
            }
            if (empty($evidence->captured_at)) {
                $evidence->captured_at = now();
            }
        });
    }

    public function securityEvent(): BelongsTo
    {
        return $this->belongsTo(SecurityEvent::class, 'security_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'device_id');
    }

    public function getStoragePathAttribute(): ?string
    {
        return $this->attributes['storage_path'] ?? $this->attributes['file_path'] ?? null;
    }

    public function getFilePathAttribute(): ?string
    {
        return $this->attributes['file_path'] ?? $this->attributes['storage_path'] ?? null;
    }

    public function getFileSizeAttribute(): ?int
    {
        return $this->attributes['file_size'] ?? $this->attributes['file_size_bytes'] ?? null;
    }

    public function getChecksumAttribute(): ?string
    {
        return $this->attributes['checksum'] ?? $this->attributes['sha256_hash'] ?? null;
    }

    public function existsInStorage(): bool
    {
        return Storage::disk($this->storage_disk ?: 'private_evidence')->exists($this->storage_path ?: $this->file_path);
    }
}
