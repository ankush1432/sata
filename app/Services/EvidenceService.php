<?php

namespace App\Services;

use App\Enums\EvidenceType;
use App\Models\Admin;
use App\Models\EvidenceAuditLog;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EvidenceService
{
    /**
     * Allowed MIME types for evidence artifacts.
     */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/webp',
        'audio/wav',
        'audio/x-wav',
        'audio/mpeg',
        'audio/mp3',
        'audio/mp4',
        'audio/x-m4a',
        'video/mp4',
        'video/webm',
        'application/octet-stream', // fallback checked against extension
    ];

    /**
     * Allowed file extensions.
     */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'mp4', 'wav', 'mp3', 'm4a', 'webm',
    ];

    /**
     * Dangerous extensions strictly rejected.
     */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'sh', 'bash', 'exe',
        'bat', 'cmd', 'js', 'html', 'htm', 'cgi', 'pl', 'py', 'jar', 'war',
        'bin', 'com', 'scr', 'vbs', 'ps1',
    ];

    /**
     * Maximum file size in bytes (20MB).
     */
    public const MAX_FILE_SIZE_BYTES = 20971520; // 20MB

    /**
     * Validate an evidence file upload for MIME type, extension, and file size.
     *
     * @throws ValidationException
     */
    public function validateEvidenceFile(UploadedFile $file): void
    {
        $errors = [];

        // Check file size
        $size = $file->getSize();
        if ($size <= 0) {
            $errors['file'][] = 'The uploaded evidence file is empty.';
        } elseif ($size > self::MAX_FILE_SIZE_BYTES) {
            $errors['file'][] = 'The evidence file must not exceed 20MB in size.';
        }

        // Check extension
        $rawExt = strtolower($file->getClientOriginalExtension());
        if (empty($rawExt) || in_array($rawExt, self::DANGEROUS_EXTENSIONS, true)) {
            $errors['file'][] = "The file extension [{$rawExt}] is dangerous or not allowed.";
        } elseif (! in_array($rawExt, self::ALLOWED_EXTENSIONS, true)) {
            $errors['file'][] = "The file extension [{$rawExt}] is not supported. Allowed extensions: " . implode(', ', self::ALLOWED_EXTENSIONS);
        }

        // Check MIME type
        $clientMime = strtolower($file->getClientMimeType() ?: '');
        $detectedMime = strtolower($file->getMimeType() ?: '');

        $dangerousMimes = [
            'application/x-php', 'text/x-php', 'application/php', 'text/php',
            'application/x-sh', 'application/x-shellscript', 'text/x-shellscript',
            'application/x-executable', 'application/x-msdownload', 'application/exe',
            'text/html', 'application/javascript', 'text/javascript', 'application/x-bat',
        ];

        if (in_array($clientMime, $dangerousMimes, true) || in_array($detectedMime, $dangerousMimes, true)) {
            $errors['file'][] = 'The uploaded file type is forbidden.';
        }

        // At least one of clientMime or detectedMime must be in allowed mimes
        $isMimeAllowed = in_array($clientMime, self::ALLOWED_MIMES, true) ||
                         in_array($detectedMime, self::ALLOWED_MIMES, true) ||
                         str_starts_with($detectedMime, 'image/') ||
                         str_starts_with($detectedMime, 'video/') ||
                         str_starts_with($detectedMime, 'audio/');

        if (! $isMimeAllowed) {
            $errors['file'][] = "The mime type [{$detectedMime}] is not allowed for evidence.";
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Store an evidence file securely on the private storage disk.
     */
    public function storeEvidence(
        SecurityEvent $event,
        mixed $arg2,
        mixed $arg3 = null,
        mixed $arg4 = null,
        ?array $metadata = null
    ): SecurityEvidence {
        $user = null;
        if ($arg2 instanceof UploadedFile) {
            $file = $arg2;
            $type = $arg3;
            $metadata = is_array($arg4) ? $arg4 : $metadata;
        } else {
            $user = $arg2;
            $file = $arg3;
            $type = $arg4;
        }

        // Validate file before processing
        $this->validateEvidenceFile($file);

        if (is_string($type)) {
            $type = EvidenceType::tryFrom($type) ?? EvidenceType::FRONT_PHOTO;
        } elseif (! ($type instanceof EvidenceType)) {
            $type = EvidenceType::FRONT_PHOTO;
        }

        $disk = Storage::disk('private_evidence');

        // Path organized by user and event: {user_id}/{event_id}/{uuid}.{ext}
        $userId = $user?->id ?? $event->user_id;
        $rawExt = strtolower($file->getClientOriginalExtension());
        $safeExtension = in_array($rawExt, self::ALLOWED_EXTENSIONS, true) ? $rawExt : 'bin';
        $fileName = Str::uuid()->toString() . '.' . $safeExtension;
        $relativeDir = $userId . '/' . $event->id;
        $relativePath = $relativeDir . '/' . $fileName;

        $fileContents = file_get_contents($file->getRealPath());
        $sha256 = hash('sha256', $fileContents);

        $disk->put($relativePath, $fileContents);

        return SecurityEvidence::create([
            'security_event_id' => $event->id,
            'user_id' => $userId,
            'device_id' => $event->device_id,
            'evidence_type' => $type,
            'storage_disk' => 'private_evidence',
            'storage_path' => $relativePath,
            'file_path' => $relativePath,
            'file_name' => basename($file->getClientOriginalName()),
            'mime_type' => $file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream',
            'file_size' => $file->getSize(),
            'file_size_bytes' => $file->getSize(),
            'checksum' => $sha256,
            'sha256_hash' => $sha256,
            'captured_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get the stream or response for private evidence.
     */
    public function getEvidenceStream(SecurityEvidence $evidence)
    {
        $disk = Storage::disk('private_evidence');
        $path = $evidence->file_path ?? $evidence->storage_path;

        if (! $disk->exists($path)) {
            abort(404, 'Evidence file not found in secure storage.');
        }

        return $disk->response($path, $evidence->file_name, [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => 'inline; filename="' . addslashes($evidence->file_name) . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Sat, 01 Jan 2000 00:00:00 GMT',
        ]);
    }

    /**
     * Record an audit log for evidence access.
     */
    public function auditAccess(
        SecurityEvidence $evidence,
        ?User $user = null,
        ?Admin $admin = null,
        string $action = 'STREAM',
        ?Request $request = null
    ): EvidenceAuditLog {
        return EvidenceAuditLog::create([
            'security_evidence_id' => $evidence->id,
            'user_id' => $user?->id,
            'admin_id' => $admin?->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255),
            'metadata' => [
                'evidence_uuid' => $evidence->evidence_uuid,
                'file_name' => $evidence->file_name,
                'mime_type' => $evidence->mime_type,
            ],
        ]);
    }

    /**
     * Generate a short-lived signed URL for an evidence artifact.
     */
    public function generateSignedUrl(SecurityEvidence $evidence, int $expirationMinutes = 15): string
    {
        $identifier = $evidence->evidence_uuid ?: (string) $evidence->id;

        return URL::temporarySignedRoute(
            'evidence.signed-download',
            now()->addMinutes($expirationMinutes),
            ['id' => $identifier]
        );
    }
}
