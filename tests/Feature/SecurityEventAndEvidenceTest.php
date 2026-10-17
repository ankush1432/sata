<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityEventAndEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_report_security_event_with_location(): void
    {
        $user = User::create([
            'phone_number' => '+15557778888',
            'status' => UserStatus::ACTIVE,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'test-device-uuid-1',
            'device_name' => 'Pixel 9',
            'model' => 'Pixel 9 Pro',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/security-events', [
                'device_uuid' => 'test-device-uuid-1',
                'event_type' => SecurityEventType::INTRUDER_DETECTED->value,
                'severity' => 'CRITICAL',
                'description' => 'Failed unlock attempt with front camera trigger.',
                'latitude' => 37.774929,
                'longitude' => -122.419416,
                'accuracy' => 5.2,
                'metadata' => [
                    'failed_attempts' => 3,
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event' => [
                        'event_type' => SecurityEventType::INTRUDER_DETECTED->value,
                        'severity' => 'CRITICAL',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED->value,
        ]);

        $this->assertDatabaseHas('security_event_locations', [
            'latitude' => 37.774929,
            'longitude' => -122.419416,
        ]);
    }

    public function test_security_events_are_immutable_and_cannot_be_deleted(): void
    {
        $user = User::create([
            'phone_number' => '+15558889999',
            'status' => UserStatus::ACTIVE,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'test-device-uuid-2',
        ]);

        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => SecurityEventType::SIM_CHANGED,
            'triggered_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Security events are immutable audit logs and cannot be deleted.');

        $event->delete();
    }

    public function test_evidence_is_stored_in_private_storage_and_streamed_securely(): void
    {
        Storage::fake('private_evidence');

        $user = User::create([
            'phone_number' => '+15556667777',
            'status' => UserStatus::ACTIVE,
        ]);

        $device = UserDevice::create([
            'user_id' => $user->id,
            'device_uuid' => 'test-device-uuid-3',
        ]);

        $event = SecurityEvent::create([
            'user_id' => $user->id,
            'device_id' => $device->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'triggered_at' => now(),
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $fakePhoto = UploadedFile::fake()->create('intruder.jpg', 100, 'image/jpeg');

        // Upload evidence
        $uploadResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/evidence/upload', [
                'security_event_id' => $event->id,
                'evidence_type' => EvidenceType::FRONT_PHOTO->value,
                'file' => $fakePhoto,
                'metadata' => ['camera' => 'front'],
            ]);

        $uploadResp->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'evidence' => [
                        'evidence_type' => EvidenceType::FRONT_PHOTO->value,
                        'file_name' => 'intruder.jpg',
                    ],
                ],
            ]);

        $evidenceId = $uploadResp->json('data.evidence.id');
        $sha256 = $uploadResp->json('data.evidence.sha256_hash');

        $this->assertNotEmpty($sha256);
        $this->assertDatabaseHas('security_evidence', [
            'id' => $evidenceId,
            'evidence_type' => EvidenceType::FRONT_PHOTO->value,
            'sha256_hash' => $sha256,
        ]);

        // Download/stream evidence
        $downloadResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->get('/api/v1/evidence/' . $evidenceId);

        $downloadResp->assertStatus(200);
        $downloadResp->assertHeader('Content-Disposition', 'inline; filename="intruder.jpg"');
    }
}
