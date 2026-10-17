<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\SecurityEvent;
use App\Models\SecurityEvidence;
use App\Models\User;
use App\Services\EvidenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_evidence_is_stored_on_private_disk_only(): void
    {
        Storage::fake('private_evidence');

        $user = User::create([
            'name' => 'Evidence User',
            'phone' => '+15552223333',
            'status' => UserStatus::ACTIVE,
        ]);

        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        $file = UploadedFile::fake()->create('intruder_front.jpg', 1024, 'image/jpeg');

        $evidenceService = new EvidenceService();
        $evidence = $evidenceService->storeEvidence($event, $user, $file, EvidenceType::FRONT_PHOTO);

        $this->assertEquals('private_evidence', $evidence->storage_disk);
        $this->assertEquals('image/jpeg', $evidence->mime_type);
        $this->assertGreaterThan(0, $evidence->file_size);
        $this->assertNotNull($evidence->checksum);

        // Verify it exists in the private disk
        Storage::disk('private_evidence')->assertExists($evidence->storage_path);
    }

    public function test_api_upload_and_secure_download(): void
    {
        Storage::fake('private_evidence');

        $user = User::create([
            'name' => 'Evidence User',
            'phone' => '+15552223333',
            'status' => UserStatus::ACTIVE,
        ]);

        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->create('capture.jpg', 512, 'image/jpeg');

        $uploadResponse = $this->postJson("/api/v1/security-events/{$event->id}/evidence", [
            'file' => $file,
            'evidence_type' => EvidenceType::FRONT_PHOTO->value,
        ]);

        $uploadResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'evidence_type' => EvidenceType::FRONT_PHOTO->value,
                    'mime_type' => 'image/jpeg',
                ],
            ]);

        $uuid = $uploadResponse->json('data.evidence_uuid');

        // Test secure download
        $downloadResponse = $this->get("/api/v1/evidence/{$uuid}/download");
        $downloadResponse->assertStatus(200);
        $this->assertEquals('image/jpeg', $downloadResponse->headers->get('content-type'));
        $this->assertStringContainsString('private', $downloadResponse->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', $downloadResponse->headers->get('cache-control'));
    }
}
