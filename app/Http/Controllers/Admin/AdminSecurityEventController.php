<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Services\EvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSecurityEventController extends Controller
{
    public function __construct(
        protected EvidenceService $evidenceService
    ) {}

    /**
     * List security events with filtering and search.
     */
    public function events(Request $request): JsonResponse
    {
        $query = SecurityEvent::with([
            'user:id,name,email,phone_number',
            'device:id,device_name,device_model,model,device_uuid',
            'locations',
            'evidence',
            'reviewer:id,name,email',
        ]);

        // Filters
        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        } elseif ($request->filled('type')) {
            $query->where('event_type', $request->input('type'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->input('device_id'));
        }

        if ($request->filled('from_date')) {
            $query->where('occurred_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('occurred_at', '<=', $request->input('to_date'));
        }

        // Search
        if ($request->filled('search') || $request->filled('q')) {
            $search = $request->input('search') ?? $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('event_uuid', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        $events = $query->latest('occurred_at')->paginate($request->input('per_page', 25));

        return $this->successResponse($events);
    }

    /**
     * Show detailed security event.
     */
    public function showEvent(int|string $id): JsonResponse
    {
        $event = SecurityEvent::with([
            'user',
            'device',
            'locations',
            'evidence',
            'reviewer:id,name,email',
        ])->where('id', $id)->orWhere('event_uuid', $id)->first();

        if (! $event) {
            return $this->errorResponse('Security event not found.', null, 404);
        }

        return $this->successResponse([
            'event' => $event,
        ]);
    }

    /**
     * Mark a security event as reviewed by admin.
     */
    public function markReviewed(Request $request, int|string $id): JsonResponse
    {
        $event = SecurityEvent::where('id', $id)->orWhere('event_uuid', $id)->first();

        if (! $event) {
            return $this->errorResponse('Security event not found.', null, 404);
        }

        $admin = $request->user();
        $previousStatus = $event->status;

        $status = $request->input('status', 'REVIEWED');
        $notes = $request->input('notes') ?? $request->input('review_notes');

        $event->update([
            'status' => $status,
            'reviewed_at' => now(),
            'reviewed_by' => $admin?->id,
            'review_notes' => $notes,
        ]);

        // Audit log
        if ($admin) {
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'SECURITY_EVENT_REVIEWED',
                'module' => 'SECURITY_EVENTS',
                'target_type' => SecurityEvent::class,
                'target_id' => $event->id,
                'before_payload' => [
                    'status' => $previousStatus,
                ],
                'after_payload' => [
                    'status' => $event->status,
                    'reviewed_at' => $event->reviewed_at?->toIso8601String(),
                    'review_notes' => $notes,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return $this->successResponse([
            'event' => $event->load(['reviewer', 'locations', 'evidence', 'device', 'user']),
        ], 'Security event marked as reviewed successfully.');
    }

    /**
     * List security evidence items with filters.
     */
    public function evidence(Request $request): JsonResponse
    {
        $query = SecurityEvidence::with(['securityEvent', 'user:id,name,email,phone_number', 'device']);

        if ($request->filled('evidence_type')) {
            $query->where('evidence_type', $request->input('evidence_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->filled('security_event_id')) {
            $query->where('security_event_id', $request->input('security_event_id'));
        }

        if ($request->filled('search') || $request->filled('q')) {
            $search = $request->input('search') ?? $request->input('q');
            $query->where(function ($q) use ($search) {
                $q->where('file_name', 'like', "%{$search}%")
                    ->orWhere('evidence_uuid', 'like', "%{$search}%")
                    ->orWhere('sha256_hash', 'like', "%{$search}%");
            });
        }

        $evidence = $query->latest('captured_at')->paginate($request->input('per_page', 25));

        return $this->successResponse($evidence);
    }

    /**
     * Show specific evidence details.
     */
    public function showEvidence(int|string $id): JsonResponse
    {
        $evidence = SecurityEvidence::with(['securityEvent', 'user', 'device'])
            ->where('id', $id)
            ->orWhere('evidence_uuid', $id)
            ->first();

        if (! $evidence) {
            return $this->errorResponse('Security evidence not found.', null, 404);
        }

        return $this->successResponse([
            'evidence' => $evidence,
        ]);
    }

    /**
     * Stream evidence file to admin with audit logging.
     */
    public function streamEvidence(Request $request, int|string $id)
    {
        $evidence = SecurityEvidence::where('id', $id)->orWhere('evidence_uuid', $id)->first();

        if (! $evidence) {
            return $this->errorResponse('Security evidence not found.', null, 404);
        }

        $admin = $request->user();

        // Audit log
        $this->evidenceService->auditAccess(
            $evidence,
            null,
            $admin,
            'STREAM_ADMIN',
            $request
        );

        if ($admin) {
            AdminAuditLog::create([
                'admin_id' => $admin->id,
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

    public function destroy(Request $request, int|string $id): JsonResponse
    {
        $event = SecurityEvent::where('id', $id)->orWhere('event_uuid', $id)->first();

        if (! $event) {
            return $this->errorResponse('Security event not found.', null, 404);
        }

        $before = $event->only(['id', 'event_type', 'user_id', 'device_id']);
        $event->evidence()->delete();
        $event->locations()->delete();
        $event->delete();

        $admin = $request->user();
        if ($admin) {
            AdminAuditLog::create([
                'admin_id' => $admin->id,
                'action' => 'SECURITY_EVENT_DELETED',
                'module' => 'SECURITY_EVENTS',
                'target_type' => SecurityEvent::class,
                'target_id' => (int) $before['id'],
                'before_payload' => $before,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        }

        return $this->successResponse(null, 'Security event deleted successfully.');
    }
}
