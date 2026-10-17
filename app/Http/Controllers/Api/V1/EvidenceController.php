<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AdminPermission;
use App\Enums\EvidenceType;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EvidenceController extends Controller
{
    public function __construct(
        protected EvidenceService $evidenceService
    ) {}

    /**
     * Upload an evidence artifact for a security event specified in URL parameter.
     */
    public function uploadForEvent(Request $request, int|string $id): JsonResponse
    {
        $request->merge(['security_event_id' => $id]);
        return $this->uploadEvidence($request);
    }

    /**
     * Upload an evidence artifact (photo, audio, screenshot) for a security event.
     * Stored strictly in private storage with safe server-side filename and SHA-256 hash.
     */
    public function uploadEvidence(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'security_event_id' => ['required'],
            'evidence_type' => ['nullable'],
            'file' => ['required', 'file', 'max:20480'], // max 20MB
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $user = $request->user();
        $eventIdentifier = $request->input('security_event_id');

        $event = SecurityEvent::where(function ($q) use ($eventIdentifier) {
            $q->where('id', $eventIdentifier)->orWhere('event_uuid', $eventIdentifier);
        })->first();

        if (! $event) {
            return $this->errorResponse('Security event not found.', null, 404);
        }

        // Ownership validation: authenticated user must own the event unless admin
        if ($user && $event->user_id !== $user->id && ! ($user instanceof Admin)) {
            return $this->errorResponse('Unauthorized to upload evidence for this security event.', null, 403);
        }

        $file = $request->file('file');

        try {
            $this->evidenceService->validateEvidenceFile($file);
        } catch (ValidationException $e) {
            return $this->errorResponse('Evidence file validation failed.', $e->errors(), 422);
        }

        $evidenceType = EvidenceType::tryFrom($request->input('evidence_type')) ?? EvidenceType::FRONT_PHOTO;

        $evidence = $this->evidenceService->storeEvidence(
            $event,
            $user,
            $file,
            $evidenceType,
            $request->input('metadata')
        );

        return $this->successResponse([
            'id' => $evidence->id,
            'evidence_uuid' => $evidence->evidence_uuid,
            'security_event_id' => $evidence->security_event_id,
            'evidence_type' => $evidence->evidence_type->value ?? (string) $evidence->evidence_type,
            'mime_type' => $evidence->mime_type,
            'file_name' => $evidence->file_name,
            'file_size' => $evidence->file_size ?? $evidence->file_size_bytes,
            'storage_path' => $evidence->storage_path,
            'sha256_hash' => $evidence->sha256_hash ?? $evidence->checksum,
            'evidence' => $evidence,
        ], 'Evidence uploaded securely to private storage.', 201);
    }

    /**
     * Generate a short-lived signed URL for an evidence artifact.
     */
    public function generateSignedUrl(Request $request, string $id): JsonResponse
    {
        $evidence = SecurityEvidence::where('id', $id)->orWhere('evidence_uuid', $id)->first();

        if (! $evidence) {
            return $this->errorResponse('Evidence not found.', null, 404);
        }

        $user = $request->user();

        // Authorization check: User must own the evidence, be an admin, or be a verified trusted user
        if (! $this->canAccessEvidence($user, $evidence)) {
            return $this->errorResponse('Unauthorized to access this evidence.', null, 403);
        }

        $minutes = (int) $request->input('expires_in', 15);
        if ($minutes < 1 || $minutes > 60) {
            $minutes = 15;
        }

        $signedUrl = $this->evidenceService->generateSignedUrl($evidence, $minutes);

        // Audit logging
        $this->evidenceService->auditAccess(
            $evidence,
            $user instanceof User ? $user : null,
            $user instanceof Admin ? $user : null,
            'SIGNED_URL_GENERATED',
            $request
        );

        return $this->successResponse([
            'signed_url' => $signedUrl,
            'evidence_uuid' => $evidence->evidence_uuid,
            'expires_in_minutes' => $minutes,
            'expires_at' => now()->addMinutes($minutes)->toIso8601String(),
        ], 'Signed URL generated successfully.');
    }

    /**
     * Stream or download private evidence artifact.
     * Accessible via:
     * 1. Authenticated owner or admin (Sanctum)
     * 2. Short-lived signed URL
     */
    public function downloadEvidence(Request $request, string $id)
    {
        $evidence = SecurityEvidence::where('id', $id)->orWhere('evidence_uuid', $id)->first();

        if (! $evidence) {
            return $this->errorResponse('Evidence not found.', null, 404);
        }

        $user = $request->user();
        $hasSignature = $request->has('signature');

        if ($hasSignature) {
            if (! $request->hasValidSignature()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Signed URL has expired or is invalid.',
                ], 403);
            }

            // Valid signed URL access
            $this->evidenceService->auditAccess(
                $evidence,
                $user instanceof User ? $user : null,
                $user instanceof Admin ? $user : null,
                'SIGNED_URL_ACCESSED',
                $request
            );

            return $this->evidenceService->getEvidenceStream($evidence);
        }

        // Authenticated access check
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized evidence access. Authentication or valid signed URL required.',
            ], 401);
        }

        if (! $this->canAccessEvidence($user, $evidence)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized evidence access. You do not own this evidence artifact.',
            ], 403);
        }

        $isAdmin = $user instanceof Admin;
        $isOwner = $evidence->user_id === $user->id;

        // Audit log access
        $this->evidenceService->auditAccess(
            $evidence,
            $user instanceof User ? $user : null,
            $isAdmin ? $user : null,
            'STREAM',
            $request
        );

        if ($isAdmin) {
            AdminAuditLog::create([
                'admin_id' => $user->id,
                'action' => 'EVIDENCE_VIEWED',
                'module' => 'SECURITY_EVIDENCE',
                'target_type' => SecurityEvidence::class,
                'target_id' => $evidence->id,
                'after_payload' => [
                    'evidence_uuid' => $evidence->evidence_uuid,
                    'file_name' => $evidence->file_name,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return $this->evidenceService->getEvidenceStream($evidence);
    }

    /**
     * Dedicated signed download route handler.
     */
    public function signedDownload(Request $request, string $id)
    {
        return $this->downloadEvidence($request, $id);
    }

    /**
     * Verify if user is owner, admin, or verified trusted user.
     */
    protected function canAccessEvidence(?object $user, SecurityEvidence $evidence): bool
    {
        if (! $user) {
            return false;
        }

        if ($user instanceof Admin) {
            return true;
        }

        if ($user instanceof User && $evidence->user_id === $user->id) {
            return true;
        }

        if ($user instanceof User) {
            $phone = $user->phone_number ?: $user->phone;
            if (! empty($phone)) {
                return \App\Models\TrustedUser::where('user_id', $evidence->user_id)
                    ->where(function ($q) use ($phone) {
                        $q->where('phone', $phone)->orWhere('phone_number', $phone);
                    })
                    ->where('status', \App\Enums\TrustedUserStatus::VERIFIED)
                    ->whereNotNull('verified_at')
                    ->exists();
            }
        }

        return false;
    }
}
