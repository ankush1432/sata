<?php

namespace Tests\Feature;

use App\Enums\SecurityEventType;
use App\Enums\UserStatus;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Models\UserDevice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_event_uuid_is_enforced(): void
    {
        $user = User::create([
            'name' => 'Event User',
            'phone' => '+15551112233',
            'status' => UserStatus::ACTIVE,
        ]);

        $uuid = (string) Str::uuid();

        SecurityEvent::create([
            'event_uuid' => $uuid,
            'user_id' => $user->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        // Duplicate event_uuid must fail
        SecurityEvent::create([
            'event_uuid' => $uuid,
            'user_id' => $user->id,
            'event_type' => SecurityEventType::SIM_CHANGED,
            'occurred_at' => now(),
        ]);
    }

    public function test_all_security_event_types_can_be_stored(): void
    {
        $user = User::create([
            'name' => 'Event User',
            'phone' => '+15551112233',
            'status' => UserStatus::ACTIVE,
        ]);

        foreach (SecurityEventType::cases() as $eventType) {
            $event = SecurityEvent::create([
                'event_uuid' => (string) Str::uuid(),
                'user_id' => $user->id,
                'event_type' => $eventType,
                'severity' => 'HIGH',
                'occurred_at' => now(),
            ]);

            $this->assertEquals($eventType, $event->event_type);
        }

        $this->assertEquals(count(SecurityEventType::cases()), $user->securityEvents()->count());
    }

    public function test_security_event_locations_are_stored_correctly(): void
    {
        $user = User::create([
            'name' => 'Event User',
            'phone' => '+15551112233',
            'status' => UserStatus::ACTIVE,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/security-events', [
            'event_type' => SecurityEventType::MOTION_DETECTED->value,
            'severity' => 'HIGH',
            'latitude' => 37.7749,
            'longitude' => -122.4194,
            'accuracy' => 4.5,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'event_type' => SecurityEventType::MOTION_DETECTED->value,
                    'location' => [
                        'latitude' => 37.7749,
                        'longitude' => -122.4194,
                    ],
                ],
            ]);
    }

    public function test_non_destructive_cascade_prevents_accidental_security_history_wipes(): void
    {
        $user = User::create([
            'name' => 'Audit User',
            'phone' => '+15558887766',
            'status' => UserStatus::ACTIVE,
        ]);

        SecurityEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'event_type' => SecurityEventType::INTRUDER_DETECTED,
            'occurred_at' => now(),
        ]);

        // Trying to delete a user with security events should fail due to restrictOnDelete foreign key
        $this->expectException(QueryException::class);
        $user->delete();
    }
}
